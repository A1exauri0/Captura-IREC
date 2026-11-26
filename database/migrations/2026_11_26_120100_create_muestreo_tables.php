<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('control_muestreos')) {
            Schema::create('control_muestreos', function (Blueprint $table) {
                $table->id();
                $table->date('fecha_laboral')->index();
                $table->string('turno', 60)->index();
                $table->string('tipo_documento', 30)->default('sellos');
                $table->string('regla', 30)->default('confianza_96'); // confianza_96, porcentaje_30, porcentaje_50
                $table->unsignedInteger('universo')->default(0);
                $table->unsignedInteger('muestra')->default(0);
                $table->unsignedInteger('errores_permitidos')->default(0);
                $table->unsignedInteger('revisados')->default(0);
                $table->unsignedInteger('errores_detectados')->default(0);
                $table->string('estado', 30)->default('en_proceso')->index(); // en_proceso, aprobado, rechazado
                $table->unsignedBigInteger('generado_por')->nullable();
                $table->timestamps();

                $table->index(['fecha_laboral', 'turno', 'tipo_documento'], 'idx_ctrl_muestreo_fecha_turno');
            });
        }

        if (!Schema::hasTable('archivos_muestreos')) {
            Schema::create('archivos_muestreos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('control_muestreo_id')->constrained('control_muestreos')->cascadeOnDelete();
                $table->unsignedBigInteger('auditoria_sello_id')->nullable()->index();
                $table->unsignedBigInteger('indice_pdf_id')->nullable()->index();
                $table->unsignedBigInteger('delegacion_id')->nullable()->index();
                $table->text('ruta_origen')->nullable();
                $table->string('archivo_original', 255);
                $table->string('archivo_capturado', 255); // lo que guardó el capturista en auditoria_sellos (archivo_nuevo)
                $table->string('archivo_muestreo', 255)->nullable(); // lo que captura el usuario de Calidad
                $table->string('accion_muestreo', 30)->nullable(); // validado | renombrado
                $table->unsignedInteger('paginas')->default(1);
                $table->string('hash', 128)->nullable();
                $table->unsignedBigInteger('capturista_id')->nullable()->index();
                $table->unsignedBigInteger('calidad_user_id')->nullable()->index();
                $table->string('estado', 30)->default('pendiente')->index(); // pendiente, en_captura, completado
                $table->boolean('es_correcto')->nullable();
                $table->text('detalles')->nullable();
                $table->timestamp('tomado_en')->nullable();
                $table->timestamp('completado_en')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_muestreos');
        Schema::dropIfExists('control_muestreos');
    }
};
