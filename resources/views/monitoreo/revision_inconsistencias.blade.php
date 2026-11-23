@extends('layouts.crud')

@section('titulo', 'Revisión de Inconsistencias')
@section('crud_modulo', 'Supervisión Documental')
@section('crud_titulo', 'Revisión de Inconsistencias (Catálogo R01–R09)')
@section('crud_subtitulo', 'Gestiona los documentos marcados con inconsistencia por los capturistas y da seguimiento a su acción correctiva.')

@section('contenido')
    <!-- Pestañas por tipo documental (Solo Sellos activo temporalmente) -->
    <div class="d-flex flex-wrap mb-3" style="gap: 8px;">
        @foreach(['sellos' => 'Sellos' /* , 'registros' => 'Registros', 'notarias' => 'Notarías', 'catastro' => 'Catastro' */] as $k => $lbl)
            <a href="{{ route('admin.inconsistencias.index', ['tipo' => $k]) }}"
               class="btn btn-sm {{ $tipo === $k ? 'btn-primary' : 'btn-light border' }}"
               style="{{ $tipo === $k ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                {{ $lbl }}
                <span class="badge {{ ($contadores[$k] ?? 0) > 0 ? 'badge-warning' : 'badge-light' }} ml-1">{{ $contadores[$k] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <!-- Filtros -->
    <form method="GET" action="{{ route('admin.inconsistencias.index') }}" class="card border-0 shadow-sm mb-3">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <div class="card-body py-2.5 d-flex flex-wrap align-items-end" style="gap: 10px;">
            <div>
                <label class="text-xs font-weight-bold mb-1 d-block">Código</label>
                <select name="codigo" class="form-control form-control-sm">
                    <option value="">Todos (R01–R09)</option>
                    @foreach($catalogo as $c)
                        <option value="{{ $c->codigo }}" {{ $codigo === $c->codigo ? 'selected' : '' }}>
                            {{ $c->codigo }} — {{ $c->motivo_rechazo }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-weight-bold mb-1 d-block">Estado</label>
                <select name="estado" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    <option value="pendiente" {{ $estado === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                    <option value="en_revision" {{ $estado === 'en_revision' ? 'selected' : '' }}>En Revisión</option>
                    <option value="reescaneo_solicitado" {{ $estado === 'reescaneo_solicitado' ? 'selected' : '' }}>Reescaneo Solicitado</option>
                    <option value="resuelta" {{ $estado === 'resuelta' ? 'selected' : '' }}>Resuelta</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">
                <i class="fa-solid fa-filter mr-1"></i> Filtrar
            </button>
        </div>
    </form>

    <!-- Tabla de inconsistencias -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">ID / Fecha</th>
                        <th>Código y Motivo</th>
                        <th>Acción Correctiva</th>
                        <th>Archivo / Ruta Origen</th>
                        <th>Capturista</th>
                        <th>Estado</th>
                        <th class="text-right px-3">Resolver</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registros as $r)
                        <tr>
                            <td class="px-3 text-xs">
                                <div class="font-weight-bold">#{{ $r->id }}</div>
                                <div class="text-muted">{{ $r->fecha_hora }}</div>
                            </td>
                            <td>
                                <span class="badge badge-warning font-weight-bold mr-1">{{ $r->inconsistencia_codigo ?: 'N/A' }}</span>
                                <span class="text-xs font-weight-bold text-slate-800">{{ $r->motivo_rechazo ?: $r->detalles }}</span>
                                @if($r->inconsistencia_comentario)
                                    <div class="text-xs text-muted mt-0.5">Nota: {{ $r->inconsistencia_comentario }}</div>
                                @endif
                            </td>
                            <td class="text-xs text-sky-800 font-weight-bold">{{ $r->accion_correctiva ?: '-' }}</td>
                            <td class="text-xs">
                                <div class="font-weight-bold">{{ $r->archivo_original }}</div>
                                <code class="text-muted" style="word-break: break-all;">{{ $r->ruta_origen ?: $r->ruta }}</code>
                            </td>
                            <td class="text-xs">{{ $r->usuario_nombre ?: ('Usuario #' . $r->user_id) }}</td>
                            <td>
                                @php $est = $r->inconsistencia_estado ?: 'pendiente'; @endphp
                                <span class="badge {{ $est === 'resuelta' ? 'badge-success' : ($est === 'pendiente' ? 'badge-danger' : 'badge-info') }}">
                                    {{ strtoupper(str_replace('_', ' ', $est)) }}
                                </span>
                            </td>
                            <td class="text-right px-3">
                                <form method="POST" action="{{ route('admin.inconsistencias.actualizar', [$tipo, $r->id]) }}" class="d-inline-flex align-items-center" style="gap: 4px;">
                                    @csrf
                                    <select name="inconsistencia_estado" class="form-control form-control-sm" style="width: 145px;">
                                        <option value="pendiente" {{ $est === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                                        <option value="en_revision" {{ $est === 'en_revision' ? 'selected' : '' }}>En Revisión</option>
                                        <option value="reescaneo_solicitado" {{ $est === 'reescaneo_solicitado' ? 'selected' : '' }}>Reescaneo</option>
                                        <option value="resuelta" {{ $est === 'resuelta' ? 'selected' : '' }}>Resuelta</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-check"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Sin inconsistencias registradas para este filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($registros->hasPages())
            <div class="card-footer bg-white py-2">{{ $registros->links('pagination::bootstrap-4') }}</div>
        @endif
    </div>
@endsection
