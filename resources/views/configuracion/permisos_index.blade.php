@extends('layouts.crud')

@section('titulo', 'Gestión de Permisos')
@section('crud_title', 'Permisos del Sistema')
@section('crud_subtitle', 'Administra el catálogo de permisos granulares asignables a los roles.')

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
                <div class="card-header bg-white"><i class="fa-solid fa-key text-sky-700 mr-2"></i>Crear Nuevo Permiso</div>
                <form action="{{ route('admin.permisos.store') }}" method="POST" class="card-body">
                    @csrf
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Clave del Permiso</label>
                        <input type="text" name="name" class="form-control" placeholder="Ej. autorizar_peticiones, ver_bitacora..." required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-block" style="background-color:#034d6d;border-color:#034d6d;">
                        <i class="fa-solid fa-plus mr-1"></i> Agregar Permiso
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><i class="fa-solid fa-list-check text-sky-700 mr-2"></i>Catálogo de Permisos ({{ $permisos->count() }})</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-sm">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                            <tr>
                                <th class="px-3">ID</th>
                                <th>Permiso</th>
                                <th>Roles Asociados</th>
                                <th class="text-right px-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($permisos as $perm)
                                <tr>
                                    <td class="px-3 text-muted font-weight-bold">#{{ $perm->id }}</td>
                                    <td class="font-weight-bold text-slate-800">{{ $perm->name }}</td>
                                    <td><span class="badge badge-light border">{{ $perm->roles_count }}</span></td>
                                    <td class="text-right px-3">
                                        <form action="{{ route('admin.permisos.update', $perm->id) }}" method="POST" class="d-inline-flex align-items-center mr-1" style="gap: 4px;">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="name" value="{{ $perm->name }}" class="form-control form-control-sm" style="width: 170px;" required>
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Renombrar"><i class="fa-solid fa-check"></i></button>
                                        </form>
                                        <form action="{{ route('admin.permisos.destroy', $perm->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar permiso {{ $perm->name }}?');">
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
        </div>
    </div>
@endsection
