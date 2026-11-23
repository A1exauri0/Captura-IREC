<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TurnoController extends Controller
{
    public function index()
    {
        $turnos = Turno::withCount('usuarios')->orderBy('id')->get();
        $usuarios = User::with('turnoRelacion')->orderBy('name')->get();

        return view('admin.turnos.index', compact('turnos', 'usuarios'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'       => 'required|string|max:80|unique:turnos,nombre',
            'hora_entrada' => 'required',
            'hora_salida'  => 'required',
            'color_hex'    => 'nullable|string|max:20',
            'descripcion'  => 'nullable|string|max:255',
        ]);

        $cruzaMedianoche = strtotime($data['hora_salida']) <= strtotime($data['hora_entrada']);

        Turno::create([
            'nombre'           => trim($data['nombre']),
            'hora_entrada'     => $data['hora_entrada'],
            'hora_salida'      => $data['hora_salida'],
            'cruza_medianoche' => $cruzaMedianoche,
            'color_hex'        => $data['color_hex'] ?: '#034d6d',
            'descripcion'      => $data['descripcion'],
            'estatus'          => true,
        ]);

        Cache::forget('mapa_turnos_adaptativos');

        return redirect()->route('admin.turnos.index')->with('success', 'Turno adaptativo creado exitosamente.');
    }

    public function update(Request $request, $id)
    {
        $turno = Turno::findOrFail($id);

        $data = $request->validate([
            'nombre'       => 'required|string|max:80|unique:turnos,nombre,' . $turno->id,
            'hora_entrada' => 'required',
            'hora_salida'  => 'required',
            'color_hex'    => 'nullable|string|max:20',
            'descripcion'  => 'nullable|string|max:255',
            'estatus'      => 'nullable|boolean',
        ]);

        $cruzaMedianoche = strtotime($data['hora_salida']) <= strtotime($data['hora_entrada']);

        $turno->update([
            'nombre'           => trim($data['nombre']),
            'hora_entrada'     => $data['hora_entrada'],
            'hora_salida'      => $data['hora_salida'],
            'cruza_medianoche' => $cruzaMedianoche,
            'color_hex'        => $data['color_hex'] ?: $turno->color_hex,
            'descripcion'      => $data['descripcion'],
            'estatus'          => $request->has('estatus') ? (bool) $request->estatus : $turno->estatus,
        ]);

        // Sincronizar el nombre del turno en los usuarios asociados
        User::where('turno_id', $turno->id)->update(['turno' => $turno->nombre]);

        Cache::forget('mapa_turnos_adaptativos');

        return redirect()->route('admin.turnos.index')->with('success', "Turno '{$turno->nombre}' actualizado correctamente.");
    }

    public function destroy($id)
    {
        $turno = Turno::findOrFail($id);
        $turno->delete();
        Cache::forget('mapa_turnos_adaptativos');

        return redirect()->route('admin.turnos.index')->with('success', 'Turno eliminado correctamente.');
    }

    /**
     * Asigna un turno adaptativo a uno o varios usuarios capturistas.
     */
    public function asignarUsuarios(Request $request)
    {
        $request->validate([
            'turno_id' => 'required|exists:turnos,id',
            'user_ids' => 'required|array',
        ]);

        $turno = Turno::findOrFail($request->turno_id);

        User::whereIn('id', $request->user_ids)->update([
            'turno_id' => $turno->id,
            'turno'    => $turno->nombre,
        ]);

        return redirect()->route('admin.turnos.index')->with('success', count($request->user_ids) . " capturista(s) asignados al turno '{$turno->nombre}'.");
    }
}
