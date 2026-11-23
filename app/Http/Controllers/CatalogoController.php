<?php

namespace App\Http\Controllers;

use App\Models\Delegaciones;
use App\Models\StellMunicipios;
use App\Models\StellLocalidades;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function index(Request $request)
    {
        $delegaciones = Delegaciones::orderBy('id')->get();
        $municipios = StellMunicipios::withCount('localidades')->orderBy('clave')->get();
        $rutasPorTipo = config('captura_pdf.rutas_por_tipo', []);
        $tiposDoc = config('captura_pdf.tipos_documento', []);

        $municipioSeleccionado = $request->query('municipio_id');
        $localidades = collect();
        if ($municipioSeleccionado) {
            $localidades = StellLocalidades::where('municipio', $municipioSeleccionado)
                ->orderBy('clave')
                ->limit(500)
                ->get();
        }

        return view('admin.catalogos.index', compact(
            'delegaciones',
            'municipios',
            'localidades',
            'municipioSeleccionado',
            'rutasPorTipo',
            'tiposDoc'
        ));
    }
}
