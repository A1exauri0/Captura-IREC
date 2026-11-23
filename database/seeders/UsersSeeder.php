<?php

namespace Database\Seeders;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Crea únicamente al administrador principal y un usuario de ejemplo por cada rol del sistema.
     */
    public function run(): void
    {
        $turnoMatutino   = Turno::where('nombre', 'Matutino')->first();
        $turnoVespertino = Turno::where('nombre', 'Vespertino')->first();
        $turnoNocturno   = Turno::where('nombre', 'Nocturno')->first();

        $usuariosBase = [
            [
                'name'     => 'Administrador General',
                'user'     => 'Administrador',
                'email'    => 'admin@gmail.com',
                'password' => 'makintosh96@',
                'rol'      => 'superadmin',
                'turno'    => $turnoMatutino?->nombre ?? 'Matutino',
                'turno_id' => $turnoMatutino?->id,
            ],
            [
                'name'     => 'Supervisor Administrativo',
                'user'     => 'admin.operativo',
                'email'    => 'administrador@irec.gob.mx',
                'password' => 'admin1234',
                'rol'      => 'administrador',
                'turno'    => $turnoMatutino?->nombre ?? 'Matutino',
                'turno_id' => $turnoMatutino?->id,
            ],
            [
                'name'     => 'Capturista Operativo',
                'user'     => 'capturista',
                'email'    => 'capturista@irec.gob.mx',
                'password' => 'captura1234',
                'rol'      => 'Captura',
                'turno'    => $turnoMatutino?->nombre ?? 'Matutino',
                'turno_id' => $turnoMatutino?->id,
            ],
            [
                'name'     => 'Digitalizador Operativo',
                'user'     => 'digitalizador',
                'email'    => 'digitalizador@irec.gob.mx',
                'password' => 'digital1234',
                'rol'      => 'Digitalizador',
                'turno'    => $turnoVespertino?->nombre ?? 'Vespertino',
                'turno_id' => $turnoVespertino?->id,
            ],
            [
                'name'     => 'Validador de Calidad',
                'user'     => 'calidad',
                'email'    => 'calidad@irec.gob.mx',
                'password' => 'calidad1234',
                'rol'      => 'Calidad',
                'turno'    => $turnoVespertino?->nombre ?? 'Vespertino',
                'turno_id' => $turnoVespertino?->id,
            ],
            [
                'name'     => 'Auditor Documental',
                'user'     => 'auditor',
                'email'    => 'auditor@irec.gob.mx',
                'password' => 'auditor1234',
                'rol'      => 'Auditor',
                'turno'    => $turnoMatutino?->nombre ?? 'Matutino',
                'turno_id' => $turnoMatutino?->id,
            ],
            [
                'name'     => 'Soporte Técnico',
                'user'     => 'soporte',
                'email'    => 'soporte@irec.gob.mx',
                'password' => 'soporte1234',
                'rol'      => 'Soporte',
                'turno'    => $turnoNocturno?->nombre ?? 'Nocturno',
                'turno_id' => $turnoNocturno?->id,
            ],
        ];

        $emailsPermitidos = collect($usuariosBase)->pluck('email')->all();

        // Eliminar usuarios excedentes antiguos que no pertenecen al set limpio
        User::whereNotIn('email', $emailsPermitidos)->delete();

        foreach ($usuariosBase as $item) {
            $usuario = User::updateOrCreate(
                ['email' => $item['email']],
                [
                    'name'     => $item['name'],
                    'user'     => $item['user'],
                    'password' => Hash::make($item['password']),
                    'turno'    => $item['turno'],
                    'turno_id' => $item['turno_id'],
                    'estatus'  => true,
                ]
            );

            $usuario->syncRoles([$item['rol']]);
        }
    }
}
