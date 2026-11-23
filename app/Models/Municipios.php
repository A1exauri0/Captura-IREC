<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipios extends Model
{
    protected $table = 'municipios';

    protected $fillable = [
        'estado',
        'nombre',
        'clave',
    ];

    public function localidades()
    {
        return $this->hasMany(Localidades::class, 'municipio');
    }
}
