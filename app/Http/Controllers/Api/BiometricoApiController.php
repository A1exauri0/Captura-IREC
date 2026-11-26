<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Huella;
use App\Models\Turno;
use App\Models\User;
use App\Services\AsistenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BiometricoApiController extends Controller
{
    /**
     * Verifica conectividad y devuelve reloj oficial del servidor.
     */
    public function ping(): JsonResponse
    {
        $ahora = now()->timezone(config('app.timezone', 'America/Mexico_City'));

        return response()->json([
            'ok'                => true,
            'sistema'           => config('app.name', 'Captura-IREC'),
            'version_api'       => '1.0',
            'timezone'          => $ahora->timezoneName,
            'hora_servidor'     => $ahora->format('Y-m-d H:i:s'),
            'hora_servidor_iso' => $ahora->toIso8601String(),
            'huellas_activas'   => Huella::where('activo', true)->count(),
            'usuarios_activos'  => User::where('estatus', true)->count(),
        ]);
    }

    /**
     * Valida credenciales de un administrador para desbloquear el enrolamiento local.
     */
    public function authAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user'     => 'required|string|max:150',
            'password' => 'required|string|max:150',
        ]);

        $login = trim($data['user']);
        $loginLower = mb_strtolower($login);
        $usuario = User::with('roles')
            ->where('user', $login)
            ->orWhere('email', $login)
            ->orWhereRaw('LOWER(user) = ?', [$loginLower])
            ->orWhereRaw('LOWER(email) = ?', [$loginLower])
            ->first();

        if (!$usuario || !Hash::check($data['password'], $usuario->password)) {
            return response()->json([
                'ok'    => false,
                'error' => 'Credenciales incorrectas.',
            ], 401);
        }

        if (!$usuario->estatus) {
            return response()->json([
                'ok'    => false,
                'error' => 'La cuenta se encuentra inactiva.',
            ], 403);
        }

        if (!$usuario->hasAnyRole(['superadmin', 'administrador'])) {
            return response()->json([
                'ok'    => false,
                'error' => 'Acceso denegado. Solo cuentas con rol de administrador pueden iniciar sesión en el checador.',
            ], 403);
        }

        return response()->json([
            'ok'    => true,
            'admin' => [
                'id'    => $usuario->id,
                'name'  => $usuario->name,
                'user'  => $usuario->user,
                'roles' => $usuario->getRoleNames()->values(),
            ],
        ]);
    }

    /**
     * Devuelve el catálogo de usuarios activos con su turno y número de huellas registradas.
     */
    public function usuarios(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = User::with('turnoRelacion')
            ->withCount(['huellasActivas as huellas_activas_count'])
            ->where('estatus', true)
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('user', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $usuarios = $query->get()->map(function (User $u) {
            $dedosActivos = Huella::where('user_id', $u->id)
                ->where('activo', true)
                ->distinct('dedo')
                ->count('dedo');

            return [
                'id'                    => $u->id,
                'name'                  => $u->name,
                'user'                  => $u->user,
                'email'                 => $u->email,
                'turno_id'              => $u->turno_id,
                'turno'                 => $u->turnoRelacion?->nombre ?? $u->turno ?? 'Sin turno',
                'hora_entrada'          => $u->turnoRelacion?->hora_entrada,
                'hora_salida'           => $u->turnoRelacion?->hora_salida,
                'tolerancia_minutos'    => $u->turnoRelacion?->tolerancia_minutos ?? config('services.biometrico.tolerancia_default', 10),
                'huellas_activas_count' => (int) $u->huellas_activas_count,
                'dedos_activos_count'   => $dedosActivos,
            ];
        })->values();

        return response()->json([
            'ok'                => true,
            'total'             => $usuarios->count(),
            'usuarios'          => $usuarios,
            'dedos'             => Huella::DEDOS_VALIDOS,
            'dedo_default'      => Huella::DEDO_DEFAULT,
            'muestras_por_dedo' => Huella::MUESTRAS_POR_DEDO,
        ]);
    }

    /**
     * Lista las huellas activas para identificación 1:N en memoria en la estación Python.
     * Soporta ?version_hash= para evitar retransferir plantillas si no hubo cambios.
     */
    public function huellas(Request $request): JsonResponse
    {
        $query = Huella::with(['usuario.turnoRelacion'])
            ->where('activo', true)
            ->whereHas('usuario', fn ($q) => $q->where('estatus', true));

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('formato')) {
            $query->where('formato', trim((string) $request->query('formato')));
        }

        $meta = (clone $query)->selectRaw('COUNT(*) as total, MAX(id) as max_id, MAX(updated_at) as max_updated')->first();
        $versionHash = sha1(($meta->total ?? 0) . '|' . ($meta->max_id ?? 0) . '|' . ($meta->max_updated ?? ''));

        if ($request->filled('version_hash') && hash_equals($versionHash, (string) $request->query('version_hash'))) {
            return response()->json([
                'ok'           => true,
                'sin_cambios'  => true,
                'version_hash' => $versionHash,
                'total'        => (int) ($meta->total ?? 0),
            ]);
        }

        $huellas = $query->orderBy('id')->get()->map(function (Huella $h) {
            return [
                'id'                 => $h->id,
                'user_id'            => $h->user_id,
                'user_name'          => $h->usuario?->name,
                'user_login'         => $h->usuario?->user,
                'turno'              => $h->usuario?->turnoRelacion?->nombre ?? $h->usuario?->turno ?? 'Sin turno',
                'dedo'               => $h->dedo,
                'dedo_nombre'        => $h->dedo_nombre,
                'numero_muestra'     => (int) ($h->numero_muestra ?? 1),
                'grupo_enrolamiento' => $h->grupo_enrolamiento,
                'etiqueta'           => $h->etiqueta,
                'template'           => $h->template,
                'formato'            => $h->formato,
                'calidad'            => $h->calidad,
                'dispositivo_modelo' => $h->dispositivo_modelo,
                'ultimo_uso_en'      => $h->ultimo_uso_en?->format('Y-m-d H:i:s'),
                'created_at'         => $h->created_at?->format('Y-m-d H:i:s'),
            ];
        })->values();

        return response()->json([
            'ok'           => true,
            'sin_cambios'  => false,
            'version_hash' => $versionHash,
            'total'        => $huellas->count(),
            'huellas'      => $huellas,
        ]);
    }

    /**
     * Registra un dedo enrolado guardando exactamente 3 huellas (muestras 1, 2 y 3) en `huellas`.
     * Si no se especifica `dedo`, toma por default `pulgar_derecho`.
     * Si el usuario ya tenía huellas activas de ese mismo dedo, las reemplaza (desactiva las previas).
     */
    public function storeHuella(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'            => 'required|integer|exists:users,id',
            'dedo'               => ['nullable', 'string', 'max:40', Rule::in(array_keys(Huella::DEDOS_VALIDOS))],
            'etiqueta'           => 'nullable|string|max:80',
            'template'           => 'nullable|string|min:8',
            'muestra_1'          => 'required_without:muestras|string|min:8',
            'muestra_2'          => 'required_without:muestras|string|min:8',
            'muestra_3'          => 'required_without:muestras|string|min:8',
            'muestras'           => 'nullable|array|size:3',
            'muestras.*.template'=> 'required_with:muestras|string|min:8',
            'muestras.*.calidad' => 'nullable|integer|min:0|max:100',
            'formato'            => 'required|string|max:40',
            'calidad'            => 'nullable|integer|min:0|max:100',
            'dispositivo_modelo' => 'nullable|string|max:100',
            'registrado_por'     => 'nullable|integer|exists:users,id',
        ]);

        $usuario = User::findOrFail($data['user_id']);
        if (!$usuario->estatus) {
            return response()->json([
                'ok'    => false,
                'error' => 'No se puede enrolar una huella para un usuario inactivo.',
            ], 422);
        }

        $dedo = !empty($data['dedo']) ? trim($data['dedo']) : Huella::DEDO_DEFAULT;
        $dedoNombre = Huella::DEDOS_VALIDOS[$dedo] ?? $dedo;
        $etiquetaBase = !empty($data['etiqueta']) ? trim($data['etiqueta']) : $dedoNombre;

        // Normalizar exactamente las 3 huellas a persistir para este dedo
        if (!empty($data['muestras']) && count($data['muestras']) === Huella::MUESTRAS_POR_DEDO) {
            $items = array_values($data['muestras']);
            $tresMuestras = [
                ['numero' => 1, 'template' => trim($items[0]['template']), 'calidad' => $items[0]['calidad'] ?? ($data['calidad'] ?? null)],
                ['numero' => 2, 'template' => trim($items[1]['template']), 'calidad' => $items[1]['calidad'] ?? ($data['calidad'] ?? null)],
                ['numero' => 3, 'template' => trim($items[2]['template']), 'calidad' => $items[2]['calidad'] ?? ($data['calidad'] ?? null)],
            ];
        } else {
            $tresMuestras = [
                ['numero' => 1, 'template' => trim($data['muestra_1']), 'calidad' => $data['calidad'] ?? null],
                ['numero' => 2, 'template' => trim($data['muestra_2']), 'calidad' => $data['calidad'] ?? null],
                ['numero' => 3, 'template' => trim($data['muestra_3']), 'calidad' => $data['calidad'] ?? null],
            ];
        }

        $hashes = array_map(fn ($m) => hash('sha256', $m['template']), $tresMuestras);

        // Impedir registrar plantillas idénticas que ya pertenezcan a OTRO usuario u OTRO dedo activo
        $duplicadaOtro = Huella::whereIn('template_hash', $hashes)
            ->where('activo', true)
            ->where(function ($q) use ($usuario, $dedo) {
                $q->where('user_id', '!=', $usuario->id)
                  ->orWhere('dedo', '!=', $dedo);
            })
            ->first();

        if ($duplicadaOtro) {
            return response()->json([
                'ok'    => false,
                'error' => 'Al menos una de las 3 huellas ya se encuentra registrada en otro usuario o dedo activo.',
            ], 422);
        }

        $grupoUuid = (string) Str::uuid();
        $creadas = DB::transaction(function () use ($usuario, $dedo, $etiquetaBase, $tresMuestras, $hashes, $data, $grupoUuid) {
            // Reemplazar (desactivar) huellas previas de este mismo usuario y mismo dedo para mantener exactamente 3 activas por dedo
            Huella::where('user_id', $usuario->id)
                ->where('dedo', $dedo)
                ->where('activo', true)
                ->update(['activo' => false]);

            $lista = [];
            foreach ($tresMuestras as $idx => $m) {
                $lista[] = Huella::create([
                    'user_id'            => $usuario->id,
                    'dedo'               => $dedo,
                    'numero_muestra'     => $m['numero'],
                    'grupo_enrolamiento' => $grupoUuid,
                    'etiqueta'           => "{$etiquetaBase} (Muestra {$m['numero']}/3)",
                    'template'           => $m['template'],
                    'muestra_1'          => $tresMuestras[0]['template'],
                    'muestra_2'          => $tresMuestras[1]['template'],
                    'muestra_3'          => $tresMuestras[2]['template'],
                    'template_hash'      => $hashes[$idx],
                    'formato'            => trim($data['formato']),
                    'calidad'            => $m['calidad'],
                    'dispositivo_modelo' => isset($data['dispositivo_modelo']) ? trim($data['dispositivo_modelo']) : null,
                    'registrado_por'     => $data['registrado_por'] ?? null,
                    'activo'             => true,
                ]);
            }

            return $lista;
        });

        $formateadas = array_map(fn (Huella $h) => [
            'id'                 => $h->id,
            'user_id'            => $h->user_id,
            'user_name'          => $usuario->name,
            'dedo'               => $h->dedo,
            'dedo_nombre'        => $h->dedo_nombre,
            'numero_muestra'     => $h->numero_muestra,
            'grupo_enrolamiento' => $h->grupo_enrolamiento,
            'etiqueta'           => $h->etiqueta,
            'formato'            => $h->formato,
            'calidad'            => $h->calidad,
            'dispositivo_modelo' => $h->dispositivo_modelo,
            'created_at'         => $h->created_at?->format('Y-m-d H:i:s'),
        ], $creadas);

        return response()->json([
            'ok'                 => true,
            'mensaje'            => "Se registraron 3 huellas de {$dedoNombre} correctamente para {$usuario->name}.",
            'grupo_enrolamiento' => $grupoUuid,
            'total_creadas'      => count($formateadas),
            'huella'             => $formateadas[0],
            'huellas'            => $formateadas,
        ], 201);
    }

    /**
     * Desactiva lógicamente una huella registrada (o las 3 muestras del dedo si ?todo_el_dedo=1).
     */
    public function destroyHuella(Request $request, int $id): JsonResponse
    {
        $huella = Huella::find($id);
        if (!$huella) {
            return response()->json([
                'ok'    => false,
                'error' => 'Huella no encontrada.',
            ], 404);
        }

        $todoElDedo = $request->boolean('todo_el_dedo', true);
        if ($todoElDedo) {
            $desactivadas = Huella::where('user_id', $huella->user_id)
                ->where('dedo', $huella->dedo)
                ->where('activo', true)
                ->update(['activo' => false]);

            return response()->json([
                'ok'           => true,
                'desactivadas' => $desactivadas,
                'mensaje'      => "Se desactivaron las {$desactivadas} huellas del dedo {$huella->dedo_nombre}.",
            ]);
        }

        $huella->update(['activo' => false]);

        return response()->json([
            'ok'           => true,
            'desactivadas' => 1,
            'mensaje'      => 'Huella desactivada correctamente.',
        ]);
    }

    /**
     * Registra entrada o salida de un usuario identificado biométricamente.
     */
    public function checar(Request $request, AsistenciaService $asistenciaService): JsonResponse
    {
        $data = $request->validate([
            'user_id'     => 'required|integer|exists:users,id',
            'huella_id'   => 'nullable|integer|exists:huellas,id',
            'score'       => 'nullable|integer|min:0|max:1000',
            'dispositivo' => 'nullable|string|max:100',
        ]);

        $usuario = User::with('turnoRelacion')->findOrFail($data['user_id']);
        $huella = null;

        if (!empty($data['huella_id'])) {
            $huella = Huella::where('id', $data['huella_id'])
                ->where('user_id', $usuario->id)
                ->where('activo', true)
                ->first();

            if (!$huella) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'La huella indicada no corresponde al usuario o fue desactivada.',
                ], 422);
            }
        }

        $resultado = $asistenciaService->registrarChequeo($usuario, $huella, [
            'score'       => $data['score'] ?? null,
            'dispositivo' => $data['dispositivo'] ?? null,
        ]);

        if (!$resultado['ok']) {
            return response()->json($resultado, 422);
        }

        /** @var Asistencia|null $asistencia */
        $asistencia = $resultado['asistencia'];

        return response()->json([
            'ok'                 => true,
            'accion'             => $resultado['accion'],
            'estatus'            => $resultado['estatus'] ?? $asistencia?->estatus,
            'minutos_retardo'    => $resultado['minutos_retardo'] ?? 0,
            'minutos_trabajados' => $resultado['minutos_trabajados'] ?? $asistencia?->minutos_trabajados,
            'mensaje'            => $resultado['mensaje'],
            'hora_servidor'      => $resultado['hora_servidor'],
            'usuario'            => [
                'id'    => $usuario->id,
                'name'  => $usuario->name,
                'user'  => $usuario->user,
                'turno' => $asistencia?->turno_nombre ?? $usuario->turnoRelacion?->nombre ?? $usuario->turno,
            ],
            'asistencia'         => $asistencia ? [
                'id'                 => $asistencia->id,
                'fecha_laboral'      => $asistencia->fecha_laboral?->format('Y-m-d'),
                'turno_id'           => $asistencia->turno_id,
                'turno_nombre'       => $asistencia->turno_nombre,
                'hora_entrada'       => $asistencia->hora_entrada?->format('Y-m-d H:i:s'),
                'hora_salida'        => $asistencia->hora_salida?->format('Y-m-d H:i:s'),
                'minutos_retardo'    => $asistencia->minutos_retardo,
                'minutos_trabajados' => $asistencia->minutos_trabajados,
                'estatus'            => $asistencia->estatus,
                'estatus_etiqueta'   => $asistencia->estatus_etiqueta,
                'dedo'               => $asistencia->huella?->dedo_nombre,
            ] : null,
        ]);
    }

    /**
     * Devuelve los chequeos más recientes de hoy (y ayer para turnos nocturnos activos).
     */
    public function hoy(Request $request): JsonResponse
    {
        $limite = min(100, max(5, (int) $request->query('limit', 25)));
        $desde = now()->timezone(config('app.timezone', 'America/Mexico_City'))->subDay()->toDateString();

        $registros = Asistencia::with(['usuario', 'turno', 'huella'])
            ->whereDate('fecha_laboral', '>=', $desde)
            ->orderByDesc('updated_at')
            ->limit($limite)
            ->get()
            ->map(function (Asistencia $a) {
                return [
                    'id'                 => $a->id,
                    'user_id'            => $a->user_id,
                    'user_name'          => $a->usuario?->name ?? 'Usuario #' . $a->user_id,
                    'turno_nombre'       => $a->turno_nombre ?? $a->turno?->nombre ?? 'Sin turno',
                    'fecha_laboral'      => $a->fecha_laboral?->format('Y-m-d'),
                    'hora_entrada'       => $a->hora_entrada?->format('H:i:s'),
                    'hora_salida'        => $a->hora_salida?->format('H:i:s'),
                    'minutos_retardo'    => $a->minutos_retardo,
                    'minutos_trabajados' => $a->minutos_trabajados,
                    'estatus'            => $a->estatus,
                    'estatus_etiqueta'   => $a->estatus_etiqueta,
                    'dedo'               => $a->huella?->dedo_nombre,
                ];
            })
            ->values();

        return response()->json([
            'ok'        => true,
            'registros' => $registros,
        ]);
    }

    /**
     * Consulta filtrada de asistencias con KPIs para visualizar y exportar desde la app local Python.
     */
    public function historialAsistencia(Request $request): JsonResponse
    {
        $tz = config('app.timezone', 'America/Mexico_City');
        $hoy = now()->timezone($tz)->toDateString();

        $fechaInicio = trim((string) $request->query('fecha_inicio', $hoy));
        $fechaFin = trim((string) $request->query('fecha_fin', $hoy));
        $turnoId = $request->query('turno_id');
        $userId = $request->query('user_id');
        $estatus = trim((string) $request->query('estatus', ''));

        $query = Asistencia::with(['usuario', 'turno', 'huella']);

        if ($fechaInicio !== '') {
            $query->whereDate('fecha_laboral', '>=', $fechaInicio);
        }
        if ($fechaFin !== '') {
            $query->whereDate('fecha_laboral', '<=', $fechaFin);
        }
        if (!empty($turnoId)) {
            $query->where('turno_id', (int) $turnoId);
        }
        if (!empty($userId)) {
            $query->where('user_id', (int) $userId);
        }
        if ($estatus !== '') {
            $query->where('estatus', $estatus);
        }

        $items = $query
            ->orderByDesc('fecha_laboral')
            ->orderByDesc('hora_entrada')
            ->limit(500)
            ->get();

        $kpis = [
            'total'     => $items->count(),
            'a_tiempo'  => $items->where('estatus', 'a_tiempo')->count(),
            'retardos'  => $items->where('estatus', 'retardo')->count(),
            'completas' => $items->whereNotNull('hora_salida')->count(),
            'en_turno'  => $items->whereNull('hora_salida')->count(),
        ];

        $turnos = Turno::where('estatus', true)->orderBy('id')->get(['id', 'nombre', 'hora_entrada', 'hora_salida', 'tolerancia_minutos']);

        $registros = $items->map(function (Asistencia $a) {
            return [
                'id'                   => $a->id,
                'fecha_laboral'        => $a->fecha_laboral?->format('Y-m-d'),
                'user_id'              => $a->user_id,
                'user_name'            => $a->usuario?->name ?? 'Usuario #' . $a->user_id,
                'user_login'           => $a->usuario?->user ?? '',
                'turno_id'             => $a->turno_id,
                'turno_nombre'         => $a->turno_nombre ?? $a->turno?->nombre ?? 'Sin turno',
                'hora_entrada_esperada'=> $a->hora_entrada_esperada ? substr((string) $a->hora_entrada_esperada, 0, 5) : '',
                'hora_entrada'         => $a->hora_entrada?->format('Y-m-d H:i:s'),
                'hora_salida'          => $a->hora_salida?->format('Y-m-d H:i:s'),
                'minutos_retardo'      => (int) $a->minutos_retardo,
                'minutos_trabajados'   => $a->minutos_trabajados,
                'horas_trabajadas'     => $a->horas_trabajadas_texto,
                'estatus'              => $a->estatus,
                'estatus_etiqueta'     => $a->estatus_etiqueta,
                'dedo'                 => $a->huella ? "{$a->huella->dedo_nombre} ({$a->huella->numero_muestra}/3)" : 'Manual',
                'dispositivo_entrada'  => $a->dispositivo_entrada ?? '',
                'dispositivo_salida'   => $a->dispositivo_salida ?? '',
                'observaciones'        => $a->observaciones ?? '',
            ];
        })->values();

        return response()->json([
            'ok'        => true,
            'kpis'      => $kpis,
            'turnos'    => $turnos,
            'registros' => $registros,
        ]);
    }
}
