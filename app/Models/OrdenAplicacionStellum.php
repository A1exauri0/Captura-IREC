<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenAplicacionStellum extends Model
{
    protected $table = 'ordenes_aplicacion_stellum';

    protected $fillable = [
        'tipo_documento',
        'delegacion_id',
        'tipo_sello',
        'aprobado_por',
        'total_archivos',
        'procesados',
        'exitosos',
        'fallidos',
        'estado',
        'detalle_errores',
    ];

    protected $casts = [
        'detalle_errores' => 'array',
    ];

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function delegacion()
    {
        return $this->belongsTo(Delegaciones::class, 'delegacion_id');
    }
}
