@extends('layouts.crud')

@section('titulo', 'Asignación de Captura por Usuario')
@section('crud_modulo', 'Operación y Reglas Automáticas')
@section('crud_titulo', 'Asignación de Tipo de Archivo por Usuario')
@section('crud_subtitulo', 'Define qué tipo de archivo trabajará cada capturista para que su estación de captura seleccione automáticamente la regla correspondiente.')

@section('contenido')
    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><i class="fa-solid fa-user-tag text-sky-700 mr-2"></i>Asignar Tipo de Archivo</div>
                <form method="POST" action="{{ route('admin.asignaciones.guardar') }}" class="card-body">
                    @csrf
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Usuario Capturista</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">-- Seleccionar usuario --</option>
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->user }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Tipo de Documento (Define Regla Automática)</label>
                        <select name="tipo_documento" class="form-control" required>
                            <option value="registros">Registros (Auto-selecciona Regla 1)</option>
                            <option value="sellos">Sellos y Boletas (Auto-selecciona Regla 2)</option>
                            <option value="notarias">Notarías (ReglaNotarias)</option>
                            <option value="catastro">Catastro (ReglaCatastro)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Delegación Asignada (opcional)</label>
                        <select name="delegacion_id" class="form-control">
                            <option value="">-- Cualquier delegación --</option>
                            @foreach($delegaciones as $d)
                                <option value="{{ $d->id }}">{{ $d->clave }} - {{ $d->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Tipo de Sello (opcional, solo Sellos)</label>
                        <input type="text" name="tipo_sello" class="form-control" placeholder="Ej. Boleta de Inscripción">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-block" style="background-color:#034d6d;border-color:#034d6d;">
                        <i class="fa-solid fa-save mr-1"></i> Guardar Asignación
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><i class="fa-solid fa-list-check text-sky-700 mr-2"></i>Asignaciones Activas ({{ $asignaciones->count() }})</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-sm">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                            <tr>
                                <th class="px-3">Usuario</th>
                                <th>Tipo Archivo</th>
                                <th>Regla Automática</th>
                                <th>Delegación / Sello</th>
                                <th class="text-right px-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($asignaciones as $a)
                                <tr>
                                    <td class="px-3 font-weight-bold text-slate-800">{{ $a->usuario->name ?? ('Usuario #' . $a->user_id) }}</td>
                                    <td><span class="badge badge-info uppercase">{{ $a->tipo_documento }}</span></td>
                                    <td><span class="badge badge-success">{{ $a->regla_automatica }}</span></td>
                                    <td class="text-xs">
                                        {{ $a->delegacion->nombre ?? 'Todas' }}
                                        @if($a->tipo_sello)
                                            <span class="text-muted">/ {{ $a->tipo_sello }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right px-3">
                                        <form method="POST" action="{{ route('admin.asignaciones.eliminar', $a->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta asignación?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No hay asignaciones configuradas todavía.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
