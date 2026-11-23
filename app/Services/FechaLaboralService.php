<?php

namespace App\Services;

use App\Models\Turno;
use Illuminate\Support\Facades\Cache;

class FechaLaboralService
{
    /**
     * Obtiene el mapa de turnos adaptativos cacheados para evitar consultas repetidas en bucles.
     */
    public static function obtenerMapaTurnos(): array
    {
        return Cache::remember('mapa_turnos_adaptativos', 300, function () {
            try {
                return Turno::where('estatus', true)->get()->keyBy(function ($item) {
                    return strtolower(trim($item->nombre));
                })->toArray();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /**
     * Calcula la fecha laboral según el turno adaptativo configurado (hora_entrada, hora_salida, cruza_medianoche).
     */
    public static function calcular(?string $fechaCompleta, ?string $turnoNombre = null): string
    {
        if (empty($fechaCompleta) || $fechaCompleta === '1900-01-01 00:00:00' || strlen($fechaCompleta) < 10) {
            return 'Sin Fecha';
        }
        if (strlen($fechaCompleta) === 10) {
            return $fechaCompleta;
        }

        $timestamp = strtotime($fechaCompleta);
        if ($timestamp === false) {
            return 'Sin Fecha';
        }

        $hora = (int) date('H', $timestamp);
        $minuto = (int) date('i', $timestamp);
        $minutosDelDia = ($hora * 60) + $minuto;
        $w = (int) date('w', $timestamp);

        $mapaTurnos = self::obtenerMapaTurnos();
        $keyTurno = strtolower(trim((string) $turnoNombre));
        $turnoConfig = $mapaTurnos[$keyTurno] ?? null;

        if ($turnoConfig) {
            $cruzaMedianoche = !empty($turnoConfig['cruza_medianoche']);
            if ($cruzaMedianoche) {
                // Calcular hora de salida con margen de 2.5 horas (ej. 06:30 -> corte 09:00 AM)
                $partesSalida = explode(':', $turnoConfig['hora_salida'] ?? '06:30:00');
                $minSalida = ((int) ($partesSalida[0] ?? 6) * 60) + (int) ($partesSalida[1] ?? 30) + 150;
                $minCorte = max(540, min(720, $minSalida)); // al menos hasta las 09:00 AM
                if ($minutosDelDia < $minCorte) {
                    return date('Y-m-d', strtotime('-1 day', $timestamp));
                }
            }
            return date('Y-m-d', $timestamp);
        }

        // Fallback clásico para turnos no encontrados en catálogo
        if (($w === 6 || $w === 0) && $hora < 9 && stripos((string) $turnoNombre, 'matutino') === false) {
            return date('Y-m-d', strtotime('-1 day', $timestamp));
        }

        if ($turnoNombre && (
            stripos($turnoNombre, 'matutino') !== false ||
            stripos($turnoNombre, 'vespertino') !== false ||
            stripos($turnoNombre, 'mixto') !== false ||
            stripos($turnoNombre, 'calidad') !== false
        )) {
            return date('Y-m-d', $timestamp);
        }

        if ($hora < 9) {
            return date('Y-m-d', strtotime('-1 day', $timestamp));
        }

        return date('Y-m-d', $timestamp);
    }
}
