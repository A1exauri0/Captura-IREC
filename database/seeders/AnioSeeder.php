<?php

namespace Database\Seeders;

use App\Models\Anio;
use Illuminate\Database\Seeder;

class AnioSeeder extends Seeder
{
    /**
     * Siembra el catálogo de años desde 1850 hasta 2030 de forma idempotente.
     */
    public function run(): void
    {
        for ($anio = 1850; $anio <= 2030; $anio++) {
            Anio::firstOrCreate(
                ['nombre' => (string) $anio],
                ['estatus' => 1]
            );
        }
    }
}
