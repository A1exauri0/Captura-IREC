<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccesoController extends Controller
{
    // =========================================================
    // VISTAS SEPARADAS: USUARIOS, ROLES Y PERMISOS
    // =========================================================

    public function usuarios()
    {
        $usuarios = User::with(['roles', 'turnoRelacion'])->orderBy('id')->get();
        $roles = Role::orderBy('name')->get();
        $turnos = Turno::where('estatus', true)->orderBy('id')->get();

        return view('configuracion.usuarios_index', compact('usuarios', 'roles', 'turnos'));
    }

    public function roles()
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('id')->get();
        $permisos = Permission::orderBy('name')->get();

        return view('configuracion.roles_index', compact('roles', 'permisos'));
    }

    public function permisos()
    {
        $permisos = Permission::withCount('roles')->orderBy('name')->get();

        return view('configuracion.permisos_index', compact('permisos'));
    }

    // =========================================================
    // CRUD USUARIOS
    // =========================================================

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:150',
            'user'     => 'required|string|max:80|unique:users,user',
            'email'    => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:6',
            'turno_id' => 'nullable|exists:turnos,id',
            'roles'    => 'nullable|array',
        ]);

        $turnoNombre = 'Matutino';
        if (!empty($data['turno_id'])) {
            $turnoObj = Turno::find($data['turno_id']);
            if ($turnoObj) {
                $turnoNombre = $turnoObj->nombre;
            }
        }

        $usuario = User::create([
            'name'     => trim($data['name']),
            'user'     => trim($data['user']),
            'email'    => trim($data['email']),
            'password' => Hash::make($data['password']),
            'turno_id' => $data['turno_id'] ?? null,
            'turno'    => $turnoNombre,
            'estatus'  => true,
        ]);

        if (!empty($data['roles'])) {
            $usuario->syncRoles($data['roles']);
        }

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$usuario->name}' creado correctamente.");
    }

    public function updateUser(Request $request, $id)
    {
        $usuario = User::findOrFail($id);

        $data = $request->validate([
            'name'     => 'required|string|max:150',
            'user'     => 'required|string|max:80|unique:users,user,' . $usuario->id,
            'email'    => 'required|email|max:150|unique:users,email,' . $usuario->id,
            'password' => 'nullable|string|min:6',
            'turno_id' => 'nullable|exists:turnos,id',
            'estatus'  => 'nullable|boolean',
            'roles'    => 'nullable|array',
        ]);

        $turnoNombre = $usuario->turno;
        if (!empty($data['turno_id'])) {
            $turnoObj = Turno::find($data['turno_id']);
            if ($turnoObj) {
                $turnoNombre = $turnoObj->nombre;
            }
        }

        $campos = [
            'name'     => trim($data['name']),
            'user'     => trim($data['user']),
            'email'    => trim($data['email']),
            'turno_id' => $data['turno_id'] ?? null,
            'turno'    => $turnoNombre,
            'estatus'  => $request->has('estatus') ? (bool) $request->estatus : $usuario->estatus,
        ];

        if (!empty($data['password'])) {
            $campos['password'] = Hash::make($data['password']);
        }

        $usuario->update($campos);
        $usuario->syncRoles($data['roles'] ?? []);

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$usuario->name}' actualizado correctamente.");
    }

    public function destroyUser($id)
    {
        $usuario = User::findOrFail($id);
        if (auth()->id() === $usuario->id) {
            return redirect()->route('admin.usuarios.index')
                ->with('error', 'No puedes eliminar tu propio usuario activo.');
        }

        $nombre = $usuario->name;
        $usuario->delete();

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario '{$nombre}' eliminado correctamente.");
    }

    // =========================================================
    // CRUD ROLES
    // =========================================================

    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:80|unique:roles,name',
            'permissions' => 'nullable|array',
        ]);

        $role = Role::create([
            'name'       => trim($data['name']),
            'guard_name' => 'web',
        ]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', "Rol '{$role->name}' creado exitosamente.");
    }

    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:80|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
        ]);

        $role->update(['name' => trim($data['name'])]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')
            ->with('success', "Rol '{$role->name}' y sus permisos actualizados.");
    }

    public function destroyRole($id)
    {
        $role = Role::findOrFail($id);
        if (strtolower($role->name) === 'superadmin') {
            return redirect()->route('admin.roles.index')
                ->with('error', 'El rol superadmin no puede ser eliminado.');
        }

        $nombre = $role->name;
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', "Rol '{$nombre}' eliminado correctamente.");
    }

    // =========================================================
    // CRUD PERMISOS
    // =========================================================

    public function storePermission(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80|unique:permissions,name',
        ]);

        $permiso = Permission::create([
            'name'       => trim($data['name']),
            'guard_name' => 'web',
        ]);

        return redirect()->route('admin.permisos.index')
            ->with('success', "Permiso '{$permiso->name}' creado correctamente.");
    }

    public function updatePermission(Request $request, $id)
    {
        $permiso = Permission::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:80|unique:permissions,name,' . $permiso->id,
        ]);

        $permiso->update(['name' => trim($data['name'])]);

        return redirect()->route('admin.permisos.index')
            ->with('success', "Permiso actualizado a '{$permiso->name}'.");
    }

    public function destroyPermission($id)
    {
        $permiso = Permission::findOrFail($id);
        $nombre = $permiso->name;
        $permiso->delete();

        return redirect()->route('admin.permisos.index')
            ->with('success', "Permiso '{$nombre}' eliminado correctamente.");
    }
}
