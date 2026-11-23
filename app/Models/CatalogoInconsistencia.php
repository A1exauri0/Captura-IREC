<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoInconsistencia extends Model
{
    protected $table = 'catalogo_inconsistencias';

    protected $fillable = [
        'codigo',
        'motivo_rechazo',
        'accion_correctiva',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
