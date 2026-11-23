<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionCapturaUsuario extends Model
{
    protected $table = 'asignaciones_captura_usuario';

    protected $fillable = [
        'user_id',
        'tipo_documento',
        'regla_automatica',
        'delegacion_id',
        'tipo_sello',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function delegacion()
    {
        return $this->belongsTo(Delegaciones::class, 'delegacion_id');
    }
}
