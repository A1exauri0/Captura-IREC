<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Huella extends Model
{
    use HasFactory;

    protected $table = 'huellas';

    public const DEDO_DEFAULT = 'pulgar_derecho';
    public const MUESTRAS_POR_DEDO = 3;

    public const DEDOS_VALIDOS = [
        'pulgar_derecho'    => 'Pulgar Derecho',
        'pulgar_izquierdo'  => 'Pulgar Izquierdo',
        'indice_derecho'    => 'Índice Derecho',
        'indice_izquierdo'  => 'Índice Izquierdo',
        'medio_derecho'     => 'Medio Derecho',
        'medio_izquierdo'   => 'Medio Izquierdo',
        'anular_derecho'    => 'Anular Derecho',
        'anular_izquierdo'  => 'Anular Izquierdo',
        'menique_derecho'   => 'Meñique Derecho',
        'menique_izquierdo' => 'Meñique Izquierdo',
    ];

    protected $fillable = [
        'user_id',
        'dedo',
        'numero_muestra',
        'grupo_enrolamiento',
        'etiqueta',
        'template',
        'muestra_1',
        'muestra_2',
        'muestra_3',
        'template_hash',
        'formato',
        'calidad',
        'dispositivo_modelo',
        'registrado_por',
        'activo',
        'ultimo_uso_en',
    ];

    protected $hidden = [
        'muestra_1',
        'muestra_2',
        'muestra_3',
    ];

    protected $casts = [
        'numero_muestra' => 'integer',
        'calidad'        => 'integer',
        'activo'         => 'boolean',
        'ultimo_uso_en'  => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'huella_id');
    }

    public function getDedoNombreAttribute(): string
    {
        return self::DEDOS_VALIDOS[$this->dedo] ?? ucfirst(str_replace('_', ' ', (string) $this->dedo));
    }
}
