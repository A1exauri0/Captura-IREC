<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteCaptura extends Model
{
    protected $table = 'lotes_captura';

    protected $fillable = [
        'disco',
        'tipo_archivo',
        'delegacion_id',
        'notaria_nombre',
        'volumen',
        'pc',
        'nombre_lote',
        'ruta_relativa',
        'ruta_origen',
        'ruta_destino',
        'paginas',
        'hash',
        'archivo_danado',
        'estado',
        'user_id',
        'tomado_en',
        'completado_en',
        'progreso_archivo',
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

    public function delegacion()
    {
        return $this->belongsTo(Delegaciones::class, 'delegacion_id');
    }
}
