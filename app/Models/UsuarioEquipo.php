<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioEquipo extends Model
{
    protected $table = 'usuario_equipos';

    protected $fillable = [
        'user_id',
        'no_serie',
        'numero_pc',
        'tipo',
        'marca',
        'ubicacion',
        'tabla_origen',
        'equipo_id_stellum',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
