@extends('layouts.crud')

@section('titulo', 'Equipos Entregados y Asignación de PC')
@section('crud_modulo', 'Infraestructura Sincronizada con Stellum')
@section('crud_titulo', 'Equipos Entregados (PCs y Laptops)')
@section('crud_subtitulo', 'Listado consultado en tiempo real desde Stellum únicamente con equipos marcados como entregados, listo para asignarlos a los usuarios.')

@section('crud_acciones')
    <a href="{{ route('admin.equipos.index') }}" class="btn btn-light border btn-sm">
        <i class="fa-solid fa-rotate mr-1"></i> Actualizar desde Stellum
    </a>
@endsection

@section('contenido')
    @if(!$conexionStellumOk)
        <div class="alert alert-warning rounded-lg shadow-sm mb-3 text-sm">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            No se pudo consultar la lista en vivo desde Stellum ({{ $errorStellum ?: 'verifica STELLUM_BRIDGE_URL y STELLUM_BRIDGE_TOKEN' }}).
        </div>
    @endif

    @php
        $totalEntregados = $equiposEntregados->count();
        $totalAsignados  = $mapaAsignados->count();
        $totalDisponibles = max(0, $totalEntregados - $totalAsignados);
    @endphp

    <!-- KPIs rápidos -->
    <div class="row mb-3">
        <div class="col-md-4 mb-2">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center" style="gap: 12px;">
                <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 d-flex align-items-center justify-content-center">
                    <i class="fa-solid fa-laptop text-lg"></i>
                </div>
                <div>
                    <div class="h5 font-weight-bold m-0 text-slate-900">{{ $totalEntregados }}</div>
                    <div class="text-xs text-muted">Entregados en Stellum</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center" style="gap: 12px;">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 d-flex align-items-center justify-content-center">
                    <i class="fa-solid fa-user-check text-lg"></i>
                </div>
                <div>
                    <div class="h5 font-weight-bold m-0 text-slate-900">{{ $totalAsignados }}</div>
                    <div class="text-xs text-muted">Asignados a Usuarios</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center" style="gap: 12px;">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 d-flex align-items-center justify-content-center">
                    <i class="fa-solid fa-box-open text-lg"></i>
                </div>
                <div>
                    <div class="h5 font-weight-bold m-0 text-slate-900">{{ $totalDisponibles }}</div>
                    <div class="text-xs text-muted">Disponibles para Asignar</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 1. Listado de Equipos Entregados desde Stellum -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
            <span><i class="fa-solid fa-desktop text-sky-700 mr-1.5"></i> Equipos Marcados como Entregados en Stellum ({{ $totalEntregados }})</span>
            <div class="d-flex align-items-center" style="gap: 8px;">
                <select id="filtroTipoEquipo" class="form-control form-control-sm" style="width: 130px;">
                    <option value="">Todos</option>
                    <option value="pc">PC</option>
                    <option value="laptop">Laptop</option>
                </select>
                <input type="text" id="filtroEquipos" class="form-control form-control-sm" style="width: 240px;" placeholder="Buscar serie, marca, ubicación...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-sm" id="tablaEquiposEntregados">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">No. Serie</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Ubicación</th>
                        <th>Hora Entrega</th>
                        <th>Préstamo (Stellum)</th>
                        <th>Usuario Asignado (Captura-IREC)</th>
                        <th class="text-right px-3">Asignar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($equiposEntregados as $eq)
                        @php
                            $serie = $eq['no_serie'] ?? '';
                            $tipoEq = $eq['tipo'] ?? 'PC';
                            $usuarioAsig = $mapaAsignados->get($serie);
                        @endphp
                        <tr data-tipo="{{ strtolower($tipoEq) }}">
                            <td class="px-3 font-mono font-weight-bold text-sky-800">{{ $serie }}</td>
                            <td>
                                <span class="badge {{ strtolower($tipoEq) === 'laptop' ? 'badge-info' : 'badge-secondary' }}">
                                    {{ strtoupper($tipoEq) }}
                                </span>
                            </td>
                            <td class="font-weight-bold text-slate-700">{{ $eq['marca'] ?? '-' }}</td>
                            <td class="text-xs">{{ $eq['ubicacion'] ?? '-' }}</td>
                            <td class="text-xs text-muted">{{ $eq['hora_entrega'] ?? '-' }}</td>
                            <td class="text-xs">{{ !empty($eq['prestado_a']) ? $eq['prestado_a'] : '—' }}</td>
                            <td>
                                @if($usuarioAsig)
                                    <span class="badge badge-success px-2 py-1">
                                        <i class="fa-solid fa-user mr-1"></i> {{ $usuarioAsig->name }}
                                    </span>
                                @else
                                    <span class="badge badge-light border text-muted">Disponible</span>
                                @endif
                            </td>
                            <td class="text-right px-3">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        onclick="abrirModalAsignar({{ json_encode($eq) }})">
                                    <i class="fa-solid fa-link mr-1"></i> {{ $usuarioAsig ? 'Reasignar' : 'Asignar' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                No se encontraron equipos marcados como entregados en Stellum.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. Resumen de Usuarios y su PC Asignada -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <i class="fa-solid fa-users text-sky-700 mr-1.5"></i> Usuarios y PC Asignada
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                    <tr>
                        <th class="px-3">Usuario</th>
                        <th>Turno</th>
                        <th>PC / Laptop Asignada</th>
                        <th>Marca / Ubicación</th>
                        <th class="text-right px-3">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $u)
                        <tr>
                            <td class="px-3 font-weight-bold text-slate-800">{{ $u->name }} <span class="text-xs text-muted">({{ $u->user }})</span></td>
                            <td class="text-xs">{{ $u->turnoRelacion->nombre ?? $u->turno ?? 'Matutino' }}</td>
                            <td>
                                @if($u->equipo_no_serie)
                                    <span class="badge badge-primary" style="background-color:#034d6d;">
                                        {{ strtoupper($u->equipo_tipo ?: 'PC') }} · {{ $u->equipo_no_serie }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted">Sin equipo asignado</span>
                                @endif
                            </td>
                            <td class="text-xs text-muted">
                                {{ $u->equipo_no_serie ? trim(($u->equipo_marca ?: '') . ' ' . ($u->equipo_ubicacion ? '· ' . $u->equipo_ubicacion : '')) : '—' }}
                            </td>
                            <td class="text-right px-3">
                                @if($u->equipo_no_serie)
                                    <form method="POST" action="{{ route('admin.equipos.desasignar_usuario', $u->id) }}" class="d-inline" onsubmit="return confirm('¿Liberar equipo {{ $u->equipo_no_serie }} de {{ $u->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2">Liberar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Asignar Equipo Entregado a Usuario -->
    <div class="modal fade" id="modalAsignarEquipo" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('admin.equipos.asignar_usuario') }}" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-slate-900 text-white">
                    <h5 class="modal-title text-base font-weight-bold"><i class="fa-solid fa-link mr-2 text-sky-400"></i>Asignar Equipo a Usuario</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="p-2.5 bg-slate-50 rounded border mb-3 text-sm">
                        <div><strong>No. Serie:</strong> <span id="lblModalSerie" class="font-mono text-sky-800 font-weight-bold"></span></div>
                        <div class="text-xs text-muted mt-0.5" id="lblModalDetalle"></div>
                    </div>

                    <input type="hidden" name="equipo_no_serie" id="inputEquipoSerieModal">
                    <input type="hidden" name="equipo_tipo" id="inputEquipoTipoModal">
                    <input type="hidden" name="equipo_tabla" id="inputEquipoTablaModal">
                    <input type="hidden" name="equipo_id_stellum" id="inputEquipoIdStellumModal">
                    <input type="hidden" name="equipo_marca" id="inputEquipoMarcaModal">
                    <input type="hidden" name="equipo_ubicacion" id="inputEquipoUbicacionModal">

                    <div class="form-group mb-0">
                        <label class="text-xs font-weight-bold">Seleccionar Usuario</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">-- Seleccionar usuario --</option>
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}">
                                    {{ $u->name }} ({{ $u->user }}){{ $u->equipo_no_serie ? ' [Actual: ' . $u->equipo_no_serie . ']' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Al guardar, la asignación se refleja en Captura-IREC y actualiza el préstamo en Stellum.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">Guardar Asignación</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('pos-script')
<script>
    function abrirModalAsignar(eq) {
        document.getElementById('lblModalSerie').textContent = eq.no_serie || '';
        document.getElementById('lblModalDetalle').textContent = `${(eq.tipo || 'PC').toUpperCase()} · ${eq.marca || ''} · ${eq.ubicacion || ''}`;
        document.getElementById('inputEquipoSerieModal').value = eq.no_serie || '';
        document.getElementById('inputEquipoTipoModal').value = eq.tipo || 'PC';
        document.getElementById('inputEquipoTablaModal').value = eq.tabla_origen || 'computadoras';
        document.getElementById('inputEquipoIdStellumModal').value = eq.id || '';
        document.getElementById('inputEquipoMarcaModal').value = eq.marca || '';
        document.getElementById('inputEquipoUbicacionModal').value = eq.ubicacion || '';
        $('#modalAsignarEquipo').modal('show');
    }

    function aplicarFiltrosTabla() {
        const q = (document.getElementById('filtroEquipos')?.value || '').toLowerCase();
        const tipo = (document.getElementById('filtroTipoEquipo')?.value || '').toLowerCase();
        document.querySelectorAll('#tablaEquiposEntregados tbody tr[data-tipo]').forEach(tr => {
            const matchTexto = tr.innerText.toLowerCase().includes(q);
            const matchTipo = !tipo || tr.dataset.tipo === tipo;
            tr.style.display = (matchTexto && matchTipo) ? '' : 'none';
        });
    }

    document.getElementById('filtroEquipos')?.addEventListener('input', aplicarFiltrosTabla);
    document.getElementById('filtroTipoEquipo')?.addEventListener('change', aplicarFiltrosTabla);
</script>
@endsection
