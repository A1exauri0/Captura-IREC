@extends('layouts.crud')

@section('titulo', 'Gestión de Usuarios')
@section('crud_modulo', 'Control de Acceso')
@section('crud_titulo', 'Usuarios del Sistema')
@section('crud_subtitulo', 'Administra las cuentas, turnos adaptativos y roles asignados de forma centralizada.')

@section('crud_acciones')
    <button type="button" class="btn btn-primary btn-sm px-3" style="background-color:#034d6d;border-color:#034d6d;" data-toggle="modal" data-target="#modalCrearUsuario">
        <i class="fa-solid fa-user-plus mr-1"></i> Nuevo Usuario
    </button>
@endsection

@section('contenido')
    @if($errors->any())
        <div class="alert alert-danger rounded-lg mb-3">
            <ul class="mb-0 pl-3 text-sm">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <span><i class="fa-solid fa-users text-sky-700 mr-2"></i> Listado de Usuarios ({{ $usuarios->count() }})</span>
            <input type="text" id="filtroUsuarios" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Filtrar por nombre, usuario o correo...">
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-sm" id="tablaUsuarios">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">ID</th>
                        <th>Nombre</th>
                        <th>Usuario / Correo</th>
                        <th>Rol</th>
                        <th>Turno</th>
                        <th>PC / Equipo Asignado</th>
                        <th>Estado</th>
                        <th class="text-right px-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $u)
                        <tr>
                            <td class="px-3 font-weight-bold text-slate-500">#{{ $u->id }}</td>
                            <td class="font-weight-bold text-slate-800">{{ $u->name }}</td>
                            <td>
                                <div class="font-medium text-slate-700">{{ $u->user }}</div>
                                <div class="text-xs text-muted">{{ $u->email }}</div>
                            </td>
                            <td>
                                @forelse($u->roles as $rol)
                                    <span class="badge badge-info px-2 py-1 mr-1">{{ $rol->name }}</span>
                                @empty
                                    <span class="text-xs text-muted">Sin rol</span>
                                @endforelse
                            </td>
                            <td>
                                <span class="badge badge-light border text-slate-700">
                                    {{ $u->turnoRelacion->nombre ?? $u->turno ?? 'Matutino' }}
                                </span>
                            </td>
                            <td>
                                @if($u->equipo_no_serie)
                                    <span class="badge badge-primary" style="background-color:#034d6d;">
                                        <i class="fa-solid fa-desktop mr-1"></i> {{ $u->equipo_no_serie }}
                                    </span>
                                    <div class="text-muted" style="font-size: 11px;">
                                        {{ $u->equipo_tipo ?: 'PC' }} {{ $u->equipo_marca ? '· ' . $u->equipo_marca : '' }}
                                    </div>
                                @else
                                    <span class="text-xs text-muted">Sin equipo</span>
                                @endif
                            </td>
                            <td>
                                @if($u->estatus)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-right px-3">
                                <button type="button" class="btn btn-sm btn-outline-primary mr-1"
                                        data-toggle="modal" data-target="#modalEditarUsuario{{ $u->id }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                @if(auth()->id() !== $u->id)
                                    <form action="{{ route('admin.usuarios.destroy', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar al usuario {{ $u->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modales Editar Usuario (fuera de <table> para renderizado correcto) -->
    @foreach($usuarios as $u)
        <div class="modal fade" id="modalEditarUsuario{{ $u->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form action="{{ route('admin.usuarios.update', $u->id) }}" method="POST" class="modal-content bg-white border-0 shadow-lg">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-slate-900 text-white">
                        <h5 class="modal-title text-base font-weight-bold">Editar Usuario: {{ $u->name }}</h5>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body bg-white">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-weight-bold">Nombre Completo</label>
                                <input type="text" name="name" class="form-control" value="{{ $u->name }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-weight-bold">Usuario (Login)</label>
                                <input type="text" name="user" class="form-control" value="{{ $u->user }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-weight-bold">Correo Electrónico</label>
                                <input type="email" name="email" class="form-control" value="{{ $u->email }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-weight-bold">Nueva Contraseña (opcional)</label>
                                <input type="password" name="password" class="form-control" placeholder="Dejar vacío para mantener">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-weight-bold">Turno Asignado</label>
                                <select name="turno_id" class="form-control">
                                    <option value="">-- Sin turno específico --</option>
                                    @foreach($turnos as $t)
                                        <option value="{{ $t->id }}" {{ $u->turno_id == $t->id ? 'selected' : '' }}>
                                            {{ $t->nombre }} ({{ substr($t->hora_entrada, 0, 5) }} - {{ substr($t->hora_salida, 0, 5) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-weight-bold">Estado</label>
                                <select name="estatus" class="form-control">
                                    <option value="1" {{ $u->estatus ? 'selected' : '' }}>Activo</option>
                                    <option value="0" {{ !$u->estatus ? 'selected' : '' }}>Inactivo</option>
                                </select>
                            </div>
                            <div class="col-12 form-group mb-0">
                                <label class="text-xs font-weight-bold d-block">Roles Asignados</label>
                                <div class="d-flex flex-wrap p-2 bg-slate-50 rounded border" style="gap: 12px;">
                                    @foreach($roles as $rol)
                                        <label class="mb-0 d-flex align-items-center text-sm" style="gap: 6px;">
                                            <input type="checkbox" name="roles[]" value="{{ $rol->name }}" {{ $u->hasRole($rol->name) ? 'checked' : '' }}>
                                            <span>{{ $rol->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <!-- Modal Crear Usuario -->
    <div class="modal fade" id="modalCrearUsuario" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <form action="{{ route('admin.usuarios.store') }}" method="POST" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-slate-900 text-white">
                    <h5 class="modal-title text-base font-weight-bold"><i class="fa-solid fa-user-plus mr-2 text-sky-400"></i>Nuevo Usuario</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="text-xs font-weight-bold">Nombre Completo</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-xs font-weight-bold">Usuario (Login)</label>
                            <input type="text" name="user" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-xs font-weight-bold">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="text-xs font-weight-bold">Contraseña</label>
                            <input type="password" name="password" class="form-control" minlength="6" required>
                        </div>
                        <div class="col-md-12 form-group">
                            <label class="text-xs font-weight-bold">Turno Adaptativo</label>
                            <select name="turno_id" class="form-control">
                                <option value="">-- Seleccionar turno --</option>
                                @foreach($turnos as $t)
                                    <option value="{{ $t->id }}">{{ $t->nombre }} ({{ substr($t->hora_entrada, 0, 5) }} - {{ substr($t->hora_salida, 0, 5) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 form-group mb-0">
                            <label class="text-xs font-weight-bold d-block">Roles</label>
                            <div class="d-flex flex-wrap p-2 bg-slate-50 rounded border" style="gap: 12px;">
                                @foreach($roles as $rol)
                                    <label class="mb-0 d-flex align-items-center text-sm" style="gap: 6px;">
                                        <input type="checkbox" name="roles[]" value="{{ $rol->name }}">
                                        <span>{{ $rol->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('pos-script')
<script>
    document.getElementById('filtroUsuarios')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#tablaUsuarios tbody tr').forEach(tr => {
            tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });
</script>
@endsection
