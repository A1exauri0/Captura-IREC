<?php

namespace App\Services;

use App\Models\ArchivoSesion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PdfCacheService
{
    public function __construct(private StellumBridgeService $bridge)
    {
    }

    public function directorio(): string
    {
        $dir = (string) config('services.stellum_bridge.pdf_cache_dir', storage_path('app/pdf-cache'));
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return rtrim($dir, '/\\');
    }

    public function rutaArchivo(int $indiceId): string
    {
        return $this->directorio() . DIRECTORY_SEPARATOR . $indiceId . '.pdf';
    }

    public function existeEnCache(int $indiceId): bool
    {
        $path = $this->rutaArchivo($indiceId);

        return is_file($path) && (int) @filesize($path) > 100;
    }

    /**
     * Garantiza que el PDF exista en el caché local SSD usando bloqueo por archivo y descarga atómica.
     *
     * @return array{ok:bool,path:?string,cached:bool,status:int,error:?string,bytes:int}
     */
    public function asegurarEnCache(int $indiceId, int $timeoutSeconds = 120): array
    {
        if ($indiceId <= 0) {
            return ['ok' => false, 'path' => null, 'cached' => false, 'status' => 404, 'error' => 'ID inválido', 'bytes' => 0];
        }

        $finalPath = $this->rutaArchivo($indiceId);
        if ($this->existeEnCache($indiceId)) {
            @touch($finalPath);

            return [
                'ok'     => true,
                'path'   => $finalPath,
                'cached' => true,
                'status' => 200,
                'error'  => null,
                'bytes'  => (int) @filesize($finalPath),
            ];
        }

        $lockPath = $this->directorio() . DIRECTORY_SEPARATOR . $indiceId . '.lock';
        $lockFp = @fopen($lockPath, 'c+');
        if ($lockFp) {
            @flock($lockFp, LOCK_EX);
        }

        try {
            // Doble verificación tras adquirir lock (otro worker pudo haberlo descargado recién)
            if ($this->existeEnCache($indiceId)) {
                @touch($finalPath);

                return [
                    'ok'     => true,
                    'path'   => $finalPath,
                    'cached' => true,
                    'status' => 200,
                    'error'  => null,
                    'bytes'  => (int) @filesize($finalPath),
                ];
            }

            $tmpPath = $this->directorio() . DIRECTORY_SEPARATOR . $indiceId . '.' . getmypid() . '.tmp';
            @unlink($tmpPath);

            $desc = $this->bridge->descargarPdfAArchivo($indiceId, $tmpPath, $timeoutSeconds);

            if (!$desc['ok'] || !is_file($tmpPath)) {
                @unlink($tmpPath);

                return [
                    'ok'     => false,
                    'path'   => null,
                    'cached' => false,
                    'status' => (int) ($desc['status'] ?? 502),
                    'error'  => $desc['error'] ?? 'No se pudo descargar el PDF desde Stellum',
                    'bytes'  => 0,
                ];
            }

            $size = (int) @filesize($tmpPath);
            $header = '';
            $fh = @fopen($tmpPath, 'rb');
            if ($fh) {
                $header = (string) fread($fh, 5);
                fclose($fh);
            }

            if ($size < 100 || !str_starts_with($header, '%PDF')) {
                @unlink($tmpPath);
                Log::warning('PdfCacheService: respuesta de Stellum no es un PDF válido', [
                    'indice_pdf_id' => $indiceId,
                    'bytes'         => $size,
                ]);

                return [
                    'ok'     => false,
                    'path'   => null,
                    'cached' => false,
                    'status' => 502,
                    'error'  => 'Archivo devuelto por Stellum no es un PDF válido',
                    'bytes'  => 0,
                ];
            }

            if (!@rename($tmpPath, $finalPath)) {
                @copy($tmpPath, $finalPath);
                @unlink($tmpPath);
            }
            @chmod($finalPath, 0664);

            return [
                'ok'     => true,
                'path'   => $finalPath,
                'cached' => false,
                'status' => 200,
                'error'  => null,
                'bytes'  => $size,
            ];
        } finally {
            if ($lockFp) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
            @unlink($lockPath);
        }
    }

    public function eliminarDeCache(int $indiceId): void
    {
        @unlink($this->rutaArchivo($indiceId));
        @unlink($this->directorio() . DIRECTORY_SEPARATOR . $indiceId . '.lock');
    }

    /**
     * Limpia archivos temporales huérfanos, PDFs completados/expirados y garantiza no exceder PDF_CACHE_MAX_MB.
     *
     * @return array{deleted:int,freed_mb:float,remaining_files:int,remaining_mb:float}
     */
    public function purgarExcedentes(): array
    {
        $dir = $this->directorio();
        $maxBytes = max(256, (int) config('services.stellum_bridge.pdf_cache_max_mb', 20480)) * 1024 * 1024;
        $ttlSec = max(1, (int) config('services.stellum_bridge.pdf_cache_ttl_h', 12)) * 3600;
        $ahora = time();

        $deleted = 0;
        $freedBytes = 0;

        // 1. Limpiar .tmp y .lock huérfanos mayores a 20 min
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.{tmp,lock}', GLOB_BRACE) ?: [] as $orphan) {
            if (is_file($orphan) && ($ahora - (int) @filemtime($orphan)) > 1200) {
                @unlink($orphan);
            }
        }

        $enCapturaIds = [];
        $completadosIds = [];
        if (Schema::hasTable('archivos_sesiones')) {
            $enCapturaIds = ArchivoSesion::where('estado', 'en_captura')
                ->whereNotNull('indice_pdf_id')
                ->pluck('indice_pdf_id')
                ->map(fn ($v) => (int) $v)
                ->flip()
                ->all();

            $completadosIds = ArchivoSesion::where('estado', 'completado')
                ->whereNotNull('indice_pdf_id')
                ->pluck('indice_pdf_id')
                ->map(fn ($v) => (int) $v)
                ->flip()
                ->all();
        }

        $archivos = [];
        $totalBytes = 0;

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $id = (int) basename($file, '.pdf');
            $size = (int) @filesize($file);
            $mtime = (int) @filemtime($file);
            $edad = $ahora - $mtime;

            // Nunca borrar un PDF actualmente en_captura
            if (isset($enCapturaIds[$id])) {
                $totalBytes += $size;
                continue;
            }

            // Borrar completados con más de 1 hora o expirados por TTL
            if ((isset($completadosIds[$id]) && $edad > 3600) || $edad > $ttlSec) {
                if (@unlink($file)) {
                    $deleted++;
                    $freedBytes += $size;
                    continue;
                }
            }

            $totalBytes += $size;
            $archivos[] = ['path' => $file, 'size' => $size, 'mtime' => $mtime];
        }

        // 2. Si supera el límite máximo, purgar los más antiguos (LRU) hasta bajar al 85%
        if ($totalBytes > $maxBytes) {
            usort($archivos, fn ($a, $b) => $a['mtime'] <=> $b['mtime']);
            $targetBytes = (int) ($maxBytes * 0.85);

            foreach ($archivos as $item) {
                if ($totalBytes <= $targetBytes) {
                    break;
                }
                if (@unlink($item['path'])) {
                    $deleted++;
                    $freedBytes += $item['size'];
                    $totalBytes -= $item['size'];
                }
            }
        }

        $remainingFiles = count(glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: []);

        return [
            'deleted'         => $deleted,
            'freed_mb'        => round($freedBytes / 1048576, 2),
            'remaining_files' => $remainingFiles,
            'remaining_mb'    => round(max(0, $totalBytes) / 1048576, 2),
        ];
    }

    public function estadisticas(): array
    {
        $dir = $this->directorio();
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        $bytes = 0;
        foreach ($files as $f) {
            $bytes += (int) @filesize($f);
        }

        return [
            'dir'      => $dir,
            'count'    => count($files),
            'total_mb' => round($bytes / 1048576, 2),
            'max_mb'   => (int) config('services.stellum_bridge.pdf_cache_max_mb', 20480),
        ];
    }
}
