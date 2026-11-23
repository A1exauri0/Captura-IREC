<aside id="app-sidebar">
    <!-- Brand Header -->
    <div class="px-4 py-3.5 border-b border-slate-800/80 d-flex align-items-center justify-content-between flex-shrink-0">
        <a href="{{ url('/') }}" class="d-flex align-items-center text-white text-decoration-none" style="gap: 11px;">
            <div class="w-9 h-9 rounded-xl bg-sky-600 d-flex align-items-center justify-content-center shadow-sm">
                <i class="fa-solid fa-file-shield text-white text-base"></i>
            </div>
            <div>
                <span class="font-weight-bold d-block text-sm tracking-tight text-white" style="line-height: 1.15;">CAPTURA IREC</span>
                <span class="text-slate-400 font-medium" style="font-size: 0.7rem;">Auditoría Documental</span>
            </div>
        </a>
    </div>

    <!-- Navigation Links con Scroll Personalizado (sin barra nativa del navegador) -->
    <div class="sidebar-scroll-wrapper flex-1 min-h-0">
        <nav id="sidebar-scroll-area" class="custom-scroll h-full overflow-y-auto px-3 py-4">

            <!-- 1. OPERACIÓN -->
            <div class="px-2.5 mb-1.5 text-slate-500 font-weight-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.08em;">
                Operación
            </div>
            <ul class="list-unstyled mb-4">
                <li class="mb-1">
                    <a href="{{ route('admin.captura.index') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.captura.*') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-desktop w-5 text-center mr-2.5 {{ request()->routeIs('admin.captura.*') ? 'text-white' : 'text-sky-400' }}"></i>
                        <span>Estación de Captura</span>
                    </a>
                </li>
            </ul>

            <!-- 2. TIEMPO REAL -->
            <div class="px-2.5 mb-1.5 text-slate-500 font-weight-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.08em;">
                Tiempo Real
            </div>
            <ul class="list-unstyled mb-4">
                <li class="mb-1">
                    <a href="{{ route('admin.analytics_sellos.reportes.lotes_trabajando') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.analytics_sellos.reportes.lotes_trabajando') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-satellite-dish w-5 text-center mr-2.5 {{ request()->routeIs('admin.analytics_sellos.reportes.lotes_trabajando') ? 'text-white' : 'text-emerald-400' }}"></i>
                        <span>Sesiones Activas</span>
                        <span class="ml-auto d-inline-flex align-items-center px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300" style="font-size: 0.6rem;">LIVE</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="{{ route('admin.autorizaciones.index') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.autorizaciones.*') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-shield-halved w-5 text-center mr-2.5 {{ request()->routeIs('admin.autorizaciones.*') ? 'text-white' : 'text-amber-400' }}"></i>
                        <span>Autorizaciones</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="{{ route('admin.analytics_sellos.reportes.buscar_archivos') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.analytics_sellos.reportes.buscar_archivos') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-magnifying-glass w-5 text-center mr-2.5 {{ request()->routeIs('admin.analytics_sellos.reportes.buscar_archivos') ? 'text-white' : 'text-sky-400' }}"></i>
                        <span>Explorador PDF</span>
                    </a>
                </li>
            </ul>

            <!-- 3. ANALÍTICAS (Unificado con pestañas superiores por tipo de documento) -->
            @php
                $enIndicadores = request()->routeIs(
                    'admin.analytics_capturas.reportes.index',
                    'admin.analytics_sellos.reportes.index',
                    'admin.analytics_digitalizacion.reportes.capturas',
                    'admin.analytics_catastro.reportes.index'
                );
                $enProductividad = request()->routeIs(
                    'admin.analytics_capturas.reportes.usuarios',
                    'admin.analytics_sellos.reportes.usuarios',
                    'admin.analytics_digitalizacion.reportes.reportes_capturas_notarias',
                    'admin.analytics_catastro.reportes.usuarios'
                );
                $enBitacoras = request()->routeIs(
                    'admin.analytics_capturas.reportes.detalles_renombre',
                    'admin.analytics_sellos.reportes.detalles_renombre',
                    'admin.analytics_digitalizacion.reportes.detalles_renombre',
                    'admin.analytics_catastro.reportes.detalles_renombre'
                );
            @endphp
            <div class="px-2.5 mb-1.5 text-slate-500 font-weight-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.08em;">
                Analíticas & Bitácora
            </div>
            <ul class="list-unstyled mb-4">
                <li class="mb-1">
                    <a href="{{ route('admin.analytics_capturas.reportes.index') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ $enIndicadores ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-chart-pie w-5 text-center mr-2.5 {{ $enIndicadores ? 'text-white' : 'text-indigo-400' }}"></i>
                        <span>Indicadores KPI</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="{{ route('admin.analytics_capturas.reportes.usuarios') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ $enProductividad ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-user-chart w-5 text-center mr-2.5 {{ $enProductividad ? 'text-white' : 'text-emerald-400' }}"></i>
                        <span>Productividad</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="{{ route('admin.analytics_capturas.reportes.detalles_renombre') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ $enBitacoras ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-clock-rotate-left w-5 text-center mr-2.5 {{ $enBitacoras ? 'text-white' : 'text-teal-400' }}"></i>
                        <span>Bitácoras</span>
                    </a>
                </li>
            </ul>

            <!-- 4. ADMINISTRACIÓN -->
            <div class="px-2.5 mb-1.5 text-slate-500 font-weight-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.08em;">
                Administración
            </div>
            <ul class="list-unstyled mb-2">
                <li class="mb-1">
                    <a href="{{ route('admin.acceso.index') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.acceso.*') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-users-gear w-5 text-center mr-2.5 {{ request()->routeIs('admin.acceso.*') ? 'text-white' : 'text-sky-400' }}"></i>
                        <span>Usuarios y Roles</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="{{ route('admin.turnos.index') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.turnos.*') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-user-clock w-5 text-center mr-2.5 {{ request()->routeIs('admin.turnos.*') ? 'text-white' : 'text-amber-400' }}"></i>
                        <span>Turnos</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="{{ route('admin.catalogos.index') }}"
                       class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ request()->routeIs('admin.catalogos.*') ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                        <i class="fa-solid fa-folder-tree w-5 text-center mr-2.5 {{ request()->routeIs('admin.catalogos.*') ? 'text-white' : 'text-rose-400' }}"></i>
                        <span>Catálogos y Rutas</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Barra de scroll personalizada tipo overlay -->
        <div id="sidebar-scroll-thumb" class="sidebar-scroll-thumb"></div>
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const area = document.getElementById('sidebar-scroll-area');
        const thumb = document.getElementById('sidebar-scroll-thumb');
        if (!area || !thumb) return;

        let hideTimer = null;
        function updateThumb() {
            const scrollHeight = area.scrollHeight;
            const clientHeight = area.clientHeight;
            if (scrollHeight <= clientHeight + 2) {
                thumb.style.opacity = '0';
                return;
            }
            const ratio = clientHeight / scrollHeight;
            const thumbHeight = Math.max(28, clientHeight * ratio);
            const maxTop = clientHeight - thumbHeight;
            const scrollRatio = area.scrollTop / (scrollHeight - clientHeight);
            thumb.style.height = thumbHeight + 'px';
            thumb.style.transform = 'translateY(' + (scrollRatio * maxTop) + 'px)';
        }

        function showThumb() {
            updateThumb();
            if (area.scrollHeight > area.clientHeight + 2) {
                thumb.classList.add('is-visible');
                clearTimeout(hideTimer);
                hideTimer = setTimeout(() => thumb.classList.remove('is-visible'), 900);
            }
        }

        area.addEventListener('scroll', showThumb, { passive: true });
        area.addEventListener('mouseenter', updateThumb);
        window.addEventListener('resize', updateThumb);
        updateThumb();
    });
</script>
