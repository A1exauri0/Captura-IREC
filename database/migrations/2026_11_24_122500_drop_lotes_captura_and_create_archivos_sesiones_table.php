<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('lotes_captura');

        if (!Schema::hasTable('archivos_sesiones')) {
            Schema::create('archivos_sesiones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('indice_pdf_id')->nullable()->index();
                $table->string('disco', 80)->nullable();
                $table->string('tipo_archivo', 30)->default('sellos')->index();
                $table->unsignedBigInteger('delegacion_id')->nullable()->index();
                $table->string('pc', 60)->nullable();
                $table->string('archivo_nombre', 255);
                $table->text('ruta_origen');
                $table->char('ruta_hash', 64)->unique(); // Bloqueo atómico por archivo individual
                $table->unsignedInteger('paginas')->default(0);
                $table->string('hash_pdf', 64)->nullable();
                $table->boolean('archivo_danado')->default(false);
                $table->string('estado', 25)->default('en_captura')->index(); // en_captura, completado, liberado
                $table->timestamp('tomado_en')->nullable();
                $table->timestamp('expira_en')->nullable()->index();
                $table->timestamp('completado_en')->nullable();
                $table->unsignedInteger('tiempo_segundos')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'estado']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_sesiones');
    }
};
