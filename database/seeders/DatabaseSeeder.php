<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Turno;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TurnosSeeder::class,
            RolesSeeder::class,
            AnioSeeder::class,
            DelegacionesSeeder::class,
            MunicipiosSeeder::class,
            LocalidadesSeeder::class,
            FraccionamientosYColoniasSeeder::class,
            UsersSeeder::class,
        ]);

        // Sincronizar turno_id en todos los usuarios según su campo 'turno'
        $mapaTurnos = Turno::pluck('id', 'nombre')->toArray();
        foreach ($mapaTurnos as $nombre => $id) {
            DB::table('users')
                ->whereRaw('LOWER(turno) = ?', [strtolower($nombre)])
                ->update(['turno_id' => $id]);
        }
    }
}
