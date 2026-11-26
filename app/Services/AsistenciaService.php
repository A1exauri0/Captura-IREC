<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Huella;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AsistenciaService
{
    /**
     * Registra un evento biométrico (entrada o salida) para un usuario,
     * usando siempre la hora oficial del servidor y la fecha laboral según su turno.
     */
    public function registrarChequeo(
        User $usuario,
        ?Huella $huella = null,
        array $meta = [],
        ?Carbon $ahora = null
    ): array {
        $tz = config('app.timezone', 'America/Mexico_City');
        $ahora = ($ahora ? $ahora->copy() : now())->timezone($tz);

        if (!$usuario->estatus) {
            return [
                'ok'           => false,
                'accion'       => 'usuario_inactivo',
                'mensaje'      => "El usuario {$usuario->name} se encuentra inactivo en el sistema.",
                'hora_servidor'=> $ahora->format('Y-m-d H:i:s'),
                'asistencia'   => null,
            ];
        }

        $turno = $usuario->turnoRelacion;
        if (!$turno && !empty($usuario->turno)) {
            $turno = Turno::whereRaw('LOWER(nombre) = ?', [strtolower(trim($usuario->turno))])->first();
        }

        $turnoNombre = $turno?->nombre ?? $usuario->turno ?? 'Sin turno';
        $fechaLaboral = FechaLaboralService::calcular($ahora->format('Y-m-d H:i:s'), $turnoNombre);
        if ($fechaLaboral === 'Sin Fecha') {
            $fechaLaboral = $ahora->toDateString();
        }

        $score = isset($meta['score']) && is_numeric($meta['score']) ? (int) $meta['score'] : null;
        $dispositivo = isset($meta['dispositivo']) ? mb_substr(trim((string) $meta['dispositivo']), 0, 100) : null;
        $antiReboteMin = max(0, (int) config('services.biometrico.antirebote_minutos', 5));

        return DB::transaction(function () use (
            $usuario,
            $huella,
            $turno,
            $turnoNombre,
            $fechaLaboral,
            $ahora,
            $score,
            $dispositivo,
            $antiReboteMin
        ) {
            if ($huella) {
                $huella->forceFill(['ultimo_uso_en' => $ahora])->save();
            }

            $asistencia = Asistencia::where('user_id', $usuario->id)
                ->whereDate('fecha_laboral', $fechaLaboral)
                ->lockForUpdate()
                ->first();

            // 1) Primer chequeo de la jornada -> ENTRADA
            if (!$asistencia) {
                $minutosRetardo = $turno
                    ? $turno->calcularRetardoMinutos($ahora, $fechaLaboral)
                    : 0;
                $estatus = $minutosRetardo > 0 ? 'retardo' : 'a_tiempo';

                $asistencia = Asistencia::create([
                    'user_id'               => $usuario->id,
                    'turno_id'              => $turno?->id,
                    'huella_id'             => $huella?->id,
                    'turno_nombre'          => $turnoNombre,
                    'hora_entrada_esperada' => $turno?->hora_entrada,
                    'hora_salida_esperada'  => $turno?->hora_salida,
                    'fecha_laboral'         => $fechaLaboral,
                    'hora_entrada'          => $ahora,
                    'minutos_retardo'       => $minutosRetardo,
                    'estatus'               => $estatus,
                    'score_entrada'         => $score,
                    'dispositivo_entrada'   => $dispositivo,
                ]);

                $mensaje = $estatus === 'retardo'
                    ? "Entrada registrada con retardo de {$minutosRetardo} min ({$ahora->format('H:i:s')})."
                    : "Entrada registrada a tiempo ({$ahora->format('H:i:s')}).";

                return [
                    'ok'            => true,
                    'accion'        => 'entrada',
                    'estatus'       => $estatus,
                    'minutos_retardo' => $minutosRetardo,
                    'mensaje'       => $mensaje,
                    'hora_servidor' => $ahora->format('Y-m-d H:i:s'),
                    'asistencia'    => $asistencia->fresh(['usuario', 'turno', 'huella']),
                ];
            }

            // 2) Ya tiene entrada y aún no tiene salida
            if ($asistencia->hora_salida === null) {
                $horaEntrada = $asistencia->hora_entrada->copy()->timezone($ahora->getTimezone());
                $segundosTranscurridos = $horaEntrada->diffInSeconds($ahora, false);

                if ($segundosTranscurridos < ($antiReboteMin * 60)) {
                    $minutosRestantes = max(1, (int) ceil((($antiReboteMin * 60) - max(0, $segundosTranscurridos)) / 60));

                    return [
                        'ok'            => true,
                        'accion'        => 'antirebote',
                        'estatus'       => $asistencia->estatus,
                        'minutos_retardo' => $asistencia->minutos_retardo,
                        'mensaje'       => "Tu entrada ya quedó registrada a las {$horaEntrada->format('H:i:s')}. Intenta nuevamente en {$minutosRestantes} min si deseas registrar salida.",
                        'hora_servidor' => $ahora->format('Y-m-d H:i:s'),
                        'asistencia'    => $asistencia->load(['usuario', 'turno', 'huella']),
                    ];
                }

                $minutosTrabajados = max(0, (int) $horaEntrada->diffInMinutes($ahora));

                $asistencia->update([
                    'hora_salida'        => $ahora,
                    'minutos_trabajados' => $minutosTrabajados,
                    'score_salida'       => $score,
                    'dispositivo_salida' => $dispositivo,
                ]);

                return [
                    'ok'            => true,
                    'accion'        => 'salida',
                    'estatus'       => $asistencia->estatus,
                    'minutos_retardo' => $asistencia->minutos_retardo,
                    'minutos_trabajados' => $minutosTrabajados,
                    'mensaje'       => "Salida registrada correctamente ({$ahora->format('H:i:s')}). Tiempo laborado: {$asistencia->horas_trabajadas_texto}.",
                    'hora_servidor' => $ahora->format('Y-m-d H:i:s'),
                    'asistencia'    => $asistencia->fresh(['usuario', 'turno', 'huella']),
                ];
            }

            // 3) Ya completó entrada y salida en esta jornada laboral
            return [
                'ok'            => true,
                'accion'        => 'jornada_completa',
                'estatus'       => $asistencia->estatus,
                'minutos_retardo' => $asistencia->minutos_retardo,
                'minutos_trabajados' => $asistencia->minutos_trabajados,
                'mensaje'       => sprintf(
                    'Tu jornada del %s ya está completa (Entrada %s · Salida %s).',
                    $fechaLaboral,
                    $asistencia->hora_entrada->format('H:i'),
                    $asistencia->hora_salida->format('H:i')
                ),
                'hora_servidor' => $ahora->format('Y-m-d H:i:s'),
                'asistencia'    => $asistencia->load(['usuario', 'turno', 'huella']),
            ];
        });
    }
}
