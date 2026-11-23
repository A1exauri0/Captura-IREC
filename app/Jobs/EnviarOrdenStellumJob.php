<?php

namespace App\Jobs;

use App\Models\OrdenAplicacionStellum;
use App\Services\StellumBridgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnviarOrdenStellumJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;

    protected int $ordenId;

    public function __construct(int $ordenId)
    {
        $this->ordenId = $ordenId;
    }

    public function handle(StellumBridgeService $bridge): void
    {
        $orden = OrdenAplicacionStellum::with('delegacion')->find($this->ordenId);
        if (!$orden) {
            return;
        }

        $tabla = match ($orden->tipo_documento) {
            'sellos'   => 'auditoria_sellos',
            'notarias' => 'auditoria_notarias',
            'catastro' => 'auditoria_catastro',
            default    => 'auditoria_registros',
        };

        $orden->update(['estado' => 'enviando']);
        $delegacionNombre = $orden->delegacion?->nombre;
        $errores = [];
        $enviadosTotal = 0;

        $query = DB::table($tabla)
            ->where('orden_aplicacion_id', $orden->id)
            ->where('estado_aplicacion', 'aprobado')
            ->where('revisar', false)
            ->orderBy('id');

        // Procesar por chunks de 500 con chunkById (seguro para millones de registros)
        $query->chunkById(500, function ($rows) use ($bridge, $orden, $tabla, $delegacionNombre, &$errores, &$enviadosTotal) {
            $items = [];
            $ids = [];

            foreach ($rows as $row) {
                $ids[] = $row->id;
                $items[] = [
                    'auditoria_id'    => $row->id,
                    'indice_pdf_id'   => $row->indice_pdf_id ?? null,
                    'accion'          => $row->accion,
                    'ruta_origen'     => $row->ruta_origen ?: $row->ruta,
                    'ruta_propuesta'  => $row->ruta_propuesta ?: $row->ruta_destino,
                    'archivo_nuevo'   => $row->archivo_nuevo,
                    'eliminar_fisico' => ($row->decision_revision === 'conservar_existente'),
                ];
            }

            $resp = $bridge->enviarChunkAplicacion(
                $orden->id,
                $orden->tipo_documento,
                $delegacionNombre,
                (int) $orden->total_archivos,
                $items
            );

            if (!empty($resp['ok'])) {
                DB::table($tabla)->whereIn('id', $ids)->update([
                    'estado_aplicacion' => 'enviado_stellum',
                    'updated_at'        => now(),
                ]);
                $enviadosTotal += count($ids);
            } else {
                DB::table($tabla)->whereIn('id', $ids)->update([
                    'estado_aplicacion' => 'error',
                    'updated_at'        => now(),
                ]);
                $errores[] = $resp['error'] ?? ('HTTP ' . ($resp['status'] ?? 'error'));
                Log::error("EnviarOrdenStellumJob fallo chunk orden {$orden->id}", $resp);
            }
        });

        $orden->update([
            'estado'          => empty($errores) ? 'en_proceso' : 'error',
            'procesados'      => $enviadosTotal,
            'detalle_errores' => $errores ?: null,
        ]);
    }
}
