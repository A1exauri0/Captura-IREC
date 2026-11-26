<?php

namespace App\Console\Commands;

use App\Services\RutaPropuestaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AjustarDigitosSellosCommand extends Command
{
    protected $signature = 'sellos:ajustar-digitos
                            {--campo= : Campo a modificar (delegacion|1, seccion|2, libro|3, tomo|4, volumen|5, fraccionamiento|6, anio|7, inscripcion|8, bis|9)}
                            {--digitos= : Cantidad exacta de dígitos para el campo (ej. 3 o 4)}
                            {--min= : Mínimo de dígitos permitido (si se desea un rango en vez de longitud fija)}
                            {--max= : Máximo de dígitos permitido (si se desea un rango en vez de longitud fija)}
                            {--sin-bd : Solo actualizar la regla del sistema sin modificar registros existentes en auditoria_sellos}
                            {--dry-run : Simular el ajuste en auditoria_sellos sin guardar cambios}';

    protected $description = 'Modifica la regla de cantidad de dígitos de un campo de Sellos y ajusta los registros existentes en auditoria_sellos rellenando o recortando ceros a la izquierda cuando es posible.';

    private array $mapaAlias = [
        '1'               => 'delegacion',
        'del'             => 'delegacion',
        'delegacion'      => 'delegacion',
        '2'               => 'seccion',
        'secc'            => 'seccion',
        'seccion'         => 'seccion',
        '3'               => 'libro',
        'libros'          => 'libro',
        'libro'           => 'libro',
        '4'               => 'tomo',
        'tomos'           => 'tomo',
        'tomo'            => 'tomo',
        '5'               => 'volumen',
        'vol'             => 'volumen',
        'volumen'         => 'volumen',
        '6'               => 'fraccionamiento',
        'fracc'           => 'fraccionamiento',
        'fraccionamiento' => 'fraccionamiento',
        '7'               => 'anio',
        'año'             => 'anio',
        'anio'            => 'anio',
        '8'               => 'inscripcion',
        'inscr'           => 'inscripcion',
        'inscripcion'     => 'inscripcion',
        '9'               => 'bis',
        'bis'             => 'bis',
    ];

    public function handle(): int
    {
        $campoRaw = strtolower(trim((string) $this->option('campo')));
        if ($campoRaw === '' || !isset($this->mapaAlias[$campoRaw])) {
            $this->error('Debes indicar un --campo válido: delegacion (1), seccion (2), libro (3), tomo (4), volumen (5), fraccionamiento (6), anio (7), inscripcion (8), bis (9).');
            return self::FAILURE;
        }

        $claveCampo = $this->mapaAlias[$campoRaw];
        $reglas = config('reglas_digitos_sellos');
        $infoCampo = $reglas[$claveCampo];

        $digitosFijos = $this->option('digitos') !== null ? (int) $this->option('digitos') : null;
        $minDig = $this->option('min') !== null ? (int) $this->option('min') : ($digitosFijos ?? $infoCampo['min']);
        $maxDig = $this->option('max') !== null ? (int) $this->option('max') : ($digitosFijos ?? $infoCampo['max']);

        if ($minDig <= 0 || $maxDig < $minDig) {
            $this->error("Rango de dígitos inválido (min={$minDig}, max={$maxDig}).");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sinBd  = (bool) $this->option('sin-bd');

        // 1. Guardar la nueva regla en storage/app/reglas_digitos_sellos.json
        $overridePath = storage_path('app/reglas_digitos_sellos.json');
        $overrides = is_file($overridePath)
            ? (json_decode((string) @file_get_contents($overridePath), true) ?: [])
            : [];

        $overrides[$claveCampo] = [
            'min' => $minDig,
            'max' => $maxDig,
        ];

        if (!$dryRun) {
            file_put_contents($overridePath, json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Regla actualizada para [{$infoCampo['label']}]: min={$minDig}, max={$maxDig} dígitos.");
        } else {
            $this->comment("[DRY-RUN] Se actualizaría la regla para [{$infoCampo['label']}]: min={$minDig}, max={$maxDig} dígitos.");
        }

        if ($sinBd) {
            return self::SUCCESS;
        }

        // 2. Ajustar registros existentes en auditoria_sellos
        $longitudObjetivo = $digitosFijos ?? $minDig;
        $registros = DB::table('auditoria_sellos')
            ->select('id', 'archivo_original', 'archivo_nuevo', 'ruta_origen', 'ruta_propuesta', 'accion')
            ->orderBy('id')
            ->get();

        $ajustados = 0;
        $omitidosPorLongitud = 0;

        foreach ($registros as $row) {
            $nombreActual = trim((string) ($row->archivo_nuevo ?: $row->archivo_original));
            if ($nombreActual === '') {
                continue;
            }

            $nuevoNombre = $this->ajustarSegmentoNombre($nombreActual, $claveCampo, $longitudObjetivo, $minDig, $maxDig, $noAjustable);
            if ($noAjustable) {
                $omitidosPorLongitud++;
                continue;
            }

            if ($nuevoNombre !== $nombreActual) {
                $ajustados++;
                if ($dryRun) {
                    $this->line("  [DRY-RUN] ID #{$row->id}: {$nombreActual} -> {$nuevoNombre}");
                } else {
                    $nuevaRutaPropuesta = $row->ruta_propuesta;
                    if (!empty($row->ruta_origen) && $row->accion !== 'mover_libros') {
                        $nuevaRutaPropuesta = RutaPropuestaService::generar('sellos', [
                            'ruta_origen'      => $row->ruta_origen,
                            'archivo_original' => $row->archivo_original,
                            'archivo_nuevo'    => $nuevoNombre,
                        ], (string) $row->accion);
                    }

                    DB::table('auditoria_sellos')->where('id', $row->id)->update([
                        'archivo_nuevo'  => $nuevoNombre,
                        'ruta_propuesta' => $nuevaRutaPropuesta,
                        'updated_at'     => now(),
                    ]);
                }
            }
        }

        $this->info("Proceso finalizado: {$ajustados} registros ajustados en auditoria_sellos" . ($omitidosPorLongitud > 0 ? " ({$omitidosPorLongitud} omitidos porque su valor significativo excede {$maxDig} dígitos)." : '.'));

        return self::SUCCESS;
    }

    /**
     * Ajusta el segmento correspondiente de un nombre de Sellos (9 bloques) a la longitud de dígitos solicitada.
     */
    private function ajustarSegmentoNombre(
        string $nombre,
        string $claveCampo,
        int $longitudObjetivo,
        int $minDig,
        int $maxDig,
        ?bool &$noAjustable = false
    ): string {
        $noAjustable = false;
        $partes = explode('_', str_replace('-', '_', $nombre));
        $total = count($partes);
        if ($total < 9) {
            return $nombre;
        }

        // Mapear posición real del segmento considerando que Tomo o Fracc pueden contener guiones bajos
        $idxSegmento = match ($claveCampo) {
            'delegacion'  => 0,
            'seccion'     => 1,
            'libro'       => 2,
            'anio'        => $total - 3,
            'inscripcion' => $total - 2,
            'bis'         => $total - 1,
            default       => null,
        };

        if ($idxSegmento === null && $total === 9) {
            $idxSegmento = match ($claveCampo) {
                'tomo'            => 3,
                'volumen'         => 4,
                'fraccionamiento' => 5,
                default           => null,
            };
        }

        if ($idxSegmento === null || !isset($partes[$idxSegmento])) {
            return $nombre;
        }

        $valorOriginal = $partes[$idxSegmento];
        if (!ctype_digit($valorOriginal)) {
            return $nombre;
        }

        $lenActual = strlen($valorOriginal);
        if ($lenActual < $minDig || $lenActual < $longitudObjetivo) {
            // Rellenar con ceros a la izquierda (ej. 003 -> 0003)
            $partes[$idxSegmento] = str_pad($valorOriginal, $longitudObjetivo, '0', STR_PAD_LEFT);
        } elseif ($lenActual > $maxDig || $lenActual > $longitudObjetivo) {
            // Recortar ceros a la izquierda solo si no se pierden dígitos significativos (ej. 0003 -> 003)
            $sinCeros = ltrim($valorOriginal, '0');
            if ($sinCeros === '') {
                $sinCeros = '0';
            }
            if (strlen($sinCeros) <= $longitudObjetivo) {
                $partes[$idxSegmento] = str_pad($sinCeros, $longitudObjetivo, '0', STR_PAD_LEFT);
            } else {
                $noAjustable = true;
                return $nombre;
            }
        }

        return implode('_', $partes);
    }
}
