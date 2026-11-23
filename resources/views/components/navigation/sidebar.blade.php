<aside id="app-sidebar">
    <!-- Brand Header -->
    <div class="px-5 py-4 border-b border-slate-700/70 d-flex align-items-center justify-content-between">
        <a href="{{ url('/') }}" class="d-flex align-items-center text-white text-decoration-none" style="gap: 12px;">
            <div class="w-10 h-10 rounded-xl bg-sky-600 d-flex align-items-center justify-content-center shadow-md">
                <i class="fa-solid fa-file-shield text-white text-lg"></i>
            </div>
            <div>
                <span class="font-weight-bold d-block text-base tracking-tight text-white" style="line-height: 1.1;">CAPTURA IREC</span>
                <span class="text-xs text-sky-400 font-medium">Gestión & Auditoría PDF</span>
            </div>
        </a>
    </div>

    <!-- Navigation Links -->
    <div class="flex-1 overflow-y-auto px-3 py-4" style="scrollbar-width: thin;">

        <!-- 1. ESTACIÓN DE CAPTURA -->
        <div class="px-3 mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            Operación Capturistas
        </div>
        <ul class="list-unstyled mb-4">
            <li class="mb-1">
                <a href="{{ route('admin.captura.index') }}"
                   class="d-flex align-items-center px-3 py-2.5 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.captura.*') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-desktop w-6 text-center mr-2 text-sky-400"></i>
                    <span>Estación de Captura</span>
                    <span class="ml-auto badge badge-info" style="font-size: 0.65rem;">Full</span>
                </a>
            </li>
        </ul>

        <!-- 2. MONITOREO Y AUTORIZACIONES EN VIVO -->
        <div class="px-3 mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            Tiempo Real & Búsqueda
        </div>
        <ul class="list-unstyled mb-4">
            <li class="mb-1">
                <a href="{{ route('admin.analytics_sellos.reportes.lotes_trabajando') }}"
                   class="d-flex align-items-center px-3 py-2.5 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.analytics_sellos.reportes.lotes_trabajando') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-satellite-dish w-6 text-center mr-2 text-emerald-400"></i>
                    <span>Sesiones / Lotes Activos</span>
                    <span class="ml-auto d-inline-flex align-items-center px-1.5 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-300" style="font-size: 0.65rem;">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1"></span>LIVE
                    </span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.autorizaciones.index') }}"
                   class="d-flex align-items-center px-3 py-2.5 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.autorizaciones.*') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-shield-halved w-6 text-center mr-2 text-amber-400"></i>
                    <span>Autorizaciones en Vivo</span>
                    <span class="ml-auto d-inline-flex align-items-center px-1.5 py-0.5 rounded text-xs bg-amber-500/20 text-amber-300" style="font-size: 0.65rem;">
                        WS
                    </span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.analytics_sellos.reportes.buscar_archivos') }}"
                   class="d-flex align-items-center px-3 py-2.5 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.analytics_sellos.reportes.buscar_archivos') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-magnifying-glass-chart w-6 text-center mr-2 text-sky-400"></i>
                    <span>Explorador Global PDF</span>
                </a>
            </li>
        </ul>

        <!-- 3. INDICADORES (AVANCE GENERAL) -->
        <div class="px-3 mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            Indicadores de Avance
        </div>
        <ul class="list-unstyled mb-4">
            <li class="mb-1">
                <a href="{{ route('admin.analytics_capturas.reportes.index') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_capturas.reportes.index') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-chart-pie w-6 text-center mr-2 text-indigo-400"></i>
                    <span>Avance Registros</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.analytics_sellos.reportes.index') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_sellos.reportes.index') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-chart-column w-6 text-center mr-2 text-emerald-400"></i>
                    <span>Avance Sellos</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.analytics_digitalizacion.reportes.capturas') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_digitalizacion.reportes.capturas') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-chart-line w-6 text-center mr-2 text-sky-400"></i>
                    <span>Avance Notarías</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.analytics_catastro.reportes.index') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_catastro.reportes.index') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-chart-area w-6 text-center mr-2 text-amber-400"></i>
                    <span>Avance Catastro</span>
                </a>
            </li>
        </ul>

        <!-- 4. PRODUCTIVIDAD POR USUARIO & BITÁCORA -->
        <div class="px-3 mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            Productividad & Bitácora
        </div>
        <ul class="list-unstyled mb-4">
            <li class="mb-1">
                <a href="{{ route('admin.analytics_capturas.reportes.usuarios') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_capturas.reportes.usuarios') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-users-rectangle w-6 text-center mr-2 text-indigo-300"></i>
                    <span>Usuarios Registros</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.analytics_sellos.reportes.usuarios') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_sellos.reportes.usuarios') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-user-check w-6 text-center mr-2 text-emerald-300"></i>
                    <span>Usuarios Sellos</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.analytics_digitalizacion.reportes.reportes_capturas_notarias') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_digitalizacion.reportes.reportes_capturas_notarias') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-building-columns w-6 text-center mr-2 text-sky-300"></i>
                    <span>Usuarios Notarías</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.analytics_catastro.reportes.usuarios') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_catastro.reportes.usuarios') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-map-location-dot w-6 text-center mr-2 text-amber-300"></i>
                    <span>Usuarios Catastro</span>
                </a>
            </li>
            <li class="mb-1 mt-2 pt-2 border-t border-slate-700/60">
                <a href="{{ route('admin.analytics_sellos.reportes.detalles_renombre') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_sellos.reportes.detalles_renombre') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-clock-rotate-left w-6 text-center mr-2 text-teal-400"></i>
                    <span>Bitácora Sellos</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.analytics_catastro.reportes.detalles_renombre') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.analytics_catastro.reportes.detalles_renombre') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-file-pen w-6 text-center mr-2 text-purple-400"></i>
                    <span>Bitácora Catastro</span>
                </a>
            </li>
        </ul>

        <!-- 5. CONFIGURACIÓN, TURNOS Y CATÁLOGOS -->
        <div class="px-3 mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            Configuración & CRUDs
        </div>
        <ul class="list-unstyled mb-2">
            <li class="mb-1">
                <a href="{{ route('admin.turnos.index') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.turnos.*') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-user-clock w-6 text-center mr-2 text-amber-400"></i>
                    <span>Turnos y Horarios</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('admin.catalogos.index') }}"
                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm text-decoration-none {{ request()->routeIs('admin.catalogos.*') ? 'bg-sky-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fa-solid fa-map-pin w-6 text-center mr-2 text-rose-400"></i>
                    <span>Catálogos y Rutas PDF</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
