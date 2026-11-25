@extends('layouts.crud')

@section('titulo', 'Municipios y Localidades')
@section('crud_modulo', 'Catálogos Oficiales')
@section('crud_titulo', 'CRUD de Municipios y Localidades')
@section('crud_subtitulo', 'Administra el catálogo de municipios y gestiona las localidades pertenecientes a cada uno.')

@section('crud_acciones')
    <button type="button" class="btn btn-primary btn-sm px-3" style="background-color:#034d6d;border-color:#034d6d;" data-toggle="modal" data-target="#modalCrearMunicipio">
        <i class="fa-solid fa-plus mr-1"></i> Nuevo Municipio
    </button>
    @if($municipioActual)
        <button type="button" class="btn btn-success btn-sm px-3" data-toggle="modal" data-target="#modalCrearLocalidad">
            <i class="fa-solid fa-location-dot mr-1"></i> Nueva Localidad
        </button>
    @endif
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

    <div class="row">
        <!-- Panel Izquierdo: CRUD Municipios -->
        <div class="col-lg-5 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="gap: 8px;">
                    <span><i class="fa-solid fa-city text-sky-700 mr-1.5"></i> Municipios ({{ $municipios->count() }})</span>
                    <input type="text" id="filtroMunicipios" class="form-control form-control-sm" style="max-width: 170px;" placeholder="Filtrar...">
                </div>
                <div class="table-responsive" style="max-height: 540px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0 text-sm" id="tablaMunicipios">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                            <tr>
                                <th class="px-2">Clave</th>
                                <th>Municipio</th>
                                <th class="text-center">Locs</th>
                                <th class="text-right px-2">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($municipios as $m)
                                <tr class="{{ (int)$municipioSeleccionado === (int)$m->id ? 'table-info' : '' }}">
                                    <td class="px-2 font-mono font-weight-bold text-sky-700">{{ $m->clave }}</td>
                                    <td>
                                        <a href="{{ route('admin.municipios.index', ['municipio_id' => $m->id]) }}" class="font-weight-bold text-slate-800 text-decoration-none d-block">
                                            {{ $m->nombre }}
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light border">{{ $m->localidades_count }}</span>
                                    </td>
                                    <td class="text-right px-2 text-nowrap">
                                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1.5" data-toggle="modal" data-target="#modalEditarMunicipio{{ $m->id }}" title="Editar">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                        <form action="{{ route('admin.municipios.destroy', $m->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar municipio {{ $m->nombre }} y todas sus localidades?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1.5" title="Eliminar"><i class="fa-solid fa-trash text-xs"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: CRUD Localidades del Municipio Seleccionado -->
        <div class="col-lg-7 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
                    <span>
                        <i class="fa-solid fa-map-location-dot text-sky-700 mr-1.5"></i>
                        @if($municipioActual)
                            Localidades de <strong>{{ $municipioActual->clave }} - {{ $municipioActual->nombre }}</strong>
                        @else
                            Localidades por Municipio
                        @endif
                    </span>
                    @if($municipioActual)
                        <form method="GET" action="{{ route('admin.municipios.index') }}" class="d-flex align-items-center" style="gap: 6px;">
                            <input type="hidden" name="municipio_id" value="{{ $municipioActual->id }}">
                            <input type="text" name="q" value="{{ $busquedaLoc }}" class="form-control form-control-sm" style="width: 190px;" placeholder="Buscar localidad...">
                            <button type="submit" class="btn btn-sm btn-light border"><i class="fa-solid fa-search"></i></button>
                        </form>
                    @endif
                </div>

                @if($municipioActual)
                    <div class="table-responsive" style="max-height: 490px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0 text-sm">
                            <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                                <tr>
                                    <th class="px-3">Clave</th>
                                    <th>Nombre de la Localidad</th>
                                    <th class="text-right px-3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($localidades as $loc)
                                    <tr>
                                        <td class="px-3 font-mono font-weight-bold text-sky-700">{{ $loc->clave }}</td>
                                        <td class="font-weight-bold text-slate-800">{{ $loc->nombre }}</td>
                                        <td class="text-right px-3 text-nowrap">
                                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1.5" data-toggle="modal" data-target="#modalEditarLoc{{ $loc->id }}">
                                                <i class="fa-solid fa-pen text-xs"></i>
                                            </button>
                                            <form action="{{ route('admin.localidades.destroy', $loc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar localidad {{ $loc->nombre }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1.5"><i class="fa-solid fa-trash text-xs"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">Sin localidades registradas para este criterio.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(method_exists($localidades, 'links') && $localidades->hasPages())
                        <div class="card-footer bg-white py-2">
                            {{ $localidades->links('pagination::bootstrap-4') }}
                        </div>
                    @endif
                @else
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center text-muted py-5">
                        <i class="fa-solid fa-hand-pointer text-3xl text-sky-600 mb-3"></i>
                        <p class="mb-0 text-sm">Haz clic en un <strong>municipio</strong> de la lista izquierda para consultar, agregar, editar o eliminar sus localidades.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Modales Editar Municipio (fuera de <table>) -->
    @foreach($municipios as $m)
        <div class="modal fade" id="modalEditarMunicipio{{ $m->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('admin.municipios.update', $m->id) }}" method="POST" class="modal-content bg-white border-0 shadow-lg">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-slate-900 text-white">
                        <h5 class="modal-title text-base font-weight-bold">Editar Municipio</h5>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body bg-white">
                        <div class="form-group">
                            <label class="text-xs font-weight-bold">Clave</label>
                            <input type="text" name="clave" class="form-control" value="{{ $m->clave }}" required>
                        </div>
                        <div class="form-group">
                            <label class="text-xs font-weight-bold">Nombre</label>
                            <input type="text" name="nombre" class="form-control" value="{{ $m->nombre }}" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="text-xs font-weight-bold">Clave Estado</label>
                            <input type="text" name="estado" class="form-control" value="{{ $m->estado }}">
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

    @if($municipioActual)
        <!-- Modales Editar Localidad (fuera de <table>) -->
        @foreach($localidades as $loc)
            <div class="modal fade" id="modalEditarLoc{{ $loc->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form action="{{ route('admin.localidades.update', $loc->id) }}" method="POST" class="modal-content bg-white border-0 shadow-lg">
                        @csrf
                        @method('PUT')
                        <div class="modal-header bg-slate-900 text-white">
                            <h5 class="modal-title text-base font-weight-bold">Editar Localidad</h5>
                            <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body bg-white">
                            <div class="form-group">
                                <label class="text-xs font-weight-bold">Clave</label>
                                <input type="text" name="clave" class="form-control" value="{{ $loc->clave }}" required>
                            </div>
                            <div class="form-group mb-0">
                                <label class="text-xs font-weight-bold">Nombre</label>
                                <input type="text" name="nombre" class="form-control" value="{{ $loc->nombre }}" required>
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
    @endif

    <!-- Modal Crear Municipio -->
    <div class="modal fade" id="modalCrearMunicipio" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form action="{{ route('admin.municipios.store') }}" method="POST" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-slate-900 text-white">
                    <h5 class="modal-title text-base font-weight-bold"><i class="fa-solid fa-city mr-2 text-sky-400"></i>Nuevo Municipio</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Clave</label>
                        <input type="text" name="clave" class="form-control" placeholder="Ej. 101" required>
                    </div>
                    <div class="form-group">
                        <label class="text-xs font-weight-bold">Nombre</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej. Tuxtla Gutiérrez" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="text-xs font-weight-bold">Clave Estado</label>
                        <input type="text" name="estado" class="form-control" value="07">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">Crear Municipio</button>
                </div>
            </form>
        </div>
    </div>

    @if($municipioActual)
        <!-- Modal Crear Localidad -->
        <div class="modal fade" id="modalCrearLocalidad" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form action="{{ route('admin.localidades.store') }}" method="POST" class="modal-content border-0 shadow-lg">
                    @csrf
                    <input type="hidden" name="municipio" value="{{ $municipioActual->id }}">
                    <div class="modal-header bg-slate-900 text-white">
                        <h5 class="modal-title text-base font-weight-bold"><i class="fa-solid fa-location-dot mr-2 text-emerald-400"></i>Nueva Localidad en {{ $municipioActual->nombre }}</h5>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="text-xs font-weight-bold">Clave de Localidad</label>
                            <input type="text" name="clave" class="form-control" placeholder="Ej. 0001" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="text-xs font-weight-bold">Nombre de Localidad</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-sm">Guardar Localidad</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('pos-script')
<script>
    document.getElementById('filtroMunicipios')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#tablaMunicipios tbody tr').forEach(tr => {
            tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });
</script>
@endsection
