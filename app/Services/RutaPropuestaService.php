<?php

namespace App\Services;

class RutaPropuestaService
{
    /**
     * Calcula la ruta propuesta según el tipo de documento y acción, usando config/reglas_destino_pdf.php.
     */
    public static function generar(string $tipoDocumento, array $datos, string $accion = 'renombrado'): string
    {
        $tipo = strtolower(trim($tipoDocumento));
        $reglas = config('reglas_destino_pdf.reglas', []);
        $regla = $reglas[$tipo] ?? $reglas['registros'] ?? null;

        $base = rtrim((string) config('reglas_destino_pdf.base_destino', '\\\\172.40.5.84\\ssdirec\\ENTREGABLES_APROBADOS'), '\\/');

        $clavePlantilla = match (strtolower($accion)) {
            'duplicar'       => 'carpeta_duplicado',
            'inconsistencia' => 'carpeta_inconsistencia',
            default          => 'plantilla',
        };

        $plantilla = $regla[$clavePlantilla] ?? '{base_destino}\\{tipo}\\{delegacion}\\{archivo_nuevo}.pdf';

        $archivoNuevo    = preg_replace('/\.pdf$/i', '', trim((string) ($datos['archivo_nuevo'] ?? $datos['archivo_original'] ?? 'SIN_NOMBRE')));
        $archivoOriginal = preg_replace('/\.pdf$/i', '', trim((string) ($datos['archivo_original'] ?? $archivoNuevo)));

        // Extraer año de 4 dígitos si existe en el nombre del archivo
        $anio = $datos['anio'] ?? null;
        if (empty($anio) && preg_match('/\b(19\d{2}|20\d{2})\b/', $archivoNuevo, $m)) {
            $anio = $m[1];
        }
        $anio = $anio ?: date('Y');

        $reemplazos = [
            '{base_destino}'     => $base,
            '{tipo}'             => strtoupper($tipo),
            '{delegacion}'       => self::limpiarSegmento($datos['delegacion'] ?? 'GENERAL'),
            '{tipo_sello}'       => self::limpiarSegmento($datos['tipo_sello'] ?? 'GENERAL'),
            '{anio}'             => self::limpiarSegmento((string) $anio),
            '{lote}'             => self::limpiarSegmento($datos['lote'] ?? 'LOTE_GENERAL'),
            '{directorio}'       => self::limpiarSegmento($datos['directorio'] ?? 'GENERAL'),
            '{codigo}'           => self::limpiarSegmento($datos['inconsistencia_codigo'] ?? 'R00'),
            '{archivo_nuevo}'    => self::limpiarSegmento($archivoNuevo),
            '{archivo_original}' => self::limpiarSegmento($archivoOriginal),
        ];

        return str_replace(array_keys($reemplazos), array_values($reemplazos), $plantilla);
    }

    private static function limpiarSegmento(string $valor): string
    {
        $limpio = preg_replace('/[<>:"\/|?*]+/', '_', trim($valor));
        return $limpio !== '' ? $limpio : 'SIN_DATO';
    }
}
