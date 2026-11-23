@extends('layouts.report', ['tipoSeccionReporte' => 'renombres'])
@section('titulo', 'Bitácora e Historial de Notarías por Usuario')

@section('contenido')
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="h5 font-weight-bold m-0" style="color: #034d6d;">
                <i class="fa-solid fa-landmark mr-2"></i>BITÁCORA E HISTORIAL DE CAPTURA — NOTARÍAS
            </h1>
            <span class="badge badge-info">{{ $registros->total() }} registros encontrados</span>
        </div>

        <form method="GET" action="{{ route('admin.analytics_digitalizacion.reportes.detalles_renombre') }}" class="row align-items-end">
            <div class="col-sm-6 col-md-2 mb-2">
                <label class="small font-weight-bold mb-1">Desde:</label>
                <input type="date" name="inicio" class="form-control form-control-sm" value="{{ $inicio }}">
            </div>
            <div class="col-sm-6 col-md-2 mb-2">
                <label class="small font-weight-bold mb-1">Hasta:</label>
                <input type="date" name="fin" class="form-control form-control-sm" value="{{ $fin }}">
            </div>
            <div class="col-sm-6 col-md-2 mb-2">
                <label class="small font-weight-bold mb-1">Notaría / Directorio:</label>
                <input type="text" name="directorio" class="form-control form-control-sm" placeholder="Ej. NOTARIA 76..." value="{{ $directorio }}">
            </div>
            <div class="col-sm-6 col-md-2 mb-2">
                <label class="small font-weight-bold mb-1">Usuario:</label>
                <select name="user_id" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}" {{ $userId == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-3 mb-2">
                <label class="small font-weight-bold mb-1">Buscar Archivo / Volumen / Hash:</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Archivo original, nuevo, volumen..." value="{{ $search }}">
            </div>
            <div class="col-sm-6 col-md-1 mb-2">
                <button type="submit" class="btn btn-sm btn-primary w-100 font-weight-bold" style="background-color:#034d6d;border-color:#034d6d;">
                    <i class="fa-solid fa-filter"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover table-striped align-middle mb-0" style="font-size: 0.82rem;">
                <thead style="background-color:#034d6d;color:#fff;">
                    <tr>
                        <th class="text-center">#ID</th>
                        <th>Fecha / Hora</th>
                        <th>Usuario / Turno</th>
                        <th>Notaría / Volumen</th>
                        <th>Archivo Original</th>
                        <th>Archivo Nuevo</th>
                        <th>Rutas & Hash</th>
                        <th class="text-center">Acción</th>
                        <th class="text-center">Integridad</th>
                        <th class="text-center">Págs</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registros as $r)
                        <tr>
                            <td class="text-center text-muted font-weight-bold">{{ $r->id }}</td>
                            <td class="text-muted small">{{ $r->fecha_hora }}</td>
                            <td>
                                <div class="font-weight-bold text-dark">{{ $r->usuario_nombre ?: 'Sin Usuario' }}</div>
                                <span class="badge badge-light border">{{ $r->turno ?: 'Matutino' }}</span>
                            </td>
                            <td>
                                <div class="font-weight-bold">{{ $r->directorio ?: 'Notaría' }}</div>
                                <span class="badge badge-light border">{{ $r->volumen ?: ($r->lote ?: 'N/A') }}</span>
                            </td>
                            <td class="font-weight-bold text-dark">{{ $r->archivo_original ?: '-' }}</td>
                            <td class="font-weight-bold text-primary">{{ $r->archivo_nuevo ?: '-' }}</td>
                            <td>
                                @if($r->ruta_origen)<div class="text-xs text-muted"><strong>Orig:</strong> {{ $r->ruta_origen }}</div>@endif
                                @if($r->ruta_destino)<div class="text-xs text-slate-700"><strong>Dest:</strong> {{ $r->ruta_destino }}</div>@endif
                                @if($r->hash)<div class="text-xs font-mono text-sky-700">{{ substr($r->hash, 0, 14) }}...</div>@endif
                            </td>
                            <td class="text-center">
                                <span class="badge badge-info">{{ $r->accion ?: '-' }}</span>
                            </td>
                            <td class="text-center">
                                @if($r->archivo_danado)
                                    <span class="badge badge-danger">DAÑADO</span>
                                @else
                                    <span class="badge badge-success">ÍNTEGRO</span>
                                @endif
                            </td>
                            <td class="text-center font-weight-bold">{{ $r->paginas ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                No se encontraron registros de notarías en la bitácora para el rango seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($registros->hasPages())
        <div class="card-footer bg-white">
            {{ $registros->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection
