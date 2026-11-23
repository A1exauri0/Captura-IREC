<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class PdfService
{
    /**
     * Inspecciona un archivo PDF físico o de red:
     * - Calcula su hash SHA-256
     * - Cuenta el número de páginas (escáner binario + fallback FPDI)
     * - Detecta si el archivo está dañado (0 bytes, sin cabecera %PDF-, o corrupto)
     *
     * @return array{existe: bool, paginas: int, hash: ?string, archivo_danado: bool, mensaje_error: ?string}
     */
    public static function inspeccionarPdf(?string $rutaFisica): array
    {
        if (empty($rutaFisica) || !@file_exists($rutaFisica) || !@is_file($rutaFisica)) {
            return [
                'existe'         => false,
                'paginas'        => 0,
                'hash'           => null,
                'archivo_danado' => false,
                'mensaje_error'  => 'Archivo no encontrado en disco',
            ];
        }

        $tamano = @filesize($rutaFisica);
        if ($tamano === false || $tamano < 100) {
            return [
                'existe'         => true,
                'paginas'        => 0,
                'hash'           => @hash_file('sha256', $rutaFisica) ?: null,
                'archivo_danado' => true,
                'mensaje_error'  => 'Archivo vacío o truncado (< 100 bytes)',
            ];
        }

        $fp = @fopen($rutaFisica, 'rb');
        if (!$fp) {
            return [
                'existe'         => true,
                'paginas'        => 0,
                'hash'           => null,
                'archivo_danado' => true,
                'mensaje_error'  => 'No se pudo abrir para lectura',
            ];
        }

        // Verificar firma %PDF- en los primeros 1024 bytes
        $cabecera = @fread($fp, 1024);
        if ($cabecera === false || strpos($cabecera, '%PDF-') === false) {
            @fclose($fp);
            return [
                'existe'         => true,
                'paginas'        => 0,
                'hash'           => @hash_file('sha256', $rutaFisica) ?: null,
                'archivo_danado' => true,
                'mensaje_error'  => 'Cabecera %PDF- inválida o ausente',
            ];
        }

        // Escaneo binario rápido de páginas
        @rewind($fp);
        $maxCount = 0;
        $tieneEof = false;
        while (!feof($fp)) {
            $chunk = @fread($fp, 65536);
            if ($chunk === false) {
                break;
            }
            if (strpos($chunk, '%%EOF') !== false) {
                $tieneEof = true;
            }
            if (preg_match_all('/\/Count\s+(\d+)/i', $chunk, $matches)) {
                foreach ($matches[1] as $c) {
                    $cInt = (int) $c;
                    if ($cInt > $maxCount) {
                        $maxCount = $cInt;
                    }
                }
            }
        }
        @fclose($fp);

        $hash = @hash_file('sha256', $rutaFisica) ?: null;

        // Si el escaneo rápido no detectó páginas, intentar con FPDI
        if ($maxCount <= 0 && class_exists(Fpdi::class)) {
            try {
                $pdf = new Fpdi();
                $maxCount = (int) $pdf->setSourceFile($rutaFisica);
            } catch (\Throwable $e) {
                return [
                    'existe'         => true,
                    'paginas'        => 0,
                    'hash'           => $hash,
                    'archivo_danado' => true,
                    'mensaje_error'  => 'Estrutura PDF corrupta: ' . $e->getMessage(),
                ];
            }
        }

        $danado = ($maxCount <= 0) || !$tieneEof;

        return [
            'existe'         => true,
            'paginas'        => max(0, $maxCount),
            'hash'           => $hash,
            'archivo_danado' => $danado,
            'mensaje_error'  => $danado ? 'PDF sin páginas válidas o sin marcador %%EOF' : null,
        ];
    }

    /**
     * Obtiene las rutas configuradas en config/captura_pdf.php para consultar los PDFs a revisar.
     */
    public static function obtenerRutasConfiguradas(?string $tipo = null): array
    {
        $rutasPorTipo = config('captura_pdf.rutas_por_tipo', []);
        if ($tipo && isset($rutasPorTipo[$tipo])) {
            return $rutasPorTipo[$tipo];
        }
        return $rutasPorTipo;
    }
}
