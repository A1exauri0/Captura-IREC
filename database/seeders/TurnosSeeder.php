<?php

namespace Database\Seeders;

use App\Models\Turno;
use Illuminate\Database\Seeder;

class TurnosSeeder extends Seeder
{
    public function run(): void
    {
        $turnos = [
            [
                'nombre'           => 'Matutino',
                'hora_entrada'     => '06:00:00',
                'hora_salida'      => '12:00:00',
                'cruza_medianoche' => false,
                'color_hex'        => '#10b981',
                'descripcion'      => 'Turno matutino estándar (06:00 a 12:00)',
                'estatus'          => true,
            ],
            [
                'nombre'           => 'Vespertino',
                'hora_entrada'     => '14:00:00',
                'hora_salida'      => '20:00:00',
                'cruza_medianoche' => false,
                'color_hex'        => '#f59e0b',
                'descripcion'      => 'Turno vespertino estándar (14:00 a 20:00)',
                'estatus'          => true,
            ],
            [
                'nombre'           => 'Mixto',
                'hora_entrada'     => '09:00:00',
                'hora_salida'      => '18:00:00',
                'cruza_medianoche' => false,
                'color_hex'        => '#8b5cf6',
                'descripcion'      => 'Turno mixto / intermedio (09:00 a 18:00)',
                'estatus'          => true,
            ],
            [
                'nombre'           => 'Calidad',
                'hora_entrada'     => '09:00:00',
                'hora_salida'      => '18:00:00',
                'cruza_medianoche' => false,
                'color_hex'        => '#06b6d4',
                'descripcion'      => 'Turno de revisión y control de calidad (09:00 a 18:00)',
                'estatus'          => true,
            ],
        ];

        foreach ($turnos as $t) {
            Turno::updateOrCreate(['nombre' => $t['nombre']], $t);
        }
    }
}
