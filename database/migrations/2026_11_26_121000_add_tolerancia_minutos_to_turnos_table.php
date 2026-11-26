<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('turnos') && !Schema::hasColumn('turnos', 'tolerancia_minutos')) {
            Schema::table('turnos', function (Blueprint $table) {
                $table->unsignedSmallInteger('tolerancia_minutos')
                    ->default(10)
                    ->after('hora_salida');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('turnos') && Schema::hasColumn('turnos', 'tolerancia_minutos')) {
            Schema::table('turnos', function (Blueprint $table) {
                $table->dropColumn('tolerancia_minutos');
            });
        }
    }
};
