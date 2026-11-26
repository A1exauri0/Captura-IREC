<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControlMuestreo extends Model
{
    protected $table = 'control_muestreos';

    protected $fillable = [
        'fecha_laboral',
        'turno',
        'tipo_documento',
        'regla',
        'universo',
        'muestra',
        'errores_permitidos',
        'revisados',
        'errores_detectados',
        'estado',
        'generado_por',
    ];

    protected $casts = [
        'universo'           => 'integer',
        'muestra'            => 'integer',
        'errores_permitidos' => 'integer',
        'revisados'          => 'integer',
        'errores_detectados' => 'integer',
    ];

    public function archivos()
    {
        return $this->hasMany(ArchivoMuestreo::class, 'control_muestreo_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
