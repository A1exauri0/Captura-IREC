@php
    $rutaActual = request()->route() ? request()->route()->getName() : '';
@endphp

<div class="mb-3">
    <div class="bg-white px-3 py-2 rounded-xl shadow-sm border border-slate-200 d-flex flex-wrap align-items-center justify-content-between" style="gap: 8px;">
        <div class="d-flex align-items-center text-xs font-weight-bold text-slate-500 uppercase tracking-wider">
            <i class="fa-solid fa-folder-tree text-sky-700 mr-2"></i>
            @if(($seccion ?? '') === 'graficas')
                Avance General (Gráficas KPI)
            @elseif(($seccion ?? '') === 'renombres')
                Historial y Renombres por Usuario
            @else
                Reportes de Desempeño por Usuario
            @endif
        </div>

        <div class="d-flex flex-wrap" style="gap: 6px;">
            @if(($seccion ?? '') === 'graficas')
                <a href="{{ route('admin.analytics_capturas.reportes.index') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_capturas.reportes.index') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_capturas.reportes.index') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-file-signature mr-1"></i> Registros
                </a>
                <a href="{{ route('admin.analytics_sellos.reportes.index') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_sellos.reportes.index') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_sellos.reportes.index') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-stamp mr-1"></i> Sellos
                </a>
                <a href="{{ route('admin.analytics_digitalizacion.reportes.capturas') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_digitalizacion.reportes.capturas') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_digitalizacion.reportes.capturas') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-landmark mr-1"></i> Notarías
                </a>
                <a href="{{ route('admin.analytics_catastro.reportes.index') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_catastro.reportes.index') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_catastro.reportes.index') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-map-location-dot mr-1"></i> Catastro
                </a>
            @elseif(($seccion ?? '') === 'renombres')
                <a href="{{ route('admin.analytics_sellos.reportes.detalles_renombre') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_sellos.reportes.detalles_renombre') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_sellos.reportes.detalles_renombre') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-stamp mr-1"></i> Historial Sellos
                </a>
                <a href="{{ route('admin.analytics_catastro.reportes.detalles_renombre') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_catastro.reportes.detalles_renombre') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_catastro.reportes.detalles_renombre') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-map-location-dot mr-1"></i> Historial Catastro
                </a>
            @else
                <a href="{{ route('admin.analytics_capturas.reportes.usuarios') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_capturas.reportes.usuarios') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_capturas.reportes.usuarios') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-file-signature mr-1"></i> Reporte Registros
                </a>
                <a href="{{ route('admin.analytics_sellos.reportes.usuarios') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_sellos.reportes.usuarios') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_sellos.reportes.usuarios') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-stamp mr-1"></i> Reporte Sellos
                </a>
                <a href="{{ route('admin.analytics_digitalizacion.reportes.reportes_capturas_notarias') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_digitalizacion.reportes.reportes_capturas_notarias') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_digitalizacion.reportes.reportes_capturas_notarias') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-landmark mr-1"></i> Reporte Notarías
                </a>
                <a href="{{ route('admin.analytics_catastro.reportes.usuarios') }}"
                   class="btn btn-sm {{ request()->routeIs('admin.analytics_catastro.reportes.usuarios') ? 'btn-primary' : 'btn-light border' }}"
                   style="{{ request()->routeIs('admin.analytics_catastro.reportes.usuarios') ? 'background-color:#034d6d;border-color:#034d6d;' : '' }}">
                    <i class="fa-solid fa-map-location-dot mr-1"></i> Reporte Catastro
                </a>
            @endif
        </div>
    </div>
</div>
