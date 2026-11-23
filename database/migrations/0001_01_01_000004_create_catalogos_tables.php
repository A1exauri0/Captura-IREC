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

        Schema::create('stellmunicipios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estado')->default(7);
            $table->string('nombre');
            $table->string('clave', 20)->index();
            $table->timestamps();
        });

        Schema::create('stelllocalidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('municipio')->index();
            $table->string('nombre');
            $table->string('clave', 20)->index();
            $table->timestamps();
        });

        Schema::create('pines_autorizacion', function (Blueprint $table) {
            $table->id();
            $table->string('pin', 10);
            $table->boolean('usado')->default(false);
            $table->foreignId('usado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('usado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pines_autorizacion');
        Schema::dropIfExists('stelllocalidades');
        Schema::dropIfExists('stellmunicipios');
        Schema::dropIfExists('delegaciones');
        Schema::dropIfExists('anios');
    }
};
