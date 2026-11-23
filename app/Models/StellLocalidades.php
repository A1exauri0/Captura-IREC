<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StellLocalidades extends Model
{
    protected $table = 'stelllocalidades';

    protected $fillable = [
        'municipio',
        'nombre',
        'clave',
    ];

    public function municipioRel()
    {
        return $this->belongsTo(StellMunicipios::class, 'municipio');
    }
}
