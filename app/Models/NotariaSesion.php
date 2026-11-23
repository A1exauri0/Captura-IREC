<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotariaSesion extends Model
{
    protected $table = 'notarias_sesiones';

    protected $fillable = [
        'user_id',
        'notaria_nombre',
        'volumen',
        'ruta_relativa',
        'ruta_origen',
        'ruta_destino',
        'paginas',
        'hash',
        'archivo_danado',
        'disco',
        'pc',
        'estado',
        'tomado_en',
        'completado_en',
    ];

    protected $casts = [
        'tomado_en' => 'datetime',
        'completado_en' => 'datetime',
        'archivo_danado' => 'boolean',
        'paginas' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
