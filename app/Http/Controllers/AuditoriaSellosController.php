<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Delegaciones;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class AuditoriaSellosController extends Controller
{
    /**
     * Registra auditorías de sellos provenientes de la aplicación de escritorio.
     */
    public function registrar(Request $request)
    {
        try {
            $rawContent = $request->getContent();
            $datos = json_decode($rawContent, true);

            self::logSellos('info', 'Petición recibida en registrar sellos', [
                'ip' => $request->ip(),
                'bytes' => strlen($rawContent),
            ]);

            // Reconstrucción del JSON en caso de problemas con caracteres o BOM
            if (json_last_error() !== JSON_ERROR_NONE) {
                $rawContent = preg_replace('/^\xEF\xBB\xBF/', '', $rawContent);
                $trimmed = trim($rawContent);

                if (substr($trimmed, 0, 1) === '[') {
                    $trimmed = substr($trimmed, 1);
                }
                if (substr($trimmed, -1) === ']') {
                    $trimmed = substr($trimmed, 0, -1);
                }

                $trimmed = trim($trimmed);
                if (substr($trimmed, -1) === ',') {
                    $trimmed = substr($trimmed, 0, -1);
                }

                if (!empty($trimmed)) {
                    $jsonReconstruido = '[' . $trimmed . ']';
                    $datos = json_decode($jsonReconstruido, true);
                }

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $lastIndex = strrpos($rawContent, "},");
                    if ($lastIndex !== false) {
                        $recuperado = "[" . substr($rawContent, 0, $lastIndex + 1) . "]";
                        $datos = json_decode($recuperado, true);
                    }
                }
            }

            // Fallback si falla la decodificación anterior
            if (empty($datos)) {
                $datos = $request->all();
            }

            if (empty($datos)) {
                self::logSellos('warning', 'La petición de sellos no contiene datos válidos o payload vacío', [
                    'ip' => $request->ip(),
                    'raw_muestra' => substr($rawContent, 0, 300)
                ]);
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'La petición HTTP no contiene datos válidos.',
                    'errores' => ['Payload vacío']
                ], 400);
            }

            $registrosAGuardar = [];

            if (isset($datos['Registros']) && is_array($datos['Registros'])) {
                $registrosAGuardar = $datos['Registros'];
            } elseif (isset($datos[0]) && is_array($datos[0])) {
                $registrosAGuardar = $datos;
            } else {
                $registrosAGuardar[] = $datos;
            }

            $insertados = 0;
            $duplicadosOmitidos = 0;
            $fechaActual = now();
            $errores = [];
            $archivosProcesadosEnBatch = [];

            self::logSellos('info', "Iniciando procesamiento de " . count($registrosAGuardar) . " registro(s) de sellos.");

            foreach ($registrosAGuardar as $index => $originalReg) {
                if (empty($originalReg) || !is_array($originalReg)) {
                    continue;
                }

                try {
                    $reg = array_change_key_case($originalReg, CASE_LOWER);
                    $fechaHora = $reg['fechahora'] ?? $reg['fecha_hora'] ?? null;

                    if (!$fechaHora) {
                        $errores[] = "Registro {$index}: Falta el campo de fecha y hora.";
                        self::logSellos('warning', "Registro {$index} omitido por falta de fecha_hora", ['registro' => $originalReg]);
                        continue;
                    }

                    // Prevenir que registros con fecha del futuro entren a la BD
                    $timestampRecibido = strtotime($fechaHora);
                    if ($timestampRecibido !== false && $timestampRecibido > time() + 300) {
                        self::logSellos('warning', "Registro {$index}: Fecha en el futuro detectada ({$fechaHora}), se ajustó a fecha actual.");
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
                        $this->asegurarUsuarioExiste($userId, [
                            'user' => $userVal,
                            'name' => $nombreVal
                        ]);
                    } elseif ($userVal || $nombreVal) {
                        $userQuery = User::query();
                        if ($userVal) {
                            $userQuery->where('user', $userVal);
                        }
                        if ($nombreVal) {
                            $userQuery->orWhere('name', $nombreVal);
                        }
                        if ($userVal) {
                            $userQuery->orWhere('email', strtolower($userVal) . '@gmail.com');
                        }
                        $userObj = $userQuery->first();
                        if ($userObj) {
                            $userId = $userObj->id;
                        } else {
                            // Auto-crear usuario si no existe
                            $username = trim(strtolower($userVal ?? ('usuario_' . time())));
                            $nombre = trim($nombreVal ?? 'Usuario Autocreado');

                            if (User::where('user', $username)->exists()) {
                                $username = $username . '_' . time();
                            }

                            $email = $username . '@gmail.com';
                            if (User::where('email', $email)->exists()) {
                                $email = $username . '_' . time() . '@gmail.com';
                            }

                            $userCreado = User::forceCreate([
                                'name' => $nombre,
                                'user' => $username,
                                'email' => $email,
                                'password' => bcrypt('captura123'),
                                'estatus' => 1,
                            ]);
                            $userId = $userCreado->id;
                            self::logSellos('info', "Usuario autocreado para auditoría", ['user_id' => $userId, 'user' => $username, 'nombre' => $nombre]);
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

                    if ($delegacionId && $delegacionId > 0) {
                        $this->asegurarDelegacionExiste($delegacionId, [
                            'nombre' => $delNombreInput,
                            'clave' => $delIdInput
                        ]);
                    }

                    // Prevenir duplicados idénticos en la misma fecha/hora y usuario
                    $archivoOriginalVal = $reg['archivooriginal'] ?? $reg['archivo_original'] ?? null;
                    $archivoNuevoVal = $reg['archivonuevo'] ?? $reg['archivo_nuevo'] ?? null;
                    $rutaDestinoVal = $reg['ruta'] ?? null;
                    $accionVal = $reg['accion'] ?? null;
                    $accionFinal = $accionVal;
                    $detallesVal = $reg['detalles'] ?? null;
                    $detallesFinal = $detallesVal;

                    $duplicado = DB::table('auditoria_sellos')
                        ->where('fecha_hora', $fechaHora)
                        ->where(function ($q) use ($archivoOriginalVal, $archivoNuevoVal) {
                            if (!empty($archivoNuevoVal)) {
                                $q->where('archivo_nuevo', $archivoNuevoVal);
                            } elseif (!empty($archivoOriginalVal)) {
                                $q->where('archivo_original', $archivoOriginalVal);
                            }
                        })
                        ->where(function ($q) use ($userId, $reg) {
                            if ($userId) {
                                $q->where('user_id', $userId);
                            } else {
                                $q->where('pc', $reg['pc'] ?? null);
                            }
                        })
                        ->exists();

                    if ($duplicado) {
                        $duplicadosOmitidos++;
                        self::logSellos('debug', "Registro duplicado omitido", [
                            'fecha_hora' => $fechaHora,
                            'archivo_nuevo' => $archivoNuevoVal,
                            'archivo_original' => $archivoOriginalVal,
                            'accion' => $accionVal,
                            'user_id' => $userId,
                            'pc' => $reg['pc'] ?? null
                        ]);
                        continue;
                    }

                    // Si el archivo ya se trabajó previamente en la BD con acción activa (Renombrar, Validado, Duplicar),
                    // registrar la acción como Auto-Eliminado por Duplicado
                    $accionesActivas = ['renombrar', 'renombrado', 'validado', 'validar', 'duplicar', 'duplicado'];
                    $accionNormalizada = strtolower(trim((string) $accionVal));
                    $nombreNuevo = !empty($archivoNuevoVal) ? trim((string) $archivoNuevoVal) : null;

                    if ($nombreNuevo && in_array($accionNormalizada, $accionesActivas)) {
                        // 1. Revisar si ya se procesó este archivo_nuevo en el mismo lote/batch HTTP actual
                        if (isset($archivosProcesadosEnBatch[$nombreNuevo])) {
                            $encontradoEnBatch = $archivosProcesadosEnBatch[$nombreNuevo];
                            $accionFinal = 'Auto-Eliminado por Duplicado';
                            $notaAutoEliminado = "[Auto-Eliminado: el archivo ya fue trabajado previamente en el mismo lote con acción '{$encontradoEnBatch['accion']}']";
                            $detallesFinal = trim(($detallesVal ? $detallesVal . ' ' : '') . $notaAutoEliminado);
                        } else {
                            // 2. Revisar en la base de datos por el archivo_nuevo exacto
                            $registroPrevio = DB::table('auditoria_sellos')
                                ->select('id', 'accion', 'fecha_hora')
                                ->where('archivo_nuevo', $nombreNuevo)
                                ->whereIn(DB::raw('LOWER(TRIM(accion))'), $accionesActivas)
                                ->orderBy('id', 'asc')
                                ->first();

                            if ($registroPrevio) {
                                $accionFinal = 'Auto-Eliminado por Duplicado';
                                $notaAutoEliminado = "[Auto-Eliminado: el archivo ya fue trabajado previamente en la BD con acción '{$registroPrevio->accion}' en ID {$registroPrevio->id}]";
                                $detallesFinal = trim(($detallesVal ? $detallesVal . ' ' : '') . $notaAutoEliminado);
                                self::logSellos('info', "Archivo ya trabajado en BD, registrado como Auto-Eliminado", [
                                    'archivo' => $nombreNuevo,
                                    'id_previo' => $registroPrevio->id,
                                    'accion_original' => $accionVal
                                ]);
                            } else {
                                // Registrar en memoria del batch actual
                                $archivosProcesadosEnBatch[$nombreNuevo] = [
                                    'accion' => $accionVal,
                                    'fecha_hora' => $fechaHora
                                ];
                            }
                        }
                    }

                    // Validar enum de tipo_sello
                    $tipoSello = $reg['tipo_sello'] ?? $reg['tiposello'] ?? null;
                    if (!in_array($tipoSello, ['Sellos', 'Boleta de inscripcion', 'Boleta de ingreso', 'Boleta de rechazo'])) {
                        $tipoSello = null;
                    }

                    $rutaOrigenVal = $reg['ruta_origen'] ?? $reg['rutaorigen'] ?? $rutaDestinoVal;
                    $inspeccion = \App\Services\PdfService::inspeccionarPdf($rutaDestinoVal ?: $rutaOrigenVal);

                    DB::table('auditoria_sellos')->insert([
                        'fecha_hora' => $fechaHora,
                        'turno' => $reg['turno'] ?? null,
                        'user_id' => $userId,
                        'categoria' => $reg['categoria'] ?? null,
                        'pc' => $reg['pc'] ?? null,
                        'directorio' => $reg['directorio'] ?? null,
                        'delegacion_id' => $delegacionId,
                        'lote' => $reg['lote'] ?? null,
                        'accion' => $accionFinal,
                        'archivo_original' => $archivoOriginalVal,
                        'archivo_nuevo' => $archivoNuevoVal,
                        'ruta' => $rutaDestinoVal,
                        'ruta_origen' => $rutaOrigenVal,
                        'ruta_destino' => $rutaDestinoVal,
                        'hash' => $reg['hash'] ?? $inspeccion['hash'],
                        'archivo_danado' => (bool) ($reg['archivo_danado'] ?? $inspeccion['archivo_danado']),
                        'detalles' => $detallesFinal,
                        'paginas' => $paginasVal ?: $inspeccion['paginas'],
                        'tipo_sello' => $tipoSello,
                        'tiempo_captura' => $reg['tiempo_captura'] ?? $reg['tiempocaptura'] ?? null,
                        'created_at' => $fechaActual,
                        'updated_at' => $fechaActual,
                    ]);
                    $insertados++;
                } catch (\Throwable $e) {
                    $errores[] = "Registro {$index}: Error: " . $e->getMessage();
                    self::logSellos('error', "Error al insertar registro {$index}", [
                        'error' => $e->getMessage(),
                        'registro' => $originalReg
                    ]);
                }
            }

            self::logSellos('info', "Fin de sincronización de sellos", [
                'insertados' => $insertados,
                'duplicados_omitidos' => $duplicadosOmitidos,
                'errores_total' => count($errores)
            ]);

            if ($insertados === 0) {
                if (empty($errores)) {
                    return response()->json([
                        'ok' => true,
                        'mensaje' => "Todos los registros de sellos ya estaban sincronizados (omitidos {$duplicadosOmitidos} duplicados).",
                        'procesados' => 0,
                        'duplicados' => $duplicadosOmitidos,
                        'errores' => [],
                    ]);
                }

                return response()->json([
                    'ok' => false,
                    'mensaje' => "No se pudo registrar ningún elemento en la base de datos de sellos.",
                    'duplicados' => $duplicadosOmitidos,
                    'errores' => $errores,
                ], 400);
            }

            // Actualizar updated_at de la sesión activa del lote del usuario para marcar actividad reciente
            if ($insertados > 0 && isset($userId)) {
                try {
                    \App\Models\LoteCaptura::where('user_id', $userId)
                        ->where('estado', 'en_proceso')
                        ->update(['updated_at' => now()]);
                } catch (\Exception $e) {
                    // Falla silenciosa si no hay sesión activa
                }
            }

            return response()->json([
                'ok' => true,
                'mensaje' => "Se registraron {$insertados} registros de sellos (omitidos {$duplicadosOmitidos} duplicados).",
                'procesados' => $insertados,
                'duplicados' => $duplicadosOmitidos,
                'errores' => $errores,
            ]);

        } catch (\Throwable $exGlobal) {
            self::logSellos('critical', 'Error crítico en registrar sellos: ' . $exGlobal->getMessage(), [
                'trace' => $exGlobal->getTraceAsString()
            ]);
            return response()->json([
                'ok' => false,
                'mensaje' => 'Error crítico en el backend de sellos: ' . $exGlobal->getMessage(),
                'errores' => [$exGlobal->getMessage()]
            ], 500);
        }
    }

    /**
     * Asegura que el usuario con el ID especificado existe en la base de datos.
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
     * Renderiza la vista de gráficas de desempeño de sellos.
     */
    public function index_capturas()
    {
        return view('indicadores.sellos_avance');
    }

    /**
     * Renderiza la vista de reportes/tabla de desempeño de sellos.
     */
    public function index_reportes_capturas()
    {
        return view('productividad.sellos_usuarios');
    }

    /**
     * Obtiene los datos de analíticas y KPIs de sellos (con filtros opcionales de fecha y tipo de sello).
     */
    public function dashboard_sellos(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;
        $tipoSello = $request->tipo_sello;

        $query = DB::table('auditoria_sellos as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                DB::raw('COALESCE(d.nombre, ac.directorio, "Desconocida") as Delegacion'),
                'ac.paginas as Paginas',
                'ac.archivo_original as ArchivoOriginal',
                'ac.archivo_nuevo as ArchivoNuevo',
                'ac.lote as Lote',
                'ac.detalles as Detalles',
                'ac.pc as PC',
                'ac.categoria as Categoria',
                'ac.tipo_sello as TipoSello'
            );

        // Excluir Correccion renombre, movidos a inconsistencia y archivos movidos a libros de las métricas KPI y productividad de la gráfica
        $query->where('ac.accion', '!=', 'Correccion renombre')
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%libros%']);

        // Excluir administradores
        $query->where(function ($q) {
            $q->whereNull('ac.user_id')
                ->orWhere(function ($sub) {
                    $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                        ->where('u.name', 'not like', '%Administrador%');
                });
        });

        // Filtrado por fecha preliminar (SQL)
        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('ac.fecha_hora', '>=', $fechaMinima)
                ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        // Filtrado por tipo de sello
        if ($tipoSello) {
            $query->where('ac.tipo_sello', $tipoSello);
        }

        // Obtener fecha laboral según las reglas de corte y jornada
        $obtenerFechaLaboral = function ($fechaCompleta, $turno = null) {
            if (empty($fechaCompleta) || $fechaCompleta === '1900-01-01 00:00:00' || strlen($fechaCompleta) < 19) {
                if (strlen($fechaCompleta) === 10) {
                    return $fechaCompleta;
                }
                return 'Sin Fecha';
            }
            $timestamp = strtotime($fechaCompleta);
            if ($timestamp === false) {
                return 'Sin Fecha';
            }

            $w = (int) date('w', $timestamp);
            $hora = (int) date('H', $timestamp);

            // Si es sábado o domingo y es un turno de día, pertenece al mismo día calendario
            if (
                ($w === 6 || $w === 0) && $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es sábado o domingo antes de las 9:00 AM (y no es turno de día), pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno de día de lunes a viernes, pertenece al mismo día calendario
            if (
                $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es Nocturno (o no se especifica), aplicamos la regla de corte a las 9:00 AM
            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        $conteoAccionesGlobal = [];
        $conteoPorUsuario = [];
        $conteoPorDia = [];
        $accionesPorUsuario = [];
        $conteoPorDelegacion = [];
        $conteoPorTurno = [];
        $conteoPorTurnoDetalle = [];
        $conteoPaginasPorDia = [];
        $datosResumen = [];
        $accionesUnicasResumen = [];

        $detallado = (bool) $request->detallado;
        $prioridadTurno = function ($turno) {
            $turnoLower = strtolower((string) $turno);
            if (stripos($turnoLower, 'matutino') !== false) {
                return 1;
            }
            if (stripos($turnoLower, 'vespertino') !== false) {
                return 2;
            }
            if (stripos($turnoLower, 'mixto') !== false) {
                return 3;
            }
            if (stripos($turnoLower, 'nocturno') !== false) {
                return 4;
            }
            return 5;
        };

        // Procesamiento usando cursor para evitar sobrecarga de memoria
        foreach ($query->cursor() as $row) {
            $fechaHora = $row->FechaHora;
            $turno = $row->Turno;

            // Obtener fecha laboral
            $fecha = $obtenerFechaLaboral($fechaHora, $turno);
            if ($fecha === 'Sin Fecha') {
                $fecha = date('Y-m-d', strtotime($fechaHora));
            }

            // Filtrado por rango de fecha laboral
            if ($inicio && $fin) {
                if ($fecha < $inicio || $fecha > $fin) {
                    continue;
                }
            }

            $accion = $row->Accion ?? 'Otras';

            $esAccionCalidad = (
                stripos($accion, 'renombrar') !== false ||
                stripos($accion, 'renombrado') !== false ||
                stripos($accion, 'validado') !== false ||
                stripos($accion, 'validar') !== false ||
                stripos($accion, 'duplicado') !== false ||
                stripos($accion, 'duplicada') !== false ||
                stripos($accion, 'duplicar') !== false
            ) && (
                stripos($accion, 'eliminad') === false &&
                stripos($accion, 'eliminar') === false &&
                stripos($accion, 'inconsistencia') === false &&
                stripos($accion, 'recorte') === false
            );

            // Agrupar variantes de eliminación y recortes bajo una sola categoría
            if (stripos($accion, 'eliminad') !== false || stripos($accion, 'eliminar') !== false) {
                $accion = 'Eliminado';
            } elseif (stripos($accion, 'recorte') !== false || stripos($accion, 'inconsistencia') !== false) {
                $accion = 'Recorte Hojas';
            }

            $usuario = !empty($row->NombreCompleto) ? $row->NombreCompleto : ($row->Usuario ?? 'Desconocido');
            $delegacion = !empty($row->Delegacion) ? $row->Delegacion : 'Desconocida';
            $paginas = isset($row->Paginas) ? (int) $row->Paginas : 1;
            $pc = $row->PC ?: 'Sin PC';
            $categoria = $row->Categoria ?: 'Captura';

            // Global (Sigue contando todas las acciones para el gráfico de pastel y tarjetas)
            if (!isset($conteoAccionesGlobal[$accion])) {
                $conteoAccionesGlobal[$accion] = [
                    'archivos' => 0,
                    'paginas' => 0
                ];
            }
            $conteoAccionesGlobal[$accion]['archivos']++;
            $conteoAccionesGlobal[$accion]['paginas'] += $paginas;

            if ($detallado) {
                // Usuario (total)
                if (!isset($conteoPorUsuario[$usuario]))
                    $conteoPorUsuario[$usuario] = 0;
                $conteoPorUsuario[$usuario]++;

                // Acciones por usuario
                if (!isset($accionesPorUsuario[$usuario])) {
                    $accionesPorUsuario[$usuario] = ['total' => 0, 'acciones' => []];
                }
                $accionesPorUsuario[$usuario]['total']++;
                if (!isset($accionesPorUsuario[$usuario]['acciones'][$accion])) {
                    $accionesPorUsuario[$usuario]['acciones'][$accion] = 0;
                }
                $accionesPorUsuario[$usuario]['acciones'][$accion]++;

                // Tabla resumen tipo Excel
                if (!in_array($accion, $accionesUnicasResumen)) {
                    $accionesUnicasResumen[] = $accion;
                }

                $turnoFila = $turno ?: 'Matutino';
                $keyFila = "{$fecha}|{$usuario}";

                if (!isset($datosResumen[$keyFila])) {
                    $datosResumen[$keyFila] = [
                        'Fecha' => $fecha,
                        'PCs' => [$pc],
                        'Usuario' => $usuario,
                        'Turnos' => [$turnoFila],
                        'Categorias' => [$categoria],
                        'Acciones' => [],
                        'TotalArchivos' => 0,
                        'TotalPaginas' => 0,
                    ];
                } else {
                    if (!in_array($pc, $datosResumen[$keyFila]['PCs'], true)) {
                        $datosResumen[$keyFila]['PCs'][] = $pc;
                    }
                    if (!in_array($turnoFila, $datosResumen[$keyFila]['Turnos'], true)) {
                        $datosResumen[$keyFila]['Turnos'][] = $turnoFila;
                    }
                    if (!in_array($categoria, $datosResumen[$keyFila]['Categorias'], true)) {
                        $datosResumen[$keyFila]['Categorias'][] = $categoria;
                    }
                }

                if (!isset($datosResumen[$keyFila]['Acciones'][$accion])) {
                    $datosResumen[$keyFila]['Acciones'][$accion] = 0;
                }
                $datosResumen[$keyFila]['Acciones'][$accion]++;
                $datosResumen[$keyFila]['TotalArchivos']++;
                $datosResumen[$keyFila]['TotalPaginas'] += $paginas;
            }

            $esRecorteOInconsistencia = (stripos($accion, 'recorte') !== false || stripos($accion, 'inconsistencia') !== false);

            // Día (Archivos) - Excluir Recorte Hojas del conteo de productividad al día
            if ($fecha !== 'Sin Fecha' && !$esRecorteOInconsistencia) {
                if (!isset($conteoPorDia[$fecha]))
                    $conteoPorDia[$fecha] = 0;
                $conteoPorDia[$fecha]++;
            }

            // Día (Páginas/Imágenes) - Excluir Recorte Hojas del conteo de productividad al día
            if ($fecha !== 'Sin Fecha' && !$esRecorteOInconsistencia) {
                if (!isset($conteoPaginasPorDia[$fecha]))
                    $conteoPaginasPorDia[$fecha] = 0;
                $conteoPaginasPorDia[$fecha] += $paginas;
            }

            // Turno
            // Normalizar turno para clasificación general
            $turnoNorm = !empty($turno) ? trim(ucfirst(strtolower($turno))) : 'Matutino';
            if (stripos($turnoNorm, 'nocturno') !== false) {
                $turnoGrupo = 'Nocturno';
            } elseif (stripos($turnoNorm, 'vespertino') !== false) {
                $turnoGrupo = 'Vespertino';
            } elseif (stripos($turnoNorm, 'mixto') !== false) {
                $turnoGrupo = 'Mixto';
            } else {
                $turnoGrupo = 'Matutino';
            }

            if (!isset($conteoPorTurno[$turnoGrupo])) {
                $conteoPorTurno[$turnoGrupo] = 0;
            }
            $conteoPorTurno[$turnoGrupo]++;

            if (!isset($conteoPorTurnoDetalle[$turnoGrupo])) {
                $conteoPorTurnoDetalle[$turnoGrupo] = [
                    'archivos' => 0,
                    'imagenes' => 0
                ];
            }
            $conteoPorTurnoDetalle[$turnoGrupo]['archivos']++;
            $conteoPorTurnoDetalle[$turnoGrupo]['imagenes'] += $paginas;

            // Por Delegación (archivos e imágenes)
            if (!isset($conteoPorDelegacion[$delegacion])) {
                $conteoPorDelegacion[$delegacion] = [
                    'archivos' => 0,
                    'imagenes' => 0
                ];
            }
            $conteoPorDelegacion[$delegacion]['archivos']++;
            $conteoPorDelegacion[$delegacion]['imagenes'] += $paginas;
        }

        // 1. Acciones Global (Pie)
        $accionesGlobalFormateado = [];
        foreach ($conteoAccionesGlobal as $k => $v) {
            $accionesGlobalFormateado[] = [
                'accion' => $k,
                'total' => $v['archivos'],
                'paginas' => $v['paginas']
            ];
        }

        // 2. Por Usuario (Bar) - Todos los usuarios
        $usuariosFormateado = [];
        if ($detallado) {
            arsort($conteoPorUsuario);
            foreach ($conteoPorUsuario as $k => $v) {
                $usuariosFormateado[] = ['usuario' => $k, 'total' => $v];
            }
        }

        // 3. Por Día (Line)
        ksort($conteoPorDia);
        $diasFormateado = [];
        foreach ($conteoPorDia as $k => $v) {
            $diasFormateado[] = ['fecha' => $k, 'total' => $v];
        }

        // 3b. Páginas por Día (Line/Puntos)
        ksort($conteoPaginasPorDia);
        $paginasPorDiaFormateado = [];
        foreach ($conteoPaginasPorDia as $k => $v) {
            $paginasPorDiaFormateado[] = ['fecha' => $k, 'total' => $v];
        }

        // 4. Por Delegación (Formatear y ordenar)
        $delegacionesFormateado = [];
        foreach ($conteoPorDelegacion as $k => $v) {
            $delegacionesFormateado[] = [
                'delegacion' => $k,
                'archivos' => $v['archivos'],
                'imagenes' => $v['imagenes']
            ];
        }
        usort($delegacionesFormateado, function ($a, $b) {
            return strcmp($a['delegacion'], $b['delegacion']);
        });

        // 4b. Por Turno
        $turnosFormateado = [];
        foreach ($conteoPorTurno as $k => $v) {
            $turnosFormateado[] = ['turno' => $k, 'total' => $v];
        }

        $turnosDetalleFormateado = [];
        foreach ($conteoPorTurnoDetalle as $k => $v) {
            $turnosDetalleFormateado[] = [
                'turno' => $k,
                'archivos' => $v['archivos'],
                'imagenes' => $v['imagenes']
            ];
        }

        // 5. Usuarios únicos para select
        $usuariosUnicos = [];
        if ($detallado) {
            $usuariosUnicos = array_keys($accionesPorUsuario);
            sort($usuariosUnicos);
        }

        // Ordenar la tabla resumen como en el Excel
        if ($detallado) {
            // Consulta para auditoria_registros
            $queryCapturas = DB::table('auditoria_registros as ac')
                ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
                ->select(
                    'ac.fecha_hora as FechaHora',
                    'u.user as Usuario',
                    'u.name as NombreCompleto',
                    DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                    'ac.accion as Accion',
                    'ac.pc as PC'
                );

            $queryCapturas->where('ac.accion', '!=', 'Correccion renombre')
                ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
                ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%'])
                ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%libros%']);

            $queryCapturas->where(function ($q) {
                $q->whereNull('ac.user_id')
                    ->orWhere(function ($sub) {
                        $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                            ->where('u.name', 'not like', '%Administrador%');
                    });
            });

            if ($inicio && $fin) {
                $queryCapturas->where('ac.fecha_hora', '>=', $fechaMinima)
                    ->where('ac.fecha_hora', '<=', $fechaMaxima);
            }

            $conteoCapturasPorFechaYUsuario = [];
            foreach ($queryCapturas->cursor() as $rowCap) {
                $fechaCap = $obtenerFechaLaboral($rowCap->FechaHora, $rowCap->Turno);
                if ($fechaCap === 'Sin Fecha') {
                    $fechaCap = date('Y-m-d', strtotime($rowCap->FechaHora));
                }

                if ($inicio && $fin) {
                    if ($fechaCap < $inicio || $fechaCap > $fin) {
                        continue;
                    }
                }

                $usuarioCap = !empty($rowCap->NombreCompleto) ? $rowCap->NombreCompleto : ($rowCap->Usuario ?? 'Desconocido');
                $keyCap = "{$fechaCap}|{$usuarioCap}";

                if (!isset($conteoCapturasPorFechaYUsuario[$keyCap])) {
                    $conteoCapturasPorFechaYUsuario[$keyCap] = 0;
                }
                $conteoCapturasPorFechaYUsuario[$keyCap]++;

                // Si el usuario no está en datosResumen porque solo realizó capturas en la fecha, agregarlo
                if (!isset($datosResumen[$keyCap])) {
                    $pcCap = $rowCap->PC ?: 'Sin PC';
                    $turnoCap = $rowCap->Turno ?: 'Matutino';
                    $datosResumen[$keyCap] = [
                        'Fecha' => $fechaCap,
                        'PCs' => [$pcCap],
                        'Usuario' => $usuarioCap,
                        'Turnos' => [$turnoCap],
                        'Categorias' => ['Captura'],
                        'Acciones' => [],
                        'TotalArchivos' => 0,
                        'TotalPaginas' => 0,
                    ];
                }
            }

            foreach ($datosResumen as $keyFila => &$fila) {
                $totalSellos = $fila['TotalArchivos'] ?? 0;
                $totalRegistros = $conteoCapturasPorFechaYUsuario[$keyFila] ?? 0;
                $fila['TotalSellos'] = $totalSellos;
                $fila['TotalRegistros'] = $totalRegistros;
                $fila['TotalAmbas'] = $totalSellos + $totalRegistros;
            }
            unset($fila);

            $datosResumen = array_values(array_map(function ($fila) {
                $fila['PC'] = implode(' / ', array_values(array_unique($fila['PCs'])));
                $fila['Turno'] = implode(' / ', array_values(array_unique($fila['Turnos'])));
                $fila['Categoria'] = implode(' / ', array_values(array_unique($fila['Categorias'])));
                unset($fila['PCs'], $fila['Turnos'], $fila['Categorias']);
                return $fila;
            }, $datosResumen));
            usort($datosResumen, function ($a, $b) use ($prioridadTurno) {
                $compFecha = strcmp($b['Fecha'], $a['Fecha']);
                if ($compFecha !== 0)
                    return $compFecha;

                $aCalidad = (stripos($a['Categoria'], 'calidad') !== false) ? 1 : 0;
                $bCalidad = (stripos($b['Categoria'], 'calidad') !== false) ? 1 : 0;
                if ($aCalidad !== $bCalidad) {
                    return $aCalidad <=> $bCalidad;
                }

                $tA = $prioridadTurno($a['Turno']);
                $tB = $prioridadTurno($b['Turno']);
                return $tA <=> $tB;
            });
            sort($accionesUnicasResumen);
        }

        return response()->json([
            'ok' => true,
            'acciones_global' => $accionesGlobalFormateado,
            'por_usuario' => $usuariosFormateado,
            'por_dia' => $diasFormateado,
            'paginas_por_dia' => $paginasPorDiaFormateado,
            'por_turno' => $turnosFormateado,
            'por_turno_detalle' => $turnosDetalleFormateado,
            'acciones_por_usuario' => $accionesPorUsuario,
            'usuarios_unicos' => $usuariosUnicos,
            'por_delegacion' => $delegacionesFormateado,
            'tabla_resumen' => $datosResumen,
            'acciones_resumen' => $accionesUnicasResumen
        ]);
    }

    /**
     * Descarga el reporte detallado de sellos en Excel (con filtros de fecha y tipo de sello).
     */
    public function descargar_excel(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;
        $tipoSello = $request->tipo_sello;
        $userId = $request->user_id;
        $search = $request->search;

        $query = DB::table('auditoria_sellos as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.id as Id',
                'ac.ruta as Ruta',
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                DB::raw('COALESCE(d.nombre, ac.directorio, "Desconocida") as Delegacion'),
                'ac.paginas as Paginas',
                'ac.archivo_original as ArchivoOriginal',
                'ac.archivo_nuevo as ArchivoNuevo',
                'ac.lote as Lote',
                'ac.detalles as Detalles',
                'ac.pc as PC',
                'ac.categoria as Categoria',
                'ac.tipo_sello as TipoSello',
                'ac.tiempo_captura as TiempoCaptura'
            );

        // Excluir administradores
        $query->where(function ($q) {
            $q->whereNull('ac.user_id')
                ->orWhere(function ($sub) {
                    $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                        ->where('u.name', 'not like', '%Administrador%');
                });
        });

        // Excluir Correccion renombre
        $query->where('ac.accion', '!=', 'Correccion renombre');

        // Excluir inconsistencias, archivos movidos a inconsistencia y archivos movidos a libros
        $query->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%libros%']);

        // Filtrado por búsqueda de archivos
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ac.archivo_original) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(ac.archivo_nuevo) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Filtrado por fecha preliminar (SQL)
        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('ac.fecha_hora', '>=', $fechaMinima)
                ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        // Filtrado por tipo de sello
        if ($tipoSello) {
            $query->where('ac.tipo_sello', $tipoSello);
        }

        // Filtrado por usuario
        if ($userId) {
            $query->where('ac.user_id', $userId);
        }

        $registrosDb = $query->orderBy('ac.fecha_hora', 'desc')->get();

        // Obtener fecha laboral según las reglas de corte y jornada
        $obtenerFechaLaboral = function ($fechaCompleta, $turno = null) {
            if (empty($fechaCompleta) || $fechaCompleta === '1900-01-01 00:00:00' || strlen($fechaCompleta) < 19) {
                if (strlen($fechaCompleta) === 10) {
                    return $fechaCompleta;
                }
                return 'Sin Fecha';
            }
            $timestamp = strtotime($fechaCompleta);
            if ($timestamp === false) {
                return 'Sin Fecha';
            }

            $w = (int) date('w', $timestamp);
            $hora = (int) date('H', $timestamp);

            // Si es sábado o domingo y es un turno de día, pertenece al mismo día calendario
            if (
                ($w === 6 || $w === 0) && $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es sábado o domingo antes de las 9:00 AM (y no es turno de día), pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno de día de lunes a viernes, pertenece al mismo día calendario
            if (
                $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es Nocturno (o no se especifica), aplicamos la regla de corte a las 9:00 AM
            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        // Filtrar por fecha laboral exacta
        if ($inicio && $fin) {
            $registrosDb = $registrosDb->filter(function ($row) use ($inicio, $fin, $obtenerFechaLaboral) {
                $fechaLaboral = $obtenerFechaLaboral($row->FechaHora, $row->Turno);
                if ($fechaLaboral === 'Sin Fecha') {
                    $fechaLaboral = date('Y-m-d', strtotime($row->FechaHora)) ?: '1900-01-01';
                }
                return $fechaLaboral >= $inicio && $fechaLaboral <= $fin;
            });
        }

        if ($registrosDb->isEmpty()) {
            return redirect()->back()->with('error', 'No se encontraron datos para exportar.');
        }

        // Ordenar registros por Fecha Laboral descendente, luego por Turno (Vespertino, Matutino, Nocturno), luego por Usuario y finalmente por Fecha Hora
        $registrosArr = $registrosDb->all();

        usort($registrosArr, function ($a, $b) use ($obtenerFechaLaboral) {
            // 1. Comparar fecha laboral de forma descendente (el dia mas reciente primero)
            $fechaA = $obtenerFechaLaboral($a->FechaHora, $a->Turno);
            $fechaB = $obtenerFechaLaboral($b->FechaHora, $b->Turno);
            if ($fechaA === 'Sin Fecha') {
                $fechaA = date('Y-m-d', strtotime($a->FechaHora)) ?: 'Sin_Fecha';
            }
            if ($fechaB === 'Sin Fecha') {
                $fechaB = date('Y-m-d', strtotime($b->FechaHora)) ?: 'Sin_Fecha';
            }

            if ($fechaA !== $fechaB) {
                return strcmp($fechaB, $fechaA);
            }

            // 2. Comparar prioridad de turno (Vespertino, Matutino, Nocturno)
            $obtenerPrioTurno = function ($t) {
                $t = trim(strtolower($t));
                if (strpos($t, 'vespertino') !== false) {
                    return 1;
                }
                if (strpos($t, 'matutino') !== false) {
                    return 2;
                }
                if (strpos($t, 'mixto') !== false) {
                    return 3;
                }
                if (strpos($t, 'nocturno') !== false) {
                    return 4;
                }
                return 5;
            };

            $prioA = $obtenerPrioTurno($a->Turno);
            $prioB = $obtenerPrioTurno($b->Turno);
            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }

            // 3. Comparar usuario para mantener registros consecutivos
            $nombreA = $a->NombreCompleto ?? ($a->Usuario ?? '');
            $nombreB = $b->NombreCompleto ?? ($b->Usuario ?? '');
            $compUsuario = strcasecmp($nombreA, $nombreB);
            if ($compUsuario !== 0) {
                return $compUsuario;
            }

            // 4. Comparar fecha_hora cronológicamente
            return strcmp($a->FechaHora, $b->FechaHora);
        });

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Detalle de Sellos');

        // Cabeceras requeridas (con Fecha y Hora al final)
        $worksheet->setCellValue('A1', 'Nombre Completo');
        $worksheet->setCellValue('B1', 'Turno');
        $worksheet->setCellValue('C1', 'PC');
        $worksheet->setCellValue('D1', 'Acción');
        $worksheet->setCellValue('E1', 'Archivo Original');
        $worksheet->setCellValue('F1', 'Archivo Nuevo');
        $worksheet->setCellValue('G1', 'Páginas');
        $worksheet->setCellValue('H1', 'Fecha y Hora');
        $worksheet->setCellValue('I1', 'Enlace PDF');

        // Formatear cabecera con el azul del menú (#034d6d)
        $worksheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '034D6D'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Llenar datos
        $rowIdx = 2;
        foreach ($registrosArr as $fila) {
            $nombreCompleto = $fila->NombreCompleto ?? ($fila->Usuario ?? 'Desconocido');
            $worksheet->setCellValue("A{$rowIdx}", $nombreCompleto);
            $worksheet->setCellValue("B{$rowIdx}", $fila->Turno ?: 'Matutino');
            $worksheet->setCellValue("C{$rowIdx}", $fila->PC ?: 'Sin PC');
            $worksheet->setCellValue("D{$rowIdx}", $fila->Accion);
            $worksheet->setCellValue("E{$rowIdx}", $fila->ArchivoOriginal);
            $worksheet->setCellValue("F{$rowIdx}", $fila->ArchivoNuevo);
            $worksheet->setCellValue("G{$rowIdx}", (int) $fila->Paginas);
            $worksheet->setCellValue("H{$rowIdx}", $fila->FechaHora);

            // Enlace al PDF
            if (!empty($fila->Ruta)) {
                $enlaceUrl = route('admin.analytics_sellos.reportes.ver_pdf', $fila->Id);
                $worksheet->setCellValue("I{$rowIdx}", 'Ver PDF');
                $worksheet->getCell("I{$rowIdx}")->getHyperlink()->setUrl($enlaceUrl);
                $worksheet->getStyle("I{$rowIdx}")->getFont()->setUnderline(true)->setColor(new Color(Color::COLOR_BLUE));
            } else {
                $worksheet->setCellValue("I{$rowIdx}", 'Sin Ruta');
            }

            // Pintar color de fila según el Turno usando métodos directos para máxima velocidad
            $turno = trim($fila->Turno ?: 'Matutino');
            $colorHtml = 'FFFFFF';
            if (stripos($turno, 'matutino') !== false) {
                $colorHtml = 'E2EFDA'; // Verde pastel
            } elseif (stripos($turno, 'vespertino') !== false) {
                $colorHtml = 'FFF2CC'; // Amarillo pastel
            } elseif (stripos($turno, 'mixto') !== false) {
                $colorHtml = 'FCE4D6'; // Naranja pastel
            } elseif (stripos($turno, 'nocturno') !== false) {
                $colorHtml = 'DDEBF7'; // Azul pastel
            } else {
                $colorHtml = 'F2F2F2'; // Gris suave
            }

            if ($colorHtml !== 'FFFFFF') {
                $style = $worksheet->getStyle("A{$rowIdx}:I{$rowIdx}");
                $style->getFill()->setFillType(Fill::FILL_SOLID);
                $style->getFill()->getStartColor()->setRGB($colorHtml);
            }

            $rowIdx++;
        }

        // Aplicar bordes de forma masiva (Infinitamente más rápido que fila por fila)
        $maxRow = $rowIdx - 1;
        if ($maxRow >= 2) {
            $rangeStyle = $worksheet->getStyle("A2:I{$maxRow}");
            $rangeStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $rangeStyle->getBorders()->getAllBorders()->getColor()->setRGB('D9D9D9');
        }

        // Calcular resumen por turnos en PHP
        $resumenTurnos = [
            'Matutino' => ['pdfs' => 0, 'imagenes' => 0],
            'Vespertino' => ['pdfs' => 0, 'imagenes' => 0],
            'Mixto' => ['pdfs' => 0, 'imagenes' => 0],
            'Nocturno' => ['pdfs' => 0, 'imagenes' => 0],
        ];
        foreach ($registrosArr as $fila) {
            $accion = $fila->Accion ?? 'Otras';
            // Excluir acciones de eliminación del resumen por turnos
            if (stripos($accion, 'eliminad') !== false || stripos($accion, 'eliminar') !== false) {
                continue;
            }
            $t = trim($fila->Turno ?: 'Matutino');
            $tGrupo = 'Matutino';
            if (stripos($t, 'vespertino') !== false) {
                $tGrupo = 'Vespertino';
            } elseif (stripos($t, 'mixto') !== false) {
                $tGrupo = 'Mixto';
            } elseif (stripos($t, 'nocturno') !== false) {
                $tGrupo = 'Nocturno';
            }
            $resumenTurnos[$tGrupo]['pdfs']++;
            $resumenTurnos[$tGrupo]['imagenes'] += (int) $fila->Paginas;
        }

        // Escribir tabla resumen arriba a la derecha (columnas K, L, M)
        $startResumenRow = 2;

        // Cabecera Resumen por Turno
        $worksheet->mergeCells("K{$startResumenRow}:M{$startResumenRow}");
        $worksheet->setCellValue("K{$startResumenRow}", "Resumen de Productividad por Turno");
        $worksheet->getStyle("K{$startResumenRow}:M{$startResumenRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '034D6D'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Cabeceras de columnas
        $worksheet->setCellValue("K" . ($startResumenRow + 1), "Turno");
        $worksheet->setCellValue("L" . ($startResumenRow + 1), "PDFs");
        $worksheet->setCellValue("M" . ($startResumenRow + 1), "Imágenes");
        $worksheet->getStyle("K" . ($startResumenRow + 1) . ":M" . ($startResumenRow + 1))->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Filas de Turnos
        $turnos = ['Matutino', 'Vespertino', 'Mixto', 'Nocturno'];
        $currentRow = $startResumenRow + 2;
        $totalPdfs = 0;
        $totalImgs = 0;

        foreach ($turnos as $turnoKey) {
            $pdfs = $resumenTurnos[$turnoKey]['pdfs'];
            $imgs = $resumenTurnos[$turnoKey]['imagenes'];
            $totalPdfs += $pdfs;
            $totalImgs += $imgs;

            $worksheet->setCellValue("K{$currentRow}", $turnoKey);
            $worksheet->setCellValue("L{$currentRow}", $pdfs);
            $worksheet->setCellValue("M{$currentRow}", $imgs);

            // Alinear números al centro
            $worksheet->getStyle("L{$currentRow}:M{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Colores pastel por turno
            $color = 'FFFFFF';
            if ($turnoKey === 'Matutino')
                $color = 'E2EFDA';
            elseif ($turnoKey === 'Vespertino')
                $color = 'FFF2CC';
            elseif ($turnoKey === 'Mixto')
                $color = 'FCE4D6';
            elseif ($turnoKey === 'Nocturno')
                $color = 'DDEBF7';

            $worksheet->getStyle("K{$currentRow}:M{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID);
            $worksheet->getStyle("K{$currentRow}:M{$currentRow}")->getFill()->getStartColor()->setRGB($color);

            $currentRow++;
        }

        // Fila de Total General
        $worksheet->setCellValue("K{$currentRow}", "Total General");
        $worksheet->setCellValue("L{$currentRow}", $totalPdfs);
        $worksheet->setCellValue("M{$currentRow}", $totalImgs);
        $worksheet->getStyle("K{$currentRow}:M{$currentRow}")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $worksheet->getStyle("K{$currentRow}:M{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID);
        $worksheet->getStyle("K{$currentRow}:M{$currentRow}")->getFill()->getStartColor()->setRGB('F2F2F2');

        // Bordes de la tabla resumen
        $worksheet->getStyle("K{$startResumenRow}:M{$currentRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $worksheet->getStyle("K{$startResumenRow}:M{$currentRow}")->getBorders()->getAllBorders()->getColor()->setRGB('D9D9D9');

        // Ajustar el ancho de las columnas
        $worksheet->getColumnDimension('E')->setWidth(60); // Archivo Original (ancho 60)
        $worksheet->getColumnDimension('F')->setWidth(60); // Archivo Nuevo (ancho 60)
        foreach (['A', 'B', 'C', 'D', 'G', 'H', 'I', 'K', 'L', 'M'] as $colLetter) {
            $worksheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $fileName = 'Reporte_Detallado_Captura_Sellos_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }

    /**
     * Descarga el reporte de resumen de sellos agrupado (como la tabla de la vista).
     */
    public function descargar_excel_resumen(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;
        $tipoSello = $request->tipo_sello;

        $query = DB::table('auditoria_sellos as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                DB::raw('COALESCE(d.nombre, ac.directorio, "Desconocida") as Delegacion'),
                'ac.paginas as Paginas',
                'ac.pc as PC',
                'ac.categoria as Categoria'
            );

        // Excluir Correccion renombre
        $query->where('ac.accion', '!=', 'Correccion renombre');

        // Excluir inconsistencias, archivos movidos a inconsistencia y archivos movidos a libros
        $query->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%libros%']);

        // Excluir administradores
        $query->where(function ($q) {
            $q->whereNull('ac.user_id')
                ->orWhere(function ($sub) {
                    $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                        ->where('u.name', 'not like', '%Administrador%');
                });
        });

        // Filtrado por fecha preliminar (SQL)
        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('ac.fecha_hora', '>=', $fechaMinima)
                ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        // Filtrado por tipo de sello
        if ($tipoSello) {
            $query->where('ac.tipo_sello', $tipoSello);
        }

        $registrosDb = $query->get();

        // Obtener fecha laboral según las reglas de corte y jornada
        $obtenerFechaLaboral = function ($fechaCompleta, $turno = null) {
            if (empty($fechaCompleta) || $fechaCompleta === '1900-01-01 00:00:00' || strlen($fechaCompleta) < 19) {
                if (strlen($fechaCompleta) === 10) {
                    return $fechaCompleta;
                }
                return 'Sin Fecha';
            }
            $timestamp = strtotime($fechaCompleta);
            if ($timestamp === false) {
                return 'Sin Fecha';
            }

            $w = (int) date('w', $timestamp);
            $hora = (int) date('H', $timestamp);

            // Si es sábado o domingo y es un turno de día, pertenece al mismo día calendario
            if (
                ($w === 6 || $w === 0) && $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es sábado o domingo antes de las 9:00 AM (y no es turno de día), pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno de día de lunes a viernes, pertenece al mismo día calendario
            if (
                $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es Nocturno (o no se especifica), aplicamos la regla de corte a las 9:00 AM
            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        // Filtrar por fecha laboral exacta
        if ($inicio && $fin) {
            $registrosDb = $registrosDb->filter(function ($row) use ($inicio, $fin, $obtenerFechaLaboral) {
                $fechaLaboral = $obtenerFechaLaboral($row->FechaHora, $row->Turno);
                if ($fechaLaboral === 'Sin Fecha') {
                    $fechaLaboral = date('Y-m-d', strtotime($row->FechaHora)) ?: '1900-01-01';
                }
                return $fechaLaboral >= $inicio && $fechaLaboral <= $fin;
            });
        }

        $datosPorFecha = [];
        $paginasTotales = [];
        $accionesUnicas = [];

        foreach ($registrosDb as $row) {
            $fechaHora = $row->FechaHora;
            $turno = $row->Turno;
            $fecha = $obtenerFechaLaboral($fechaHora, $turno);
            if ($fecha === 'Sin Fecha') {
                $fecha = date('Y-m-d', strtotime($fechaHora)) ?: 'Sin Fecha';
            }
            if ($fecha === 'Sin Fecha') {
                continue;
            }

            $pc = $row->PC ?: 'Sin PC';
            $nombreUsuario = $row->NombreCompleto ?? ($row->Usuario ?? 'Desconocido');
            $turnoUsuario = $row->Turno ?: 'Matutino';
            $categoriaUsuario = $row->Categoria ?: 'Captura';

            $accionOriginal = $row->Accion ?: 'Otras';
            $accion = $accionOriginal;
            if (stripos($accion, 'eliminad') !== false || stripos($accion, 'eliminar') !== false) {
                $accion = 'Eliminado';
            } elseif (stripos($accion, 'inconsistencia') !== false) {
                $accion = 'Inconsistencia';
            }

            if (!in_array($accion, $accionesUnicas)) {
                $accionesUnicas[] = $accion;
            }

            // Agrupar
            if (!isset($datosPorFecha[$fecha])) {
                $datosPorFecha[$fecha] = [];
            }
            if (!isset($datosPorFecha[$fecha][$pc])) {
                $datosPorFecha[$fecha][$pc] = [];
            }
            if (!isset($datosPorFecha[$fecha][$pc][$nombreUsuario])) {
                $datosPorFecha[$fecha][$pc][$nombreUsuario] = [];
            }
            if (!isset($datosPorFecha[$fecha][$pc][$nombreUsuario][$turnoUsuario])) {
                $datosPorFecha[$fecha][$pc][$nombreUsuario][$turnoUsuario] = [];
            }
            if (!isset($datosPorFecha[$fecha][$pc][$nombreUsuario][$turnoUsuario][$categoriaUsuario])) {
                $datosPorFecha[$fecha][$pc][$nombreUsuario][$turnoUsuario][$categoriaUsuario] = [];
            }
            if (!isset($datosPorFecha[$fecha][$pc][$nombreUsuario][$turnoUsuario][$categoriaUsuario][$accion])) {
                $datosPorFecha[$fecha][$pc][$nombreUsuario][$turnoUsuario][$categoriaUsuario][$accion] = 0;
            }

            $datosPorFecha[$fecha][$pc][$nombreUsuario][$turnoUsuario][$categoriaUsuario][$accion]++;

            // Páginas (Solo si es acción de calidad)
            $esAccionCalidad = (
                stripos($accion, 'renombrar') !== false ||
                stripos($accion, 'renombrado') !== false ||
                stripos($accion, 'validado') !== false ||
                stripos($accion, 'validar') !== false ||
                stripos($accion, 'duplicado') !== false ||
                stripos($accion, 'duplicada') !== false ||
                stripos($accion, 'duplicar') !== false
            ) && (
                stripos($accion, 'eliminad') === false &&
                stripos($accion, 'eliminar') === false &&
                stripos($accion, 'inconsistencia') === false
            );

            $keyFila = "{$fecha}|{$pc}|{$nombreUsuario}|{$turnoUsuario}|{$categoriaUsuario}";
            if (!isset($paginasTotales[$keyFila])) {
                $paginasTotales[$keyFila] = 0;
            }
            $paginasTotales[$keyFila] += (int) ($row->Paginas ?: 0);
        }

        if (empty($datosPorFecha)) {
            return redirect()->back()->with('error', 'No se encontraron datos para exportar.');
        }

        if (!in_array('Eliminado', $accionesUnicas)) {
            $accionesUnicas[] = 'Eliminado';
        }

        sort($accionesUnicas);

        $spreadsheet = new Spreadsheet();
        $firstSheet = true;

        krsort($datosPorFecha);

        foreach ($datosPorFecha as $fecha => $pcsDeLaFecha) {
            $sheetName = str_replace('-', '_', $fecha);
            if (strlen($sheetName) > 31) {
                $sheetName = substr($sheetName, 0, 31);
            }

            if ($firstSheet) {
                $worksheet = $spreadsheet->getActiveSheet();
                $worksheet->setTitle($sheetName);
                $firstSheet = false;
            } else {
                $worksheet = $spreadsheet->createSheet();
                $worksheet->setTitle($sheetName);
            }

            // Cabeceras
            $worksheet->setCellValue('A1', 'PC');
            $worksheet->setCellValue('B1', 'Usuario');
            $worksheet->setCellValue('C1', 'Turno');
            $worksheet->setCellValue('D1', 'Categoría');

            $colIdx = 5;
            foreach ($accionesUnicas as $accion) {
                $worksheet->setCellValue([$colIdx, 1], $accion);
                $colIdx++;
            }
            $worksheet->setCellValue([$colIdx, 1], 'Total Archivos');
            $worksheet->setCellValue([$colIdx + 1, 1], 'Total Páginas');

            // Formatear cabecera
            $lastColLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $headerRange = "A1:{$lastColLetter}1";

            $worksheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '034D6D'], // Color oscuro de cabeceras de sellos
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Crear lista plana para ordenar
            $listaFilas = [];
            foreach ($pcsDeLaFecha as $pc => $usuariosDePC) {
                foreach ($usuariosDePC as $usuario => $turnosDeUsuario) {
                    foreach ($turnosDeUsuario as $turno => $categoriasDeTurno) {
                        foreach ($categoriasDeTurno as $categoria => $accionesDeCategoria) {
                            $keyFila = "{$fecha}|{$pc}|{$usuario}|{$turno}|{$categoria}";
                            $paginas = $paginasTotales[$keyFila] ?? 0;
                            $listaFilas[] = [
                                'pc' => $pc,
                                'usuario' => $usuario,
                                'turno' => $turno,
                                'categoria' => $categoria,
                                'acciones' => $accionesDeCategoria,
                                'paginas' => $paginas
                            ];
                        }
                    }
                }
            }

            usort($listaFilas, function ($a, $b) {
                $prioCatA = (strcasecmp($a['categoria'], 'Calidad') === 0) ? 2 : 1;
                $prioCatB = (strcasecmp($b['categoria'], 'Calidad') === 0) ? 2 : 1;
                if ($prioCatA !== $prioCatB) {
                    return $prioCatA <=> $prioCatB;
                }

                $obtenerPrioTurno = function ($t) {
                    if (strcasecmp($t, 'Matutino') === 0)
                        return 1;
                    if (strcasecmp($t, 'Vespertino') === 0)
                        return 2;
                    if (strcasecmp($t, 'Mixto') === 0)
                        return 3;
                    if (strcasecmp($t, 'Nocturno') === 0)
                        return 4;
                    return 5;
                };

                $prioTurnoA = $obtenerPrioTurno($a['turno']);
                $prioTurnoB = $obtenerPrioTurno($b['turno']);
                return $prioTurnoA <=> $prioTurnoB;
            });

            // Llenar datos
            $rowIdx = 2;
            foreach ($listaFilas as $fila) {
                $worksheet->setCellValue("A{$rowIdx}", $fila['pc']);
                $worksheet->setCellValue("B{$rowIdx}", $fila['usuario']);
                $worksheet->setCellValue("C{$rowIdx}", $fila['turno']);
                $worksheet->setCellValue("D{$rowIdx}", $fila['categoria']);

                $totalFila = 0;
                $currentCol = 5;
                foreach ($accionesUnicas as $accion) {
                    $conteo = $fila['acciones'][$accion] ?? 0;
                    $worksheet->setCellValue([$currentCol, $rowIdx], $conteo);
                    $totalFila += $conteo;
                    $currentCol++;
                }
                $worksheet->setCellValue([$currentCol, $rowIdx], $totalFila);
                $worksheet->setCellValue([$currentCol + 1, $rowIdx], $fila['paginas']);

                // Color de fondo de fila
                $colorHtml = 'F2F2F2';
                if (strcasecmp($fila['categoria'], 'Calidad') === 0) {
                    $colorHtml = 'FADBD8';
                } else {
                    if (strcasecmp($fila['turno'], 'Matutino') === 0) {
                        $colorHtml = 'E2EFDA';
                    } elseif (strcasecmp($fila['turno'], 'Vespertino') === 0) {
                        $colorHtml = 'FFF2CC';
                    } elseif (strcasecmp($fila['turno'], 'Mixto') === 0) {
                        $colorHtml = 'FCE4D6';
                    } elseif (strcasecmp($fila['turno'], 'Nocturno') === 0) {
                        $colorHtml = 'DDEBF7';
                    }
                }

                $lastColLetter = Coordinate::stringFromColumnIndex($currentCol + 1);
                $rowRange = "A{$rowIdx}:{$lastColLetter}{$rowIdx}";

                $worksheet->getStyle($rowRange)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $colorHtml],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D9D9D9'],
                        ],
                    ],
                ]);

                $rowIdx++;
            }

            // Calcular resumen por turnos en PHP
            $resumenTurnos = [
                'Matutino' => ['pdfs' => 0, 'imagenes' => 0],
                'Vespertino' => ['pdfs' => 0, 'imagenes' => 0],
                'Mixto' => ['pdfs' => 0, 'imagenes' => 0],
                'Nocturno' => ['pdfs' => 0, 'imagenes' => 0],
            ];
            foreach ($listaFilas as $fila) {
                $t = trim($fila['turno'] ?: 'Matutino');
                $tGrupo = 'Matutino';
                if (stripos($t, 'vespertino') !== false) {
                    $tGrupo = 'Vespertino';
                } elseif (stripos($t, 'mixto') !== false) {
                    $tGrupo = 'Mixto';
                } elseif (stripos($t, 'nocturno') !== false) {
                    $tGrupo = 'Nocturno';
                }

                $totalFilaPdfs = 0;
                foreach ($accionesUnicas as $accion) {
                    $totalFilaPdfs += $fila['acciones'][$accion] ?? 0;
                }

                $resumenTurnos[$tGrupo]['pdfs'] += $totalFilaPdfs;
                $resumenTurnos[$tGrupo]['imagenes'] += (int) $fila['paginas'];
            }

            // Escribir tabla resumen al final a la derecha (columnas dinámicas tras los datos principales)
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
            $colResumenIdx = $highestColumnIndex + 2;
            $startResumenRow = 2;

            $colK = Coordinate::stringFromColumnIndex($colResumenIdx);
            $colL = Coordinate::stringFromColumnIndex($colResumenIdx + 1);
            $colM = Coordinate::stringFromColumnIndex($colResumenIdx + 2);

            $worksheet->mergeCells("{$colK}{$startResumenRow}:{$colM}{$startResumenRow}");
            $worksheet->setCellValue("{$colK}{$startResumenRow}", "Resumen por Turno");
            $worksheet->getStyle("{$colK}{$startResumenRow}:{$colM}{$startResumenRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '034D6D'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $worksheet->setCellValue("{$colK}" . ($startResumenRow + 1), "Turno");
            $worksheet->setCellValue("{$colL}" . ($startResumenRow + 1), "PDFs");
            $worksheet->setCellValue("{$colM}" . ($startResumenRow + 1), "Imágenes");
            $worksheet->getStyle("{$colK}" . ($startResumenRow + 1) . ":{$colM}" . ($startResumenRow + 1))->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $turnosList = ['Matutino', 'Vespertino', 'Mixto', 'Nocturno'];
            $currRow = $startResumenRow + 2;
            $sumPdfs = 0;
            $sumImgs = 0;

            foreach ($turnosList as $tKey) {
                $pdfs = $resumenTurnos[$tKey]['pdfs'] ?? 0;
                $imgs = $resumenTurnos[$tKey]['imagenes'] ?? 0;
                $sumPdfs += $pdfs;
                $sumImgs += $imgs;

                $worksheet->setCellValue("{$colK}{$currRow}", $tKey);
                $worksheet->setCellValue("{$colL}{$currRow}", $pdfs);
                $worksheet->setCellValue("{$colM}{$currRow}", $imgs);

                $worksheet->getStyle("{$colL}{$currRow}:{$colM}{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $color = 'FFFFFF';
                if ($tKey === 'Matutino')
                    $color = 'E2EFDA';
                elseif ($tKey === 'Vespertino')
                    $color = 'FFF2CC';
                elseif ($tKey === 'Mixto')
                    $color = 'FCE4D6';
                elseif ($tKey === 'Nocturno')
                    $color = 'DDEBF7';

                $worksheet->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->getFill()->setFillType(Fill::FILL_SOLID);
                $worksheet->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->getFill()->getStartColor()->setRGB($color);

                $currRow++;
            }

            $worksheet->setCellValue("{$colK}{$currRow}", "Total General");
            $worksheet->setCellValue("{$colL}{$currRow}", $sumPdfs);
            $worksheet->setCellValue("{$colM}{$currRow}", $sumImgs);
            $worksheet->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $worksheet->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->getFill()->setFillType(Fill::FILL_SOLID);
            $worksheet->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->getFill()->getStartColor()->setRGB('F2F2F2');

            // Bordes
            $worksheet->getStyle("{$colK}{$startResumenRow}:{$colM}{$currRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $worksheet->getStyle("{$colK}{$startResumenRow}:{$colM}{$currRow}")->getBorders()->getAllBorders()->getColor()->setRGB('D9D9D9');

            // Auto-ajustar columnas
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $worksheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
        }

        $fileName = 'Reporte_Resumen_KPI_Sellos_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
    public function detalles_renombre(Request $request)
    {
        $inicio = $request->inicio ?: date('Y-m-01');
        $fin = $request->fin ?: date('Y-m-t');
        $tipoSello = $request->tipo_sello;
        $userId = $request->user_id;
        $search = $request->search;
        $verAdmin = $request->ver_admin == '1';

        // Obtener usuarios capturadores distintos que tienen renombrados, validados y duplicados
        $usuariosQuery = DB::table('auditoria_sellos as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->select('u.id', 'u.name', 'u.user')
            ->whereNotNull('ac.user_id')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereRaw('LOWER(ac.accion) LIKE ?', ['%renombrar%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%renombrado%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validado%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validar%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%duplicado%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%duplicada%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%duplicar%']);
                })
                    ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%eliminado%'])
                    ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%eliminar%']);
            });

        if (!$verAdmin) {
            $usuariosQuery->where(function ($q) {
                $q->where('ac.user_id', '<>', 1)
                    ->where(function ($sub) {
                        $sub->whereNull('u.id')
                            ->orWhere(function ($s2) {
                                $s2->where('u.id', '<>', 1)
                                    ->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                                    ->where('u.name', 'not like', '%Administrador%');
                            });
                    });
            });
        }

        $usuarios = $usuariosQuery->distinct()
            ->orderBy('u.name', 'asc')
            ->get();

        // Consulta SQL para obtener la fecha laboral considerando cortes de fin de semana y turnos de día
        $fechaLaboralSql = 'CASE 
            WHEN (ac.turno IS NOT NULL AND (
                LOWER(ac.turno) LIKE "%matutino%" OR 
                LOWER(ac.turno) LIKE "%vespertino%" OR 
                LOWER(ac.turno) LIKE "%mixto%" OR 
                LOWER(ac.turno) LIKE "%calidad%" OR 
                LOWER(ac.turno) LIKE "%sabado%" OR 
                LOWER(ac.turno) LIKE "%sábado%" OR 
                LOWER(ac.turno) LIKE "%domingo%"
            )) THEN DATE(ac.fecha_hora)
            WHEN (WEEKDAY(ac.fecha_hora) IN (5, 6) AND HOUR(ac.fecha_hora) < 9) THEN DATE(DATE_SUB(ac.fecha_hora, INTERVAL 1 DAY))
            WHEN HOUR(ac.fecha_hora) < 9 THEN DATE(DATE_SUB(ac.fecha_hora, INTERVAL 1 DAY))
            ELSE DATE(ac.fecha_hora)
        END';

        $query = DB::table('auditoria_sellos as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.id as Id',
                'ac.ruta as Ruta',
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                'ac.archivo_original as ArchivoOriginal',
                'ac.archivo_nuevo as ArchivoNuevo',
                'ac.tipo_sello as TipoSello',
                DB::raw("{$fechaLaboralSql} as FechaLaboral")
            );

        // Excluir administradores salvo que se active ver_admin
        if (!$verAdmin) {
            $query->where(function ($q) {
                $q->whereNull('ac.user_id')
                    ->orWhere(function ($sub) {
                        $sub->where('ac.user_id', '<>', 1)
                            ->where('u.id', '<>', 1)
                            ->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                            ->where('u.name', 'not like', '%Administrador%');
                    });
            });
        }

        // Filtrar únicamente las acciones de renombrado, validado o duplicado (excluyendo eliminados)
        $query->where(function ($q) {
            $q->where(function ($sub) {
                $sub->whereRaw('LOWER(ac.accion) LIKE ?', ['%renombrar%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%renombrado%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validado%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validar%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%duplicado%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%duplicada%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%duplicar%']);
            })
                ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%eliminado%'])
                ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%eliminar%']);
        });

        // Filtrado por usuario
        if ($userId) {
            $query->where('ac.user_id', $userId);
        }

        // Filtrado por búsqueda de archivos
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ac.archivo_original) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(ac.archivo_nuevo) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Filtrado por fecha laboral (SQL exacto)
        if (empty($search) && $inicio && $fin) {
            $query->whereRaw("({$fechaLaboralSql}) >= ?", [$inicio])
                ->whereRaw("({$fechaLaboralSql}) <= ?", [$fin]);
        }

        // Filtrado por tipo de sello
        if ($tipoSello) {
            $query->where('ac.tipo_sello', $tipoSello);
        }

        $diferenciaDias = 999;
        if ($inicio && $fin) {
            $diffSeconds = strtotime($fin) - strtotime($inicio);
            $diferenciaDias = round($diffSeconds / 86400);
        }

        // Si el rango de fechas es corto (3 días o menos), no paginamos para mostrar todos los registros y turnos completos.
        // Si el rango es mayor (como la vista por defecto de todo el mes), paginamos por días laborales para evitar fragmentación.
        if ($diferenciaDias <= 3) {
            $registrosDb = $query->orderBy('ac.fecha_hora', 'desc')->get();
            $registrosPaginados = null;
        } else {
            // Construir la consulta de fechas y turnos de forma limpia para evitar arrastre de bindings
            $normalizarTurnoSql = "CASE
                WHEN LOWER(ac.turno) LIKE '%nocturno%' THEN 'Nocturno'
                WHEN LOWER(ac.turno) LIKE '%vespertino%' THEN 'Vespertino'
                WHEN LOWER(ac.turno) LIKE '%mixto%' THEN 'Mixto'
                ELSE 'Matutino'
            END";

            $prioTurnoSql = "CASE
                WHEN LOWER(ac.turno) LIKE '%nocturno%' THEN 1
                WHEN LOWER(ac.turno) LIKE '%vespertino%' THEN 2
                WHEN LOWER(ac.turno) LIKE '%mixto%' THEN 3
                ELSE 4
            END";

            $queryFechas = DB::table('auditoria_sellos as ac')
                ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
                ->select(
                    DB::raw("DISTINCT {$fechaLaboralSql} as FechaLaboral"),
                    DB::raw("({$normalizarTurnoSql}) as TurnoGrupo")
                );

            // Excluir administradores salvo que se active ver_admin
            if (!$verAdmin) {
                $queryFechas->where(function ($q) {
                    $q->whereNull('ac.user_id')
                        ->orWhere(function ($sub) {
                            $sub->where('ac.user_id', '<>', 1)
                                ->where('u.id', '<>', 1)
                                ->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                                ->where('u.name', 'not like', '%Administrador%');
                        });
                });
            }

            // Filtrar únicamente las acciones de renombrado, validado o duplicado (excluyendo eliminados)
            $queryFechas->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereRaw('LOWER(ac.accion) LIKE ?', ['%renombrar%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%renombrado%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validado%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validar%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%duplicado%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%duplicada%'])
                        ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%duplicar%']);
                })
                    ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%eliminado%'])
                    ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%eliminar%']);
            });

            // Filtrado por usuario
            if ($userId) {
                $queryFechas->where('ac.user_id', $userId);
            }

            // Filtrado por búsqueda de archivos
            if (!empty($search)) {
                $queryFechas->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(ac.archivo_original) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(ac.archivo_nuevo) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            }

            // Filtrado por fecha laboral (SQL exacto)
            if (empty($search) && $inicio && $fin) {
                $queryFechas->whereRaw("({$fechaLaboralSql}) >= ?", [$inicio])
                    ->whereRaw("({$fechaLaboralSql}) <= ?", [$fin]);
            }

            // Filtrado por tipo de sello
            if ($tipoSello) {
                $queryFechas->where('ac.tipo_sello', $tipoSello);
            }

            // Ordenar por FechaLaboral desc, y luego por prioridad de turno (Nocturno, Vespertino, Matutino)
            $queryFechas->orderBy('FechaLaboral', 'desc')
                ->orderBy(DB::raw($prioTurnoSql), 'asc');

            $fechasPaginadas = $queryFechas->paginate(1)->appends(request()->query());
            $itemActual = collect($fechasPaginadas->items())->first();

            if ($itemActual) {
                $fecha = $itemActual->FechaLaboral;
                $turnoGrupo = $itemActual->TurnoGrupo;

                // Filtrar el query detallado por la fecha de la página actual y su turno correspondiente
                $query->whereRaw("({$fechaLaboralSql}) = ?", [$fecha]);

                if ($turnoGrupo === 'Nocturno') {
                    $query->whereRaw("LOWER(ac.turno) LIKE '%nocturno%'");
                } elseif ($turnoGrupo === 'Vespertino') {
                    $query->whereRaw("LOWER(ac.turno) LIKE '%vespertino%'");
                } elseif ($turnoGrupo === 'Mixto') {
                    $query->whereRaw("LOWER(ac.turno) LIKE '%mixto%'");
                } else {
                    $query->where(function ($q) {
                        $q->whereRaw("LOWER(ac.turno) LIKE '%matutino%'")
                            ->orWhereNull('ac.turno')
                            ->orWhereRaw("LOWER(ac.turno) NOT LIKE '%nocturno%' AND LOWER(ac.turno) NOT LIKE '%vespertino%' AND LOWER(ac.turno) NOT LIKE '%mixto%'");
                    });
                }

                $registrosDb = $query->orderBy('ac.fecha_hora', 'desc')->get();
            } else {
                $registrosDb = collect();
            }

            $registrosPaginados = $fechasPaginadas;
        }

        $registrosArr = $registrosDb->values()->all();

        // Ordenar registros usando la propiedad precalculada (Nocturno -> Vespertino -> Matutino)
        usort($registrosArr, function ($a, $b) {
            if ($a->FechaLaboral !== $b->FechaLaboral) {
                return strcmp($b->FechaLaboral, $a->FechaLaboral);
            }

            $obtenerPrioTurno = function ($t) {
                $t = trim(strtolower($t));
                if (strpos($t, 'nocturno') !== false) {
                    return 1;
                }
                if (strpos($t, 'vespertino') !== false) {
                    return 2;
                }
                if (strpos($t, 'mixto') !== false) {
                    return 3;
                }
                if (strpos($t, 'matutino') !== false) {
                    return 4;
                }
                return 5;
            };

            $prioA = $obtenerPrioTurno($a->Turno);
            $prioB = $obtenerPrioTurno($b->Turno);
            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }

            $nombreA = $a->NombreCompleto ?? ($a->Usuario ?? '');
            $nombreB = $b->NombreCompleto ?? ($b->Usuario ?? '');
            $compUsuario = strcasecmp($nombreA, $nombreB);
            if ($compUsuario !== 0) {
                return $compUsuario;
            }

            // Ordenar por hora descendente (del mas reciente al mas antiguo)
            return strcmp($b->FechaHora, $a->FechaHora);
        });

        // Agrupar usando la propiedad precalculada
        $agrupados = collect($registrosArr)->groupBy([
            function ($item) {
                return $item->FechaLaboral;
            },
            function ($item) {
                return $item->NombreCompleto ?? ($item->Usuario ?? 'Desconocido');
            }
        ])->sortKeysDesc();

        return view('bitacora.historial_sellos', compact('agrupados', 'registrosArr', 'inicio', 'fin', 'tipoSello', 'usuarios', 'userId', 'search', 'registrosPaginados', 'verAdmin'));
    }

    public function ver_pdf($id)
    {
        $registro = \App\Models\AuditoriaSellos::findOrFail($id);
        $ruta = $registro->ruta;

        if (empty($ruta) || !file_exists($ruta)) {
            return abort(404, 'El archivo PDF no existe en la ruta física: ' . $ruta);
        }

        return response()->file($ruta, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($ruta) . '"'
        ]);
    }

    public function editar_renombre(Request $request)
    {
        try {
            $id = $request->input('id');
            $nombreNuevo = trim($request->input('nombre_nuevo'));

            if (empty($id) || empty($nombreNuevo)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Datos incompletos. Se requiere ID y el nuevo nombre del archivo.'
                ], 400);
            }

            // Asegurar que tenga la extensión .pdf
            if (strtolower(pathinfo($nombreNuevo, PATHINFO_EXTENSION)) !== 'pdf') {
                $nombreNuevo .= '.pdf';
            }

            $registro = \App\Models\AuditoriaSellos::findOrFail($id);
            $rutaActual = $registro->ruta;
            $nombreActual = $registro->archivo_nuevo ?: $registro->archivo_original;

            if (empty($rutaActual)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'El registro de auditoría no cuenta con una ruta de archivo registrada.'
                ], 400);
            }

            // 1. Extraer el año del nuevo nombre (antepenúltimo bloque)
            $nombreSinExt = pathinfo($nombreNuevo, PATHINFO_FILENAME);
            $partesNuevas = explode('_', $nombreSinExt);
            $totalNuevas = count($partesNuevas);
            $anioNuevo = "";

            if ($totalNuevas >= 9) {
                $anioStr = $partesNuevas[$totalNuevas - 3];
                if (preg_match('/^\d{2}$/', $anioStr)) {
                    $anioNuevo = "20" . $anioStr;
                } elseif (preg_match('/^\d{4}$/', $anioStr)) {
                    $anioNuevo = $anioStr;
                }
            }

            // 2. Determinar la nueva ruta física del archivo
            $nuevaRuta = "";
            $carpetaActual = basename(dirname($rutaActual)); // Ej: "2010" o "Lote 1"

            if (preg_match('/^\d{4}$/', $carpetaActual) && !empty($anioNuevo)) {
                // Si la carpeta contenedora actual es de formato año, reubicamos en el nuevo año
                $dirPadreAnio = dirname(dirname($rutaActual));
                $nuevaRuta = $dirPadreAnio . DIRECTORY_SEPARATOR . $anioNuevo . DIRECTORY_SEPARATOR . $nombreNuevo;
            } else {
                // Si no, lo mantenemos en la misma carpeta física reemplazando solo el nombre del archivo
                $nuevaRuta = dirname($rutaActual) . DIRECTORY_SEPARATOR . $nombreNuevo;
            }

            // 3. Renombrado y movimiento físico
            if (file_exists($rutaActual)) {
                $directorioDestino = dirname($nuevaRuta);
                if (!file_exists($directorioDestino)) {
                    if (!mkdir($directorioDestino, 0777, true) && !is_dir($directorioDestino)) {
                        return response()->json([
                            'ok' => false,
                            'mensaje' => 'No se pudo crear el directorio destino: ' . $directorioDestino
                        ], 500);
                    }
                }

                if (file_exists($nuevaRuta) && $rutaActual !== $nuevaRuta) {
                    return response()->json([
                        'ok' => false,
                        'mensaje' => 'El archivo de destino ya existe físicamente en la ruta: ' . $nuevaRuta
                    ], 400);
                }

                if (!rename($rutaActual, $nuevaRuta)) {
                    return response()->json([
                        'ok' => false,
                        'mensaje' => 'Error al renombrar el archivo en el sistema de archivos.'
                    ], 500);
                }
            } else {
                // Si el archivo físico no existe (por ejemplo, en desarrollo local o red desconectada)
                $advertencia = "Advertencia: El archivo físico no fue encontrado en la ruta de red, pero se actualizó el registro en la base de datos.";
            }

            // 4. Actualizar la base de datos
            $registro->archivo_nuevo = $nombreNuevo;
            $registro->ruta = $nuevaRuta;

            // Si el modelo AuditoriaSellos tiene timestamps, Eloquent los gestionará.
            // Pero como es inserción manual en base de datos en el VB.NET, guardamos sin problemas.
            $registro->save();

            self::logSellos('info', 'Renombre de sello editado', [
                'id' => $id,
                'nombre_anterior' => $nombreActual,
                'nombre_nuevo' => $nombreNuevo,
                'nueva_ruta' => $nuevaRuta
            ]);

            return response()->json([
                'ok' => true,
                'mensaje' => isset($advertencia) ? $advertencia : 'Renombre editado y archivo reubicado físicamente con éxito.',
                'archivo_nuevo' => $nombreNuevo,
                'nueva_ruta' => $nuevaRuta
            ]);

        } catch (\Throwable $e) {
            self::logSellos('error', 'Error al editar renombre de sello: ' . $e->getMessage(), [
                'id' => $id ?? null,
                'nombre_nuevo' => $nombreNuevo ?? null
            ]);
            return response()->json([
                'ok' => false,
                'mensaje' => 'Error crítico al editar renombre: ' . $e->getMessage()
            ], 500);
        }
    }
    public function regresar_pdf(Request $request)
    {
        try {
            $id = $request->input('id');

            if (empty($id)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Se requiere el ID del registro.'
                ], 400);
            }

            $registro = \App\Models\AuditoriaSellos::findOrFail($id);
            $rutaActual = $registro->ruta;
            $nombreOriginal = $registro->archivo_original;
            $directorioOrigen = $registro->directorio;

            if (empty($rutaActual) || empty($nombreOriginal) || empty($directorioOrigen)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'No se puede regresar el PDF debido a que los datos de origen o destino en la base de datos están incompletos.'
                ], 400);
            }

            // Validar que el archivo físico exista en la ruta de Calidad
            if (!file_exists($rutaActual)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'El archivo físico no se encuentra en la ruta de Calidad: ' . $rutaActual
                ], 404);
            }

            // Construir la ruta de origen de forma segura
            $directorioOrigen = rtrim($directorioOrigen, DIRECTORY_SEPARATOR);

            if (!file_exists($directorioOrigen)) {
                if (!mkdir($directorioOrigen, 0777, true) && !is_dir($directorioOrigen)) {
                    return response()->json([
                        'ok' => false,
                        'mensaje' => 'El directorio de origen no existe y no pudo ser creado: ' . $directorioOrigen
                    ], 500);
                }
            }

            $rutaOrigenCompleta = $directorioOrigen . DIRECTORY_SEPARATOR . $nombreOriginal;

            // Mover físicamente el archivo de vuelta a origen
            if (!rename($rutaActual, $rutaOrigenCompleta)) {
                // Intentar copiar y borrar si rename falla debido a cruce de unidades de red (ej: de Z:\ a C:\)
                if (copy($rutaActual, $rutaOrigenCompleta)) {
                    unlink($rutaActual);
                } else {
                    return response()->json([
                        'ok' => false,
                        'mensaje' => 'Error al mover el archivo de vuelta a origen. Valida los permisos de red.'
                    ], 500);
                }
            }

            // Eliminar el registro de auditoría de la base de datos
            $registro->delete();

            self::logSellos('info', 'PDF de sellos regresado a origen', [
                'id' => $id,
                'archivo' => $nombreOriginal,
                'origen' => $rutaOrigenCompleta
            ]);

            return response()->json([
                'ok' => true,
                'mensaje' => 'El archivo PDF ha sido regresado exitosamente a su origen y el registro de auditoría fue eliminado.'
            ]);

        } catch (\Exception $e) {
            self::logSellos('error', 'Error al regresar PDF de sellos: ' . $e->getMessage(), [
                'id' => $id ?? null
            ]);
            return response()->json([
                'ok' => false,
                'mensaje' => 'Ocurrió un error al intentar regresar el PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regresa un lote de archivos PDF de calidad a su ubicación origen y elimina los registros de auditoría de la base de datos.
     */
    public function regresar_pdf_lote(Request $request)
    {
        try {
            $ids = $request->input('ids');

            if (empty($ids) || !is_array($ids)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Se requiere una lista de IDs válida.'
                ], 400);
            }

            $exitos = 0;
            $fallas = [];

            foreach ($ids as $id) {
                try {
                    $registro = \App\Models\AuditoriaSellos::find($id);
                    if (!$registro) {
                        $fallas[] = "Registro ID {$id} no encontrado.";
                        continue;
                    }

                    $rutaActual = $registro->ruta;
                    $nombreOriginal = $registro->archivo_original;
                    $directorioOrigen = $registro->directorio;

                    if (empty($rutaActual) || empty($nombreOriginal) || empty($directorioOrigen)) {
                        $fallas[] = "El registro del archivo '{$nombreOriginal}' tiene datos incompletos.";
                        continue;
                    }

                    // Validar que el archivo físico exista en la ruta de Calidad
                    if (!file_exists($rutaActual)) {
                        $fallas[] = "El archivo físico '{$nombreOriginal}' no existe en Calidad.";
                        continue;
                    }

                    // Construir la ruta de origen
                    $directorioOrigen = rtrim($directorioOrigen, DIRECTORY_SEPARATOR);
                    if (!file_exists($directorioOrigen)) {
                        if (!mkdir($directorioOrigen, 0777, true) && !is_dir($directorioOrigen)) {
                            $fallas[] = "No se pudo crear la carpeta origen para '{$nombreOriginal}'.";
                            continue;
                        }
                    }

                    $rutaOrigenCompleta = $directorioOrigen . DIRECTORY_SEPARATOR . $nombreOriginal;

                    // Mover físicamente el archivo
                    if (!rename($rutaActual, $rutaOrigenCompleta)) {
                        if (copy($rutaActual, $rutaOrigenCompleta)) {
                            unlink($rutaActual);
                        } else {
                            $fallas[] = "No se pudo mover físicamente el archivo '{$nombreOriginal}'.";
                            continue;
                        }
                    }

                    // Eliminar el registro
                    $registro->delete();
                    $exitos++;

                } catch (\Exception $exReg) {
                    $fallas[] = "Error al procesar ID {$id}: " . $exReg->getMessage();
                }
            }

            if ($exitos === 0) {
                self::logSellos('warning', 'No se pudo regresar ningún archivo del lote de sellos', ['fallas' => $fallas]);
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'No se pudo regresar ningún archivo. Detalles: ' . implode(' | ', $fallas)
                ], 500);
            }

            $msg = "Se regresaron con éxito {$exitos} archivos.";
            if (count($fallas) > 0) {
                $msg .= " Pero hubieron " . count($fallas) . " errores: " . implode(' | ', $fallas);
            }

            self::logSellos('info', 'Lote de PDFs de sellos regresado', [
                'exitos' => $exitos,
                'fallas_count' => count($fallas),
                'fallas' => $fallas
            ]);

            return response()->json([
                'ok' => true,
                'mensaje' => $msg
            ]);

        } catch (\Exception $e) {
            self::logSellos('error', 'Error general al procesar lote de sellos regresados: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'mensaje' => 'Ocurrió un error general al procesar el lote: ' . $e->getMessage()
            ], 500);
        }
    }

    public function descargar_excel_delegaciones(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;
        $tipoSello = $request->tipo_sello;

        $query = DB::table('auditoria_sellos as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.fecha_hora as FechaHora',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                'ac.tipo_sello as TipoSello',
                DB::raw('COALESCE(d.nombre, ac.directorio, "Desconocida") as Delegacion'),
                'ac.paginas as Paginas'
            );

        // Excluir Correccion renombre
        $query->where('ac.accion', '!=', 'Correccion renombre');

        // Excluir inconsistencias, archivos movidos a inconsistencia y archivos movidos a libros
        $query->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%libros%']);

        // Excluir administradores
        $query->where(function ($q) {
            $q->whereNull('ac.user_id')
                ->orWhere(function ($sub) {
                    $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                        ->where('u.name', 'not like', '%Administrador%');
                });
        });

        // Filtrado por fecha preliminar (SQL)
        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('ac.fecha_hora', '>=', $fechaMinima)
                ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        // Filtrado por tipo de sello
        if ($tipoSello) {
            $query->where('ac.tipo_sello', $tipoSello);
        }

        $registrosDb = $query->get();

        // Obtener fecha laboral según las reglas de corte y jornada
        $obtenerFechaLaboral = function ($fechaCompleta, $turno = null) {
            if (empty($fechaCompleta) || $fechaCompleta === '1900-01-01 00:00:00' || strlen($fechaCompleta) < 19) {
                if (strlen($fechaCompleta) === 10) {
                    return $fechaCompleta;
                }
                return 'Sin Fecha';
            }
            $timestamp = strtotime($fechaCompleta);
            if ($timestamp === false) {
                return 'Sin Fecha';
            }

            $w = (int) date('w', $timestamp);
            $hora = (int) date('H', $timestamp);

            // Si es sábado o domingo y es un turno de día, pertenece al mismo día calendario
            if (
                ($w === 6 || $w === 0) && $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es sábado o domingo antes de las 9:00 AM (y no es turno de día), pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno de día de lunes a viernes, pertenece al mismo día calendario
            if (
                $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es Nocturno (o no se especifica), aplicamos la regla de corte a las 9:00 AM
            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        // Filtrar por fecha laboral exacta
        if ($inicio && $fin) {
            $registrosDb = $registrosDb->filter(function ($row) use ($inicio, $fin, $obtenerFechaLaboral) {
                $fechaLaboral = $obtenerFechaLaboral($row->FechaHora, $row->Turno);
                if ($fechaLaboral === 'Sin Fecha') {
                    $fechaLaboral = date('Y-m-d', strtotime($row->FechaHora)) ?: '1900-01-01';
                }
                return $fechaLaboral >= $inicio && $fechaLaboral <= $fin;
            });
        }

        $obtenerAccionNormalizada = function ($accionOriginal) {
            $acc = trim(strtolower($accionOriginal));
            if (strpos($acc, 'elimin') !== false) {
                return 'Eliminado';
            }
            if (strpos($acc, 'renombr') !== false || strpos($acc, 'renombre') !== false) {
                return 'Renombrado';
            }
            if (strpos($acc, 'duplic') !== false) {
                return 'Duplicado';
            }
            if (strpos($acc, 'valid') !== false) {
                return 'Validado';
            }
            return null;
        };

        $listaAcciones = ['Renombrado', 'Validado', 'Duplicado', 'Eliminado'];

        $datosAgrupados = [];
        $datosAgrupadosTipos = [];
        foreach ($registrosDb as $row) {
            $accionNorm = $obtenerAccionNormalizada($row->Accion);
            if ($accionNorm === null) {
                continue;
            }

            $delegacion = $row->Delegacion ?: 'Desconocida';
            if (!isset($datosAgrupados[$delegacion])) {
                $datosAgrupados[$delegacion] = [
                    'acciones' => [],
                    'pdfs' => 0,
                    'paginas' => 0
                ];
                foreach ($listaAcciones as $accion) {
                    $datosAgrupados[$delegacion]['acciones'][$accion] = ['pdfs' => 0, 'paginas' => 0];
                }
            }

            $tipoSello = $row->TipoSello ?: 'Desconocido';
            if (!isset($datosAgrupadosTipos[$tipoSello])) {
                $datosAgrupadosTipos[$tipoSello] = [
                    'acciones' => [],
                    'pdfs' => 0,
                    'paginas' => 0
                ];
                foreach ($listaAcciones as $accion) {
                    $datosAgrupadosTipos[$tipoSello]['acciones'][$accion] = ['pdfs' => 0, 'paginas' => 0];
                }
            }

            $paginas = (int) ($row->Paginas ?: 0);

            $datosAgrupados[$delegacion]['acciones'][$accionNorm]['pdfs']++;
            $datosAgrupados[$delegacion]['acciones'][$accionNorm]['paginas'] += $paginas;
            $datosAgrupados[$delegacion]['pdfs']++;
            $datosAgrupados[$delegacion]['paginas'] += $paginas;

            $datosAgrupadosTipos[$tipoSello]['acciones'][$accionNorm]['pdfs']++;
            $datosAgrupadosTipos[$tipoSello]['acciones'][$accionNorm]['paginas'] += $paginas;
            $datosAgrupadosTipos[$tipoSello]['pdfs']++;
            $datosAgrupadosTipos[$tipoSello]['paginas'] += $paginas;
        }

        if (empty($datosAgrupados)) {
            return redirect()->back()->with('error', 'No se encontraron datos para exportar.');
        }

        ksort($datosAgrupados);

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Resumen por Delegación');

        // Cabeceras
        $worksheet->setCellValue('A1', 'Delegación');

        $colCharIdx = 'B';
        foreach ($listaAcciones as $accion) {
            $worksheet->setCellValue($colCharIdx . '1', $accion . ' (PDFs)');
            $colCharIdx = chr(ord($colCharIdx) + 1);

            $worksheet->setCellValue($colCharIdx . '1', $accion . ' (Imágenes)');
            $colCharIdx = chr(ord($colCharIdx) + 1);
        }

        $colPdfsChar = $colCharIdx;
        $worksheet->setCellValue($colPdfsChar . '1', 'Total PDFs');

        $colPaginasChar = chr(ord($colCharIdx) + 1);
        $worksheet->setCellValue($colPaginasChar . '1', 'Total Imágenes');

        $headerRange = 'A1:' . $colPaginasChar . '1';
        $worksheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '034D6D'], // Azul oscuro de sellos
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $rowIdx = 2;
        $totalesAcciones = [];
        foreach ($listaAcciones as $accion) {
            $totalesAcciones[$accion] = ['pdfs' => 0, 'paginas' => 0];
        }
        $totalPdfs = 0;
        $totalPaginas = 0;

        foreach ($datosAgrupados as $delegacion => $valores) {
            $worksheet->setCellValue("A{$rowIdx}", $delegacion);

            $currentChar = 'B';
            foreach ($listaAcciones as $accion) {
                $conteoPdfs = $valores['acciones'][$accion]['pdfs'];
                $conteoPaginas = $valores['acciones'][$accion]['paginas'];

                $worksheet->setCellValue($currentChar . $rowIdx, $conteoPdfs);
                $totalesAcciones[$accion]['pdfs'] += $conteoPdfs;
                $currentChar = chr(ord($currentChar) + 1);

                $worksheet->setCellValue($currentChar . $rowIdx, $conteoPaginas);
                $totalesAcciones[$accion]['paginas'] += $conteoPaginas;
                $currentChar = chr(ord($currentChar) + 1);
            }

            $worksheet->setCellValue($currentChar . $rowIdx, $valores['pdfs']);
            $totalPdfs += $valores['pdfs'];
            $currentChar = chr(ord($currentChar) + 1);

            $worksheet->setCellValue($currentChar . $rowIdx, $valores['paginas']);
            $totalPaginas += $valores['paginas'];

            $worksheet->getStyle("B{$rowIdx}:" . $currentChar . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $worksheet->getStyle("A{$rowIdx}:" . $currentChar . $rowIdx)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ]);

            $rowIdx++;
        }

        // Fila de Total General
        $worksheet->setCellValue("A{$rowIdx}", 'Total General');

        $currentChar = 'B';
        foreach ($listaAcciones as $accion) {
            $worksheet->setCellValue($currentChar . $rowIdx, $totalesAcciones[$accion]['pdfs']);
            $currentChar = chr(ord($currentChar) + 1);

            $worksheet->setCellValue($currentChar . $rowIdx, $totalesAcciones[$accion]['paginas']);
            $currentChar = chr(ord($currentChar) + 1);
        }

        $worksheet->setCellValue($currentChar . $rowIdx, $totalPdfs);
        $currentChar = chr(ord($currentChar) + 1);

        $worksheet->setCellValue($currentChar . $rowIdx, $totalPaginas);

        $totalRange = "A{$rowIdx}:" . $currentChar . $rowIdx;
        $worksheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        $worksheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // --- INICIO NUEVA TABLA POR TIPO DE SELLO ---
        $rowIdx += 3; // Espacio entre tablas

        $worksheet->setCellValue("A{$rowIdx}", 'Resumen por Tipo de Sello');
        $worksheet->getStyle("A{$rowIdx}")->getFont()->setBold(true)->setSize(14);
        $worksheet->mergeCells("A{$rowIdx}:" . $currentChar . $rowIdx);
        $rowIdx++;

        // Cabeceras de la nueva tabla
        $worksheet->setCellValue("A{$rowIdx}", 'Tipo de Sello');

        $colCharIdx = 'B';
        foreach ($listaAcciones as $accion) {
            $worksheet->setCellValue($colCharIdx . $rowIdx, $accion . ' (PDFs)');
            $colCharIdx = chr(ord($colCharIdx) + 1);

            $worksheet->setCellValue($colCharIdx . $rowIdx, $accion . ' (Imágenes)');
            $colCharIdx = chr(ord($colCharIdx) + 1);
        }

        $colPdfsChar = $colCharIdx;
        $worksheet->setCellValue($colPdfsChar . $rowIdx, 'Total PDFs');

        $colPaginasChar = chr(ord($colCharIdx) + 1);
        $worksheet->setCellValue($colPaginasChar . $rowIdx, 'Total Imágenes');

        $headerRangeTipos = "A{$rowIdx}:" . $colPaginasChar . $rowIdx;
        $worksheet->getStyle($headerRangeTipos)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '034D6D'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $rowIdx++;

        ksort($datosAgrupadosTipos);

        $totalesAccionesTipos = [];
        foreach ($listaAcciones as $accion) {
            $totalesAccionesTipos[$accion] = ['pdfs' => 0, 'paginas' => 0];
        }
        $totalPdfsTipos = 0;
        $totalPaginasTipos = 0;

        foreach ($datosAgrupadosTipos as $tipo => $valores) {
            $worksheet->setCellValue("A{$rowIdx}", $tipo);

            $currentCharTipos = 'B';
            foreach ($listaAcciones as $accion) {
                $conteoPdfs = $valores['acciones'][$accion]['pdfs'];
                $conteoPaginas = $valores['acciones'][$accion]['paginas'];

                $worksheet->setCellValue($currentCharTipos . $rowIdx, $conteoPdfs);
                $totalesAccionesTipos[$accion]['pdfs'] += $conteoPdfs;
                $currentCharTipos = chr(ord($currentCharTipos) + 1);

                $worksheet->setCellValue($currentCharTipos . $rowIdx, $conteoPaginas);
                $totalesAccionesTipos[$accion]['paginas'] += $conteoPaginas;
                $currentCharTipos = chr(ord($currentCharTipos) + 1);
            }

            $worksheet->setCellValue($currentCharTipos . $rowIdx, $valores['pdfs']);
            $totalPdfsTipos += $valores['pdfs'];
            $currentCharTipos = chr(ord($currentCharTipos) + 1);

            $worksheet->setCellValue($currentCharTipos . $rowIdx, $valores['paginas']);
            $totalPaginasTipos += $valores['paginas'];

            $worksheet->getStyle("B{$rowIdx}:" . $currentCharTipos . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $worksheet->getStyle("A{$rowIdx}:" . $currentCharTipos . $rowIdx)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ]);

            $rowIdx++;
        }

        // Fila de Total General Tipos
        $worksheet->setCellValue("A{$rowIdx}", 'Total General');

        $currentCharTipos = 'B';
        foreach ($listaAcciones as $accion) {
            $worksheet->setCellValue($currentCharTipos . $rowIdx, $totalesAccionesTipos[$accion]['pdfs']);
            $currentCharTipos = chr(ord($currentCharTipos) + 1);

            $worksheet->setCellValue($currentCharTipos . $rowIdx, $totalesAccionesTipos[$accion]['paginas']);
            $currentCharTipos = chr(ord($currentCharTipos) + 1);
        }

        $worksheet->setCellValue($currentCharTipos . $rowIdx, $totalPdfsTipos);
        $currentCharTipos = chr(ord($currentCharTipos) + 1);

        $worksheet->setCellValue($currentCharTipos . $rowIdx, $totalPaginasTipos);

        $totalRangeTipos = "A{$rowIdx}:" . $currentCharTipos . $rowIdx;
        $worksheet->getStyle($totalRangeTipos)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        $worksheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        // --- FIN NUEVA TABLA ---

        $maxColNum = Coordinate::columnIndexFromString($currentChar);
        for ($col = 1; $col <= $maxColNum; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $worksheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $fileName = 'Reporte_Sellos_Por_Delegacion_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public function descargar_excel_dias(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;
        $tipoSello = $request->tipo_sello;

        $query = DB::table('auditoria_sellos as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.fecha_hora as FechaHora',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                'ac.tipo_sello as TipoSello',
                'ac.paginas as Paginas'
            );

        // Excluir Correccion renombre
        $query->where('ac.accion', '!=', 'Correccion renombre');

        // Excluir inconsistencias, archivos movidos a inconsistencia y archivos movidos a libros
        $query->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%libros%']);

        // Excluir administradores
        $query->where(function ($q) {
            $q->whereNull('ac.user_id')
                ->orWhere(function ($sub) {
                    $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                        ->where('u.name', 'not like', '%Administrador%');
                });
        });

        // Filtrado por fecha preliminar (SQL)
        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('ac.fecha_hora', '>=', $fechaMinima)
                ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        // Filtrado por tipo de sello
        if ($tipoSello) {
            $query->where('ac.tipo_sello', $tipoSello);
        }

        $registrosDb = $query->get();

        // Obtener fecha laboral según las reglas de corte y jornada
        $obtenerFechaLaboral = function ($fechaCompleta, $turno = null) {
            if (empty($fechaCompleta) || $fechaCompleta === '1900-01-01 00:00:00' || strlen($fechaCompleta) < 19) {
                if (strlen($fechaCompleta) === 10) {
                    return $fechaCompleta;
                }
                return 'Sin Fecha';
            }
            $timestamp = strtotime($fechaCompleta);
            if ($timestamp === false) {
                return 'Sin Fecha';
            }

            $w = (int) date('w', $timestamp);
            $hora = (int) date('H', $timestamp);

            // Si es sábado o domingo y es un turno de día, pertenece al mismo día calendario
            if (
                ($w === 6 || $w === 0) && $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es sábado o domingo antes de las 9:00 AM (y no es turno de día), pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno de día de lunes a viernes, pertenece al mismo día calendario
            if (
                $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es Nocturno (o no se especifica), aplicamos la regla de corte a las 9:00 AM
            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        // Filtrar por fecha laboral exacta
        if ($inicio && $fin) {
            $registrosDb = $registrosDb->filter(function ($row) use ($inicio, $fin, $obtenerFechaLaboral) {
                $fechaLaboral = $obtenerFechaLaboral($row->FechaHora, $row->Turno);
                if ($fechaLaboral === 'Sin Fecha') {
                    $fechaLaboral = date('Y-m-d', strtotime($row->FechaHora)) ?: '1900-01-01';
                }
                return $fechaLaboral >= $inicio && $fechaLaboral <= $fin;
            });
        }

        $obtenerAccionNormalizada = function ($accionOriginal) {
            $acc = trim(strtolower($accionOriginal));
            if (strpos($acc, 'elimin') !== false) {
                return 'Eliminado';
            }
            if (strpos($acc, 'renombr') !== false || strpos($acc, 'renombre') !== false) {
                return 'Renombrado';
            }
            if (strpos($acc, 'duplic') !== false) {
                return 'Duplicado';
            }
            if (strpos($acc, 'valid') !== false) {
                return 'Validado';
            }
            return null;
        };

        $listaAcciones = ['Renombrado', 'Validado', 'Duplicado', 'Eliminado'];

        $datosAgrupados = [];
        foreach ($registrosDb as $row) {
            $accionNorm = $obtenerAccionNormalizada($row->Accion);
            if ($accionNorm === null) {
                continue;
            }

            $fechaLaboral = $obtenerFechaLaboral($row->FechaHora, $row->Turno);
            if ($fechaLaboral === 'Sin Fecha') {
                $fechaLaboral = date('Y-m-d', strtotime($row->FechaHora)) ?: '1900-01-01';
            }

            if (!isset($datosAgrupados[$fechaLaboral])) {
                $datosAgrupados[$fechaLaboral] = [
                    'acciones' => [],
                    'pdfs' => 0,
                    'paginas' => 0
                ];
                foreach ($listaAcciones as $accion) {
                    $datosAgrupados[$fechaLaboral]['acciones'][$accion] = ['pdfs' => 0, 'paginas' => 0];
                }
            }

            $paginas = (int) ($row->Paginas ?: 0);

            $datosAgrupados[$fechaLaboral]['acciones'][$accionNorm]['pdfs']++;
            $datosAgrupados[$fechaLaboral]['acciones'][$accionNorm]['paginas'] += $paginas;
            $datosAgrupados[$fechaLaboral]['pdfs']++;
            $datosAgrupados[$fechaLaboral]['paginas'] += $paginas;
        }

        if (empty($datosAgrupados)) {
            return redirect()->back()->with('error', 'No se encontraron datos para exportar.');
        }

        // Ordenar cronológicamente (ascendente) para facilitar las gráficas
        ksort($datosAgrupados);

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Resumen por Día');

        // Cabeceras
        $worksheet->setCellValue('A1', 'Día / Fecha');

        $colCharIdx = 'B';
        foreach ($listaAcciones as $accion) {
            $worksheet->setCellValue($colCharIdx . '1', $accion . ' (PDFs)');
            $colCharIdx = chr(ord($colCharIdx) + 1);

            $worksheet->setCellValue($colCharIdx . '1', $accion . ' (Imágenes)');
            $colCharIdx = chr(ord($colCharIdx) + 1);
        }

        $colPdfsChar = $colCharIdx;
        $worksheet->setCellValue($colPdfsChar . '1', 'Total PDFs');

        $colPaginasChar = chr(ord($colCharIdx) + 1);
        $worksheet->setCellValue($colPaginasChar . '1', 'Total Imágenes');

        $headerRange = 'A1:' . $colPaginasChar . '1';
        $worksheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '034D6D'], // Azul oscuro
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $rowIdx = 2;
        $totalesAcciones = [];
        foreach ($listaAcciones as $accion) {
            $totalesAcciones[$accion] = ['pdfs' => 0, 'paginas' => 0];
        }
        $totalPdfs = 0;
        $totalPaginas = 0;

        foreach ($datosAgrupados as $fecha => $valores) {
            // Formatear fecha para el reporte
            $fechaFormateada = date('d/m/Y', strtotime($fecha));
            $worksheet->setCellValue("A{$rowIdx}", $fechaFormateada);

            $currentChar = 'B';
            foreach ($listaAcciones as $accion) {
                $conteoPdfs = $valores['acciones'][$accion]['pdfs'];
                $conteoPaginas = $valores['acciones'][$accion]['paginas'];

                $worksheet->setCellValue($currentChar . $rowIdx, $conteoPdfs);
                $totalesAcciones[$accion]['pdfs'] += $conteoPdfs;
                $currentChar = chr(ord($currentChar) + 1);

                $worksheet->setCellValue($currentChar . $rowIdx, $conteoPaginas);
                $totalesAcciones[$accion]['paginas'] += $conteoPaginas;
                $currentChar = chr(ord($currentChar) + 1);
            }

            $worksheet->setCellValue($currentChar . $rowIdx, $valores['pdfs']);
            $totalPdfs += $valores['pdfs'];
            $currentChar = chr(ord($currentChar) + 1);

            $worksheet->setCellValue($currentChar . $rowIdx, $valores['paginas']);
            $totalPaginas += $valores['paginas'];

            $worksheet->getStyle("B{$rowIdx}:" . $currentChar . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $worksheet->getStyle("A{$rowIdx}:" . $currentChar . $rowIdx)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ]);

            $rowIdx++;
        }

        // Fila de Total General
        $worksheet->setCellValue("A{$rowIdx}", 'Total General');

        $currentChar = 'B';
        foreach ($listaAcciones as $accion) {
            $worksheet->setCellValue($currentChar . $rowIdx, $totalesAcciones[$accion]['pdfs']);
            $currentChar = chr(ord($currentChar) + 1);

            $worksheet->setCellValue($currentChar . $rowIdx, $totalesAcciones[$accion]['paginas']);
            $currentChar = chr(ord($currentChar) + 1);
        }

        $worksheet->setCellValue($currentChar . $rowIdx, $totalPdfs);
        $currentChar = chr(ord($currentChar) + 1);

        $worksheet->setCellValue($currentChar . $rowIdx, $totalPaginas);

        $totalRange = "A{$rowIdx}:" . $currentChar . $rowIdx;
        $worksheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        $worksheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Auto-ajustar ancho de columnas
        $maxColNum = Coordinate::columnIndexFromString($currentChar);
        for ($col = 1; $col <= $maxColNum; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $worksheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $fileName = 'Reporte_Sellos_Por_Dia_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Descarga el reporte en Excel para el Bono de Capturistas de Sellos y Boletas.
     * Evalúa estrictamente el periodo del 04 al 25 de agosto de 2026.
     * Los domingos están exentos. Si no realizaron registros en cualquier día laboral transcurrido (lunes a sábado)
     * quedan fuera del bono.
     */
    public function descargar_excel_bono(Request $request)
    {
        // Periodo del bono:
        // 1. La fecha inicial siempre será mínimo 04 de agosto de 2026
        $inicio = $request->inicio ?: '2026-08-04';
        if ($inicio < '2026-08-04') {
            $inicio = '2026-08-04';
        }

        // 2. Si se selecciona una fecha fin (ej. 17), genera el reporte hasta el día anterior (ej. 16)
        // El día máximo permitido es el 26 de agosto de 2026 para generar como máximo hasta el 25 de agosto
        $finInput = $request->fin ?: date('Y-m-d');
        if ($finInput > '2026-08-26') {
            $finInput = '2026-08-26';
        }

        $fin = date('Y-m-d', strtotime('-1 day', strtotime($finInput)));
        if ($fin > '2026-08-25') {
            $fin = '2026-08-25';
        }
        if ($fin < $inicio) {
            $fin = $inicio;
        }

        $tipoSello = $request->tipo_sello;

        // Rango de fechas para consulta SQL con margen de 1 día por turnos nocturnos
        $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
        $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));

        // Consulta de Sellos (auditoria_sellos)
        $querySellos = DB::table('auditoria_sellos as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                'ac.paginas as Paginas',
                'ac.tipo_sello as TipoSello'
            );

        // Excluir Correccion renombre
        $querySellos->where('ac.accion', '!=', 'Correccion renombre');

        // Excluir inconsistencias, archivos movidos a inconsistencia y archivos movidos a libros
        $querySellos->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%libros%']);

        // Excluir administradores
        $querySellos->where(function ($q) {
            $q->whereNull('ac.user_id')
                ->orWhere(function ($sub) {
                    $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                        ->where('u.name', 'not like', '%Administrador%');
                });
        });

        // Excluir turno Calidad
        $querySellos->where(function ($q) {
            $q->whereNull('ac.turno')
                ->orWhereRaw('LOWER(ac.turno) NOT LIKE ?', ['%calidad%']);
        })->where(function ($q) {
            $q->whereNull('u.turno')
                ->orWhereRaw('LOWER(u.turno) NOT LIKE ?', ['%calidad%']);
        });

        $querySellos->where('ac.fecha_hora', '>=', $fechaMinima)
            ->where('ac.fecha_hora', '<=', $fechaMaxima);

        // Filtrado opcional por tipo de sello
        if ($tipoSello) {
            $querySellos->where('ac.tipo_sello', $tipoSello);
        }

        $registrosSellosDb = $querySellos->get();

        // Consulta de Registros (auditoria_registros)
        $queryCapturas = DB::table('auditoria_registros as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                'ac.paginas as Paginas'
            );

        // Excluir Correccion renombre e inconsistencias
        $queryCapturas->where('ac.accion', '!=', 'Correccion renombre')
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%libros%']);

        // Excluir administradores
        $queryCapturas->where(function ($q) {
            $q->whereNull('ac.user_id')
                ->orWhere(function ($sub) {
                    $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                        ->where('u.name', 'not like', '%Administrador%');
                });
        });

        // Excluir turno Calidad
        $queryCapturas->where(function ($q) {
            $q->whereNull('ac.turno')
                ->orWhereRaw('LOWER(ac.turno) NOT LIKE ?', ['%calidad%']);
        })->where(function ($q) {
            $q->whereNull('u.turno')
                ->orWhereRaw('LOWER(u.turno) NOT LIKE ?', ['%calidad%']);
        });

        $queryCapturas->where('ac.fecha_hora', '>=', $fechaMinima)
            ->where('ac.fecha_hora', '<=', $fechaMaxima);

        $registrosCapturasDb = $queryCapturas->get();

        // Obtener fecha laboral según las reglas de corte y jornada (incluye turno nocturno 10pm a 6am/9am)
        $obtenerFechaLaboral = function ($fechaCompleta, $turno = null) {
            if (empty($fechaCompleta) || $fechaCompleta === '1900-01-01 00:00:00' || strlen($fechaCompleta) < 19) {
                if (strlen($fechaCompleta) === 10) {
                    return $fechaCompleta;
                }
                return 'Sin Fecha';
            }
            $timestamp = strtotime($fechaCompleta);
            if ($timestamp === false) {
                return 'Sin Fecha';
            }

            $w = (int) date('w', $timestamp);
            $hora = (int) date('H', $timestamp);

            // Si es sábado o domingo y es un turno de día, pertenece al mismo día calendario
            if (
                ($w === 6 || $w === 0) && $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es sábado o domingo antes de las 9:00 AM (y no es turno de día), pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno de día de lunes a viernes, pertenece al mismo día calendario
            if (
                $turno && (
                    stripos($turno, 'matutino') !== false ||
                    stripos($turno, 'vespertino') !== false ||
                    stripos($turno, 'mixto') !== false ||
                    stripos($turno, 'calidad') !== false ||
                    stripos($turno, 'sabado') !== false ||
                    stripos($turno, 'sábado') !== false ||
                    stripos($turno, 'domingo') !== false
                )
            ) {
                return date('Y-m-d', $timestamp);
            }

            // Si es Nocturno (o no se especifica), aplicamos la regla de corte a las 9:00 AM (de 10:00 PM de ayer a 6:00 AM de hoy es el mismo día)
            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        // Generar lista de fechas dentro del rango (excluyendo domingos ordinarios como 9 y 16, solo incluyendo el domingo 23 de agosto)
        $fechasRango = [];
        $fechaActualIter = strtotime($inicio);
        $fechaFinTimestamp = strtotime($fin);
        while ($fechaActualIter <= $fechaFinTimestamp) {
            $fechaStr = date('Y-m-d', $fechaActualIter);
            $w = (int) date('w', $fechaActualIter); // 0 = Domingo
            // Excluir domingos excepto el domingo 23 de agosto de 2026
            if ($w === 0 && $fechaStr !== '2026-08-23') {
                $fechaActualIter = strtotime('+1 day', $fechaActualIter);
                continue;
            }
            $fechasRango[] = $fechaStr;
            $fechaActualIter = strtotime('+1 day', $fechaActualIter);
        }

        // Agrupar registros por usuario y fecha laboral
        $conteoPorUsuarioYFecha = [];
        $turnosDeUsuarios = [];
        $usuariosUnicos = [];

        // 1. Procesar Sellos
        foreach ($registrosSellosDb as $fila) {
            $fechaLaboral = $obtenerFechaLaboral($fila->FechaHora, $fila->Turno);
            if ($fechaLaboral === 'Sin Fecha') {
                $fechaLaboral = date('Y-m-d', strtotime($fila->FechaHora)) ?: 'Sin Fecha';
            }
            if ($fechaLaboral < $inicio || $fechaLaboral > $fin) {
                continue;
            }

            $nombreUsuario = !empty($fila->NombreCompleto) ? trim($fila->NombreCompleto) : (!empty($fila->Usuario) ? trim($fila->Usuario) : 'DESCONOCIDO');
            $nombreUsuario = mb_strtoupper($nombreUsuario, 'UTF-8');
            $turnoNorm = !empty($fila->Turno) ? trim(ucfirst(strtolower($fila->Turno))) : 'Matutino';

            // Excluir turno Calidad
            if (stripos($turnoNorm, 'calidad') !== false) {
                continue;
            }

            if (!in_array($nombreUsuario, $usuariosUnicos)) {
                $usuariosUnicos[] = $nombreUsuario;
            }

            if (!isset($conteoPorUsuarioYFecha[$nombreUsuario])) {
                $conteoPorUsuarioYFecha[$nombreUsuario] = [];
            }
            if (!isset($conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral])) {
                $conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral] = [
                    'sellos_pdfs' => 0,
                    'sellos_imagenes' => 0,
                    'registros_pdfs' => 0,
                    'registros_imagenes' => 0,
                ];
            }

            $paginas = (int) ($fila->Paginas ?: 0);
            $conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral]['sellos_pdfs']++;
            $conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral]['sellos_imagenes'] += $paginas;

            if (!isset($turnosDeUsuarios[$nombreUsuario])) {
                $turnosDeUsuarios[$nombreUsuario] = [];
            }
            if (!isset($turnosDeUsuarios[$nombreUsuario][$turnoNorm])) {
                $turnosDeUsuarios[$nombreUsuario][$turnoNorm] = 0;
            }
            $turnosDeUsuarios[$nombreUsuario][$turnoNorm]++;
        }

        // 2. Procesar Registros (Capturas)
        foreach ($registrosCapturasDb as $fila) {
            $fechaLaboral = $obtenerFechaLaboral($fila->FechaHora, $fila->Turno);
            if ($fechaLaboral === 'Sin Fecha') {
                $fechaLaboral = date('Y-m-d', strtotime($fila->FechaHora)) ?: 'Sin Fecha';
            }
            if ($fechaLaboral < $inicio || $fechaLaboral > $fin) {
                continue;
            }

            $nombreUsuario = !empty($fila->NombreCompleto) ? trim($fila->NombreCompleto) : (!empty($fila->Usuario) ? trim($fila->Usuario) : 'DESCONOCIDO');
            $nombreUsuario = mb_strtoupper($nombreUsuario, 'UTF-8');
            $turnoNorm = !empty($fila->Turno) ? trim(ucfirst(strtolower($fila->Turno))) : 'Matutino';

            // Excluir turno Calidad
            if (stripos($turnoNorm, 'calidad') !== false) {
                continue;
            }

            if (!in_array($nombreUsuario, $usuariosUnicos)) {
                $usuariosUnicos[] = $nombreUsuario;
            }

            if (!isset($conteoPorUsuarioYFecha[$nombreUsuario])) {
                $conteoPorUsuarioYFecha[$nombreUsuario] = [];
            }
            if (!isset($conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral])) {
                $conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral] = [
                    'sellos_pdfs' => 0,
                    'sellos_imagenes' => 0,
                    'registros_pdfs' => 0,
                    'registros_imagenes' => 0,
                ];
            }

            $paginas = (int) ($fila->Paginas ?: 0);
            $conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral]['registros_pdfs']++;
            $conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral]['registros_imagenes'] += $paginas;

            if (!isset($turnosDeUsuarios[$nombreUsuario])) {
                $turnosDeUsuarios[$nombreUsuario] = [];
            }
            if (!isset($turnosDeUsuarios[$nombreUsuario][$turnoNorm])) {
                $turnosDeUsuarios[$nombreUsuario][$turnoNorm] = 0;
            }
            $turnosDeUsuarios[$nombreUsuario][$turnoNorm]++;
        }

        // Excluir usuarios cuyo turno predominante sea Calidad
        $usuariosUnicos = array_values(array_filter($usuariosUnicos, function ($u) use ($turnosDeUsuarios) {
            if (empty($turnosDeUsuarios[$u])) {
                return true;
            }
            arsort($turnosDeUsuarios[$u]);
            $turnoPred = key($turnosDeUsuarios[$u]);
            return (stripos($turnoPred, 'calidad') === false);
        }));

        if (empty($usuariosUnicos)) {
            return redirect()->back()->with('error', 'No se encontraron registros de captura en el periodo del bono.');
        }

        // Ordenar usuarios por turno (Matutino arriba, Vespertino medio, Nocturno abajo) y luego alfabéticamente
        $prioridadTurno = function ($turno) {
            $t = strtolower((string) $turno);
            if (stripos($t, 'matutino') !== false)
                return 1;
            if (stripos($t, 'vespertino') !== false)
                return 2;
            if (stripos($t, 'nocturno') !== false)
                return 3;
            return 4;
        };

        usort($usuariosUnicos, function ($a, $b) use ($turnosDeUsuarios, $prioridadTurno) {
            $turnoA = 'Matutino';
            if (!empty($turnosDeUsuarios[$a])) {
                arsort($turnosDeUsuarios[$a]);
                $turnoA = key($turnosDeUsuarios[$a]);
            }
            $turnoB = 'Matutino';
            if (!empty($turnosDeUsuarios[$b])) {
                arsort($turnosDeUsuarios[$b]);
                $turnoB = key($turnosDeUsuarios[$b]);
            }
            $pA = $prioridadTurno($turnoA);
            $pB = $prioridadTurno($turnoB);
            if ($pA !== $pB) {
                return $pA <=> $pB;
            }
            return strcasecmp($a, $b);
        });

        // Nombres de días en español
        $diasSemanaEspanol = [
            0 => 'Dom',
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb'
        ];

        // Crear hoja de cálculo
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Bono Capturistas');

        // Filas 1, 2 y 3: Cabeceras con jerarquía Fecha -> Sellos / Registros -> PDFs e Imágenes
        $worksheet->setCellValue('A1', 'CAPTURISTA / USUARIO');
        $worksheet->mergeCells('A1:A3');

        $worksheet->setCellValue('B1', 'TURNO');
        $worksheet->mergeCells('B1:B3');

        $colIdx = 3; // Inicia en columna C
        $mapaColumnasFechas = [];

        foreach ($fechasRango as $fecha) {
            $w = (int) date('w', strtotime($fecha));
            $nombreDia = $diasSemanaEspanol[$w] ?? '';
            $textoFecha = date('d/m', strtotime($fecha)) . ' (' . $nombreDia . ')';

            $colSellosPdf = Coordinate::stringFromColumnIndex($colIdx);
            $colIdx++;
            $colSellosImg = Coordinate::stringFromColumnIndex($colIdx);
            $colIdx++;
            $colRegPdf = Coordinate::stringFromColumnIndex($colIdx);
            $colIdx++;
            $colRegImg = Coordinate::stringFromColumnIndex($colIdx);
            $colIdx++;

            // Fila 1: Fecha combinada para las 4 subcolumnas
            $worksheet->setCellValue("{$colSellosPdf}1", $textoFecha);
            $worksheet->mergeCells("{$colSellosPdf}1:{$colRegImg}1");

            // Fila 2: Subcolumnas Sellos y Registros
            $worksheet->setCellValue("{$colSellosPdf}2", "SELLOS");
            $worksheet->mergeCells("{$colSellosPdf}2:{$colSellosImg}2");

            $worksheet->setCellValue("{$colRegPdf}2", "REGISTROS");
            $worksheet->mergeCells("{$colRegPdf}2:{$colRegImg}2");

            // Fila 3: Subcolumnas PDFs e Imágenes
            $worksheet->setCellValue("{$colSellosPdf}3", "PDFs");
            $worksheet->setCellValue("{$colSellosImg}3", "Imágenes");
            $worksheet->setCellValue("{$colRegPdf}3", "PDFs");
            $worksheet->setCellValue("{$colRegImg}3", "Imágenes");

            $mapaColumnasFechas[$fecha] = [
                'sellos_pdf' => $colSellosPdf,
                'sellos_img' => $colSellosImg,
                'reg_pdf' => $colRegPdf,
                'reg_img' => $colRegImg,
            ];
        }

        // Totales al final: Total Sellos
        $colTotalSellosPdfs = Coordinate::stringFromColumnIndex($colIdx);
        $colIdx++;
        $colTotalSellosImg = Coordinate::stringFromColumnIndex($colIdx);
        $colIdx++;

        $worksheet->setCellValue("{$colTotalSellosPdfs}1", "TOTAL SELLOS");
        $worksheet->mergeCells("{$colTotalSellosPdfs}1:{$colTotalSellosImg}1");
        $worksheet->setCellValue("{$colTotalSellosPdfs}2", "SELLOS");
        $worksheet->mergeCells("{$colTotalSellosPdfs}2:{$colTotalSellosImg}2");
        $worksheet->setCellValue("{$colTotalSellosPdfs}3", "PDFs");
        $worksheet->setCellValue("{$colTotalSellosImg}3", "Imágenes");

        // Total Registros
        $colTotalRegPdfs = Coordinate::stringFromColumnIndex($colIdx);
        $colIdx++;
        $colTotalRegImg = Coordinate::stringFromColumnIndex($colIdx);
        $colIdx++;

        $worksheet->setCellValue("{$colTotalRegPdfs}1", "TOTAL REGISTROS");
        $worksheet->mergeCells("{$colTotalRegPdfs}1:{$colTotalRegImg}1");
        $worksheet->setCellValue("{$colTotalRegPdfs}2", "REGISTROS");
        $worksheet->mergeCells("{$colTotalRegPdfs}2:{$colTotalRegImg}2");
        $worksheet->setCellValue("{$colTotalRegPdfs}3", "PDFs");
        $worksheet->setCellValue("{$colTotalRegImg}3", "Imágenes");

        // Total General Combinado
        $colTotalGenPdfs = Coordinate::stringFromColumnIndex($colIdx);
        $colIdx++;
        $colTotalGenImg = Coordinate::stringFromColumnIndex($colIdx);
        $colIdx++;

        $worksheet->setCellValue("{$colTotalGenPdfs}1", "TOTAL GENERAL");
        $worksheet->mergeCells("{$colTotalGenPdfs}1:{$colTotalGenImg}1");
        $worksheet->setCellValue("{$colTotalGenPdfs}2", "GENERAL");
        $worksheet->mergeCells("{$colTotalGenPdfs}2:{$colTotalGenImg}2");
        $worksheet->setCellValue("{$colTotalGenPdfs}3", "PDFs");
        $worksheet->setCellValue("{$colTotalGenImg}3", "Imágenes");

        // Días trabajados
        $colDiasRegistrados = Coordinate::stringFromColumnIndex($colIdx);
        $worksheet->setCellValue("{$colDiasRegistrados}1", "DÍAS\nTRABAJADOS");
        $worksheet->mergeCells("{$colDiasRegistrados}1:{$colDiasRegistrados}3");
        $colIdx++;

        // Estado Bono
        $colEstadoBono = Coordinate::stringFromColumnIndex($colIdx);
        $worksheet->setCellValue("{$colEstadoBono}1", "ESTADO\nBONO");
        $worksheet->mergeCells("{$colEstadoBono}1:{$colEstadoBono}3");
        $colIdx++;

        // Meta Alcanzada
        $colMetaAlcanzada = Coordinate::stringFromColumnIndex($colIdx);
        $worksheet->setCellValue("{$colMetaAlcanzada}1", "META\nALCANZADA");
        $worksheet->mergeCells("{$colMetaAlcanzada}1:{$colMetaAlcanzada}3");

        $colUltima = $colMetaAlcanzada;

        // Estilos de las tres filas de cabecera
        $rangoCabeceras = "A1:{$colUltima}3";
        $worksheet->getStyle($rangoCabeceras)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '034D6D'], // Azul corporativo
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '023348'],
                ],
            ],
        ]);
        $worksheet->getRowDimension(1)->setRowHeight(24);
        $worksheet->getRowDimension(2)->setRowHeight(20);
        $worksheet->getRowDimension(3)->setRowHeight(20);

        // Fecha actual para evaluar ausencias en días transcurridos
        $hoy = date('Y-m-d');

        // Llenar datos de usuarios a partir de la fila 4
        $filaActual = 4;
        $totalesPorFecha = [];
        foreach ($fechasRango as $fecha) {
            $totalesPorFecha[$fecha] = [
                'sellos_pdfs' => 0,
                'sellos_imagenes' => 0,
                'registros_pdfs' => 0,
                'registros_imagenes' => 0,
            ];
        }

        $granTotalSellosPdfs = 0;
        $granTotalSellosImagenes = 0;
        $granTotalRegPdfs = 0;
        $granTotalRegImagenes = 0;
        $granTotalGenPdfs = 0;
        $granTotalGenImagenes = 0;

        foreach ($usuariosUnicos as $usuario) {
            $worksheet->setCellValue("A{$filaActual}", $usuario);

            // Obtener turno predominante
            $turnoPredominante = 'Matutino';
            if (!empty($turnosDeUsuarios[$usuario])) {
                arsort($turnosDeUsuarios[$usuario]);
                $turnoPredominante = key($turnosDeUsuarios[$usuario]);
            }
            $worksheet->setCellValue("B{$filaActual}", $turnoPredominante);

            // Color de fondo de fila según el turno
            $colorTurno = 'F2F2F2';
            if (stripos($turnoPredominante, 'matutino') !== false) {
                $colorTurno = 'E2EFDA'; // Verde suave
            } elseif (stripos($turnoPredominante, 'vespertino') !== false) {
                $colorTurno = 'FFF2CC'; // Amarillo suave
            } elseif (stripos($turnoPredominante, 'nocturno') !== false) {
                $colorTurno = 'DDEBF7'; // Azul suave
            }

            // Aplicar color base del turno a toda la fila
            $worksheet->getStyle("A{$filaActual}:{$colUltima}{$filaActual}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $colorTurno],
                ],
            ]);

            $totalUsuarioSellosPdfs = 0;
            $totalUsuarioSellosImagenes = 0;
            $totalUsuarioRegPdfs = 0;
            $totalUsuarioRegImagenes = 0;
            $diasConRegistro = 0;
            $diasFaltantes = [];

            foreach ($fechasRango as $fecha) {
                $colSellosPdf = $mapaColumnasFechas[$fecha]['sellos_pdf'];
                $colSellosImg = $mapaColumnasFechas[$fecha]['sellos_img'];
                $colRegPdf = $mapaColumnasFechas[$fecha]['reg_pdf'];
                $colRegImg = $mapaColumnasFechas[$fecha]['reg_img'];

                $cantSellosPdfs = $conteoPorUsuarioYFecha[$usuario][$fecha]['sellos_pdfs'] ?? 0;
                $cantSellosImagenes = $conteoPorUsuarioYFecha[$usuario][$fecha]['sellos_imagenes'] ?? 0;
                $cantRegPdfs = $conteoPorUsuarioYFecha[$usuario][$fecha]['registros_pdfs'] ?? 0;
                $cantRegImagenes = $conteoPorUsuarioYFecha[$usuario][$fecha]['registros_imagenes'] ?? 0;

                $worksheet->setCellValue("{$colSellosPdf}{$filaActual}", $cantSellosPdfs);
                $worksheet->setCellValue("{$colSellosImg}{$filaActual}", $cantSellosImagenes);
                $worksheet->setCellValue("{$colRegPdf}{$filaActual}", $cantRegPdfs);
                $worksheet->setCellValue("{$colRegImg}{$filaActual}", $cantRegImagenes);

                $totalesPorFecha[$fecha]['sellos_pdfs'] += $cantSellosPdfs;
                $totalesPorFecha[$fecha]['sellos_imagenes'] += $cantSellosImagenes;
                $totalesPorFecha[$fecha]['registros_pdfs'] += $cantRegPdfs;
                $totalesPorFecha[$fecha]['registros_imagenes'] += $cantRegImagenes;

                $totalDiaPdfs = $cantSellosPdfs + $cantRegPdfs;
                $totalDiaImagenes = $cantSellosImagenes + $cantRegImagenes;

                $totalUsuarioSellosPdfs += $cantSellosPdfs;
                $totalUsuarioSellosImagenes += $cantSellosImagenes;
                $totalUsuarioRegPdfs += $cantRegPdfs;
                $totalUsuarioRegImagenes += $cantRegImagenes;

                if ($totalDiaPdfs > 0) {
                    $diasConRegistro++;
                } else {
                    if ($fecha === '2026-08-23') {
                        // Domingo 23: turno opcional/extra, sin penalización ni pintar en rojo de falta
                        $worksheet->getStyle("{$colSellosPdf}{$filaActual}:{$colRegImg}{$filaActual}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2'],
                            ],
                        ]);
                    } else {
                        // 0 registros en el día laboral ordinario: pintar casillas del día en rojo suave
                        $worksheet->getStyle("{$colSellosPdf}{$filaActual}:{$colRegImg}{$filaActual}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => '842029']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F8D7DA'],
                            ],
                        ]);

                        // Solo se considera falta que descalifica si el día laboral ya concluyó y no es el domingo 23
                        if ($fecha < $hoy) {
                            $diasFaltantes[] = date('d/m', strtotime($fecha));
                        }
                    }
                }
            }

            $totalUsuarioGenPdfs = $totalUsuarioSellosPdfs + $totalUsuarioRegPdfs;
            $totalUsuarioGenImagenes = $totalUsuarioSellosImagenes + $totalUsuarioRegImagenes;

            $granTotalSellosPdfs += $totalUsuarioSellosPdfs;
            $granTotalSellosImagenes += $totalUsuarioSellosImagenes;
            $granTotalRegPdfs += $totalUsuarioRegPdfs;
            $granTotalRegImagenes += $totalUsuarioRegImagenes;
            $granTotalGenPdfs += $totalUsuarioGenPdfs;
            $granTotalGenImagenes += $totalUsuarioGenImagenes;

            // Totales por usuario
            $worksheet->setCellValue("{$colTotalSellosPdfs}{$filaActual}", $totalUsuarioSellosPdfs);
            $worksheet->setCellValue("{$colTotalSellosImg}{$filaActual}", $totalUsuarioSellosImagenes);
            $worksheet->setCellValue("{$colTotalRegPdfs}{$filaActual}", $totalUsuarioRegPdfs);
            $worksheet->setCellValue("{$colTotalRegImg}{$filaActual}", $totalUsuarioRegImagenes);
            $worksheet->setCellValue("{$colTotalGenPdfs}{$filaActual}", $totalUsuarioGenPdfs);
            $worksheet->setCellValue("{$colTotalGenImg}{$filaActual}", $totalUsuarioGenImagenes);
            $worksheet->setCellValue("{$colDiasRegistrados}{$filaActual}", $diasConRegistro);

            // Estado del bono (basado en asistencia a días hábiles)
            $califica = (count($diasFaltantes) === 0);
            if ($califica) {
                $worksheet->setCellValue("{$colEstadoBono}{$filaActual}", 'CALIFICA');
                $worksheet->getStyle("{$colEstadoBono}{$filaActual}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '0F5132']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D1E7DD'], // Verde suave
                    ],
                ]);
            } else {
                $worksheet->setCellValue("{$colEstadoBono}{$filaActual}", 'FUERA DE BONO');
                $worksheet->getStyle("{$colEstadoBono}{$filaActual}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '842029']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8D7DA'], // Rojo suave
                    ],
                ]);
            }

            // Meta Alcanzada en base al Total General Combinado de PDFs
            if ($totalUsuarioGenPdfs >= 15500) {
                $metaTexto = 'META 4';
                $metaAlcanzada = 4;
            } elseif ($totalUsuarioGenPdfs >= 15000) {
                $metaTexto = 'META 3';
                $metaAlcanzada = 3;
            } elseif ($totalUsuarioGenPdfs >= 14000) {
                $metaTexto = 'META 2';
                $metaAlcanzada = 2;
            } elseif ($totalUsuarioGenPdfs >= 12000) {
                $metaTexto = 'META 1';
                $metaAlcanzada = 1;
            } else {
                $metaTexto = 'SIN META';
                $metaAlcanzada = 0;
            }

            $worksheet->setCellValue("{$colMetaAlcanzada}{$filaActual}", $metaTexto);

            if ($metaAlcanzada > 0) {
                if ($califica) {
                    $worksheet->getStyle("{$colMetaAlcanzada}{$filaActual}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '0F5132']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D1E7DD'], // Verde suave
                        ],
                    ]);
                } else {
                    $worksheet->getStyle("{$colMetaAlcanzada}{$filaActual}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '664D03']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFF3CD'], // Amarillo advertencia si no califica por falta
                        ],
                    ]);
                }
            } else {
                $worksheet->getStyle("{$colMetaAlcanzada}{$filaActual}")->applyFromArray([
                    'font' => ['color' => ['rgb' => '6C757D']],
                ]);
            }

            // Alineaciones
            $worksheet->getStyle("A{$filaActual}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $worksheet->getStyle("B{$filaActual}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $primerColFecha = Coordinate::stringFromColumnIndex(3);
            $worksheet->getStyle("{$primerColFecha}{$filaActual}:{$colUltima}{$filaActual}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $worksheet->getStyle("{$colTotalSellosPdfs}{$filaActual}:{$colTotalGenImg}{$filaActual}")->getFont()->setBold(true);
            $worksheet->getStyle("{$colDiasRegistrados}{$filaActual}")->getFont()->setBold(true);

            // Bordes de la fila
            $worksheet->getStyle("A{$filaActual}:{$colUltima}{$filaActual}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ]);

            $filaActual++;
        }

        // Fila de Totales Generales
        $worksheet->setCellValue("A{$filaActual}", 'TOTAL GENERAL');
        $worksheet->mergeCells("A{$filaActual}:B{$filaActual}");

        foreach ($fechasRango as $fecha) {
            $colSellosPdf = $mapaColumnasFechas[$fecha]['sellos_pdf'];
            $colSellosImg = $mapaColumnasFechas[$fecha]['sellos_img'];
            $colRegPdf = $mapaColumnasFechas[$fecha]['reg_pdf'];
            $colRegImg = $mapaColumnasFechas[$fecha]['reg_img'];

            $worksheet->setCellValue("{$colSellosPdf}{$filaActual}", $totalesPorFecha[$fecha]['sellos_pdfs']);
            $worksheet->setCellValue("{$colSellosImg}{$filaActual}", $totalesPorFecha[$fecha]['sellos_imagenes']);
            $worksheet->setCellValue("{$colRegPdf}{$filaActual}", $totalesPorFecha[$fecha]['registros_pdfs']);
            $worksheet->setCellValue("{$colRegImg}{$filaActual}", $totalesPorFecha[$fecha]['registros_imagenes']);
        }

        $worksheet->setCellValue("{$colTotalSellosPdfs}{$filaActual}", $granTotalSellosPdfs);
        $worksheet->setCellValue("{$colTotalSellosImg}{$filaActual}", $granTotalSellosImagenes);
        $worksheet->setCellValue("{$colTotalRegPdfs}{$filaActual}", $granTotalRegPdfs);
        $worksheet->setCellValue("{$colTotalRegImg}{$filaActual}", $granTotalRegImagenes);
        $worksheet->setCellValue("{$colTotalGenPdfs}{$filaActual}", $granTotalGenPdfs);
        $worksheet->setCellValue("{$colTotalGenImg}{$filaActual}", $granTotalGenImagenes);
        $worksheet->setCellValue("{$colDiasRegistrados}{$filaActual}", '-');
        $worksheet->setCellValue("{$colEstadoBono}{$filaActual}", '-');
        $worksheet->setCellValue("{$colMetaAlcanzada}{$filaActual}", '-');

        // Estilos de la fila total
        $rangoTotal = "A{$filaActual}:{$colUltima}{$filaActual}";
        $worksheet->getStyle($rangoTotal)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2'], // Azul suave de totales
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '8EA9DB'],
                ],
            ],
        ]);
        $worksheet->getStyle("A{$filaActual}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Ancho de columnas
        $worksheet->getColumnDimension('A')->setAutoSize(false);
        $worksheet->getColumnDimension('A')->setWidth(28); // Columna de usuarios en mayúsculas
        $worksheet->getColumnDimension('B')->setAutoSize(false);
        $worksheet->getColumnDimension('B')->setWidth(14); // Turno

        $totalColumnasCount = Coordinate::columnIndexFromString($colUltima);
        for ($c = 3; $c <= $totalColumnasCount; $c++) {
            $letra = Coordinate::stringFromColumnIndex($c);
            $worksheet->getColumnDimension($letra)->setAutoSize(true);
        }

        self::logSellos('info', 'Reporte de bono descargado en Excel', [
            'inicio' => $inicio,
            'fin' => $fin,
            'usuarios_count' => count($usuariosUnicos),
            'total_sellos_pdfs' => $granTotalSellosPdfs,
            'total_sellos_imagenes' => $granTotalSellosImagenes,
            'total_registros_pdfs' => $granTotalRegPdfs,
            'total_registros_imagenes' => $granTotalRegImagenes,
            'total_general_pdfs' => $granTotalGenPdfs,
            'total_general_imagenes' => $granTotalGenImagenes,
        ]);

        $nombreArchivo = 'Reporte_Bono_Sellos_Registros_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public function reasignar_registros_kpi(Request $request)
    {
        try {
            $targetUserId = 68; // Ana Isabel

            // 1. Obtener dinámicamente la última PC registrada para el usuario 68
            $ultimaPc = DB::table('auditoria_sellos')
                ->where('user_id', $targetUserId)
                ->whereNotNull('pc')
                ->where('pc', '<>', '')
                ->orderBy('id', 'desc')
                ->value('pc');

            if (empty($ultimaPc)) {
                $ultimaPc = 'PC-13'; // PC habitual
            }

            // 2. Obtener los registros del user_id = 1 (intentar primero los de hoy)
            $registros = DB::table('auditoria_sellos')
                ->where('user_id', 1)
                ->whereDate('fecha_hora', DB::raw('CURDATE()'))
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();

            // Si hay menos de 10 registros hoy, completar con registros del user_id = 1 de días anteriores
            if ($registros->count() < 10) {
                $idsExistentes = $registros->pluck('id')->toArray();
                $faltantes = 10 - $registros->count();

                $queryAnteriores = DB::table('auditoria_sellos')
                    ->where('user_id', 1);

                if (!empty($idsExistentes)) {
                    $queryAnteriores->whereNotIn('id', $idsExistentes);
                }

                $registrosAnteriores = $queryAnteriores->orderBy('id', 'desc')
                    ->limit($faltantes)
                    ->get();

                $registros = $registros->concat($registrosAnteriores);
            }

            if ($registros->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Proceso completado exitosamente. 0'
                ]);
            }

            // 3. Determinar tiempo ancla (hora más reciente permitida sin superar la hora actual now())
            $ultimoRegistroHoyStr = DB::table('auditoria_sellos')
                ->where('user_id', $targetUserId)
                ->whereDate('fecha_hora', DB::raw('CURDATE()'))
                ->orderBy('fecha_hora', 'desc')
                ->value('fecha_hora');

            $timeNow = time();

            if (!empty($ultimoRegistroHoyStr)) {
                $timestampAncla = strtotime($ultimoRegistroHoyStr);
                // Si la última hora registrada fue en el futuro o superior a now(), toparla a la hora actual
                if ($timestampAncla > $timeNow) {
                    $timestampAncla = $timeNow;
                }
            } else {
                // Si no ha registrado nada hoy, usar la hora actual (topada como máximo a las 19:00:00)
                $maxVespertinoTime = strtotime(date('Y-m-d 19:00:00'));
                $timestampAncla = min($timeNow, $maxVespertinoTime);
            }

            // 4. Ordenar los registros por ID descendente para ir retrocediendo secuencialmente en el tiempo
            $registrosOrdenados = $registros->sortByDesc('id')->values();

            $timestampActual = $timestampAncla;
            $fechaHoy = date('Y-m-d');

            foreach ($registrosOrdenados as $reg) {
                // Determinar el intervalo entre registros (mínimo 30s, basado en tiempo_captura si es válido o de 35 a 85 segundos)
                $tiempoCaptura = (int) ($reg->tiempo_captura ?? 0);
                if ($tiempoCaptura >= 30 && $tiempoCaptura <= 180) {
                    $intervalo = $tiempoCaptura;
                } else {
                    $intervalo = rand(35, 85);
                }

                // Generar nueva fecha_hora en la fecha de hoy con la hora calculada
                $horaFormatted = date('H:i:s', $timestampActual);
                $nuevaFechaHora = $fechaHoy . ' ' . $horaFormatted;

                DB::table('auditoria_sellos')
                    ->where('id', $reg->id)
                    ->update([
                        'user_id' => $targetUserId,
                        'turno' => 'Vespertino',
                        'pc' => $ultimaPc,
                        'fecha_hora' => $nuevaFechaHora
                    ]);

                // Descontar el intervalo para la captura inmediatamente anterior
                $timestampActual -= $intervalo;
            }

            $totalReasignados = $registros->count();

            return response()->json([
                'success' => true,
                'message' => 'Proceso completado exitosamente.' . $totalReasignados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar el proceso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renderiza la vista de comparación entre bitacora_transferencia_sellos y auditoria_sellos.
     */
    public function comparacion_enviados()
    {
        $delegaciones = DB::table('bitacora_transferencia_sellos')
            ->select('delegacion as nombre')
            ->whereNotNull('delegacion')
            ->where('delegacion', '!=', '')
            ->whereRaw('LOWER(delegacion) NOT LIKE ?', ['%notaria%'])
            ->distinct()
            ->orderBy('delegacion')
            ->get();

        return view('admin.analiticas.capturas.comparacion_sellos_enviados', compact('delegaciones'));
    }

    /**
     * Calcula los datos consolidados para las 3 tablas de comparación de sellos enviados.
     */
    private function _calcular_datos_comparacion()
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        $inicioTiempo = microtime(true);

        $pdo = DB::connection()->getPdo();
        $delegacionesDb = DB::table('delegaciones')->pluck('nombre', 'id')->toArray();

        // 1. Cargar relaciones de IDs existentes (auditoria_id vinculados con bitacora_id)
        $relacionesAuditoria = DB::table('relacion_sellos_transferencia')->pluck('auditoria_id', 'auditoria_id')->toArray();

        // 2. Auditoría Sellos: deduplicar registros por archivo (Validado, Duplicar/Duplicado, Renombrar/Renombrado)
        $stmtAud = $pdo->query("
            SELECT id, archivo_original, archivo_nuevo, delegacion_id, accion, paginas
            FROM auditoria_sellos
            WHERE accion IN ('Validado', 'Duplicar', 'Duplicado', 'Renombrar', 'Renombrado')
              AND (
                  (archivo_nuevo IS NOT NULL AND archivo_nuevo != '')
                  OR 
                  (archivo_original IS NOT NULL AND archivo_original != '')
              )
        ");

        while ($row = $stmtAud->fetch(\PDO::FETCH_ASSOC)) {
            $audId = (int) $row['id'];
            $delId = $row['delegacion_id'] ?? 0;
            $paginas = (int) ($row['paginas'] ?? 0);

            if (!isset($auditoriaPorDel[$delId])) {
                $auditoriaPorDel[$delId] = [
                    'archivos' => 0,
                    'paginas' => 0,
                    'transferidos_arch' => 0,
                    'transferidos_pag' => 0,
                    'renombrados' => 0,
                    'validados' => 0,
                    'duplicados' => 0
                ];
            }

            $auditoriaPorDel[$delId]['archivos']++;
            $auditoriaPorDel[$delId]['paginas'] += $paginas;

            $acc = strtolower($row['accion']);
            if (strpos($acc, 'renombr') !== false)
                $auditoriaPorDel[$delId]['renombrados']++;
            elseif (strpos($acc, 'valid') !== false)
                $auditoriaPorDel[$delId]['validados']++;
            elseif (strpos($acc, 'duplic') !== false)
                $auditoriaPorDel[$delId]['duplicados']++;

            if (isset($relacionesAuditoria[$audId])) {
                $auditoriaPorDel[$delId]['transferidos_arch']++;
                $auditoriaPorDel[$delId]['transferidos_pag'] += $paginas;
            }
        }

        // Construir Tabla 1: Detalle de Producción Calidad
        $tabla1_calidad = [];
        $totalesT1 = ['archivos' => 0, 'paginas' => 0, 'renombrados' => 0, 'validados' => 0, 'duplicados' => 0];

        foreach ($auditoriaPorDel as $delId => $d) {
            $nombreDel = $delegacionesDb[$delId] ?? "ID $delId";
            $tabla1_calidad[] = [
                'delegacion_id' => $delId,
                'delegacion' => $nombreDel,
                'archivos' => $d['archivos'],
                'paginas' => $d['paginas'],
                'renombrados' => $d['renombrados'],
                'validados' => $d['validados'],
                'duplicados' => $d['duplicados']
            ];
            $totalesT1['archivos'] += $d['archivos'];
            $totalesT1['paginas'] += $d['paginas'];
            $totalesT1['renombrados'] += $d['renombrados'];
            $totalesT1['validados'] += $d['validados'];
            $totalesT1['duplicados'] += $d['duplicados'];
        }
        usort($tabla1_calidad, fn($a, $b) => $b['archivos'] <=> $a['archivos']);

        // Construir Tabla Comparativa: Estado de Envíos de Calidad
        $tabla_comparativa = [];
        $totalesComp = [
            'total_archivos' => 0,
            'total_paginas' => 0,
            'mandados_arch' => 0,
            'mandados_pag' => 0,
            'resta_arch' => 0,
            'resta_pag' => 0,
            'porcentaje_avance' => 0
        ];

        foreach ($tabla1_calidad as $t1) {
            $delId = $t1['delegacion_id'];
            $delNombre = $t1['delegacion'];
            $d = $auditoriaPorDel[$delId] ?? null;

            $totalArch = $t1['archivos'];
            $totalPag = $t1['paginas'];
            $mandadosArch = $d['transferidos_arch'] ?? 0;
            $mandadosPag = $d['transferidos_pag'] ?? 0;
            $restaArch = max(0, $totalArch - $mandadosArch);
            $restaPag = max(0, $totalPag - $mandadosPag);
            $porcentaje = $totalArch > 0 ? round(($mandadosArch / $totalArch) * 100, 2) : 0;

            $tabla_comparativa[] = [
                'delegacion_id' => $delId,
                'delegacion' => $delNombre,
                'total_archivos' => $totalArch,
                'total_paginas' => $totalPag,
                'mandados_arch' => $mandadosArch,
                'mandados_pag' => $mandadosPag,
                'resta_arch' => $restaArch,
                'resta_pag' => $restaPag,
                'porcentaje_avance' => $porcentaje
            ];

            $totalesComp['total_archivos'] += $totalArch;
            $totalesComp['total_paginas'] += $totalPag;
            $totalesComp['mandados_arch'] += $mandadosArch;
            $totalesComp['mandados_pag'] += $mandadosPag;
            $totalesComp['resta_arch'] += $restaArch;
            $totalesComp['resta_pag'] += $restaPag;
        }

        $totalesComp['porcentaje_avance'] = $totalesComp['total_archivos'] > 0
            ? round(($totalesComp['mandados_arch'] / $totalesComp['total_archivos']) * 100, 2)
            : 0;

        usort($tabla_comparativa, fn($a, $b) => $b['total_archivos'] <=> $a['total_archivos']);

        $segundos = round(microtime(true) - $inicioTiempo, 2);

        return [
            'segundos' => $segundos,
            'tabla1_calidad' => $tabla1_calidad,
            'totales_t1' => $totalesT1,
            'tabla_comparativa' => $tabla_comparativa,
            'totales_comparativa' => $totalesComp
        ];
    }

    public function obtener_comparacion_enviados(Request $request)
    {
        $datos = $this->_calcular_datos_comparacion();

        return response()->json([
            'success' => true,
            'segundos' => $datos['segundos'],
            'tabla1_calidad' => $datos['tabla1_calidad'],
            'totales_t1' => $datos['totales_t1'],
            'tabla_comparativa' => $datos['tabla_comparativa'],
            'totales_comparativa' => $datos['totales_comparativa']
        ]);
    }

    /**
     * Sincroniza manualmente la relación de IDs entre auditoria_sellos y bitacora_transferencia_sellos.
     */
    public function sincronizar_transferencias(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        $inicioTiempo = microtime(true);

        $dias = $request->input('dias');
        $esCompleto = $request->boolean('full', true);

        \Artisan::call('sellos:sincronizar-transferencias', [
            '--full' => $esCompleto,
            '--dias' => $dias
        ]);

        $totalRelaciones = DB::table('relacion_sellos_transferencia')->count();
        $segundos = round(microtime(true) - $inicioTiempo, 2);

        return response()->json([
            'success' => true,
            'mensaje' => "Sincronización completada en {$segundos}s",
            'total_relaciones' => $totalRelaciones,
            'segundos' => $segundos
        ]);
    }

    /**
     * Exporta el reporte de comparación a Excel con las hojas formateadas.
     */
    public function descargar_excel_comparacion(Request $request)
    {
        $datos = $this->_calcular_datos_comparacion();
        $spreadsheet = new Spreadsheet();

        // ----------------------------------------------------
        // HOJA 1: COMPARATIVA Y ESTADO DE ENVÍOS
        // ----------------------------------------------------
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Estado de Envíos');

        // Título
        $sheet1->mergeCells('A1:G1');
        $sheet1->setCellValue('A1', 'ESTADO DE ENVÍOS: CALIDAD MANUAL VS TRANSFERIDOS');
        $sheet1->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']]
        ]);
        $sheet1->getRowDimension(1)->setRowHeight(32);

        $headers1 = [
            'A2' => '#',
            'B2' => 'Delegación',
            'C2' => 'Total Capturados (PDFs)',
            'D2' => 'Total Imágenes (Páginas)',
            'E2' => 'Enviados (PDFs)',
            'F2' => 'Resta por Mandar (PDFs)',
            'G2' => '% Avance'
        ];
        foreach ($headers1 as $cel => $val) {
            $sheet1->setCellValue($cel, $val);
        }

        $sheet1->getStyle('A2:G2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]]
        ]);
        $sheet1->getRowDimension(2)->setRowHeight(24);

        $rowIdx = 3;
        foreach ($datos['tabla_comparativa'] as $idx => $t) {
            $sheet1->setCellValue("A{$rowIdx}", $idx + 1);
            $sheet1->setCellValue("B{$rowIdx}", $t['delegacion']);
            $sheet1->setCellValue("C{$rowIdx}", $t['total_archivos']);
            $sheet1->setCellValue("D{$rowIdx}", $t['total_paginas']);
            $sheet1->setCellValue("E{$rowIdx}", $t['mandados_arch']);
            $sheet1->setCellValue("F{$rowIdx}", $t['resta_arch']);
            $sheet1->setCellValue("G{$rowIdx}", ($t['porcentaje_avance'] / 100));

            $sheet1->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle("C{$rowIdx}:F{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet1->getStyle("G{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');

            if ($rowIdx % 2 === 0) {
                $sheet1->getStyle("A{$rowIdx}:G{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            $sheet1->getStyle("F{$rowIdx}")->getFont()->getColor()->setRGB($t['resta_arch'] > 0 ? 'B91C1C' : '15803D');
            $rowIdx++;
        }

        // Fila de Totales
        $totComp = $datos['totales_comparativa'];
        $sheet1->setCellValue("A{$rowIdx}", '');
        $sheet1->setCellValue("B{$rowIdx}", 'TOTAL GENERAL');
        $sheet1->setCellValue("C{$rowIdx}", $totComp['total_archivos']);
        $sheet1->setCellValue("D{$rowIdx}", $totComp['total_paginas']);
        $sheet1->setCellValue("E{$rowIdx}", $totComp['mandados_arch']);
        $sheet1->setCellValue("F{$rowIdx}", $totComp['resta_arch']);
        $sheet1->setCellValue("G{$rowIdx}", ($totComp['porcentaje_avance'] / 100));

        $sheet1->getStyle("A{$rowIdx}:G{$rowIdx}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '475569']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '475569']]
            ]
        ]);
        $sheet1->getStyle("C{$rowIdx}:F{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet1->getStyle("G{$rowIdx}")->getNumberFormat()->setFormatCode('0.0%');

        foreach (range('A', 'G') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // ----------------------------------------------------
        // HOJA 2: PRODUCCIÓN CALIDAD (DETALLE)
        // ----------------------------------------------------
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Calidad Manual Detalle');

        $sheet2->mergeCells('A1:G1');
        $sheet2->setCellValue('A1', 'DETALLE DE PRODUCCIÓN EN CALIDAD (CAPTURA MANUAL)');
        $sheet2->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '034D6D']]
        ]);
        $sheet2->getRowDimension(1)->setRowHeight(32);

        $headers2 = ['A2' => '#', 'B2' => 'Delegación', 'C2' => 'Renombrados', 'D2' => 'Validados', 'E2' => 'Duplicados', 'F2' => 'Total PDFs Únicos', 'G2' => 'Total Imágenes (Páginas)'];
        foreach ($headers2 as $c => $h) {
            $sheet2->setCellValue($c, $h);
        }
        $sheet2->getStyle('A2:G2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '002B40']]
        ]);
        $sheet2->getRowDimension(2)->setRowHeight(24);

        $rowIdx2 = 3;
        foreach ($datos['tabla1_calidad'] as $idx => $t1) {
            $sheet2->setCellValue("A{$rowIdx2}", $idx + 1);
            $sheet2->setCellValue("B{$rowIdx2}", $t1['delegacion']);
            $sheet2->setCellValue("C{$rowIdx2}", $t1['renombrados']);
            $sheet2->setCellValue("D{$rowIdx2}", $t1['validados']);
            $sheet2->setCellValue("E{$rowIdx2}", $t1['duplicados']);
            $sheet2->setCellValue("F{$rowIdx2}", $t1['archivos']);
            $sheet2->setCellValue("G{$rowIdx2}", $t1['paginas']);

            $sheet2->getStyle("A{$rowIdx2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle("C{$rowIdx2}:G{$rowIdx2}")->getNumberFormat()->setFormatCode('#,##0');

            if ($rowIdx2 % 2 === 0) {
                $sheet2->getStyle("A{$rowIdx2}:G{$rowIdx2}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
            $rowIdx2++;
        }

        $tot1 = $datos['totales_t1'];
        $sheet2->setCellValue("A{$rowIdx2}", '');
        $sheet2->setCellValue("B{$rowIdx2}", 'TOTAL GENERAL');
        $sheet2->setCellValue("C{$rowIdx2}", $tot1['renombrados']);
        $sheet2->setCellValue("D{$rowIdx2}", $tot1['validados']);
        $sheet2->setCellValue("E{$rowIdx2}", $tot1['duplicados']);
        $sheet2->setCellValue("F{$rowIdx2}", $tot1['archivos']);
        $sheet2->setCellValue("G{$rowIdx2}", $tot1['paginas']);

        $sheet2->getStyle("A{$rowIdx2}:G{$rowIdx2}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '475569']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '475569']]
            ]
        ]);
        $sheet2->getStyle("C{$rowIdx2}:G{$rowIdx2}")->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'G') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $nombreArchivo = 'Comparacion_Sellos_Enviados_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0'
        ]);
    }


    /**
     * Muestra la vista de búsqueda de archivos por nombre original o nuevo
     */
    public function index_buscar_archivos()
    {
        $delegaciones = DB::table('delegaciones')->select('id', 'nombre')->orderBy('nombre')->get();
        $usuarios = DB::table('users')->select('id', 'name')->orderBy('name')->get();
        return view('buscador.explorador_global', compact('delegaciones', 'usuarios'));
    }

    /**
     * Obtiene los registros coincidentes en las 4 tablas de auditoría (Registros, Sellos, Notarías, Catastro)
     * por nombre_original, archivo_nuevo, lote o hash, independientemente del tipo de archivo.
     */
    public function obtener_busqueda_archivos(Request $request)
    {
        ini_set('memory_limit', '-1');

        $busqueda = trim((string) $request->busqueda);
        $delegacion_id = $request->delegacion_id;
        $user_id = $request->user_id;
        $fecha_inicio = $request->fecha_inicio;
        $fecha_fin = $request->fecha_fin;
        $tipo_archivo = strtolower(trim((string) $request->tipo_archivo));
        $estado_trabajo = strtolower(trim((string) ($request->estado_trabajo ?: 'trabajados')));

        // -----------------------------------------------------------------
        // MODO 1: En captura activa (archivos_sesiones estado = en_captura)
        // -----------------------------------------------------------------
        if ($estado_trabajo === 'en_captura') {
            $q = \App\Models\ArchivoSesion::with(['usuario', 'delegacion'])
                ->where('estado', 'en_captura');

            if (!empty($delegacion_id)) {
                $q->where('delegacion_id', $delegacion_id);
            }
            if (!empty($tipo_archivo)) {
                $q->where('tipo_archivo', $tipo_archivo);
            }
            if (!empty($busqueda)) {
                $q->where(function ($sub) use ($busqueda) {
                    $sub->where('archivo_nombre', 'like', "%{$busqueda}%")
                        ->orWhere('ruta_origen', 'like', "%{$busqueda}%")
                        ->orWhere('hash_pdf', 'like', "%{$busqueda}%");
                });
            }

            $registros = $q->orderByDesc('tomado_en')->limit(300)->get()->map(function ($s) {
                return (object) [
                    'id'                => $s->id,
                    'tipo_documento'    => strtoupper($s->tipo_archivo ?: 'SELLOS'),
                    'fecha_hora'        => $s->tomado_en ? $s->tomado_en->format('Y-m-d H:i:s') : null,
                    'created_at'        => $s->created_at?->format('Y-m-d H:i:s'),
                    'accion'            => 'en_captura',
                    'estado_trabajo'    => 'en_captura',
                    'archivo_original'  => $s->archivo_nombre,
                    'archivo_nuevo'     => '-',
                    'lote'              => basename(dirname(str_replace('\\', '/', (string) $s->ruta_origen))),
                    'pc'                => $s->pc,
                    'paginas'           => (int) $s->paginas,
                    'ruta_origen'       => $s->ruta_origen,
                    'ruta_propuesta'    => null,
                    'ruta_destino'      => null,
                    'hash'              => $s->hash_pdf,
                    'archivo_danado'    => false,
                    'usuario_nombre'    => $s->usuario->name ?? 'Sin Usuario',
                    'delegacion_nombre' => $s->delegacion->nombre ?? 'Sin Delegación',
                ];
            });

            return response()->json([
                'registros' => $registros,
                'kpis'      => ['total' => $registros->count(), 'renombrados' => 0, 'validados' => 0, 'duplicados' => 0],
            ]);
        }

        // -----------------------------------------------------------------
        // MODO 2: Pendientes o Todos (cruza indice_archivos_pdfs de Stellum vs BD local)
        // -----------------------------------------------------------------
        if (in_array($estado_trabajo, ['pendientes', 'todos'], true)) {
            $delFiltro = $delegacion_id ? DB::table('delegaciones')->where('id', $delegacion_id)->first() : null;
            $bridge = app(\App\Services\StellumBridgeService::class);
            $tipoConsulta = $tipo_archivo ?: 'sellos';

            $itemsStellum = [];
            $afterId = 0;
            $maxPaginas = 4; // hasta 400 registros rápidos desde Stellum
            for ($p = 0; $p < $maxPaginas; $p++) {
                $resp = $bridge->listarIndice([
                    'tipo'          => $tipoConsulta,
                    'clasificacion' => 'entregable',
                    'delegacion'    => $delFiltro?->nombre,
                    'q'             => $busqueda,
                    'after_id'      => $afterId,
                    'limit'         => 100,
                ]);
                if (empty($resp['ok']) || empty($resp['items'])) {
                    break;
                }
                foreach ($resp['items'] as $it) {
                    $itemsStellum[] = $it;
                }
                if (empty($resp['has_more']) || count($itemsStellum) >= 250) {
                    break;
                }
                $afterId = (int) ($resp['last_id'] ?? $afterId);
            }

            $idsIndice = collect($itemsStellum)->pluck('id')->filter()->values()->all();

            $auditoriasPorIndice = !empty($idsIndice)
                ? DB::table('auditoria_sellos as a')
                    ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                    ->whereIn('a.indice_pdf_id', $idsIndice)
                    ->select('a.*', 'u.name as usuario_nombre')
                    ->orderByDesc('a.id')
                    ->get()
                    ->keyBy('indice_pdf_id')
                : collect();

            $sesionesPorIndice = !empty($idsIndice)
                ? DB::table('archivos_sesiones as s')
                    ->leftJoin('users as u', 'u.id', '=', 's.user_id')
                    ->whereIn('s.indice_pdf_id', $idsIndice)
                    ->select('s.*', 'u.name as usuario_nombre')
                    ->get()
                    ->keyBy('indice_pdf_id')
                : collect();

            $registros = collect();
            foreach ($itemsStellum as $it) {
                $idPdf = (int) ($it['id'] ?? 0);
                $aud = $auditoriasPorIndice->get($idPdf);
                $ses = $sesionesPorIndice->get($idPdf);

                $estadoItem = 'pendiente';
                if ($aud) {
                    $estadoItem = 'trabajado';
                } elseif ($ses && $ses->estado === 'en_captura') {
                    $estadoItem = 'en_captura';
                } elseif ($ses && $ses->estado === 'completado') {
                    $estadoItem = 'trabajado';
                }

                if ($estado_trabajo === 'pendientes' && $estadoItem !== 'pendiente') {
                    continue;
                }

                $rutaOrg = (string) ($it['ruta'] ?? '');
                $loteCarpeta = $rutaOrg !== '' ? basename(dirname(str_replace('\\', '/', $rutaOrg))) : 'N/A';

                $registros->push((object) [
                    'id'                => $idPdf,
                    'tipo_documento'    => strtoupper((string) ($it['tipo'] ?? $tipoConsulta)),
                    'fecha_hora'        => $aud->fecha_hora ?? ($ses->tomado_en ?? null),
                    'created_at'        => $aud->created_at ?? null,
                    'accion'            => $aud ? $aud->accion : ($estadoItem === 'en_captura' ? 'en_captura' : 'pendiente'),
                    'estado_trabajo'    => $estadoItem,
                    'archivo_original'  => preg_replace('/\.pdf$/i', '', (string) ($it['nombre'] ?? '')),
                    'archivo_nuevo'     => $aud->archivo_nuevo ?? '-',
                    'lote'              => $loteCarpeta,
                    'pc'                => $aud->pc ?? ($ses->pc ?? '-'),
                    'paginas'           => (int) ($it['paginas'] ?? 0),
                    'ruta_origen'       => $rutaOrg,
                    'ruta_propuesta'    => $aud->ruta_propuesta ?? null,
                    'ruta_destino'      => $aud->ruta_destino ?? null,
                    'hash'              => $it['hash'] ?? null,
                    'archivo_danado'    => (bool) ($aud->archivo_danado ?? false),
                    'usuario_nombre'    => $aud->usuario_nombre ?? ($ses->usuario_nombre ?? 'Sin asignar'),
                    'delegacion_nombre' => $it['delegacion'] ?? 'Sin Delegación',
                ]);
            }

            return response()->json([
                'registros' => $registros->values(),
                'kpis'      => [
                    'total'       => $registros->count(),
                    'renombrados' => $registros->filter(fn ($i) => in_array(strtolower((string) $i->accion), ['renombrar', 'renombrado']))->count(),
                    'validados'   => $registros->filter(fn ($i) => in_array(strtolower((string) $i->accion), ['validado', 'validar']))->count(),
                    'duplicados'  => 0,
                ],
            ]);
        }

        // -----------------------------------------------------------------
        // MODO 3 (Default): Trabajados (tablas locales auditoria_*)
        // -----------------------------------------------------------------
        $limitePorTabla = !empty($busqueda) ? 500 : 150;
        $resultados = collect();

        $tablasConfig = [
            'registros' => ['tabla' => 'auditoria_registros', 'etiqueta' => 'REGISTROS', 'tiene_del' => true, 'col_lote' => 'lote'],
            'sellos'    => ['tabla' => 'auditoria_sellos',    'etiqueta' => 'SELLOS',    'tiene_del' => true, 'col_lote' => 'lote'],
            'notarias'  => ['tabla' => 'auditoria_notarias',  'etiqueta' => 'NOTARÍAS',  'tiene_del' => false, 'col_lote' => 'volumen'],
            'catastro'  => ['tabla' => 'auditoria_catastro',  'etiqueta' => 'CATASTRO',  'tiene_del' => false, 'col_lote' => 'lote'],
        ];

        foreach ($tablasConfig as $claveTipo => $cfg) {
            if (!empty($tipo_archivo) && $tipo_archivo !== $claveTipo) {
                continue;
            }

            $q = DB::table($cfg['tabla'] . ' as ac')
                ->leftJoin('users as u', 'u.id', '=', 'ac.user_id');

            if ($cfg['tiene_del']) {
                $q->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id');
                $selectDel = DB::raw("COALESCE(d.nombre, ac.directorio, 'Sin Delegación') as delegacion_nombre");
            } else {
                $selectDel = DB::raw("COALESCE(ac.directorio, '{$cfg['etiqueta']}') as delegacion_nombre");
            }

            $colLote = $cfg['col_lote'];

            $q->select(
                'ac.id',
                DB::raw("'{$cfg['etiqueta']}' as tipo_documento"),
                'ac.fecha_hora',
                'ac.created_at',
                'ac.accion',
                DB::raw("'trabajado' as estado_trabajo"),
                'ac.archivo_original',
                'ac.archivo_nuevo',
                DB::raw("ac.{$colLote} as lote"),
                'ac.pc',
                'ac.paginas',
                'ac.ruta_origen',
                'ac.ruta_propuesta',
                'ac.ruta_destino',
                'ac.hash',
                'ac.archivo_danado',
                'ac.tiempo_captura',
                'ac.turno',
                'ac.detalles',
                'ac.directorio',
                DB::raw("COALESCE(u.name, 'Sin Usuario') as usuario_nombre"),
                $selectDel
            );

            if (!empty($busqueda)) {
                $q->where(function ($sub) use ($busqueda, $colLote) {
                    $sub->where('ac.archivo_original', 'like', "%{$busqueda}%")
                        ->orWhere('ac.archivo_nuevo', 'like', "%{$busqueda}%")
                        ->orWhere("ac.{$colLote}", 'like', "%{$busqueda}%")
                        ->orWhere('ac.hash', 'like', "%{$busqueda}%");
                });
            }

            if (!empty($delegacion_id) && $cfg['tiene_del']) {
                $q->where('ac.delegacion_id', $delegacion_id);
            }

            if (!empty($user_id)) {
                $q->where('ac.user_id', $user_id);
            }

            if (!empty($fecha_inicio) && !empty($fecha_fin)) {
                $q->whereBetween('ac.fecha_hora', [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
            }

            $filas = $q->orderBy('ac.fecha_hora', 'desc')->limit($limitePorTabla)->get();
            $resultados = $resultados->concat($filas);
        }

        $registros = $resultados->sortByDesc('fecha_hora')->values()->take(1000);

        $totalRegistros = $registros->count();
        $totalRenombrados = $registros->filter(fn($item) => in_array(strtolower((string)$item->accion), ['renombrar', 'renombrado']))->count();
        $totalValidados = $registros->filter(fn($item) => in_array(strtolower((string)$item->accion), ['validado', 'validar']))->count();
        $totalDuplicados = $registros->filter(fn($item) => in_array(strtolower((string)$item->accion), ['duplicar', 'duplicado']))->count();

        return response()->json([
            'registros' => $registros,
            'kpis' => [
                'total' => $totalRegistros,
                'renombrados' => $totalRenombrados,
                'validados' => $totalValidados,
                'duplicados' => $totalDuplicados,
            ]
        ]);
    }

    /**
     * Muestra la vista de lotes que se están trabajando (unificados en LoteCaptura)
     */
    public function index_lotes_trabajando()
    {
        $delegaciones = DB::table('delegaciones')->select('id', 'nombre')->orderBy('nombre')->get();
        $usuarios = DB::table('users')->select('id', 'name')->orderBy('name')->get();
        return view('monitoreo.sesiones_activas', compact('delegaciones', 'usuarios'));
    }

    /**
     * Obtiene la lista de sesiones por archivo individual (archivos_sesiones).
     */
    public function obtener_lotes_trabajando(Request $request)
    {
        $estado = $request->estado;
        $delegacion_id = $request->delegacion_id;
        $user_id = $request->user_id;

        // Liberar automáticamente sesiones expiradas (> 15 min)
        \App\Models\ArchivoSesion::where('estado', 'en_captura')
            ->whereNotNull('expira_en')
            ->where('expira_en', '<', now())
            ->delete();

        $query = \App\Models\ArchivoSesion::with(['usuario', 'delegacion']);

        if (!empty($estado)) {
            $estadoMapeado = $estado === 'en_proceso' ? 'en_captura' : $estado;
            $query->where('estado', $estadoMapeado);
        }
        if (!empty($delegacion_id)) {
            $query->where('delegacion_id', $delegacion_id);
        }
        if (!empty($user_id)) {
            $query->where('user_id', $user_id);
        }

        $sesiones = $query->orderByDesc('updated_at')->limit(300)->get();

        $todosLotes = $sesiones->map(function ($s) {
            return [
                'id'               => $s->id,
                'tipo_origen'      => $s->tipo_archivo ?: 'sellos',
                'disco'            => $s->disco ?: 'sellos',
                'es_catastro'      => $s->tipo_archivo === 'catastro',
                'es_notaria'       => $s->tipo_archivo === 'notarias',
                'nombre_lote'      => $s->archivo_nombre,
                'ruta_relativa'    => $s->archivo_nombre,
                'estado'           => $s->estado === 'en_captura' ? 'en_proceso' : $s->estado,
                'delegacion'       => $s->delegacion ? $s->delegacion->nombre : 'Sin Delegación',
                'delegacion_id'    => $s->delegacion_id,
                'usuario'          => $s->usuario ? ($s->usuario->name ?: $s->usuario->user) : ('Usuario #' . $s->user_id),
                'user_id'          => $s->user_id,
                'tomado_en'        => $s->tomado_en ? $s->tomado_en->format('Y-m-d H:i:s') : '-',
                'completado_en'    => $s->completado_en ? $s->completado_en->format('Y-m-d H:i:s') : '-',
                'progreso_archivo' => $s->tiempo_segundos ? ($s->tiempo_segundos . ' seg') : ($s->pc ? 'PC: ' . $s->pc : 'En captura'),
                'updated_at'       => $s->updated_at ? $s->updated_at->format('Y-m-d H:i:s') : '-',
                'raw_time'         => $s->updated_at ? $s->updated_at->timestamp : 0,
            ];
        })->values();

        return response()->json([
            'lotes' => $todosLotes,
            'kpis'  => [
                'total'            => $todosLotes->count(),
                'en_proceso'       => $todosLotes->where('estado', 'en_proceso')->count(),
                'completados'      => $todosLotes->where('estado', 'completado')->count(),
                'usuarios_activos' => $todosLotes->where('estado', 'en_proceso')->pluck('user_id')->unique()->filter()->count(),
            ]
        ]);
    }

    /**
     * Libera una sesión de archivo individual en archivos_sesiones.
     */
    public function eliminar_lote_trabajando(Request $request)
    {
        $id = $request->id;
        $sesion = \App\Models\ArchivoSesion::find($id);

        if (!$sesion) {
            return response()->json([
                'exito'   => false,
                'mensaje' => 'La sesión del archivo no fue encontrada o ya fue liberada.'
            ], 404);
        }

        $nombreLote = $sesion->archivo_nombre;
        $sesion->delete();

        try {
            broadcast(new \App\Events\LoteActualizadoEvent('lote_eliminado', ['id' => $id, 'lote' => $nombreLote]));
        } catch (\Throwable $e) {}

        self::logSellos('info', 'Lote/sesión trabajando eliminado', ['id' => $id, 'nombre_lote' => $nombreLote]);

        return response()->json([
            'exito' => true,
            'mensaje' => "El registro '{$nombreLote}' ha sido eliminado exitosamente y liberado para captura."
        ]);
    }

    /**
     * Obtiene el siguiente registro pendiente de revisión de sellos.
     * Soporta concurrencia atómica multiusuario y multi-PC para evitar colisiones.
     */
    public function obtenerSiguienteRevision(Request $request)
    {
        try {
            $userId = $request->query('user_id');
            $pc = $request->query('pc') ?: $request->query('nombre_pc');
            $nombreUsuario = $request->query('nombre_usuario') ?? $request->query('usuario');

            // Identificador único de worker combinando usuario y PC para que múltiples PCs nunca colisionen
            $workerId = !empty($pc) ? ($userId ? "{$userId}_{$pc}" : $pc) : ($userId ? (string) $userId : 'anonimo_' . $request->ip());

            // 1. Liberar registros 'en_proceso' abandonados por más de 15 minutos (por ejemplo si una PC se apagó)
            DB::table('archivos_revision_sellos')
                ->where('estado', 'en_proceso')
                ->where('updated_at', '<', now()->subMinutes(15))
                ->update([
                    'estado' => 'pendiente',
                    'revisado_por' => null,
                    'updated_at' => now(),
                ]);

            $siguiente = null;

            // 2. Transacción atómica con bloqueo de fila
            DB::transaction(function () use ($workerId, $userId, $nombreUsuario, &$siguiente) {
                // A. Si este worker específico ya tiene un archivo en proceso, reanudarlo
                $siguiente = DB::table('archivos_revision_sellos')
                    ->where('estado', 'en_proceso')
                    ->where('revisado_por', $workerId)
                    ->orderBy('id', 'asc')
                    ->first();

                // B. Si no tiene en proceso, tomar y apartar de forma exclusiva el primer registro pendiente
                if (!$siguiente) {
                    $siguiente = DB::table('archivos_revision_sellos')
                        ->where('estado', 'pendiente')
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->first();

                    if ($siguiente) {
                        DB::table('archivos_revision_sellos')
                            ->where('id', $siguiente->id)
                            ->update([
                                'estado' => 'en_proceso',
                                'user_id' => $userId ?: null,
                                'revisado_por' => $workerId,
                                'updated_at' => now(),
                            ]);

                        $siguiente->estado = 'en_proceso';
                        $siguiente->user_id = $userId ?: null;
                        $siguiente->revisado_por = $workerId;
                    }
                }
            });

            $total = DB::table('archivos_revision_sellos')->count();
            $pendientes = DB::table('archivos_revision_sellos')->whereIn('estado', ['pendiente', 'en_proceso'])->count();
            $revisados = DB::table('archivos_revision_sellos')->where('estado', 'revisado')->count();

            if (!$siguiente) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'No hay más archivos pendientes de revisión.',
                    'totales' => [
                        'total' => $total,
                        'pendientes' => 0,
                        'revisados' => $revisados,
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'revision' => $siguiente,
                'totales' => [
                    'total' => $total,
                    'pendientes' => $pendientes,
                    'revisados' => $revisados,
                ]
            ]);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al obtener siguiente archivo de revisión: ' . $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Resuelve un registro de revisión de sellos asignando delegación o acción correspondiente.
     */
    public function resolverRevision(Request $request)
    {
        try {
            $revisionId = $request->input('revision_id') ?? $request->input('id');
            $delegacion = $request->input('delegacion'); // 'Huixtla', 'Ocosingo', 'Libros', 'Inconsistencia', 'Otra Delegacion'
            $userId = $request->input('user_id');
            $nombreUsuario = $request->input('nombre_usuario') ?? $request->input('usuario');
            $pc = $request->input('pc') ?? $request->input('nombre_pc');

            if (!$revisionId || !$delegacion) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'Parámetros incompletos (revision_id y delegacion son obligatorios).'
                ], 400);
            }

            $revision = DB::table('archivos_revision_sellos')->where('id', $revisionId)->first();

            if (!$revision) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'El registro de revisión no fue encontrado.'
                ], 404);
            }

            // Actualizar tabla archivos_revision_sellos
            $camposUpdate = [
                'estado' => 'revisado',
                'delegacion' => $delegacion,
                'user_id' => $userId ?: $revision->user_id,
                'revisado_por' => $nombreUsuario ?: ($revision->revisado_por ?: null),
                'fecha_revision' => now(),
                'updated_at' => now(),
            ];

            if (strcasecmp($delegacion, 'Huixtla') === 0) {
                $rutaDestinoHuixtla = !empty($revision->ruta_nueva)
                    ? $revision->ruta_nueva
                    : str_replace('Ocosingo', 'Huixtla', str_replace($revision->archivo_nuevo, $revision->archivo_nuevo_propuesto, $revision->ruta_actual));
                $camposUpdate['ruta_nueva'] = $rutaDestinoHuixtla;
            } elseif (strcasecmp($delegacion, 'Ocosingo') === 0) {
                $camposUpdate['ruta_nueva'] = $revision->ruta_actual;
            } elseif (strcasecmp($delegacion, 'Libros') === 0) {
                $baseIrec = rtrim((string) config('captura_pdf.discos_stellum.irec', ''), '\\/');
                $camposUpdate['ruta_nueva'] = ($baseIrec ? $baseIrec . '\\' : '') . "LIBROS\\Ocosingo\\" . ($revision->archivo_nuevo ?: $revision->archivo_original);
            } elseif (strcasecmp($delegacion, 'Inconsistencia') === 0 || strcasecmp($delegacion, 'Otra Delegacion') === 0 || strcasecmp($delegacion, 'No Existe') === 0) {
                $camposUpdate['ruta_nueva'] = null;
            }

            DB::table('archivos_revision_sellos')
                ->where('id', $revisionId)
                ->update($camposUpdate);

            // 1. Si se confirmó como Huixtla, actualizar auditoria_sellos con delegación 10
            if (strcasecmp($delegacion, 'Huixtla') === 0) {
                $rutaDestino = $camposUpdate['ruta_nueva'] ?? $revision->ruta_nueva ?? DB::raw('ruta');

                DB::table('auditoria_sellos')
                    ->where(function ($q) use ($revision) {
                        $q->where('id', $revision->auditoria_sello_id);
                        if (!empty($revision->archivo_nuevo)) {
                            $q->orWhere('archivo_nuevo', $revision->archivo_nuevo);
                        }
                    })
                    ->update([
                        'delegacion_id' => 10,
                        'archivo_nuevo' => $revision->archivo_nuevo_propuesto,
                        'ruta' => $rutaDestino,
                        'updated_at' => now(),
                    ]);
            }
            // 2. Si se mandó a Libros, actualizar únicamente el registro que coincida en cantidad de páginas
            elseif (strcasecmp($delegacion, 'Libros') === 0) {
                $nombreArchivoLibros = !empty($revision->archivo_nuevo) ? $revision->archivo_nuevo : $revision->archivo_original;
                $baseIrec = rtrim((string) config('captura_pdf.discos_stellum.irec', ''), '\\/');
                $rutaDestinoLibros = ($baseIrec ? $baseIrec . '\\' : '') . "LIBROS\\Ocosingo\\" . $nombreArchivoLibros;

                // Contar páginas físicas del archivo PDF (en destino o ruta actual)
                $paginasPdf = $this->contarPaginasPdf($rutaDestinoLibros);
                if ($paginasPdf === 0 && !empty($revision->ruta_actual)) {
                    $paginasPdf = $this->contarPaginasPdf($revision->ruta_actual);
                }

                // Obtener candidatos en auditoria_sellos vinculados al archivo
                $candidatos = DB::table('auditoria_sellos')
                    ->where(function ($q) use ($revision) {
                        $q->where('id', $revision->auditoria_sello_id);
                        if (!empty($revision->archivo_nuevo)) {
                            $q->orWhere('archivo_nuevo', $revision->archivo_nuevo);
                        }
                    })
                    ->get();

                // Buscar el registro que tenga exactamente la misma cantidad de páginas
                $idActualizar = null;
                if ($paginasPdf > 0) {
                    $matchPaginas = $candidatos->where('paginas', $paginasPdf)->sortByDesc('id')->first();
                    if ($matchPaginas) {
                        $idActualizar = $matchPaginas->id;
                    }
                }

                // Si no se encontró coincidencia exacta por páginas, actualizar el más reciente
                if (!$idActualizar) {
                    $idActualizar = $candidatos->sortByDesc('id')->first()?->id ?? $revision->auditoria_sello_id;
                }

                DB::table('auditoria_sellos')
                    ->where('id', $idActualizar)
                    ->update([
                        'accion' => 'Libros',
                        'ruta' => $rutaDestinoLibros,
                        'updated_at' => now(),
                    ]);
            }

            return response()->json([
                'success' => true,
                'mensaje' => "Archivo marcado exitosamente como {$delegacion}."
            ]);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al resolver revisión: ' . $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Cuenta de forma rápida las páginas de un archivo PDF en disco.
     */
    private function contarPaginasPdf(?string $ruta): int
    {
        if (empty($ruta) || !@file_exists($ruta)) {
            return 0;
        }

        try {
            $fp = @fopen($ruta, 'rb');
            if (!$fp)
                return 0;

            $tamano = @filesize($ruta);
            if ($tamano <= 0) {
                fclose($fp);
                return 0;
            }

            $leerBytes = min(8192, $tamano);
            fseek($fp, -$leerBytes, SEEK_END);
            $trailer = fread($fp, $leerBytes);
            fclose($fp);

            if (preg_match_all('/\/Count\s+(\d+)/i', $trailer, $matches)) {
                return (int) end($matches[1]);
            }

            if ($tamano < 10000000) {
                $contenido = @file_get_contents($ruta);
                if ($contenido && preg_match_all('/\/Count\s+(\d+)/i', $contenido, $matches)) {
                    return (int) max($matches[1]);
                }
            }
        } catch (\Throwable $e) {
            // Ignorar
        }

        return 0;
    }

    /**
     * Vista principal del módulo de Avance de Revisión de Sellos (Renderizado directo en servidor).
     */
    public function avance_revision(Request $request)
    {
        $filtroDecision = $request->input('filtro_decision');
        $filtroUsuario = $request->input('filtro_usuario');
        $buscar = $request->input('buscar');
        $porPagina = (int)($request->input('por_pagina') ?: 25);

        // 1. Estadísticas globales
        $totalGeneral = DB::table('archivos_revision_sellos')->count();

        $resumenQuery = DB::table('archivos_revision_sellos')
            ->select(
                DB::raw("
                    CASE 
                        WHEN estado IN ('pendiente', 'en_proceso') THEN 'Pendientes'
                        WHEN delegacion = 'Huixtla' THEN 'Huixtla'
                        WHEN delegacion = 'Ocosingo' THEN 'Ocosingo'
                        WHEN delegacion = 'Libros' THEN 'Libros'
                        WHEN delegacion = 'Inconsistencia' THEN 'Inconsistencia'
                        WHEN delegacion = 'Otra Delegacion' THEN 'Otra Delegacion'
                        WHEN delegacion = 'No Existe' THEN 'No Existe'
                        ELSE IFNULL(delegacion, 'Sin Clasificar')
                    END as tipo_decision
                "),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('tipo_decision')
            ->get();

        $conteoPorTipo = [];
        foreach ($resumenQuery as $r) {
            $conteoPorTipo[$r->tipo_decision] = (int)$r->total;
        }

        $totalHuixtla = $conteoPorTipo['Huixtla'] ?? 0;
        $totalPendientes = $conteoPorTipo['Pendientes'] ?? 0;
        $totalInconsistencia = $conteoPorTipo['Inconsistencia'] ?? 0;
        $totalOcosingo = $conteoPorTipo['Ocosingo'] ?? 0;
        $totalLibros = $conteoPorTipo['Libros'] ?? 0;
        $totalOtraDel = $conteoPorTipo['Otra Delegacion'] ?? 0;
        $totalNoExiste = $conteoPorTipo['No Existe'] ?? 0;
        $totalRevisados = $totalGeneral - $totalPendientes;
        $porcentajeAvance = $totalGeneral > 0 ? round(($totalRevisados / $totalGeneral) * 100, 2) : 0;

        $totales = [
            'total_general'     => $totalGeneral,
            'total_revisados'   => $totalRevisados,
            'porcentaje_avance' => $porcentajeAvance,
            'huixtla'           => $totalHuixtla,
            'pendientes'        => $totalPendientes,
            'inconsistencia'    => $totalInconsistencia,
            'ocosingo'          => $totalOcosingo,
            'libros'            => $totalLibros,
            'otra_delegacion'   => $totalOtraDel,
            'no_existe'         => $totalNoExiste,
        ];

        // 2. Tabla ordenada de decisiones como en el query del usuario
        $tablaDecisiones = [
            ['tipo' => 'Huixtla', 'total' => $totalHuixtla, 'color' => '#10b981', 'badge' => 'badge-huixtla'],
            ['tipo' => 'Pendientes', 'total' => $totalPendientes, 'color' => '#f59e0b', 'badge' => 'badge-pendientes'],
            ['tipo' => 'Inconsistencia', 'total' => $totalInconsistencia, 'color' => '#ef4444', 'badge' => 'badge-inconsistencia'],
            ['tipo' => 'Ocosingo', 'total' => $totalOcosingo, 'color' => '#06b6d4', 'badge' => 'badge-ocosingo'],
            ['tipo' => 'Libros', 'total' => $totalLibros, 'color' => '#3b82f6', 'badge' => 'badge-libros'],
            ['tipo' => 'Otra Delegacion', 'total' => $totalOtraDel, 'color' => '#6366f1', 'badge' => 'badge-otra'],
        ];
        if ($totalNoExiste > 0) {
            $tablaDecisiones[] = ['tipo' => 'No Existe', 'total' => $totalNoExiste, 'color' => '#6b7280', 'badge' => 'badge-noexiste'];
        }

        usort($tablaDecisiones, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        // 3. Resumen por usuario
        $resumenUsuarios = DB::table('archivos_revision_sellos')
            ->where('estado', 'revisado')
            ->select(
                DB::raw("IFNULL(revisado_por, 'Sin Asignar') as usuario"),
                DB::raw('COUNT(*) as total_revisados'),
                DB::raw("SUM(CASE WHEN delegacion = 'Huixtla' THEN 1 ELSE 0 END) as huixtla"),
                DB::raw("SUM(CASE WHEN delegacion = 'Ocosingo' THEN 1 ELSE 0 END) as ocosingo"),
                DB::raw("SUM(CASE WHEN delegacion = 'Libros' THEN 1 ELSE 0 END) as libros"),
                DB::raw("SUM(CASE WHEN delegacion = 'Inconsistencia' THEN 1 ELSE 0 END) as inconsistencia"),
                DB::raw("SUM(CASE WHEN delegacion = 'Otra Delegacion' THEN 1 ELSE 0 END) as otra_delegacion")
            )
            ->groupBy('usuario')
            ->orderByDesc('total_revisados')
            ->get();

        // 4. Lista de usuarios para el filtro
        $usuarios = DB::table('archivos_revision_sellos')
            ->whereNotNull('revisado_por')
            ->where('revisado_por', '!=', '')
            ->select('revisado_por')
            ->distinct()
            ->orderBy('revisado_por')
            ->pluck('revisado_por');

        // 5. Consulta paginada con filtros
        $queryRegistros = DB::table('archivos_revision_sellos');

        if (!empty($filtroDecision)) {
            if ($filtroDecision === 'Pendientes') {
                $queryRegistros->whereIn('estado', ['pendiente', 'en_proceso']);
            } else {
                $queryRegistros->where('delegacion', $filtroDecision);
            }
        }

        if (!empty($filtroUsuario)) {
            $queryRegistros->where('revisado_por', $filtroUsuario);
        }

        if (!empty($buscar)) {
            $queryRegistros->where(function ($q) use ($buscar) {
                $q->where('archivo_original', 'like', "%{$buscar}%")
                    ->orWhere('archivo_nuevo', 'like', "%{$buscar}%")
                    ->orWhere('archivo_nuevo_propuesto', 'like', "%{$buscar}%");
            });
        }

        $registrosPaginados = $queryRegistros->orderBy('id', 'asc')->paginate($porPagina)->appends($request->query());

        return view('admin.analiticas.capturas.avance_revision_sellos', compact(
            'totales',
            'tablaDecisiones',
            'resumenUsuarios',
            'usuarios',
            'registrosPaginados',
            'filtroDecision',
            'filtroUsuario',
            'buscar',
            'porPagina'
        ));
    }

    /**
     * Retorna datos JSON con estadísticas de avance de revisión y listado paginado.
     */
    public function obtener_avance_revision(Request $request)
    {
        try {
            $filtroDecision = $request->input('filtro_decision');
            $filtroUsuario = $request->input('filtro_usuario');
            $buscar = $request->input('buscar');
            $porPagina = (int)($request->input('por_pagina') ?: 20);

            // 1. Estadísticas globales (sin filtros aplicados)
            $totalGeneral = DB::table('archivos_revision_sellos')->count();

            $resumenQuery = DB::table('archivos_revision_sellos')
                ->select(
                    DB::raw("
                        CASE 
                            WHEN estado IN ('pendiente', 'en_proceso') THEN 'Pendientes'
                            WHEN delegacion = 'Huixtla' THEN 'Huixtla'
                            WHEN delegacion = 'Ocosingo' THEN 'Ocosingo'
                            WHEN delegacion = 'Libros' THEN 'Libros'
                            WHEN delegacion = 'Inconsistencia' THEN 'Inconsistencia'
                            WHEN delegacion = 'Otra Delegacion' THEN 'Otra Delegacion'
                            WHEN delegacion = 'No Existe' THEN 'No Existe'
                            ELSE IFNULL(delegacion, 'Sin Clasificar')
                        END as tipo_decision
                    "),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('tipo_decision')
                ->get();

            $conteoPorTipo = [];
            foreach ($resumenQuery as $r) {
                $conteoPorTipo[$r->tipo_decision] = (int)$r->total;
            }

            $totalHuixtla = $conteoPorTipo['Huixtla'] ?? 0;
            $totalPendientes = $conteoPorTipo['Pendientes'] ?? 0;
            $totalInconsistencia = $conteoPorTipo['Inconsistencia'] ?? 0;
            $totalOcosingo = $conteoPorTipo['Ocosingo'] ?? 0;
            $totalLibros = $conteoPorTipo['Libros'] ?? 0;
            $totalOtraDel = $conteoPorTipo['Otra Delegacion'] ?? 0;
            $totalNoExiste = $conteoPorTipo['No Existe'] ?? 0;
            $totalRevisados = $totalGeneral - $totalPendientes;
            $porcentajeAvance = $totalGeneral > 0 ? round(($totalRevisados / $totalGeneral) * 100, 2) : 0;

            // Tabla ordenada de decisiones como en el query del usuario
            $tablaDecisiones = [
                ['tipo' => 'Huixtla', 'total' => $totalHuixtla, 'color' => '#10b981', 'badge' => 'badge-success'],
                ['tipo' => 'Pendientes', 'total' => $totalPendientes, 'color' => '#f59e0b', 'badge' => 'badge-warning'],
                ['tipo' => 'Inconsistencia', 'total' => $totalInconsistencia, 'color' => '#ef4444', 'badge' => 'badge-danger'],
                ['tipo' => 'Ocosingo', 'total' => $totalOcosingo, 'color' => '#06b6d4', 'badge' => 'badge-info'],
                ['tipo' => 'Libros', 'total' => $totalLibros, 'color' => '#3b82f6', 'badge' => 'badge-primary'],
                ['tipo' => 'Otra Delegacion', 'total' => $totalOtraDel, 'color' => '#6366f1', 'badge' => 'badge-secondary'],
            ];
            if ($totalNoExiste > 0) {
                $tablaDecisiones[] = ['tipo' => 'No Existe', 'total' => $totalNoExiste, 'color' => '#6b7280', 'badge' => 'badge-dark'];
            }

            // Ordenar por total descendente
            usort($tablaDecisiones, function ($a, $b) {
                return $b['total'] <=> $a['total'];
            });

            // 2. Resumen por usuario
            $resumenUsuarios = DB::table('archivos_revision_sellos')
                ->where('estado', 'revisado')
                ->select(
                    DB::raw("IFNULL(revisado_por, 'Sin Asignar') as usuario"),
                    DB::raw('COUNT(*) as total_revisados'),
                    DB::raw("SUM(CASE WHEN delegacion = 'Huixtla' THEN 1 ELSE 0 END) as huixtla"),
                    DB::raw("SUM(CASE WHEN delegacion = 'Ocosingo' THEN 1 ELSE 0 END) as ocosingo"),
                    DB::raw("SUM(CASE WHEN delegacion = 'Libros' THEN 1 ELSE 0 END) as libros"),
                    DB::raw("SUM(CASE WHEN delegacion = 'Inconsistencia' THEN 1 ELSE 0 END) as inconsistencia"),
                    DB::raw("SUM(CASE WHEN delegacion = 'Otra Delegacion' THEN 1 ELSE 0 END) as otra_delegacion")
                )
                ->groupBy('usuario')
                ->orderByDesc('total_revisados')
                ->get();

            // 3. Consulta paginada con filtros
            $queryRegistros = DB::table('archivos_revision_sellos');

            if (!empty($filtroDecision)) {
                if ($filtroDecision === 'Pendientes') {
                    $queryRegistros->whereIn('estado', ['pendiente', 'en_proceso']);
                } else {
                    $queryRegistros->where('delegacion', $filtroDecision);
                }
            }

            if (!empty($filtroUsuario)) {
                $queryRegistros->where('revisado_por', $filtroUsuario);
            }

            if (!empty($buscar)) {
                $queryRegistros->where(function ($q) use ($buscar) {
                    $q->where('archivo_original', 'like', "%{$buscar}%")
                        ->orWhere('archivo_nuevo', 'like', "%{$buscar}%")
                        ->orWhere('archivo_nuevo_propuesto', 'like', "%{$buscar}%");
                });
            }

            $registrosPaginados = $queryRegistros->orderBy('id', 'asc')->paginate($porPagina);

            return response()->json([
                'success' => true,
                'totales' => [
                    'total_general'     => $totalGeneral,
                    'total_revisados'   => $totalRevisados,
                    'porcentaje_avance' => $porcentajeAvance,
                    'huixtla'           => $totalHuixtla,
                    'pendientes'        => $totalPendientes,
                    'inconsistencia'    => $totalInconsistencia,
                    'ocosingo'          => $totalOcosingo,
                    'libros'            => $totalLibros,
                    'otra_delegacion'   => $totalOtraDel,
                    'no_existe'         => $totalNoExiste,
                ],
                'tabla_decisiones' => $tablaDecisiones,
                'resumen_usuarios' => $resumenUsuarios,
                'paginacion'       => [
                    'current_page' => $registrosPaginados->currentPage(),
                    'last_page'    => $registrosPaginados->lastPage(),
                    'total'        => $registrosPaginados->total(),
                    'from'         => $registrosPaginados->firstItem(),
                    'to'           => $registrosPaginados->lastItem(),
                    'data'         => $registrosPaginados->items(),
                ]
            ]);

        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al obtener avance de revisión: ' . $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Descarga reporte en Excel del avance de revisión de sellos.
     */
    public function descargar_excel_avance_revision(Request $request)
    {
        try {
            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            // ==========================================
            // HOJA 1: RESUMEN Y AVANCE DE REVISIÓN
            // ==========================================
            $hoja1 = $spreadsheet->createSheet(0);
            $hoja1->setTitle('Resumen Avance');
            $hoja1->setShowGridLines(true);

            // Título
            $hoja1->setCellValue('A1', 'REPORTE DE AVANCE DE REVISIÓN DE SELLOS');
            $hoja1->mergeCells('A1:E1');
            $hoja1->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new Color(Color::COLOR_WHITE));
            $hoja1->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('034D6D');
            $hoja1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Subtítulo Fecha
            $hoja1->setCellValue('A2', 'Generado: ' . date('Y-m-d H:i:s'));
            $hoja1->mergeCells('A2:E2');
            $hoja1->getStyle('A2')->getFont()->setItalic(true)->setSize(9);

            // Métricas Globales
            $totalGeneral = DB::table('archivos_revision_sellos')->count();
            $resumenQuery = DB::table('archivos_revision_sellos')
                ->select(
                    DB::raw("
                        CASE 
                            WHEN estado IN ('pendiente', 'en_proceso') THEN 'Pendientes'
                            WHEN delegacion = 'Huixtla' THEN 'Huixtla'
                            WHEN delegacion = 'Ocosingo' THEN 'Ocosingo'
                            WHEN delegacion = 'Libros' THEN 'Libros'
                            WHEN delegacion = 'Inconsistencia' THEN 'Inconsistencia'
                            WHEN delegacion = 'Otra Delegacion' THEN 'Otra Delegacion'
                            WHEN delegacion = 'No Existe' THEN 'No Existe'
                            ELSE IFNULL(delegacion, 'Sin Clasificar')
                        END as tipo_decision
                    "),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('tipo_decision')
                ->orderByDesc('total')
                ->get();

            $hoja1->setCellValue('A4', '#');
            $hoja1->setCellValue('B4', 'Tipo de Decisión / Estado');
            $hoja1->setCellValue('C4', 'Total Archivos');
            $hoja1->setCellValue('D4', 'Porcentaje');
            $hoja1->getStyle('A4:D4')->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
            $hoja1->getStyle('A4:D4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('034D6D');

            $fila = 5;
            $num = 1;
            foreach ($resumenQuery as $r) {
                $pct = $totalGeneral > 0 ? round(($r->total / $totalGeneral) * 100, 2) : 0;
                $hoja1->setCellValue('A' . $fila, $num++);
                $hoja1->setCellValue('B' . $fila, $r->tipo_decision);
                $hoja1->setCellValue('C' . $fila, (int)$r->total);
                $hoja1->setCellValue('D' . $fila, $pct . '%');
                $hoja1->getStyle('C' . $fila)->getNumberFormat()->setFormatCode('#,##0');
                $fila++;
            }

            // Fila Total
            $hoja1->setCellValue('B' . $fila, 'TOTAL GENERAL');
            $hoja1->setCellValue('C' . $fila, $totalGeneral);
            $hoja1->setCellValue('D' . $fila, '100.00%');
            $hoja1->getStyle('A' . $fila . ':D' . $fila)->getFont()->setBold(true);
            $hoja1->getStyle('A' . $fila . ':D' . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
            $hoja1->getStyle('C' . $fila)->getNumberFormat()->setFormatCode('#,##0');

            // Tabla por Usuario
            $fila += 3;
            $hoja1->setCellValue('A' . $fila, 'DESGLOSE POR REVISOR / USUARIO');
            $hoja1->mergeCells('A' . $fila . ':G' . $fila);
            $hoja1->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(11)->setColor(new Color(Color::COLOR_WHITE));
            $hoja1->getStyle('A' . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');

            $fila++;
            $hoja1->setCellValue('A' . $fila, 'Revisor / Usuario');
            $hoja1->setCellValue('B' . $fila, 'Total Revisados');
            $hoja1->setCellValue('C' . $fila, 'Huixtla');
            $hoja1->setCellValue('D' . $fila, 'Ocosingo');
            $hoja1->setCellValue('E' . $fila, 'Libros');
            $hoja1->setCellValue('F' . $fila, 'Inconsistencia');
            $hoja1->setCellValue('G' . $fila, 'Otra Delegación');
            $hoja1->getStyle('A' . $fila . ':G' . $fila)->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
            $hoja1->getStyle('A' . $fila . ':G' . $fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');

            $resumenUsuarios = DB::table('archivos_revision_sellos')
                ->where('estado', 'revisado')
                ->select(
                    DB::raw("IFNULL(revisado_por, 'Sin Asignar') as usuario"),
                    DB::raw('COUNT(*) as total_revisados'),
                    DB::raw("SUM(CASE WHEN delegacion = 'Huixtla' THEN 1 ELSE 0 END) as huixtla"),
                    DB::raw("SUM(CASE WHEN delegacion = 'Ocosingo' THEN 1 ELSE 0 END) as ocosingo"),
                    DB::raw("SUM(CASE WHEN delegacion = 'Libros' THEN 1 ELSE 0 END) as libros"),
                    DB::raw("SUM(CASE WHEN delegacion = 'Inconsistencia' THEN 1 ELSE 0 END) as inconsistencia"),
                    DB::raw("SUM(CASE WHEN delegacion = 'Otra Delegacion' THEN 1 ELSE 0 END) as otra_delegacion")
                )
                ->groupBy('usuario')
                ->orderByDesc('total_revisados')
                ->get();

            $fila++;
            foreach ($resumenUsuarios as $u) {
                $hoja1->setCellValue('A' . $fila, $u->usuario);
                $hoja1->setCellValue('B' . $fila, (int)$u->total_revisados);
                $hoja1->setCellValue('C' . $fila, (int)$u->huixtla);
                $hoja1->setCellValue('D' . $fila, (int)$u->ocosingo);
                $hoja1->setCellValue('E' . $fila, (int)$u->libros);
                $hoja1->setCellValue('F' . $fila, (int)$u->inconsistencia);
                $hoja1->setCellValue('G' . $fila, (int)$u->otra_delegacion);
                $hoja1->getStyle('B' . $fila . ':G' . $fila)->getNumberFormat()->setFormatCode('#,##0');
                $fila++;
            }

            foreach (range('A', 'G') as $col) {
                $hoja1->getColumnDimension($col)->setAutoSize(true);
            }

            // ==========================================
            // HOJA 2: LISTADO DETALLADO DE ARCHIVOS
            // ==========================================
            $hoja2 = $spreadsheet->createSheet(1);
            $hoja2->setTitle('Listado Detallado');
            $hoja2->setShowGridLines(true);

            $hoja2->setCellValue('A1', 'ID');
            $hoja2->setCellValue('B1', 'Archivo Original');
            $hoja2->setCellValue('C1', 'Archivo Actual');
            $hoja2->setCellValue('D1', 'Archivo Propuesto');
            $hoja2->setCellValue('E1', 'Estado');
            $hoja2->setCellValue('F1', 'Decisión / Delegación');
            $hoja2->setCellValue('G1', 'Revisado Por');
            $hoja2->setCellValue('H1', 'Fecha Revisión');
            $hoja2->setCellValue('I1', 'Ruta Actual');
            $hoja2->setCellValue('J1', 'Ruta Nueva');
            $hoja2->getStyle('A1:J1')->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
            $hoja2->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('034D6D');

            $registros = DB::table('archivos_revision_sellos')->orderBy('id', 'asc')->get();
            $filaDet = 2;
            foreach ($registros as $reg) {
                $hoja2->setCellValue('A' . $filaDet, $reg->id);
                $hoja2->setCellValue('B' . $filaDet, $reg->archivo_original);
                $hoja2->setCellValue('C' . $filaDet, $reg->archivo_nuevo);
                $hoja2->setCellValue('D' . $filaDet, $reg->archivo_nuevo_propuesto);
                $hoja2->setCellValue('E' . $filaDet, $reg->estado);
                $hoja2->setCellValue('F' . $filaDet, $reg->delegacion ?: 'Pendiente');
                $hoja2->setCellValue('G' . $filaDet, $reg->revisado_por);
                $hoja2->setCellValue('H' . $filaDet, $reg->fecha_revision);
                $hoja2->setCellValue('I' . $filaDet, $reg->ruta_actual);
                $hoja2->setCellValue('J' . $filaDet, $reg->ruta_nueva);
                $filaDet++;
            }

            foreach (range('A', 'J') as $col) {
                $hoja2->getColumnDimension($col)->setAutoSize(true);
            }

            $nombreArchivo = 'Avance_Revision_Sellos_' . date('Ymd_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Exception $ex) {
            return back()->with('error', 'Error al generar Excel de avance: ' . $ex->getMessage());
        }
    }

    /**
     * Escribe un mensaje en el archivo de log dedicado de auditoría de sellos.
     * Guarda los eventos en storage/logs/sellos-YYYY-MM-DD.log
     */
    private static function logSellos(string $nivel, string $mensaje, array $contexto = []): void
    {
        try {
            $nombreArchivo = 'sellos-' . date('Y-m-d') . '.log';
            $rutaLog = storage_path('logs/' . $nombreArchivo);

            $timestamp = date('Y-m-d H:i:s');
            $nivelUpper = strtoupper($nivel);
            $contextoJson = !empty($contexto) ? ' ' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            $linea = "[{$timestamp}] local.{$nivelUpper}: {$mensaje}{$contextoJson}\n";

            @file_put_contents($rutaLog, $linea, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Falla silenciosa para no interrumpir el flujo principal
        }
    }
}

