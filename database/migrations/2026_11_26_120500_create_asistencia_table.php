<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asistencia')) {
            Schema::create('asistencia', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->foreignId('turno_id')
                    ->nullable()
                    ->constrained('turnos')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->foreignId('huella_id')
                    ->nullable()
                    ->constrained('huellas')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->string('turno_nombre', 80)->nullable();
                $table->time('hora_entrada_esperada')->nullable();
                $table->time('hora_salida_esperada')->nullable();
                $table->date('fecha_laboral');
                $table->dateTime('hora_entrada');
                $table->dateTime('hora_salida')->nullable();
                $table->unsignedSmallInteger('minutos_retardo')->default(0);
                $table->unsignedSmallInteger('minutos_trabajados')->nullable();
                $table->string('estatus', 30)->default('a_tiempo');
                $table->unsignedSmallInteger('score_entrada')->nullable();
                $table->unsignedSmallInteger('score_salida')->nullable();
                $table->string('dispositivo_entrada', 100)->nullable();
                $table->string('dispositivo_salida', 100)->nullable();
                $table->string('observaciones', 255)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'fecha_laboral'], 'uq_asistencia_usuario_fecha');
                $table->index(['fecha_laboral', 'turno_id'], 'idx_asistencia_fecha_turno');
                $table->index(['fecha_laboral', 'estatus'], 'idx_asistencia_fecha_estatus');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia');
    }
};
