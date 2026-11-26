<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivoSesion extends Model
{
    protected $table = 'archivos_sesiones';

    protected $fillable = [
        'user_id',
        'indice_pdf_id',
        'disco',
        'tipo_archivo',
        'delegacion_id',
        'pc',
        'archivo_nombre',
        'ruta_origen',
        'ruta_hash',
        'paginas',
        'hash_pdf',
        'archivo_danado',
        'estado',
        'tomado_en',
        'expira_en',
        'completado_en',
        'tiempo_segundos',
        'liberado_a_partir_de',
        'turno_pospuesto',
        'motivo_pospuesto',
    ];

    protected $casts = [
        'tomado_en'            => 'datetime',
        'expira_en'            => 'datetime',
        'completado_en'        => 'datetime',
        'liberado_a_partir_de' => 'datetime',
        'archivo_danado'       => 'boolean',
        'paginas'              => 'integer',
        'tiempo_segundos'      => 'integer',
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
