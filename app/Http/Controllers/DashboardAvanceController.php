<?php

namespace App\Http\Controllers;

use App\Models\ArchivoSesion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardAvanceController extends Controller
{
    public function index()
    {
        $usuario = Auth::user();
        abort_unless($usuario && $usuario->hasRole(['superadmin', 'administrador']), 403, 'Acceso exclusivo para Administradores y Superadministradores.');

        return view('dashboard.avance_global', $this->construirDatos());
    }

    public function datos()
    {
        $usuario = Auth::user();
        abort_unless($usuario && $usuario->hasRole(['superadmin', 'administrador']), 403);

        return response()->json([
            'ok' => true,
            'actualizado_en' => now()->format('H:i:s'),
            'datos' => $this->construirDatos(),
        ]);
    }

    private function construirDatos(): array
    {

        $metaEntregables = (int) config('metas_entregables.total_entregables', 1120130);
        $metaPaginas     = (int) config('metas_entregables.total_paginas', 22079039);
        $totalIndice     = (int) config('metas_entregables.total_indice', 1671607);
        $totalEliminados = (int) config('metas_entregables.total_eliminados', 551477);
        $metasDel        = config('metas_entregables.por_delegacion', []);

        // Resumen global desde auditoria_sellos
        $resumen = DB::table('auditoria_sellos')
            ->selectRaw("
                COUNT(*) as total_trabajados,
                SUM(CASE WHEN LOWER(COALESCE(accion, '')) IN ('renombrado', 'renombrar') THEN 1 ELSE 0 END) as renombrados,
                SUM(CASE WHEN LOWER(COALESCE(accion, '')) IN ('validado', 'validar') THEN 1 ELSE 0 END) as validados,
                SUM(CASE WHEN LOWER(COALESCE(accion, '')) = 'inconsistencia' THEN 1 ELSE 0 END) as inconsistencias,
                SUM(CASE WHEN estado_aplicacion = 'aplicado' THEN 1 ELSE 0 END) as aplicados_stellum,
                SUM(COALESCE(paginas, 0)) as paginas_trabajadas,
                AVG(NULLIF(tiempo_captura, 0)) as promedio_segundos
            ")
            ->first();

        $totalTrabajados   = (int) ($resumen->total_trabajados ?? 0);
        $renombrados       = (int) ($resumen->renombrados ?? 0);
        $validados         = (int) ($resumen->validados ?? 0);
        $inconsistencias   = (int) ($resumen->inconsistencias ?? 0);
        $aplicadosStellum  = (int) ($resumen->aplicados_stellum ?? 0);
        $paginasTrabajadas = (int) ($resumen->paginas_trabajadas ?? 0);
        $promedioSegundos  = $resumen->promedio_segundos ? round((float) $resumen->promedio_segundos, 1) : 0;

        $faltantes         = max(0, $metaEntregables - $totalTrabajados);
        $porcentajeAvance  = $metaEntregables > 0 ? round(($totalTrabajados / $metaEntregables) * 100, 3) : 0;
        $porcentajePaginas = $metaPaginas > 0 ? round(($paginasTrabajadas / $metaPaginas) * 100, 3) : 0;

        // Hoy y en captura activa
        $hoyTotal = (int) DB::table('auditoria_sellos')
            ->whereDate('fecha_hora', now()->toDateString())
            ->count();

        $enCapturaActiva = (int) ArchivoSesion::where('estado', 'en_captura')
            ->where(function ($q) {
                $q->whereNull('expira_en')->orWhere('expira_en', '>=', now());
            })
            ->count();

        // Avance por delegación (cruzando nombres normalizados sin acentos)
        $trabajadosPorDel = DB::table('auditoria_sellos as a')
            ->leftJoin('delegaciones as d', 'd.id', '=', 'a.delegacion_id')
            ->selectRaw("
                COALESCE(d.nombre, a.directorio, 'SIN DELEGACION') as del_nombre,
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(COALESCE(a.accion, '')) IN ('renombrado', 'renombrar') THEN 1 ELSE 0 END) as renombrados,
                SUM(CASE WHEN LOWER(COALESCE(a.accion, '')) IN ('validado', 'validar') THEN 1 ELSE 0 END) as validados,
                SUM(CASE WHEN LOWER(COALESCE(a.accion, '')) = 'inconsistencia' THEN 1 ELSE 0 END) as inconsistencias,
                SUM(COALESCE(a.paginas, 0)) as paginas
            ")
            ->groupBy('del_nombre')
            ->get();

        $mapaDel = [];
        foreach ($trabajadosPorDel as $row) {
            $clave = self::normalizarNombre((string) $row->del_nombre);
            if (!isset($mapaDel[$clave])) {
                $mapaDel[$clave] = ['total' => 0, 'renombrados' => 0, 'validados' => 0, 'inconsistencias' => 0, 'paginas' => 0];
            }
            $mapaDel[$clave]['total']           += (int) $row->total;
            $mapaDel[$clave]['renombrados']     += (int) $row->renombrados;
            $mapaDel[$clave]['validados']       += (int) $row->validados;
            $mapaDel[$clave]['inconsistencias'] += (int) $row->inconsistencias;
            $mapaDel[$clave]['paginas']         += (int) $row->paginas;
        }

        $delegacionesAvance = [];
        foreach ($metasDel as $m) {
            $clave = self::normalizarNombre($m['nombre']);
            $est = $mapaDel[$clave] ?? ['total' => 0, 'renombrados' => 0, 'validados' => 0, 'inconsistencias' => 0, 'paginas' => 0];
            $meta = (int) $m['meta'];
            $trab = $est['total'];
            $falt = max(0, $meta - $trab);
            $pct  = $meta > 0 ? round(($trab / $meta) * 100, 2) : 0;

            $delegacionesAvance[] = [
                'nombre'          => $m['nombre'],
                'meta'            => $meta,
                'paginas_meta'    => (int) $m['paginas'],
                'trabajados'      => $trab,
                'renombrados'     => $est['renombrados'],
                'validados'       => $est['validados'],
                'inconsistencias' => $est['inconsistencias'],
                'paginas_avance'  => $est['paginas'],
                'faltantes'       => $falt,
                'porcentaje'      => $pct,
            ];
        }

        // Tendencia últimos 14 días
        $desde14 = now()->subDays(13)->startOfDay();
        $porDiaRaw = DB::table('auditoria_sellos')
            ->where('fecha_hora', '>=', $desde14)
            ->selectRaw("
                DATE(fecha_hora) as dia,
                SUM(CASE WHEN LOWER(COALESCE(accion, '')) IN ('renombrado', 'renombrar') THEN 1 ELSE 0 END) as renombrados,
                SUM(CASE WHEN LOWER(COALESCE(accion, '')) IN ('validado', 'validar') THEN 1 ELSE 0 END) as validados,
                SUM(CASE WHEN LOWER(COALESCE(accion, '')) = 'inconsistencia' THEN 1 ELSE 0 END) as inconsistencias
            ")
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        $tendenciaDias = [];
        for ($i = 13; $i >= 0; $i--) {
            $fecha = now()->subDays($i)->format('Y-m-d');
            $r = $porDiaRaw->get($fecha);
            $tendenciaDias[] = [
                'dia'             => now()->subDays($i)->format('d/m'),
                'renombrados'     => (int) ($r->renombrados ?? 0),
                'validados'       => (int) ($r->validados ?? 0),
                'inconsistencias' => (int) ($r->inconsistencias ?? 0),
            ];
        }

        // Top capturistas
        $topCapturistas = DB::table('auditoria_sellos as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->selectRaw("
                COALESCE(u.name, 'Sin Usuario') as nombre,
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(COALESCE(a.accion, '')) IN ('renombrado', 'renombrar') THEN 1 ELSE 0 END) as renombrados,
                SUM(CASE WHEN LOWER(COALESCE(a.accion, '')) IN ('validado', 'validar') THEN 1 ELSE 0 END) as validados,
                SUM(COALESCE(a.paginas, 0)) as paginas,
                ROUND(AVG(NULLIF(a.tiempo_captura, 0)), 1) as prom_seg
            ")
            ->groupBy('a.user_id', 'u.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return compact(
            'metaEntregables',
            'metaPaginas',
            'totalIndice',
            'totalEliminados',
            'totalTrabajados',
            'renombrados',
            'validados',
            'inconsistencias',
            'aplicadosStellum',
            'paginasTrabajadas',
            'promedioSegundos',
            'faltantes',
            'porcentajeAvance',
            'porcentajePaginas',
            'hoyTotal',
            'enCapturaActiva',
            'delegacionesAvance',
            'tendenciaDias',
            'topCapturistas'
        );
    }

    private static function normalizarNombre(string $texto): string
    {
        $t = mb_strtoupper(trim($texto), 'UTF-8');
        $mapa = ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'];
        return strtr($t, $mapa);
    }
}
