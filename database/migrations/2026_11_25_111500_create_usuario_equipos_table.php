<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('usuario_equipos')) {
            Schema::create('usuario_equipos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('no_serie', 100)->index();
                $table->string('numero_pc', 30)->nullable();
                $table->string('tipo', 40)->nullable();
                $table->string('marca', 100)->nullable();
                $table->string('ubicacion', 120)->nullable();
                $table->string('tabla_origen', 30)->nullable();
                $table->unsignedBigInteger('equipo_id_stellum')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'no_serie'], 'uq_usuario_equipo_serie');
            });

            // Migrar asignaciones existentes en users.equipo_no_serie hacia usuario_equipos
            $usuariosConEquipo = DB::table('users')
                ->whereNotNull('equipo_no_serie')
                ->where('equipo_no_serie', '!=', '')
                ->get(['id', 'equipo_no_serie', 'equipo_tipo', 'equipo_marca', 'equipo_ubicacion', 'equipo_tabla', 'equipo_id_stellum']);

            foreach ($usuariosConEquipo as $u) {
                $numPc = Schema::hasTable('pcs_captura')
                    ? DB::table('pcs_captura')->where('no_serie', $u->equipo_no_serie)->value('numero_pc')
                    : null;
                DB::table('usuario_equipos')->updateOrInsert(
                    ['user_id' => $u->id, 'no_serie' => $u->equipo_no_serie],
                    [
                        'numero_pc'         => $numPc ? preg_replace('/^PC[-\s]*0*/i', '', (string) $numPc) : null,
                        'tipo'              => $u->equipo_tipo,
                        'marca'             => $u->equipo_marca,
                        'ubicacion'         => $u->equipo_ubicacion,
                        'tabla_origen'      => $u->equipo_tabla,
                        'equipo_id_stellum' => $u->equipo_id_stellum,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]
                );
            }
        }

        // Normalizar pcs_captura existentes para guardar solo el número (sin prefijo "PC-")
        if (Schema::hasTable('pcs_captura')) {
            $pcs = DB::table('pcs_captura')->get();
            foreach ($pcs as $pc) {
                $soloNum = preg_replace('/^PC[-\s]*0*/i', '', trim((string) $pc->numero_pc));
                if ($soloNum === '') {
                    $soloNum = (string) $pc->id;
                }
                if ($soloNum !== $pc->numero_pc) {
                    $existe = DB::table('pcs_captura')->where('numero_pc', $soloNum)->where('id', '!=', $pc->id)->exists();
                    if (!$existe) {
                        DB::table('pcs_captura')->where('id', $pc->id)->update(['numero_pc' => $soloNum]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_equipos');
    }
};
