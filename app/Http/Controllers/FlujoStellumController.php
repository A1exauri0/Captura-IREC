<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarOrdenStellumJob;
use App\Models\AsignacionCapturaUsuario;
use App\Models\CatalogoInconsistencia;
use App\Models\Delegaciones;
use App\Models\OrdenAplicacionStellum;
use App\Models\User;
use App\Services\StellumBridgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FlujoStellumController extends Controller
{
    private array $mapaTablas = [
        'registros' => 'auditoria_registros',
        'sellos'    => 'auditoria_sellos',
        'notarias'  => 'auditoria_notarias',
        'catastro'  => 'auditoria_catastro',
    ];

    // =========================================================================
    // 1. VISTA DE REVISIÓN DE INCONSISTENCIAS (R01-R09)
    // =========================================================================

    public function inconsistencias(Request $request)
    {
        $tipo     = $request->query('tipo', 'sellos');
        $codigo   = $request->query('codigo');
        $estado   = $request->query('estado');
        $tabla    = $this->mapaTablas[$tipo] ?? 'auditoria_registros';

        $query = DB::table($tabla . ' as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('catalogo_inconsistencias as ci', 'ci.codigo', '=', 'a.inconsistencia_codigo')
            ->select(
                'a.*',
                'u.name as usuario_nombre',
                'u.user as usuario_login',
                'ci.motivo_rechazo',
                'ci.accion_correctiva'
            )
            ->where(function ($q) {
                $q->whereNotNull('a.inconsistencia_codigo')
                  ->orWhereRaw("LOWER(COALESCE(a.accion, '')) = 'inconsistencia'");
            })
            ->orderByDesc('a.id');

        if (!empty($codigo)) {
            $query->where('a.inconsistencia_codigo', $codigo);
        }
        if (!empty($estado)) {
            $query->where('a.inconsistencia_estado', $estado);
        }

        $registros = $query->paginate(40)->withQueryString();
        $catalogo  = CatalogoInconsistencia::where('activo', true)->orderBy('codigo')->get();

        // Contadores globales de inconsistencias pendientes por tabla
        $contadores = [];
        foreach ($this->mapaTablas as $key => $tbl) {
            $contadores[$key] = DB::table($tbl)
                ->where(function ($q) {
                    $q->whereNotNull('inconsistencia_codigo')
                      ->orWhereRaw("LOWER(COALESCE(accion, '')) = 'inconsistencia'");
                })
                ->where(function ($q) {
                    $q->whereNull('inconsistencia_estado')
                      ->orWhere('inconsistencia_estado', 'pendiente');
                })
                ->count();
        }

        return view('monitoreo.revision_inconsistencias', compact(
            'registros', 'catalogo', 'tipo', 'codigo', 'estado', 'contadores'
        ));
    }

    public function actualizarInconsistencia(Request $request, string $tipo, int $id)
    {
        $tabla = $this->mapaTablas[$tipo] ?? null;
        if (!$tabla) {
            return back()->with('error', 'Tipo documental no válido.');
        }

        $data = $request->validate([
            'inconsistencia_estado'     => 'required|in:pendiente,en_revision,reescaneo_solicitado,resuelta',
            'inconsistencia_comentario' => 'nullable|string|max:500',
        ]);

        DB::table($tabla)->where('id', $id)->update([
            'inconsistencia_estado'     => $data['inconsistencia_estado'],
            'inconsistencia_comentario' => $data['inconsistencia_comentario'] ?? null,
            'updated_at'                => now(),
        ]);

        return back()->with('success', "Inconsistencia #{$id} actualizada a '{$data['inconsistencia_estado']}'.");
    }

    // =========================================================================
    // 2. VISTA DE RESOLUCIÓN DE COLISIONES (revisar = 1)
    // =========================================================================

    public function colisiones(Request $request)
    {
        $tipo     = $request->query('tipo', 'sellos');
        $decision = $request->query('decision', 'pendiente');
        $tabla    = $this->mapaTablas[$tipo] ?? 'auditoria_registros';

        $query = DB::table($tabla . ' as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->select('a.*', 'u.name as usuario_nombre', 'u.user as usuario_login')
            ->where('a.revisar', true)
            ->orderByDesc('a.id');

        if ($decision === 'pendiente') {
            $query->where(function ($q) {
                $q->whereNull('a.decision_revision')->orWhere('a.decision_revision', 'pendiente');
            });
        } elseif ($decision !== 'todas') {
            $query->where('a.decision_revision', $decision);
        }

        $registros = $query->paginate(40)->withQueryString();

        $contadores = [];
        foreach ($this->mapaTablas as $key => $tbl) {
            $contadores[$key] = DB::table($tbl)
                ->where('revisar', true)
                ->where(function ($q) {
                    $q->whereNull('decision_revision')->orWhere('decision_revision', 'pendiente');
                })
                ->count();
        }

        return view('monitoreo.resolucion_colisiones', compact(
            'registros', 'tipo', 'decision', 'contadores'
        ));
    }

    public function resolverColision(Request $request, string $tipo, int $id)
    {
        $tabla = $this->mapaTablas[$tipo] ?? null;
        if (!$tabla) {
            return back()->with('error', 'Tipo documental no válido.');
        }

        $data = $request->validate([
            'decision_revision' => 'required|in:conservar_nuevo,conservar_existente,conservar_ambos',
            'nota'              => 'nullable|string|max:300',
        ]);

        $actual = DB::table($tabla)->where('id', $id)->first();
        if (!$actual) {
            return back()->with('error', 'Registro no encontrado.');
        }

        $detalles = trim((string) $actual->detalles);
        $etiqueta = match ($data['decision_revision']) {
            'conservar_nuevo'     => '[REVISADO: Conservar archivo nuevo y marcar existente en entregables para eliminar]',
            'conservar_existente' => '[REVISADO: Conservar archivo existente en entregables y descartar/eliminar el capturado]',
            'conservar_ambos'     => '[REVISADO: Conservar ambos archivos]',
        };
        if (!empty($data['nota'])) {
            $etiqueta .= ' Nota: ' . $data['nota'];
        }

        DB::table($tabla)->where('id', $id)->update([
            'decision_revision' => $data['decision_revision'],
            'revisar'           => false,
            'detalles'          => trim($detalles . ' ' . $etiqueta),
            'updated_at'        => now(),
        ]);

        return back()->with('success', "Colisión #{$id} resuelta: {$data['decision_revision']}.");
    }

    // =========================================================================
    // 3. VISTA DE APROBACIÓN MASIVA DE RENOMBRES HACIA STELLUM (MILLONES DE PDFs)
    // =========================================================================

    public function aprobacionRenombres(Request $request)
    {
        $tipo = $request->query('tipo', 'sellos');
        $tabla = $this->mapaTablas[$tipo] ?? 'auditoria_sellos';

        $tieneDelegacion = in_array($tipo, ['registros', 'sellos']);
        $tieneTipoSello  = ($tipo === 'sellos');

        if ($tieneDelegacion) {
            $select = [
                'a.delegacion_id',
                DB::raw('COALESCE(d.nombre, "Sin Delegación") as delegacion_nombre'),
                DB::raw($tieneTipoSello ? 'COALESCE(NULLIF(a.tipo_sello, ""), "General") as tipo_sello_grupo' : '"General" as tipo_sello_grupo'),
                DB::raw("SUM(CASE WHEN a.estado_aplicacion = 'pendiente' AND a.revisar = 0 AND COALESCE(a.inconsistencia_codigo, '') = '' THEN 1 ELSE 0 END) as listos_aprobar"),
                DB::raw("SUM(CASE WHEN a.revisar = 1 THEN 1 ELSE 0 END) as con_colision"),
                DB::raw("SUM(CASE WHEN a.inconsistencia_codigo IS NOT NULL AND COALESCE(a.inconsistencia_estado, 'pendiente') != 'resuelta' THEN 1 ELSE 0 END) as con_inconsistencia"),
                DB::raw("SUM(CASE WHEN a.estado_aplicacion IN ('aprobado', 'enviado_stellum') THEN 1 ELSE 0 END) as en_proceso"),
                DB::raw("SUM(CASE WHEN a.estado_aplicacion = 'aplicado' THEN 1 ELSE 0 END) as aplicados"),
                DB::raw('COUNT(*) as total'),
            ];

            $groupBy = $tieneTipoSello
                ? ['a.delegacion_id', 'd.nombre', DB::raw('COALESCE(NULLIF(a.tipo_sello, ""), "General")')]
                : ['a.delegacion_id', 'd.nombre'];

            $resumenGrupos = DB::table($tabla . ' as a')
                ->leftJoin('delegaciones as d', 'd.id', '=', 'a.delegacion_id')
                ->select($select)
                ->groupBy($groupBy)
                ->orderBy('delegacion_nombre')
                ->get();
        } else {
            $resumenGrupos = DB::table($tabla . ' as a')
                ->select([
                    DB::raw('NULL as delegacion_id'),
                    DB::raw('COALESCE(NULLIF(a.directorio, ""), "General") as delegacion_nombre'),
                    DB::raw('"General" as tipo_sello_grupo'),
                    DB::raw("SUM(CASE WHEN a.estado_aplicacion = 'pendiente' AND a.revisar = 0 AND COALESCE(a.inconsistencia_codigo, '') = '' THEN 1 ELSE 0 END) as listos_aprobar"),
                    DB::raw("SUM(CASE WHEN a.revisar = 1 THEN 1 ELSE 0 END) as con_colision"),
                    DB::raw("SUM(CASE WHEN a.inconsistencia_codigo IS NOT NULL AND COALESCE(a.inconsistencia_estado, 'pendiente') != 'resuelta' THEN 1 ELSE 0 END) as con_inconsistencia"),
                    DB::raw("SUM(CASE WHEN a.estado_aplicacion IN ('aprobado', 'enviado_stellum') THEN 1 ELSE 0 END) as en_proceso"),
                    DB::raw("SUM(CASE WHEN a.estado_aplicacion = 'aplicado' THEN 1 ELSE 0 END) as aplicados"),
                    DB::raw('COUNT(*) as total'),
                ])
                ->groupBy(DB::raw('COALESCE(NULLIF(a.directorio, ""), "General")'))
                ->orderBy('delegacion_nombre')
                ->get();
        }

        $ordenes = OrdenAplicacionStellum::with(['delegacion', 'aprobador'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $reglasDestino = config('reglas_destino_pdf.reglas', []);

        return view('monitoreo.aprobacion_renombres', compact(
            'tipo', 'resumenGrupos', 'ordenes', 'reglasDestino'
        ));
    }

    /**
     * Aprueba los registros listos de una delegación + tipo de sello y despacha EnviarOrdenStellumJob (por chunks de 500).
     */
    public function aprobarYEnviar(Request $request)
    {
        $data = $request->validate([
            'tipo_documento' => 'required|in:registros,sellos,notarias,catastro',
            'delegacion_id'  => 'nullable|integer',
            'tipo_sello'     => 'nullable|string|max:80',
            'directorio'     => 'nullable|string|max:150',
        ]);

        $tipo  = $data['tipo_documento'];
        $tabla = $this->mapaTablas[$tipo];

        $query = DB::table($tabla)
            ->where('estado_aplicacion', 'pendiente')
            ->where('revisar', false)
            ->whereNull('inconsistencia_codigo');

        if (in_array($tipo, ['registros', 'sellos']) && !empty($data['delegacion_id'])) {
            $query->where('delegacion_id', $data['delegacion_id']);
        }
        if ($tipo === 'sellos' && !empty($data['tipo_sello']) && $data['tipo_sello'] !== 'General') {
            $query->where('tipo_sello', $data['tipo_sello']);
        }
        if (in_array($tipo, ['notarias', 'catastro']) && !empty($data['directorio']) && $data['directorio'] !== 'General') {
            $query->where('directorio', $data['directorio']);
        }

        $totalListos = (clone $query)->count();
        if ($totalListos === 0) {
            return back()->with('error', 'No hay registros pendientes listos para aprobar en este grupo.');
        }

        $orden = OrdenAplicacionStellum::create([
            'tipo_documento' => $tipo,
            'delegacion_id'  => $data['delegacion_id'] ?? null,
            'tipo_sello'     => $data['tipo_sello'] ?? null,
            'aprobado_por'   => Auth::id(),
            'total_archivos' => $totalListos,
            'estado'         => 'pendiente',
        ]);

        // Marcar lote como aprobado de forma eficiente sin cargar modelos en memoria
        $query->update([
            'estado_aplicacion'   => 'aprobado',
            'orden_aplicacion_id' => $orden->id,
            'updated_at'          => now(),
        ]);

        // Despachar envío por chunks de 500 hacia Stellum (https://app.astronmx.cloud/)
        EnviarOrdenStellumJob::dispatch($orden->id);

        return back()->with('success', "Orden #{$orden->id} aprobada ({$totalListos} archivos). Se está enviando por lotes a Stellum.");
    }

    public function sincronizarOrden(int $id, StellumBridgeService $bridge)
    {
        $orden = OrdenAplicacionStellum::findOrFail($id);
        $resp = $bridge->consultarEstadoOrden($orden->id);

        if (!empty($resp['ok'])) {
            $orden->update([
                'procesados'      => (int) ($resp['procesados'] ?? $orden->procesados),
                'exitosos'        => (int) ($resp['exitosos'] ?? $orden->exitosos),
                'fallidos'        => (int) ($resp['fallidos'] ?? $orden->fallidos),
                'estado'          => $resp['estado'] ?? $orden->estado,
                'detalle_errores' => $resp['errores_recientes'] ?? $orden->detalle_errores,
            ]);
            return response()->json(['ok' => true, 'orden' => $orden->fresh()]);
        }

        return response()->json(['ok' => false, 'mensaje' => 'No se pudo consultar el estado en Stellum.', 'orden' => $orden]);
    }

    // =========================================================================
    // 4. ASIGNACIÓN DE TIPO DE ARCHIVO / REGLA AUTOMÁTICA POR USUARIO
    // =========================================================================

    public function asignaciones()
    {
        $asignaciones = AsignacionCapturaUsuario::with(['usuario', 'delegacion'])->orderBy('id')->get();
        $usuarios     = User::orderBy('name')->get(['id', 'name', 'user']);
        $delegaciones = Delegaciones::orderBy('nombre')->get(['id', 'clave', 'nombre']);
        $reglasDestino = config('reglas_destino_pdf.reglas', []);

        return view('configuracion.asignaciones_captura', compact(
            'asignaciones', 'usuarios', 'delegaciones', 'reglasDestino'
        ));
    }

    public function guardarAsignacion(Request $request)
    {
        $data = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'tipo_documento' => 'required|in:registros,sellos,notarias,catastro',
            'delegacion_id'  => 'nullable|exists:delegaciones,id',
            'tipo_sello'     => 'nullable|string|max:80',
        ]);

        $reglaAuto = match ($data['tipo_documento']) {
            'sellos'   => 'Regla2',
            'catastro' => 'ReglaCatastro',
            'notarias' => 'ReglaNotarias',
            default    => 'Regla1',
        };

        AsignacionCapturaUsuario::updateOrCreate(
            ['user_id' => $data['user_id']],
            [
                'tipo_documento'   => $data['tipo_documento'],
                'regla_automatica' => $reglaAuto,
                'delegacion_id'    => $data['delegacion_id'] ?? null,
                'tipo_sello'       => $data['tipo_sello'] ?? null,
                'activo'           => true,
            ]
        );

        return back()->with('success', "Asignación guardada. Regla automática asignada: {$reglaAuto}.");
    }

    public function eliminarAsignacion(int $id)
    {
        AsignacionCapturaUsuario::findOrFail($id)->delete();
        return back()->with('success', 'Asignación eliminada correctamente.');
    }
}
