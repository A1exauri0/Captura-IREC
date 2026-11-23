<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeticionAutorizacion extends Model
{
    protected $table = 'peticiones_autorizacion';

    protected $fillable = [
        'user_id',
        'tipo_peticion',
        'accion_solicitada',
        'disco',
        'lote',
        'archivo_nombre',
        'ruta_origen',
        'ruta_destino',
        'paginas',
        'hash',
        'archivo_danado',
        'motivo',
        'estado',
        'autorizado_por',
        'comentario_resolucion',
        'resuelto_en',
    ];

    protected $casts = [
        'archivo_danado' => 'boolean',
        'paginas' => 'integer',
        'resuelto_en' => 'datetime',
    ];

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }
}
