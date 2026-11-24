<?php

namespace App\Http\Controllers;

use App\Models\PcCaptura;
use Illuminate\Http\Request;

class PcCapturaController extends Controller
{
    public function index()
    {
        $pcs = PcCaptura::orderBy('numero_pc')->get();
        return view('configuracion.pcs_captura', compact('pcs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'numero_pc'   => 'required|string|max:30|unique:pcs_captura,numero_pc',
            'no_serie'    => 'nullable|string|max:100',
            'ubicacion'   => 'nullable|string|max:120',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $data['numero_pc'] = strtoupper(trim($data['numero_pc']));
        $data['activo'] = true;

        PcCaptura::create($data);
        return back()->with('success', "Equipo {$data['numero_pc']} registrado correctamente.");
    }

    public function update(Request $request, $id)
    {
        $pc = PcCaptura::findOrFail($id);
        $data = $request->validate([
            'numero_pc'   => 'required|string|max:30|unique:pcs_captura,numero_pc,' . $pc->id,
            'no_serie'    => 'nullable|string|max:100',
            'ubicacion'   => 'nullable|string|max:120',
            'descripcion' => 'nullable|string|max:255',
            'activo'      => 'nullable|boolean',
        ]);

        $pc->update([
            'numero_pc'   => strtoupper(trim($data['numero_pc'])),
            'no_serie'    => $data['no_serie'] ?? null,
            'ubicacion'   => $data['ubicacion'] ?? null,
            'descripcion' => $data['descripcion'] ?? null,
            'activo'      => $request->has('activo') ? (bool) $request->input('activo') : $pc->activo,
        ]);

        return back()->with('success', "Equipo {$pc->numero_pc} actualizado.");
    }

    public function destroy($id)
    {
        $pc = PcCaptura::findOrFail($id);
        $nombre = $pc->numero_pc;
        $pc->delete();
        return back()->with('success', "Equipo {$nombre} eliminado.");
    }
}
