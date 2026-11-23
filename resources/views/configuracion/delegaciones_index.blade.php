@extends('layouts.crud')

@section('titulo', 'Catálogo de Delegaciones')
@section('crud_modulo', 'Catálogos Oficiales')
@section('crud_titulo', 'Gestión de Delegaciones')
@section('crud_subtitulo', 'Alta, edición y administración de las delegaciones utilizadas en la estación de captura y reportes.')

@section('crud_acciones')
    <button type="button" class="btn btn-primary btn-sm px-3" style="background-color:#034d6d;border-color:#034d6d;" data-toggle="modal" data-target="#modalCrearDelegacion">
        <i class="fa-solid fa-plus mr-1"></i> Nueva Delegación
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
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-building-flag text-sky-700 mr-2"></i> Delegaciones Registradas ({{ $delegaciones->count() }})</span>
            <input type="text" id="filtroDelegaciones" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Buscar por clave o nombre...">
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-sm" id="tablaDelegaciones">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">ID</th>
                        <th>Clave</th>
                        <th>Nombre</th>
                        <th>Nota</th>
                        <th>Estado</th>
                        <th class="text-right px-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($delegaciones as $d)
                        <tr>
                            <td class="px-3 text-muted font-weight-bold">#{{ $d->id }}</td>
                            <td class="font-mono font-weight-bold text-sky-700">{{ $d->clave }}</td>
                            <td class="font-weight-bold text-slate-800">{{ $d->nombre }}</td>
                            <td class="text-xs text-muted">{{ $d->nota ?: '-' }}</td>
                            <td>
                                @if($d->estatus)
                                    <span class="badge badge-success">Activa</span>
                                @else
                                    <span class="badge badge-secondary">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-right px-3">
                                <button type="button" class="btn btn-sm btn-outline-primary mr-1" data-toggle="modal" data-target="#modalEditarDelegacion{{ $d->id }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form action="{{ route('admin.delegaciones.destroy', $d->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar la delegación {{ $d->nombre }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal fade" id="modalEditarDelegacion{{ $d->id }}" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <form action="{{ route('admin.delegaciones.update', $d->id) }}" method="POST" class="modal-content border-0 shadow-lg">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header bg-slate-900 text-white">
                                        <h5 class="modal-title text-base font-weight-bold">Editar Delegación</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label class="text-xs font-weight-bold">Clave</label>
                                            <input type="text" name="clave" class="form-control" value="{{ $d->clave }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="text-xs font-weight-bold">Nombre</label>
                                            <input type="text" name="nombre" class="form-control" value="{{ $d->nombre }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="text-xs font-weight-bold">Nota (opcional)</label>
                                            <input type="text" name="nota" class="form-control" value="{{ $d->nota }}">
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="text-xs font-weight-bold">Estado</label>
                                            <select name="estatus" class="form-control">
                                                <option value="1" {{ $d->estatus ? 'selected' : '' }}>Activa</option>
                                                <option value="0" {{ !$d->estatus ? 'selected' : '' }}>Inactiva</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light">
                                        <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">Guardar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Crear Delegación -->
    <div class="modal fade" id="modalCrearDelegacion" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form action="{{ route('admin.delegaciones.store') }}" method="POST" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-slate-900 text-white">
                    <h5 class="modal-title text-base font-weight-bold"><i class="fa-solid fa-building-flag mr-2 text-sky-400"></i>Nueva Delegación</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Clave</label>
                        <input type="text" name="clave" class="form-control" placeholder="Ej. 01" required>
                    </div>
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Nombre de Delegación</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej. Tuxtla Gutiérrez" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="text-xs font-weight-bold">Nota (opcional)</label>
                        <input type="text" name="nota" class="form-control" placeholder="Observaciones...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">Crear Delegación</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('pos-script')
<script>
    document.getElementById('filtroDelegaciones')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#tablaDelegaciones tbody tr').forEach(tr => {
            tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });
</script>
@endsection
