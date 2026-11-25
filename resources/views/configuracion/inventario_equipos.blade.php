@extends('layouts.crud')

@section('titulo', 'Equipos Entregados y Asignación de PC')
@section('crud_modulo', 'Infraestructura Sincronizada con Stellum')
@section('crud_titulo', 'Equipos Entregados (PCs y Laptops)')
@section('crud_subtitulo', 'Listado consultado en tiempo real desde Stellum únicamente con equipos marcados como entregados, listo para asignarlos a los usuarios.')

@section('crud_acciones')
    <div class="d-flex flex-wrap" style="gap: 6px;">
        <form method="POST" action="{{ route('admin.equipos.auto_asignar_pcs') }}" class="d-inline"
              onsubmit="return confirm('¿Asignar automáticamente Número de PC (1, 2, 3...) a todos los equipos de Stellum que aún no tienen uno?');">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">
                <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Auto-asignar Números de PC
            </button>
        </form>
        <a href="{{ route('admin.equipos.index') }}" class="btn btn-light border btn-sm">
            <i class="fa-solid fa-rotate mr-1"></i> Actualizar desde Stellum
        </a>
    </div>
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
        $totalAsignados  = $mapaAsignadosPorSerie->count();
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
                        <th class="px-3">Número de PC</th>
                        <th>No. Serie (Stellum)</th>
                        <th>Tipo</th>
                        <th>Marca</th>
                        <th>Ubicación</th>
                        <th>Préstamo (Stellum)</th>
                        <th>Usuario Asignado</th>
                        <th class="text-right px-3">Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($equiposEntregados as $eq)
                        @php
                            $serie = $eq['no_serie'] ?? '';
                            $tipoEq = $eq['tipo'] ?? 'PC';
                            $asignadosSerie = $mapaAsignadosPorSerie->get($serie, collect());
                            $pcLocal = $mapaPcsPorSerie->get($serie);
                        @endphp
                        <tr data-tipo="{{ strtolower($tipoEq) }}">
                            <td class="px-3" style="min-width: 135px;">
                                <form method="POST" action="{{ route('admin.equipos.asignar_numero_pc') }}" class="d-flex align-items-center" style="gap: 4px;">
                                    @csrf
                                    <input type="hidden" name="no_serie" value="{{ $serie }}">
                                    <input type="hidden" name="tipo" value="{{ $tipoEq }}">
                                    <input type="hidden" name="marca" value="{{ $eq['marca'] ?? '' }}">
                                    <input type="hidden" name="ubicacion" value="{{ $eq['ubicacion'] ?? '' }}">
                                    <input type="text" name="numero_pc" value="{{ $pcLocal->numero_pc ?? '' }}" placeholder="Ej. 1" required
                                           class="form-control form-control-sm font-mono font-weight-bold text-center" style="width: 68px;">
                                    <button type="submit" class="btn btn-xs {{ $pcLocal ? 'btn-outline-success' : 'btn-primary' }} py-1 px-2" title="Guardar Número de PC en BD">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="font-mono font-weight-bold text-sky-800">{{ $serie }}</td>
                            <td>
                                <span class="badge {{ strtolower($tipoEq) === 'laptop' ? 'badge-info' : 'badge-secondary' }}">
                                    {{ strtoupper($tipoEq) }}
                                </span>
                            </td>
                            <td class="font-weight-bold text-slate-700">{{ $eq['marca'] ?? '-' }}</td>
                            <td class="text-xs">{{ $eq['ubicacion'] ?? '-' }}</td>
                            <td class="text-xs">{{ !empty($eq['prestado_a']) ? $eq['prestado_a'] : '—' }}</td>
                            <td>
                                @if($asignadosSerie->isNotEmpty())
                                    <div class="d-flex flex-wrap" style="gap: 4px;">
                                        @foreach($asignadosSerie as $asig)
                                            @if($asig->usuario)
                                                <span class="badge badge-success px-2 py-1">
                                                    <i class="fa-solid fa-user mr-1"></i>{{ $asig->usuario->name }}
                                                    <span class="opacity-75">({{ $asig->usuario->turnoRelacion->nombre ?? $asig->usuario->turno ?? 'Turno' }})</span>
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="badge badge-light border text-muted">Disponible</span>
                                @endif
                            </td>
                            <td class="text-right px-3">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        onclick="abrirModalAsignar({{ json_encode($eq) }})">
                                    <i class="fa-solid fa-user-plus mr-1"></i> Asignar
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
                        @php $equiposUsr = $mapaEquiposPorUsuario->get($u->id, collect()); @endphp
                        <tr>
                            <td class="px-3 font-weight-bold text-slate-800">{{ $u->name }} <span class="text-xs text-muted">({{ $u->user }})</span></td>
                            <td class="text-xs">{{ $u->turnoRelacion->nombre ?? $u->turno ?? 'Matutino' }}</td>
                            <td colspan="2">
                                @if($equiposUsr->isNotEmpty())
                                    <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                                        @foreach($equiposUsr as $eqU)
                                            @php $numPcU = $eqU->numero_pc ?: ($mapaPcsPorSerie->get($eqU->no_serie)?->numero_pc); @endphp
                                            <span class="badge badge-light border px-2 py-1 d-inline-flex align-items-center" style="gap: 6px;">
                                                @if($numPcU)
                                                    <span class="badge badge-success">#{{ $numPcU }}</span>
                                                @endif
                                                <span class="font-mono text-sky-800 font-weight-bold">{{ $eqU->no_serie }}</span>
                                                @if($eqU->marca)
                                                    <span class="text-muted">({{ $eqU->marca }})</span>
                                                @endif
                                                <form method="POST" action="{{ route('admin.equipos.desasignar_usuario', [$u->id, $eqU->id]) }}" class="d-inline m-0" onsubmit="return confirm('¿Quitar equipo {{ $eqU->no_serie }} de {{ $u->name }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link text-danger p-0 m-0" style="line-height:1;" title="Quitar este equipo">&times;</button>
                                                </form>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-muted">Sin equipos asignados</span>
                                @endif
                            </td>
                            <td class="text-right px-3 text-xs text-muted">
                                {{ $equiposUsr->count() }} equipo(s)
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
                        <label class="text-xs font-weight-bold">Seleccionar Usuario(s) — puedes marcar varios para rotación por turno</label>
                        <input type="text" id="buscarUsuarioModalEquipo" class="form-control form-control-sm mb-2" placeholder="Buscar usuario por nombre, login o turno...">
                        <div id="listaUsuariosModalEquipo" class="p-2 bg-slate-50 rounded border" style="max-height: 220px; overflow-y: auto;">
                            @foreach($usuarios as $u)
                                <label class="item-usuario-modal d-flex align-items-center justify-content-between mb-1.5 text-sm px-1" style="gap: 8px; cursor: pointer;">
                                    <span class="d-flex align-items-center" style="gap: 8px;">
                                        <input type="checkbox" name="user_ids[]" value="{{ $u->id }}">
                                        <span><strong>{{ $u->name }}</strong> <span class="text-muted text-xs">({{ $u->user }})</span></span>
                                    </span>
                                    <span class="badge badge-light border text-xs">{{ $u->turnoRelacion->nombre ?? $u->turno ?? 'Sin turno' }}</span>
                                </label>
                            @endforeach
                        </div>
                        <small class="text-muted">Permite asignar más de un usuario al mismo equipo (por turno) y más de un equipo a un mismo usuario.</small>
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
        const inputBuscar = document.getElementById('buscarUsuarioModalEquipo');
        if (inputBuscar) {
            inputBuscar.value = '';
            document.querySelectorAll('#listaUsuariosModalEquipo .item-usuario-modal').forEach(el => {
                el.classList.remove('d-none');
                el.classList.add('d-flex');
                const cb = el.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = false;
            });
        }
        $('#modalAsignarEquipo').modal('show');
        setTimeout(() => inputBuscar?.focus(), 200);
    }

    document.getElementById('buscarUsuarioModalEquipo')?.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#listaUsuariosModalEquipo .item-usuario-modal').forEach(el => {
            const coincide = el.innerText.toLowerCase().includes(q);
            el.classList.toggle('d-none', !coincide);
            el.classList.toggle('d-flex', coincide);
        });
    });

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
