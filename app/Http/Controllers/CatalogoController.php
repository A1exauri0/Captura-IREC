<?php

namespace App\Http\Controllers;

use App\Models\Delegaciones;
use App\Models\Municipios;
use App\Models\Localidades;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function index(Request $request)
    {
        $delegaciones = Delegaciones::orderBy('id')->get();
        $municipios = Municipios::withCount('localidades')->orderBy('clave')->get();
        $rutasPorTipo = config('captura_pdf.rutas_por_tipo', []);
        $tiposDoc = config('captura_pdf.tipos_documento', []);

        $municipioSeleccionado = $request->query('municipio_id');
        $localidades = collect();
        if ($municipioSeleccionado) {
            $localidades = Localidades::where('municipio', $municipioSeleccionado)
                ->orderBy('clave')
                ->limit(500)
                ->get();
        }

        return view('configuracion.catalogos_rutas', compact(
            'delegaciones',
            'municipios',
            'localidades',
            'municipioSeleccionado',
            'rutasPorTipo',
            'tiposDoc'
        ));
    }
}
