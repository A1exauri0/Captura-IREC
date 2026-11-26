<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    use HasFactory;

    protected $table = 'turnos';

    protected $fillable = [
        'nombre',
        'hora_entrada',
        'hora_salida',
        'tolerancia_minutos',
        'cruza_medianoche',
        'color_hex',
        'descripcion',
        'estatus',
    ];

    protected $casts = [
        'tolerancia_minutos' => 'integer',
        'cruza_medianoche'   => 'boolean',
        'estatus'            => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class, 'turno_id');
    }

    /**
     * Verifica si el momento indicado (hora del servidor) está dentro del horario del turno.
     */
    public function estaEnHorario(?\Carbon\Carbon $ahora = null): bool
    {
        if (!$this->estatus || empty($this->hora_entrada) || empty($this->hora_salida)) {
            return true;
        }

        $ahora = $ahora ?: now();
        $horaActual = $ahora->format('H:i:s');
        $entrada = strlen($this->hora_entrada) === 5 ? $this->hora_entrada . ':00' : $this->hora_entrada;
        $salida  = strlen($this->hora_salida) === 5  ? $this->hora_salida . ':00'  : $this->hora_salida;

        if ($this->cruza_medianoche || $salida < $entrada) {
            return $horaActual >= $entrada || $horaActual <= $salida;
        }

        return $horaActual >= $entrada && $horaActual <= $salida;
    }

    public function rangoTexto(): string
    {
        $ent = substr((string) $this->hora_entrada, 0, 5);
        $sal = substr((string) $this->hora_salida, 0, 5);
        return "{$this->nombre} ({$ent} - {$sal})";
    }

    /**
     * Calcula los minutos de retardo respecto a la hora_entrada esperada en la fecha_laboral indicada.
     * Retorna 0 si el empleado ingresó dentro de la tolerancia configurada.
     */
    public function calcularRetardoMinutos(\Carbon\Carbon $horaEntrada, string $fechaLaboral, ?int $toleranciaFallback = null): int
    {
        if (empty($this->hora_entrada)) {
            return 0;
        }

        $entradaStr = strlen((string) $this->hora_entrada) === 5
            ? $this->hora_entrada . ':00'
            : (string) $this->hora_entrada;

        $tz = $horaEntrada->getTimezone();
        $esperada = \Carbon\Carbon::parse("{$fechaLaboral} {$entradaStr}", $tz);

        $tolerancia = $this->tolerancia_minutos !== null
            ? (int) $this->tolerancia_minutos
            : (int) ($toleranciaFallback ?? config('services.biometrico.tolerancia_default', 10));

        $limite = $esperada->copy()->addMinutes(max(0, $tolerancia));

        if ($horaEntrada->lte($limite)) {
            return 0;
        }

        return (int) max(1, $esperada->diffInMinutes($horaEntrada));
    }
}
