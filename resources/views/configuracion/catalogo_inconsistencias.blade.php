@extends('layouts.crud')

@section('titulo', 'Catálogo de Inconsistencias')
@section('crud_modulo', 'Catálogos Oficiales')
@section('crud_titulo', 'CRUD de Catálogo de Inconsistencias (R01–R09)')
@section('crud_subtitulo', 'Administra los códigos, motivos de rechazo y acciones correctivas disponibles en la estación de captura.')

@section('crud_acciones')
    <button type="button" class="btn btn-primary btn-sm px-3" style="background-color:#034d6d;border-color:#034d6d;" data-toggle="modal" data-target="#modalCrearInconsistencia">
        <i class="fa-solid fa-plus mr-1"></i> Nuevo Código
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
            <span><i class="fa-solid fa-triangle-exclamation text-amber-500 mr-2"></i> Códigos Registrados ({{ $items->count() }})</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">Código</th>
                        <th>Motivo de Rechazo</th>
                        <th>Acción Correctiva</th>
                        <th>Estado</th>
                        <th class="text-right px-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td class="px-3 font-mono font-weight-bold text-amber-700">{{ $item->codigo }}</td>
                            <td class="font-weight-bold text-slate-800">{{ $item->motivo_rechazo }}</td>
                            <td class="text-sky-800">{{ $item->accion_correctiva }}</td>
                            <td>
                                @if($item->activo)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-right px-3 text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary mr-1" data-toggle="modal" data-target="#modalEditarInc{{ $item->id }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form action="{{ route('admin.catalogo_inconsistencias.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar código {{ $item->codigo }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modales Editar Código (fuera de <table>) -->
    @foreach($catalogo as $item)
        <div class="modal fade" id="modalEditarInc{{ $item->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('admin.catalogo_inconsistencias.update', $item->id) }}" method="POST" class="modal-content bg-white border-0 shadow-lg">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-slate-900 text-white">
                        <h5 class="modal-title text-base font-weight-bold">Editar Código {{ $item->codigo }}</h5>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body bg-white">
                        <div class="form-group">
                            <label class="text-xs font-weight-bold">Código</label>
                            <input type="text" name="codigo" class="form-control font-mono" value="{{ $item->codigo }}" required maxlength="10">
                        </div>
                        <div class="form-group">
                            <label class="text-xs font-weight-bold">Motivo de Rechazo</label>
                            <input type="text" name="motivo_rechazo" class="form-control" value="{{ $item->motivo_rechazo }}" required>
                        </div>
                        <div class="form-group">
                            <label class="text-xs font-weight-bold">Acción Correctiva</label>
                            <input type="text" name="accion_correctiva" class="form-control" value="{{ $item->accion_correctiva }}" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="text-xs font-weight-bold">Estado</label>
                            <select name="activo" class="form-control">
                                <option value="1" {{ $item->activo ? 'selected' : '' }}>Activo</option>
                                <option value="0" {{ !$item->activo ? 'selected' : '' }}>Inactivo</option>
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

    <!-- Modal Crear -->
    <div class="modal fade" id="modalCrearInconsistencia" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form action="{{ route('admin.catalogo_inconsistencias.store') }}" method="POST" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-slate-900 text-white">
                    <h5 class="modal-title text-base font-weight-bold"><i class="fa-solid fa-plus-circle mr-2 text-amber-400"></i>Nuevo Código de Inconsistencia</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Código</label>
                        <input type="text" name="codigo" class="form-control font-mono" placeholder="Ej. R10" required maxlength="10">
                    </div>
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Motivo de Rechazo</label>
                        <input type="text" name="motivo_rechazo" class="form-control" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="text-xs font-weight-bold">Acción Correctiva</label>
                        <input type="text" name="accion_correctiva" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">Crear Código</button>
                </div>
            </form>
        </div>
    </div>
@endsection
