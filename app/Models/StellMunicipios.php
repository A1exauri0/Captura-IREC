<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StellMunicipios extends Model
{
    protected $table = 'stellmunicipios';

    protected $fillable = [
        'estado',
        'nombre',
        'clave',
    ];

    public function localidades()
    {
        return $this->hasMany(StellLocalidades::class, 'municipio');
    }
}
