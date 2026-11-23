<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'equipo_no_serie')) {
                $table->string('equipo_no_serie', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('users', 'equipo_tipo')) {
                $table->string('equipo_tipo', 30)->nullable();
            }
            if (!Schema::hasColumn('users', 'equipo_tabla')) {
                $table->string('equipo_tabla', 30)->nullable();
            }
            if (!Schema::hasColumn('users', 'equipo_id_stellum')) {
                $table->unsignedInteger('equipo_id_stellum')->nullable();
            }
            if (!Schema::hasColumn('users', 'equipo_marca')) {
                $table->string('equipo_marca', 100)->nullable();
            }
            if (!Schema::hasColumn('users', 'equipo_ubicacion')) {
                $table->string('equipo_ubicacion', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['equipo_no_serie', 'equipo_tipo', 'equipo_tabla', 'equipo_id_stellum', 'equipo_marca', 'equipo_ubicacion'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
