<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraccionamientoColonia extends Model
{
    protected $table = 'fraccionamientos_y_colonias';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'nombre',
    ];
}
