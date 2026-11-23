@extends('layouts.crud')

@section('titulo', 'Catálogos y Rutas PDF Configuradas')
@section('crud_modulo', 'Infraestructura y Catálogos del Sistema')
@section('crud_titulo', 'Rutas PDF de Entregas (Stellum), Delegaciones y Localidades')
@section('crud_subtitulo', 'Visualice las rutas de red configuradas por tipo de archivo y filtre los catálogos oficiales de Delegaciones, Municipios y Localidades.')

@section('contenido')
<!-- 1. Rutas PDF Configuradas por Tipo de Archivo -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-network-wired mr-2 text-sky-700"></i>Rutas de Red Configuradas por Tipo de Archivo (config/captura_pdf.php)</span>
        <span class="badge badge-info">Extensible para nuevos tipos de PDF</span>
    </div>
    <div class="card-body">
        <div class="row">
            @foreach($rutasPorTipo as $tipo => $listaRutas)
                <div class="col-md-6 mb-3">
                    <div class="p-3 rounded-xl border bg-slate-50 h-100">
                        <h6 class="font-weight-bold text-uppercase text-sky-800 mb-2">
                            <i class="fa-solid fa-folder-open mr-1"></i> {{ strtoupper($tipo) }} ({{ count($listaRutas) }} rutas)
                        </h6>
                        <ul class="list-unstyled mb-0 space-y-2">
                            @foreach($listaRutas as $r)
                                <li class="p-2 bg-white rounded border text-xs">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="font-weight-bold text-dark">{{ $r['nombre'] }}</span>
                                        <span class="badge badge-light border">{{ $r['entrega'] }}</span>
                                    </div>
                                    <code class="text-sky-700 d-block" style="word-break: break-all;">{{ $r['ruta'] }}</code>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- 2. Catálogo de Delegaciones y Filtrado de Municipios / Localidades -->
<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-building-flag mr-2 text-sky-700"></i>Delegaciones (DelegacionesSeeder)</span>
                <span class="badge badge-primary" style="background:#034d6d;">{{ $delegaciones->count() }}</span>
            </div>
            <div class="card-body p-0" style="max-height: 450px; overflow-y: auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="bg-light text-xs uppercase">
                        <tr>
                            <th class="text-center">ID</th>
                            <th class="text-center">Clave</th>
                            <th>Nombre de Delegación</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($delegaciones as $d)
                            <tr>
                                <td class="text-center text-muted small">{{ $d->id }}</td>
                                <td class="text-center font-mono font-weight-bold text-sky-700">{{ $d->clave }}</td>
                                <td class="font-weight-bold small">{{ $d->nombre }}</td>
                                <td class="text-center">
                                    <span class="badge badge-success">Activa</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
                <span><i class="fa-solid fa-map-location-dot mr-2 text-sky-700"></i>Municipios y Localidades (LocalidadesSeeder)</span>
                <form method="GET" action="{{ route('admin.catalogos.index') }}" class="d-flex align-items-center" style="gap: 8px;">
                    <select name="municipio_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Seleccione Municipio para filtrar Localidades --</option>
                        @foreach($municipios as $m)
                            <option value="{{ $m->id }}" {{ $municipioSeleccionado == $m->id ? 'selected' : '' }}>
                                {{ $m->clave }} - {{ $m->nombre }} ({{ $m->localidades_count }} locs)
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="card-body p-0" style="max-height: 450px; overflow-y: auto;">
                @if($municipioSeleccionado)
                    <table class="table table-sm table-hover mb-0">
                        <thead class="bg-light text-xs uppercase">
                            <tr>
                                <th class="text-center">Clave Localidad</th>
                                <th>Nombre de la Localidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($localidades as $loc)
                                <tr>
                                    <td class="text-center font-mono font-weight-bold text-sky-700">{{ $loc->clave }}</td>
                                    <td class="small font-weight-bold">{{ $loc->nombre }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center py-4 text-muted">Sin localidades para este municipio.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <div class="p-4 text-center text-muted">
                        <i class="fa-solid fa-filter text-2xl mb-2 text-sky-600 d-block"></i>
                        Seleccione uno de los <strong>{{ $municipios->count() }} municipios</strong> en el selector superior para consultar sus localidades y claves oficiales.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
