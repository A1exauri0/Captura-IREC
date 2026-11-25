<?php

namespace App\Http\Controllers;

use App\Models\PcCaptura;
use App\Models\User;
use App\Models\UsuarioEquipo;
use App\Services\StellumBridgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class InventarioEquiposController extends Controller
{
    /**
     * Muestra el listado unificado de equipos ENTREGADOS desde Stellum,
     * permite asignarles Número de PC (solo el número, sin prefijo "PC-")
     * y asignar múltiples equipos por usuario y múltiples usuarios por equipo (rotación por turnos).
     */
    public function index(StellumBridgeService $bridge)
    {
        $usuarios = User::with('turnoRelacion')
            ->orderBy('name')
            ->get(['id', 'name', 'user', 'turno', 'turno_id', 'equipo_no_serie', 'equipo_tipo', 'equipo_tabla', 'equipo_id_stellum', 'equipo_marca', 'equipo_ubicacion']);

        $respStellum = $bridge->obtenerEquiposEntregados();
        $equiposEntregados = collect($respStellum['equipos'] ?? []);
        $conexionStellumOk = (bool) ($respStellum['ok'] ?? false);
        $errorStellum = $respStellum['error'] ?? null;

        // Asignaciones múltiples (muchos a muchos) en usuario_equipos
        $asignaciones = Schema::hasTable('usuario_equipos')
            ? UsuarioEquipo::with(['usuario.turnoRelacion'])->get()
            : collect();

        // Mapa: no_serie => colección de UsuarioEquipo (varios usuarios pueden rotar el mismo equipo)
        $mapaAsignadosPorSerie = $asignaciones->groupBy('no_serie');

        // Mapa: user_id => colección de UsuarioEquipo (un usuario puede tener varios equipos)
        $mapaEquiposPorUsuario = $asignaciones->groupBy('user_id');

        // Mapa rápido: no_serie => registro en pcs_captura (Número de PC asignado)
        $mapaPcsPorSerie = Schema::hasTable('pcs_captura')
            ? PcCaptura::whereNotNull('no_serie')->where('no_serie', '!=', '')->get()->keyBy('no_serie')
            : collect();

        // Ordenar equipos por Número de PC asignado (1, 2, 3... numéricamente); sin número al final
        $equiposEntregados = $equiposEntregados->sort(function ($a, $b) use ($mapaPcsPorSerie) {
            $pcA = $mapaPcsPorSerie->get($a['no_serie'] ?? '')?->numero_pc;
            $pcB = $mapaPcsPorSerie->get($b['no_serie'] ?? '')?->numero_pc;
            $tieneA = $pcA !== null && $pcA !== '';
            $tieneB = $pcB !== null && $pcB !== '';

            if ($tieneA && !$tieneB) return -1;
            if (!$tieneA && $tieneB) return 1;
            if ($tieneA && $tieneB) {
                return strnatcasecmp((string) $pcA, (string) $pcB);
            }
            return 0;
        })->values();

        return view('configuracion.inventario_equipos', compact(
            'usuarios',
            'equiposEntregados',
            'conexionStellumOk',
            'errorStellum',
            'mapaAsignadosPorSerie',
            'mapaEquiposPorUsuario',
            'mapaPcsPorSerie'
        ));
    }

    /**
     * Asigna o actualiza el Número de PC (solo el número, ej. "1", "2", "15") en `pcs_captura`
     * tomando directamente el `no_serie`, marca y ubicación que vienen de Stellum.
     */
    public function asignarNumeroPc(Request $request)
    {
        $data = $request->validate([
            'no_serie'  => 'required|string|max:100',
            'numero_pc' => 'required|string|max:30',
            'marca'     => 'nullable|string|max:100',
            'tipo'      => 'nullable|string|max:40',
            'ubicacion' => 'nullable|string|max:120',
        ]);

        $noSerie = trim($data['no_serie']);
        // Guardar solo el número (quitar prefijo "PC-" si el usuario lo escribió por costumbre)
        $numeroPc = trim((string) preg_replace('/^PC[-\s]*/i', '', trim($data['numero_pc'])));
        if ($numeroPc === '') {
            return back()->with('error', 'Ingresa un número de PC válido.');
        }

        // Impedir repetir un Número de PC que ya pertenece a otro equipo
        $ocupadoPorOtro = PcCaptura::where('numero_pc', $numeroPc)
            ->whereNotNull('no_serie')
            ->where('no_serie', '!=', '')
            ->where('no_serie', '!=', $noSerie)
            ->first();
        if ($ocupadoPorOtro) {
            return back()->with('error', "El número de PC {$numeroPc} ya está asignado al equipo {$ocupadoPorOtro->no_serie}. Elige otro número.");
        }

        $descripcion = trim(($data['tipo'] ?? 'PC') . ' ' . ($data['marca'] ?? ''));

        // Desvincular este no_serie de cualquier otro numero_pc previo
        PcCaptura::where('no_serie', $noSerie)
            ->where('numero_pc', '!=', $numeroPc)
            ->update(['no_serie' => null]);

        PcCaptura::updateOrCreate(
            ['numero_pc' => $numeroPc],
            [
                'no_serie'    => $noSerie,
                'ubicacion'   => $data['ubicacion'] ?? null,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'activo'      => true,
            ]
        );

        // Sincronizar numero_pc en las asignaciones usuario_equipos de ese no_serie
        if (Schema::hasTable('usuario_equipos')) {
            UsuarioEquipo::where('no_serie', $noSerie)->update(['numero_pc' => $numeroPc]);
        }

        return back()->with('success', "Equipo {$noSerie} registrado con número {$numeroPc} en base de datos.");
    }

    /**
     * Registra automáticamente en `pcs_captura` (1, 2, 3...) todos los equipos
     * entregados de Stellum que aún no tengan número de PC asignado.
     */
    public function autoAsignarNumerosPc(StellumBridgeService $bridge)
    {
        $resp = $bridge->obtenerEquiposEntregados();
        if (empty($resp['ok'])) {
            return back()->with('error', 'No se pudo consultar Stellum para sincronizar equipos.');
        }

        $equipos = collect($resp['equipos'] ?? []);
        $seriesYaAsignadas = PcCaptura::whereNotNull('no_serie')
            ->where('no_serie', '!=', '')
            ->pluck('no_serie')
            ->flip();

        $ocupados = PcCaptura::whereNotNull('no_serie')
            ->where('no_serie', '!=', '')
            ->pluck('numero_pc')
            ->flip();

        $asignadosNuevos = 0;
        $contador = 1;

        foreach ($equipos as $eq) {
            $serie = trim((string) ($eq['no_serie'] ?? ''));
            if ($serie === '' || $seriesYaAsignadas->has($serie)) {
                continue;
            }

            // Buscar el siguiente número libre (1, 2, 3...)
            do {
                $candidato = (string) $contador;
                $contador++;
            } while ($ocupados->has($candidato));

            $descripcion = trim((string) ($eq['tipo'] ?? 'PC') . ' ' . (string) ($eq['marca'] ?? ''));

            PcCaptura::updateOrCreate(
                ['numero_pc' => $candidato],
                [
                    'no_serie'    => $serie,
                    'ubicacion'   => $eq['ubicacion'] ?? null,
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                    'activo'      => true,
                ]
            );

            if (Schema::hasTable('usuario_equipos')) {
                UsuarioEquipo::where('no_serie', $serie)->update(['numero_pc' => $candidato]);
            }

            $ocupados->put($candidato, true);
            $seriesYaAsignadas->put($serie, true);
            $asignadosNuevos++;
        }

        return back()->with('success', $asignadosNuevos > 0
            ? "Se asignó número de PC automáticamente a {$asignadosNuevos} equipo(s) desde Stellum."
            : 'Todos los equipos entregados de Stellum ya tienen un Número de PC asignado.');
    }

    /**
     * Proxy transparente hacia Stellum (/api/captura-bridge/equipos/api).
     * Permite leer y escribir exactamente las mismas tablas de Stellum en tiempo real.
     */
    public function apiProxy(Request $request, StellumBridgeService $bridge)
    {
        $resultado = $bridge->proxyEquipos(
            $request->method(),
            $request->query(),
            $request->isMethod('post') ? $request->all() : []
        );

        return response()->json($resultado['body'], $resultado['status']);
    }

    /**
     * Descarga el Excel multi-hoja directamente desde Stellum.
     */
    public function exportarExcel(StellumBridgeService $bridge)
    {
        try {
            $resp = $bridge->descargarExcelEquipos();
            if (!$resp->successful()) {
                return back()->with('error', 'No se pudo descargar el Excel desde Stellum (HTTP ' . $resp->status() . ').');
            }

            $filename = 'Inventario_Equipos_Stellum_' . date('Ymd_His') . '.xlsx';
            return response($resp->body(), 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al conectar con Stellum para exportar Excel: ' . $e->getMessage());
        }
    }

    /**
     * Asigna un equipo de Stellum a uno o varios usuarios (rotación por turnos) sin desvincular
     * otros equipos del usuario ni otros usuarios del mismo equipo.
     */
    public function asignarUsuario(Request $request, StellumBridgeService $bridge)
    {
        $data = $request->validate([
            'user_ids'          => 'nullable|array',
            'user_ids.*'        => 'exists:users,id',
            'user_id'           => 'nullable|exists:users,id',
            'equipo_no_serie'   => 'required|string|max:100',
            'equipo_tipo'       => 'nullable|string|max:30',
            'equipo_tabla'      => 'nullable|string|max:30',
            'equipo_id_stellum' => 'nullable|integer',
            'equipo_marca'      => 'nullable|string|max:100',
            'equipo_ubicacion'  => 'nullable|string|max:100',
        ]);

        $idsUsuarios = collect($data['user_ids'] ?? [])
            ->when(!empty($data['user_id']), fn ($c) => $c->push($data['user_id']))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($idsUsuarios->isEmpty()) {
            return back()->with('error', 'Selecciona al menos un usuario para asignar el equipo.');
        }

        $serie = trim($data['equipo_no_serie']);
        $numPcLocal = Schema::hasTable('pcs_captura')
            ? PcCaptura::where('no_serie', $serie)->value('numero_pc')
            : null;

        $usuariosAsignados = User::whereIn('id', $idsUsuarios)->get();
        foreach ($usuariosAsignados as $usuario) {
            UsuarioEquipo::updateOrCreate(
                ['user_id' => $usuario->id, 'no_serie' => $serie],
                [
                    'numero_pc'         => $numPcLocal,
                    'tipo'              => $data['equipo_tipo'] ?: 'PC',
                    'marca'             => $data['equipo_marca'] ?? null,
                    'ubicacion'         => $data['equipo_ubicacion'] ?? null,
                    'tabla_origen'      => $data['equipo_tabla'] ?: 'computadoras',
                    'equipo_id_stellum' => $data['equipo_id_stellum'] ?? null,
                ]
            );

            // Mantener equipo_no_serie principal actualizado en users por compatibilidad
            if (empty($usuario->equipo_no_serie)) {
                $usuario->update([
                    'equipo_no_serie'   => $serie,
                    'equipo_tipo'       => $data['equipo_tipo'] ?: 'PC',
                    'equipo_tabla'      => $data['equipo_tabla'] ?: 'computadoras',
                    'equipo_id_stellum' => $data['equipo_id_stellum'] ?? null,
                    'equipo_marca'      => $data['equipo_marca'] ?? null,
                    'equipo_ubicacion'  => $data['equipo_ubicacion'] ?? null,
                ]);
            }
        }

        // Sincronizar también en Stellum (campo prestado_a con todos los usuarios asignados a ese equipo)
        if (!empty($data['equipo_id_stellum']) && !empty($data['equipo_tabla'])) {
            $todosNombres = UsuarioEquipo::with('usuario')
                ->where('no_serie', $serie)
                ->get()
                ->map(fn ($ue) => $ue->usuario?->name)
                ->filter()
                ->unique()
                ->implode(', ');

            $bridge->proxyEquipos('POST', ['accion' => 'actualizar_prestamo'], [
                'accion'       => 'actualizar_prestamo',
                'id'           => (int) $data['equipo_id_stellum'],
                'tabla_origen' => $data['equipo_tabla'],
                'prestado_a'   => $todosNombres,
            ]);
        }

        return back()->with('success', "Equipo {$serie} asignado correctamente (" . $usuariosAsignados->count() . " usuario(s)).");
    }

    /**
     * Quita una asignación específica (o todas si no se pasa asignacionId) de un usuario
     * y actualiza `prestado_a` en Stellum con los usuarios restantes de ese equipo.
     */
    public function desasignarUsuario(int $userId, ?int $asignacionId = null, StellumBridgeService $bridge = null)
    {
        $bridge = $bridge ?: app(StellumBridgeService::class);
        $usuario = User::findOrFail($userId);

        $asignacion = $asignacionId
            ? UsuarioEquipo::where('id', $asignacionId)->where('user_id', $userId)->first()
            : UsuarioEquipo::where('user_id', $userId)->first();

        $serie = $asignacion?->no_serie ?: $usuario->equipo_no_serie;
        $idStellum = $asignacion?->equipo_id_stellum ?: $usuario->equipo_id_stellum;
        $tablaStellum = $asignacion?->tabla_origen ?: $usuario->equipo_tabla;

        if ($asignacion) {
            $asignacion->delete();
        } else {
            UsuarioEquipo::where('user_id', $userId)->delete();
        }

        // Actualizar equipo principal en users con otro equipo restante del usuario (si tiene)
        $otroEquipo = UsuarioEquipo::where('user_id', $userId)->latest('id')->first();
        $usuario->update([
            'equipo_no_serie'   => $otroEquipo?->no_serie,
            'equipo_tipo'       => $otroEquipo?->tipo,
            'equipo_tabla'      => $otroEquipo?->tabla_origen,
            'equipo_id_stellum' => $otroEquipo?->equipo_id_stellum,
            'equipo_marca'      => $otroEquipo?->marca,
            'equipo_ubicacion'  => $otroEquipo?->ubicacion,
        ]);

        if ($idStellum && $tablaStellum && $serie) {
            $restantes = UsuarioEquipo::with('usuario')
                ->where('no_serie', $serie)
                ->get()
                ->map(fn ($ue) => $ue->usuario?->name)
                ->filter()
                ->unique()
                ->implode(', ');

            $bridge->proxyEquipos('POST', ['accion' => 'actualizar_prestamo'], [
                'accion'       => 'actualizar_prestamo',
                'id'           => (int) $idStellum,
                'tabla_origen' => $tablaStellum,
                'prestado_a'   => $restantes,
            ]);
        }

        return back()->with('success', "Se liberó el equipo {$serie} del usuario {$usuario->name}.");
    }
}
