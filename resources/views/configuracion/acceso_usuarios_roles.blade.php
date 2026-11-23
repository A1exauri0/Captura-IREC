@extends('layouts.crud')

@section('titulo', 'Administración Unificada de Usuarios, Roles y Permisos')
@section('crud_modulo', 'Seguridad y Control de Acceso')
@section('crud_titulo', 'Usuarios, Roles y Permisos')
@section('crud_subtitulo', 'Gestione cuentas de capturistas, supervisores, roles y matriz de permisos en un solo panel unificado.')

@section('crud_acciones')
    <button type="button" class="btn btn-primary btn-sm px-3" data-toggle="modal" data-target="#modalCrearUsuario" style="background-color:#034d6d;border-color:#034d6d;">
        <i class="fa-solid fa-user-plus mr-1"></i> Nuevo Usuario
    </button>
    <button type="button" class="btn btn-outline-primary btn-sm px-3" data-toggle="modal" data-target="#modalCrearRol">
        <i class="fa-solid fa-shield-halved mr-1"></i> Nuevo Rol
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-toggle="modal" data-target="#modalCrearPermiso">
        <i class="fa-solid fa-key mr-1"></i> Nuevo Permiso
    </button>
@endsection

@section('contenido')
<!-- Pestañas de Módulo Unificado -->
<ul class="nav nav-pills mb-3 bg-white p-2 rounded-xl shadow-sm border border-slate-200" style="gap: 8px;">
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'usuarios' ? 'active' : 'text-dark' }}"
           style="{{ $tab === 'usuarios' ? 'background-color:#034d6d;' : '' }}"
           href="{{ route('admin.acceso.index', ['tab' => 'usuarios']) }}">
            <i class="fa-solid fa-users mr-1"></i> Usuarios ({{ $usuarios->count() }})
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'roles' ? 'active' : 'text-dark' }}"
           style="{{ $tab === 'roles' ? 'background-color:#034d6d;' : '' }}"
           href="{{ route('admin.acceso.index', ['tab' => 'roles']) }}">
            <i class="fa-solid fa-user-shield mr-1"></i> Roles ({{ $roles->count() }})
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'permisos' ? 'active' : 'text-dark' }}"
           style="{{ $tab === 'permisos' ? 'background-color:#034d6d;' : '' }}"
           href="{{ route('admin.acceso.index', ['tab' => 'permisos']) }}">
            <i class="fa-solid fa-key mr-1"></i> Permisos ({{ $permisos->count() }})
        </a>
    </li>
</ul>

@if($tab === 'usuarios')
    <!-- TAB 1: CRUD DE USUARIOS -->
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:10px;">
            <span><i class="fa-solid fa-users mr-2 text-sky-700"></i>Directorio General de Usuarios</span>
            <input type="text" id="buscadorUsuarios" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Buscar por nombre, usuario o rol...">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="tablaUsuarios">
                    <thead class="bg-light text-xs uppercase">
                        <tr>
                            <th class="text-center">#</th>
                            <th>Nombre Completo</th>
                            <th>Usuario / Correo</th>
                            <th>Roles Asignados</th>
                            <th>Turno Adaptativo</th>
                            <th class="text-center">Estatus</th>
                            <th class="text-center" style="width: 130px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $idx => $u)
                            <tr>
                                <td class="text-center text-muted small">{{ $idx + 1 }}</td>
                                <td class="font-weight-bold text-dark small">{{ $u->name }}</td>
                                <td>
                                    <div class="font-mono text-xs font-weight-bold text-sky-800">{{ $u->user }}</div>
                                    <div class="text-xs text-muted">{{ $u->email }}</div>
                                </td>
                                <td>
                                    @forelse($u->roles as $r)
                                        <span class="badge badge-info mr-1">{{ $r->name }}</span>
                                    @empty
                                        <span class="text-xs text-muted">Sin rol</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if($u->turnoRelacion)
                                        <span class="badge text-white" style="background-color: {{ $u->turnoRelacion->color_hex }}">
                                            {{ $u->turnoRelacion->nombre }}
                                        </span>
                                    @else
                                        <span class="badge badge-light border">{{ $u->turno ?: 'Matutino' }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($u->estatus)
                                        <span class="badge badge-success">Activo</span>
                                    @else
                                        <span class="badge badge-secondary">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-light border btn-editar-usuario"
                                            data-id="{{ $u->id }}"
                                            data-name="{{ $u->name }}"
                                            data-user="{{ $u->user }}"
                                            data-email="{{ $u->email }}"
                                            data-turno="{{ $u->turno_id }}"
                                            data-estatus="{{ $u->estatus ? 1 : 0 }}"
                                            data-roles='@json($u->roles->pluck("name"))'>
                                        <i class="fa-solid fa-pen text-sky-700"></i>
                                    </button>
                                    <form action="{{ route('admin.acceso.usuarios.destroy', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario permanentemente?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light border text-danger">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@elseif($tab === 'roles')
    <!-- TAB 2: CRUD DE ROLES -->
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-user-shield mr-2 text-sky-700"></i>Catálogo de Roles y Permisos Asociados
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-xs uppercase">
                        <tr>
                            <th style="width: 60px;" class="text-center">ID</th>
                            <th style="width: 180px;">Nombre del Rol</th>
                            <th>Permisos Habilitados</th>
                            <th class="text-center" style="width: 110px;">Usuarios</th>
                            <th class="text-center" style="width: 130px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $rol)
                            <tr>
                                <td class="text-center text-muted small">{{ $rol->id }}</td>
                                <td class="font-weight-bold text-dark">{{ $rol->name }}</td>
                                <td>
                                    @forelse($rol->permissions as $p)
                                        <span class="badge badge-light border text-sky-800 mr-1 mb-1">{{ $p->name }}</span>
                                    @empty
                                        <span class="text-xs text-muted">Sin permisos asignados</span>
                                    @endforelse
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-pill badge-primary" style="background-color:#034d6d;">{{ $rol->users_count }}</span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-light border btn-editar-rol"
                                            data-id="{{ $rol->id }}"
                                            data-name="{{ $rol->name }}"
                                            data-permissions='@json($rol->permissions->pluck("name"))'>
                                        <i class="fa-solid fa-pen text-sky-700"></i>
                                    </button>
                                    @if(strtolower($rol->name) !== 'superadmin')
                                        <form action="{{ route('admin.acceso.roles.destroy', $rol->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el rol {{ $rol->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light border text-danger">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@else
    <!-- TAB 3: CRUD DE PERMISOS -->
    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-key mr-2 text-sky-700"></i>Catálogo de Permisos del Sistema
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-xs uppercase">
                        <tr>
                            <th style="width: 60px;" class="text-center">ID</th>
                            <th>Nombre del Permiso</th>
                            <th class="text-center" style="width: 140px;">Roles Asignados</th>
                            <th class="text-center" style="width: 140px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($permisos as $perm)
                            <tr>
                                <td class="text-center text-muted small">{{ $perm->id }}</td>
                                <td class="font-weight-bold text-dark font-mono small">{{ $perm->name }}</td>
                                <td class="text-center">
                                    <span class="badge badge-light border">{{ $perm->roles_count }} rol(es)</span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-light border btn-editar-permiso"
                                            data-id="{{ $perm->id }}"
                                            data-name="{{ $perm->name }}">
                                        <i class="fa-solid fa-pen text-sky-700"></i>
                                    </button>
                                    <form action="{{ route('admin.acceso.permisos.destroy', $perm->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este permiso?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light border text-danger">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

<!-- MODAL CREAR USUARIO -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('admin.acceso.usuarios.store') }}" method="POST" class="modal-content border-0 shadow-lg rounded-xl">
            @csrf
            <div class="modal-header text-white" style="background:#034d6d;">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-user-plus mr-2"></i>Crear Usuario</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold">Nombre Completo:</label>
                    <input type="text" name="name" required class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Usuario (Login):</label>
                        <input type="text" name="user" required class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Correo Electrónico:</label>
                        <input type="email" name="email" required class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Contraseña:</label>
                        <input type="password" name="password" required minlength="6" class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Turno Adaptativo:</label>
                        <select name="turno_id" class="form-control">
                            @foreach($turnos as $t)
                                <option value="{{ $t->id }}">{{ $t->nombre }} ({{ substr($t->hora_entrada, 0, 5) }} - {{ substr($t->hora_salida, 0, 5) }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold d-block">Roles:</label>
                    <div class="d-flex flex-wrap" style="gap: 10px;">
                        @foreach($roles as $r)
                            <label class="small d-inline-flex align-items-center mb-1" style="gap:4px;">
                                <input type="checkbox" name="roles[]" value="{{ $r->name }}"> {{ $r->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#034d6d;">Guardar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR USUARIO -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formEditarUsuario" method="POST" class="modal-content border-0 shadow-lg rounded-xl">
            @csrf
            @method('PUT')
            <div class="modal-header text-white" style="background:#034d6d;">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-user-pen mr-2"></i>Editar Usuario</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold">Nombre Completo:</label>
                    <input type="text" name="name" id="editUserName" required class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Usuario (Login):</label>
                        <input type="text" name="user" id="editUserLogin" required class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Correo Electrónico:</label>
                        <input type="email" name="email" id="editUserEmail" required class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Nueva Contraseña (opcional):</label>
                        <input type="password" name="password" minlength="6" class="form-control" placeholder="Dejar vacío para conservar">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold">Turno Adaptativo:</label>
                        <select name="turno_id" id="editUserTurno" class="form-control">
                            @foreach($turnos as $t)
                                <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Estatus:</label>
                    <select name="estatus" id="editUserEstatus" class="form-control">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold d-block">Roles:</label>
                    <div class="d-flex flex-wrap" id="editUserRolesBox" style="gap: 10px;">
                        @foreach($roles as $r)
                            <label class="small d-inline-flex align-items-center mb-1" style="gap:4px;">
                                <input type="checkbox" name="roles[]" value="{{ $r->name }}" class="chk-edit-role"> {{ $r->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#034d6d;">Actualizar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CREAR ROL -->
<div class="modal fade" id="modalCrearRol" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('admin.acceso.roles.store') }}" method="POST" class="modal-content border-0 shadow-lg rounded-xl">
            @csrf
            <div class="modal-header text-white" style="background:#034d6d;">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-shield-halved mr-2"></i>Crear Nuevo Rol</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold">Nombre del Rol:</label>
                    <input type="text" name="name" required class="form-control" placeholder="Ej. Supervisor, Analista, Capturista...">
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold d-block">Permisos del Rol:</label>
                    <div class="border rounded p-3 bg-light" style="max-height: 220px; overflow-y: auto;">
                        @foreach($permisos as $p)
                            <label class="d-block small mb-1">
                                <input type="checkbox" name="permissions[]" value="{{ $p->name }}" class="mr-1"> {{ $p->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#034d6d;">Guardar Rol</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR ROL -->
<div class="modal fade" id="modalEditarRol" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formEditarRol" method="POST" class="modal-content border-0 shadow-lg rounded-xl">
            @csrf
            @method('PUT')
            <div class="modal-header text-white" style="background:#034d6d;">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-pen mr-2"></i>Editar Rol y Permisos</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="small font-weight-bold">Nombre del Rol:</label>
                    <input type="text" name="name" id="editRolName" required class="form-control">
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold d-block">Permisos del Rol:</label>
                    <div class="border rounded p-3 bg-light" style="max-height: 220px; overflow-y: auto;">
                        @foreach($permisos as $p)
                            <label class="d-block small mb-1">
                                <input type="checkbox" name="permissions[]" value="{{ $p->name }}" class="mr-1 chk-edit-perm"> {{ $p->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#034d6d;">Actualizar Rol</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CREAR PERMISO -->
<div class="modal fade" id="modalCrearPermiso" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('admin.acceso.permisos.store') }}" method="POST" class="modal-content border-0 shadow-lg rounded-xl">
            @csrf
            <div class="modal-header text-white" style="background:#034d6d;">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-key mr-2"></i>Crear Nuevo Permiso</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Clave / Nombre del Permiso:</label>
                    <input type="text" name="name" required class="form-control" placeholder="Ej. autorizar_peticiones, editar_renombres...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#034d6d;">Guardar Permiso</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR PERMISO -->
<div class="modal fade" id="modalEditarPermiso" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formEditarPermiso" method="POST" class="modal-content border-0 shadow-lg rounded-xl">
            @csrf
            @method('PUT')
            <div class="modal-header text-white" style="background:#034d6d;">
                <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-pen mr-2"></i>Editar Permiso</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Nombre del Permiso:</label>
                    <input type="text" name="name" id="editPermisoName" required class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#034d6d;">Actualizar Permiso</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#buscadorUsuarios').on('input', function() {
        const q = $(this).val().toLowerCase();
        $('#tablaUsuarios tbody tr').each(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
        });
    });

    $('.btn-editar-usuario').click(function() {
        const id = $(this).data('id');
        const roles = $(this).data('roles') || [];
        $('#formEditarUsuario').attr('action', '/acceso/usuarios/' + id);
        $('#editUserName').val($(this).data('name'));
        $('#editUserLogin').val($(this).data('user'));
        $('#editUserEmail').val($(this).data('email'));
        $('#editUserTurno').val($(this).data('turno'));
        $('#editUserEstatus').val($(this).data('estatus'));
        $('.chk-edit-role').prop('checked', false);
        roles.forEach(r => $(`.chk-edit-role[value="${r}"]`).prop('checked', true));
        $('#modalEditarUsuario').modal('show');
    });

    $('.btn-editar-rol').click(function() {
        const id = $(this).data('id');
        const perms = $(this).data('permissions') || [];
        $('#formEditarRol').attr('action', '/acceso/roles/' + id);
        $('#editRolName').val($(this).data('name'));
        $('.chk-edit-perm').prop('checked', false);
        perms.forEach(p => $(`.chk-edit-perm[value="${p}"]`).prop('checked', true));
        $('#modalEditarRol').modal('show');
    });

    $('.btn-editar-permiso').click(function() {
        const id = $(this).data('id');
        $('#formEditarPermiso').attr('action', '/acceso/permisos/' + id);
        $('#editPermisoName').val($(this).data('name'));
        $('#modalEditarPermiso').modal('show');
    });
});
</script>
@endsection
