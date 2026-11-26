<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Anio;
use App\Models\ArchivoMuestreo;
use App\Models\ArchivoSesion;
use App\Models\ControlMuestreo;
use App\Models\Delegaciones;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Municipios;
use App\Models\Localidades;
use App\Models\AsignacionCapturaUsuario;
use App\Models\CatalogoInconsistencia;
use App\Services\PdfCacheService;
use App\Services\PdfService;
use App\Services\RutaPropuestaService;
use App\Services\StellumBridgeService;
use App\Events\LoteActualizadoEvent;
use App\Events\InconsistenciaReportadaEvent;

class CapturaController extends Controller
{
    private function esDispositivoMovil(Request $request): bool
    {
        $ua = (string) $request->userAgent();
        return (bool) preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i', $ua);
    }

    private function obtenerPcActual(Request $request, ?User $usuario = null): string
    {
        $pcSesion = trim((string) $request->session()->get('pc_seleccionada', ''));
        if ($pcSesion !== '') {
            return $pcSesion;
        }
        if ($usuario) {
            if (\Illuminate\Support\Facades\Schema::hasTable('usuario_equipos')) {
                $asignados = \App\Models\UsuarioEquipo::where('user_id', $usuario->id)
                    ->orderByDesc('updated_at')
                    ->get();
                if ($asignados->isNotEmpty()) {
                    $nums = $asignados->map(fn ($e) => $e->numero_pc ?: $e->no_serie)->filter()->unique()->values()->all();
                    if (!empty($nums)) {
                        return implode(', ', $nums);
                    }
                }
            }
            if (!empty($usuario->equipo_no_serie)) {
                $pcLocal = \App\Models\PcCaptura::where('no_serie', $usuario->equipo_no_serie)->value('numero_pc');
                return $pcLocal ?: (string) $usuario->equipo_no_serie;
            }
        }
        return (string) $request->ip();
    }

    /**
     * Evalúa si el usuario está dentro de su horario laboral (turno asignado).
     * Los superadmin/administrador no son bloqueados.
     * Si el turno ya terminó pero el capturista tiene un archivo `en_captura`,
     * se le permite guardar ese último registro y luego se bloquea la estación.
     */
    private function evaluarHorarioUsuario(?User $usuario, bool $permitirUltimoConSesion = false, ?ArchivoSesion $sesionActiva = null): array
    {
        if (!$usuario || $usuario->hasAnyRole(['superadmin', 'administrador'])) {
            return ['permitido' => true, 'en_horario' => true, 'ultimo_registro_gracia' => false, 'mensaje' => null, 'turno_texto' => null];
        }

        $turno = $usuario->turnoRelacion;
        if (!$turno || !$turno->estatus) {
            return ['permitido' => true, 'en_horario' => true, 'ultimo_registro_gracia' => false, 'mensaje' => null, 'turno_texto' => null];
        }

        $ahora = now();
        $enHorario = $turno->estaEnHorario($ahora);
        $rango = $turno->rangoTexto();

        if ($enHorario) {
            return [
                'permitido'              => true,
                'en_horario'             => true,
                'ultimo_registro_gracia' => false,
                'mensaje'                => null,
                'turno_texto'            => $rango,
                'turno_nombre'           => $turno->nombre,
                'hora_entrada'           => substr((string) $turno->hora_entrada, 0, 5),
                'hora_salida'            => substr((string) $turno->hora_salida, 0, 5),
                'cruza_medianoche'       => (bool) $turno->cruza_medianoche,
            ];
        }

        // Fuera de horario: si ya tenía un archivo abierto en_captura y está guardándolo, permitir guardar este último registro
        if ($permitirUltimoConSesion && $sesionActiva && $sesionActiva->estado === 'en_captura') {
            return [
                'permitido'              => true,
                'en_horario'             => false,
                'ultimo_registro_gracia' => true,
                'mensaje'                => "Tu horario laboral ({$rango}) ya finalizó. Este último registro se guardó correctamente, pero ya no podrás capturar más hasta tu próximo turno.",
                'turno_texto'            => $rango,
                'turno_nombre'           => $turno->nombre,
                'hora_entrada'           => substr((string) $turno->hora_entrada, 0, 5),
                'hora_salida'            => substr((string) $turno->hora_salida, 0, 5),
                'cruza_medianoche'       => (bool) $turno->cruza_medianoche,
            ];
        }

        return [
            'permitido'              => false,
            'en_horario'             => false,
            'ultimo_registro_gracia' => false,
            'mensaje'                => "Fuera de horario laboral. Tu turno asignado es {$rango} y la hora actual del servidor es " . $ahora->format('H:i:s') . '.',
            'turno_texto'            => $rango,
            'turno_nombre'           => $turno->nombre,
            'hora_entrada'           => substr((string) $turno->hora_entrada, 0, 5),
            'hora_salida'            => substr((string) $turno->hora_salida, 0, 5),
            'cruza_medianoche'       => (bool) $turno->cruza_medianoche,
        ];
    }

    /**
     * Muestra la vista de captura con los catálogos y rutas configuradas inyectados.
     */
    public function index(Request $request)
    {
        // Bloquear acceso desde celulares / dispositivos móviles
        if ($this->esDispositivoMovil($request)) {
            return response()->view('operacion.bloqueo_movil', [], 200);
        }

        $usuario = Auth::user();
        $sesionActivaUsuario = $usuario
            ? ArchivoSesion::where('user_id', $usuario->id)->where('estado', 'en_captura')->first()
            : null;
        $estadoHorario = $this->evaluarHorarioUsuario($usuario, true, $sesionActivaUsuario);
        $pcSeleccionada = $this->obtenerPcActual($request, $usuario);
        $servidorTimestampMs = now()->timestamp * 1000;
        $servidorZonaHoraria = config('app.timezone', 'America/Mexico_City');
        $anios = Anio::where('estatus', 1)->pluck('nombre')->toArray();
        if (empty($anios)) {
            $anios = ['2018', '2019', '2020', '2021', '2022', '2023', '2024', '2025', '2026'];
        }

        $delegaciones = Delegaciones::where('estatus', 1)->get()->map(function ($del) {
            $clave = str_pad($del->clave ?? $del->id, 2, '0', STR_PAD_LEFT);
            return [
                'id' => $del->id,
                'clave' => $clave,
                'nombre' => $del->nombre,
            ];
        })->toArray();

        $municipios = Municipios::orderBy('clave')->get(['id', 'nombre', 'clave'])->toArray();

        $rutasPorTipo = config('captura_pdf.rutas_por_tipo', []);
        // Temporalmente solo trabajamos con Sellos (registros, catastro y notarias comentados)
        $tiposActivos = ['sellos']; // ['registros', 'sellos', 'catastro', 'notarias']
        $directoriosPorDefecto = [];
        foreach ($rutasPorTipo as $tipo => $listaRutas) {
            if (!in_array($tipo, $tiposActivos, true)) {
                continue;
            }
            $reglas = match ($tipo) {
                'registros' => ['Regla1'],
                'sellos'    => ['Regla2'],
                default     => ['Regla2'],
            };
            foreach ($listaRutas as $r) {
                $directoriosPorDefecto[] = [
                    'id'      => $r['id'],
                    'tipo'    => strtoupper($tipo),
                    'entrega' => $r['entrega'] ?? '',
                    'nombre'  => '[' . strtoupper($tipo) . '] ' . $r['nombre'],
                    'reglas'  => $reglas,
                ];
            }
        }

        $asignacionUsuario = AsignacionCapturaUsuario::with('delegacion')
            ->where('user_id', Auth::id())
            ->where('activo', true)
            ->first();

        $catalogoInconsistencias = CatalogoInconsistencia::where('activo', true)
            ->orderBy('codigo')
            ->get(['codigo', 'motivo_rechazo', 'accion_correctiva']);

        // Si en algún servidor aún no se ha ejecutado el seeder, sembrar el catálogo base R01-R09 automáticamente
        if ($catalogoInconsistencias->isEmpty()) {
            $baseInc = [
                ['codigo' => 'R01', 'motivo_rechazo' => 'Imagen ilegible o borrosa',           'accion_correctiva' => 'Reescanear documento'],
                ['codigo' => 'R02', 'motivo_rechazo' => 'Página faltante',                     'accion_correctiva' => 'Completar escaneo'],
                ['codigo' => 'R03', 'motivo_rechazo' => 'Página duplicada',                    'accion_correctiva' => 'Eliminar página duplicada'],
                ['codigo' => 'R04', 'motivo_rechazo' => 'Página invertida o rotada',           'accion_correctiva' => 'Rotar página correctamente'],
                ['codigo' => 'R05', 'motivo_rechazo' => 'Documento incompleto o recortado',    'accion_correctiva' => 'Reescanear completo'],
                ['codigo' => 'R06', 'motivo_rechazo' => 'Archivo dañado o corrupto',           'accion_correctiva' => 'Regenerar PDF'],
                ['codigo' => 'R07', 'motivo_rechazo' => 'Sello sin datos legibles',            'accion_correctiva' => 'Verificar libro físico'],
                ['codigo' => 'R08', 'motivo_rechazo' => 'No corresponde a la delegación/lote', 'accion_correctiva' => 'Reubicar expediente'],
                ['codigo' => 'R09', 'motivo_rechazo' => 'Otra inconsistencia',                 'accion_correctiva' => 'Revisar con supervisor'],
            ];
            foreach ($baseInc as $rowInc) {
                CatalogoInconsistencia::firstOrCreate(
                    ['codigo' => $rowInc['codigo']],
                    array_merge($rowInc, ['activo' => true])
                );
            }
            $catalogoInconsistencias = CatalogoInconsistencia::where('activo', true)
                ->orderBy('codigo')
                ->get(['codigo', 'motivo_rechazo', 'accion_correctiva']);
        }

        // Determinar modo de operación según el rol del usuario:
        // - Captura -> siempre 'captura'
        // - Calidad -> siempre 'muestreo'
        // - Admin / Superadmin -> 'muestreo' si viene ?modo=muestreo, si no 'captura'
        $esRolCalidad = $usuario && $usuario->hasRole('Calidad') && !$usuario->hasRole('Captura');
        $esAdmin = $usuario && $usuario->hasAnyRole(['superadmin', 'administrador']);
        $modoOperacion = ($esRolCalidad || ($esAdmin && $request->query('modo') === 'muestreo'))
            ? 'muestreo'
            : 'captura';

        $reglasDigitosSellos = config('reglas_digitos_sellos', []);

        return view('operacion.estacion_captura', compact(
            'anios',
            'delegaciones',
            'municipios',
            'directoriosPorDefecto',
            'asignacionUsuario',
            'catalogoInconsistencias',
            'estadoHorario',
            'pcSeleccionada',
            'servidorTimestampMs',
            'servidorZonaHoraria',
            'modoOperacion',
            'reglasDigitosSellos'
        ));
    }

    /**
     * Filtra el catálogo de localidades por municipio (alimentado por LocalidadesSeeder).
     */
    public function obtenerLocalidades(Request $request)
    {
        $municipioId = $request->query('municipio_id');
        $busqueda = trim((string) $request->query('q', ''));

        $query = Localidades::query();
        if ($municipioId) {
            $query->where('municipio', $municipioId);
        }
        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%")
                  ->orWhere('clave', 'like', "%{$busqueda}%");
            });
        }

        return response()->json([
            'ok' => true,
            'localidades' => $query->orderBy('clave')->limit(300)->get(['id', 'municipio', 'nombre', 'clave']),
        ]);
    }

    // =========================================================
    // BÚSQUEDA AUTOMÁTICA DE LOTE
    // =========================================================

    /**
     * Resuelve la ruta física raíz tanto para los discos clásicos como para las
     * entregas configuradas en config/captura_pdf.php.
     */
    private function obtenerRutaRaizConfigurada(string $identificador): ?string
    {
        $rutasPorTipo = config('captura_pdf.rutas_por_tipo', []);
        foreach ($rutasPorTipo as $lista) {
            foreach ($lista as $item) {
                if (($item['id'] ?? '') === $identificador) {
                    return $item['ruta'];
                }
            }
        }
        $discos = config('captura_pdf.discos_stellum', []);
        if (isset($discos[$identificador])) {
            return $discos[$identificador];
        }
        return null;
    }

    /**
     * Busca el primer lote libre en el disco/entrega y delegación indicados.
     * Registra el lote como 'en_proceso' en la BD para bloquearlo.
     */
    /**
     * Asigna un único archivo PDF libre al capturista (bloqueo por archivo individual en archivos_sesiones).
     * Reanuda automáticamente si el usuario ya tiene un archivo en_captura no expirado.
     */
    public function buscarPdf(Request $request)
    {
        $disco = $request->query('disco') ?: 'entrega_1_sellos';
        $delegacionId = $request->query('delegacion_id');
        $usuario = Auth::user();
        $userId = $usuario?->id;
        $tipoDoc = 'sellos';

        $esRolCalidad = $usuario && $usuario->hasRole('Calidad') && !$usuario->hasRole('Captura');
        $esAdmin = $usuario && $usuario->hasAnyRole(['superadmin', 'administrador']);
        $esModoMuestreo = $esRolCalidad || ($esAdmin && $request->query('modo') === 'muestreo');

        // Helper para extraer etiqueta corta de botón (ej. número de registro/inscripción "0063" o "0001")
        $extraerEtiqueta = function (string $nombreSinExt, int $orden): string {
            if (preg_match('/_(?:18|19|20)\d{2}_([A-Z0-9]{1,8})(?:_|$)/i', $nombreSinExt, $m)) {
                return strtoupper($m[1]);
            }
            return str_pad((string) $orden, 4, '0', STR_PAD_LEFT);
        };

        // =========================================================================
        // FLUJO EN MODO MUESTREO (ROL CALIDAD)
        // Solo entrega archivos de muestreos previamente generados (control_muestreos.estado = 'en_proceso')
        // =========================================================================
        if ($esModoMuestreo) {
            $evalHorarioMuestreo = $this->evaluarHorarioUsuario($usuario, false, null);
            if (!$evalHorarioMuestreo['permitido']) {
                return response()->json([
                    'ok'            => false,
                    'fuera_horario' => true,
                    'mensaje'       => $evalHorarioMuestreo['mensaje'],
                ], 403);
            }

            // Reanudar archivo de muestreo que este usuario ya tenga en_captura, o tomar el siguiente pendiente
            $itemMuestreo = ArchivoMuestreo::with('controlMuestreo')
                ->whereHas('controlMuestreo', fn ($q) => $q->where('estado', 'en_proceso'))
                ->where('calidad_user_id', $userId)
                ->where('estado', 'en_captura')
                ->orderBy('id')
                ->first();

            if (!$itemMuestreo) {
                $itemMuestreo = ArchivoMuestreo::with('controlMuestreo')
                    ->whereHas('controlMuestreo', fn ($q) => $q->where('estado', 'en_proceso'))
                    ->where('estado', 'pendiente')
                    ->where(function ($q) use ($userId) {
                        $q->where('calidad_user_id', $userId)
                          ->orWhereNull('calidad_user_id');
                    })
                    ->orderByRaw('CASE WHEN calidad_user_id = ? THEN 0 ELSE 1 END', [$userId ?? 0])
                    ->orderBy('id')
                    ->first();

                if ($itemMuestreo) {
                    $itemMuestreo->update([
                        'estado'          => 'en_captura',
                        'calidad_user_id' => $userId,
                        'tomado_en'       => now(),
                    ]);
                }
            }

            if (!$itemMuestreo) {
                return response()->json([
                    'ok'      => false,
                    'modo'    => 'muestreo',
                    'mensaje' => 'No hay muestreos generados pendientes por trabajar. Primero debe generarse el muestreo con el botón "Generar Muestreo".',
                ]);
            }

            $delMuestreo = $itemMuestreo->delegacion_id ? Delegaciones::find($itemMuestreo->delegacion_id) : null;
            if (!$delMuestreo && preg_match('/^(\d{2})[_-]/', (string) $itemMuestreo->archivo_capturado, $mDel)) {
                $delMuestreo = Delegaciones::where('clave', $mDel[1])->first();
            }

            $ctrl = $itemMuestreo->controlMuestreo;
            return response()->json([
                'ok'         => true,
                'modo'       => 'muestreo',
                'fuente'     => $itemMuestreo->indice_pdf_id ? 'stellum' : 'directorio_directo',
                'lote_id'    => $itemMuestreo->id,
                'lote'       => "Muestreo #{$ctrl->id} ({$ctrl->fecha_laboral} · {$ctrl->turno})",
                'delegacion' => $delMuestreo ? [
                    'id'     => $delMuestreo->id,
                    'clave'  => str_pad($delMuestreo->clave ?? $delMuestreo->id, 2, '0', STR_PAD_LEFT),
                    'nombre' => $delMuestreo->nombre,
                ] : null,
                'muestreo_info' => [
                    'control_id'         => $ctrl->id,
                    'fecha_laboral'      => $ctrl->fecha_laboral,
                    'turno'              => $ctrl->turno,
                    'regla'              => $ctrl->regla,
                    'revisados'          => $ctrl->revisados,
                    'muestra'            => $ctrl->muestra,
                    'errores_detectados' => $ctrl->errores_detectados,
                    'errores_permitidos' => $ctrl->errores_permitidos,
                ],
                'archivos' => [[
                    'sesion_id'        => $itemMuestreo->id,
                    'muestreo_item_id' => $itemMuestreo->id,
                    'indice_pdf_id'    => $itemMuestreo->indice_pdf_id,
                    'ruta'             => $itemMuestreo->indice_pdf_id ? ('stellum:' . $itemMuestreo->indice_pdf_id) : $itemMuestreo->ruta_origen,
                    'ruta_origen'      => $itemMuestreo->ruta_origen,
                    'nombre'           => $itemMuestreo->archivo_capturado,
                    'etiqueta_boton'   => $extraerEtiqueta((string) $itemMuestreo->archivo_capturado, 1),
                    'paginas'          => (int) ($itemMuestreo->paginas ?: 1),
                    'hash'             => $itemMuestreo->hash,
                    'tipo'             => 'sellos',
                ]],
                'mismo_hash' => false,
                'total'      => 1,
            ]);
        }

        // 0. Liberar sesiones expiradas (> 15 min inactivas)
        ArchivoSesion::where('estado', 'en_captura')
            ->whereNotNull('expira_en')
            ->where('expira_en', '<', now())
            ->delete();

        // 0.5. Si el usuario tiene PDFs pospuestos ("skipeados") cuyo horario de liberación (siguiente turno) ya llegó,
        // reactivarlos exclusivamente para este mismo usuario
        if ($userId) {
            $pospuestoListo = ArchivoSesion::where('user_id', $userId)
                ->where('estado', 'pospuesto')
                ->whereNotNull('liberado_a_partir_de')
                ->where('liberado_a_partir_de', '<=', now())
                ->orderBy('liberado_a_partir_de')
                ->first();

            if ($pospuestoListo) {
                $pospuestoListo->update([
                    'estado'               => 'en_captura',
                    'tomado_en'            => now(),
                    'expira_en'            => now()->addMinutes(15),
                    'liberado_a_partir_de' => null,
                ]);
            }
        }

        // 1. Reanudar o gestionar las 2 sesiones activas por usuario (1 PDF en trabajo + 1 PDF precargado en segundo plano)
        $esPrefetch = $request->boolean('prefetch');
        $sesionesActivas = ArchivoSesion::where('user_id', $userId)
            ->where('estado', 'en_captura')
            ->orderBy('id')
            ->get();
        $sesionActiva = $sesionesActivas->first();

        // Verificar bloqueo por horario laboral en backend
        $evalHorario = $this->evaluarHorarioUsuario($usuario, true, $sesionActiva);
        if (!$evalHorario['permitido']) {
            return response()->json([
                'ok'            => false,
                'fuera_horario' => true,
                'mensaje'       => $evalHorario['mensaje'],
            ], 403);
        }

        // Agrupar sesiones activas por documento/hash para distinguir el PDF #1 (actual) del PDF #2 (precargado en sesión)
        $gruposActivos = [];
        foreach ($sesionesActivas as $ses) {
            $keyGrupo = trim((string) $ses->hash_pdf) !== ''
                ? ('hash_' . strtolower(trim((string) $ses->hash_pdf)))
                : ('sesion_' . $ses->id);
            $gruposActivos[$keyGrupo][] = $ses;
        }
        $listaGruposActivos = array_values($gruposActivos);

        $formatearGrupoSesion = function (array $grupoSesiones) use ($extraerEtiqueta): array {
            $primera = $grupoSesiones[0];
            $delGrupo = $primera->delegacion_id ? Delegaciones::find($primera->delegacion_id) : null;
            if (!$delGrupo && preg_match('/^(\d{2})[_-]/', (string) $primera->archivo_nombre, $mDel)) {
                $delGrupo = Delegaciones::where('clave', $mDel[1])->first();
            }
            $archivosGrupo = [];
            foreach (array_values($grupoSesiones) as $idx => $ses) {
                $archivosGrupo[] = [
                    'sesion_id'      => $ses->id,
                    'indice_pdf_id'  => $ses->indice_pdf_id,
                    'ruta'           => $ses->indice_pdf_id ? ('stellum:' . $ses->indice_pdf_id) : $ses->ruta_origen,
                    'ruta_origen'    => $ses->ruta_origen,
                    'nombre'         => $ses->archivo_nombre,
                    'etiqueta_boton' => $extraerEtiqueta((string) $ses->archivo_nombre, $idx + 1),
                    'paginas'        => (int) $ses->paginas,
                    'hash'           => $ses->hash_pdf,
                    'tipo'           => $ses->tipo_archivo,
                ];
            }
            return [
                'ok'         => true,
                'fuente'     => $primera->indice_pdf_id ? 'stellum' : 'directorio_directo',
                'lote_id'    => $primera->id,
                'lote'       => $primera->archivo_nombre,
                'delegacion' => $delGrupo ? [
                    'id'     => $delGrupo->id,
                    'clave'  => str_pad($delGrupo->clave ?? $delGrupo->id, 2, '0', STR_PAD_LEFT),
                    'nombre' => $delGrupo->nombre,
                ] : null,
                'archivos'   => $archivosGrupo,
                'mismo_hash' => count($archivosGrupo) > 1,
                'total'      => count($archivosGrupo),
            ];
        };

        if ($sesionesActivas->isNotEmpty()) {
            ArchivoSesion::whereIn('id', $sesionesActivas->pluck('id'))
                ->update(['expira_en' => now()->addMinutes(15)]);

            // Si es petición de precarga en segundo plano (prefetch=1):
            // - Si ya tiene un 2.º PDF en sesión, devolver ese 2.º PDF sin abrir un 3.º.
            // - Si solo tiene 1 PDF en sesión, continuar abajo para asignarle el 2.º PDF en archivos_sesiones.
            if ($esPrefetch) {
                if (count($listaGruposActivos) >= 2) {
                    $payloadPrefetch = $formatearGrupoSesion($listaGruposActivos[1]);
                    $payloadPrefetch['prefetch'] = true;
                    return response()->json($payloadPrefetch);
                }
            } else {
                // Petición principal: devolver el PDF #1 como activo y, si ya existe el PDF #2 en sesión, adjuntarlo en prefetch_data
                $payloadPrincipal = $formatearGrupoSesion($listaGruposActivos[0]);
                if (count($listaGruposActivos) >= 2) {
                    $payloadPrincipal['prefetch_data'] = $formatearGrupoSesion($listaGruposActivos[1]);
                }
                return response()->json($payloadPrincipal);
            }
        }

        $delFiltro = $delegacionId ? Delegaciones::find($delegacionId) : null;

        // 2. Consultar EXCLUSIVAMENTE indice_archivos_pdfs en Stellum (paginación por cursor after_id)
        // Prioridad operativa: cuando no hay delegación fija, buscar primero PDFs de Tuxtla (clave 01_)
        // y solo si ya no hay disponibles en Tuxtla continuar con el resto de delegaciones.
        try {
            $bridge = app(StellumBridgeService::class);
            $skipAfterIdInicial = (int) $request->session()->pull('admin_skip_after_id', 0);
            $maxPaginas = 20; // hasta 2,000 registros por ciclo sin OFFSET

            $fasesBusqueda = $delFiltro
                ? [['delegacion' => $delFiltro->nombre, 'q' => null]]
                : [
                    ['delegacion' => null, 'q' => '01_'], // Fase 1: Prioridad Tuxtla (01_)
                    ['delegacion' => null, 'q' => null],  // Fase 2: Resto de delegaciones
                ];

            foreach ($fasesBusqueda as $idxFase => $fase) {
                $afterId = ($idxFase === 0) ? $skipAfterIdInicial : 0;

                for ($paginaCursor = 0; $paginaCursor < $maxPaginas; $paginaCursor++) {
                    $paramsStellum = [
                        'tipo'          => $tipoDoc,
                        'clasificacion' => 'entregable',
                        'after_id'      => $afterId,
                        'limit'         => 100,
                    ];
                    if (!empty($fase['delegacion'])) {
                        $paramsStellum['delegacion'] = $fase['delegacion'];
                    }
                    if (!empty($fase['q'])) {
                        $paramsStellum['q'] = $fase['q'];
                    }

                    $respStellum = $bridge->listarIndice($paramsStellum);

                    if (empty($respStellum['ok'])) {
                        return response()->json([
                            'ok'      => false,
                            'mensaje' => 'No se pudo consultar indice_archivos_pdfs en Stellum: ' . ($respStellum['error'] ?? ('HTTP ' . ($respStellum['status'] ?? 'error'))),
                        ]);
                    }

                    $items = $respStellum['items'] ?? [];
                    if (empty($items)) {
                        break;
                    }

                    foreach ($items as $item) {
                        $indiceId = isset($item['id']) ? (int) $item['id'] : null;
                        if (!$indiceId) {
                            continue;
                        }

                        $rutaOrigen = (string) ($item['ruta'] ?? ('stellum:' . $indiceId));
                        $rutaHash = hash('sha256', $rutaOrigen);
                        $hashItem = trim((string) ($item['hash'] ?? ''));

                        $yaTomado = ArchivoSesion::where(function ($q) use ($rutaHash, $indiceId, $hashItem) {
                                $q->where('ruta_hash', $rutaHash)
                                  ->orWhere('indice_pdf_id', $indiceId);
                                // Si otro usuario ya tiene en_captura o pospuesto cualquier PDF con este mismo hash, bloquear todo el grupo
                                if ($hashItem !== '') {
                                    $q->orWhere(function ($qH) use ($hashItem) {
                                        $qH->where('hash_pdf', $hashItem)
                                           ->whereIn('estado', ['en_captura', 'pospuesto']);
                                    });
                                }
                            })
                            ->whereIn('estado', ['en_captura', 'completado', 'pospuesto'])
                            ->exists();

                        if ($yaTomado) {
                            continue;
                        }

                        $nombreSinExt = preg_replace('/\.pdf$/i', '', (string) ($item['nombre'] ?? 'archivo'));

                        // Resolver delegación desde el prefijo del archivo (ej. 01_...) o el campo delegacion de Stellum
                        $delItem = $delFiltro;
                        if (!$delItem && preg_match('/^(\d{2})[_-]/', $nombreSinExt, $mClave)) {
                            $delItem = Delegaciones::where('clave', $mClave[1])->first();
                        }
                        if (!$delItem && !empty($item['delegacion'])) {
                            $delItem = Delegaciones::whereRaw('LOWER(nombre) = ?', [strtolower(trim((string) $item['delegacion']))])->first();
                        }

                        try {
                            $nuevaSesion = ArchivoSesion::create([
                                'user_id'        => $userId,
                                'indice_pdf_id'  => $indiceId,
                                'disco'          => 'indice_archivos_pdfs',
                                'tipo_archivo'   => $tipoDoc,
                                'delegacion_id'  => $delItem?->id,
                                'pc'             => $this->obtenerPcActual($request, $usuario),
                                'archivo_nombre' => $nombreSinExt,
                                'ruta_origen'    => $rutaOrigen,
                                'ruta_hash'      => $rutaHash,
                                'paginas'        => (int) ($item['paginas'] ?? 1),
                                'hash_pdf'       => $item['hash'] ?? null,
                                'estado'         => 'en_captura',
                                'tomado_en'      => now(),
                                'expira_en'      => now()->addMinutes(15),
                            ]);

                            $archivosAsignados = [[
                                'sesion_id'      => $nuevaSesion->id,
                                'indice_pdf_id'  => $indiceId,
                                'ruta'           => 'stellum:' . $indiceId,
                                'ruta_origen'    => $rutaOrigen,
                                'nombre'         => $nombreSinExt,
                                'etiqueta_boton' => $extraerEtiqueta($nombreSinExt, 1),
                                'paginas'        => (int) ($item['paginas'] ?? 1),
                                'hash'           => $item['hash'] ?? null,
                                'tipo'           => $item['tipo'] ?? $tipoDoc,
                                'clasificacion'  => $item['clasificacion'] ?? null,
                            ]];

                            // Consultar si existen otros PDFs entregables con el MISMO hash (tanto en el lote actual como en Stellum)
                            $hashPdf = trim((string) ($item['hash'] ?? ''));
                            if ($hashPdf !== '') {
                                $candidatosMismoHash = [];
                                // 1) Revisar primero dentro del bloque actual ya descargado ($items)
                                foreach ($items as $itLocal) {
                                    if (strtolower((string) ($itLocal['clasificacion'] ?? 'entregable')) !== 'entregable') {
                                        continue;
                                    }
                                    $hLocal = trim((string) ($itLocal['hash'] ?? ''));
                                    if ($hLocal !== '' && strcasecmp($hLocal, $hashPdf) === 0) {
                                        $candidatosMismoHash[$itLocal['id'] ?? uniqid()] = $itLocal;
                                    }
                                }
                                // 2) Consultar también por parámetro hash en Stellum (hasta 100 coincidencias exactas para bloquear todo el grupo)
                                $respHash = $bridge->listarIndice([
                                    'tipo'          => $tipoDoc,
                                    'clasificacion' => 'entregable',
                                    'hash'          => $hashPdf,
                                    'limit'         => 100,
                                ]);
                                if (!empty($respHash['ok']) && !empty($respHash['items'])) {
                                    foreach ($respHash['items'] as $itRemoto) {
                                        $hRem = trim((string) ($itRemoto['hash'] ?? ''));
                                        if ($hRem !== '' && strcasecmp($hRem, $hashPdf) === 0) {
                                            $candidatosMismoHash[$itRemoto['id'] ?? uniqid()] = $itRemoto;
                                        }
                                    }
                                }

                                if (!empty($candidatosMismoHash)) {
                                    foreach ($candidatosMismoHash as $itDup) {
                                        $dupId = isset($itDup['id']) ? (int) $itDup['id'] : null;
                                        if (!$dupId || $dupId === $indiceId) {
                                            continue;
                                        }
                                        // Validación estricta: solo agrupar si el hash coincide EXACTAMENTE con el PDF actual
                                        $dupHash = trim((string) ($itDup['hash'] ?? ''));
                                        if ($dupHash === '' || strcasecmp($dupHash, $hashPdf) !== 0) {
                                            continue;
                                        }
                                        $dupRuta = (string) ($itDup['ruta'] ?? ('stellum:' . $dupId));
                                        $dupRutaHash = hash('sha256', $dupRuta);
                                        $dupYaTomado = ArchivoSesion::where(function ($q) use ($dupRutaHash, $dupId) {
                                                $q->where('ruta_hash', $dupRutaHash)
                                                  ->orWhere('indice_pdf_id', $dupId);
                                            })
                                            ->whereIn('estado', ['en_captura', 'completado', 'pospuesto'])
                                            ->exists();
                                        if ($dupYaTomado) {
                                            continue;
                                        }
                                        $dupNombre = preg_replace('/\.pdf$/i', '', (string) ($itDup['nombre'] ?? 'archivo'));
                                        try {
                                            $sesDup = ArchivoSesion::create([
                                                'user_id'        => $userId,
                                                'indice_pdf_id'  => $dupId,
                                                'disco'          => 'indice_archivos_pdfs',
                                                'tipo_archivo'   => $tipoDoc,
                                                'delegacion_id'  => $delItem?->id,
                                                'pc'             => $this->obtenerPcActual($request, $usuario),
                                                'archivo_nombre' => $dupNombre,
                                                'ruta_origen'    => $dupRuta,
                                                'ruta_hash'      => $dupRutaHash,
                                                'paginas'        => (int) ($itDup['paginas'] ?? 1),
                                                'hash_pdf'       => $itDup['hash'] ?? $hashPdf,
                                                'estado'         => 'en_captura',
                                                'tomado_en'      => now(),
                                                'expira_en'      => now()->addMinutes(15),
                                            ]);
                                            $archivosAsignados[] = [
                                                'sesion_id'      => $sesDup->id,
                                                'indice_pdf_id'  => $dupId,
                                                'ruta'           => 'stellum:' . $dupId,
                                                'ruta_origen'    => $dupRuta,
                                                'nombre'         => $dupNombre,
                                                'etiqueta_boton' => $extraerEtiqueta($dupNombre, count($archivosAsignados) + 1),
                                                'paginas'        => (int) ($itDup['paginas'] ?? 1),
                                                'hash'           => $itDup['hash'] ?? $hashPdf,
                                                'tipo'           => $itDup['tipo'] ?? $tipoDoc,
                                                'clasificacion'  => $itDup['clasificacion'] ?? null,
                                            ];
                                        } catch (\Throwable $eDup) {
                                            continue;
                                        }
                                    }
                                }
                            }

                            return response()->json([
                                'ok'         => true,
                                'prefetch'   => $esPrefetch,
                                'fuente'     => 'stellum',
                                'lote_id'    => $nuevaSesion->id,
                                'lote'       => $nombreSinExt,
                                'delegacion' => $delItem ? [
                                    'id'     => $delItem->id,
                                    'clave'  => str_pad($delItem->clave ?? $delItem->id, 2, '0', STR_PAD_LEFT),
                                    'nombre' => $delItem->nombre,
                                ] : null,
                                'archivos'   => $archivosAsignados,
                                'mismo_hash' => count($archivosAsignados) > 1,
                                'total'      => count($archivosAsignados),
                            ]);
                        } catch (\Throwable $e) {
                            // Otro capturista tomó este archivo en el mismo milisegundo; continuar con el siguiente
                            continue;
                        }
                    }

                    if (empty($respStellum['has_more'])) {
                        break;
                    }
                    $afterId = (int) ($respStellum['last_id'] ?? $afterId);
                }
            }
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Error al consultar indice_archivos_pdfs en Stellum: ' . $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => false, 'mensaje' => 'No hay archivos PDF disponibles en indice_archivos_pdfs para capturar.']);
    }

    /**
     * Exclusivo para administrador/superadmin: libera el PDF actual y salta al siguiente en Stellum.
     */
    public function adminSiguientePdf(Request $request)
    {
        $usuario = Auth::user();
        abort_unless($usuario && $usuario->hasAnyRole(['superadmin', 'administrador']), 403, 'Solo administradores.');

        $sesiones = ArchivoSesion::where('user_id', $usuario->id)
            ->where('estado', 'en_captura')
            ->orderBy('id')
            ->get();

        if ($sesiones->isNotEmpty()) {
            $primera = $sesiones->first();
            $hashPrimera = trim((string) $primera->hash_pdf);
            $idsEliminar = $sesiones
                ->filter(fn ($s) => $hashPrimera !== '' ? strcasecmp(trim((string) $s->hash_pdf), $hashPrimera) === 0 : $s->id === $primera->id)
                ->pluck('id');
            $maxIdGrupo = (int) $sesiones->whereIn('id', $idsEliminar)->max('indice_pdf_id');
            ArchivoSesion::whereIn('id', $idsEliminar)->delete();
            if ($maxIdGrupo > 0) {
                $request->session()->put('admin_skip_after_id', $maxIdGrupo);
            }
        }

        return $this->buscarPdf($request);
    }

    /**
     * Exclusivo para administrador/superadmin: busca en Stellum un grupo de 2+ PDFs entregables
     * con exactamente el mismo hash y los asigna juntos en archivos_sesiones.
     */
    public function adminBuscarMismoHash(Request $request)
    {
        $usuario = Auth::user();
        abort_unless($usuario && $usuario->hasAnyRole(['superadmin', 'administrador']), 403, 'Solo administradores.');

        $tipoDoc = 'sellos';
        $bridge = app(StellumBridgeService::class);

        // Excluir hashes que el admin acaba de ver o ya están completados
        $excluirHashes = ArchivoSesion::whereNotNull('hash_pdf')
            ->where('hash_pdf', '!=', '')
            ->where(function ($q) use ($usuario) {
                $q->where('estado', 'completado')
                  ->orWhere('user_id', $usuario->id);
            })
            ->distinct()
            ->limit(50)
            ->pluck('hash_pdf')
            ->all();

        $resp = $bridge->buscarGrupoMismoHash($tipoDoc, $excluirHashes);
        if (empty($resp['ok']) || empty($resp['items']) || count($resp['items']) < 2) {
            return response()->json([
                'ok'      => false,
                'mensaje' => $resp['error'] ?? 'No se encontró un grupo de varios PDFs con el mismo hash en Stellum (asegúrate de hacer git pull en Stellum).',
            ]);
        }

        // Liberar sesiones en_captura anteriores del administrador
        ArchivoSesion::where('user_id', $usuario->id)
            ->where('estado', 'en_captura')
            ->delete();

        $extraerEtiqueta = function (string $nombreSinExt, int $orden): string {
            if (preg_match('/_(?:18|19|20)\d{2}_([A-Z0-9]{1,8})(?:_|$)/i', $nombreSinExt, $m)) {
                return strtoupper($m[1]);
            }
            return str_pad((string) $orden, 4, '0', STR_PAD_LEFT);
        };

        $hashGrupo = (string) ($resp['hash'] ?? $resp['items'][0]['hash'] ?? '');
        $archivosAsignados = [];
        $delItem = null;

        foreach ($resp['items'] as $it) {
            $idPdf = isset($it['id']) ? (int) $it['id'] : null;
            $itHash = trim((string) ($it['hash'] ?? ''));
            if (!$idPdf || ($hashGrupo !== '' && strcasecmp($itHash, $hashGrupo) !== 0)) {
                continue;
            }
            $rutaOrg = (string) ($it['ruta'] ?? ('stellum:' . $idPdf));
            $rutaHash = hash('sha256', $rutaOrg);
            $nombreSinExt = preg_replace('/\.pdf$/i', '', (string) ($it['nombre'] ?? 'archivo'));

            if (!$delItem && preg_match('/^(\d{2})[_-]/', $nombreSinExt, $mClave)) {
                $delItem = Delegaciones::where('clave', $mClave[1])->first();
            }
            if (!$delItem && !empty($it['delegacion'])) {
                $delItem = Delegaciones::whereRaw('LOWER(nombre) = ?', [strtolower(trim((string) $it['delegacion']))])->first();
            }

            try {
                // Reusar si no está tomado por otro capturista
                $yaTomadoOtro = ArchivoSesion::where(function ($q) use ($rutaHash, $idPdf) {
                        $q->where('ruta_hash', $rutaHash)->orWhere('indice_pdf_id', $idPdf);
                    })
                    ->whereIn('estado', ['en_captura', 'completado'])
                    ->exists();
                if ($yaTomadoOtro) {
                    continue;
                }

                $ses = ArchivoSesion::create([
                    'user_id'        => $usuario->id,
                    'indice_pdf_id'  => $idPdf,
                    'disco'          => 'indice_archivos_pdfs',
                    'tipo_archivo'   => $tipoDoc,
                    'delegacion_id'  => $delItem?->id,
                    'pc'             => $this->obtenerPcActual($request, $usuario),
                    'archivo_nombre' => $nombreSinExt,
                    'ruta_origen'    => $rutaOrg,
                    'ruta_hash'      => $rutaHash,
                    'paginas'        => (int) ($it['paginas'] ?? 1),
                    'hash_pdf'       => $itHash,
                    'estado'         => 'en_captura',
                    'tomado_en'      => now(),
                    'expira_en'      => now()->addMinutes(15),
                ]);

                $archivosAsignados[] = [
                    'sesion_id'      => $ses->id,
                    'indice_pdf_id'  => $idPdf,
                    'ruta'           => 'stellum:' . $idPdf,
                    'ruta_origen'    => $rutaOrg,
                    'nombre'         => $nombreSinExt,
                    'etiqueta_boton' => $extraerEtiqueta($nombreSinExt, count($archivosAsignados) + 1),
                    'paginas'        => (int) ($it['paginas'] ?? 1),
                    'hash'           => $itHash,
                    'tipo'           => $it['tipo'] ?? $tipoDoc,
                    'clasificacion'  => $it['clasificacion'] ?? 'entregable',
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        if (empty($archivosAsignados)) {
            return response()->json(['ok' => false, 'mensaje' => 'Los PDFs de ese grupo ya están ocupados o completados. Intenta de nuevo.']);
        }

        return response()->json([
            'ok'         => true,
            'fuente'     => 'stellum',
            'lote_id'    => $archivosAsignados[0]['sesion_id'],
            'lote'       => $archivosAsignados[0]['nombre'],
            'delegacion' => $delItem ? [
                'id'     => $delItem->id,
                'clave'  => str_pad($delItem->clave ?? $delItem->id, 2, '0', STR_PAD_LEFT),
                'nombre' => $delItem->nombre,
            ] : null,
            'archivos'   => $archivosAsignados,
            'mismo_hash' => count($archivosAsignados) > 1,
            'total'      => count($archivosAsignados),
        ]);
    }

    /**
     * Extrae el número de un nombre de lote (ej. "Lote 25" → 25).
     */
    private function extraerNumeroLote(string $nombre): int
    {
        $limpio = preg_replace('/[^0-9]/', '', strtolower(str_replace('lote', '', $nombre)));
        return $limpio !== '' ? (int) $limpio : 999999;
    }

    /**
     * Resuelve la ruta relativa de la delegación dentro del subdirectorio base (ej. Respaldo_Original),
     * manejando discrepancias de acentos (ej. "TUXTLA GUTIÉRREZ" en BD vs "Tuxtla Gutierrez" en disco)
     * y mayúsculas/minúsculas en el disco de red.
     */
    private function resolverCarpetaDelegacion($storage, string $subcarpetaBase, string $nombreDelegacion): ?string
    {
        $rutaDirecta = $subcarpetaBase . '/' . $nombreDelegacion;
        if (is_dir($storage->path($rutaDirecta))) {
            return $rutaDirecta;
        }

        $rutaFisicaBase = $storage->path($subcarpetaBase);
        if (!is_dir($rutaFisicaBase)) {
            return null;
        }

        $entradas = @scandir($rutaFisicaBase);
        if ($entradas === false) {
            return null;
        }

        $normBuscado = $this->normalizarTextoSinAcentos($nombreDelegacion);

        foreach ($entradas as $entrada) {
            if ($entrada === '.' || $entrada === '..') {
                continue;
            }
            if ($this->normalizarTextoSinAcentos($entrada) === $normBuscado) {
                return $subcarpetaBase . '/' . $entrada;
            }
        }

        return null;
    }

    private function normalizarTextoSinAcentos(string $str): string
    {
        $sinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sinAcentos ?: $str));
    }

    // =========================================================
    // SERVIR PDF AL IFRAME
    // =========================================================

    /**
     * Sirve un PDF exclusivamente desde indice_archivos_pdfs vía proxy server-side hacia Stellum
     * (mismo origen para el iframe, sin CORS y sin exponer STELLUM_BRIDGE_TOKEN al navegador).
     */
    /**
     * Sirve un PDF de indice_archivos_pdfs desde el caché local SSD (vía Nginx X-Accel-Redirect en producción
     * con soporte nativo de Range 206, o response()->file() en local), descargándolo en streaming desde Stellum
     * si aún no estaba pre-descargado.
     */
    public function verPdf(Request $request)
    {
        $ruta = (string) $request->query('ruta', '');

        if (str_starts_with($ruta, 'stellum:')) {
            $indiceId = (int) substr($ruta, 8);
            if ($indiceId > 0) {
                $cache = app(PdfCacheService::class);
                $res = $cache->asegurarEnCache($indiceId, 95);

                if (!$res['ok'] || empty($res['path'])) {
                    $status = (int) ($res['status'] ?? 502);
                    if ($status === 404) {
                        abort(404, 'PDF no encontrado en Stellum.');
                    }
                    if ($status === 504) {
                        abort(504, 'Tiempo de espera agotado al obtener el PDF desde Stellum.');
                    }
                    abort(502, 'No se pudo obtener el PDF desde Stellum.');
                }

                $headers = [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="documento_' . $indiceId . '.pdf"',
                    'Cache-Control'       => 'private, max-age=300',
                ];

                if (config('services.stellum_bridge.pdf_cache_xaccel', false)) {
                    $headers['X-Accel-Redirect'] = '/_internal_pdf_cache/' . $indiceId . '.pdf';

                    return response('', 200, $headers);
                }

                return response()->file($res['path'], $headers);
            }
        }

        abort(404, 'Solo se permiten archivos provenientes de indice_archivos_pdfs (Stellum).');
    }

    // =========================================================
    // FLUJO SOLO-BD (NUNCA RENOMBRA NI MUEVE ARCHIVOS FÍSICOS)
    // =========================================================

    /**
     * Registra en BD la acción de captura (validado, renombrado, duplicar, inconsistencia),
     * verifica colisiones en indice_archivos_pdfs (Stellum) y calcula ruta_propuesta.
     */
    private function registrarAccionSoloBd(Request $request, string $modo): \Illuminate\Http\JsonResponse
    {
        $usuario = Auth::user();

        // =========================================================================
        // GUARDADO EN MODO MUESTREO (ROL CALIDAD) — 100% EN BD
        // Si coincide el nombre anterior (archivo_capturado) y el nuevo -> 'validado' (correcto)
        // Si no coinciden -> 'renombrado' (error de muestreo + marca revisar=1 en auditoria_sellos)
        // =========================================================================
        $esRolCalidad = $usuario && $usuario->hasRole('Calidad') && !$usuario->hasRole('Captura');
        $muestreoItemId = $request->input('muestreo_item_id') ? (int) $request->input('muestreo_item_id') : null;
        if ($esRolCalidad || $muestreoItemId || $request->input('modo_operacion') === 'muestreo') {
            $itemMuestreo = $muestreoItemId
                ? ArchivoMuestreo::with('controlMuestreo')->find($muestreoItemId)
                : ArchivoMuestreo::with('controlMuestreo')
                    ->where('calidad_user_id', $usuario?->id)
                    ->where('estado', 'en_captura')
                    ->latest('id')
                    ->first();

            if ($itemMuestreo) {
                $capturadoAnterior = preg_replace('/\.pdf$/i', '', trim((string) $itemMuestreo->archivo_capturado));
                $nuevoReq = trim((string) ($request->input('nuevo_nombre') ?: $request->input('archivo_nuevo') ?: $capturadoAnterior));
                $nuevoMuestreo = preg_replace('/\.pdf$/i', '', $nuevoReq);

                $coincide = strcasecmp($capturadoAnterior, $nuevoMuestreo) === 0;
                $accionMuestreo = $coincide ? 'validado' : 'renombrado';

                DB::transaction(function () use ($itemMuestreo, $usuario, $nuevoMuestreo, $accionMuestreo, $coincide, $capturadoAnterior) {
                    $itemMuestreo->update([
                        'archivo_muestreo' => $nuevoMuestreo,
                        'accion_muestreo'  => $accionMuestreo,
                        'calidad_user_id'  => $usuario?->id,
                        'estado'           => 'completado',
                        'es_correcto'      => $coincide,
                        'detalles'         => $coincide
                            ? 'Muestreo de Calidad: Validado (coincide con la captura).'
                            : "Muestreo de Calidad: Renombrado / Error detectado (Capturista: {$capturadoAnterior} | Calidad: {$nuevoMuestreo}).",
                        'completado_en'    => now(),
                    ]);

                    // Si no coincide (renombrado en muestreo), es un error que debe revisarse en auditoria_sellos
                    if (!$coincide && $itemMuestreo->auditoria_sello_id) {
                        $selloRow = DB::table('auditoria_sellos')->where('id', $itemMuestreo->auditoria_sello_id)->first();
                        if ($selloRow) {
                            $detActual = trim((string) $selloRow->detalles);
                            $notaMuestreo = "[ERROR EN MUESTREO CALIDAD: Capturista capturó '{$capturadoAnterior}', Calidad capturó '{$nuevoMuestreo}'. Marcado para revisión]";
                            DB::table('auditoria_sellos')
                                ->where('id', $itemMuestreo->auditoria_sello_id)
                                ->update([
                                    'revisar'           => true,
                                    'decision_revision' => 'pendiente',
                                    'detalles'          => trim($detActual . ' ' . $notaMuestreo),
                                    'updated_at'        => now(),
                                ]);
                        }
                    }

                    // Actualizar contadores y dictamen del ControlMuestreo
                    $ctrl = $itemMuestreo->controlMuestreo;
                    if ($ctrl) {
                        $revisados = ArchivoMuestreo::where('control_muestreo_id', $ctrl->id)
                            ->where('estado', 'completado')
                            ->count();
                        $errores = ArchivoMuestreo::where('control_muestreo_id', $ctrl->id)
                            ->where('estado', 'completado')
                            ->where('es_correcto', false)
                            ->count();

                        $nuevoEstadoCtrl = 'en_proceso';
                        if ($revisados >= $ctrl->muestra) {
                            $nuevoEstadoCtrl = ($errores > $ctrl->errores_permitidos) ? 'rechazado' : 'aprobado';
                        }

                        $ctrl->update([
                            'revisados'          => $revisados,
                            'errores_detectados' => $errores,
                            'estado'             => $nuevoEstadoCtrl,
                        ]);
                    }
                });

                return response()->json([
                    'ok'          => true,
                    'modo'        => 'muestreo',
                    'accion'      => $accionMuestreo,
                    'es_correcto' => $coincide,
                    'revisar'     => !$coincide,
                    'mensaje'     => $coincide
                        ? 'Muestreo registrado como VALIDADO.'
                        : 'Discrepancia registrada como RENOMBRADO en muestreo y enviada a revisión.',
                ]);
            }
        }

        $asignacion = AsignacionCapturaUsuario::where('user_id', $usuario?->id)->where('activo', true)->first();

        // Recuperar la sesión individual activa del archivo (fuente de verdad de ruta_origen, paginas, hash e indice_pdf_id)
        $sesionIdReq = $request->input('sesion_id') ? (int) $request->input('sesion_id') : null;
        $sesionActiva = null;
        if ($sesionIdReq) {
            $sesionActiva = ArchivoSesion::where('id', $sesionIdReq)
                ->when($usuario, fn ($q) => $q->where('user_id', $usuario->id))
                ->first();
        }
        if (!$sesionActiva && $usuario) {
            $sesionActiva = ArchivoSesion::where('user_id', $usuario->id)
                ->where('estado', 'en_captura')
                ->latest('id')
                ->first();
        }

        // Validación de horario laboral en backend (permite guardar el último archivo en_captura si acaba de terminar el turno)
        $evalHorario = $this->evaluarHorarioUsuario($usuario, true, $sesionActiva);
        if (!$evalHorario['permitido']) {
            return response()->json([
                'ok'            => false,
                'fuera_horario' => true,
                'mensaje'       => $evalHorario['mensaje'],
            ], 403);
        }

        $tipoDocumento = strtolower((string) ($request->input('tipo_documento') ?: ($sesionActiva?->tipo_archivo ?: ($asignacion?->tipo_documento ?? 'sellos'))));
        if (!in_array($tipoDocumento, ['registros', 'sellos', 'notarias', 'catastro'])) {
            $tipoDocumento = 'sellos';
        }

        // Resolver ruta_origen real (nunca guardar "stellum:ID" como ruta ni como nombre de archivo)
        $rutaReq = trim((string) ($request->input('ruta_origen') ?: $request->input('ruta_original') ?: $request->input('ruta_archivo') ?: ''));
        $indiceDesdeRuta = str_starts_with($rutaReq, 'stellum:') ? (int) substr($rutaReq, 8) : null;

        $rutaOrigen = ($rutaReq !== '' && !str_starts_with($rutaReq, 'stellum:'))
            ? $rutaReq
            : (string) ($sesionActiva?->ruta_origen ?? '');

        $indicePdfId = $request->input('indice_pdf_id')
            ? (int) $request->input('indice_pdf_id')
            : ($sesionActiva?->indice_pdf_id ?: $indiceDesdeRuta);

        $archivoOriginalReq = trim((string) $request->input('archivo_original', ''));
        if ($archivoOriginalReq !== '' && !str_starts_with($archivoOriginalReq, 'stellum:')) {
            $archivoOriginal = $archivoOriginalReq;
        } elseif (!empty($sesionActiva?->archivo_nombre)) {
            $archivoOriginal = (string) $sesionActiva->archivo_nombre;
        } else {
            $archivoOriginal = pathinfo(str_replace('\\', '/', $rutaOrigen), PATHINFO_FILENAME);
        }

        $archivoNuevoReq = trim((string) ($request->input('nuevo_nombre') ?: $request->input('archivo_nuevo') ?: ''));
        $archivoNuevo = ($archivoNuevoReq !== '' && !str_starts_with($archivoNuevoReq, 'stellum:'))
            ? $archivoNuevoReq
            : $archivoOriginal;

        $archivoOriginalLimpio = preg_replace('/\.pdf$/i', '', trim($archivoOriginal));
        $archivoNuevoLimpio    = preg_replace('/\.pdf$/i', '', trim($archivoNuevo));

        // Determinar acción exacta:
        // Si el nombre original y el nuevo solo varían por la cantidad de ceros a la izquierda en el campo 3 (Libro),
        // se considera VALIDADO (no renombrado) y se deja constancia en observaciones/detalles.
        $notaCerosLibro = null;
        $esIgualPorCerosEnLibro = false;
        if (strcasecmp($archivoOriginalLimpio, $archivoNuevoLimpio) !== 0) {
            $pOrig = explode('_', str_replace('-', '_', $archivoOriginalLimpio));
            $pNuev = explode('_', str_replace('-', '_', $archivoNuevoLimpio));
            if (
                count($pOrig) === count($pNuev) &&
                count($pOrig) >= 9 &&
                ctype_digit($pOrig[2]) &&
                ctype_digit($pNuev[2]) &&
                (int) $pOrig[2] === (int) $pNuev[2]
            ) {
                $restoIguales = true;
                foreach ($pOrig as $idxSeg => $valSeg) {
                    if ($idxSeg === 2) {
                        continue;
                    }
                    if (strcasecmp($valSeg, $pNuev[$idxSeg]) !== 0) {
                        $restoIguales = false;
                        break;
                    }
                }
                if ($restoIguales) {
                    $esIgualPorCerosEnLibro = true;
                    $notaCerosLibro = "Validado por diferencia de ceros (0) en el campo Libro ({$pOrig[2]} -> {$pNuev[2]}).";
                }
            }
        }

        $esSelloAgregado = ($modo === 'agregar_sello');
        if ($modo === 'inconsistencia') {
            $accion = 'inconsistencia';
        } elseif ($modo === 'mover_libros') {
            $accion = 'mover_libros';
        } elseif ($modo === 'eliminar') {
            $accion = 'eliminar';
        } elseif ($modo === 'duplicar') {
            $accion = 'duplicar';
        } elseif ($esSelloAgregado) {
            $accion = 'renombrado';
        } else {
            $accion = (strcasecmp($archivoOriginalLimpio, $archivoNuevoLimpio) === 0 || $esIgualPorCerosEnLibro)
                ? 'validado'
                : 'renombrado';
        }

        $delegacionId = $request->input('delegacion_id') ?: $sesionActiva?->delegacion_id ?: $asignacion?->delegacion_id;
        $delegacionObj = $delegacionId ? Delegaciones::find($delegacionId) : null;
        $delegacionNombre = $delegacionObj?->nombre ?? 'GENERAL';
        $tipoSello = $request->input('tipo_sello') ?: $asignacion?->tipo_sello;

        // Extraer carpeta/lote real desde ruta_origen si no viene explícito o si venía igual al nombre de archivo
        $carpetaPadre = $rutaOrigen !== '' ? basename(dirname(str_replace('\\', '/', $rutaOrigen))) : '';
        $loteReq = trim((string) ($request->input('nombre_lote') ?: $request->input('lote') ?: ''));
        $lote = ($carpetaPadre !== '' && $carpetaPadre !== '.' && $carpetaPadre !== '/')
            ? $carpetaPadre
            : ($loteReq !== '' ? $loteReq : 'LOTE_GENERAL');

        $directorio = $request->input('directorio') ?: $request->input('disco') ?: ($sesionActiva?->disco ?: $delegacionNombre);
        $paginas = $request->filled('paginas') && (int) $request->input('paginas') > 0
            ? (int) $request->input('paginas')
            : (int) ($sesionActiva?->paginas ?: 1);
        $hashPdf = trim((string) $request->input('hash', '')) ?: ($sesionActiva?->hash_pdf ?: null);

        // Catálogo de inconsistencias (R01-R09)
        $codigoInconsistencia = null;
        $estadoInconsistencia = null;
        $comentarioInconsistencia = null;
        $detallesBase = (string) $request->input('detalles', '');

        if ($accion === 'inconsistencia') {
            $codigoInconsistencia = strtoupper(trim((string) $request->input('inconsistencia_codigo', 'R01')));
            $catInc = CatalogoInconsistencia::where('codigo', $codigoInconsistencia)->first();
            $estadoInconsistencia = 'pendiente';
            $comentarioInconsistencia = trim((string) $request->input('inconsistencia_comentario', ''));
            if ($catInc) {
                $detallesBase = trim("{$catInc->codigo}: {$catInc->motivo_rechazo} | Acción: {$catInc->accion_correctiva}" . ($comentarioInconsistencia ? " | Nota: {$comentarioInconsistencia}" : ''));
            }
        }

        if ($accion === 'mover_libros' && $detallesBase === '') {
            $detallesBase = 'Marcado para mover a Libros (solo registro en BD, sin movimiento físico).';
        }
        if ($accion === 'eliminar' && $detallesBase === '') {
            $detallesBase = 'Marcado como eliminar con autorización (solo registro en BD, sin eliminar archivo físico).';
        }

        if ($esSelloAgregado && $detallesBase === '') {
            $detallesBase = 'Sello adicional agregado desde el mismo PDF.';
        }
        if ($notaCerosLibro) {
            $detallesBase = $notaCerosLibro;
        }

        // Verificar existencia en indice_archivos_pdfs (Stellum) para detectar colisión en entregables
        $revisar = false;
        $archivoColisionId = null;
        $rutaColision = null;
        $decisionRevision = null;
        $motivosRevision = [];

        if ($archivoNuevoLimpio !== '' && !in_array($accion, ['mover_libros', 'eliminar'], true)) {
            $bridge = app(StellumBridgeService::class);
            // Cuando se agrega un sello nuevo ($esSelloAgregado), no excluir el indice_pdf_id actual para verificar toda la tabla indice_archivos_pdfs
            $idExcluir = $esSelloAgregado ? null : $indicePdfId;
            $check = $bridge->verificarExistenciaEnEntregables($archivoNuevoLimpio, $tipoDocumento, $idExcluir);
            if (!empty($check['existe']) && !empty($check['coincidencia'])) {
                $col = $check['coincidencia'];
                $revisar = true;
                $archivoColisionId = $col['id'] ?? null;
                $rutaColision = $col['ruta'] ?? null;
                $decisionRevision = 'pendiente';
                $avisoColision = $esSelloAgregado
                    ? "Enviado a revisión porque al agregar el sello ya existe un PDF con el nombre '{$archivoNuevoLimpio}' en indice_archivos_pdfs (ID: {$archivoColisionId}, Ruta: {$rutaColision})."
                    : "El archivo ya se encuentra en los entregables (ID: {$archivoColisionId}, Ruta: {$rutaColision}).";
                $motivosRevision[] = $avisoColision;
                $detallesBase = trim($detallesBase . ' ' . $avisoColision);
            }
        }

        // Regla de mismo hash / sello agregado: si el PDF pertenece a un grupo con el mismo hash (o es un sello agregado sobre el mismo PDF)
        // y la sección capturada es > 01, marcar internamente para revisión en BD.
        if (in_array($accion, ['validado', 'renombrado'], true) && ($hashPdf || $esSelloAgregado)) {
            $esGrupoMismoHash = $esSelloAgregado
                || $request->boolean('mismo_hash')
                || ($hashPdf && ArchivoSesion::where('hash_pdf', $hashPdf)->count() > 1);
            if ($esGrupoMismoHash) {
                $partesNombre = explode('_', str_replace('-', '_', $archivoNuevoLimpio ?: $archivoOriginalLimpio));
                $seccionStr = isset($partesNombre[1]) ? trim($partesNombre[1]) : '';
                if ($seccionStr !== '' && is_numeric($seccionStr) && (int) $seccionStr > 1) {
                    $revisar = true;
                    $decisionRevision = 'pendiente';
                }
            }
        }

        // Calcular ruta_propuesta conservando la ruta de entregables (null si es mover_libros o eliminar)
        $rutaPropuesta = in_array($accion, ['mover_libros', 'eliminar'], true)
            ? null
            : RutaPropuestaService::generar($tipoDocumento, [
                'ruta_origen'           => $rutaOrigen,
                'delegacion'            => $delegacionNombre,
                'tipo_sello'            => $tipoSello ?: 'GENERAL',
                'lote'                  => $lote,
                'directorio'            => $directorio,
                'inconsistencia_codigo' => $codigoInconsistencia,
                'archivo_original'      => $archivoOriginalLimpio,
                'archivo_nuevo'         => $archivoNuevoLimpio,
            ], $accion);

        $tabla = match ($tipoDocumento) {
            'sellos'   => 'auditoria_sellos',
            'notarias' => 'auditoria_notarias',
            'catastro' => 'auditoria_catastro',
            default    => 'auditoria_registros',
        };

        $segs = ($sesionActiva && $sesionActiva->tomado_en)
            ? (int) max(0, round($sesionActiva->tomado_en->diffInSeconds(now(), false)))
            : null;

        $registro = [
            'fecha_hora'                => now()->format('Y-m-d H:i:s'),
            'turno'                     => $usuario?->turnoRelacion?->nombre ?? $usuario?->turno ?? 'Matutino',
            'user_id'                   => $usuario?->id,
            'categoria'                 => 'Captura',
            'pc'                        => $this->obtenerPcActual($request, $usuario),
            'directorio'                => $directorio,
            'lote'                      => $lote,
            'accion'                    => $accion,
            'archivo_original'          => $archivoOriginalLimpio,
            'archivo_nuevo'             => $archivoNuevoLimpio,
            'ruta'                      => $rutaOrigen,
            'ruta_origen'               => $rutaOrigen,
            'ruta_propuesta'            => $rutaPropuesta,
            'ruta_destino'              => null,
            'hash'                      => $hashPdf,
            'archivo_danado'            => false,
            'indice_pdf_id'             => $indicePdfId,
            'revisar'                   => $revisar,
            'archivo_colision_id'       => $archivoColisionId,
            'ruta_colision'             => $rutaColision,
            'decision_revision'         => $decisionRevision,
            'inconsistencia_codigo'     => $codigoInconsistencia,
            'inconsistencia_estado'     => $estadoInconsistencia,
            'inconsistencia_comentario' => $comentarioInconsistencia,
            'estado_aplicacion'         => 'pendiente',
            'detalles'                  => $detallesBase ?: null,
            'paginas'                   => $paginas,
            'tiempo_captura'            => $segs,
            'created_at'                => now(),
            'updated_at'                => now(),
        ];

        if (in_array($tipoDocumento, ['registros', 'sellos'])) {
            $registro['delegacion_id'] = $delegacionId;
        }
        if ($tipoDocumento === 'sellos') {
            $registro['tipo_sello'] = $tipoSello;
        }

        $auditoriaId = DB::table($tabla)->insertGetId($registro);

        // Cerrar la sesión individual del archivo actual y registrar tiempo real de captura
        // (si es un sello agregado y aún quedan otros registros pendientes en el mismo PDF, mantener la sesión abierta)
        $mantenerSesionAbierta = $esSelloAgregado && $request->boolean('quedan_pendientes_en_pdf');
        if ($accion !== 'duplicar' && !$mantenerSesionAbierta && $sesionActiva && $sesionActiva->estado === 'en_captura') {
            $sesionActiva->update([
                'estado'          => 'completado',
                'completado_en'   => now(),
                'tiempo_segundos' => $segs,
            ]);
        }

        // Si es inconsistencia, notificar en tiempo real a la campana del navbar del administrador
        if ($accion === 'inconsistencia') {
            try {
                broadcast(new InconsistenciaReportadaEvent([
                    'id'                    => $auditoriaId,
                    'tipo_documento'        => $tipoDocumento,
                    'capturista'            => $usuario?->name ?? 'Capturista',
                    'inconsistencia_codigo' => $codigoInconsistencia,
                    'archivo_original'      => $archivoOriginalLimpio,
                    'lote'                  => $lote,
                    'detalles'              => $detallesBase,
                ]));
            } catch (\Throwable $e) {
                // Continuar aunque Reverb no esté corriendo
            }
        }

        return response()->json([
            'ok'                               => true,
            'solo_bd'                          => true,
            'auditoria_id'                     => $auditoriaId,
            'accion'                           => $accion,
            'es_sello_agregado'                => $esSelloAgregado,
            'revisar'                          => $revisar,
            'motivo_revision'                  => $revisar ? implode(' ', $motivosRevision) : null,
            'ruta_origen'                      => $rutaOrigen,
            'ruta_propuesta'                   => $rutaPropuesta,
            'ruta_nueva'                       => $rutaOrigen,
            'paginas'                          => $paginas,
            'fuera_horario_despues_de_guardar' => (bool) ($evalHorario['ultimo_registro_gracia'] ?? false),
            'aviso_horario'                    => ($evalHorario['ultimo_registro_gracia'] ?? false) ? $evalHorario['mensaje'] : null,
            'mensaje'                          => $revisar
                ? ('Registrado en BD y enviado a revisión: ' . implode(' ', $motivosRevision))
                : "Acción '{$accion}' registrada únicamente en BD (sin modificar archivo físico).",
        ]);
    }

    public function renombrar(Request $request)
    {
        return $this->registrarAccionSoloBd($request, 'auto');
    }

    public function agregarSello(Request $request)
    {
        return $this->registrarAccionSoloBd($request, 'agregar_sello');
    }

    public function validar(Request $request)
    {
        return $this->registrarAccionSoloBd($request, 'auto');
    }

    public function duplicar(Request $request)
    {
        return $this->registrarAccionSoloBd($request, 'duplicar');
    }

    public function inconsistencia(Request $request)
    {
        return $this->registrarAccionSoloBd($request, 'inconsistencia');
    }

    public function moverLibros(Request $request)
    {
        return $this->registrarAccionSoloBd($request, 'mover_libros');
    }

    public function eliminar(Request $request)
    {
        return $this->registrarAccionSoloBd($request, 'eliminar');
    }

    /**
     * Pospone ("skipea") el PDF actual previa autorización del supervisor y programa su liberación
     * exclusivamente para el mismo usuario al día siguiente al inicio de su turno.
     */
    public function posponerPdf(Request $request)
    {
        $usuario = Auth::user();
        if (!$usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'Sesión no válida.'], 401);
        }

        $sesionIdReq = $request->input('sesion_id') ? (int) $request->input('sesion_id') : null;
        $sesion = null;
        if ($sesionIdReq) {
            $sesion = ArchivoSesion::where('id', $sesionIdReq)
                ->where('user_id', $usuario->id)
                ->first();
        }
        if (!$sesion) {
            $sesion = ArchivoSesion::where('user_id', $usuario->id)
                ->where('estado', 'en_captura')
                ->latest('id')
                ->first();
        }

        if (!$sesion) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'No se encontró una sesión activa del PDF para posponer.',
            ], 404);
        }

        $tz = config('app.timezone', 'America/Mexico_City');
        $turnoObj = $usuario->turnoRelacion;
        $nombreTurno = $turnoObj?->nombre ?? ($usuario->turno ?: 'Matutino');
        $horaInicio = $turnoObj?->hora_inicio ?: '08:00:00';
        if (strlen($horaInicio) === 5) {
            $horaInicio .= ':00';
        }

        $fechaManana = Carbon::now($tz)->addDay()->toDateString();
        $liberadoAPartirDe = Carbon::parse("{$fechaManana} {$horaInicio}", $tz);
        $motivo = trim((string) $request->input('motivo', 'Pospuesto con autorización para revisar en el siguiente turno'));

        $sesion->update([
            'estado'               => 'pospuesto',
            'user_id'              => $usuario->id,
            'liberado_a_partir_de' => $liberadoAPartirDe,
            'turno_pospuesto'      => $nombreTurno,
            'motivo_pospuesto'     => $motivo,
            'expira_en'            => null,
        ]);

        return response()->json([
            'ok'                   => true,
            'sesion_id'            => $sesion->id,
            'liberado_a_partir_de' => $liberadoAPartirDe->format('d/m/Y H:i'),
            'turno'                => $nombreTurno,
            'mensaje'              => "PDF pospuesto correctamente. Se liberará de nuevo para ti mañana ({$liberadoAPartirDe->format('d/m/Y H:i')}) en tu turno ({$nombreTurno}).",
        ]);
    }

    /**
     * Detecta el año del nombre del archivo (segunda parte separada por _).
     */
    private function detectarAnioDelNombre(string $nombre): ?string
    {
        $partes = explode('_', $nombre);
        if (count($partes) >= 2) {
            $anio = $partes[1];
            if (preg_match('/^\d{4}$/', $anio) && (int) $anio >= 2000 && (int) $anio <= 2100) {
                return $anio;
            }
        }
        return null;
    }

    // =========================================================
    // ROTAR PÁGINA
    // =========================================================

    /**
     * Rota una página específica de un PDF usando la librería FPDI/TCPDF.
     * (Requiere paquete setasign/fpdi instalado.)
     */
    /**
     * Rota una página específica de un PDF usando la librería FPDI.
     */
    public function rotar(Request $request)
    {
        $disco = (string) $request->input('disco', '');
        $rutaArchivo = (string) $request->input('ruta_archivo', '');
        $pagina = max(1, (int) $request->input('pagina', 1));
        $grados = (int) $request->input('grados', 90);
        $delegacionId = $request->input('delegacion_id');
        $lote = (string) $request->input('lote', 'LOTE');

        $rutaConfigurada = $this->obtenerRutaRaizConfigurada($disco);
        if (!in_array($disco, ['irec', 'cats', 'rciv']) && !$rutaConfigurada) {
            return response()->json(['ok' => false, 'mensaje' => 'Disco o ruta configurada no válida para rotar.']);
        }

        $storage = in_array($disco, ['irec', 'cats', 'rciv'])
            ? Storage::disk($disco)
            : Storage::build(['driver' => 'local', 'root' => $rutaConfigurada]);

        $rutaNormalizada = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaArchivo);
        $rutaArchivoFisica = $storage->path($rutaNormalizada);

        if (!file_exists($rutaArchivoFisica)) {
            return response()->json(['ok' => false, 'mensaje' => 'El archivo físico no existe en el directorio configurado.']);
        }

        try {
            $pdfContenido = file_get_contents($rutaArchivoFisica);

            $tempOri = tempnam(sys_get_temp_dir(), 'pdf_ori_');
            $tempDest = tempnam(sys_get_temp_dir(), 'pdf_dest_');
            file_put_contents($tempOri, $pdfContenido);

            $pdf = new RotatableFpdi();
            $cantPaginas = $pdf->setSourceFile($tempOri);

            if ($pagina > $cantPaginas) {
                return response()->json([
                    'ok'      => false,
                    'mensaje' => "La página {$pagina} excede el total de páginas del PDF ({$cantPaginas}).",
                ]);
            }

            for ($i = 1; $i <= $cantPaginas; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);

                $wOrig = $size['width'];
                $hOrig = $size['height'];

                if ($i === $pagina) {
                    $anguloRotacion = ($grados + 360) % 360;
                    if ($anguloRotacion === 90 || $anguloRotacion === 270) {
                        $wNueva = $hOrig;
                        $hNueva = $wOrig;
                        $pdf->AddPage($wNueva > $hNueva ? 'L' : 'P', [$wNueva, $hNueva]);
                        $pdf->Rotate($anguloRotacion, $wNueva / 2, $hNueva / 2);
                        $pdf->useTemplate($tpl, ($wNueva - $wOrig) / 2, ($hNueva - $hOrig) / 2, $wOrig, $hOrig);
                        $pdf->Rotate(0);
                    } elseif ($anguloRotacion === 180) {
                        $pdf->AddPage($wOrig > $hOrig ? 'L' : 'P', [$wOrig, $hOrig]);
                        $pdf->Rotate(180, $wOrig / 2, $hOrig / 2);
                        $pdf->useTemplate($tpl, 0, 0, $wOrig, $hOrig);
                        $pdf->Rotate(0);
                    } else {
                        $pdf->AddPage($wOrig > $hOrig ? 'L' : 'P', [$wOrig, $hOrig]);
                        $pdf->useTemplate($tpl, 0, 0, $wOrig, $hOrig);
                    }
                } else {
                    $pdf->AddPage($wOrig > $hOrig ? 'L' : 'P', [$wOrig, $hOrig]);
                    $pdf->useTemplate($tpl, 0, 0, $wOrig, $hOrig);
                }
            }

            $pdf->Output($tempDest, 'F');
            file_put_contents($rutaArchivoFisica, file_get_contents($tempDest));

            // Registrar en auditoría qué página se rotó físicamente
            $usuario = Auth::user();
            $nombreSinExt = pathinfo($rutaArchivoFisica, PATHINFO_FILENAME);
            DB::table('auditoria_sellos')->insert([
                'fecha_hora'       => now()->format('Y-m-d H:i:s'),
                'turno'            => $usuario?->turnoRelacion?->nombre ?? $usuario?->turno ?? 'Matutino',
                'user_id'          => $usuario?->id,
                'categoria'        => 'Rotación',
                'pc'               => $request->ip(),
                'directorio'       => $disco,
                'lote'             => $lote,
                'accion'           => 'rotar_pagina',
                'archivo_original' => $nombreSinExt,
                'archivo_nuevo'    => $nombreSinExt,
                'ruta'             => $rutaArchivoFisica,
                'ruta_origen'      => $rutaArchivoFisica,
                'ruta_propuesta'   => $rutaArchivoFisica,
                'ruta_destino'     => $rutaArchivoFisica,
                'delegacion_id'    => $delegacionId,
                'paginas'          => $cantPaginas,
                'detalles'         => "Página {$pagina} de {$cantPaginas} rotada {$grados}° físicamente en el PDF.",
                'estado_aplicacion'=> 'aplicado',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return response()->json([
                'ok'           => true,
                'pagina'       => $pagina,
                'total_paginas'=> $cantPaginas,
                'mensaje'      => "Página {$pagina} rotada {$grados}° físicamente y registrada en auditoría.",
            ]);
        } catch (\Exception $e) {
            \Log::error("Error al rotar PDF: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Error al rotar el PDF: ' . $e->getMessage(),
            ]);
        } finally {
            if (isset($tempOri) && file_exists($tempOri)) {
                @unlink($tempOri);
            }
            if (isset($tempDest) && file_exists($tempDest)) {
                @unlink($tempDest);
            }
        }
    }

    // =========================================================
    // LIBERAR LOTE
    // =========================================================

    /**
     * Libera la sesión de archivo individual activa del usuario.
     */
    public function liberarLote(Request $request)
    {
        $sesionId = $request->input('lote_id');
        $query = ArchivoSesion::where('user_id', Auth::id())->where('estado', 'en_captura');
        if ($sesionId) {
            $query->where('id', $sesionId);
        }

        if ($query->delete() > 0) {
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false, 'mensaje' => 'No hay sesión de archivo activa para liberar.']);
    }

    // =========================================================
    // AUDITORÍA (desde la web)
    // =========================================================

    /**
     * Registra una auditoría de captura desde el frontend web.
     */
    public function auditoria(Request $request)
    {
        $usuario = Auth::user();

        $delegacionId = $request->input('delegacion_id');
        if ($delegacionId) {
            $delegacion = Delegaciones::find($delegacionId);
            if (!$delegacion) {
                $delegacionId = null;
            }
        }

        $rutaOrigen = $request->input('ruta_origen', $request->input('ruta'));
        $rutaDestino = $request->input('ruta_destino', $request->input('ruta'));
        $inspeccion = PdfService::inspeccionarPdf($rutaDestino ?: $rutaOrigen);

        DB::table('auditoria_registros')->insert([
            'fecha_hora' => now(),
            'turno' => $request->input('turno', $usuario ? $usuario->turno : null),
            'user_id' => $usuario ? $usuario->id : null,
            'categoria' => 'Captura',
            'pc' => $request->input('pc', 'Web'),
            'directorio' => $request->input('disco'),
            'delegacion_id' => $delegacionId,
            'lote' => $request->input('nombre_lote'),
            'accion' => $request->input('accion'),
            'archivo_original' => $request->input('archivo_original'),
            'archivo_nuevo' => $request->input('archivo_nuevo'),
            'ruta' => $request->input('ruta'),
            'ruta_origen' => $rutaOrigen,
            'ruta_destino' => $rutaDestino,
            'hash' => $request->input('hash', $inspeccion['hash']),
            'archivo_danado' => (bool) $request->input('archivo_danado', $inspeccion['archivo_danado']),
            'detalles' => $request->input('detalles'),
            'paginas' => (int) $request->input('paginas', $inspeccion['paginas'] ?: 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            broadcast(new LoteActualizadoEvent('auditoria_registrada', [
                'user_id' => $usuario?->id,
                'lote'    => $request->input('nombre_lote'),
                'accion'  => $request->input('accion'),
            ]));
        } catch (\Throwable $e) {
            // Continuar si el servidor WebSocket no está activo
        }

        return response()->json(['ok' => true]);
    }

    // =========================================================
    // API LEGADA (registrar desde VB.NET)
    // =========================================================

    /**
     * Registra auditorías de captura provenientes de la aplicación VB.NET.
     */
    public function registrar(Request $request)
    {
        $datos = $request->all();
        // \Log::info("Auditoría recibida desde la aplicación de escritorio:", ['datos' => $datos]);

        $registrosAGuardar = [];

        if (isset($datos['Registros']) && is_array($datos['Registros'])) {
            $registrosAGuardar = $datos['Registros'];
        } elseif (isset($datos[0]) && is_array($datos[0])) {
            $registrosAGuardar = $datos;
        } else {
            $registrosAGuardar[] = $datos;
        }

        $insertados = 0;
        $fechaActual = now();
        $errores = [];

        foreach ($registrosAGuardar as $index => $originalReg) {
            if (empty($originalReg) || !is_array($originalReg)) {
                continue;
            }

            try {
                $reg = array_change_key_case($originalReg, CASE_LOWER);
                $fechaHora = $reg['fechahora'] ?? $reg['fecha_hora'] ?? null;

                if (!$fechaHora) {
                    $errores[] = "Registro {$index}: Falta el campo de fecha y hora.";
                    \Log::warning("Registro de auditoría sin fecha_hora:", ['registro' => $originalReg]);
                    continue;
                }

                // Prevenir que registros con fecha del futuro (relojes desajustados) entren a la BD
                $timestampRecibido = strtotime($fechaHora);
                if ($timestampRecibido !== false && $timestampRecibido > time() + 300) {
                    $fechaHora = now()->toDateTimeString();
                }

                $paginasVal = 0;
                if (isset($reg['paginas'])) {
                    $paginasVal = (int) $reg['paginas'];
                } elseif (isset($reg['pagina'])) {
                    $paginasVal = (int) $reg['pagina'];
                }

                // Resolver user_id
                $userIdDirecto = $reg['userid'] ?? $reg['user_id'] ?? null;
                $userVal = $reg['usuario'] ?? null;
                $nombreVal = $reg['nombrecompleto'] ?? $reg['nombre_completo'] ?? null;
                $userId = null;

                if ($userIdDirecto && (int) $userIdDirecto > 0) {
                    $userId = (int) $userIdDirecto;
                } elseif ($userVal || $nombreVal) {
                    $userQuery = User::query();
                    if ($userVal) {
                        $userQuery->where('user', $userVal);
                    }
                    if ($nombreVal) {
                        $userQuery->orWhere('name', $nombreVal);
                    }
                    $userObj = $userQuery->first();
                    if ($userObj) {
                        $userId = $userObj->id;
                    }
                }

                // Resolver delegacion_id
                $delIdDirecto = $reg['delegacionidnumerico'] ?? $reg['delegacion_id_numerico'] ?? null;
                $delIdInput = $reg['delegacionid'] ?? $reg['delegacion_id'] ?? null;
                $delNombreInput = $reg['delegacionnombre'] ?? $reg['delegacion_nombre'] ?? null;
                $delegacionId = null;

                if ($delIdDirecto && (int) $delIdDirecto > 0) {
                    $delegacionId = (int) $delIdDirecto;
                } elseif ($delIdInput || $delNombreInput) {
                    $delQuery = Delegaciones::query();
                    if ($delIdInput) {
                        if (is_numeric($delIdInput)) {
                            $delQuery->where('id', $delIdInput)->orWhere('clave', $delIdInput);
                        } else {
                            $delQuery->where('clave', $delIdInput);
                        }
                    }
                    if ($delNombreInput) {
                        $delQuery->orWhere('nombre', $delNombreInput);
                    }
                    $delObj = $delQuery->first();
                    if ($delObj) {
                        $delegacionId = $delObj->id;
                    }
                }

                if ($userId && $userId > 0) {
                    $this->asegurarUsuarioExiste($userId, [
                        'user' => $userVal,
                        'name' => $nombreVal
                    ]);
                }

                if ($delegacionId && $delegacionId > 0) {
                    $this->asegurarDelegacionExiste($delegacionId, [
                        'nombre' => $delNombreInput,
                        'clave' => $delIdInput
                    ]);
                }

                // Prevenir duplicados: verificar si ya existe un registro idéntico
                $accionVal = $reg['accion'] ?? null;
                $archivoOriginalVal = $reg['archivooriginal'] ?? $reg['archivo_original'] ?? null;

                $duplicado = DB::table('auditoria_registros')
                    ->where('fecha_hora', $fechaHora)
                    ->where('accion', $accionVal)
                    ->where('archivo_original', $archivoOriginalVal)
                    ->where(function ($q) use ($userId, $reg) {
                        if ($userId) {
                            $q->where('user_id', $userId);
                        } else {
                            $q->where('pc', $reg['pc'] ?? null);
                        }
                    })
                    ->exists();

                if ($duplicado) {
                    continue; // Ya existe, saltar sin error
                }

                $rutaVal = $reg['ruta'] ?? null;
                $rutaOrigenVal = $reg['ruta_origen'] ?? $reg['rutaorigen'] ?? $rutaVal;
                $rutaDestinoVal = $reg['ruta_destino'] ?? $reg['rutadestino'] ?? $rutaVal;
                $inspeccion = PdfService::inspeccionarPdf($rutaDestinoVal ?: $rutaOrigenVal);

                DB::table('auditoria_registros')->insert([
                    'fecha_hora' => $fechaHora,
                    'turno' => $reg['turno'] ?? null,
                    'user_id' => $userId,
                    'categoria' => $reg['categoria'] ?? null,
                    'pc' => $reg['pc'] ?? null,
                    'directorio' => $reg['directorio'] ?? null,
                    'delegacion_id' => $delegacionId,
                    'lote' => $reg['lote'] ?? null,
                    'accion' => $accionVal,
                    'archivo_original' => $archivoOriginalVal,
                    'archivo_nuevo' => $reg['archivonuevo'] ?? $reg['archivo_nuevo'] ?? null,
                    'ruta' => $rutaVal,
                    'ruta_origen' => $rutaOrigenVal,
                    'ruta_destino' => $rutaDestinoVal,
                    'hash' => $reg['hash'] ?? $inspeccion['hash'],
                    'archivo_danado' => (bool) ($reg['archivo_danado'] ?? $inspeccion['archivo_danado']),
                    'detalles' => $reg['detalles'] ?? null,
                    'paginas' => $paginasVal ?: $inspeccion['paginas'],
                    'tiempo_captura' => $reg['tiempo_captura'] ?? $reg['tiempocaptura'] ?? null,
                    'created_at' => $fechaActual,
                    'updated_at' => $fechaActual,
                ]);
                $insertados++;
            } catch (\Exception $e) {
                $errores[] = "Registro {$index}: Error de base de datos: " . $e->getMessage();
                \Log::error("Error al insertar auditoría en la base de datos:", ['registro' => $originalReg, 'error' => $e->getMessage()]);
            }
        }

        if ($insertados === 0) {
            \Log::warning("No se insertó ninguna auditoría en el endpoint registrar.", ['errores' => $errores]);
            return response()->json([
                'ok' => false,
                'mensaje' => "No se pudo registrar ninguna auditoría.",
                'errores' => $errores,
            ], 400);
        }

        // Actualizar updated_at de la sesión activa del lote del usuario para marcar actividad reciente
        if ($insertados > 0 && isset($userId)) {
            try {
                LoteCaptura::where('user_id', $userId)
                    ->where('estado', 'en_proceso')
                    ->update(['updated_at' => now()]);
            } catch (\Exception $e) {
                // Falla silenciosa si no hay sesión activa
            }
        }

        // \Log::info("Auditorías registradas exitosamente en el endpoint:", ['cantidad' => $insertados]);
        return response()->json([
            'ok' => true,
            'mensaje' => "Se registraron exitosamente $insertados auditorías.",
            'procesados' => $insertados,
            'errores' => $errores,
        ]);
    }

    /**
     * Cuenta el número total de páginas de un archivo PDF en el disco.
     */
    private function contarPaginasPdf($disco, $ruta)
    {
        try {
            $storage = Storage::disk($disco);
            if (!$storage->exists($ruta)) {
                return 0;
            }
            $pdfContenido = $storage->get($ruta);
            // Crear archivo temporal en el directorio temporal del sistema
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_count_');
            file_put_contents($tempFile, $pdfContenido);

            $pdf = new RotatableFpdi();
            $cantPaginas = $pdf->setSourceFile($tempFile);

            return (int) $cantPaginas;
        } catch (\Exception $e) {
            \Log::error("Error al contar páginas del PDF: " . $e->getMessage());
            return 0;
        } finally {
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    // =========================================================
    // SESIONES ACTIVAS (API para VB.NET — reemplaza archivo .lock)
    // =========================================================

    /**
     * Registra o actualiza la sesión activa de un usuario desde la app de escritorio.
     * Se usa como heartbeat para saber quién está trabajando.
     */
    /**
     * Asegura que el usuario con el ID especificado existe en la base de datos.
     * Si no existe, lo crea automáticamente para evitar errores de integridad.
     */
    private function asegurarUsuarioExiste($userId, $datosComplementarios = [])
    {
        if (!$userId || (int) $userId <= 0) {
            return;
        }

        $userExists = User::where('id', $userId)->exists();
        if (!$userExists) {
            $username = trim(strtolower($datosComplementarios['user'] ?? ('usuario_' . $userId)));
            $nombre = trim($datosComplementarios['name'] ?? ('Usuario Autocreado ' . $userId));

            if (User::where('user', $username)->exists()) {
                $username = $username . '_' . time();
            }

            $email = $username . '@gmail.com';
            if (User::where('email', $email)->exists()) {
                $email = $username . '_' . time() . '@gmail.com';
            }

            User::forceCreate([
                'id' => $userId,
                'name' => $nombre,
                'user' => $username,
                'email' => $email,
                'password' => bcrypt('password123'),
                'estatus' => 1,
            ]);
        }
    }

    /**
     * Asegura que la delegación con el ID especificado existe en la base de datos.
     * Si no existe, la crea automáticamente.
     */
    private function asegurarDelegacionExiste($delegacionId, $datosComplementarios = [])
    {
        if (!$delegacionId || (int) $delegacionId <= 0) {
            return;
        }

        $delExists = Delegaciones::where('id', $delegacionId)->exists();
        if (!$delExists) {
            $nombre = $datosComplementarios['nombre'] ?? ('Delegación ' . $delegacionId);
            $clave = $datosComplementarios['clave'] ?? ('DEL_' . $delegacionId);

            if (Delegaciones::where('clave', $clave)->exists()) {
                $clave = $clave . '_' . time();
            }

            Delegaciones::forceCreate([
                'id' => $delegacionId,
                'nombre' => $nombre,
                'clave' => $clave,
                'estatus' => 1,
            ]);
        }
    }

    /**
     * Registra o actualiza la sesión activa de un usuario desde la app de escritorio (Actas).
     * Se usa como heartbeat para saber quién está trabajando.
     */
    public function registrarSesion(Request $request)
    {
        $userId = $request->input('user_id');
        $delegacionId = $request->input('delegacion_id');
        if (empty($delegacionId) || (int)$delegacionId <= 0) {
            $delegacionId = 1;
        }
        $pc = $request->input('pc');
        $directorio = strtolower($request->input('directorio'));
        $lote = $request->input('lote');
        $liberar = $request->input('liberar', false);
        $categoria = $request->input('categoria');
        $progresoArchivo = $request->input('progreso_archivo');

        if (!$userId) {
            return response()->json(['ok' => false, 'mensaje' => 'Se requiere user_id.'], 400);
        }

        if ($liberar) {
            ArchivoSesion::where('user_id', $userId)->where('estado', 'en_captura')->delete();
            return response()->json([
                'ok' => true,
                'mensaje' => 'Sesión de archivo liberada correctamente.',
            ]);
        }

        $rutaRelativa = $request->input('ruta_relativa');
        $rutaFinal = !empty($rutaRelativa) ? $rutaRelativa : ($directorio . '/' . $lote);
        $rutaHash = hash('sha256', (string) $rutaFinal);

        $this->asegurarUsuarioExiste($userId);
        if ($delegacionId) {
            $this->asegurarDelegacionExiste($delegacionId);
        }

        $resultado = DB::transaction(function () use ($userId, $directorio, $rutaFinal, $rutaHash, $delegacionId, $lote, $request) {
            ArchivoSesion::where('estado', 'en_captura')
                ->whereNotNull('expira_en')
                ->where('expira_en', '<', now())
                ->delete();

            $ocupante = ArchivoSesion::where('ruta_hash', $rutaHash)
                ->where('estado', 'en_captura')
                ->where('user_id', '!=', $userId)
                ->lockForUpdate()
                ->first();

            if ($ocupante) {
                $usuarioOcupante = User::find($ocupante->user_id);
                $nombreOcupante = $usuarioOcupante ? ($usuarioOcupante->name ?? $usuarioOcupante->user) : ('Usuario #' . $ocupante->user_id);
                return [
                    'ok' => false,
                    'mensaje' => "El archivo '{$lote}' ya está siendo trabajado por {$nombreOcupante}.",
                    'ocupado_por' => $nombreOcupante,
                    'ocupado_por_id' => $ocupante->user_id,
                ];
            }

            ArchivoSesion::where('user_id', $userId)->where('estado', 'en_captura')->delete();

            $sesion = ArchivoSesion::updateOrCreate(
                ['ruta_hash' => $rutaHash],
                [
                    'user_id'        => $userId,
                    'disco'          => $directorio ?: 'sellos',
                    'tipo_archivo'   => 'sellos',
                    'delegacion_id'  => $delegacionId,
                    'pc'             => $request->ip(),
                    'archivo_nombre' => $lote ?: basename((string) $rutaFinal),
                    'ruta_origen'    => (string) $rutaFinal,
                    'estado'         => 'en_captura',
                    'tomado_en'      => now(),
                    'expira_en'      => now()->addMinutes(15),
                    'completado_en'  => null,
                ]
            );

            return [
                'ok'        => true,
                'sesion_id' => $sesion->id,
                'mensaje'   => 'Sesión de archivo registrada correctamente.',
            ];
        });

        return response()->json($resultado, $resultado['ok'] ? 200 : 409);
    }

    /**
     * Obtiene la lista de usuarios con sesiones activas por archivo individual (archivos_sesiones).
     */
    public function obtenerSesiones(Request $request)
    {
        $sesionesUnificadas = ArchivoSesion::where('estado', 'en_captura')
            ->with(['usuario:id,name,user', 'delegacion:id,nombre,clave'])
            ->get()
            ->map(function ($s) {
                return [
                    'id'              => $s->id,
                    'user_id'         => $s->user_id,
                    'usuario'         => $s->usuario ? $s->usuario->name : null,
                    'nombre_usuario'  => $s->usuario ? $s->usuario->user : null,
                    'delegacion_id'   => $s->delegacion_id,
                    'delegacion'       => $s->delegacion ? $s->delegacion->nombre : null,
                    'directorio'       => $s->disco,
                    'lote'             => $s->archivo_nombre,
                    'pc'               => $s->pc,
                    'tomado_en'        => $s->tomado_en ? $s->tomado_en->toDateTimeString() : null,
                    'progreso_archivo' => $s->archivo_nombre,
                ];
            });

        return response()->json([
            'ok' => true,
            'sesiones' => $sesionesUnificadas,
        ]);
    }

    /**
     * Registra o actualiza la sesión activa de un capturista de libros.
     */
    public function registrarSesionLibros(Request $request)
    {
        $userId = $request->input('user_id');
        $pc = $request->input('pc');
        $directorio = strtolower($request->input('directorio'));
        $carpeta = $request->input('lote'); // Se recibe como lote pero se almacena en la columna carpeta
        $liberar = $request->input('liberar', false);

        if (!$userId) {
            return response()->json(['ok' => false, 'mensaje' => 'Se requiere user_id.'], 400);
        }

        if ($liberar) {
            LibrosSesion::where('user_id', $userId)->where('estado', 'en_proceso')->delete();
            return response()->json([
                'ok' => true,
                'mensaje' => 'Sesión liberada correctamente.',
            ]);
        }

        $rutaRelativa = $request->input('ruta_relativa');
        $rutaFinal = !empty($rutaRelativa) ? $rutaRelativa : $pc;

        // Asegurar que exista el usuario
        $this->asegurarUsuarioExiste($userId);

        // Transacción atómica con verificación de colisión
        $resultado = DB::transaction(function () use ($userId, $directorio, $rutaFinal, $carpeta, $pc) {
            // 1. Liberar automáticamente sesiones inactivas de este lote (más de 30 minutos sin actualizar)
            if ($directorio && $rutaFinal) {
                LibrosSesion::where('ruta_relativa', $rutaFinal)
                    ->where('disco', $directorio)
                    ->where('estado', 'en_proceso')
                    ->where('updated_at', '<', now()->subMinutes(30))
                    ->delete();

                // 2. Verificar si OTRO usuario ya tiene este lote/carpeta reservado
                $ocupante = LibrosSesion::where('ruta_relativa', $rutaFinal)
                    ->where('disco', $directorio)
                    ->where('estado', 'en_proceso')
                    ->where('user_id', '!=', $userId)
                    ->lockForUpdate()
                    ->first();

                if ($ocupante) {
                    // El lote ya está ocupado por otro usuario → RECHAZAR
                    $nombreOcupante = '';
                    $usuarioOcupante = User::find($ocupante->user_id);
                    if ($usuarioOcupante) {
                        $nombreOcupante = $usuarioOcupante->name ?? $usuarioOcupante->user ?? 'Usuario #' . $ocupante->user_id;
                    }
                    return [
                        'ok' => false,
                        'mensaje' => "El lote '{$carpeta}' ya está siendo trabajado por {$nombreOcupante}.",
                        'ocupado_por' => $nombreOcupante,
                        'ocupado_por_id' => $ocupante->user_id,
                    ];
                }
            }

            // 3. Limpiar sesiones previas del mismo usuario (un usuario solo puede tener 1 sesión activa)
            LibrosSesion::where('user_id', $userId)
                ->where('estado', 'en_proceso')
                ->delete();

            // 4. Crear la nueva sesión inmediatamente en libros_sesiones
            $sesion = LibrosSesion::create([
                'user_id' => $userId,
                'estado' => 'en_proceso',
                'disco' => $directorio ?? '',
                'carpeta' => $carpeta ?? '',
                'ruta_relativa' => $rutaFinal ?? '',
                'pc' => $pc,
                'tomado_en' => now(),
            ]);

            return [
                'ok' => true,
                'sesion_id' => $sesion->id,
                'mensaje' => 'Sesión registrada correctamente.',
            ];
        });

        $statusCode = $resultado['ok'] ? 200 : 409; // 409 = Conflicto
        return response()->json($resultado, $statusCode);
    }

    /**
     * Obtiene la lista de usuarios con sesiones activas de libros (en_proceso).
     */
    public function obtenerSesionesLibros(Request $request)
    {
        $sesiones = LibrosSesion::where('estado', 'en_proceso')
            ->with(['usuario:id,name,user'])
            ->get()
            ->map(function ($lote) {
                return [
                    'id' => $lote->id,
                    'user_id' => $lote->user_id,
                    'usuario' => $lote->usuario ? $lote->usuario->name : null,
                    'nombre_usuario' => $lote->usuario ? $lote->usuario->user : null,
                    'delegacion_id' => null,
                    'delegacion' => null,
                    'directorio' => $lote->disco,
                    'lote' => $lote->carpeta,
                    'pc' => $lote->ruta_relativa,
                    'tomado_en' => $lote->tomado_en ? $lote->tomado_en->toDateTimeString() : null,
                ];
            });

        return response()->json([
            'ok' => true,
            'sesiones' => $sesiones,
        ]);
    }

    /**
     * Obtiene el resumen de la jornada del usuario (conteo de archivos procesados hoy).
     */
    public function obtenerResumenDia($id)
    {
        try {
            // Contar registros de acciones de trabajo para el usuario hoy en capturas generales
            $hoy = now()->toDateString();
            $conteoCapturas = DB::table('auditoria_registros')
                ->where('user_id', $id)
                ->whereIn('accion', [
                    'Validado',
                    'Renombrar',
                    'Duplicar',
                    'Union',
                    'Dividir',
                    'Inconsistencia',
                    'MoverInconsistencia',
                    'Eliminado por Duplicado',
                    'Auto-Eliminado por Duplicado',
                    'Eliminado por Duplicado Local'
                ])
                ->where(function ($consulta) use ($hoy) {
                    $consulta->whereDate('fecha_hora', $hoy)
                        ->orWhereDate('created_at', $hoy);
                })
                ->count();

            // Contar registros de acciones de trabajo para el usuario hoy en notarias
            $conteoNotarias = DB::table('auditoria_notarias')
                ->where('user_id', $id)
                ->whereIn('accion', [
                    'Validado',
                    'Renombrar',
                    'Duplicar',
                    'Union',
                    'Dividir',
                    'Inconsistencia',
                    'MoverInconsistencia',
                    'Eliminado por Duplicado',
                    'Auto-Eliminado por Duplicado',
                    'Eliminado por Duplicado Local',
                    'Capturado'
                ])
                ->where(function ($consulta) use ($hoy) {
                    $consulta->whereDate('fecha_hora', $hoy)
                        ->orWhereDate('created_at', $hoy);
                })
                ->count();

            // Contar registros de acciones de trabajo para el usuario hoy en sellos
            $conteoSellos = DB::table('auditoria_sellos')
                ->where('user_id', $id)
                ->whereIn('accion', [
                    'Validado',
                    'Renombrar',
                    'Duplicar',
                    'Union',
                    'Dividir',
                    'Inconsistencia',
                    'MoverInconsistencia',
                    'Eliminado por Duplicado',
                    'Auto-Eliminado por Duplicado',
                    'Eliminado por Duplicado Local',
                    'Capturado',
                    'Recorte hojas',
                ])
                ->where(function ($consulta) use ($hoy) {
                    $consulta->whereDate('fecha_hora', $hoy)
                        ->orWhereDate('created_at', $hoy);
                })
                ->count();

            // Totalizar ambos conteos
            $conteo = $conteoCapturas + $conteoNotarias + $conteoSellos;

            // Mensajes variados motivacionales según el rendimiento
            if ($conteo <= 200) {
                $mensajes = [
                    "Buen esfuerzo el de hoy: :conteo archivos procesados. ¡Cada archivo cuenta! ✨",
                    "Hoy registraste :conteo archivos. ¡Sigue esforzándote para subir tu ritmo! 📈",
                    "Buen inicio de jornada, procesaste :conteo archivos. ¡Poco a poco se avanza! 👍"
                ];
            } elseif ($conteo <= 500) {
                $mensajes = [
                    "¡Buen ritmo! Hoy procesaste :conteo archivos. ¡Vas por muy buen camino! 🚀",
                    "Completaste :conteo archivos hoy. ¡Excelente trabajo y dedicación! 👏",
                    "Hoy cerraste con :conteo archivos. ¡Buen esfuerzo en esta sesión! 💪"
                ];
            } elseif ($conteo <= 800) {
                $mensajes = [
                    "¡Increíble trabajo! Lograste procesar :conteo archivos hoy. ¡Tienes un gran ritmo! ⚡",
                    "¡Meta del día superada! Completaste :conteo archivos hoy. ¡Felicidades! 🎉",
                    "¡Qué gran desempeño! Hoy procesaste :conteo archivos. ¡Muchas gracias por tu dedicación! 🌟"
                ];
            } else {
                $mensajes = [
                    "¡Impresionante! Hoy procesaste :conteo archivos. ¡Eres una máquina de validar! 🏆",
                    "¡Nivel leyenda! Lograste :conteo archivos procesados hoy. ¡Rendimiento fuera de serie! 👑",
                    "¡Espectacular desempeño hoy con :conteo archivos! Tu productividad es asombrosa. 🚀🔥"
                ];
            }

            // Seleccionar mensaje aleatorio del rango correspondiente
            $mensajeBase = $mensajes[array_rand($mensajes)];
            $mensajeFinal = str_replace(':conteo', $conteo, $mensajeBase);

            return response()->json([
                'success' => true,
                'conteo' => $conteo,
                'mensaje' => $mensajeFinal
            ]);
        } catch (\Exception $excepcion) {
            return response()->json([
                'success' => false,
                'mensaje' => 'No se pudo obtener el resumen de hoy.'
            ], 500);
        }
    }
}

/**
 * Clase auxiliar para permitir la rotación de páginas en plantillas PDF importadas con FPDI.
 */
if (!class_exists('App\Http\Controllers\RotatableFpdi')) {
    class RotatableFpdi extends \setasign\Fpdi\Fpdi
    {
        public $angle = 0;

        public function Rotate($angle, $x = -1, $y = -1)
        {
            if ($x == -1)
                $x = $this->x;
            if ($y == -1)
                $y = $this->y;
            if ($this->angle != 0)
                $this->_out('Q');
            $this->angle = $angle;
            if ($angle != 0) {
                $angle *= M_PI / 180;
                $c = cos($angle);
                $s = sin($angle);
                $cx = $x * $this->k;
                $cy = ($this->h - $y) * $this->k;
                $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
            }
        }

        protected function _endpage()
        {
            if ($this->angle != 0) {
                $this->angle = 0;
                $this->_out('Q');
            }
            parent::_endpage();
        }
    }
}
