<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivoMuestreo extends Model
{
    protected $table = 'archivos_muestreos';

    protected $fillable = [
        'control_muestreo_id',
        'auditoria_sello_id',
        'indice_pdf_id',
        'delegacion_id',
        'ruta_origen',
        'archivo_original',
        'archivo_capturado',
        'archivo_muestreo',
        'accion_muestreo',
        'paginas',
        'hash',
        'capturista_id',
        'calidad_user_id',
        'estado',
        'es_correcto',
        'detalles',
        'tomado_en',
        'completado_en',
    ];

    protected $casts = [
        'paginas'       => 'integer',
        'es_correcto'   => 'boolean',
        'tomado_en'     => 'datetime',
        'completado_en' => 'datetime',
    ];

    public function controlMuestreo()
    {
        return $this->belongsTo(ControlMuestreo::class, 'control_muestreo_id');
    }

    public function auditoriaSello()
    {
        return $this->belongsTo(AuditoriaSellos::class, 'auditoria_sello_id');
    }

    public function capturista()
    {
        return $this->belongsTo(User::class, 'capturista_id');
    }

    public function revisorCalidad()
    {
        return $this->belongsTo(User::class, 'calidad_user_id');
    }

    public function delegacion()
    {
        return $this->belongsTo(Delegaciones::class, 'delegacion_id');
    }
}
