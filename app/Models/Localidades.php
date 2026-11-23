<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Localidades extends Model
{
    protected $table = 'localidades';

    protected $fillable = [
        'municipio',
        'nombre',
        'clave',
    ];

    public function municipioRel()
    {
        return $this->belongsTo(Municipios::class, 'municipio');
    }
}
