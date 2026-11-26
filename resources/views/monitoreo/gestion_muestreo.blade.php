@extends('layouts.crud')

@section('titulo', 'Gestión de Muestreo de Calidad')
@section('crud_modulo', 'Control de Calidad y Muestreo')
@section('crud_titulo', 'Gestión de Muestreos por Fecha y Turno (Sellos)')
@section('crud_subtitulo', 'Genera y supervisa los muestreos sobre lo trabajado en auditoria_sellos aplicando las reglas oficiales (96% Confianza, 30% Muestra o 50% Muestra). Hasta que un muestreo no se genere aquí, no podrá trabajarse en Modo Muestreo.')

@section('contenido')
    @if(session('success'))
        <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger shadow-sm">{{ session('error') }}</div>
    @endif

    <!-- 1. MUESTREOS POR HACER (Turnos trabajados en auditoria_sellos pendientes de generar muestreo) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <i class="fa-solid fa-hourglass-half text-amber-600 mr-2"></i>
                <span class="font-weight-bold">Muestreos Por Hacer (Turnos Capturados en <code>auditoria_sellos</code>)</span>
            </div>
            <span class="badge badge-warning">{{ count($muestreosPorHacer) }} pendientes de generar</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-sm align-middle">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">Fecha Laboral</th>
                        <th>Turno</th>
                        <th class="text-center">Universo Capturado</th>
                        <th>Capturistas</th>
                        <th style="min-width: 250px;">Regla de Muestreo</th>
                        <th class="text-center">Muestra / Límite Error</th>
                        <th class="text-right px-3">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($muestreosPorHacer as $idx => $item)
                        @php
                            $p96 = $item['preview_reglas']['confianza_96'];
                            $p30 = $item['preview_reglas']['porcentaje_30'];
                            $p50 = $item['preview_reglas']['porcentaje_50'];
                        @endphp
                        <tr>
                            <td class="px-3 font-weight-bold text-slate-800">
                                <i class="fa-regular fa-calendar mr-1 text-sky-700"></i>
                                {{ \Carbon\Carbon::parse($item['fecha_laboral'])->format('d/m/Y') }}
                            </td>
                            <td>
                                <span class="badge badge-info px-2 py-1">{{ $item['turno'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="font-weight-bold text-slate-800">{{ number_format($item['universo']) }}</span> PDFs
                                <div class="text-xs text-muted">
                                    <span class="text-emerald-600">Val: {{ number_format($item['validados']) }}</span> ·
                                    <span class="text-sky-600">Ren: {{ number_format($item['renombrados']) }}</span>
                                </div>
                            </td>
                            <td class="text-xs text-slate-600">
                                {{ implode(', ', array_slice($item['capturistas'], 0, 4)) }}
                                @if(count($item['capturistas']) > 4)
                                    <span class="text-muted">(+{{ count($item['capturistas']) - 4 }} más)</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.muestreo.generar') }}" id="formGenerarMuestreo_{{ $idx }}" class="m-0">
                                    @csrf
                                    <input type="hidden" name="fecha_laboral" value="{{ $item['fecha_laboral'] }}">
                                    <input type="hidden" name="turno" value="{{ $item['turno'] }}">
                                    <select name="regla" class="form-control form-control-sm selector-regla-muestreo"
                                            data-target="#previewMetricas_{{ $idx }}"
                                            data-m96="{{ $p96['muestra'] }}" data-e96="{{ $p96['errores_permitidos'] }}"
                                            data-m30="{{ $p30['muestra'] }}" data-e30="{{ $p30['errores_permitidos'] }}"
                                            data-m50="{{ $p50['muestra'] }}" data-e50="{{ $p50['errores_permitidos'] }}">
                                        <option value="confianza_96">Estándar: 96% Confianza (5% Error)</option>
                                        <option value="porcentaje_30">Muestreo 30% (20% Margen Error)</option>
                                        <option value="porcentaje_50">Muestreo 50% (10% Margen Error)</option>
                                    </select>
                                </form>
                            </td>
                            <td class="text-center" id="previewMetricas_{{ $idx }}">
                                <span class="badge badge-primary px-2 py-1">Muestra: {{ number_format($p96['muestra']) }}</span>
                                <span class="badge badge-danger px-2 py-1">Máx. Error: {{ number_format($p96['errores_permitidos']) }}</span>
                            </td>
                            <td class="text-right px-3">
                                <button type="submit" form="formGenerarMuestreo_{{ $idx }}" class="btn btn-sm btn-success font-weight-bold shadow-sm">
                                    <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Generar Muestreo
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                No hay turnos pendientes de generar muestreo en este momento.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. MUESTREOS GENERADOS (EN PROCESO Y HECHOS) -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <i class="fa-solid fa-vial-circle-check text-emerald-600 mr-2"></i>
                <span class="font-weight-bold">Muestreos Generados (En Proceso y Hechos)</span>
            </div>
            <a href="{{ route('admin.captura.index', ['modo' => 'muestreo']) }}" class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-desktop mr-1"></i> Ir a Estación en Modo Muestreo
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-sm align-middle">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">ID</th>
                        <th>Fecha Laboral / Turno</th>
                        <th>Regla Aplicada</th>
                        <th class="text-center">Universo</th>
                        <th class="text-center">Progreso Muestra</th>
                        <th class="text-center">Errores (Renombres)</th>
                        <th class="text-center">Dictamen / Estado</th>
                        <th class="text-right px-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($muestreosGenerados as $m)
                        @php
                            $pct = $m->muestra > 0 ? round(($m->revisados / $m->muestra) * 100) : 0;
                            $etiquetaRegla = match($m->regla) {
                                'porcentaje_30' => '30% Muestra (20% Error)',
                                'porcentaje_50' => '50% Muestra (10% Error)',
                                default         => '96% Confianza (5% Error)',
                            };
                            $badgeEstado = match($m->estado) {
                                'aprobado'  => 'badge-success',
                                'rechazado' => 'badge-danger',
                                default     => 'badge-warning',
                            };
                        @endphp
                        <tr>
                            <td class="px-3 font-weight-bold">#{{ $m->id }}</td>
                            <td>
                                <div class="font-weight-bold text-slate-800">{{ \Carbon\Carbon::parse($m->fecha_laboral)->format('d/m/Y') }}</div>
                                <span class="badge badge-light border text-xs">{{ $m->turno }}</span>
                            </td>
                            <td><span class="badge badge-info">{{ $etiquetaRegla }}</span></td>
                            <td class="text-center font-weight-bold">{{ number_format($m->universo) }}</td>
                            <td style="min-width: 170px;">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-emerald-600" style="width: {{ $pct }}%"></div>
                                </div>
                                <div class="text-xs text-muted mt-1 text-center">
                                    <strong>{{ number_format($m->revisados) }}</strong> / {{ number_format($m->muestra) }} ({{ $pct }}%)
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $m->errores_detectados > $m->errores_permitidos ? 'badge-danger' : ($m->errores_detectados > 0 ? 'badge-warning' : 'badge-light border') }} px-2 py-1">
                                    {{ number_format($m->errores_detectados) }} / {{ number_format($m->errores_permitidos) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $badgeEstado }} px-2 py-1 uppercase">{{ str_replace('_', ' ', $m->estado) }}</span>
                            </td>
                            <td class="text-right px-3">
                                <button type="button" class="btn btn-xs btn-outline-info btn-ver-detalle-muestreo"
                                        data-url="{{ route('admin.muestreo.detalle', $m->id) }}">
                                    <i class="fa-solid fa-eye"></i> Detalle
                                </button>
                                <form method="POST" action="{{ route('admin.muestreo.destroy', $m->id) }}" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar el muestreo #{{ $m->id }} para poder regenerarlo?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger ml-1">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                Aún no se ha generado ningún muestreo. Selecciona una regla arriba y presiona <strong>Generar Muestreo</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Detalle de Archivos del Muestreo -->
    <div class="modal fade" id="modalDetalleMuestreo" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold" id="lblTituloDetalleMuestreo">Detalle del Muestreo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive" style="max-height: 65vh;">
                        <table class="table table-sm table-striped mb-0 text-xs">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th class="px-3">#</th>
                                    <th>Nombre Capturado (Capturista)</th>
                                    <th>Nombre Muestreo (Calidad)</th>
                                    <th>Acción Muestreo</th>
                                    <th>Capturista</th>
                                    <th>Revisor Calidad</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDetalleMuestreo"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('pos-script')
<script>
    document.querySelectorAll('.selector-regla-muestreo').forEach(sel => {
        sel.addEventListener('change', function () {
            const target = document.querySelector(this.dataset.target);
            if (!target) return;
            let m = this.dataset.m96, e = this.dataset.e96;
            if (this.value === 'porcentaje_30') { m = this.dataset.m30; e = this.dataset.e30; }
            if (this.value === 'porcentaje_50') { m = this.dataset.m50; e = this.dataset.e50; }
            target.innerHTML = `<span class="badge badge-primary px-2 py-1">Muestra: ${m}</span> <span class="badge badge-danger px-2 py-1">Máx. Error: ${e}</span>`;
        });
    });

    document.querySelectorAll('.btn-ver-detalle-muestreo').forEach(btn => {
        btn.addEventListener('click', async function () {
            const tbody = document.getElementById('tbodyDetalleMuestreo');
            const titulo = document.getElementById('lblTituloDetalleMuestreo');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3">Cargando...</td></tr>';
            $('#modalDetalleMuestreo').modal('show');
            try {
                const res = await fetch(this.dataset.url);
                const data = await res.json();
                if (!data.ok) throw new Error('Error al cargar detalle');
                titulo.textContent = `Detalle Muestreo #${data.control.id} — ${data.control.fecha_laboral} (${data.control.turno})`;
                tbody.innerHTML = data.archivos.map((a, i) => {
                    const badgeAcc = a.accion_muestreo === 'validado'
                        ? '<span class="badge badge-success">VALIDADO</span>'
                        : (a.accion_muestreo === 'renombrado' ? '<span class="badge badge-danger">RENOMBRADO (ERROR)</span>' : '<span class="badge badge-secondary">PENDIENTE</span>');
                    return `<tr>
                        <td class="px-3">${i + 1}</td>
                        <td class="font-mono font-weight-bold">${a.archivo_capturado || '-'}</td>
                        <td class="font-mono ${a.es_correcto === false ? 'text-danger font-weight-bold' : ''}">${a.archivo_muestreo || '—'}</td>
                        <td>${badgeAcc}</td>
                        <td>${a.capturista ? a.capturista.name : '-'}</td>
                        <td>${a.revisor_calidad ? a.revisor_calidad.name : 'Libre'}</td>
                        <td>${a.estado}</td>
                    </tr>`;
                }).join('');
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">${e.message}</td></tr>`;
            }
        });
    });
</script>
@endsection
