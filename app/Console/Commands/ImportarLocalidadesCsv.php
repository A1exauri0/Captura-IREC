<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StellLocalidades;
use Illuminate\Support\Facades\DB;

class ImportarLocalidadesCsv extends Command
{
    protected $signature = 'importar:localidades-csv';

    protected $description = 'Lee el archivo localidades.csv y lo inserta en la tabla stelllocalidades';

    public function handle()
    {
        $rutaArchivo = storage_path('app/localidades.csv');

        if (!file_exists($rutaArchivo)) {
            $this->error("Error: El archivo no se encuentra en $rutaArchivo");
            return 1;
        }

        $this->info("Iniciando importación de localidades...");

        $file = fopen($rutaArchivo, 'r');
        fgetcsv($file);

        $lote = [];
        $tamanoLote = 1000;
        $totalProcesados = 0;

        DB::table('stelllocalidades')->truncate();

        while (($fila = fgetcsv($file)) !== false) {
            if (empty($fila) || count($fila) < 3) {
                continue;
            }

            $lote[] = [
                'municipio' => (int) trim($fila[0]),
                'nombre' => mb_convert_encoding(trim($fila[1]), 'UTF-8', 'UTF-8'),
                'clave' => trim($fila[2]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($lote) >= $tamanoLote) {
                StellLocalidades::insert($lote);
                $totalProcesados += count($lote);
                $lote = [];
            }
        }

        if (!empty($lote)) {
            StellLocalidades::insert($lote);
            $totalProcesados += count($lote);
        }

        fclose($file);

        $this->info("¡Importación finalizada exitosamente! Total: $totalProcesados");

        return 0;
    }
}
