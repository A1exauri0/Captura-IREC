<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'asistencia';

    protected $fillable = [
        'user_id',
        'turno_id',
        'huella_id',
        'turno_nombre',
        'hora_entrada_esperada',
        'hora_salida_esperada',
        'fecha_laboral',
        'hora_entrada',
        'hora_salida',
        'minutos_retardo',
        'minutos_trabajados',
        'estatus',
        'score_entrada',
        'score_salida',
        'dispositivo_entrada',
        'dispositivo_salida',
        'observaciones',
    ];

    protected $casts = [
        'fecha_laboral'      => 'date:Y-m-d',
        'hora_entrada'       => 'datetime',
        'hora_salida'        => 'datetime',
        'minutos_retardo'    => 'integer',
        'minutos_trabajados' => 'integer',
        'score_entrada'      => 'integer',
        'score_salida'       => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function turno()
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function huella()
    {
        return $this->belongsTo(Huella::class, 'huella_id');
    }

    public function getHorasTrabajadasTextoAttribute(): string
    {
        if ($this->minutos_trabajados === null) {
            return 'En turno';
        }
        $horas = intdiv($this->minutos_trabajados, 60);
        $mins = $this->minutos_trabajados % 60;
        return sprintf('%dh %02dm', $horas, $mins);
    }

    public function getEstatusEtiquetaAttribute(): string
    {
        return match ($this->estatus) {
            'a_tiempo' => 'A tiempo',
            'retardo'  => 'Retardo',
            'manual'   => 'Manual',
            default    => ucfirst((string) $this->estatus),
        };
    }
}
