<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('estatus')->default(true);
            $table->timestamps();
        });

        Schema::create('delegaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('clave', 20)->nullable()->index();
            $table->string('nota')->nullable();
            $table->boolean('estatus')->default(true);
            $table->timestamps();
        });

        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estado')->default(7);
            $table->string('nombre');
            $table->string('clave', 20)->index();
            $table->timestamps();
        });

        Schema::create('localidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipio')->index();
            $table->string('nombre');
            $table->string('clave', 20)->index();
            $table->timestamps();
        });

        Schema::create('peticiones_autorizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('cascade');
            $table->string('tipo_peticion')->index();
            $table->string('accion_solicitada')->nullable();
            $table->string('disco')->nullable();
            $table->string('lote')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->text('ruta_origen')->nullable();
            $table->text('ruta_destino')->nullable();
            $table->integer('paginas')->default(0);
            $table->string('hash', 64)->nullable()->index();
            $table->boolean('archivo_danado')->default(false)->index();
            $table->text('motivo')->nullable();
            $table->string('estado')->default('pendiente')->index(); // pendiente, autorizada, rechazada
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comentario_resolucion')->nullable();
            $table->dateTime('resuelto_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peticiones_autorizacion');
        Schema::dropIfExists('localidades');
        Schema::dropIfExists('municipios');
        Schema::dropIfExists('delegaciones');
        Schema::dropIfExists('anios');
    }
};
