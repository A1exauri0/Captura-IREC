<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pcs_captura')) {
            Schema::create('pcs_captura', function (Blueprint $table) {
                $table->id();
                $table->string('numero_pc', 30)->unique();
                $table->string('no_serie', 100)->nullable();
                $table->string('ubicacion', 120)->nullable();
                $table->string('descripcion', 255)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });

            // Precargar PC-01 a PC-20 por defecto para uso inmediato
            $now = now();
            $rows = [];
            for ($i = 1; $i <= 20; $i++) {
                $rows[] = [
                    'numero_pc'  => 'PC-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'activo'     => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('pcs_captura')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pcs_captura');
    }
};
