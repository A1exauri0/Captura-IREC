<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditoriaNotarias extends Model
{
    use HasFactory;

    protected $table = 'auditoria_notarias';

    protected $fillable = [
        'fecha_hora',
        'turno',
        'user_id',
        'categoria',
        'pc',
        'directorio',
        'volumen',
        'lote',
        'accion',
        'archivo_original',
        'archivo_nuevo',
        'ruta',
        'ruta_origen',
        'ruta_propuesta',
        'ruta_destino',
        'indice_pdf_id',
        'revisar',
        'archivo_colision_id',
        'ruta_colision',
        'decision_revision',
        'inconsistencia_codigo',
        'inconsistencia_estado',
        'inconsistencia_comentario',
        'estado_aplicacion',
        'orden_aplicacion_id',
        'hash',
        'archivo_danado',
        'detalles',
        'paginas',
        'tiempo_captura',
    ];

    protected $casts = [
        'archivo_danado' => 'boolean',
        'revisar'        => 'boolean',
        'paginas'        => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
