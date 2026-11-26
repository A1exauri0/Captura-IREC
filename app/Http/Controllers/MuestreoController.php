<?php

namespace App\Http\Controllers;

use App\Models\ArchivoMuestreo;
use App\Models\ControlMuestreo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MuestreoController extends Controller
{
    /**
     * Calcula la fecha laboral de un registro según su fecha_hora y turno
     * (idéntico al criterio de productividad: turnos nocturnos antes de las 9:00 AM pertenecen al día laboral anterior).
     */
    public static function obtenerFechaLaboral(?string $fechaCompleta, ?string $turno = null): string
    {
        if (empty($fechaCompleta) || strlen($fechaCompleta) < 10) {
            return date('Y-m-d');
        }
        if (strlen($fechaCompleta) === 10) {
            return $fechaCompleta;
        }
        $ts = strtotime($fechaCompleta);
        if ($ts === false) {
            return date('Y-m-d');
        }

        $hora = (int) date('H', $ts);
        $esTurnoDia = $turno && (
            stripos($turno, 'matutino') !== false ||
            stripos($turno, 'vespertino') !== false ||
            stripos($turno, 'mixto') !== false ||
            stripos($turno, 'calidad') !== false ||
            stripos($turno, 'sabado') !== false ||
            stripos($turno, 'sábado') !== false ||
            stripos($turno, 'domingo') !== false
        );

        if (!$esTurnoDia && $hora < 9) {
            return date('Y-m-d', strtotime('-1 day', $ts));
        }

        return date('Y-m-d', $ts);
    }

    /**
     * Normaliza el nombre del turno para agrupar los muestreos consistentemente.
     */
    public static function normalizarTurno(?string $turno): string
    {
        $t = trim((string) $turno);
        if ($t === '') {
            return 'Matutino';
        }
        if (stripos($t, 'nocturno') !== false) {
            return 'Nocturno';
        }
        if (stripos($t, 'vespertino') !== false) {
            return 'Vespertino';
        }
        if (stripos($t, 'mixto') !== false) {
            return 'Mixto';
        }
        return ucfirst($t);
    }

    /**
     * Fórmula de tamaño de muestra con 96% de confianza y 5% de margen de error
     * (idéntica a GenerarMuestreoCommand de Stellum).
     */
    public static function calcularTamanoMuestra96(int $universo): int
    {
        if ($universo <= 0) {
            return 0;
        }
        $z = 2.0537489;
        $p = 0.5;
        $q = 0.5;
        $error = 0.05;

        $numerador = $universo * ($z * $z) * $p * $q;
        $denominador = (($error * $error) * ($universo - 1)) + (($z * $z) * $p * $q);
        $muestra = $denominador > 0 ? (int) ceil($numerador / $denominador) : 1;

        return min($universo, max(1, $muestra));
    }

    /**
     * Calcula tamaño de muestra y errores permitidos según la regla seleccionada.
     */
    public static function calcularMetricasRegla(int $universo, string $regla): array
    {
        if ($universo <= 0) {
            return ['muestra' => 0, 'errores_permitidos' => 0];
        }

        return match ($regla) {
            'porcentaje_30' => [
                'muestra'            => max(1, (int) ceil($universo * 0.30)),
                'errores_permitidos' => (int) ceil(max(1, (int) ceil($universo * 0.30)) * 0.20),
            ],
            'porcentaje_50' => [
                'muestra'            => max(1, (int) ceil($universo * 0.50)),
                'errores_permitidos' => (int) ceil(max(1, (int) ceil($universo * 0.50)) * 0.10),
            ],
            default => [ // confianza_96
                'muestra'            => self::calcularTamanoMuestra96($universo),
                'errores_permitidos' => (int) floor(self::calcularTamanoMuestra96($universo) * 0.05),
            ],
        };
    }

    /**
     * Vista principal de Gestión de Muestreos (Por Hacer y Generados/Hechos).
     */
    public function index(Request $request)
    {
        // 1. Obtener muestreos ya generados
        $muestreosGenerados = ControlMuestreo::with('creador')
            ->orderByDesc('fecha_laboral')
            ->orderByDesc('id')
            ->get();

        $llavesGeneradas = [];
        foreach ($muestreosGenerados as $mg) {
            $llavesGeneradas[$mg->fecha_laboral . '|' . strtolower($mg->turno)] = $mg;
        }

        // 2. Agrupar lo trabajado en auditoria_sellos por Fecha Laboral + Turno
        $registrosSellos = DB::table('auditoria_sellos as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->select(
                'a.id',
                'a.fecha_hora',
                DB::raw('COALESCE(NULLIF(a.turno, ""), NULLIF(u.turno, ""), "Matutino") as turno_raw'),
                'a.user_id',
                'u.name as usuario_nombre',
                'a.accion'
            )
            ->whereIn(DB::raw('LOWER(a.accion)'), ['validado', 'renombrado'])
            ->orderByDesc('a.fecha_hora')
            ->get();

        $gruposPorFechaTurno = [];
        foreach ($registrosSellos as $r) {
            $turnoNorm = self::normalizarTurno($r->turno_raw);
            $fechaLab = self::obtenerFechaLaboral($r->fecha_hora, $turnoNorm);
            $key = $fechaLab . '|' . strtolower($turnoNorm);

            // Si ya fue generado el muestreo para esta Fecha + Turno, no aparece en "Por Hacer"
            if (isset($llavesGeneradas[$key])) {
                continue;
            }

            if (!isset($gruposPorFechaTurno[$key])) {
                $gruposPorFechaTurno[$key] = [
                    'fecha_laboral' => $fechaLab,
                    'turno'         => $turnoNorm,
                    'universo'      => 0,
                    'validados'     => 0,
                    'renombrados'   => 0,
                    'capturistas'   => [],
                ];
            }

            $gruposPorFechaTurno[$key]['universo']++;
            if (strtolower((string) $r->accion) === 'validado') {
                $gruposPorFechaTurno[$key]['validados']++;
            } else {
                $gruposPorFechaTurno[$key]['renombrados']++;
            }
            $nomCap = $r->usuario_nombre ?: ('Usuario #' . ($r->user_id ?: '?'));
            $gruposPorFechaTurno[$key]['capturistas'][$nomCap] = true;
        }

        $muestreosPorHacer = [];
        foreach ($gruposPorFechaTurno as $g) {
            $univ = $g['universo'];
            $g['capturistas'] = array_keys($g['capturistas']);
            $g['preview_reglas'] = [
                'confianza_96'  => self::calcularMetricasRegla($univ, 'confianza_96'),
                'porcentaje_30' => self::calcularMetricasRegla($univ, 'porcentaje_30'),
                'porcentaje_50' => self::calcularMetricasRegla($univ, 'porcentaje_50'),
            ];
            $muestreosPorHacer[] = $g;
        }

        // Usuarios con rol Calidad (para asignación opcional de carga 100% o 50%)
        $usuariosCalidad = User::role('Calidad')->where('estatus', 1)->orderBy('name')->get();

        return view('monitoreo.gestion_muestreo', compact(
            'muestreosPorHacer',
            'muestreosGenerados',
            'usuariosCalidad'
        ));
    }

    /**
     * Genera el muestreo de una Fecha Laboral + Turno aplicando la regla seleccionada
     * (96% Confianza, 30% Muestra o 50% Muestra) directamente en el controlador.
     */
    public function generar(Request $request)
    {
        $data = $request->validate([
            'fecha_laboral'     => 'required|date_format:Y-m-d',
            'turno'             => 'required|string|max:60',
            'regla'             => 'required|in:confianza_96,porcentaje_30,porcentaje_50',
            'usuarios_normales' => 'nullable|array',
            'usuarios_medios'   => 'nullable|array',
        ]);

        $fechaObjetivo = $data['fecha_laboral'];
        $turnoObjetivo = self::normalizarTurno($data['turno']);
        $regla = $data['regla'];

        $existente = ControlMuestreo::where('fecha_laboral', $fechaObjetivo)
            ->whereRaw('LOWER(turno) = ?', [strtolower($turnoObjetivo)])
            ->first();

        if ($existente) {
            return back()->with('error', "Ya existe un muestreo generado para {$fechaObjetivo} ({$turnoObjetivo}).");
        }

        // Obtener los registros de auditoria_sellos correspondientes a esa Fecha Laboral y Turno
        $fechaMin = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($fechaObjetivo)));
        $fechaMax = date('Y-m-d 23:59:59', strtotime('+1 day', strtotime($fechaObjetivo)));

        $candidatos = DB::table('auditoria_sellos as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->select(
                'a.id',
                'a.fecha_hora',
                DB::raw('COALESCE(NULLIF(a.turno, ""), NULLIF(u.turno, ""), "Matutino") as turno_raw'),
                'a.user_id',
                'a.delegacion_id',
                'a.indice_pdf_id',
                'a.ruta_origen',
                'a.ruta',
                'a.archivo_original',
                'a.archivo_nuevo',
                'a.paginas',
                'a.hash'
            )
            ->whereIn(DB::raw('LOWER(a.accion)'), ['validado', 'renombrado'])
            ->where('a.fecha_hora', '>=', $fechaMin)
            ->where('a.fecha_hora', '<=', $fechaMax)
            ->orderBy('a.id', 'asc')
            ->get()
            ->filter(function ($r) use ($fechaObjetivo, $turnoObjetivo) {
                $tNorm = self::normalizarTurno($r->turno_raw);
                $fLab = self::obtenerFechaLaboral($r->fecha_hora, $tNorm);
                return $fLab === $fechaObjetivo && strcasecmp($tNorm, $turnoObjetivo) === 0;
            })
            ->values()
            ->all();

        $universoTotal = count($candidatos);
        if ($universoTotal === 0) {
            return back()->with('error', "No se encontraron registros capturados para {$fechaObjetivo} ({$turnoObjetivo}).");
        }

        // Selección aleatoria según la regla elegida (replicando los 3 algoritmos de Stellum)
        $seleccionados = [];
        if ($regla === 'porcentaje_30' || $regla === 'porcentaje_50') {
            $porcentaje = ($regla === 'porcentaje_30') ? 0.30 : 0.50;
            foreach (array_chunk($candidatos, 100) as $bloque) {
                $cantidadTomar = max(1, (int) ceil(count($bloque) * $porcentaje));
                shuffle($bloque);
                $sub = array_slice($bloque, 0, $cantidadTomar);
                usort($sub, fn ($a, $b) => $a->id <=> $b->id);
                foreach ($sub as $item) {
                    $seleccionados[] = $item;
                }
            }
            $tamanoMuestra = count($seleccionados);
            $margenError = ($regla === 'porcentaje_30') ? 0.20 : 0.10;
            $erroresPermitidos = (int) ceil($tamanoMuestra * $margenError);
        } else {
            // confianza_96: Reservoir / muestra aleatoria uniforme con Z=2.0537489, E=0.05
            $tamanoMuestra = self::calcularTamanoMuestra96($universoTotal);
            $copia = $candidatos;
            shuffle($copia);
            $seleccionados = array_slice($copia, 0, $tamanoMuestra);
            usort($seleccionados, fn ($a, $b) => $a->id <=> $b->id);
            $erroresPermitidos = (int) floor($tamanoMuestra * 0.05);
        }

        // Distribución entre usuarios de Calidad (100% y 50% carga) o libre (null) si no se seleccionaron
        $usuariosNormales = array_values(array_filter(array_map('intval', $data['usuarios_normales'] ?? [])));
        $usuariosMedios = array_values(array_diff(
            array_values(array_filter(array_map('intval', $data['usuarios_medios'] ?? []))),
            $usuariosNormales
        ));
        $asignaciones = $this->distribuirMuestra($seleccionados, $usuariosNormales, $usuariosMedios);

        $ahora = now();
        DB::transaction(function () use (
            $fechaObjetivo,
            $turnoObjetivo,
            $regla,
            $universoTotal,
            $tamanoMuestra,
            $erroresPermitidos,
            $asignaciones,
            $ahora
        ) {
            $control = ControlMuestreo::create([
                'fecha_laboral'      => $fechaObjetivo,
                'turno'              => $turnoObjetivo,
                'tipo_documento'     => 'sellos',
                'regla'              => $regla,
                'universo'           => $universoTotal,
                'muestra'            => $tamanoMuestra,
                'errores_permitidos' => $erroresPermitidos,
                'revisados'          => 0,
                'errores_detectados' => 0,
                'estado'             => 'en_proceso',
                'generado_por'       => Auth::id(),
            ]);

            $filas = [];
            foreach ($asignaciones as $asig) {
                $r = $asig['registro'];
                $origLimpio = preg_replace('/\.pdf$/i', '', (string) $r->archivo_original);
                $capLimpio  = preg_replace('/\.pdf$/i', '', (string) ($r->archivo_nuevo ?: $r->archivo_original));
                $filas[] = [
                    'control_muestreo_id' => $control->id,
                    'auditoria_sello_id'  => $r->id,
                    'indice_pdf_id'       => $r->indice_pdf_id,
                    'delegacion_id'       => $r->delegacion_id,
                    'ruta_origen'         => $r->ruta_origen ?: $r->ruta,
                    'archivo_original'    => $origLimpio,
                    'archivo_capturado'   => $capLimpio,
                    'archivo_muestreo'    => null,
                    'accion_muestreo'     => null,
                    'paginas'             => (int) ($r->paginas ?: 1),
                    'hash'                => $r->hash,
                    'capturista_id'       => $r->user_id,
                    'calidad_user_id'     => $asig['calidad_user_id'],
                    'estado'              => 'pendiente',
                    'es_correcto'         => null,
                    'detalles'            => null,
                    'created_at'          => $ahora,
                    'updated_at'          => $ahora,
                ];
            }

            foreach (array_chunk($filas, 500) as $chunk) {
                ArchivoMuestreo::insert($chunk);
            }
        });

        return back()->with('success', "Muestreo generado correctamente para {$fechaObjetivo} ({$turnoObjetivo}): {$tamanoMuestra} archivos de un universo de {$universoTotal} (límite de errores: {$erroresPermitidos}).");
    }

    /**
     * Devuelve en JSON el listado de archivos de un muestreo para su inspección en modal.
     */
    public function detalle(int $id)
    {
        $control = ControlMuestreo::findOrFail($id);
        $archivos = ArchivoMuestreo::with(['capturista:id,name,user', 'revisorCalidad:id,name,user'])
            ->where('control_muestreo_id', $control->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'ok'       => true,
            'control'  => $control,
            'archivos' => $archivos,
        ]);
    }

    /**
     * Elimina un muestreo para poder regenerarlo con otra regla si aún se requiere.
     */
    public function destroy(int $id)
    {
        $control = ControlMuestreo::findOrFail($id);
        $info = "{$control->fecha_laboral} ({$control->turno})";
        $control->delete();

        return back()->with('success', "Muestreo de {$info} eliminado correctamente.");
    }

    /**
     * Distribuye los registros seleccionados entre usuarios de Calidad con carga 100% y 50%
     * (idéntico al método distribuir de GenerarMuestreoCommand en Stellum).
     */
    private function distribuirMuestra(array $registros, array $normalUsers, array $medioUsers): array
    {
        $resultado = [];
        $total = count($registros);

        if (empty($normalUsers) && empty($medioUsers)) {
            foreach ($registros as $reg) {
                $resultado[] = ['registro' => $reg, 'calidad_user_id' => null];
            }
            return $resultado;
        }

        $totalWeight = (count($normalUsers) * 1.0) + (count($medioUsers) * 0.5);
        if ($totalWeight <= 0) {
            foreach ($registros as $reg) {
                $resultado[] = ['registro' => $reg, 'calidad_user_id' => null];
            }
            return $resultado;
        }

        $unit = $total / $totalWeight;
        $cuotas = [];
        foreach ($normalUsers as $uid) {
            $cuotas[$uid] = (int) floor($unit * 1.0);
        }
        foreach ($medioUsers as $uid) {
            $cuotas[$uid] = (int) floor($unit * 0.5);
        }

        $residuo = $total - array_sum($cuotas);
        $ordenPrioridad = array_merge($normalUsers, $medioUsers);
        $numUsuarios = count($ordenPrioridad);
        $idx = 0;

        while ($residuo > 0 && $numUsuarios > 0) {
            $uid = $ordenPrioridad[$idx % $numUsuarios];
            $cuotas[$uid]++;
            $residuo--;
            $idx++;
        }

        $cursor = 0;
        foreach ($cuotas as $uid => $cantidad) {
            for ($i = 0; $i < $cantidad; $i++) {
                if ($cursor < $total) {
                    $resultado[] = [
                        'registro'        => $registros[$cursor],
                        'calidad_user_id' => $uid,
                    ];
                    $cursor++;
                }
            }
        }

        return $resultado;
    }
}
