@extends('layouts.crud')

@section('titulo', 'Rutas PDF Configuradas')
@section('crud_modulo', 'Infraestructura Documental')
@section('crud_titulo', 'Rutas PDF de Entregas Configuradas')
@section('crud_subtitulo', 'Visualiza las rutas de red configuradas por tipo de documento en config/captura_pdf.php.')

@section('contenido')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-network-wired mr-2 text-sky-700"></i>Rutas de Red Configuradas por Tipo de Archivo</span>
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
                        <ul class="list-unstyled mb-0">
                            @foreach($listaRutas as $r)
                                <li class="p-2 mb-2 bg-white rounded border text-xs">
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
@endsection
