<?php

namespace App\Http\Controllers;

use App\Models\Delegaciones;
use App\Models\Municipios;
use App\Models\Localidades;
use App\Models\CatalogoInconsistencia;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    /**
     * Vista de Rutas PDF de Entregas configuradas (config/captura_pdf.php).
     */
    public function index()
    {
        $rutasPorTipo = config('captura_pdf.rutas_por_tipo', []);
        $tiposDoc = config('captura_pdf.tipos_documento', []);

        return view('configuracion.catalogos_rutas', compact('rutasPorTipo', 'tiposDoc'));
    }

    // =========================================================================
    // CRUD DELEGACIONES
    // =========================================================================

    public function delegaciones()
    {
        $delegaciones = Delegaciones::orderBy('id')->get();
        return view('configuracion.delegaciones_index', compact('delegaciones'));
    }

    public function storeDelegacion(Request $request)
    {
        $data = $request->validate([
            'nombre'  => 'required|string|max:150',
            'clave'   => 'required|string|max:30|unique:delegaciones,clave',
            'nota'    => 'nullable|string|max:255',
            'estatus' => 'nullable|boolean',
        ]);

        Delegaciones::create([
            'nombre'  => trim($data['nombre']),
            'clave'   => trim($data['clave']),
            'nota'    => $data['nota'] ?? null,
            'estatus' => $request->has('estatus') ? (bool) $request->estatus : true,
        ]);

        return redirect()->route('admin.delegaciones.index')
            ->with('success', "Delegación '{$data['nombre']}' creada correctamente.");
    }

    public function updateDelegacion(Request $request, $id)
    {
        $delegacion = Delegaciones::findOrFail($id);

        $data = $request->validate([
            'nombre'  => 'required|string|max:150',
            'clave'   => 'required|string|max:30|unique:delegaciones,clave,' . $delegacion->id,
            'nota'    => 'nullable|string|max:255',
            'estatus' => 'nullable|boolean',
        ]);

        $delegacion->update([
            'nombre'  => trim($data['nombre']),
            'clave'   => trim($data['clave']),
            'nota'    => $data['nota'] ?? null,
            'estatus' => $request->has('estatus') ? (bool) $request->estatus : $delegacion->estatus,
        ]);

        return redirect()->route('admin.delegaciones.index')
            ->with('success', "Delegación '{$delegacion->nombre}' actualizada correctamente.");
    }

    public function destroyDelegacion($id)
    {
        $delegacion = Delegaciones::findOrFail($id);
        $nombre = $delegacion->nombre;
        $delegacion->delete();

        return redirect()->route('admin.delegaciones.index')
            ->with('success', "Delegación '{$nombre}' eliminada correctamente.");
    }

    // =========================================================================
    // CRUD MUNICIPIOS Y LOCALIDADES
    // =========================================================================

    public function municipios(Request $request)
    {
        $municipios = Municipios::withCount('localidades')->orderBy('clave')->get();
        $municipioSeleccionado = $request->query('municipio_id');
        $municipioActual = $municipioSeleccionado ? Municipios::find($municipioSeleccionado) : null;
        $busquedaLoc = trim((string) $request->query('q', ''));

        $localidades = collect();
        if ($municipioActual) {
            $queryLoc = Localidades::where('municipio', $municipioActual->id)->orderBy('clave');
            if ($busquedaLoc !== '') {
                $queryLoc->where(function ($q) use ($busquedaLoc) {
                    $q->where('nombre', 'like', "%{$busquedaLoc}%")
                      ->orWhere('clave', 'like', "%{$busquedaLoc}%");
                });
            }
            $localidades = $queryLoc->paginate(50)->withQueryString();
        }

        return view('configuracion.municipios_localidades_index', compact(
            'municipios',
            'municipioSeleccionado',
            'municipioActual',
            'localidades',
            'busquedaLoc'
        ));
    }

    public function storeMunicipio(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'clave'  => 'required|string|max:20',
            'estado' => 'nullable|string|max:10',
        ]);

        $municipio = Municipios::create([
            'estado' => $data['estado'] ?: '07',
            'nombre' => trim($data['nombre']),
            'clave'  => trim($data['clave']),
        ]);

        return redirect()->route('admin.municipios.index', ['municipio_id' => $municipio->id])
            ->with('success', "Municipio '{$municipio->nombre}' creado correctamente.");
    }

    public function updateMunicipio(Request $request, $id)
    {
        $municipio = Municipios::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'clave'  => 'required|string|max:20',
            'estado' => 'nullable|string|max:10',
        ]);

        $municipio->update([
            'estado' => $data['estado'] ?: $municipio->estado,
            'nombre' => trim($data['nombre']),
            'clave'  => trim($data['clave']),
        ]);

        return redirect()->route('admin.municipios.index', ['municipio_id' => $municipio->id])
            ->with('success', "Municipio '{$municipio->nombre}' actualizado.");
    }

    public function destroyMunicipio($id)
    {
        $municipio = Municipios::findOrFail($id);
        $nombre = $municipio->nombre;
        Localidades::where('municipio', $municipio->id)->delete();
        $municipio->delete();

        return redirect()->route('admin.municipios.index')
            ->with('success', "Municipio '{$nombre}' y sus localidades fueron eliminados.");
    }

    public function storeLocalidad(Request $request)
    {
        $data = $request->validate([
            'municipio' => 'required|exists:municipios,id',
            'nombre'    => 'required|string|max:150',
            'clave'     => 'required|string|max:20',
        ]);

        Localidades::create([
            'municipio' => $data['municipio'],
            'nombre'    => trim($data['nombre']),
            'clave'     => trim($data['clave']),
        ]);

        return redirect()->route('admin.municipios.index', ['municipio_id' => $data['municipio']])
            ->with('success', "Localidad '{$data['nombre']}' agregada correctamente.");
    }

    public function updateLocalidad(Request $request, $id)
    {
        $localidad = Localidades::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'clave'  => 'required|string|max:20',
        ]);

        $localidad->update([
            'nombre' => trim($data['nombre']),
            'clave'  => trim($data['clave']),
        ]);

        return redirect()->route('admin.municipios.index', ['municipio_id' => $localidad->municipio])
            ->with('success', "Localidad '{$localidad->nombre}' actualizada.");
    }

    public function destroyLocalidad($id)
    {
        $localidad = Localidades::findOrFail($id);
        $municipioId = $localidad->municipio;
        $nombre = $localidad->nombre;
        $localidad->delete();

        return redirect()->route('admin.municipios.index', ['municipio_id' => $municipioId])
            ->with('success', "Localidad '{$nombre}' eliminada.");
    }

    // =========================================================================
    // CRUD CATÁLOGO DE INCONSISTENCIAS (R01-R09)
    // =========================================================================

    public function catalogoInconsistencias()
    {
        $items = CatalogoInconsistencia::orderBy('codigo')->get();
        return view('configuracion.catalogo_inconsistencias', compact('items'));
    }

    public function storeCatalogoInconsistencia(Request $request)
    {
        $data = $request->validate([
            'codigo'            => 'required|string|max:10|unique:catalogo_inconsistencias,codigo',
            'motivo_rechazo'    => 'required|string|max:255',
            'accion_correctiva' => 'required|string|max:255',
        ]);

        CatalogoInconsistencia::create([
            'codigo'            => strtoupper(trim($data['codigo'])),
            'motivo_rechazo'    => trim($data['motivo_rechazo']),
            'accion_correctiva' => trim($data['accion_correctiva']),
            'activo'            => true,
        ]);

        return redirect()->route('admin.catalogo_inconsistencias.index')
            ->with('success', "Código {$data['codigo']} agregado al catálogo.");
    }

    public function updateCatalogoInconsistencia(Request $request, int $id)
    {
        $item = CatalogoInconsistencia::findOrFail($id);

        $data = $request->validate([
            'codigo'            => 'required|string|max:10|unique:catalogo_inconsistencias,codigo,' . $item->id,
            'motivo_rechazo'    => 'required|string|max:255',
            'accion_correctiva' => 'required|string|max:255',
            'activo'            => 'nullable|boolean',
        ]);

        $item->update([
            'codigo'            => strtoupper(trim($data['codigo'])),
            'motivo_rechazo'    => trim($data['motivo_rechazo']),
            'accion_correctiva' => trim($data['accion_correctiva']),
            'activo'            => $request->has('activo') ? (bool) $request->activo : $item->activo,
        ]);

        return redirect()->route('admin.catalogo_inconsistencias.index')
            ->with('success', "Código {$item->codigo} actualizado.");
    }

    public function destroyCatalogoInconsistencia(int $id)
    {
        $item = CatalogoInconsistencia::findOrFail($id);
        $codigo = $item->codigo;
        $item->delete();

        return redirect()->route('admin.catalogo_inconsistencias.index')
            ->with('success', "Código {$codigo} eliminado del catálogo.");
    }
}
