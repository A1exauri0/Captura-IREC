@extends('layouts.app')

@section('header-layout')
<div class="mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-sky-700 mb-1">
                <i class="fa-solid fa-layer-group mr-1"></i> @yield('crud_modulo', 'Administración y Catálogos')
            </div>
            <h1 class="h4 font-weight-bold text-slate-900 m-0">@yield('crud_titulo', 'Gestión de Registros')</h1>
            <p class="text-muted small m-0 mt-1">@yield('crud_subtitulo', 'Administre las opciones, horarios y configuraciones del sistema.')</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex align-items-center" style="gap: 10px;">
            @yield('crud_acciones')
        </div>
    </div>
</div>
@endsection
