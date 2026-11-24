<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Delegaciones;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AuditoriaCatastroController extends Controller
{
    /**
     * Registra auditorías de Catastro provenientes de la aplicación ValidarPDF.
     */
    public function registrar(Request $request)
    {
        try {
            $datos = $request->all();

            if (empty($datos)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'La petición HTTP no contiene datos válidos.'
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

            foreach ($registrosAGuardar as $index => $originalReg) {
                if (empty($originalReg) || !is_array($originalReg)) {
                    continue;
                }

                try {
                    $reg = array_change_key_case($originalReg, CASE_LOWER);
                    $fechaHora = $reg['fechahora'] ?? $reg['fecha_hora'] ?? null;

                    if (!$fechaHora) {
                        $errores[] = "Registro {$index}: Falta el campo de fecha y hora.";
                        continue;
                    }

                    // Prevenir fechas del futuro
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

                    $archivoOriginalVal = $reg['archivooriginal'] ?? $reg['archivo_original'] ?? null;
                    $archivoNuevoVal = $reg['archivonuevo'] ?? $reg['archivo_nuevo'] ?? null;
                    $rutaOrigenVal = $reg['rutaorigen'] ?? $reg['ruta_origen'] ?? $reg['ruta_original'] ?? null;
                    $rutaDestinoVal = $reg['rutadestino'] ?? $reg['ruta_destino'] ?? $reg['ruta'] ?? null;

                    // Fallback para ruta_origen si viene vacía
                    $baseSsdcats = rtrim((string) config('captura_pdf.discos_stellum.ssdcats', ''), '\\/');
                    if (empty($rutaOrigenVal) && !empty($archivoOriginalVal)) {
                        $loteVal = $reg['lote'] ?? '';
                        if ($loteVal && $baseSsdcats !== '') {
                            $rutaOrigenVal = "{$baseSsdcats}\\LOTES_CATASTRO_CAPTURA\\{$loteVal}\\{$archivoOriginalVal}";
                        }
                    }

                    $accionVal = $reg['accion'] ?? null;
                    $accionFinal = $accionVal;
                    $detallesVal = $reg['detalles'] ?? null;
                    $detallesFinal = $detallesVal;

                    // Normalizar y asegurar ruta_destino completa si viene vacía o incompleta
                    if (empty($rutaDestinoVal) || !preg_match('/^(\\\\\\\\|[A-Z]:)/i', $rutaDestinoVal)) {
                        $nombreDestino = $archivoNuevoVal ?: $archivoOriginalVal;
                        if ($nombreDestino && $baseSsdcats !== '') {
                            if (stripos((string)$accionVal, 'dañado') !== false || stripos((string)$accionVal, 'danado') !== false) {
                                $rutaDestinoVal = "{$baseSsdcats}\\INCONSISTENCIAS_CATASTRO\\ARCHIVOS DAÑADOS\\{$nombreDestino}";
                            } else {
                                $baseDir = 'CALIDAD_CATASTRO';
                                if (stripos((string)$accionVal, 'inconsistencia') !== false) {
                                    $baseDir = 'INCONSISTENCIAS_CATASTRO';
                                } elseif (stripos((string)$accionVal, 'eliminado') !== false) {
                                    $baseDir = 'ELIMINADOS_CATASTRO';
                                }

                                $tipoDir = '';
                                $munClave = '';
                                if (preg_match('/^([012])(\d{3})/', $nombreDestino, $mTipoMun)) {
                                    $tipoDir = $mTipoMun[1];
                                    $munClave = $mTipoMun[2];
                                } elseif (stripos($nombreDestino, 'SIN_CLAVE_CATASTRAL') !== false) {
                                    $tipoDir = 'SIN CLAVE CATASTRAL';
                                    if ($detallesVal && preg_match('/(?:asignada\s*-\s*|Municipio\s*)(\d{3})/i', $detallesVal, $mMunDet)) {
                                        $munClave = $mMunDet[1];
                                    }
                                } elseif ($detallesVal && preg_match('/(?:asignada\s*-\s*|Municipio\s*)(\d{3})/i', $detallesVal, $mMunDet)) {
                                    $munClave = $mMunDet[1];
                                }

                                if ($tipoDir && $munClave) {
                                    $rutaDestinoVal = "{$baseSsdcats}\\{$baseDir}\\{$tipoDir}\\{$munClave}\\{$nombreDestino}";
                                } elseif ($tipoDir) {
                                    $rutaDestinoVal = "{$baseSsdcats}\\{$baseDir}\\{$tipoDir}\\{$nombreDestino}";
                                } elseif ($munClave) {
                                    $rutaDestinoVal = "{$baseSsdcats}\\{$baseDir}\\{$munClave}\\{$nombreDestino}";
                                } else {
                                    $rutaDestinoVal = "{$baseSsdcats}\\{$baseDir}\\{$nombreDestino}";
                                }
                            }
                        }
                    }

                    // Asegurar subcarpeta SIN CLAVE CATASTRAL/{municipio}/ en CALIDAD_CATASTRO
                    if ($rutaDestinoVal && $baseSsdcats !== '' && stripos($rutaDestinoVal, 'CALIDAD_CATASTRO') !== false && stripos((string)$archivoNuevoVal, 'SIN_CLAVE_CATASTRAL') !== false) {
                        if ($detallesVal && preg_match('/(?:asignada\s*-\s*|Municipio\s*)(\d{3})/i', $detallesVal, $mMunDet)) {
                            $munClave = $mMunDet[1];
                            $rutaDeseada = "CALIDAD_CATASTRO\\SIN CLAVE CATASTRAL\\{$munClave}";
                            if (stripos($rutaDestinoVal, $rutaDeseada) === false) {
                                $rutaDestinoVal = "{$baseSsdcats}\\{$rutaDeseada}\\{$archivoNuevoVal}";
                            }
                        }
                    }

                    // Resolver categoría y tipo específico de Catastro
                    $tipoVal = $reg['tipo'] ?? $reg['tiposello'] ?? $reg['tipo_sello'] ?? null;
                    $categoriaVal = $reg['categoria'] ?? 'Catastro';

                    if (empty($tipoVal) || strtolower($tipoVal) === 'catastro') {
                        $nombreRef = $archivoNuevoVal ?: $archivoOriginalVal;
                        if ($nombreRef) {
                            if (preg_match('/^0\d{15}/', $nombreRef)) {
                                $tipoVal = 'Urbano';
                            } elseif (preg_match('/^1\d{3}\d{4}\s\d+/', $nombreRef) || preg_match('/^1\d{7}\s\d+/', $nombreRef)) {
                                $tipoVal = 'Rustico';
                            } elseif (preg_match('/^2\d{3}\d{4}\s\d+/', $nombreRef) || preg_match('/^2\d{7}\s\d+/', $nombreRef)) {
                                $tipoVal = 'Sin Estudio';
                            } elseif (stripos($nombreRef, 'SIN_CLAVE_CATASTRAL') !== false) {
                                $tipoVal = 'Sin Clave';
                            } else {
                                $tipoVal = 'General';
                            }
                        } else {
                            $tipoVal = 'General';
                        }
                    } else {
                        // Normalizar nombre de tipo
                        if (stripos($tipoVal, 'Urbano') !== false) {
                            $tipoVal = 'Urbano';
                        } elseif (stripos($tipoVal, 'Rustico') !== false) {
                            $tipoVal = 'Rustico';
                        } elseif (stripos($tipoVal, 'Sin Estudio') !== false) {
                            $tipoVal = 'Sin Estudio';
                        } elseif (stripos($tipoVal, 'Sin Clave') !== false) {
                            $tipoVal = 'Sin Clave';
                        }
                    }

                    // Prevenir duplicado idéntico exacto
                    $duplicado = DB::table('auditoria_catastro')
                        ->where('fecha_hora', $fechaHora)
                        ->where('archivo_original', $archivoOriginalVal)
                        ->where('accion', $accionVal)
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
                        continue;
                    }

                    // Protección contra duplicados activos previos (consultando tablas de bitácoras OCR y auditoria_catastro)
                    $accionesActivas = ['renombrar', 'renombrado', 'validado', 'validar', 'duplicar', 'duplicado', 'correcto', 'sin clave catastral', 'sin clave'];
                    $nombreParaChecar = !empty($archivoNuevoVal) ? $archivoNuevoVal : $archivoOriginalVal;

                    if ($nombreParaChecar && in_array(strtolower((string)$accionVal), $accionesActivas)) {
                        $nombreSinExt = preg_replace('/\.pdf$/i', '', $nombreParaChecar);
                        $nombreConExt = $nombreSinExt . '.pdf';

                        // 1. Consultar primero en las tablas de bitácoras de OCR para identificar la tabla exacta de origen
                        $tablasBitacoraOcr = ['bitacoracatastro3000_pruebas', 'bitacoracatastro3000', 'bitacoracatastro3000_2do', 'bitacoracatastro3000_real'];
                        $registroPrevioBitacora = null;
                        $tablaBitacoraEncontrada = null;

                        foreach ($tablasBitacoraOcr as $tablaB) {
                            if (!Schema::hasTable($tablaB)) continue;

                            $regB = DB::table($tablaB)
                                ->select('id', 'tipo_archivo', 'fecha')
                                ->where('tipo_archivo', 'Correcto')
                                ->where(function ($q) use ($nombreSinExt, $nombreConExt) {
                                    $q->where('nombre_nuevo', $nombreSinExt)
                                      ->orWhere('nombre_nuevo', $nombreConExt);
                                })
                                ->orderBy('id', 'asc')
                                ->first();

                            if ($regB) {
                                $registroPrevioBitacora = $regB;
                                $tablaBitacoraEncontrada = $tablaB;
                                break;
                            }
                        }

                        if ($registroPrevioBitacora) {
                            $accionFinal = 'Auto-Eliminado por Duplicado';
                            $fechaBitacora = !empty($registroPrevioBitacora->fecha) ? " ({$registroPrevioBitacora->fecha})" : '';
                            $notaAutoEliminado = "[Auto-Eliminado: el archivo ya fue procesado previamente por OCR en la tabla '{$tablaBitacoraEncontrada}' en ID {$registroPrevioBitacora->id}{$fechaBitacora}]";
                            $detallesFinal = trim(($detallesVal ? $detallesVal . ' ' : '') . $notaAutoEliminado);
                        } else {
                            // 2. Consultar en auditoria_catastro (capturas previas o registros de OCR importados)
                            $registroPrevioAuditoria = DB::table('auditoria_catastro')
                                ->select('id', 'accion', 'categoria', 'fecha_hora')
                                ->where('archivo_nuevo', $nombreParaChecar)
                                ->where(function ($q) use ($accionesActivas) {
                                    $q->whereIn(DB::raw('LOWER(accion)'), $accionesActivas)
                                      ->orWhere('categoria', 'OCR');
                                })
                                ->orderBy('id', 'asc')
                                ->first();

                            if ($registroPrevioAuditoria) {
                                $accionFinal = 'Auto-Eliminado por Duplicado';
                                $fechaAuditoria = !empty($registroPrevioAuditoria->fecha_hora) ? " ({$registroPrevioAuditoria->fecha_hora})" : '';
                                if ($registroPrevioAuditoria->categoria === 'OCR' || strtolower((string)$registroPrevioAuditoria->accion) === 'correcto') {
                                    $notaAutoEliminado = "[Auto-Eliminado: el archivo ya fue procesado previamente por OCR en la tabla 'auditoria_catastro' en ID {$registroPrevioAuditoria->id}{$fechaAuditoria}]";
                                } else {
                                    $notaAutoEliminado = "[Auto-Eliminado: el archivo ya fue trabajado previamente en la tabla 'auditoria_catastro' con acción '{$registroPrevioAuditoria->accion}' en ID {$registroPrevioAuditoria->id}{$fechaAuditoria}]";
                                }
                                $detallesFinal = trim(($detallesVal ? $detallesVal . ' ' : '') . $notaAutoEliminado);
                            }
                        }
                    }

                    if ($accionFinal === 'Auto-Eliminado por Duplicado' && $rutaDestinoVal) {
                        $rutaDestinoVal = str_replace('CALIDAD_CATASTRO', 'ELIMINADOS_CATASTRO', $rutaDestinoVal);
                    }

                    $inspeccion = \App\Services\PdfService::inspeccionarPdf($rutaDestinoVal ?: $rutaOrigenVal);

                    DB::table('auditoria_catastro')->insert([
                        'fecha_hora' => $fechaHora,
                        'turno' => $reg['turno'] ?? null,
                        'user_id' => $userId,
                        'categoria' => $categoriaVal,
                        'tipo' => $tipoVal,
                        'pc' => $reg['pc'] ?? null,
                        'directorio' => $reg['directorio'] ?? null,
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
                        'tiempo_captura' => $reg['tiempo_captura'] ?? $reg['tiempocaptura'] ?? null,
                        'created_at' => $fechaActual,
                        'updated_at' => $fechaActual,
                    ]);
                    $insertados++;
                } catch (\Throwable $e) {
                    $errores[] = "Registro {$index}: Error: " . $e->getMessage();
                }
            }

            return response()->json([
                'ok' => true,
                'mensaje' => "Se registraron {$insertados} registros de catastro (omitidos {$duplicadosOmitidos} duplicados).",
                'procesados' => $insertados,
                'duplicados' => $duplicadosOmitidos,
                'errores' => $errores
            ]);

        } catch (\Throwable $ex) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Error al registrar la auditoría de catastro: ' . $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Resuelve el municipio a partir del nombre del archivo o ruta de destino.
     */
    public static function resolverMunicipio($nombreArchivo, $rutaDestino = null, $mapaMunicipios = [])
    {
        $clave = null;
        $sinExt = pathinfo((string)$nombreArchivo, PATHINFO_FILENAME);
        
        if (preg_match('/^[012]-?(\d{3})/', $sinExt, $m)) {
            $clave = $m[1];
        } elseif ($rutaDestino && preg_match('/(?:CALIDAD_CATASTRO|ELIMINADOS_CATASTRO|INCONSISTENCIAS_CATASTRO)[\\\\\/](\d{3})/i', $rutaDestino, $m)) {
            $clave = $m[1];
        }

        if ($clave) {
            $nombreMun = $mapaMunicipios[$clave] ?? null;
            return $nombreMun ? "{$clave} - {$nombreMun}" : "Municipio {$clave}";
        }

        return 'Sin Municipio';
    }

    /**
     * Calcula la fecha laboral según el turno y hora de corte.
     */
    public static function obtenerFechaLaboral($fechaCompleta, $turno = null)
    {
        if (empty($fechaCompleta) || $fechaCompleta === '1900-01-01 00:00:00' || strlen($fechaCompleta) < 10) {
            return 'Sin Fecha';
        }
        $timestamp = strtotime($fechaCompleta);
        if ($timestamp === false) {
            return 'Sin Fecha';
        }

        $w = (int) date('w', $timestamp);
        $hora = (int) date('H', $timestamp);

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

        if (($w === 6 || $w === 0) && $hora < 9) {
            return date('Y-m-d', strtotime('-1 day', $timestamp));
        }

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

        if ($hora < 9) {
            return date('Y-m-d', strtotime('-1 day', $timestamp));
        }
        return date('Y-m-d', $timestamp);
    }

    /**
     * Resuelve el tipo de catastro (Urbano, Rústico, Sin Estudio) para registros de OCR.
     */
    public static function resolverTipoCatastroOcr($clasificacion, $nombreArchivo)
    {
        if ($clasificacion === '0' || $clasificacion === 0) return 'Urbano';
        if ($clasificacion === '1' || $clasificacion === 1) return 'Rustico';
        if ($clasificacion === '2' || $clasificacion === 2) return 'Sin Estudio';

        if (preg_match('/^0\d{15}/', (string)$nombreArchivo)) return 'Urbano';
        if (preg_match('/^1\d{3}\d{4}\s\d+/', (string)$nombreArchivo) || preg_match('/^1\d{7}\s\d+/', (string)$nombreArchivo)) return 'Rustico';
        if (preg_match('/^2\d{3}\d{4}\s\d+/', (string)$nombreArchivo) || preg_match('/^2\d{7}\s\d+/', (string)$nombreArchivo)) return 'Sin Estudio';

        return 'Urbano';
    }

    /**
     * Vista de Gráficas de Desempeño Catastro.
     */
    public function index_capturas()
    {
        return view('indicadores.catastro_avance');
    }

    /**
     * Vista de Reportes de Catastro por Usuario.
     */
    public function index_reportes_capturas()
    {
        return view('productividad.catastro_usuarios');
    }

    /**
     * Endpoint API para Dashboard de Catastro (Captura Manual vs OCR).
     */
    public function dashboard_catastro(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;
        $tipoCatastro = $request->tipo_catastro;
        $modoOrigen = $request->modo;

        $mapaMunicipios = [];
        if (Schema::hasTable('municipios')) {
            $mapaMunicipios = DB::table('municipios')->pluck('nombre', 'clave')->toArray();
        }

        $query = DB::table('auditoria_catastro as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->select(
                'ac.id',
                'ac.fecha_hora as FechaHora',
                'ac.categoria as Categoria',
                'ac.tipo as Tipo',
                'ac.accion as Accion',
                'ac.archivo_original as ArchivoOriginal',
                'ac.archivo_nuevo as ArchivoNuevo',
                'ac.ruta_origen as RutaOrigen',
                'ac.ruta_destino as RutaDestino',
                'ac.detalles as Detalles',
                'ac.paginas as Paginas',
                'ac.tiempo_captura as TiempoCaptura',
                'ac.pc as PC',
                'ac.directorio as Directorio',
                'ac.lote as Lote',
                'u.id as UserId',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno')
            );

        if (!empty($tipoCatastro)) {
            $query->where('ac.tipo', $tipoCatastro);
        }

        if ($modoOrigen === 'Captura') {
            $query->where('ac.categoria', '!=', 'OCR');
        } elseif ($modoOrigen === 'OCR') {
            $query->where('ac.categoria', 'OCR');
        }

        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('ac.fecha_hora', '>=', $fechaMinima)
                  ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        $kpis = [
            'total_general' => 0,
            'total_captura' => 0,
            'total_ocr' => 0,
            'validados' => 0,
            'renombrados' => 0,
            'duplicados' => 0,
            'inconsistencias' => 0,
            'paginas_total' => 0,
            'paginas_captura' => 0,
            'paginas_ocr' => 0,
            'tiempo_total_captura' => 0,
            'capturas_con_tiempo' => 0,
        ];

        $porDia = [];
        $porMunicipio = [];
        $porTipo = [
            'Urbano' => ['captura' => 0, 'ocr' => 0, 'total' => 0],
            'Rustico' => ['captura' => 0, 'ocr' => 0, 'total' => 0],
            'Sin Estudio' => ['captura' => 0, 'ocr' => 0, 'total' => 0],
            'Otro' => ['captura' => 0, 'ocr' => 0, 'total' => 0],
        ];

        $conteoAccionesGlobal = [
            'Validado' => ['archivos' => 0, 'paginas' => 0],
            'Renombrar' => ['archivos' => 0, 'paginas' => 0],
            'Eliminado' => ['archivos' => 0, 'paginas' => 0],
        ];

        $conteoPorTurnoDetalle = [
            'Matutino' => ['turno' => 'Matutino', 'archivos' => 0, 'imagenes' => 0],
            'Vespertino' => ['turno' => 'Vespertino', 'archivos' => 0, 'imagenes' => 0],
            'Nocturno' => ['turno' => 'Nocturno', 'archivos' => 0, 'imagenes' => 0],
            'Mixto' => ['turno' => 'Mixto', 'archivos' => 0, 'imagenes' => 0],
        ];

        $porTurno = [];
        $porUsuario = [];
        $accionesPorUsuario = [];
        $conteoPorUsuarioTotales = [];
        $conteoPorDelegacion = [];
        $datosResumen = [];
        $ocrPorMunicipio = [];
        $accionesUnicasResumen = ['Validado', 'Renombrar', 'Eliminado'];

        $archivosVistos = [];

        foreach ($query->cursor() as $row) {
            $nombreRef = !empty($row->ArchivoNuevo) ? $row->ArchivoNuevo : $row->ArchivoOriginal;
            if ($nombreRef) {
                $archivosVistos[strtolower(trim((string)$nombreRef))] = true;
            }

            $fechaHora = $row->FechaHora;
            $turno = $row->Turno;
            $esOcr = (strtoupper((string)$row->Categoria) === 'OCR');

            $fecha = self::obtenerFechaLaboral($fechaHora, $turno);
            if ($fecha === 'Sin Fecha') {
                $fecha = date('Y-m-d', strtotime($fechaHora)) ?: 'Sin Fecha';
            }

            if ($inicio && $fin) {
                if ($fecha < $inicio || $fecha > $fin) {
                    continue;
                }
            }

            $paginas = (int)($row->Paginas ?: 1);
            $tiempo = (int)($row->TiempoCaptura ?: 0);
            $accionOriginal = $row->Accion ?: ($esOcr ? 'Correcto' : 'Validado');
            $tipo = $row->Tipo ?: 'Urbano';
            $municipio = self::resolverMunicipio($nombreRef, $row->RutaDestino, $mapaMunicipios);
            $usuario = !empty($row->NombreCompleto) ? $row->NombreCompleto : ($row->Usuario ?: ($esOcr ? 'SISTEMA OCR' : 'Desconocido'));
            $pc = $row->PC ?: 'Sin PC';
            $categoria = $row->Categoria ?: ($esOcr ? 'OCR' : 'Captura');

            // Normalización de acción para reporte y cards (Catastro no tiene duplicados ni recortes)
            $accionNorm = 'Validado';
            $accionLower = strtolower($accionOriginal);
            if (str_contains($accionLower, 'valida')) {
                $accionNorm = 'Validado';
            } elseif (str_contains($accionLower, 'renombr')) {
                $accionNorm = 'Renombrar';
            } elseif (str_contains($accionLower, 'elimina') || str_contains($accionLower, 'duplica')) {
                $accionNorm = 'Eliminado';
            } else {
                $accionNorm = 'Validado';
            }

            // Normalizar turno
            $turnoNorm = 'Matutino';
            if (stripos($turno, 'nocturno') !== false) $turnoNorm = 'Nocturno';
            elseif (stripos($turno, 'vespertino') !== false) $turnoNorm = 'Vespertino';
            elseif (stripos($turno, 'mixto') !== false) $turnoNorm = 'Mixto';

            // Conteo por Delegación / Municipio
            if (!isset($conteoPorDelegacion[$municipio])) {
                $conteoPorDelegacion[$municipio] = [
                    'delegacion' => $municipio,
                    'archivos' => 0,
                    'imagenes' => 0
                ];
            }
            $conteoPorDelegacion[$municipio]['archivos']++;
            $conteoPorDelegacion[$municipio]['imagenes'] += $paginas;

            // KPIs
            $kpis['total_general']++;
            $kpis['paginas_total'] += $paginas;

            if ($esOcr) {
                $kpis['total_ocr']++;
                $kpis['paginas_ocr'] += $paginas;

                if (!isset($ocrPorMunicipio[$municipio])) {
                    $ocrPorMunicipio[$municipio] = [
                        'municipio' => $municipio,
                        'urbano' => 0,
                        'rustico' => 0,
                        'sin_estudio' => 0,
                        'total' => 0,
                        'paginas' => 0,
                    ];
                }
                $ocrPorMunicipio[$municipio]['total']++;
                $ocrPorMunicipio[$municipio]['paginas'] += $paginas;
                if (stripos($tipo, 'rustic') !== false) $ocrPorMunicipio[$municipio]['rustico']++;
                elseif (stripos($tipo, 'estudio') !== false || stripos($tipo, 'sin') !== false) $ocrPorMunicipio[$municipio]['sin_estudio']++;
                else $ocrPorMunicipio[$municipio]['urbano']++;

            } else {
                $kpis['total_captura']++;
                $kpis['paginas_captura'] += $paginas;
                if ($tiempo > 0) {
                    $kpis['tiempo_total_captura'] += $tiempo;
                    $kpis['capturas_con_tiempo']++;
                }

                if ($accionNorm === 'Validado') $kpis['validados']++;
                elseif ($accionNorm === 'Renombrar') $kpis['renombrados']++;
                elseif ($accionNorm === 'Eliminado') $kpis['duplicados']++;

                if (!isset($conteoAccionesGlobal[$accionNorm])) {
                    $conteoAccionesGlobal[$accionNorm] = ['archivos' => 0, 'paginas' => 0];
                }
                $conteoAccionesGlobal[$accionNorm]['archivos']++;
                $conteoAccionesGlobal[$accionNorm]['paginas'] += $paginas;

                if (!isset($conteoPorTurnoDetalle[$turnoNorm])) {
                    $conteoPorTurnoDetalle[$turnoNorm] = ['turno' => $turnoNorm, 'archivos' => 0, 'imagenes' => 0];
                }
                $conteoPorTurnoDetalle[$turnoNorm]['archivos']++;
                $conteoPorTurnoDetalle[$turnoNorm]['imagenes'] += $paginas;

                if (!isset($porTurno[$turnoNorm])) {
                    $porTurno[$turnoNorm] = ['total' => 0, 'paginas' => 0];
                }
                $porTurno[$turnoNorm]['total']++;
                $porTurno[$turnoNorm]['paginas'] += $paginas;

                // Conteo por usuario
                if (!isset($conteoPorUsuarioTotales[$usuario])) {
                    $conteoPorUsuarioTotales[$usuario] = 0;
                }
                $conteoPorUsuarioTotales[$usuario]++;

                if (!isset($accionesPorUsuario[$usuario])) {
                    $accionesPorUsuario[$usuario] = ['total' => 0, 'acciones' => []];
                }
                $accionesPorUsuario[$usuario]['total']++;
                if (!isset($accionesPorUsuario[$usuario]['acciones'][$accionNorm])) {
                    $accionesPorUsuario[$usuario]['acciones'][$accionNorm] = 0;
                }
                $accionesPorUsuario[$usuario]['acciones'][$accionNorm]++;

                // Tabla resumen estilo Excel ($fecha|$usuario)
                $keyFila = "{$fecha}|{$usuario}";
                if (!isset($datosResumen[$keyFila])) {
                    $datosResumen[$keyFila] = [
                        'Fecha' => $fecha,
                        'PC' => $pc,
                        'PCs' => [$pc],
                        'Usuario' => $usuario,
                        'Turno' => $turnoNorm,
                        'Turnos' => [$turnoNorm],
                        'Categoria' => $categoria,
                        'Acciones' => [
                            'Validado' => 0,
                            'Renombrar' => 0,
                            'Eliminado' => 0,
                        ],
                        'TotalArchivos' => 0,
                        'TotalPaginas' => 0,
                        'TotalCatastro' => 0,
                        'TotalRegistros' => 0,
                        'TotalAmbas' => 0,
                    ];
                } else {
                    if (!in_array($pc, $datosResumen[$keyFila]['PCs'], true)) {
                        $datosResumen[$keyFila]['PCs'][] = $pc;
                        $datosResumen[$keyFila]['PC'] = implode(', ', $datosResumen[$keyFila]['PCs']);
                    }
                }

                if (!isset($datosResumen[$keyFila]['Acciones'][$accionNorm])) {
                    $datosResumen[$keyFila]['Acciones'][$accionNorm] = 0;
                }
                $datosResumen[$keyFila]['Acciones'][$accionNorm]++;
                $datosResumen[$keyFila]['TotalArchivos']++;
                $datosResumen[$keyFila]['TotalCatastro']++;
                $datosResumen[$keyFila]['TotalAmbas']++;
                $datosResumen[$keyFila]['TotalPaginas'] += $paginas;
            }

            // Por Día
            if (!isset($porDia[$fecha])) {
                $porDia[$fecha] = [
                    'fecha' => $fecha,
                    'captura' => 0,
                    'ocr' => 0,
                    'total' => 0,
                    'captura_paginas' => 0,
                    'ocr_paginas' => 0,
                    'total_paginas' => 0,
                ];
            }
            $porDia[$fecha]['total']++;
            $porDia[$fecha]['total_paginas'] += $paginas;
            if ($esOcr) {
                $porDia[$fecha]['ocr']++;
                $porDia[$fecha]['ocr_paginas'] += $paginas;
            } else {
                $porDia[$fecha]['captura']++;
                $porDia[$fecha]['captura_paginas'] += $paginas;
            }

            // Por Municipio
            if (!isset($porMunicipio[$municipio])) {
                $porMunicipio[$municipio] = [
                    'municipio' => $municipio,
                    'captura' => 0,
                    'ocr' => 0,
                    'total' => 0,
                    'captura_paginas' => 0,
                    'ocr_paginas' => 0,
                    'paginas' => 0,
                ];
            }
            $porMunicipio[$municipio]['total']++;
            $porMunicipio[$municipio]['paginas'] += $paginas;
            if ($esOcr) {
                $porMunicipio[$municipio]['ocr']++;
                $porMunicipio[$municipio]['ocr_paginas'] += $paginas;
            } else {
                $porMunicipio[$municipio]['captura']++;
                $porMunicipio[$municipio]['captura_paginas'] += $paginas;
            }

            // Por Tipo
            $claveTipo = 'Otro';
            if (stripos($tipo, 'urban') !== false) $claveTipo = 'Urbano';
            elseif (stripos($tipo, 'rustic') !== false) $claveTipo = 'Rustico';
            elseif (stripos($tipo, 'estudio') !== false || stripos($tipo, 'sin') !== false) $claveTipo = 'Sin Estudio';

            if (!isset($porTipo[$claveTipo])) {
                $porTipo[$claveTipo] = ['captura' => 0, 'ocr' => 0, 'total' => 0];
            }
            $porTipo[$claveTipo]['total']++;
            if ($esOcr) {
                $porTipo[$claveTipo]['ocr']++;
            } else {
                $porTipo[$claveTipo]['captura']++;
            }
        }

        // Incorporar registros directos de las bitácoras de OCR si el filtro no es solo "Captura"
        if ($modoOrigen !== 'Captura') {
            $tablasBitacoraOcr = ['bitacoracatastro3000_pruebas', 'bitacoracatastro3000', 'bitacoracatastro3000_2do', 'bitacoracatastro3000_real'];
            foreach ($tablasBitacoraOcr as $tablaB) {
                if (!Schema::hasTable($tablaB)) continue;

                $queryB = DB::table($tablaB)
                    ->where('tipo_archivo', 'Correcto')
                    ->whereNotNull('nombre_nuevo');

                if ($inicio && $fin) {
                    $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
                    $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
                    $queryB->where('fecha', '>=', $fechaMinima)
                           ->where('fecha', '<=', $fechaMaxima);
                }

                foreach ($queryB->cursor() as $rowB) {
                    $clave = strtolower(trim((string)$rowB->nombre_nuevo));
                    if ($clave === '' || isset($archivosVistos[$clave])) {
                        continue;
                    }
                    $archivosVistos[$clave] = true;

                    $fechaHora = $rowB->fecha;
                    $turno = 'Matutino';
                    $fecha = self::obtenerFechaLaboral($fechaHora, $turno);
                    if ($fecha === 'Sin Fecha') {
                        $fecha = date('Y-m-d', strtotime($fechaHora)) ?: 'Sin Fecha';
                    }

                    if ($inicio && $fin) {
                        if ($fecha < $inicio || $fecha > $fin) {
                            continue;
                        }
                    }

                    $tipo = self::resolverTipoCatastroOcr($rowB->clasificacion, $rowB->nombre_nuevo);
                    if (!empty($tipoCatastro) && $tipo !== $tipoCatastro) {
                        continue;
                    }

                    $paginas = (int)($rowB->paginas ?: 1);
                    $nombreRef = $rowB->nombre_nuevo;
                    $municipio = !empty($rowB->municipio) ? (string)$rowB->municipio : self::resolverMunicipio($nombreRef, $rowB->carpeta_destino, $mapaMunicipios);
                    if (isset($mapaMunicipios[$municipio])) {
                        $municipio = "{$municipio} - " . $mapaMunicipios[$municipio];
                    }

                    // Conteo por Delegación / Municipio
                    if (!isset($conteoPorDelegacion[$municipio])) {
                        $conteoPorDelegacion[$municipio] = [
                            'delegacion' => $municipio,
                            'archivos' => 0,
                            'imagenes' => 0
                        ];
                    }
                    $conteoPorDelegacion[$municipio]['archivos']++;
                    $conteoPorDelegacion[$municipio]['imagenes'] += $paginas;

                    // KPIs
                    $kpis['total_general']++;
                    $kpis['paginas_total'] += $paginas;
                    $kpis['total_ocr']++;
                    $kpis['paginas_ocr'] += $paginas;

                    if (!isset($ocrPorMunicipio[$municipio])) {
                        $ocrPorMunicipio[$municipio] = [
                            'municipio' => $municipio,
                            'urbano' => 0,
                            'rustico' => 0,
                            'sin_estudio' => 0,
                            'total' => 0,
                            'paginas' => 0,
                        ];
                    }
                    $ocrPorMunicipio[$municipio]['total']++;
                    $ocrPorMunicipio[$municipio]['paginas'] += $paginas;
                    if ($tipo === 'Rustico') $ocrPorMunicipio[$municipio]['rustico']++;
                    elseif ($tipo === 'Sin Estudio') $ocrPorMunicipio[$municipio]['sin_estudio']++;
                    else $ocrPorMunicipio[$municipio]['urbano']++;

                    // Por Día
                    if (!isset($porDia[$fecha])) {
                        $porDia[$fecha] = [
                            'fecha' => $fecha,
                            'captura' => 0,
                            'ocr' => 0,
                            'total' => 0,
                            'captura_paginas' => 0,
                            'ocr_paginas' => 0,
                            'total_paginas' => 0,
                        ];
                    }
                    $porDia[$fecha]['total']++;
                    $porDia[$fecha]['total_paginas'] += $paginas;
                    $porDia[$fecha]['ocr']++;
                    $porDia[$fecha]['ocr_paginas'] += $paginas;

                    // Por Municipio
                    if (!isset($porMunicipio[$municipio])) {
                        $porMunicipio[$municipio] = [
                            'municipio' => $municipio,
                            'captura' => 0,
                            'ocr' => 0,
                            'total' => 0,
                            'captura_paginas' => 0,
                            'ocr_paginas' => 0,
                            'paginas' => 0,
                        ];
                    }
                    $porMunicipio[$municipio]['total']++;
                    $porMunicipio[$municipio]['paginas'] += $paginas;
                    $porMunicipio[$municipio]['ocr']++;
                    $porMunicipio[$municipio]['ocr_paginas'] += $paginas;

                    // Por Tipo
                    $claveTipo = in_array($tipo, ['Urbano', 'Rustico', 'Sin Estudio']) ? $tipo : 'Otro';
                    if (!isset($porTipo[$claveTipo])) {
                        $porTipo[$claveTipo] = ['captura' => 0, 'ocr' => 0, 'total' => 0];
                    }
                    $porTipo[$claveTipo]['total']++;
                    $porTipo[$claveTipo]['ocr']++;
                }
            }
        }

        ksort($porDia);
        $porDiaLista = array_values($porDia);

        uasort($porMunicipio, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        $porMunicipioLista = array_values($porMunicipio);

        uasort($ocrPorMunicipio, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        $ocrPorMunicipioLista = array_values($ocrPorMunicipio);

        uasort($conteoPorDelegacion, function ($a, $b) {
            return $b['archivos'] <=> $a['archivos'];
        });
        $porDelegacionLista = array_values($conteoPorDelegacion);

        // Lista de acciones global para tarjetas KPI
        $accionesGlobal = [];
        foreach ($conteoAccionesGlobal as $acc => $val) {
            $accionesGlobal[] = [
                'accion' => $acc,
                'total' => $val['archivos'],
                'paginas' => $val['paginas']
            ];
        }

        // Por usuario para gráfica de barras
        arsort($conteoPorUsuarioTotales);
        $porUsuarioLista = [];
        foreach ($conteoPorUsuarioTotales as $usu => $tot) {
            $porUsuarioLista[] = [
                'usuario' => $usu,
                'total' => $tot
            ];
        }

        $usuariosUnicos = array_keys($conteoPorUsuarioTotales);

        // Tabla resumen array
        $tablaResumenLista = array_values($datosResumen);
        usort($tablaResumenLista, function ($a, $b) {
            return strcmp($b['Fecha'], $a['Fecha']);
        });

        return response()->json([
            'ok' => true,
            'kpis' => $kpis,
            'por_dia' => $porDiaLista,
            'paginas_por_dia' => $porDiaLista,
            'por_municipio' => $porMunicipioLista,
            'ocr_por_municipio' => $ocrPorMunicipioLista,
            'por_delegacion' => $porDelegacionLista,
            'por_tipo' => $porTipo,
            'acciones_captura' => $accionesGlobal,
            'acciones_global' => $accionesGlobal,
            'por_turno' => $porTurno,
            'por_turno_detalle' => array_values($conteoPorTurnoDetalle),
            'por_usuario' => $porUsuarioLista,
            'usuarios_unicos' => $usuariosUnicos,
            'acciones_por_usuario' => $accionesPorUsuario,
            'tabla_resumen' => $tablaResumenLista,
            'acciones_resumen' => $accionesUnicasResumen,
        ]);
    }

    /**
     * Descarga de reporte detallado de Catastro en Excel.
     */
    public function descargar_excel(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;
        $tipoCatastro = $request->tipo_catastro;

        $mapaMunicipios = [];
        if (Schema::hasTable('municipios')) {
            $mapaMunicipios = DB::table('municipios')->pluck('nombre', 'clave')->toArray();
        }

        $query = DB::table('auditoria_catastro as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->select(
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.categoria as Categoria',
                'ac.tipo as Tipo',
                'ac.accion as Accion',
                'ac.archivo_original as ArchivoOriginal',
                'ac.archivo_nuevo as ArchivoNuevo',
                'ac.paginas as Paginas',
                'ac.tiempo_captura as TiempoCaptura',
                'ac.pc as PC',
                'ac.directorio as Directorio',
                'ac.lote as Lote',
                'ac.ruta_destino as RutaDestino',
                'ac.detalles as Detalles'
            );

        if (!empty($tipoCatastro)) {
            $query->where('ac.tipo', $tipoCatastro);
        }

        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('ac.fecha_hora', '>=', $fechaMinima)
                  ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Detalle Catastro');

        $headers = [
            'Fecha y Hora', 'Fecha Laboral', 'Origen/Modo', 'Tipo Catastro', 'Municipio',
            'Usuario', 'Nombre Completo', 'Turno', 'Acción', 'Archivo Original',
            'Archivo Nuevo', 'Páginas', 'Tiempo (seg)', 'PC', 'Lote', 'Detalles'
        ];

        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $col++;
        }

        $archivosVistos = [];
        $rowNum = 2;
        foreach ($query->cursor() as $r) {
            $fechaLaboral = self::obtenerFechaLaboral($r->FechaHora, $r->Turno);
            if ($inicio && $fin) {
                if ($fechaLaboral < $inicio || $fechaLaboral > $fin) continue;
            }

            $nombreRef = !empty($r->ArchivoNuevo) ? $r->ArchivoNuevo : $r->ArchivoOriginal;
            if ($nombreRef) {
                $archivosVistos[strtolower(trim((string)$nombreRef))] = true;
            }
            $municipio = self::resolverMunicipio($nombreRef, $r->RutaDestino, $mapaMunicipios);
            $esOcr = (strtoupper((string)$r->Categoria) === 'OCR');

            $sheet->setCellValueByColumnAndRow(1, $rowNum, $r->FechaHora);
            $sheet->setCellValueByColumnAndRow(2, $rowNum, $fechaLaboral);
            $sheet->setCellValueByColumnAndRow(3, $rowNum, $esOcr ? 'OCR' : 'Captura Manual');
            $sheet->setCellValueByColumnAndRow(4, $rowNum, $r->Tipo ?: 'Urbano');
            $sheet->setCellValueByColumnAndRow(5, $rowNum, $municipio);
            $sheet->setCellValueByColumnAndRow(6, $rowNum, $esOcr ? 'SISTEMA OCR' : ($r->Usuario ?: 'N/A'));
            $sheet->setCellValueByColumnAndRow(7, $rowNum, $esOcr ? 'PROCESAMIENTO OCR' : ($r->NombreCompleto ?: 'N/A'));
            $sheet->setCellValueByColumnAndRow(8, $rowNum, $esOcr ? '-' : $r->Turno);
            $sheet->setCellValueByColumnAndRow(9, $rowNum, $r->Accion);
            $sheet->setCellValueByColumnAndRow(10, $rowNum, $r->ArchivoOriginal);
            $sheet->setCellValueByColumnAndRow(11, $rowNum, $r->ArchivoNuevo);
            $sheet->setCellValueByColumnAndRow(12, $rowNum, (int)$r->Paginas);
            $sheet->setCellValueByColumnAndRow(13, $rowNum, (int)$r->TiempoCaptura);
            $sheet->setCellValueByColumnAndRow(14, $rowNum, $r->PC);
            $sheet->setCellValueByColumnAndRow(15, $rowNum, $r->Lote);
            $sheet->setCellValueByColumnAndRow(16, $rowNum, $r->Detalles);

            $rowNum++;
        }

        // Incorporar registros directos de bitácoras OCR
        $tablasBitacoraOcr = ['bitacoracatastro3000_pruebas', 'bitacoracatastro3000', 'bitacoracatastro3000_2do', 'bitacoracatastro3000_real'];
        foreach ($tablasBitacoraOcr as $tablaB) {
            if (!Schema::hasTable($tablaB)) continue;

            $queryB = DB::table($tablaB)
                ->where('tipo_archivo', 'Correcto')
                ->whereNotNull('nombre_nuevo');

            if ($inicio && $fin) {
                $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
                $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
                $queryB->where('fecha', '>=', $fechaMinima)
                       ->where('fecha', '<=', $fechaMaxima);
            }

            foreach ($queryB->cursor() as $rowB) {
                $clave = strtolower(trim((string)$rowB->nombre_nuevo));
                if ($clave === '' || isset($archivosVistos[$clave])) continue;
                $archivosVistos[$clave] = true;

                $fechaHora = $rowB->fecha;
                $fechaLaboral = self::obtenerFechaLaboral($fechaHora, 'Matutino');
                if ($inicio && $fin) {
                    if ($fechaLaboral < $inicio || $fechaLaboral > $fin) continue;
                }

                $tipo = self::resolverTipoCatastroOcr($rowB->clasificacion, $rowB->nombre_nuevo);
                if (!empty($tipoCatastro) && $tipo !== $tipoCatastro) continue;

                $nombreRef = $rowB->nombre_nuevo;
                $municipio = !empty($rowB->municipio) ? (string)$rowB->municipio : self::resolverMunicipio($nombreRef, $rowB->carpeta_destino, $mapaMunicipios);
                if (isset($mapaMunicipios[$municipio])) {
                    $municipio = "{$municipio} - " . $mapaMunicipios[$municipio];
                }

                $sheet->setCellValueByColumnAndRow(1, $rowNum, $fechaHora);
                $sheet->setCellValueByColumnAndRow(2, $rowNum, $fechaLaboral);
                $sheet->setCellValueByColumnAndRow(3, $rowNum, 'OCR');
                $sheet->setCellValueByColumnAndRow(4, $rowNum, $tipo);
                $sheet->setCellValueByColumnAndRow(5, $rowNum, $municipio);
                $sheet->setCellValueByColumnAndRow(6, $rowNum, 'SISTEMA OCR');
                $sheet->setCellValueByColumnAndRow(7, $rowNum, 'PROCESAMIENTO OCR');
                $sheet->setCellValueByColumnAndRow(8, $rowNum, '-');
                $sheet->setCellValueByColumnAndRow(9, $rowNum, 'Correcto');
                $sheet->setCellValueByColumnAndRow(10, $rowNum, $rowB->nombre_original);
                $sheet->setCellValueByColumnAndRow(11, $rowNum, $rowB->nombre_nuevo);
                $sheet->setCellValueByColumnAndRow(12, $rowNum, (int)($rowB->paginas ?: 1));
                $sheet->setCellValueByColumnAndRow(13, $rowNum, 0);
                $sheet->setCellValueByColumnAndRow(14, $rowNum, 'SERVIDOR OCR');
                $sheet->setCellValueByColumnAndRow(15, $rowNum, $tablaB);
                $sheet->setCellValueByColumnAndRow(16, $rowNum, 'Procesado automáticamente por OCR');

                $rowNum++;
            }
        }

        // Estilo cabecera
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('A1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4F46E5');
        $sheet->getStyle('A1:P1')->getFont()->getColor()->setARGB('FFFFFFFF');

        foreach (range(1, 16) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $nombreArchivo = 'Reporte_Detallado_Catastro_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Descarga de resumen por municipio en Excel.
     */
    public function descargar_excel_municipios(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;

        $mapaMunicipios = [];
        if (Schema::hasTable('municipios')) {
            $mapaMunicipios = DB::table('municipios')->pluck('nombre', 'clave')->toArray();
        }

        $query = DB::table('auditoria_catastro')
            ->select('archivo_original', 'archivo_nuevo', 'ruta_destino', 'categoria', 'tipo', 'paginas', 'fecha_hora', 'turno');

        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('fecha_hora', '>=', $fechaMinima)
                  ->where('fecha_hora', '<=', $fechaMaxima);
        }

        $porMunicipio = [];
        $archivosVistos = [];

        foreach ($query->cursor() as $r) {
            $fecha = self::obtenerFechaLaboral($r->fecha_hora, $r->turno);
            if ($inicio && $fin && ($fecha < $inicio || $fecha > $fin)) continue;

            $nombreRef = !empty($r->archivo_nuevo) ? $r->archivo_nuevo : $r->archivo_original;
            if ($nombreRef) {
                $archivosVistos[strtolower(trim((string)$nombreRef))] = true;
            }
            $mun = self::resolverMunicipio($nombreRef, $r->ruta_destino, $mapaMunicipios);
            $esOcr = (strtoupper((string)$r->categoria) === 'OCR');
            $pags = (int)($r->paginas ?: 1);

            if (!isset($porMunicipio[$mun])) {
                $porMunicipio[$mun] = [
                    'municipio' => $mun,
                    'captura' => 0,
                    'ocr' => 0,
                    'total' => 0,
                    'paginas' => 0,
                ];
            }
            $porMunicipio[$mun]['total']++;
            $porMunicipio[$mun]['paginas'] += $pags;
            if ($esOcr) $porMunicipio[$mun]['ocr']++;
            else $porMunicipio[$mun]['captura']++;
        }

        // Directo de bitácoras OCR
        $tablasBitacoraOcr = ['bitacoracatastro3000_pruebas', 'bitacoracatastro3000', 'bitacoracatastro3000_2do', 'bitacoracatastro3000_real'];
        foreach ($tablasBitacoraOcr as $tablaB) {
            if (!Schema::hasTable($tablaB)) continue;

            $queryB = DB::table($tablaB)
                ->where('tipo_archivo', 'Correcto')
                ->whereNotNull('nombre_nuevo');

            if ($inicio && $fin) {
                $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
                $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
                $queryB->where('fecha', '>=', $fechaMinima)
                       ->where('fecha', '<=', $fechaMaxima);
            }

            foreach ($queryB->cursor() as $rowB) {
                $clave = strtolower(trim((string)$rowB->nombre_nuevo));
                if ($clave === '' || isset($archivosVistos[$clave])) continue;
                $archivosVistos[$clave] = true;

                $fecha = self::obtenerFechaLaboral($rowB->fecha, 'Matutino');
                if ($inicio && $fin && ($fecha < $inicio || $fecha > $fin)) continue;

                $nombreRef = $rowB->nombre_nuevo;
                $mun = !empty($rowB->municipio) ? (string)$rowB->municipio : self::resolverMunicipio($nombreRef, $rowB->carpeta_destino, $mapaMunicipios);
                if (isset($mapaMunicipios[$mun])) {
                    $mun = "{$mun} - " . $mapaMunicipios[$mun];
                }
                $pags = (int)($rowB->paginas ?: 1);

                if (!isset($porMunicipio[$mun])) {
                    $porMunicipio[$mun] = [
                        'municipio' => $mun,
                        'captura' => 0,
                        'ocr' => 0,
                        'total' => 0,
                        'paginas' => 0,
                    ];
                }
                $porMunicipio[$mun]['total']++;
                $porMunicipio[$mun]['paginas'] += $pags;
                $porMunicipio[$mun]['ocr']++;
            }
        }

        uasort($porMunicipio, function($a, $b) { return $b['total'] <=> $a['total']; });

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Por Municipios');

        $headers = ['Municipio', 'Captura Manual (Archivos)', 'OCR (Archivos)', 'Total Archivos', 'Total Páginas'];
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $col++;
        }

        $rowNum = 2;
        foreach ($porMunicipio as $m) {
            $sheet->setCellValueByColumnAndRow(1, $rowNum, $m['municipio']);
            $sheet->setCellValueByColumnAndRow(2, $rowNum, $m['captura']);
            $sheet->setCellValueByColumnAndRow(3, $rowNum, $m['ocr']);
            $sheet->setCellValueByColumnAndRow(4, $rowNum, $m['total']);
            $sheet->setCellValueByColumnAndRow(5, $rowNum, $m['paginas']);
            $rowNum++;
        }

        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF218838');
        $sheet->getStyle('A1:E1')->getFont()->getColor()->setARGB('FFFFFFFF');

        foreach (range(1, 5) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $nombreArchivo = 'Reporte_Catastro_Municipios_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Descarga de resumen por día en Excel.
     */
    public function descargar_excel_dias(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;

        $query = DB::table('auditoria_catastro')
            ->select('archivo_nuevo', 'archivo_original', 'categoria', 'paginas', 'fecha_hora', 'turno');

        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('fecha_hora', '>=', $fechaMinima)
                  ->where('fecha_hora', '<=', $fechaMaxima);
        }

        $porDia = [];
        $archivosVistos = [];

        foreach ($query->cursor() as $r) {
            $fecha = self::obtenerFechaLaboral($r->fecha_hora, $r->turno);
            if ($inicio && $fin && ($fecha < $inicio || $fecha > $fin)) continue;

            $nombreRef = !empty($r->archivo_nuevo) ? $r->archivo_nuevo : $r->archivo_original;
            if ($nombreRef) {
                $archivosVistos[strtolower(trim((string)$nombreRef))] = true;
            }

            $esOcr = (strtoupper((string)$r->categoria) === 'OCR');
            $pags = (int)($r->paginas ?: 1);

            if (!isset($porDia[$fecha])) {
                $porDia[$fecha] = [
                    'fecha' => $fecha,
                    'captura_archivos' => 0,
                    'ocr_archivos' => 0,
                    'total_archivos' => 0,
                    'captura_paginas' => 0,
                    'ocr_paginas' => 0,
                    'total_paginas' => 0,
                ];
            }
            $porDia[$fecha]['total_archivos']++;
            $porDia[$fecha]['total_paginas'] += $pags;
            if ($esOcr) {
                $porDia[$fecha]['ocr_archivos']++;
                $porDia[$fecha]['ocr_paginas'] += $pags;
            } else {
                $porDia[$fecha]['captura_archivos']++;
                $porDia[$fecha]['captura_paginas'] += $pags;
            }
        }

        // Directo de bitácoras OCR
        $tablasBitacoraOcr = ['bitacoracatastro3000_pruebas', 'bitacoracatastro3000', 'bitacoracatastro3000_2do', 'bitacoracatastro3000_real'];
        foreach ($tablasBitacoraOcr as $tablaB) {
            if (!Schema::hasTable($tablaB)) continue;

            $queryB = DB::table($tablaB)
                ->where('tipo_archivo', 'Correcto')
                ->whereNotNull('nombre_nuevo');

            if ($inicio && $fin) {
                $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
                $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
                $queryB->where('fecha', '>=', $fechaMinima)
                       ->where('fecha', '<=', $fechaMaxima);
            }

            foreach ($queryB->cursor() as $rowB) {
                $clave = strtolower(trim((string)$rowB->nombre_nuevo));
                if ($clave === '' || isset($archivosVistos[$clave])) continue;
                $archivosVistos[$clave] = true;

                $fecha = self::obtenerFechaLaboral($rowB->fecha, 'Matutino');
                if ($inicio && $fin && ($fecha < $inicio || $fecha > $fin)) continue;

                $pags = (int)($rowB->paginas ?: 1);

                if (!isset($porDia[$fecha])) {
                    $porDia[$fecha] = [
                        'fecha' => $fecha,
                        'captura_archivos' => 0,
                        'ocr_archivos' => 0,
                        'total_archivos' => 0,
                        'captura_paginas' => 0,
                        'ocr_paginas' => 0,
                        'total_paginas' => 0,
                    ];
                }
                $porDia[$fecha]['total_archivos']++;
                $porDia[$fecha]['total_paginas'] += $pags;
                $porDia[$fecha]['ocr_archivos']++;
                $porDia[$fecha]['ocr_paginas'] += $pags;
            }
        }

        ksort($porDia);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Por Día');

        $headers = ['Fecha Laboral', 'Captura Manual (Archivos)', 'Captura Manual (Páginas)', 'OCR (Archivos)', 'OCR (Páginas)', 'Total Archivos', 'Total Páginas'];
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $col++;
        }

        $rowNum = 2;
        foreach ($porDia as $d) {
            $sheet->setCellValueByColumnAndRow(1, $rowNum, $d['fecha']);
            $sheet->setCellValueByColumnAndRow(2, $rowNum, $d['captura_archivos']);
            $sheet->setCellValueByColumnAndRow(3, $rowNum, $d['captura_paginas']);
            $sheet->setCellValueByColumnAndRow(4, $rowNum, $d['ocr_archivos']);
            $sheet->setCellValueByColumnAndRow(5, $rowNum, $d['ocr_paginas']);
            $sheet->setCellValueByColumnAndRow(6, $rowNum, $d['total_archivos']);
            $sheet->setCellValueByColumnAndRow(7, $rowNum, $d['total_paginas']);
            $rowNum++;
        }

        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E7E34');
        $sheet->getStyle('A1:G1')->getFont()->getColor()->setARGB('FFFFFFFF');

        foreach (range(1, 7) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $nombreArchivo = 'Reporte_Catastro_Por_Dia_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Descarga de resumen por usuario (Capturistas) en Excel.
     */
    public function descargar_excel_resumen(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;

        $query = DB::table('auditoria_catastro as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->select(
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                'ac.paginas as Paginas',
                'ac.tiempo_captura as TiempoCaptura',
                'ac.categoria as Categoria'
            )
            ->where(function ($q) {
                $q->whereNull('ac.categoria')->orWhere('ac.categoria', '!=', 'OCR');
            });

        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('ac.fecha_hora', '>=', $fechaMinima)
                  ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        $porUsuario = [];
        foreach ($query->cursor() as $r) {
            $fecha = self::obtenerFechaLaboral($r->FechaHora, $r->Turno);
            if ($inicio && $fin && ($fecha < $inicio || $fecha > $fin)) continue;

            $nom = !empty($r->NombreCompleto) ? $r->NombreCompleto : ($r->Usuario ?: 'Desconocido');
            $userKey = $nom;
            $pags = (int)($r->Paginas ?: 1);
            $tiempo = (int)($r->TiempoCaptura ?: 0);
            $acc = strtolower((string)$r->Accion);

            if (!isset($porUsuario[$userKey])) {
                $porUsuario[$userKey] = [
                    'usuario' => $r->Usuario ?: 'N/A',
                    'nombre' => $nom,
                    'turno' => $r->Turno,
                    'validados' => 0,
                    'renombrados' => 0,
                    'duplicados' => 0,
                    'inconsistencias' => 0,
                    'total_archivos' => 0,
                    'total_paginas' => 0,
                    'tiempo_total' => 0,
                    'capturas_tiempo' => 0,
                ];
            }
            $porUsuario[$userKey]['total_archivos']++;
            $porUsuario[$userKey]['total_paginas'] += $pags;
            if ($tiempo > 0) {
                $porUsuario[$userKey]['tiempo_total'] += $tiempo;
                $porUsuario[$userKey]['capturas_tiempo']++;
            }

            if (str_contains($acc, 'valida')) $porUsuario[$userKey]['validados']++;
            elseif (str_contains($acc, 'renombr')) $porUsuario[$userKey]['renombrados']++;
            elseif (str_contains($acc, 'duplica') || str_contains($acc, 'elimina')) $porUsuario[$userKey]['duplicados']++;
            elseif (str_contains($acc, 'inconsisten')) $porUsuario[$userKey]['inconsistencias']++;
        }

        usort($porUsuario, function($a, $b) { return $b['total_archivos'] <=> $a['total_archivos']; });

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen Usuarios');

        $headers = ['Usuario', 'Nombre Completo', 'Turno', 'Validados', 'Renombrados', 'Duplicados', 'Inconsistencias', 'Total Archivos', 'Total Páginas', 'Tiempo Promedio'];
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $col++;
        }

        $rowNum = 2;
        foreach ($porUsuario as $u) {
            $promSeg = $u['capturas_tiempo'] > 0 ? round($u['tiempo_total'] / $u['capturas_tiempo']) : 0;
            $tiempoFormato = sprintf('%02d:%02d', floor($promSeg / 60), $promSeg % 60);

            $sheet->setCellValueByColumnAndRow(1, $rowNum, $u['usuario']);
            $sheet->setCellValueByColumnAndRow(2, $rowNum, $u['nombre']);
            $sheet->setCellValueByColumnAndRow(3, $rowNum, $u['turno']);
            $sheet->setCellValueByColumnAndRow(4, $rowNum, $u['validados']);
            $sheet->setCellValueByColumnAndRow(5, $rowNum, $u['renombrados']);
            $sheet->setCellValueByColumnAndRow(6, $rowNum, $u['duplicados']);
            $sheet->setCellValueByColumnAndRow(7, $rowNum, $u['inconsistencias']);
            $sheet->setCellValueByColumnAndRow(8, $rowNum, $u['total_archivos']);
            $sheet->setCellValueByColumnAndRow(9, $rowNum, $u['total_paginas']);
            $sheet->setCellValueByColumnAndRow(10, $rowNum, $tiempoFormato);
            $rowNum++;
        }

        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF007BFF');
        $sheet->getStyle('A1:J1')->getFont()->getColor()->setARGB('FFFFFFFF');

        foreach (range(1, 10) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $nombreArchivo = 'Reporte_Catastro_Usuarios_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Vista de Detalles de Renombre de Catastro agrupada por día laboral y usuario.
     */
    public function detalles_renombre(Request $request)
    {
        $inicio = $request->inicio ?: date('Y-m-01');
        $fin = $request->fin ?: date('Y-m-t');
        $tipoCatastro = $request->tipo_catastro;
        $userId = $request->user_id;
        $search = $request->search;

        $usuarios = DB::table('auditoria_catastro as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->select('u.id', 'u.name', 'u.user')
            ->whereNotNull('ac.user_id')
            ->where(function ($q) {
                $q->whereRaw('LOWER(ac.accion) LIKE ?', ['%renombrar%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%renombrado%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validado%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validar%']);
            })
            ->distinct()
            ->orderBy('u.name', 'asc')
            ->get();

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

        $query = DB::table('auditoria_catastro as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->select(
                'ac.id as Id',
                DB::raw('COALESCE(ac.ruta_destino, ac.ruta_origen) as Ruta'),
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                'ac.archivo_original as ArchivoOriginal',
                'ac.archivo_nuevo as ArchivoNuevo',
                'ac.tipo as Tipo',
                'ac.categoria as Categoria',
                DB::raw("{$fechaLaboralSql} as FechaLaboral")
            );

        $query->where(function ($q) {
            $q->whereRaw('LOWER(ac.accion) LIKE ?', ['%renombrar%'])
                ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%renombrado%'])
                ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validado%'])
                ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validar%']);
        });

        if ($userId) {
            $query->where('ac.user_id', $userId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ac.archivo_original) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(ac.archivo_nuevo) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        if (empty($search) && $inicio && $fin) {
            $query->whereRaw("({$fechaLaboralSql}) >= ?", [$inicio])
                ->whereRaw("({$fechaLaboralSql}) <= ?", [$fin]);
        }

        if ($tipoCatastro) {
            $query->where('ac.tipo', $tipoCatastro);
        }

        $diferenciaDias = 999;
        if ($inicio && $fin) {
            $diffSeconds = strtotime($fin) - strtotime($inicio);
            $diferenciaDias = round($diffSeconds / 86400);
        }

        if ($diferenciaDias <= 3) {
            $registrosDb = $query->orderBy('ac.fecha_hora', 'desc')->get();
            $registrosPaginados = null;
        } else {
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

            $queryFechas = DB::table('auditoria_catastro as ac')
                ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
                ->select(
                    DB::raw("DISTINCT {$fechaLaboralSql} as FechaLaboral"),
                    DB::raw("({$normalizarTurnoSql}) as TurnoGrupo")
                );

            $queryFechas->where(function ($q) {
                $q->whereRaw('LOWER(ac.accion) LIKE ?', ['%renombrar%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%renombrado%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validado%'])
                    ->orWhereRaw('LOWER(ac.accion) LIKE ?', ['%validar%']);
            });

            if ($userId) {
                $queryFechas->where('ac.user_id', $userId);
            }

            if (!empty($search)) {
                $queryFechas->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(ac.archivo_original) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(ac.archivo_nuevo) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            }

            if (empty($search) && $inicio && $fin) {
                $queryFechas->whereRaw("({$fechaLaboralSql}) >= ?", [$inicio])
                    ->whereRaw("({$fechaLaboralSql}) <= ?", [$fin]);
            }

            if ($tipoCatastro) {
                $queryFechas->where('ac.tipo', $tipoCatastro);
            }

            $queryFechas->orderBy('FechaLaboral', 'desc')
                ->orderBy(DB::raw($prioTurnoSql), 'asc');

            $fechasPaginadas = $queryFechas->paginate(1)->appends(request()->query());
            $itemActual = collect($fechasPaginadas->items())->first();

            if ($itemActual) {
                $fecha = $itemActual->FechaLaboral;
                $turnoGrupo = $itemActual->TurnoGrupo;

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

        usort($registrosArr, function ($a, $b) {
            if ($a->FechaLaboral !== $b->FechaLaboral) {
                return strcmp($b->FechaLaboral, $a->FechaLaboral);
            }

            $obtenerPrioTurno = function ($t) {
                $t = trim(strtolower($t));
                if (strpos($t, 'nocturno') !== false) return 1;
                if (strpos($t, 'vespertino') !== false) return 2;
                if (strpos($t, 'mixto') !== false) return 3;
                if (strpos($t, 'matutino') !== false) return 4;
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

            return strcmp($b->FechaHora, $a->FechaHora);
        });

        $agrupados = collect($registrosArr)->groupBy([
            function ($item) {
                return $item->FechaLaboral;
            },
            function ($item) {
                return $item->NombreCompleto ?? ($item->Usuario ?? 'Desconocido');
            }
        ])->sortKeysDesc();

        return view('bitacora.historial_catastro', compact(
            'agrupados', 'registrosArr', 'inicio', 'fin', 'tipoCatastro', 'usuarios', 'userId', 'search', 'registrosPaginados'
        ));
    }

    /**
     * Visualización inline de PDF en Catastro.
     */
    public function ver_pdf($id)
    {
        $registro = DB::table('auditoria_catastro')->where('id', $id)->first();
        if (!$registro) {
            abort(404, 'Registro no encontrado');
        }

        $ruta = $registro->ruta_destino ?: $registro->ruta_origen;

        if (empty($ruta) || !file_exists($ruta)) {
            abort(404, 'El archivo PDF no existe en la ruta física: ' . $ruta);
        }

        return response()->file($ruta, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($ruta) . '"'
        ]);
    }

    /**
     * Edición y actualización física del archivo renombrado en Catastro.
     */
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

            if (strtolower(pathinfo($nombreNuevo, PATHINFO_EXTENSION)) !== 'pdf') {
                $nombreNuevo .= '.pdf';
            }

            $registro = DB::table('auditoria_catastro')->where('id', $id)->first();
            if (!$registro) {
                return response()->json(['ok' => false, 'mensaje' => 'Registro no encontrado.'], 404);
            }

            $rutaActual = $registro->ruta_destino ?: $registro->ruta_origen;

            if (empty($rutaActual)) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'El registro de auditoría no cuenta con una ruta de archivo registrada.'
                ], 400);
            }

            $nuevaRuta = dirname($rutaActual) . DIRECTORY_SEPARATOR . $nombreNuevo;

            if (file_exists($rutaActual)) {
                if (file_exists($nuevaRuta) && $rutaActual !== $nuevaRuta) {
                    return response()->json([
                        'ok' => false,
                        'mensaje' => 'El archivo de destino ya existe físicamente: ' . $nuevaRuta
                    ], 400);
                }

                if (!rename($rutaActual, $nuevaRuta)) {
                    return response()->json([
                        'ok' => false,
                        'mensaje' => 'Error al renombrar el archivo en el sistema de archivos.'
                    ], 500);
                }
            }

            DB::table('auditoria_catastro')
                ->where('id', $id)
                ->update([
                    'archivo_nuevo' => $nombreNuevo,
                    'ruta_destino' => $nuevaRuta,
                ]);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Archivo renombrado con éxito.',
                'archivo_nuevo' => $nombreNuevo,
                'nueva_ruta' => $nuevaRuta
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Ocurrió un error inesperado: ' . $e->getMessage()
            ], 500);
        }
    }
}

