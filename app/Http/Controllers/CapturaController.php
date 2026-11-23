<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Anio;
use App\Models\Delegaciones;
use App\Models\LoteCaptura;
use App\Models\User;
use App\Models\Municipios;
use App\Models\Localidades;
use App\Models\AsignacionCapturaUsuario;
use App\Models\CatalogoInconsistencia;
use App\Services\PdfService;
use App\Services\RutaPropuestaService;
use App\Services\StellumBridgeService;
use App\Events\LoteActualizadoEvent;
use App\Events\InconsistenciaReportadaEvent;

class CapturaController extends Controller
{
    /**
     * Muestra la vista de captura con los catálogos y rutas configuradas inyectados.
     */
    public function index()
    {
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

        return view('operacion.estacion_captura', compact(
            'anios',
            'delegaciones',
            'municipios',
            'directoriosPorDefecto',
            'asignacionUsuario',
            'catalogoInconsistencias'
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
    public function buscarLote(Request $request)
    {
        $disco = $request->query('disco') ?: 'irec';
        $delegacionId = $request->query('delegacion_id');
        $userId = Auth::id();

        // 0. Intentar primero consultar indice_archivos_pdfs en Stellum (si está disponible)
        $asignacion = AsignacionCapturaUsuario::where('user_id', $userId)->where('activo', true)->first();
        $tipoDoc = 'sellos'; // $asignacion?->tipo_documento ?? 'sellos';
        $delFiltro = $delegacionId ? Delegaciones::find($delegacionId) : null;

        try {
            $bridge = app(StellumBridgeService::class);
            $respStellum = $bridge->listarIndice([
                'tipo'       => $tipoDoc,
                'delegacion' => $delFiltro?->nombre,
                'limit'      => 100,
            ]);

            if (!empty($respStellum['ok']) && !empty($respStellum['items'])) {
                $listaStellum = array_values(array_map(function ($item) {
                    return [
                        'indice_pdf_id' => $item['id'] ?? null,
                        'ruta'          => 'stellum:' . ($item['id'] ?? 0),
                        'ruta_origen'   => $item['ruta'] ?? '',
                        'nombre'        => preg_replace('/\.pdf$/i', '', (string) ($item['nombre'] ?? 'archivo')),
                        'paginas'       => (int) ($item['paginas'] ?? 1),
                        'hash'          => $item['hash'] ?? null,
                    ];
                }, $respStellum['items']));

                return response()->json([
                    'ok'            => true,
                    'fuente'        => 'stellum',
                    'lote_id'       => 0,
                    'lote'          => 'Stellum (' . strtoupper($tipoDoc) . ')',
                    'ruta_relativa' => 'stellum/' . $tipoDoc,
                    'delegacion'    => $delFiltro ? [
                        'id'     => $delFiltro->id,
                        'clave'  => str_pad($delFiltro->clave ?? $delFiltro->id, 2, '0', STR_PAD_LEFT),
                        'nombre' => $delFiltro->nombre,
                    ] : null,
                    'archivos'      => $listaStellum,
                    'total'         => count($listaStellum),
                ]);
            }
        } catch (\Throwable $e) {
            // Fallback automático a lectura directa en los directorios configurados
        }

        // FALLBACK DIRECTO A DIRECTORIOS DE RED CONFIGURADOS (config/captura_pdf.php)
        $rutaConfigurada = $this->obtenerRutaRaizConfigurada($disco);
        if (!in_array($disco, ['irec', 'cats', 'rciv']) && !$rutaConfigurada) {
            $disco = 'irec';
        }

        $storage = in_array($disco, ['irec', 'cats', 'rciv'])
            ? Storage::disk($disco)
            : Storage::build(['driver' => 'local', 'root' => $rutaConfigurada]);

        // 1. Reanudar: Verificar si el usuario ya tiene un lote en proceso en este disco
        $queryExistente = LoteCaptura::where('disco', $disco)
            ->where('estado', 'en_proceso')
            ->where('user_id', $userId);

        if ($delegacionId) {
            $queryExistente->where('delegacion_id', $delegacionId);
        }

        $loteExistente = $queryExistente->first();

        if ($loteExistente) {
            $carpeta = $loteExistente->ruta_relativa;
            $rutaFisicaCarpeta = $storage->path($carpeta);
            if (is_dir($rutaFisicaCarpeta)) {
                $pdfs = glob($rutaFisicaCarpeta . DIRECTORY_SEPARATOR . '*.pdf');
                if (empty($pdfs)) {
                    $loteExistente->update([
                        'estado' => 'completado',
                        'completado_en' => now()
                    ]);
                } else {
                    $raizDisco = $storage->path('');
                    $listaPdfs = array_values(array_map(function ($f) use ($raizDisco) {
                        $relativa = str_replace('\\', '/', ltrim(str_replace($raizDisco, '', $f), '\\/'));
                        return [
                            'ruta' => $relativa,
                            'nombre' => pathinfo($f, PATHINFO_FILENAME),
                        ];
                    }, $pdfs));

                    $delExistente = Delegaciones::find($loteExistente->delegacion_id);

                    return response()->json([
                        'ok' => true,
                        'lote_id' => $loteExistente->id,
                        'lote' => $loteExistente->nombre_lote,
                        'ruta_relativa' => $carpeta,
                        'delegacion' => $delExistente ? [
                            'id' => $delExistente->id,
                            'clave' => str_pad($delExistente->clave ?? $delExistente->id, 2, '0', STR_PAD_LEFT),
                            'nombre' => $delExistente->nombre,
                        ] : null,
                        'archivos' => $listaPdfs,
                        'total' => count($listaPdfs),
                    ]);
                }
            }
        }

        // 2. Determinar las delegaciones a examinar
        if ($delegacionId) {
            $delegacion = Delegaciones::find($delegacionId);
            $delegacionesAExaminar = $delegacion ? [$delegacion] : [];
        } else {
            // Búsqueda automática en todas las delegaciones activas
            $delegacionesAExaminar = Delegaciones::where('estatus', 1)->orderBy('id')->get();
        }

        if (empty($delegacionesAExaminar)) {
            return response()->json(['ok' => false, 'mensaje' => 'No se encontraron delegaciones para buscar lotes.']);
        }

        $raizDisco = $storage->path('');

        foreach ($delegacionesAExaminar as $del) {
            $rutaBase = $this->resolverCarpetaDelegacion($storage, 'Respaldo_Original', $del->nombre);
            if (!$rutaBase) {
                continue;
            }

            $rutaFisicaBase = $storage->path($rutaBase);
            $entradas = @scandir($rutaFisicaBase);
            if ($entradas === false) {
                continue;
            }

            $carpetas = [];
            foreach ($entradas as $entrada) {
                if ($entrada === '.' || $entrada === '..') {
                    continue;
                }
                $rutaCompleta = $rutaFisicaBase . DIRECTORY_SEPARATOR . $entrada;
                if (is_dir($rutaCompleta)) {
                    $carpetas[] = $entrada;
                }
            }

            // Ordenamiento natural (Lote 1, Lote 2... Lote 10)
            usort($carpetas, function ($a, $b) {
                $numA = $this->extraerNumeroLote($a);
                $numB = $this->extraerNumeroLote($b);
                return $numA - $numB;
            });

            foreach ($carpetas as $nombreLote) {
                // Solo aceptar carpetas que empiecen con "Lote"
                if (!preg_match('/^Lote\s/i', $nombreLote)) {
                    continue;
                }

                // Ignorar carpetas terminadas (nombre termina en " OK")
                if (preg_match('/\sOK$/i', $nombreLote)) {
                    continue;
                }

                $rutaRelativa = $rutaBase . '/' . $nombreLote;

                // Verificar si ya está en la BD
                $existe = LoteCaptura::where('disco', $disco)
                    ->where('ruta_relativa', $rutaRelativa)
                    ->whereIn('estado', ['en_proceso', 'completado'])
                    ->exists();

                if ($existe) {
                    continue;
                }

                // Verificar que tenga PDFs
                $rutaFisicaLote = $rutaFisicaBase . DIRECTORY_SEPARATOR . $nombreLote;
                $pdfs = glob($rutaFisicaLote . DIRECTORY_SEPARATOR . '*.pdf');

                if (empty($pdfs)) {
                    continue;
                }

                // Asignar el lote al usuario actual
                $loteCaptura = LoteCaptura::create([
                    'disco' => $disco,
                    'delegacion_id' => $del->id,
                    'nombre_lote' => $nombreLote,
                    'ruta_relativa' => $rutaRelativa,
                    'estado' => 'en_proceso',
                    'user_id' => $userId,
                    'tomado_en' => now(),
                ]);

                $listaPdfs = array_values(array_map(function ($f) use ($raizDisco) {
                    $relativa = str_replace('\\', '/', ltrim(str_replace($raizDisco, '', $f), '\\/'));
                    return [
                        'ruta' => $relativa,
                        'nombre' => pathinfo($f, PATHINFO_FILENAME),
                    ];
                }, $pdfs));

                return response()->json([
                    'ok' => true,
                    'lote_id' => $loteCaptura->id,
                    'lote' => $nombreLote,
                    'ruta_relativa' => $rutaRelativa,
                    'delegacion' => [
                        'id' => $del->id,
                        'clave' => str_pad($del->clave ?? $del->id, 2, '0', STR_PAD_LEFT),
                        'nombre' => $del->nombre,
                    ],
                    'archivos' => $listaPdfs,
                    'total' => count($listaPdfs),
                ]);
            }
        }

        // Fallback final: si es una ruta de entrega directa (sin estructura Respaldo_Original/Lote N),
        // escanear directamente los PDFs en el directorio configurado (hasta 200 por lote)
        if ($rutaConfigurada && is_dir($rutaConfigurada)) {
            $pdfsDirectos = [];
            try {
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($rutaConfigurada, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iter as $fileInfo) {
                    if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'pdf') {
                        $rutaCompleta = $fileInfo->getPathname();
                        $relativa = str_replace('\\', '/', ltrim(str_replace($rutaConfigurada, '', $rutaCompleta), '\\/'));
                        $pdfsDirectos[] = [
                            'ruta'        => $relativa,
                            'ruta_origen' => $rutaCompleta,
                            'nombre'      => pathinfo($rutaCompleta, PATHINFO_FILENAME),
                        ];
                        if (count($pdfsDirectos) >= 200) {
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Directorio de red inaccesible desde esta máquina
            }

            if (!empty($pdfsDirectos)) {
                $delPrimero = $delegacionesAExaminar[0] ?? null;
                return response()->json([
                    'ok'            => true,
                    'fuente'        => 'directorio_directo',
                    'lote_id'       => 0,
                    'lote'          => basename($rutaConfigurada),
                    'ruta_relativa' => '',
                    'delegacion'    => $delPrimero ? [
                        'id'     => $delPrimero->id,
                        'clave'  => str_pad($delPrimero->clave ?? $delPrimero->id, 2, '0', STR_PAD_LEFT),
                        'nombre' => $delPrimero->nombre,
                    ] : null,
                    'archivos'      => $pdfsDirectos,
                    'total'         => count($pdfsDirectos),
                ]);
            }
        }

        return response()->json(['ok' => false, 'mensaje' => 'No hay más lotes disponibles para capturar (ni en Stellum ni en los directorios locales/red).']);
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
     * Sirve un PDF desde el disco de red al iframe del navegador.
     */
    public function verPdf(Request $request)
    {
        $disco = $request->query('disco');
        $ruta = (string) $request->query('ruta', '');

        // Si el PDF proviene de Stellum (prefijo stellum:{id}), hacer streaming directo desde el puente
        if (str_starts_with($ruta, 'stellum:')) {
            $indiceId = (int) substr($ruta, 8);
            if ($indiceId > 0) {
                $bridge = app(StellumBridgeService::class);
                return redirect()->away($bridge->urlStreamPdf($indiceId));
            }
        }

        $rutaConfigurada = $this->obtenerRutaRaizConfigurada($disco);
        if (!in_array($disco, ['irec', 'cats', 'rciv']) && !$rutaConfigurada) {
            abort(400, 'Disco o ruta de entrega no válida.');
        }

        $storage = in_array($disco, ['irec', 'cats', 'rciv'])
            ? Storage::disk($disco)
            : Storage::build(['driver' => 'local', 'root' => $rutaConfigurada]);

        // Obtener la ruta física completa (ej. \\172.40.5.84\irec\Respaldo_Original\...)
        $rutaFisica = $storage->path($ruta);

        if (!file_exists($rutaFisica)) {
            // Intentar resolver discrepancia de acentos/casing en el directorio
            $partes = explode('/', str_replace('\\', '/', $ruta));
            if (count($partes) >= 2) {
                $subBase = $partes[0];
                $nombreDel = $partes[1];
                $resuelto = $this->resolverCarpetaDelegacion($storage, $subBase, $nombreDel);
                if ($resuelto) {
                    $partes[0] = dirname($resuelto) === '.' ? $subBase : dirname($resuelto);
                    $partes[1] = basename($resuelto);
                    $rutaCorregida = implode('/', $partes);
                    $rutaFisica = $storage->path($rutaCorregida);
                }
            }
        }

        if (!file_exists($rutaFisica)) {
            abort(404, 'Archivo no encontrado: ' . $ruta);
        }

        // Servir directamente por streaming (no carga todo en memoria como Storage::get)
        return response()->file($rutaFisica, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
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
        $asignacion = AsignacionCapturaUsuario::where('user_id', $usuario?->id)->where('activo', true)->first();

        $tipoDocumento = strtolower((string) ($request->input('tipo_documento') ?: ($asignacion?->tipo_documento ?? 'sellos')));
        if (!in_array($tipoDocumento, ['registros', 'sellos', 'notarias', 'catastro'])) {
            $tipoDocumento = 'sellos';
        }

        $rutaOrigen = (string) ($request->input('ruta_origen') ?: $request->input('ruta_original') ?: $request->input('ruta_archivo') ?: '');
        $archivoOriginal = (string) ($request->input('archivo_original') ?: pathinfo($rutaOrigen, PATHINFO_FILENAME));
        $archivoNuevo    = (string) ($request->input('nuevo_nombre') ?: $request->input('archivo_nuevo') ?: $archivoOriginal);
        $archivoOriginalLimpio = preg_replace('/\.pdf$/i', '', trim($archivoOriginal));
        $archivoNuevoLimpio    = preg_replace('/\.pdf$/i', '', trim($archivoNuevo));

        // Determinar acción exacta
        if ($modo === 'inconsistencia') {
            $accion = 'inconsistencia';
        } elseif ($modo === 'duplicar') {
            $accion = 'duplicar';
        } else {
            $accion = strcasecmp($archivoOriginalLimpio, $archivoNuevoLimpio) === 0 ? 'validado' : 'renombrado';
        }

        $delegacionId = $request->input('delegacion_id') ?: $asignacion?->delegacion_id;
        $delegacionObj = $delegacionId ? Delegaciones::find($delegacionId) : null;
        $delegacionNombre = $delegacionObj?->nombre ?? 'GENERAL';
        $tipoSello = $request->input('tipo_sello') ?: $asignacion?->tipo_sello;
        $lote = $request->input('nombre_lote') ?: $request->input('lote') ?: 'LOTE_GENERAL';
        $directorio = $request->input('directorio') ?: $request->input('disco') ?: $delegacionNombre;
        $indicePdfId = $request->input('indice_pdf_id') ? (int) $request->input('indice_pdf_id') : null;
        $paginas = (int) $request->input('paginas', 1);

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

        // Verificar existencia en indice_archivos_pdfs (Stellum) para detectar colisión en entregables
        $revisar = false;
        $archivoColisionId = null;
        $rutaColision = null;
        $decisionRevision = null;

        if ($archivoNuevoLimpio !== '') {
            $bridge = app(StellumBridgeService::class);
            $check = $bridge->verificarExistenciaEnEntregables($archivoNuevoLimpio, $tipoDocumento, $indicePdfId);
            if (!empty($check['existe']) && !empty($check['coincidencia'])) {
                $col = $check['coincidencia'];
                $revisar = true;
                $archivoColisionId = $col['id'] ?? null;
                $rutaColision = $col['ruta'] ?? null;
                $decisionRevision = 'pendiente';
                $avisoColision = "El archivo ya se encuentra en los entregables (ID: {$archivoColisionId}, Ruta: {$rutaColision}).";
                $detallesBase = trim($detallesBase . ' ' . $avisoColision);
            }
        }

        // Calcular ruta_propuesta según config/reglas_destino_pdf.php
        $rutaPropuesta = RutaPropuestaService::generar($tipoDocumento, [
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

        $registro = [
            'fecha_hora'                => now()->format('Y-m-d H:i:s'),
            'turno'                     => $usuario?->turnoRelacion?->nombre ?? $usuario?->turno ?? 'Matutino',
            'user_id'                   => $usuario?->id,
            'categoria'                 => 'Captura',
            'pc'                        => $request->ip(),
            'directorio'                => $directorio,
            'lote'                      => $lote,
            'accion'                    => $accion,
            'archivo_original'          => $archivoOriginalLimpio,
            'archivo_nuevo'             => $archivoNuevoLimpio,
            'ruta'                      => $rutaOrigen,
            'ruta_origen'               => $rutaOrigen,
            'ruta_propuesta'            => $rutaPropuesta,
            'ruta_destino'              => $rutaPropuesta,
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
            'ok'             => true,
            'solo_bd'        => true,
            'auditoria_id'   => $auditoriaId,
            'accion'         => $accion,
            'revisar'        => $revisar,
            'ruta_origen'    => $rutaOrigen,
            'ruta_propuesta' => $rutaPropuesta,
            'ruta_nueva'     => $rutaOrigen,
            'paginas'        => $paginas,
            'mensaje'        => $revisar
                ? "Registrado en BD ({$accion}). ATENCIÓN: El archivo ya existe en entregables y se marcó para revisión."
                : "Acción '{$accion}' registrada únicamente en BD (sin modificar archivo físico).",
        ]);
    }

    public function renombrar(Request $request)
    {
        return $this->registrarAccionSoloBd($request, 'auto');
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
     * Libera un lote marcándolo como disponible (elimina el registro).
     */
    public function liberarLote(Request $request)
    {
        $loteId = $request->input('lote_id');
        $lote = LoteCaptura::find($loteId);

        if ($lote && $lote->user_id === Auth::id()) {
            $lote->delete();
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false, 'mensaje' => 'No se puede liberar el lote.']);
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
            if ($categoria === 'Calidad') {
                // En Calidad conservamos el progreso al liberar
                LoteCaptura::where('user_id', $userId)
                    ->where('estado', 'en_proceso')
                    ->update([
                        'user_id' => null,
                        'tomado_en' => null,
                    ]);
            } else {
                LoteCaptura::where('user_id', $userId)->where('estado', 'en_proceso')->delete();
            }
            return response()->json([
                'ok' => true,
                'mensaje' => 'Sesión liberada correctamente.',
            ]);
        }

        $rutaRelativa = $request->input('ruta_relativa');
        $rutaFinal = !empty($rutaRelativa) ? $rutaRelativa : ($directorio . '/' . $lote);

        // Asegurar que existan usuario y delegación para evitar violaciones de clave foránea
        $this->asegurarUsuarioExiste($userId);
        if ($delegacionId) {
            $this->asegurarDelegacionExiste($delegacionId);
        }

        // Transacción atómica con verificación de colisión
        $resultado = DB::transaction(function () use ($userId, $directorio, $rutaFinal, $delegacionId, $lote, $progresoArchivo) {
            // 1. Liberar automáticamente sesiones inactivas de este lote (más de 30 minutos sin actualizar)
            if ($directorio && $rutaFinal) {
                $sesionInactiva = LoteCaptura::where('ruta_relativa', $rutaFinal)
                    ->where('disco', $directorio)
                    ->where('estado', 'en_proceso')
                    ->where('updated_at', '<', now()->subMinutes(30))
                    ->first();

                if ($sesionInactiva) {
                    if (str_contains(strtolower($rutaFinal), 'calidad_')) {
                        // En calidad liberamos la asignación de usuario pero conservamos el registro con su progreso
                        $sesionInactiva->update([
                            'user_id' => null,
                            'tomado_en' => null,
                        ]);
                    } else {
                        $sesionInactiva->delete();
                    }
                }

                // 2. Verificar si OTRO usuario ya tiene este lote reservado (usando la ruta relativa única)
                $ocupante = LoteCaptura::where('ruta_relativa', $rutaFinal)
                    ->where('disco', $directorio)
                    ->where('estado', 'en_proceso')
                    ->whereNotNull('user_id')
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
                        'mensaje' => "El lote '{$lote}' ya está siendo trabajado por {$nombreOcupante}.",
                        'ocupado_por' => $nombreOcupante,
                        'ocupado_por_id' => $ocupante->user_id,
                    ];
                }
            }

            // 2. Limpiar sesiones previas del mismo usuario (un usuario solo puede tener 1 lote)
            LoteCaptura::where('user_id', $userId)
                ->where('estado', 'en_proceso')
                ->delete();

            // 3. Crear o actualizar la sesión activa
            $datosUpdate = [
                'user_id' => $userId,
                'estado' => 'en_proceso',
                'delegacion_id' => $delegacionId,
                'nombre_lote' => $lote ?? '',
                'tomado_en' => now(),
                'completado_en' => null,
            ];

            if (!empty($progresoArchivo)) {
                $datosUpdate['progreso_archivo'] = $progresoArchivo;
            }

            $sesion = LoteCaptura::updateOrCreate(
                [
                    'disco' => $directorio ?? '',
                    'ruta_relativa' => $rutaFinal ?? '',
                ],
                $datosUpdate
            );

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
     * Obtiene la lista de usuarios con sesiones activas (en_proceso) unificadas en LoteCaptura.
     */
    public function obtenerSesiones(Request $request)
    {
        $sesionesUnificadas = LoteCaptura::where('estado', 'en_proceso')
            ->with(['usuario:id,name,user', 'delegacion:id,nombre,clave'])
            ->get()
            ->map(function ($lote) {
                $esNotaria = ($lote->tipo_archivo === 'notarias' || !empty($lote->notaria_nombre));
                $notariaId = $esNotaria ? preg_replace('/[^0-9]/', '', $lote->notaria_nombre ?? '') : null;

                return [
                    'id' => $lote->id,
                    'user_id' => $lote->user_id,
                    'usuario' => $lote->usuario ? $lote->usuario->name : null,
                    'nombre_usuario' => $lote->usuario ? $lote->usuario->user : null,
                    'delegacion_id' => $esNotaria ? (!empty($notariaId) ? (int) $notariaId : null) : $lote->delegacion_id,
                    'delegacion' => $esNotaria ? $lote->notaria_nombre : ($lote->delegacion ? $lote->delegacion->nombre : null),
                    'directorio' => $lote->disco,
                    'lote' => $esNotaria ? ($lote->volumen ?: $lote->nombre_lote) : $lote->nombre_lote,
                    'pc' => $lote->pc ?: $lote->ruta_relativa,
                    'tomado_en' => $lote->tomado_en ? $lote->tomado_en->toDateTimeString() : null,
                    'progreso_archivo' => $lote->progreso_archivo,
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
