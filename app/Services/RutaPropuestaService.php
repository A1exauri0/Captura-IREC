<?php

namespace App\Services;

class RutaPropuestaService
{
    /**
     * Calcula la ruta propuesta manteniendo la misma ruta de entregables de `ruta_origen`,
     * modificando la carpeta inmediata a partir de los segmentos hasta el año (inclusive)
     * y manteniendo el nombre final propuesto del archivo.
     *
     * Ejemplo:
     *   ruta_origen:    \\host\...\SELLOS\Villaflores\15_05_01_00_00_00_2001\15_05_01_00_00_00_2001_0023_00.pdf
     *   archivo_nuevo:  15_05_01_00_00_00_2000_0023_00
     *   ruta_propuesta: \\host\...\SELLOS\Villaflores\15_05_01_00_00_00_2000\15_05_01_00_00_00_2000_0023_00.pdf
     */
    public static function generar(string $tipoDocumento, array $datos, string $accion = 'renombrado'): string
    {
        $rutaOrigen = trim((string) ($datos['ruta_origen'] ?? ''));
        $archivoNuevo = self::limpiarSegmento(
            preg_replace('/\.pdf$/i', '', trim((string) ($datos['archivo_nuevo'] ?? $datos['archivo_original'] ?? 'SIN_NOMBRE')))
        );
        $archivoOriginal = self::limpiarSegmento(
            preg_replace('/\.pdf$/i', '', trim((string) ($datos['archivo_original'] ?? $archivoNuevo)))
        );

        if ($rutaOrigen === '') {
            return $archivoNuevo . '.pdf';
        }

        // Si es inconsistencia, no se propone reubicación en entregables
        if (strtolower($accion) === 'inconsistencia') {
            return $rutaOrigen;
        }

        $usaBackslash = str_contains($rutaOrigen, '\\');
        $sep = $usaBackslash ? '\\' : '/';
        $esUnc = str_starts_with($rutaOrigen, '\\\\');

        // Normalizar separadores a '/' para manipular directorios con precisión
        $norm = str_replace('\\', '/', $rutaOrigen);
        $ext = pathinfo($norm, PATHINFO_EXTENSION);
        $ext = $ext !== '' ? $ext : 'pdf';

        $carpetaActual = dirname($norm);           // ej: //172.../SELLOS/Villaflores/15_05_01_00_00_00_2001
        $carpetaBase   = dirname($carpetaActual);  // ej: //172.../SELLOS/Villaflores
        $nombreCarpetaActual = basename($carpetaActual);

        // Construir nombre de carpeta usando desde el inicio hasta el año (4 dígitos: 18xx, 19xx, 20xx)
        $carpetaNueva = $nombreCarpetaActual;
        if (preg_match('/^(.*_(?:18|19|20)\d{2})(?:_|$)/', $archivoNuevo, $m)) {
            $carpetaNueva = $m[1];
        }

        $rutaNorm = rtrim($carpetaBase, '/') . '/' . $carpetaNueva . '/' . $archivoNuevo . '.' . $ext;

        if ($usaBackslash) {
            $rutaFinal = str_replace('/', '\\', $rutaNorm);
            if ($esUnc && !str_starts_with($rutaFinal, '\\\\')) {
                $rutaFinal = '\\' . ltrim($rutaFinal, '\\');
            }
            return $rutaFinal;
        }

        return $rutaNorm;
    }

    private static function limpiarSegmento(string $valor): string
    {
        $limpio = preg_replace('/[<>:"\/|?*]+/', '_', trim($valor));
        return $limpio !== '' ? $limpio : 'SIN_DATO';
    }
}
