<?php

namespace App\Console\Commands;

use App\Models\ArchivoSesion;
use App\Services\PdfCacheService;
use App\Services\StellumBridgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PrefetchPdfsCommand extends Command
{
    protected $signature = 'pdfs:prefetch
        {--buffer= : Cantidad de PDFs pendientes a mantener listos en caché SSD}
        {--tipo=sellos : Tipo documental en Stellum}
        {--daemon : Ejecutar continuamente (ideal para Supervisor)}
        {--sleep=5 : Segundos de espera entre ciclos en modo daemon}
        {--max-cycles=250 : Máximo de ciclos antes de salir limpiamente para reciclaje de memoria}';

    protected $description = 'Pre-descarga de forma regulada los próximos PDFs pendientes desde Stellum hacia el caché local SSD.';

    public function handle(StellumBridgeService $bridge, PdfCacheService $cache): int
    {
        $buffer = (int) ($this->option('buffer') ?: config('services.stellum_bridge.pdf_prefetch_buf', 60));
        $tipo = (string) ($this->option('tipo') ?: 'sellos');
        $daemon = (bool) $this->option('daemon');
        $sleepSec = max(1, (int) $this->option('sleep'));
        $maxCycles = max(1, (int) $this->option('max-cycles'));

        $cycle = 0;
        do {
            $cycle++;
            $this->ejecutarCiclo($bridge, $cache, $buffer, $tipo, $cycle);

            if (!$daemon || $cycle >= $maxCycles) {
                break;
            }

            sleep($sleepSec);
        } while (true);

        return self::SUCCESS;
    }

    private function ejecutarCiclo(
        StellumBridgeService $bridge,
        PdfCacheService $cache,
        int $buffer,
        string $tipo,
        int $cycle
    ): void {
        // Purgar excedentes cada 10 ciclos (y en el primer ciclo)
        if ($cycle === 1 || $cycle % 10 === 0) {
            $purga = $cache->purgarExcedentes();
            if ($purga['deleted'] > 0) {
                $this->info("[prefetch] Purgados {$purga['deleted']} PDFs ({$purga['freed_mb']} MB liberados). Restantes: {$purga['remaining_files']} ({$purga['remaining_mb']} MB)");
            }
        }

        // 1. Priorizar PDFs actualmente en_captura que aún no estén en caché
        $candidatos = [];
        $completadosMap = [];

        if (Schema::hasTable('archivos_sesiones')) {
            $activos = ArchivoSesion::where('estado', 'en_captura')
                ->whereNotNull('indice_pdf_id')
                ->orderBy('id')
                ->pluck('indice_pdf_id');

            foreach ($activos as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $candidatos[$id] = true;
                }
            }

            $completadosMap = ArchivoSesion::where('estado', 'completado')
                ->whereNotNull('indice_pdf_id')
                ->pluck('indice_pdf_id')
                ->map(fn ($v) => (int) $v)
                ->flip()
                ->all();
        }

        // 2. Recorrer el índice de Stellum por cursor hasta juntar $buffer PDFs pendientes
        $afterId = 0;
        $maxPaginas = 20;

        for ($p = 0; $p < $maxPaginas && count($candidatos) < $buffer; $p++) {
            $resp = $bridge->listarIndice([
                'tipo'          => $tipo,
                'clasificacion' => 'entregable',
                'after_id'      => $afterId,
                'limit'         => 100,
            ]);

            if (empty($resp['ok'])) {
                $this->warn('[prefetch] Stellum no respondió al índice: ' . ($resp['error'] ?? 'error'));
                break;
            }

            $items = $resp['items'] ?? [];
            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $id = isset($item['id']) ? (int) $item['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                if ($id > $afterId) {
                    $afterId = $id;
                }
                if (isset($completadosMap[$id])) {
                    continue;
                }
                $candidatos[$id] = true;
                if (count($candidatos) >= $buffer) {
                    break;
                }
            }

            $nextAfter = (int) ($resp['next_after_id'] ?? 0);
            if ($nextAfter > $afterId) {
                $afterId = $nextAfter;
            }
            if (count($items) < 100) {
                break;
            }
        }

        // 3. Descargar los que faltan en caché SSD (de forma secuencial/regulada por worker)
        $descargados = 0;
        $yaEnCache = 0;

        foreach (array_keys($candidatos) as $id) {
            if ($cache->existeEnCache($id)) {
                $yaEnCache++;
                continue;
            }

            $inicio = microtime(true);
            $res = $cache->asegurarEnCache($id, 120);
            $elapsed = round(microtime(true) - $inicio, 2);

            if ($res['ok']) {
                if (!$res['cached']) {
                    $descargados++;
                    $mb = round(($res['bytes'] ?? 0) / 1048576, 2);
                    $this->info("[prefetch] id={$id} cacheado ({$mb} MB en {$elapsed}s)");
                } else {
                    $yaEnCache++;
                }
            } else {
                $this->warn("[prefetch] id={$id} falló (HTTP {$res['status']}): {$res['error']}");
            }
        }

        if ($descargados > 0 || $cycle === 1) {
            $stats = $cache->estadisticas();
            $this->line("[prefetch] Ciclo #{$cycle}: objetivo=" . count($candidatos) . ", en_cache={$yaEnCache}, nuevos={$descargados}, total_disco={$stats['count']} ({$stats['total_mb']} MB)");
        }
    }
}
