@extends('layouts.crud')

@section('titulo', 'Gestión de Roles')
@section('crud_title', 'Roles del Sistema')
@section('crud_subtitle', 'Define perfiles de acceso y asigna los permisos correspondientes a cada rol.')

@section('crud_content')
    @if($errors->any())
        <div class="alert alert-danger rounded-lg mb-3">
            <ul class="mb-0 pl-3 text-sm">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><i class="fa-solid fa-plus-circle text-sky-700 mr-2"></i>Crear Nuevo Rol</div>
                <form action="{{ route('admin.roles.store') }}" method="POST" class="card-body">
                    @csrf
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Nombre del Rol</label>
                        <input type="text" name="name" class="form-control" placeholder="Ej. Supervisor, Auditor..." required>
                    </div>
                    <div class="form-group">
                        <label class="text-xs font-weight-bold d-block">Permisos Asignados</label>
                        <div class="p-2 bg-slate-50 rounded border" style="max-height: 220px; overflow-y: auto;">
                            @foreach($permisos as $p)
                                <label class="d-flex align-items-center mb-1.5 text-sm" style="gap: 8px;">
                                    <input type="checkbox" name="permissions[]" value="{{ $p->name }}">
                                    <span>{{ $p->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-block" style="background-color:#034d6d;border-color:#034d6d;">
                        <i class="fa-solid fa-save mr-1"></i> Guardar Rol
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><i class="fa-solid fa-user-shield text-sky-700 mr-2"></i>Roles Configurados ({{ $roles->count() }})</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-sm">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                            <tr>
                                <th class="px-3">Rol</th>
                                <th>Usuarios</th>
                                <th>Permisos</th>
                                <th class="text-right px-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $rol)
                                <tr>
                                    <td class="px-3 font-weight-bold text-slate-800">{{ $rol->name }}</td>
                                    <td><span class="badge badge-light border">{{ $rol->users_count }}</span></td>
                                    <td>
                                        @forelse($rol->permissions as $perm)
                                            <span class="badge badge-secondary mr-1 mb-1">{{ $perm->name }}</span>
                                        @empty
                                            <span class="text-xs text-muted">Sin permisos</span>
                                        @endforelse
                                    </td>
                                    <td class="text-right px-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary mr-1" data-toggle="modal" data-target="#modalEditarRol{{ $rol->id }}">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        @if(strtolower($rol->name) !== 'superadmin')
                                            <form action="{{ route('admin.roles.destroy', $rol->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar rol {{ $rol->name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>

                                <div class="modal fade" id="modalEditarRol{{ $rol->id }}" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <form action="{{ route('admin.roles.update', $rol->id) }}" method="POST" class="modal-content border-0 shadow-lg">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header bg-slate-900 text-white">
                                                <h5 class="modal-title text-base font-weight-bold">Editar Rol: {{ $rol->name }}</h5>
                                                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <label class="text-xs font-weight-bold">Nombre</label>
                                                    <input type="text" name="name" class="form-control" value="{{ $rol->name }}" required>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label class="text-xs font-weight-bold d-block">Permisos</label>
                                                    <div class="p-2 bg-slate-50 rounded border" style="max-height: 220px; overflow-y: auto;">
                                                        @foreach($permisos as $p)
                                                            <label class="d-flex align-items-center mb-1.5 text-sm" style="gap: 8px;">
                                                                <input type="checkbox" name="permissions[]" value="{{ $p->name }}" {{ $rol->hasPermissionTo($p->name) ? 'checked' : '' }}>
                                                                <span>{{ $p->name }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">Actualizar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
