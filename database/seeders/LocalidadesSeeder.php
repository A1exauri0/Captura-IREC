<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LocalidadesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Llamamos al comando que ya creamos para evitar duplicar la lógica
        // y asegurar que se ejecute correctamente durante migrate:fresh --seed
        $this->command->call('importar:localidades-csv');
    }
}
