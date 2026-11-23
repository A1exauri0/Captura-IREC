<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tablasAuditoria = [
        'auditoria_registros',
        'auditoria_sellos',
        'auditoria_notarias',
        'auditoria_catastro',
    ];

    public function up(): void
    {
        // 1. Agregar columnas de flujo Solo-BD, colisiones, inconsistencias y aprobación Stellum a las 4 auditorías
        foreach ($this->tablasAuditoria as $tabla) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (!Schema::hasColumn($tabla, 'indice_pdf_id')) {
                    $table->unsignedBigInteger('indice_pdf_id')->nullable()->index();
                }
                if (!Schema::hasColumn($tabla, 'ruta_propuesta')) {
                    $table->text('ruta_propuesta')->nullable();
                }
                if (!Schema::hasColumn($tabla, 'revisar')) {
                    $table->boolean('revisar')->default(false)->index();
                }
                if (!Schema::hasColumn($tabla, 'archivo_colision_id')) {
                    $table->unsignedBigInteger('archivo_colision_id')->nullable();
                }
                if (!Schema::hasColumn($tabla, 'ruta_colision')) {
                    $table->text('ruta_colision')->nullable();
                }
                if (!Schema::hasColumn($tabla, 'decision_revision')) {
                    $table->string('decision_revision', 30)->nullable()->index();
                }
                if (!Schema::hasColumn($tabla, 'inconsistencia_codigo')) {
                    $table->string('inconsistencia_codigo', 10)->nullable()->index();
                }
                if (!Schema::hasColumn($tabla, 'inconsistencia_estado')) {
                    $table->string('inconsistencia_estado', 25)->nullable()->index();
                }
                if (!Schema::hasColumn($tabla, 'inconsistencia_comentario')) {
                    $table->text('inconsistencia_comentario')->nullable();
                }
                if (!Schema::hasColumn($tabla, 'estado_aplicacion')) {
                    $table->string('estado_aplicacion', 25)->default('pendiente')->index();
                }
                if (!Schema::hasColumn($tabla, 'orden_aplicacion_id')) {
                    $table->unsignedBigInteger('orden_aplicacion_id')->nullable()->index();
                }
            });
        }

        // 2. Catálogo de inconsistencias (R01-R09)
        if (!Schema::hasTable('catalogo_inconsistencias')) {
            Schema::create('catalogo_inconsistencias', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 10)->unique();
                $table->string('motivo_rechazo', 255);
                $table->string('accion_correctiva', 255);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        // 3. Asignación de tipo de archivo y regla automática por usuario
        if (!Schema::hasTable('asignaciones_captura_usuario')) {
            Schema::create('asignaciones_captura_usuario', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('tipo_documento', 30)->default('registros'); // registros, sellos, notarias, catastro
                $table->string('regla_automatica', 30)->default('Regla1'); // Regla1, Regla2, ReglaCatastro, ReglaNotarias
                $table->unsignedBigInteger('delegacion_id')->nullable();
                $table->string('tipo_sello', 80)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['tipo_documento', 'activo']);
            });
        }

        // 4. Órdenes masivas de aplicación de renombres hacia Stellum (millones de archivos)
        if (!Schema::hasTable('ordenes_aplicacion_stellum')) {
            Schema::create('ordenes_aplicacion_stellum', function (Blueprint $table) {
                $table->id();
                $table->string('tipo_documento', 30)->index(); // registros, sellos, notarias, catastro
                $table->unsignedBigInteger('delegacion_id')->nullable()->index();
                $table->string('tipo_sello', 80)->nullable()->index();
                $table->unsignedBigInteger('aprobado_por')->nullable();
                $table->unsignedBigInteger('total_archivos')->default(0);
                $table->unsignedBigInteger('procesados')->default(0);
                $table->unsignedBigInteger('exitosos')->default(0);
                $table->unsignedBigInteger('fallidos')->default(0);
                $table->string('estado', 30)->default('pendiente')->index(); // pendiente, enviando, en_proceso, completada, completada_con_errores, error
                $table->json('detalle_errores')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_aplicacion_stellum');
        Schema::dropIfExists('asignaciones_captura_usuario');
        Schema::dropIfExists('catalogo_inconsistencias');

        foreach ($this->tablasAuditoria as $tabla) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $cols = [
                    'indice_pdf_id', 'ruta_propuesta', 'revisar', 'archivo_colision_id',
                    'ruta_colision', 'decision_revision', 'inconsistencia_codigo',
                    'inconsistencia_estado', 'inconsistencia_comentario',
                    'estado_aplicacion', 'orden_aplicacion_id',
                ];
                foreach ($cols as $c) {
                    if (Schema::hasColumn($tabla, $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
