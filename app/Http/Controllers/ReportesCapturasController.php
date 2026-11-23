<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReportesCapturasController extends Controller
{
    public function index()
    {
        return view('admin.flow.reportes.graficaskpi');
    }

    public function dashboard(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;

        // 1. Procesados por usuario
        $porUsuario = DB::table('revisionrenombrado as rr')
            ->join('users as u', 'u.id', '=', 'rr.creador')
            ->whereBetween('rr.created_at', [$inicio, $fin])
            ->select(
                'u.name as usuario',
                DB::raw('COUNT(rr.id) as total')
            )
            ->groupBy('u.name')
            ->orderByDesc('total')
            ->get();

        // 2. Por tipo de comentario
        $porComentario = DB::table('revisionrenombrado')
            ->whereBetween('created_at', [$inicio, $fin])
            ->select(
                DB::raw("COALESCE(comentarios, 'Sin comentario') as comentario"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('comentario')
            ->orderByDesc('total')
            ->get();

        // Carpeta por usuario
        $carpetasPorUsuario = DB::table('revisioncarpetas')
            ->join('users', 'users.id', '=', 'revisioncarpetas.creador')
            ->whereBetween('revisioncarpetas.created_at', [$inicio, $fin])
            ->select(
                'users.name as usuario',
                DB::raw('COUNT(revisioncarpetas.id) as total')
            )
            ->groupBy('users.name')
            ->get();

        // Archivos procesados por carpeta
        $archivosPorCarpeta = DB::table('revisioncarpetas as rc')
            ->leftJoin('revisionrenombrado as rr', 'rr.carpeta_id', '=', 'rc.id')
            ->whereBetween('rc.created_at', [$inicio, $fin])
            ->select(
                'rc.id',
                'rc.carpeta',
                'rc.total_archivos',
                DB::raw('COUNT(rr.id) as procesados')
            )
            ->groupBy('rc.id', 'rc.carpeta', 'rc.total_archivos')
            ->get();

        return response()->json([
            'ok' => true,
            'por_usuario' => $porUsuario,
            'por_comentario' => $porComentario,
            'carpetas_por_usuario' => $carpetasPorUsuario,
            'archivos_por_carpeta' => $archivosPorCarpeta
        ]);
    }

    public function index_capturas()
    {
        $delegaciones = DB::table('delegaciones')
            ->whereRaw('LOWER(nombre) NOT LIKE ?', ['%notaria%'])
            ->whereRaw('LOWER(nombre) NOT LIKE ?', ['%delegacion%'])
            ->whereRaw('LOWER(nombre) NOT LIKE ?', ['%delegación%'])
            ->whereRaw('LOWER(nombre) NOT LIKE ?', ['%libro%'])
            ->orderBy('nombre', 'asc')
            ->get();

        return view('indicadores.registros_avance', compact('delegaciones'));
    }

    public function index_reportes_capturas()
    {
        return view('productividad.registros_usuarios');
    }

    public function index_reportes_capturas_notarias()
    {
        return view('productividad.notarias_usuarios');
    }


    public function dashboard_capturas(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;

        $query = DB::table('auditoria_registros as ac')
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
                'ac.categoria as Categoria'
            );

        // Excluir inconsistencias y movidos a inconsistencia de las métricas KPI y productividad de la gráfica
        $query->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%']);

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

            // Si es sábado o domingo antes de las 9:00 AM, pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno Matutino, Vespertino, Mixto o Calidad de lunes a viernes, pertenece al mismo dia calendario
            if ($turno && (stripos($turno, 'matutino') !== false || stripos($turno, 'vespertino') !== false || stripos($turno, 'mixto') !== false || stripos($turno, 'calidad') !== false)) {
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
        $prioridadTurno = function ($turno) {
            $turnoLower = strtolower((string) $turno);
            if (stripos($turnoLower, 'matutino') !== false) {
                return 1;
            }
            if (stripos($turnoLower, 'vespertino') !== false) {
                return 2;
            }
            if (stripos($turnoLower, 'nocturno') !== false) {
                return 3;
            }
            return 4;
        };

        // Procesamiento en una sola pasada usando Lazy Collection (cursor) para evitar sobrecarga de memoria
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

            // Agrupar variantes de eliminación bajo una sola categoría
            if (stripos($accion, 'eliminad') !== false || stripos($accion, 'eliminar') !== false) {
                $accion = 'Eliminado';
            } elseif (stripos($accion, 'inconsistencia') !== false) {
                $accion = 'Inconsistencia';
            }

            $usuario = !empty($row->NombreCompleto) ? $row->NombreCompleto : ($row->Usuario ?? 'Desconocido');
            $delegacion = !empty($row->Delegacion) ? $row->Delegacion : 'Desconocida';
            $paginas = isset($row->Paginas) ? (int) $row->Paginas : 1;
            $pc = $row->PC ?: 'Sin PC';
            $categoria = $row->Categoria ?: 'Captura';

            // Global
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

                // Acciones por usuario (para el cuarto grafico)
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

            // Día (Archivos)
            if ($fecha !== 'Sin Fecha') {
                if (!isset($conteoPorDia[$fecha]))
                    $conteoPorDia[$fecha] = 0;
                $conteoPorDia[$fecha]++;
            }

            // Día (Páginas/Imágenes)
            if ($fecha !== 'Sin Fecha') {
                if (!isset($conteoPaginasPorDia[$fecha]))
                    $conteoPaginasPorDia[$fecha] = 0;
                $conteoPaginasPorDia[$fecha] += $paginas;
            }

            // Turno
            $turnoNorm = !empty($turno) ? trim(ucfirst(strtolower($turno))) : 'Sin Turno';
            if ($turnoNorm === 'Matutino' || $turnoNorm === 'Vespertino' || $turnoNorm === 'Mixto' || $turnoNorm === 'Nocturno') {
                $turnoGrupo = $turnoNorm;
            } else {
                $turnoGrupo = 'Sin Turno';
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
            'detallados' => [], // Removido listado detallado plano para optimizar rendimiento y memoria
            'tabla_resumen' => $datosResumen,
            'acciones_resumen' => $accionesUnicasResumen
        ]);
    }

    public function descargar_excel(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;

        $query = DB::table('auditoria_registros as ac')
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
                'ac.categoria as Categoria'
            );

        // Excluir inconsistencias y movidos a inconsistencia
        $query->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%']);

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

        $registrosDb = $query->get();

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

            // Si es sábado o domingo antes de las 9:00 AM, pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno Matutino, Vespertino, Mixto o Calidad de lunes a viernes, pertenece al mismo dia calendario
            if ($turno && (stripos($turno, 'matutino') !== false || stripos($turno, 'vespertino') !== false || stripos($turno, 'mixto') !== false || stripos($turno, 'calidad') !== false)) {
                return date('Y-m-d', $timestamp);
            }

            // Si es Nocturno (o no se especifica), aplicamos la regla de corte a las 9:00 AM
            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        // Filtrar por fecha laboral
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

            // Páginas
            $keyFila = "{$fecha}|{$pc}|{$nombreUsuario}|{$turnoUsuario}|{$categoriaUsuario}";
            if (!isset($paginasTotales[$keyFila])) {
                $paginasTotales[$keyFila] = 0;
            }
            $paginasTotales[$keyFila] += (int) ($row->Paginas ?: 0);
        }

        if (empty($datosPorFecha)) {
            return redirect()->back()->with('error', 'No se encontraron datos para exportar.');
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

            // Formatear cabecera: fondo #4F81BD, texto blanco, negrita
            $lastColLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $headerRange = "A1:{$lastColLetter}1";

            $worksheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F81BD'],
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

            // Ordenar filas: Categoría ("Calidad" después de "Captura") y luego Turno (Matutino, Vespertino, Nocturno)
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
                    if (strcasecmp($t, 'Nocturno') === 0)
                        return 3;
                    return 4;
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
                'Nocturno' => ['pdfs' => 0, 'imagenes' => 0],
            ];
            foreach ($listaFilas as $fila) {
                $t = trim($fila['turno'] ?: 'Matutino');
                $tGrupo = 'Matutino';
                if (stripos($t, 'vespertino') !== false) {
                    $tGrupo = 'Vespertino';
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
                    'startColor' => ['rgb' => '4F81BD'],
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

            $turnosList = ['Matutino', 'Vespertino', 'Nocturno'];
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
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $worksheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
            // Auto-ajustar las columnas de la tabla resumen también
            $worksheet->getColumnDimension($colK)->setAutoSize(true);
            $worksheet->getColumnDimension($colL)->setAutoSize(true);
            $worksheet->getColumnDimension($colM)->setAutoSize(true);
        }

        $fileName = 'Reporte_Diario_Auditoria_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public function descargar_excel_resumen(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;

        // Consulta base para obtener los datos de la auditoria de capturas
        $consulta = DB::table('auditoria_registros as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                'ac.accion as Accion',
                'ac.paginas as Paginas',
                DB::raw('COALESCE(d.nombre, ac.directorio, "Desconocida") as Delegacion')
            );

        // Excluir inconsistencias y movidos a inconsistencia
        $consulta->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%']);

        // Excluir administradores
        $consulta->where(function ($q) {
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
            $consulta->where('ac.fecha_hora', '>=', $fechaMinima)
                ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        $registrosDb = $consulta->get();

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

            // Si es sabado o domingo antes de las 9:00 AM, pertenece al dia laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno Matutino, Vespertino, Mixto o Calidad de lunes a viernes, pertenece al mismo dia calendario
            if ($turno && (stripos($turno, 'matutino') !== false || stripos($turno, 'vespertino') !== false || stripos($turno, 'mixto') !== false || stripos($turno, 'calidad') !== false)) {
                return date('Y-m-d', $timestamp);
            }

            // Si es Nocturno (o no se especifica), aplicamos la regla de corte a las 9:00 AM
            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        // Filtrar y agrupar por fecha laboral
        $conteoPorUsuarioYFecha = [];
        $turnosDeUsuarios = [];
        $fechasUnicas = [];
        $usuariosUnicos = [];

        // Acumular resumen de turnos global
        $resumenTurnosGlobal = [
            'Matutino' => ['pdfs' => 0, 'imagenes' => 0],
            'Vespertino' => ['pdfs' => 0, 'imagenes' => 0],
            'Nocturno' => ['pdfs' => 0, 'imagenes' => 0],
        ];

        foreach ($registrosDb as $fila) {
            $fechaLaboral = $obtenerFechaLaboral($fila->FechaHora, $fila->Turno);
            if ($fechaLaboral === 'Sin Fecha') {
                $fechaLaboral = date('Y-m-d', strtotime($fila->FechaHora)) ?: 'Sin Fecha';
            }
            if ($fechaLaboral === 'Sin Fecha') {
                continue;
            }

            // Filtrar estrictamente por el rango de fechas seleccionado
            if ($inicio && $fin) {
                if ($fechaLaboral < $inicio || $fechaLaboral > $fin) {
                    continue;
                }
            }

            $nombreUsuario = $fila->NombreCompleto ?? ($fila->Usuario ?? 'Desconocido');
            $turnoNorm = !empty($fila->Turno) ? trim(ucfirst(strtolower($fila->Turno))) : 'Sin Turno';
            if ($turnoNorm !== 'Matutino' && $turnoNorm !== 'Vespertino' && $turnoNorm !== 'Nocturno') {
                $turnoNorm = 'Sin Turno';
            }

            // Acumular para el total por turno del Excel
            $tGrupo = 'Matutino';
            if (stripos($turnoNorm, 'vespertino') !== false) {
                $tGrupo = 'Vespertino';
            } elseif (stripos($turnoNorm, 'nocturno') !== false) {
                $tGrupo = 'Nocturno';
            }
            $resumenTurnosGlobal[$tGrupo]['pdfs']++;
            $resumenTurnosGlobal[$tGrupo]['imagenes'] += (int) ($fila->Paginas ?? 1);

            // Registrar usuario y fecha
            if (!in_array($nombreUsuario, $usuariosUnicos)) {
                $usuariosUnicos[] = $nombreUsuario;
            }
            if (!in_array($fechaLaboral, $fechasUnicas)) {
                $fechasUnicas[] = $fechaLaboral;
            }

            if (!isset($conteoPorUsuarioYFecha[$nombreUsuario])) {
                $conteoPorUsuarioYFecha[$nombreUsuario] = [];
            }
            if (!isset($conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral])) {
                $conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral] = 0;
            }
            $conteoPorUsuarioYFecha[$nombreUsuario][$fechaLaboral]++;

            if (!isset($turnosDeUsuarios[$nombreUsuario])) {
                $turnosDeUsuarios[$nombreUsuario] = [];
            }
            if (!isset($turnosDeUsuarios[$nombreUsuario][$turnoNorm])) {
                $turnosDeUsuarios[$nombreUsuario][$turnoNorm] = 0;
            }
            $turnosDeUsuarios[$nombreUsuario][$turnoNorm]++;
        }

        if (empty($conteoPorUsuarioYFecha)) {
            return redirect()->back()->with('error', 'No se encontraron datos para exportar.');
        }

        // Filtrar y preparar datos de los usuarios que cumplen con la media >= 800
        $usuariosFiltrados = [];
        $datosUsuarios = [];

        foreach ($usuariosUnicos as $usuario) {
            $totalPdfs = array_sum($conteoPorUsuarioYFecha[$usuario]);
            $cantidadDias = count($conteoPorUsuarioYFecha[$usuario]);
            $media = $cantidadDias > 0 ? $totalPdfs / $cantidadDias : 0;

            if ($media >= 800) {
                // Obtener turno predominante
                $turnoPredominante = 'Sin Turno';
                if (!empty($turnosDeUsuarios[$usuario])) {
                    arsort($turnosDeUsuarios[$usuario]);
                    $turnoPredominante = key($turnosDeUsuarios[$usuario]);
                }

                $usuariosFiltrados[] = $usuario;
                $datosUsuarios[$usuario] = [
                    'turno' => $turnoPredominante,
                    'media' => $media,
                    'total' => $totalPdfs
                ];
            }
        }

        if (empty($usuariosFiltrados)) {
            return redirect()->back()->with('error', 'No se encontraron usuarios con una media de 800 o mas PDFs.');
        }

        // Ordenar fechas de forma descendente (mas reciente primero)
        usort($fechasUnicas, function ($a, $b) {
            return strcmp($b, $a);
        });

        // Ordenar usuarios alfabeticamente
        sort($usuariosFiltrados);

        $libroExcel = new Spreadsheet();
        $hojaTrabajo = $libroExcel->getActiveSheet();
        $hojaTrabajo->setTitle('Resumen de Capturas');

        // Escribir cabeceras
        $hojaTrabajo->setCellValue('A1', 'USUARIO');
        $hojaTrabajo->setCellValue('B1', 'TURNO');

        $indiceColumna = 3;
        $mapaColumnasFechas = [];
        foreach ($fechasUnicas as $fecha) {
            // Formatear la fecha como d/m/Y (ej. 20/06/2026)
            $fechaFormateada = date('d/m/Y', strtotime($fecha));
            $hojaTrabajo->setCellValue([$indiceColumna, 1], $fechaFormateada);
            $mapaColumnasFechas[$fecha] = $indiceColumna;
            $indiceColumna++;
        }

        // Columna de Total y Promedio al final
        $columnaTotalLetra = Coordinate::stringFromColumnIndex($indiceColumna);
        $hojaTrabajo->setCellValue([$indiceColumna, 1], 'TOTAL');

        $columnaPromedioLetra = Coordinate::stringFromColumnIndex($indiceColumna + 1);
        $hojaTrabajo->setCellValue([$indiceColumna + 1, 1], 'PROMEDIO');

        // Formatear cabecera: fondo #4F81BD, texto blanco, negrita
        $rangoCabecera = "A1:{$columnaPromedioLetra}1";
        $hojaTrabajo->getStyle($rangoCabecera)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Escribir los datos de los usuarios
        $indiceFila = 2;
        foreach ($usuariosFiltrados as $usuario) {
            $hojaTrabajo->setCellValue("A{$indiceFila}", $usuario);

            $turno = $datosUsuarios[$usuario]['turno'];
            $hojaTrabajo->setCellValue("B{$indiceFila}", $turno);

            foreach ($fechasUnicas as $fecha) {
                $conteo = $conteoPorUsuarioYFecha[$usuario][$fecha] ?? 0;
                $columnaNum = $mapaColumnasFechas[$fecha];
                $hojaTrabajo->setCellValue([$columnaNum, $indiceFila], $conteo);
            }

            // Escribir el total y el promedio del usuario
            $total = $datosUsuarios[$usuario]['total'];
            $media = $datosUsuarios[$usuario]['media'];

            $hojaTrabajo->setCellValue([$indiceColumna, $indiceFila], $total);
            $hojaTrabajo->setCellValue([$indiceColumna + 1, $indiceFila], round($media, 2));

            // Determinar color en base al turno
            $colorHtml = 'F2F2F2';
            if (strcasecmp($turno, 'Matutino') === 0) {
                $colorHtml = 'E2EFDA';
            } elseif (strcasecmp($turno, 'Vespertino') === 0) {
                $colorHtml = 'FFF2CC';
            } elseif (strcasecmp($turno, 'Nocturno') === 0) {
                $colorHtml = 'DDEBF7';
            }

            // Aplicar estilos a la fila: color del turno y bordes delgados
            $rangoFila = "A{$indiceFila}:{$columnaPromedioLetra}{$indiceFila}";
            $hojaTrabajo->getStyle($rangoFila)->applyFromArray([
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

            $indiceFila++;
        }

        // Auto-ajustar el ancho de las columnas
        for ($col = 1; $col <= $indiceColumna + 1; $col++) {
            $letraColumna = Coordinate::stringFromColumnIndex($col);
            $hojaTrabajo->getColumnDimension($letraColumna)->setAutoSize(true);
        }

        // Escribir tabla resumen por turno global a la derecha (columnas dinámicas tras los datos principales)
        $colResumenIdx = $indiceColumna + 3;
        $startResumenRow = 2;

        $colK = Coordinate::stringFromColumnIndex($colResumenIdx);
        $colL = Coordinate::stringFromColumnIndex($colResumenIdx + 1);
        $colM = Coordinate::stringFromColumnIndex($colResumenIdx + 2);

        $hojaTrabajo->mergeCells("{$colK}{$startResumenRow}:{$colM}{$startResumenRow}");
        $hojaTrabajo->setCellValue("{$colK}{$startResumenRow}", "Resumen por Turno");
        $hojaTrabajo->getStyle("{$colK}{$startResumenRow}:{$colM}{$startResumenRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $hojaTrabajo->setCellValue("{$colK}" . ($startResumenRow + 1), "Turno");
        $hojaTrabajo->setCellValue("{$colL}" . ($startResumenRow + 1), "PDFs");
        $hojaTrabajo->setCellValue("{$colM}" . ($startResumenRow + 1), "Imágenes");
        $hojaTrabajo->getStyle("{$colK}" . ($startResumenRow + 1) . ":{$colM}" . ($startResumenRow + 1))->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $turnosList = ['Matutino', 'Vespertino', 'Nocturno'];
        $currRow = $startResumenRow + 2;
        $sumPdfs = 0;
        $sumImgs = 0;

        foreach ($turnosList as $tKey) {
            $pdfs = $resumenTurnosGlobal[$tKey]['pdfs'] ?? 0;
            $imgs = $resumenTurnosGlobal[$tKey]['imagenes'] ?? 0;
            $sumPdfs += $pdfs;
            $sumImgs += $imgs;

            $hojaTrabajo->setCellValue("{$colK}{$currRow}", $tKey);
            $hojaTrabajo->setCellValue("{$colL}{$currRow}", $pdfs);
            $hojaTrabajo->setCellValue("{$colM}{$currRow}", $imgs);

            $hojaTrabajo->getStyle("{$colL}{$currRow}:{$colM}{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $color = 'FFFFFF';
            if ($tKey === 'Matutino')
                $color = 'E2EFDA';
            elseif ($tKey === 'Vespertino')
                $color = 'FFF2CC';
            elseif ($tKey === 'Nocturno')
                $color = 'DDEBF7';

            $hojaTrabajo->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->getFill()->setFillType(Fill::FILL_SOLID);
            $hojaTrabajo->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->getFill()->getStartColor()->setRGB($color);

            $currRow++;
        }

        $hojaTrabajo->setCellValue("{$colK}{$currRow}", "Total General");
        $hojaTrabajo->setCellValue("{$colL}{$currRow}", $sumPdfs);
        $hojaTrabajo->setCellValue("{$colM}{$currRow}", $sumImgs);
        $hojaTrabajo->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $hojaTrabajo->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->getFill()->setFillType(Fill::FILL_SOLID);
        $hojaTrabajo->getStyle("{$colK}{$currRow}:{$colM}{$currRow}")->getFill()->getStartColor()->setRGB('F2F2F2');

        // Bordes
        $hojaTrabajo->getStyle("{$colK}{$startResumenRow}:{$colM}{$currRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $hojaTrabajo->getStyle("{$colK}{$startResumenRow}:{$colM}{$currRow}")->getBorders()->getAllBorders()->getColor()->setRGB('D9D9D9');

        // Auto-ajustar las columnas del resumen también
        $hojaTrabajo->getColumnDimension($colK)->setAutoSize(true);
        $hojaTrabajo->getColumnDimension($colL)->setAutoSize(true);
        $hojaTrabajo->getColumnDimension($colM)->setAutoSize(true);

        $nombreArchivo = 'Resumen_General_Capturas_' . date('Ymd_His') . '.xlsx';
        $escritor = new Xlsx($libroExcel);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');

        $escritor->save('php://output');
        exit;
    }

    public function index_capturas_notarias()
    {
        return view('indicadores.notarias_avance');
    }

    public function dashboard_capturas_notarias(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;

        // Consultar la tabla auditoria_notarias usando la columna directorio directamente como Notaría
        $query = DB::table('auditoria_notarias as an')
            ->leftJoin('users as u', 'u.id', '=', 'an.user_id')
            ->select(
                'an.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                'an.turno as Turno',
                'an.directorio as Notaria',
                'an.paginas as Paginas',
                'an.archivo_original as ArchivoOriginal',
                'an.archivo_nuevo as ArchivoNuevo',
                'an.accion as Accion',
                'an.detalles as Detalles',
                'an.pc as PC',
                'an.categoria as Categoria'
            );

        // Excluir movidos a inconsistencia de las métricas KPI y productividad de la gráfica
        $query->whereRaw('LOWER(an.accion) NOT LIKE ?', ['%mover%']);

        // Excluir administradores
        $query->where(function ($q) {
            $q->whereNull('an.user_id')
                ->orWhere(function ($sub) {
                    $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                        ->where('u.name', 'not like', '%Administrador%');
                });
        });

        // Filtrado por fecha preliminar (SQL)
        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('an.fecha_hora', '>=', $fechaMinima)
                ->where('an.fecha_hora', '<=', $fechaMaxima);
        }

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

            // Si es sábado o domingo antes de las 9:00 AM, pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            // Si es turno Matutino, Vespertino, Mixto o Calidad de lunes a viernes, pertenece al mismo dia calendario
            if ($turno && (stripos($turno, 'matutino') !== false || stripos($turno, 'vespertino') !== false || stripos($turno, 'mixto') !== false || stripos($turno, 'calidad') !== false)) {
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
        $conteoPorNotaria = [];
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
            if (stripos($turnoLower, 'nocturno') !== false) {
                return 3;
            }
            return 4;
        };

        // Procesamiento en una sola pasada usando Lazy Collection (cursor) para evitar sobrecarga de memoria
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

            $accion = $row->Accion ?: 'Capturado';

            // Agrupar variantes de eliminación bajo una sola categoría
            if (stripos($accion, 'eliminad') !== false || stripos($accion, 'eliminar') !== false) {
                $accion = 'Eliminado';
            } elseif (stripos($accion, 'inconsistencia') !== false) {
                $accion = 'Inconsistencia';
            }

            $usuario = !empty($row->NombreCompleto) ? $row->NombreCompleto : ($row->Usuario ?? 'Desconocido');
            $notaria = trim($row->Notaria ?? '');

            // Omitir notaría desconocida o vacía
            if (empty($notaria) || stripos($notaria, 'desconocid') !== false) {
                continue;
            }

            $paginas = isset($row->Paginas) ? (int) $row->Paginas : 1;
            $pc = $row->PC ?: 'Sin PC';
            $categoria = $row->Categoria ?: 'Captura';

            // Global
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

                // Acciones por usuario (para el cuarto grafico)
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

            $esInconsistencia = (stripos($accion, 'inconsistencia') !== false);

            // Día (Archivos) - Excluir inconsistencias del conteo por día
            if ($fecha !== 'Sin Fecha' && !$esInconsistencia) {
                if (!isset($conteoPorDia[$fecha]))
                    $conteoPorDia[$fecha] = 0;
                $conteoPorDia[$fecha]++;
            }

            // Día (Páginas/Imágenes) - Excluir inconsistencias del conteo por día
            if ($fecha !== 'Sin Fecha' && !$esInconsistencia) {
                if (!isset($conteoPaginasPorDia[$fecha]))
                    $conteoPaginasPorDia[$fecha] = 0;
                $conteoPaginasPorDia[$fecha] += $paginas;
            }

            // Turno
            $turnoNorm = !empty($turno) ? trim(ucfirst(strtolower($turno))) : 'Sin Turno';
            if ($turnoNorm === 'Matutino' || $turnoNorm === 'Vespertino' || $turnoNorm === 'Nocturno') {
                $turnoGrupo = $turnoNorm;
            } else {
                $turnoGrupo = 'Sin Turno';
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

            // Por Notaría (archivos e imágenes)
            if (!isset($conteoPorNotaria[$notaria])) {
                $conteoPorNotaria[$notaria] = [
                    'archivos' => 0,
                    'imagenes' => 0
                ];
            }
            $conteoPorNotaria[$notaria]['archivos']++;
            $conteoPorNotaria[$notaria]['imagenes'] += $paginas;
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

        // 4. Por Notaría (Formatear y ordenar)
        $notariasFormateado = [];
        foreach ($conteoPorNotaria as $k => $v) {
            $notariasFormateado[] = [
                'notaria' => $k,
                'archivos' => $v['archivos'],
                'imagenes' => $v['imagenes']
            ];
        }
        usort($notariasFormateado, function ($a, $b) {
            return strcmp($a['notaria'], $b['notaria']);
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

        // Ordenar la tabla resumen
        if ($detallado) {
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
            'por_notaria' => $notariasFormateado,
            'detallados' => [], // Removido listado detallado plano para optimizar rendimiento y memoria
            'tabla_resumen' => $datosResumen,
            'acciones_resumen' => $accionesUnicasResumen
        ]);
    }

    public function descargar_excel_capturas_notarias(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;

        // Consultar la tabla de digitalización de notarías real (auditoria_notarias) usando la columna directorio como Notaría
        $query = DB::table('auditoria_notarias as an')
            ->leftJoin('users as u', 'u.id', '=', 'an.user_id')
            ->select(
                'an.fecha_hora as FechaHora',
                'u.user as Usuario',
                'u.name as NombreCompleto',
                'an.turno as Turno',
                'an.accion as Accion',
                'an.directorio as Notaria',
                'an.paginas as Paginas',
                'an.pc as PC',
                'an.categoria as Categoria'
            );

        // Excluir Correccion renombre
        $query->where('an.accion', '!=', 'Correccion renombre');

        // Excluir inconsistencias y movidos a inconsistencia
        $query->whereRaw('LOWER(an.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(an.accion) NOT LIKE ?', ['%mover%']);

        // Excluir administradores
        $query->where(function ($q) {
            $q->whereNull('an.user_id')
                ->orWhere(function ($sub) {
                    $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                        ->where('u.name', 'not like', '%Administrador%');
                });
        });

        // Filtrado por fecha preliminar (SQL)
        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('an.fecha_hora', '>=', $fechaMinima)
                ->where('an.fecha_hora', '<=', $fechaMaxima);
        }

        $registrosDb = $query->get();

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

            // Si es sábado o domingo antes de las 9:00 AM, pertenece al día laboral anterior
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            if ($turno && (stripos($turno, 'matutino') !== false || stripos($turno, 'vespertino') !== false || stripos($turno, 'mixto') !== false || stripos($turno, 'calidad') !== false)) {
                return date('Y-m-d', $timestamp);
            }

            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        // Filtrar por fecha laboral
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
            $notaria = trim($row->Notaria ?? '');

            // Omitir notaría desconocida o vacía en el reporte Excel
            if (empty($notaria) || stripos($notaria, 'desconocid') !== false) {
                continue;
            }

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

            // Páginas
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
                    'startColor' => ['rgb' => '034D6D'],
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
                    if (strcasecmp($t, 'Nocturno') === 0)
                        return 3;
                    return 4;
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
                'Nocturno' => ['pdfs' => 0, 'imagenes' => 0],
            ];
            foreach ($listaFilas as $fila) {
                $t = trim($fila['turno'] ?: 'Matutino');
                $tGrupo = 'Matutino';
                if (stripos($t, 'vespertino') !== false) {
                    $tGrupo = 'Vespertino';
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

            $turnosList = ['Matutino', 'Vespertino', 'Nocturno'];
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
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $worksheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
            // Auto-ajustar las columnas de la tabla resumen también
            $worksheet->getColumnDimension($colK)->setAutoSize(true);
            $worksheet->getColumnDimension($colL)->setAutoSize(true);
            $worksheet->getColumnDimension($colM)->setAutoSize(true);
        }

        $fileName = 'Reporte_Capturas_Notarias_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public function descargar_excel_general(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;
        $tipo = $request->tipo; // 'notarias' o 'sellos'
        $tipoSello = $request->tipo_sello;

        $esNotarias = ($tipo === 'notarias');

        if ($esNotarias) {
            $query = DB::table('auditoria_notarias as an')
                ->leftJoin('users as u', 'u.id', '=', 'an.user_id')
                ->select(
                    'an.fecha_hora as FechaHora',
                    'u.user as Usuario',
                    'u.name as NombreCompleto',
                    'an.turno as Turno',
                    'an.paginas as Paginas',
                    'an.pc as PC'
                );
            // Excluir inconsistencias
            $query->whereRaw('LOWER(an.accion) NOT LIKE ?', ['%inconsistencia%'])
                ->whereRaw('LOWER(an.accion) NOT LIKE ?', ['%mover%']);
            // Excluir administradores
            $query->where(function ($q) {
                $q->whereNull('an.user_id')
                    ->orWhere(function ($sub) {
                        $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                            ->where('u.name', 'not like', '%Administrador%');
                    });
            });
            if ($inicio && $fin) {
                $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
                $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
                $query->where('an.fecha_hora', '>=', $fechaMinima)
                    ->where('an.fecha_hora', '<=', $fechaMaxima);
            }
        } else {
            $query = DB::table('auditoria_sellos as ac')
                ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
                ->select(
                    'ac.fecha_hora as FechaHora',
                    'u.user as Usuario',
                    'u.name as NombreCompleto',
                    DB::raw('COALESCE(NULLIF(ac.turno, ""), NULLIF(u.turno, ""), "Matutino") as Turno'),
                    'ac.paginas as Paginas',
                    'ac.tipo_sello as TipoSello',
                    'ac.pc as PC'
                );
            // Excluir Correccion renombre e inconsistencias
            $query->where('ac.accion', '!=', 'Correccion renombre');
            $query->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
                ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%'])
                ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%libro%']);

            // Excluir administradores
            $query->where(function ($q) {
                $q->whereNull('ac.user_id')
                    ->orWhere(function ($sub) {
                        $sub->whereRaw('LOWER(u.user) NOT IN ("admin", "administrador")')
                            ->where('u.name', 'not like', '%Administrador%');
                    });
            });

            // Filtrar por tipo de sello si se especificó
            if ($tipoSello) {
                $query->where('ac.tipo_sello', '=', $tipoSello);
            }

            if ($inicio && $fin) {
                $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
                $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
                $query->where('ac.fecha_hora', '>=', $fechaMinima)
                    ->where('ac.fecha_hora', '<=', $fechaMaxima);
            }
        }

        $registrosDb = $query->get();

        // Mapa de la PC más utilizada por cada usuario como respaldo cuando $row->PC sea NULL/Sin PC
        $tablaAuditoria = $esNotarias ? 'auditoria_notarias' : 'auditoria_sellos';
        $pcsHabituales = DB::table("{$tablaAuditoria} as a")
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->select(
                DB::raw('COALESCE(u.name, u.user, "Desconocido") as usuario_key'),
                'a.pc',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('a.pc')
            ->where('a.pc', '!=', '')
            ->whereRaw('LOWER(a.pc) NOT IN ("sin pc", "null")')
            ->groupBy('usuario_key', 'a.pc')
            ->orderBy('total', 'desc')
            ->get()
            ->groupBy('usuario_key')
            ->map(function ($items) {
                return $items->first()->pc;
            })
            ->toArray();

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
            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            if ($turno && (stripos($turno, 'matutino') !== false || stripos($turno, 'vespertino') !== false || stripos($turno, 'mixto') !== false || stripos($turno, 'calidad') !== false)) {
                return date('Y-m-d', $timestamp);
            }
            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        // Estructura para almacenar conteo consolidado por fecha, turno y PC
        $matriz = [];
        $pcsUnicas = [];

        foreach ($registrosDb as $row) {
            $fecha = $obtenerFechaLaboral($row->FechaHora, $row->Turno);
            if ($fecha === 'Sin Fecha') {
                $fecha = date('Y-m-d', strtotime($row->FechaHora)) ?: 'Sin Fecha';
            }
            if ($fecha === 'Sin Fecha') {
                continue;
            }

            // Filtrar strictly por el rango de fechas seleccionado
            if ($inicio && $fin) {
                if ($fecha < $inicio || $fecha > $fin) {
                    continue;
                }
            }

            $turno = $row->Turno ?: 'Matutino';
            // Estandarizar nombre del turno
            if (stripos($turno, 'matutino') !== false) {
                $turno = 'Matutino';
            } elseif (stripos($turno, 'vespertino') !== false) {
                $turno = 'Vespertino';
            } elseif (stripos($turno, 'nocturno') !== false) {
                $turno = 'Nocturno';
            } else {
                $turno = 'Matutino';
            }

            $usuarioNombre = !empty($row->NombreCompleto) ? $row->NombreCompleto : ($row->Usuario ?? 'Desconocido');
            $pc = (!empty($row->PC) && $row->PC !== 'Sin PC' && strtolower($row->PC) !== 'null')
                ? $row->PC
                : ($pcsHabituales[$usuarioNombre] ?? ($pcsHabituales[$row->Usuario ?? ''] ?? 'Sin PC'));

            $pcsUnicas[$pc] = true;

            if (!isset($matriz[$fecha])) {
                $matriz[$fecha] = [];
            }
            if (!isset($matriz[$fecha][$turno])) {
                $matriz[$fecha][$turno] = [];
            }
            if (!isset($matriz[$fecha][$turno][$pc])) {
                $matriz[$fecha][$turno][$pc] = [
                    'pdfs' => 0,
                    'imagenes' => 0
                ];
            }

            // Incrementar la cantidad de archivos (PDFs) e imagenes (paginas) trabajados
            $matriz[$fecha][$turno][$pc]['pdfs']++;
            $matriz[$fecha][$turno][$pc]['imagenes'] += intval($row->Paginas);
        }

        if (empty($matriz)) {
            return redirect()->back()->with('error', 'No se encontraron datos para exportar.');
        }

        // Ordenar alfabéticamente las PCs
        $pcsLista = array_keys($pcsUnicas);
        sort($pcsLista);
        krsort($matriz); // Ordenar fechas desc (más recientes arriba)

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Excel General');

        // Cabeceras FECHA y TURNO combinadas verticalmente (A1:A2 y B1:B2)
        $worksheet->mergeCells('A1:A2');
        $worksheet->setCellValue('A1', 'FECHA');
        $worksheet->mergeCells('B1:B2');
        $worksheet->setCellValue('B1', 'TURNO');

        // Columnas dinamicas de PCs con subcolumnas
        $colIdx = 3; // Empezamos en Columna C (3)
        $mapaColumnas = []; // [$pc] = ['pdfs' => colLetraPdfs, 'imagenes' => colLetraImagenes]

        foreach ($pcsLista as $pc) {
            $colLetraPdfs = Coordinate::stringFromColumnIndex($colIdx);
            $colLetraImagenes = Coordinate::stringFromColumnIndex($colIdx + 1);

            // Combinar horizontalmente la fila 1 para el nombre de la PC
            $worksheet->mergeCells("{$colLetraPdfs}1:{$colLetraImagenes}1");
            $worksheet->setCellValue("{$colLetraPdfs}1", $pc);

            // Subcabeceras en la fila 2
            $worksheet->setCellValue("{$colLetraPdfs}2", "PDFs");
            $worksheet->setCellValue("{$colLetraImagenes}2", "Imágenes");

            $mapaColumnas[$pc] = [
                'pdfs' => $colLetraPdfs,
                'imagenes' => $colLetraImagenes
            ];
            $colIdx += 2;
        }

        // Total general al final (con dos subcolumnas)
        $colTotalPdfs = Coordinate::stringFromColumnIndex($colIdx);
        $colTotalImagenes = Coordinate::stringFromColumnIndex($colIdx + 1);

        $worksheet->mergeCells("{$colTotalPdfs}1:{$colTotalImagenes}1");
        $worksheet->setCellValue("{$colTotalPdfs}1", "TOTAL GENERAL");

        $worksheet->setCellValue("{$colTotalPdfs}2", "PDFs");
        $worksheet->setCellValue("{$colTotalImagenes}2", "Imágenes");

        $maxColLetra = $colTotalImagenes;

        // Estilo cabecera (Filas 1 y 2)
        $worksheet->getStyle("A1:{$maxColLetra}2")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F497D']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFFFFF']
                ]
            ]
        ]);

        $filaActual = 3;
        $ordenTurnos = ['Matutino', 'Vespertino', 'Nocturno'];

        foreach ($matriz as $fecha => $datosTurnos) {
            // Combinar columna de fecha verticalmente de 3 en 3
            $worksheet->mergeCells("A{$filaActual}:A" . ($filaActual + 2));
            $fechaFormateada = date('d-m-Y', strtotime($fecha));
            $worksheet->setCellValue("A{$filaActual}", $fechaFormateada);

            // Tres filas consecutivas por fecha (una para cada turno)
            foreach ($ordenTurnos as $turno) {
                // Escribir nombre del turno
                $worksheet->setCellValue("B{$filaActual}", strtoupper($turno));

                $totalFilaPdfs = 0;
                $totalFilaImagenes = 0;

                // Escribir datos de PCs en sus respectivas columnas
                foreach ($pcsLista as $pc) {
                    $datosPc = $datosTurnos[$turno][$pc] ?? ['pdfs' => 0, 'imagenes' => 0];
                    $valPdfs = $datosPc['pdfs'];
                    $valImagenes = $datosPc['imagenes'];

                    $colLetraPdfs = $mapaColumnas[$pc]['pdfs'];
                    $colLetraImagenes = $mapaColumnas[$pc]['imagenes'];

                    $worksheet->setCellValue("{$colLetraPdfs}{$filaActual}", $valPdfs ?: '');
                    $worksheet->setCellValue("{$colLetraImagenes}{$filaActual}", $valImagenes ?: '');

                    $totalFilaPdfs += $valPdfs;
                    $totalFilaImagenes += $valImagenes;
                }

                // Escribir total general de la fila
                $worksheet->setCellValue("{$colTotalPdfs}{$filaActual}", $totalFilaPdfs);
                $worksheet->setCellValue("{$colTotalImagenes}{$filaActual}", $totalFilaImagenes);

                // Coloreado por fila de turno
                $colorCol = 'FFFFFF';
                if ($turno === 'Matutino')
                    $colorCol = 'E2EFDA'; // Verde claro
                elseif ($turno === 'Vespertino')
                    $colorCol = 'FFF2CC'; // Amarillo claro
                elseif ($turno === 'Nocturno')
                    $colorCol = 'DDEBF7'; // Azul claro

                // Pintamos toda la fila (incluyendo el total general) del color del turno
                $worksheet->getStyle("B{$filaActual}:{$maxColLetra}{$filaActual}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $colorCol]
                    ]
                ]);

                // Alineacion y bordes de la fila
                $worksheet->getStyle("B{$filaActual}:{$maxColLetra}{$filaActual}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D9D9D9']
                        ]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER
                    ]
                ]);

                $filaActual++;
            }

            // Aplicar borde exterior y alineacion a la celda de la fecha combinada
            $worksheet->getStyle("A" . ($filaActual - 3) . ":A" . ($filaActual - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D9D9D9']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
        }

        // Formato numérico con separadores de miles y millones (#,##0) para todas las columnas de datos
        $worksheet->getStyle("C3:{$maxColLetra}" . ($filaActual - 1))
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Autoajustar columnas
        $maxColIndex = Coordinate::columnIndexFromString($maxColLetra);
        for ($col = 1; $col <= $maxColIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $worksheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $fileName = 'Reporte_General_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Descarga el reporte detallado de capturas de registros por delegación específica o todas (omitiendo notarías).
     */
    public function descargar_excel_delegacion(Request $request)
    {
        $inicio = $request->inicio;
        $fin = $request->fin;
        $delegacionId = $request->delegacion_id;

        $query = DB::table('auditoria_registros as ac')
            ->leftJoin('users as u', 'u.id', '=', 'ac.user_id')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'ac.delegacion_id')
            ->select(
                'ac.id as Id',
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
                'ac.categoria as Categoria'
            );

        // Excluir inconsistencias y movidos a inconsistencia
        $query->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%inconsistencia%'])
            ->whereRaw('LOWER(ac.accion) NOT LIKE ?', ['%mover%']);

        // Filtrar por delegación
        $nombreDelegacionTexto = 'Todas_Delegaciones';
        if (!empty($delegacionId) && $delegacionId !== 'todas') {
            $delObj = DB::table('delegaciones')->where('id', $delegacionId)->first();
            $nombreDel = $delObj ? $delObj->nombre : null;
            if ($nombreDel) {
                $nombreDelegacionTexto = str_replace(' ', '_', $nombreDel);
            }

            $query->where(function ($q) use ($delegacionId, $nombreDel) {
                $q->where('ac.delegacion_id', $delegacionId)
                    ->orWhere('d.id', $delegacionId);
                if ($nombreDel) {
                    $q->orWhere('ac.directorio', $nombreDel)
                        ->orWhere('d.nombre', $nombreDel)
                        ->orWhereRaw('LOWER(ac.directorio) = ?', [strtolower($nombreDel)])
                        ->orWhereRaw('LOWER(d.nombre) = ?', [strtolower($nombreDel)])
                        ->orWhereRaw('LOWER(ac.directorio) LIKE ?', ['%' . strtolower($nombreDel) . '%'])
                        ->orWhereRaw('LOWER(d.nombre) LIKE ?', ['%' . strtolower($nombreDel) . '%']);
                }
            });
        } else {
            // Omitir notarias, delegacion y libros si vienen todas
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('d.nombre')
                        ->orWhere(function ($sub2) {
                            $sub2->whereRaw('LOWER(d.nombre) NOT LIKE ?', ['%notaria%'])
                                ->whereRaw('LOWER(d.nombre) NOT LIKE ?', ['%delegacion%'])
                                ->whereRaw('LOWER(d.nombre) NOT LIKE ?', ['%delegación%'])
                                ->whereRaw('LOWER(d.nombre) NOT LIKE ?', ['%libro%']);
                        });
                })->where(function ($sub) {
                    $sub->whereNull('ac.directorio')
                        ->orWhere(function ($sub2) {
                            $sub2->whereRaw('LOWER(ac.directorio) NOT LIKE ?', ['%notaria%'])
                                ->whereRaw('LOWER(ac.directorio) NOT LIKE ?', ['%delegacion%'])
                                ->whereRaw('LOWER(ac.directorio) NOT LIKE ?', ['%delegación%'])
                                ->whereRaw('LOWER(ac.directorio) NOT LIKE ?', ['%libro%']);
                        });
                });
            });
        }

        // Filtrado por fecha preliminar (SQL)
        if ($inicio && $fin) {
            $fechaMinima = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($inicio)));
            $fechaMaxima = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fin)));
            $query->where('ac.fecha_hora', '>=', $fechaMinima)
                ->where('ac.fecha_hora', '<=', $fechaMaxima);
        }

        $registrosDb = $query->orderBy('ac.fecha_hora', 'desc')->get();

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

            if (($w === 6 || $w === 0) && $hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }

            if ($turno && (stripos($turno, 'matutino') !== false || stripos($turno, 'vespertino') !== false || stripos($turno, 'mixto') !== false || stripos($turno, 'calidad') !== false)) {
                return date('Y-m-d', $timestamp);
            }

            if ($hora < 9) {
                return date('Y-m-d', strtotime('-1 day', $timestamp));
            }
            return date('Y-m-d', $timestamp);
        };

        if ($inicio && $fin) {
            $registrosDb = $registrosDb->filter(function ($row) use ($inicio, $fin, $obtenerFechaLaboral) {
                $fechaLaboral = $obtenerFechaLaboral($row->FechaHora, $row->Turno);
                if ($fechaLaboral === 'Sin Fecha') {
                    $fechaLaboral = date('Y-m-d', strtotime($row->FechaHora)) ?: '1900-01-01';
                }
                return $fechaLaboral >= $inicio && $fechaLaboral <= $fin;
            });
        }

        $registrosArr = $registrosDb->all();

        usort($registrosArr, function ($a, $b) use ($obtenerFechaLaboral) {
            $fechaA = $obtenerFechaLaboral($a->FechaHora, $a->Turno);
            $fechaB = $obtenerFechaLaboral($b->FechaHora, $b->Turno);
            if ($fechaA === 'Sin Fecha') $fechaA = date('Y-m-d', strtotime($a->FechaHora)) ?: 'Sin_Fecha';
            if ($fechaB === 'Sin Fecha') $fechaB = date('Y-m-d', strtotime($b->FechaHora)) ?: 'Sin_Fecha';

            if ($fechaA !== $fechaB) {
                return strcmp($fechaB, $fechaA);
            }

            $obtenerPrioTurno = function ($t) {
                $t = trim(strtolower($t));
                if (strpos($t, 'matutino') !== false) return 1;
                if (strpos($t, 'vespertino') !== false) return 2;
                if (strpos($t, 'mixto') !== false) return 3;
                if (strpos($t, 'nocturno') !== false) return 4;
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

            return strcmp($a->FechaHora, $b->FechaHora);
        });

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Detalle de Capturas');

        // Cabeceras
        $worksheet->setCellValue('A1', 'Nombre Completo');
        $worksheet->setCellValue('B1', 'Turno');
        $worksheet->setCellValue('C1', 'PC');
        $worksheet->setCellValue('D1', 'Delegación');
        $worksheet->setCellValue('E1', 'Acción');
        $worksheet->setCellValue('F1', 'Archivo Original');
        $worksheet->setCellValue('G1', 'Archivo Nuevo');
        $worksheet->setCellValue('H1', 'Páginas');
        $worksheet->setCellValue('I1', 'Lote');
        $worksheet->setCellValue('J1', 'Fecha y Hora');
        $worksheet->setCellValue('K1', 'Detalles');

        // Estilo cabecera #034D6D
        $worksheet->getStyle('A1:K1')->applyFromArray([
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

        $rowIdx = 2;
        foreach ($registrosArr as $fila) {
            $nombreCompleto = $fila->NombreCompleto ?? ($fila->Usuario ?? 'Desconocido');
            $worksheet->setCellValue("A{$rowIdx}", $nombreCompleto);
            $worksheet->setCellValue("B{$rowIdx}", $fila->Turno ?: 'Matutino');
            $worksheet->setCellValue("C{$rowIdx}", $fila->PC ?: 'Sin PC');
            $worksheet->setCellValue("D{$rowIdx}", $fila->Delegacion ?: 'Desconocida');
            $worksheet->setCellValue("E{$rowIdx}", $fila->Accion);
            $worksheet->setCellValue("F{$rowIdx}", $fila->ArchivoOriginal);
            $worksheet->setCellValue("G{$rowIdx}", $fila->ArchivoNuevo ?: '-');
            $worksheet->setCellValue("H{$rowIdx}", (int) $fila->Paginas);
            $worksheet->setCellValue("I{$rowIdx}", $fila->Lote ?: '-');
            $worksheet->setCellValue("J{$rowIdx}", $fila->FechaHora);
            $worksheet->setCellValue("K{$rowIdx}", $fila->Detalles ?: '-');

            // Colores por turno
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
                $colorHtml = 'F2F2F2';
            }

            if ($colorHtml !== 'FFFFFF') {
                $style = $worksheet->getStyle("A{$rowIdx}:K{$rowIdx}");
                $style->getFill()->setFillType(Fill::FILL_SOLID);
                $style->getFill()->getStartColor()->setRGB($colorHtml);
            }

            $rowIdx++;
        }

        $maxRow = $rowIdx - 1;
        if ($maxRow >= 2) {
            $rangeStyle = $worksheet->getStyle("A2:K{$maxRow}");
            $rangeStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $rangeStyle->getBorders()->getAllBorders()->getColor()->setRGB('D9D9D9');
        }

        // Resumen por turnos
        $resumenTurnos = [
            'Matutino' => ['pdfs' => 0, 'imagenes' => 0],
            'Vespertino' => ['pdfs' => 0, 'imagenes' => 0],
            'Mixto' => ['pdfs' => 0, 'imagenes' => 0],
            'Nocturno' => ['pdfs' => 0, 'imagenes' => 0],
        ];

        foreach ($registrosArr as $fila) {
            $accion = $fila->Accion ?? 'Otras';
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

        // Escribir tabla resumen en columnas M, N, O
        $startResumenRow = 2;
        $worksheet->mergeCells("M{$startResumenRow}:O{$startResumenRow}");
        $worksheet->setCellValue("M{$startResumenRow}", "Resumen de Productividad por Turno");
        $worksheet->getStyle("M{$startResumenRow}:O{$startResumenRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '034D6D'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $worksheet->setCellValue("M" . ($startResumenRow + 1), "Turno");
        $worksheet->setCellValue("N" . ($startResumenRow + 1), "PDFs");
        $worksheet->setCellValue("O" . ($startResumenRow + 1), "Imágenes");
        $worksheet->getStyle("M" . ($startResumenRow + 1) . ":O" . ($startResumenRow + 1))->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $turnos = ['Matutino', 'Vespertino', 'Mixto', 'Nocturno'];
        $currentRow = $startResumenRow + 2;
        $totalPdfs = 0;
        $totalImgs = 0;

        foreach ($turnos as $turnoKey) {
            $pdfs = $resumenTurnos[$turnoKey]['pdfs'];
            $imgs = $resumenTurnos[$turnoKey]['imagenes'];
            $totalPdfs += $pdfs;
            $totalImgs += $imgs;

            $worksheet->setCellValue("M{$currentRow}", $turnoKey);
            $worksheet->setCellValue("N{$currentRow}", $pdfs);
            $worksheet->setCellValue("O{$currentRow}", $imgs);

            $worksheet->getStyle("N{$currentRow}:O{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $color = 'FFFFFF';
            if ($turnoKey === 'Matutino') $color = 'E2EFDA';
            elseif ($turnoKey === 'Vespertino') $color = 'FFF2CC';
            elseif ($turnoKey === 'Mixto') $color = 'FCE4D6';
            elseif ($turnoKey === 'Nocturno') $color = 'DDEBF7';

            $worksheet->getStyle("M{$currentRow}:O{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID);
            $worksheet->getStyle("M{$currentRow}:O{$currentRow}")->getFill()->getStartColor()->setRGB($color);

            $currentRow++;
        }

        $worksheet->setCellValue("M{$currentRow}", "Total General");
        $worksheet->setCellValue("N{$currentRow}", $totalPdfs);
        $worksheet->setCellValue("O{$currentRow}", $totalImgs);
        $worksheet->getStyle("M{$currentRow}:O{$currentRow}")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $worksheet->getStyle("M{$currentRow}:O{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID);
        $worksheet->getStyle("M{$currentRow}:O{$currentRow}")->getFill()->getStartColor()->setRGB('F2F2F2');

        $worksheet->getStyle("M{$startResumenRow}:O{$currentRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $worksheet->getStyle("M{$startResumenRow}:O{$currentRow}")->getBorders()->getAllBorders()->getColor()->setRGB('D9D9D9');

        // Anchos de columna
        $worksheet->getColumnDimension('F')->setWidth(50); // Archivo Original
        $worksheet->getColumnDimension('G')->setWidth(50); // Archivo Nuevo
        $worksheet->getColumnDimension('K')->setWidth(40); // Detalles
        foreach (['A', 'B', 'C', 'D', 'E', 'H', 'I', 'J', 'M', 'N', 'O'] as $colLetter) {
            $worksheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $fileName = 'Reporte_Detallado_Registros_' . $nombreDelegacionTexto . '_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        $writer->save('php://output');
        exit;
    }
}

