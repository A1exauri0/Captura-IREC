<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'superadmin',
            'administrador',
            'Digitalizador',
            'Calidad',
            'Auditor',
            'Soporte',
            'Captura',
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $permisos = [
            'Captura',
            'Validación',
            'analiticas',
            'usuarios',
            'superadmin',
        ];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
    }
}
