<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('huellas')) {
            Schema::create('huellas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->string('dedo', 40);
                $table->string('etiqueta', 80)->nullable();
                $table->longText('template');
                $table->longText('muestra_1')->nullable();
                $table->longText('muestra_2')->nullable();
                $table->longText('muestra_3')->nullable();
                $table->string('template_hash', 64)->index();
                $table->string('formato', 40)->default('generico');
                $table->unsignedTinyInteger('calidad')->nullable();
                $table->string('dispositivo_modelo', 100)->nullable();
                $table->foreignId('registrado_por')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->boolean('activo')->default(true);
                $table->dateTime('ultimo_uso_en')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'activo'], 'idx_huellas_user_activo');
                $table->index(['user_id', 'dedo'], 'idx_huellas_user_dedo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('huellas');
    }
};
