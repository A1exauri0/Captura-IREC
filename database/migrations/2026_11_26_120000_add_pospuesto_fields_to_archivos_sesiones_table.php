<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('archivos_sesiones')) {
            return;
        }

        // Asegurar que la columna estado soporte 'pospuesto' además de 'en_captura', 'completado', 'liberado'
        try {
            DB::statement("ALTER TABLE archivos_sesiones MODIFY COLUMN estado VARCHAR(30) NOT NULL DEFAULT 'en_captura'");
        } catch (\Throwable $e) {
            // Ignorar si el motor no requiere MODIFY COLUMN
        }

        Schema::table('archivos_sesiones', function (Blueprint $table) {
            if (!Schema::hasColumn('archivos_sesiones', 'liberado_a_partir_de')) {
                $table->timestamp('liberado_a_partir_de')->nullable()->after('completado_en')->index();
            }
            if (!Schema::hasColumn('archivos_sesiones', 'turno_pospuesto')) {
                $table->string('turno_pospuesto', 60)->nullable()->after('liberado_a_partir_de');
            }
            if (!Schema::hasColumn('archivos_sesiones', 'motivo_pospuesto')) {
                $table->string('motivo_pospuesto', 500)->nullable()->after('turno_pospuesto');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('archivos_sesiones')) {
            return;
        }

        Schema::table('archivos_sesiones', function (Blueprint $table) {
            foreach (['liberado_a_partir_de', 'turno_pospuesto', 'motivo_pospuesto'] as $col) {
                if (Schema::hasColumn('archivos_sesiones', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
