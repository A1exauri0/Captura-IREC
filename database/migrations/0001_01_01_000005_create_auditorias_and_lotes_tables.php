<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Auditoria Registros (anteriormente auditoria_capturas)
        Schema::create('auditoria_registros', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha_hora')->index();
            $table->string('turno')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->string('categoria')->nullable();
            $table->string('pc')->nullable();
            $table->string('directorio')->nullable();
            $table->foreignId('delegacion_id')->nullable()->constrained('delegaciones')->onUpdate('cascade')->onDelete('set null');
            $table->string('lote')->nullable()->index();
            $table->string('accion')->nullable()->index();
            $table->string('archivo_original')->nullable()->index();
            $table->string('archivo_nuevo')->nullable()->index();
            $table->text('ruta')->nullable();
            $table->text('ruta_origen')->nullable();
            $table->text('ruta_destino')->nullable();
            $table->string('hash', 64)->nullable()->index();
            $table->boolean('archivo_danado')->default(false)->index();
            $table->text('detalles')->nullable();
            $table->integer('paginas')->default(0);
            $table->string('lugar_trabajo')->nullable();
            $table->integer('tiempo_captura')->nullable();
            $table->timestamps();
        });

        // 2. Auditoria Sellos
        Schema::create('auditoria_sellos', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha_hora')->index();
            $table->string('turno')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->string('categoria')->nullable();
            $table->string('pc')->nullable();
            $table->string('directorio')->nullable();
            $table->foreignId('delegacion_id')->nullable()->constrained('delegaciones')->onUpdate('cascade')->onDelete('set null');
            $table->string('lote')->nullable()->index();
            $table->string('accion')->nullable()->index();
            $table->string('archivo_original')->nullable()->index();
            $table->string('archivo_nuevo')->nullable()->index();
            $table->text('ruta')->nullable();
            $table->text('ruta_origen')->nullable();
            $table->text('ruta_destino')->nullable();
            $table->string('hash', 64)->nullable()->index();
            $table->boolean('archivo_danado')->default(false)->index();
            $table->text('detalles')->nullable();
            $table->integer('paginas')->default(0);
            $table->string('tipo_sello')->nullable()->index();
            $table->integer('tiempo_captura')->nullable();
            $table->timestamps();
        });

        // 3. Auditoria Notarias
        Schema::create('auditoria_notarias', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha_hora')->index();
            $table->string('turno')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->string('categoria')->nullable();
            $table->string('pc')->nullable();
            $table->string('directorio')->nullable();
            $table->string('volumen')->nullable()->index();
            $table->string('lote')->nullable();
            $table->string('accion')->nullable()->index();
            $table->string('archivo_original')->nullable()->index();
            $table->string('archivo_nuevo')->nullable()->index();
            $table->text('ruta')->nullable();
            $table->text('ruta_origen')->nullable();
            $table->text('ruta_destino')->nullable();
            $table->string('hash', 64)->nullable()->index();
            $table->boolean('archivo_danado')->default(false)->index();
            $table->text('detalles')->nullable();
            $table->integer('paginas')->default(0);
            $table->integer('tiempo_captura')->nullable();
            $table->timestamps();
        });

        // 4. Auditoria Catastro
        Schema::create('auditoria_catastro', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha_hora')->index();
            $table->string('turno')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->string('categoria')->nullable()->index();
            $table->string('tipo')->nullable()->index();
            $table->string('pc')->nullable();
            $table->string('directorio')->nullable();
            $table->string('lote')->nullable()->index();
            $table->string('accion')->nullable()->index();
            $table->string('archivo_original')->nullable()->index();
            $table->string('archivo_nuevo')->nullable()->index();
            $table->text('ruta')->nullable();
            $table->text('ruta_origen')->nullable();
            $table->text('ruta_destino')->nullable();
            $table->string('hash', 64)->nullable()->index();
            $table->boolean('archivo_danado')->default(false)->index();
            $table->text('detalles')->nullable();
            $table->integer('paginas')->default(0);
            $table->integer('tiempo_captura')->nullable();
            $table->timestamps();
        });

        // 5. Lotes Captura (Monitoreo en tiempo real)
        Schema::create('lotes_captura', function (Blueprint $table) {
            $table->id();
            $table->string('disco')->default('irec');
            $table->string('tipo_archivo')->default('registros')->index();
            $table->foreignId('delegacion_id')->nullable()->constrained('delegaciones')->onUpdate('cascade')->onDelete('set null');
            $table->string('nombre_lote');
            $table->string('ruta_relativa');
            $table->text('ruta_origen')->nullable();
            $table->text('ruta_destino')->nullable();
            $table->integer('paginas')->default(0);
            $table->string('hash', 64)->nullable()->index();
            $table->boolean('archivo_danado')->default(false)->index();
            $table->string('estado')->default('en_proceso')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->dateTime('tomado_en')->nullable();
            $table->dateTime('completado_en')->nullable();
            $table->string('progreso_archivo')->nullable();
            $table->timestamps();
        });

        // 6. Notarias Sesiones (Monitoreo en tiempo real de Notarias)
        Schema::create('notarias_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->string('notaria_nombre')->nullable();
            $table->string('volumen')->nullable();
            $table->string('ruta_relativa')->nullable();
            $table->text('ruta_origen')->nullable();
            $table->text('ruta_destino')->nullable();
            $table->integer('paginas')->default(0);
            $table->string('hash', 64)->nullable()->index();
            $table->boolean('archivo_danado')->default(false)->index();
            $table->string('disco')->nullable();
            $table->string('pc')->nullable();
            $table->string('estado')->default('en_proceso')->index();
            $table->dateTime('tomado_en')->nullable();
            $table->dateTime('completado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notarias_sesiones');
        Schema::dropIfExists('lotes_captura');
        Schema::dropIfExists('auditoria_catastro');
        Schema::dropIfExists('auditoria_notarias');
        Schema::dropIfExists('auditoria_sellos');
        Schema::dropIfExists('auditoria_registros');
    }
};
