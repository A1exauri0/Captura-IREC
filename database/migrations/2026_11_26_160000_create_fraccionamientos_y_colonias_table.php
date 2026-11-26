<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fraccionamientos_y_colonias')) {
            Schema::create('fraccionamientos_y_colonias', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('nombre', 255);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fraccionamientos_y_colonias');
    }
};
