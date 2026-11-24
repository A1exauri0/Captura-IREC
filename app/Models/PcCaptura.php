<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcCaptura extends Model
{
    protected $table = 'pcs_captura';

    protected $fillable = [
        'numero_pc',
        'no_serie',
        'ubicacion',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
