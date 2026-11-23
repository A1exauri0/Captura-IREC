@extends('layouts.crud')

@section('titulo', 'Aprobación Masiva de Renombres')
@section('crud_modulo', 'Ejecución Masiva hacia Stellum')
@section('crud_titulo', 'Aprobación por Delegación y Tipo de Sello / Documento')
@section('crud_subtitulo', 'Aprueba los cambios registrados en BD para despacharlos por chunks (500 registros/lote) hacia Stellum (https://app.astronmx.cloud/) y aplicar los renombres físicamente.')

@section('contenido')
    <!-- Selector de tipo documental (Solo Sellos activo temporalmente) -->
    <div class="d-flex flex-wrap mb-3" style="gap: 8px;">
        @foreach(['sellos' => 'Sellos' /* , 'registros' => 'Registros', 'notarias' => 'Notarías', 'catastro' => 'Catastro' */] as $k => $lbl)
            <a href="{{ route('admin.aprobacion.index', ['tipo' => $k]) }}"
               class="btn btn-sm {{ $tipo === $k ? 'btn-primary' : 'btn-light border' }}"
               style="{{ $tipo === $k ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                {{ $lbl }}
            </a>
        @endforeach
    </div>

    <!-- Matriz de aprobación por Delegación y Tipo de Sello -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-check-double text-sky-700 mr-2"></i>Resumen Pendiente de Aprobación — {{ strtoupper($tipo) }}</span>
            <span class="badge badge-info">Solo se envían registros sin colisión (revisar=0) y sin inconsistencias abiertas</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">Delegación / Directorio</th>
                        <th>Tipo Sello</th>
                        <th class="text-center">Listos p/ Aprobar</th>
                        <th class="text-center">Con Colisión</th>
                        <th class="text-center">Inconsistencias</th>
                        <th class="text-center">En Proceso</th>
                        <th class="text-center">Aplicados</th>
                        <th class="text-right px-3">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resumenGrupos as $g)
                        <tr>
                            <td class="px-3 font-weight-bold text-slate-800">{{ $g->delegacion_nombre }}</td>
                            <td><span class="badge badge-light border">{{ $g->tipo_sello_grupo }}</span></td>
                            <td class="text-center font-weight-bold text-emerald-700">{{ number_format($g->listos_aprobar) }}</td>
                            <td class="text-center">
                                <span class="badge {{ $g->con_colision > 0 ? 'badge-danger' : 'badge-light' }}">{{ number_format($g->con_colision) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $g->con_inconsistencia > 0 ? 'badge-warning' : 'badge-light' }}">{{ number_format($g->con_inconsistencia) }}</span>
                            </td>
                            <td class="text-center text-sky-700 font-weight-bold">{{ number_format($g->en_proceso) }}</td>
                            <td class="text-center text-muted">{{ number_format($g->aplicados) }}</td>
                            <td class="text-right px-3">
                                @if($g->listos_aprobar > 0)
                                    <form method="POST" action="{{ route('admin.aprobacion.enviar') }}" class="d-inline"
                                          onsubmit="return confirm('¿Aprobar {{ number_format($g->listos_aprobar) }} archivos de {{ $g->delegacion_nombre }} ({{ $g->tipo_sello_grupo }}) y enviarlos a Stellum para renombrado físico?');">
                                        @csrf
                                        <input type="hidden" name="tipo_documento" value="{{ $tipo }}">
                                        <input type="hidden" name="delegacion_id" value="{{ $g->delegacion_id }}">
                                        <input type="hidden" name="tipo_sello" value="{{ $g->tipo_sello_grupo }}">
                                        <input type="hidden" name="directorio" value="{{ $g->delegacion_nombre }}">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fa-solid fa-paper-plane mr-1"></i> Aprobar y Enviar a Stellum
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-muted">Sin pendientes listos</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No hay registros en BD para este tipo documental todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Historial de Órdenes enviadas a Stellum -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <i class="fa-solid fa-server text-sky-700 mr-2"></i> Órdenes Masivas Enviadas a Stellum
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">Orden</th>
                        <th>Tipo / Delegación / Sello</th>
                        <th>Aprobado Por</th>
                        <th>Progreso</th>
                        <th>Estado</th>
                        <th class="text-right px-3">Sincronizar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ordenes as $ord)
                        @php
                            $pct = $ord->total_archivos > 0 ? round(($ord->procesados / $ord->total_archivos) * 100) : 0;
                        @endphp
                        <tr>
                            <td class="px-3 font-weight-bold">#{{ $ord->id }}</td>
                            <td class="text-xs">
                                <span class="badge badge-info">{{ strtoupper($ord->tipo_documento) }}</span>
                                {{ $ord->delegacion->nombre ?? 'General' }} / {{ $ord->tipo_sello ?: 'General' }}
                            </td>
                            <td class="text-xs">{{ $ord->aprobador->name ?? '-' }}</td>
                            <td style="min-width: 180px;">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: {{ $pct }}%"></div>
                                </div>
                                <div class="text-xs text-muted mt-0.5">
                                    {{ number_format($ord->procesados) }} / {{ number_format($ord->total_archivos) }} (OK: {{ $ord->exitosos }}, Error: {{ $ord->fallidos }})
                                </div>
                            </td>
                            <td><span class="badge badge-secondary">{{ strtoupper($ord->estado) }}</span></td>
                            <td class="text-right px-3">
                                <button type="button" class="btn btn-xs btn-outline-primary btn-sync-orden" data-url="{{ route('admin.aprobacion.sincronizar', $ord->id) }}">
                                    <i class="fa-solid fa-rotate"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-3 text-muted">Aún no se han enviado órdenes masivas a Stellum.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Referencia de Reglas de Ruta Propuesta -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <i class="fa-solid fa-route text-sky-700 mr-2"></i> Reglas de Ruta Propuesta Activas (<code>config/reglas_destino_pdf.php</code>)
        </div>
        <div class="card-body py-3">
            <div class="row">
                @foreach($reglasDestino as $tipoKey => $reg)
                    <div class="col-md-6 mb-2">
                        <div class="p-2.5 bg-slate-50 rounded border text-xs">
                            <div class="font-weight-bold text-sky-800 uppercase">{{ $tipoKey }} — {{ $reg['regla_captura'] }}</div>
                            <code class="d-block mt-1 text-slate-700" style="word-break: break-all;">{{ $reg['plantilla'] }}</code>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@section('pos-script')
<script>
    document.querySelectorAll('.btn-sync-orden').forEach(btn => {
        btn.addEventListener('click', async function () {
            this.disabled = true;
            try {
                const res = await fetch(this.dataset.url);
                const data = await res.json();
                if (data.ok) {
                    window.location.reload();
                } else {
                    alert(data.mensaje || 'No se pudo consultar Stellum.');
                }
            } finally {
                this.disabled = false;
            }
        });
    });
</script>
@endsection
