<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('huellas')) {
            return;
        }

        Schema::table('huellas', function (Blueprint $table) {
            if (!Schema::hasColumn('huellas', 'numero_muestra')) {
                $table->unsignedTinyInteger('numero_muestra')
                    ->default(1)
                    ->after('dedo');
            }
            if (!Schema::hasColumn('huellas', 'grupo_enrolamiento')) {
                $table->string('grupo_enrolamiento', 36)
                    ->nullable()
                    ->after('numero_muestra')
                    ->index('idx_huellas_grupo');
            }
        });

        // Asegurar valor por defecto 'pulgar_derecho' en columna dedo
        Schema::table('huellas', function (Blueprint $table) {
            $table->string('dedo', 40)->default('pulgar_derecho')->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('huellas')) {
            return;
        }

        Schema::table('huellas', function (Blueprint $table) {
            if (Schema::hasColumn('huellas', 'grupo_enrolamiento')) {
                $table->dropIndex('idx_huellas_grupo');
                $table->dropColumn('grupo_enrolamiento');
            }
            if (Schema::hasColumn('huellas', 'numero_muestra')) {
                $table->dropColumn('numero_muestra');
            }
        });
    }
};
