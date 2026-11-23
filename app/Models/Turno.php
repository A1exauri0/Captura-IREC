<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    use HasFactory;

    protected $table = 'turnos';

    protected $fillable = [
        'nombre',
        'hora_entrada',
        'hora_salida',
        'cruza_medianoche',
        'color_hex',
        'descripcion',
        'estatus',
    ];

    protected $casts = [
        'cruza_medianoche' => 'boolean',
        'estatus' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class, 'turno_id');
    }
}
