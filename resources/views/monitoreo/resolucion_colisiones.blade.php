@extends('layouts.crud')

@section('titulo', 'Resolución de Colisiones')
@section('crud_modulo', 'Control de Entregables')
@section('crud_titulo', 'Archivos Marcados para Revisar (Colisión con Entregables)')
@section('crud_subtitulo', 'Cuando un nombre capturado ya existe en indice_archivos_pdfs, decide aquí qué archivo conservar y cuál marcar para eliminación.')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3" style="gap: 8px;">
        <div class="d-flex flex-wrap" style="gap: 8px;">
            @foreach(['sellos' => 'Sellos' /* , 'registros' => 'Registros', 'notarias' => 'Notarías', 'catastro' => 'Catastro' */] as $k => $lbl)
                <a href="{{ route('admin.colisiones.index', ['tipo' => $k, 'decision' => $decision]) }}"
                   class="btn btn-sm {{ $tipo === $k ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ $tipo === $k ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    {{ $lbl }}
                    <span class="badge {{ ($contadores[$k] ?? 0) > 0 ? 'badge-danger' : 'badge-light' }} ml-1">{{ $contadores[$k] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        <div class="btn-group btn-group-sm">
            <a href="{{ route('admin.colisiones.index', ['tipo' => $tipo, 'decision' => 'pendiente']) }}" class="btn {{ $decision === 'pendiente' ? 'btn-dark' : 'btn-light border' }}">Pendientes</a>
            <a href="{{ route('admin.colisiones.index', ['tipo' => $tipo, 'decision' => 'todas']) }}" class="btn {{ $decision === 'todas' ? 'btn-dark' : 'btn-light border' }}">Todas</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">ID / Capturista</th>
                        <th>Archivo Nuevo / Ruta Propuesta</th>
                        <th>Archivo Existente en Entregables</th>
                        <th>Detalles</th>
                        <th class="text-right px-3">Decisión</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registros as $r)
                        <tr>
                            <td class="px-3 text-xs">
                                <div class="font-weight-bold">#{{ $r->id }}</div>
                                <div>{{ $r->usuario_nombre ?: ('Usuario #' . $r->user_id) }}</div>
                                <div class="text-muted">{{ $r->fecha_hora }}</div>
                            </td>
                            <td class="text-xs">
                                <div class="font-weight-bold text-sky-800">{{ $r->archivo_nuevo }}</div>
                                <div><strong>Origen:</strong> <code style="word-break: break-all;">{{ $r->ruta_origen ?: $r->ruta }}</code></div>
                                <div><strong>Propuesta:</strong> <code style="word-break: break-all;">{{ $r->ruta_propuesta }}</code></div>
                            </td>
                            <td class="text-xs">
                                <span class="badge badge-danger mb-1">Ya existe en Entregables</span>
                                <div><strong>ID Índice:</strong> {{ $r->archivo_colision_id ?: '-' }}</div>
                                <code class="text-danger" style="word-break: break-all;">{{ $r->ruta_colision ?: 'Ver detalles' }}</code>
                            </td>
                            <td class="text-xs text-muted">{{ $r->detalles }}</td>
                            <td class="text-right px-3">
                                <form method="POST" action="{{ route('admin.colisiones.resolver', [$tipo, $r->id]) }}" class="d-flex flex-column align-items-end" style="gap: 4px;">
                                    @csrf
                                    <select name="decision_revision" class="form-control form-control-sm" style="width: 210px;">
                                        <option value="conservar_nuevo">Conservar Nuevo (Eliminar Existente)</option>
                                        <option value="conservar_existente">Conservar Existente (Eliminar Nuevo)</option>
                                        <option value="conservar_ambos">Conservar Ambos</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary" style="background-color:#034d6d;border-color:#034d6d;">
                                        <i class="fa-solid fa-gavel mr-1"></i> Aplicar Decisión
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No hay colisiones pendientes de revisión.</td>
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
