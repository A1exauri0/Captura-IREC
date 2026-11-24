@php
    // Definición dinámica del menú en JSON directamente dentro del código del sidebar
    $sidebarMenuJson = <<<'JSON'
[
    {
        "section": "Operación",
        "items": [
            {
                "label": "Dashboard de Avance",
                "icon": "fa-solid fa-gauge-high",
                "route": "admin.dashboard.avance",
                "active_routes": ["admin.dashboard.avance"]
            },
            {
                "label": "Estación de Captura",
                "icon": "fa-solid fa-desktop",
                "route": "admin.captura.index",
                "active_routes": ["admin.captura.*"]
            }
        ]
    },
    {
        "section": "Tiempo Real",
        "items": [
            {
                "label": "Sesiones Activas",
                "icon": "fa-solid fa-satellite-dish",
                "route": "admin.analytics_sellos.reportes.lotes_trabajando",
                "active_routes": ["admin.analytics_sellos.reportes.lotes_trabajando"],
                "badge": "LIVE"
            },
            {
                "label": "Autorizaciones",
                "icon": "fa-solid fa-shield-halved",
                "route": "admin.autorizaciones.index",
                "active_routes": ["admin.autorizaciones.*"]
            },
            {
                "label": "Explorador PDF",
                "icon": "fa-solid fa-magnifying-glass",
                "route": "admin.analytics_sellos.reportes.buscar_archivos",
                "active_routes": ["admin.analytics_sellos.reportes.buscar_archivos"]
            },
            {
                "id": "submenu-supervision",
                "label": "Supervisión & Stellum",
                "icon": "fa-solid fa-list-check",
                "children": [
                    { "label": "Inconsistencias", "icon": "fa-solid fa-triangle-exclamation", "route": "admin.inconsistencias.index" },
                    { "label": "Archivos por revisar", "icon": "fa-solid fa-code-compare", "route": "admin.colisiones.index" },
                    { "label": "Aprobación de Renombres", "icon": "fa-solid fa-check-double", "route": "admin.aprobacion.index" }
                ]
            }
        ]
    },
    {
        "section": "Analíticas & Bitácora",
        "items": [
            {
                "id": "submenu-indicadores",
                "label": "Indicadores KPI",
                "icon": "fa-solid fa-chart-pie",
                "children": [
                    /* { "label": "Registros", "icon": "fa-solid fa-file-signature", "route": "admin.analytics_capturas.reportes.index" }, */
                    { "label": "Sellos", "icon": "fa-solid fa-stamp", "route": "admin.analytics_sellos.reportes.index" }
                    /* ,{ "label": "Notarías", "icon": "fa-solid fa-landmark", "route": "admin.analytics_digitalizacion.reportes.capturas" },
                    { "label": "Catastro", "icon": "fa-solid fa-map-location-dot", "route": "admin.analytics_catastro.reportes.index" } */
                ]
            },
            {
                "id": "submenu-productividad",
                "label": "Productividad",
                "icon": "fa-solid fa-users-line",
                "children": [
                    /* { "label": "Registros", "icon": "fa-solid fa-file-signature", "route": "admin.analytics_capturas.reportes.usuarios" }, */
                    { "label": "Sellos", "icon": "fa-solid fa-stamp", "route": "admin.analytics_sellos.reportes.usuarios" }
                    /* ,{ "label": "Notarías", "icon": "fa-solid fa-landmark", "route": "admin.analytics_digitalizacion.reportes.reportes_capturas_notarias" },
                    { "label": "Catastro", "icon": "fa-solid fa-map-location-dot", "route": "admin.analytics_catastro.reportes.usuarios" } */
                ]
            },
            {
                "id": "submenu-bitacoras",
                "label": "Bitácoras",
                "icon": "fa-solid fa-clock-rotate-left",
                "children": [
                    /* { "label": "Registros", "icon": "fa-solid fa-file-signature", "route": "admin.analytics_capturas.reportes.detalles_renombre" }, */
                    { "label": "Sellos", "icon": "fa-solid fa-stamp", "route": "admin.analytics_sellos.reportes.detalles_renombre" }
                    /* ,{ "label": "Notarías", "icon": "fa-solid fa-landmark", "route": "admin.analytics_digitalizacion.reportes.detalles_renombre" },
                    { "label": "Catastro", "icon": "fa-solid fa-map-location-dot", "route": "admin.analytics_catastro.reportes.detalles_renombre" } */
                ]
            }
        ]
    },
    {
        "section": "Administración",
        "items": [
            {
                "id": "submenu-acceso",
                "label": "Control de Acceso",
                "icon": "fa-solid fa-users-gear",
                "children": [
                    { "label": "Usuarios", "icon": "fa-solid fa-users", "route": "admin.usuarios.index" },
                    { "label": "Roles", "icon": "fa-solid fa-user-shield", "route": "admin.roles.index" },
                    { "label": "Permisos", "icon": "fa-solid fa-key", "route": "admin.permisos.index" }
                ]
            },
            {
                "label": "Turnos",
                "icon": "fa-solid fa-user-clock",
                "route": "admin.turnos.index",
                "active_routes": ["admin.turnos.*"]
            },
            {
                "label": "Asignación de Captura",
                "icon": "fa-solid fa-user-tag",
                "route": "admin.asignaciones.index",
                "active_routes": ["admin.asignaciones.*"]
            },
            {
                "label": "Inventario de Equipos",
                "icon": "fa-solid fa-laptop",
                "route": "admin.equipos.index",
                "active_routes": ["admin.equipos.*"]
            },
            {
                "id": "submenu-catalogos",
                "label": "Catálogos",
                "icon": "fa-solid fa-folder-tree",
                "children": [
                    { "label": "Rutas PDF", "icon": "fa-solid fa-network-wired", "route": "admin.catalogos.index" },
                    { "label": "Delegaciones", "icon": "fa-solid fa-building-flag", "route": "admin.delegaciones.index" },
                    { "label": "Municipios y Localidades", "icon": "fa-solid fa-map-location-dot", "route": "admin.municipios.index" },
                    { "label": "Catálogo Inconsistencias", "icon": "fa-solid fa-triangle-exclamation", "route": "admin.catalogo_inconsistencias.index" }
                ]
            }
        ]
    }
]
JSON;

    $sidebarMenuJsonLimpio = preg_replace('/\/\*.*?\*\//s', '', $sidebarMenuJson);
    $menuSections = json_decode($sidebarMenuJsonLimpio, true) ?: [];

    // Si el usuario no es superadmin ni administrador, ocultar Dashboard de Avance; y si es Captura, dejar solo Estación de Captura
    $usrSidebar = auth()->user();
    $esAdminGlobal = $usrSidebar && $usrSidebar->hasAnyRole(['superadmin', 'administrador']);
    if (!$esAdminGlobal) {
        foreach ($menuSections as &$sec) {
            if (!empty($sec['items'])) {
                $sec['items'] = array_values(array_filter(
                    $sec['items'],
                    fn ($it) => ($it['route'] ?? '') !== 'admin.dashboard.avance'
                ));
            }
        }
        unset($sec);
    }
    if ($usrSidebar && $usrSidebar->hasRole('Captura') && !$esAdminGlobal) {
        $menuSections = array_values(array_filter($menuSections, fn ($s) => ($s['section'] ?? '') === 'Operación'));
    }
@endphp

<aside id="app-sidebar">
    <!-- Brand Header -->
    <div class="px-4 py-3.5 border-b border-slate-800/80 d-flex align-items-center justify-content-between flex-shrink-0">
        <a href="{{ url('/') }}" class="d-flex align-items-center text-white text-decoration-none" style="gap: 11px;">
            <div class="w-9 h-9 rounded-xl bg-white p-1 d-flex align-items-center justify-content-center shadow-sm flex-shrink-0">
                <img src="{{ asset('img/favicon.png') }}" alt="Logo" class="w-full h-full" style="object-fit: contain;">
            </div>
            <div>
                <span class="font-weight-bold d-block tracking-tight text-white" style="font-size: 0.9rem; line-height: 1.15;">Digitalización Chiapas 2026</span>
                <span class="text-slate-400 font-medium" style="font-size: 0.7rem;">Auditoría Documental</span>
            </div>
        </a>
    </div>

    <!-- Navigation Links pintados dinámicamente desde JSON + Scroll Personalizado -->
    <div class="sidebar-scroll-wrapper flex-1 min-h-0">
        <nav id="sidebar-scroll-area" class="custom-scroll h-full overflow-y-auto px-3 py-4">
            @foreach($menuSections as $section)
                <div class="px-2.5 mb-1.5 text-slate-400 font-weight-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.08em;">
                    {{ $section['section'] ?? '' }}
                </div>
                <ul class="list-unstyled mb-4">
                    @foreach(($section['items'] ?? []) as $idx => $item)
                        @php
                            $hasChildren = !empty($item['children']) && is_array($item['children']);
                            $isActive = false;
                            if ($hasChildren) {
                                foreach ($item['children'] as $child) {
                                    if (!empty($child['route']) && request()->routeIs($child['route'])) {
                                        $isActive = true;
                                        break;
                                    }
                                }
                            } else {
                                $patterns = $item['active_routes'] ?? (!empty($item['route']) ? [$item['route']] : []);
                                foreach ($patterns as $pat) {
                                    if (request()->routeIs($pat)) {
                                        $isActive = true;
                                        break;
                                    }
                                }
                            }
                            $submenuId = $item['id'] ?? ('submenu-' . $loop->parent->index . '-' . $idx);
                        @endphp

                        @if($hasChildren)
                            <li class="mb-1">
                                <button type="button"
                                        data-submenu-toggle="{{ $submenuId }}"
                                        aria-expanded="{{ $isActive ? 'true' : 'false' }}"
                                        class="w-100 border-0 bg-transparent d-flex align-items-center px-3 py-2 rounded-lg font-medium transition-colors text-left {{ $isActive ? 'bg-slate-800 text-white' : 'text-slate-200 hover:bg-slate-800/80 hover:text-white' }}"
                                        style="font-size: 0.875rem; color: {{ $isActive ? '#ffffff' : '#e2e8f0' }}; cursor: pointer; outline: none;">
                                    <i class="{{ $item['icon'] ?? 'fa-solid fa-circle' }} w-5 text-center mr-2.5" style="color: {{ $isActive ? '#38bdf8' : '#cbd5e1' }};"></i>
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200 submenu-arrow"
                                       style="color: #94a3b8; transform: rotate({{ $isActive ? '180deg' : '0deg' }});"></i>
                                </button>
                                <ul id="{{ $submenuId }}"
                                    class="list-unstyled mt-1 mb-1 pl-3 ml-3 border-l border-slate-700/70 {{ $isActive ? '' : 'd-none' }}">
                                    @foreach($item['children'] as $child)
                                        @php
                                            $childRoute = $child['route'] ?? null;
                                            $childActive = $childRoute && request()->routeIs($childRoute);
                                            $childHref = ($childRoute && Route::has($childRoute)) ? route($childRoute) : '#';
                                        @endphp
                                        <li class="mb-0.5">
                                            <a href="{{ $childHref }}"
                                               class="d-flex align-items-center px-2.5 py-1.5 rounded-md font-medium text-decoration-none transition-colors {{ $childActive ? 'bg-sky-600 text-white shadow-sm' : 'hover:bg-slate-800/70' }}"
                                               style="font-size: 0.78rem; color: {{ $childActive ? '#ffffff' : '#cbd5e1' }};">
                                                <i class="{{ $child['icon'] ?? 'fa-solid fa-angle-right' }} w-4 text-center mr-2" style="color: {{ $childActive ? '#ffffff' : '#94a3b8' }};"></i>
                                                <span>{{ $child['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            @php
                                $itemRoute = $item['route'] ?? null;
                                $itemHref = ($itemRoute && Route::has($itemRoute)) ? route($itemRoute) : '#';
                            @endphp
                            <li class="mb-1">
                                <a href="{{ $itemHref }}"
                                   class="d-flex align-items-center px-3 py-2 rounded-lg font-medium text-decoration-none transition-colors {{ $isActive ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-200 hover:bg-slate-800/80 hover:text-white' }}"
                                   style="font-size: 0.875rem; color: {{ $isActive ? '#ffffff' : '#e2e8f0' }};">
                                    <i class="{{ $item['icon'] ?? 'fa-solid fa-circle' }} w-5 text-center mr-2.5" style="color: {{ $isActive ? '#ffffff' : '#cbd5e1' }};"></i>
                                    <span>{{ $item['label'] }}</span>
                                    @if(!empty($item['badge']))
                                        <span class="ml-auto d-inline-flex align-items-center px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300" style="font-size: 0.6rem;">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <!-- Barra de scroll personalizada tipo overlay -->
        <div id="sidebar-scroll-thumb" class="sidebar-scroll-thumb"></div>
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const area = document.getElementById('sidebar-scroll-area');
        const thumb = document.getElementById('sidebar-scroll-thumb');

        // Submenús desplegables del sidebar
        document.querySelectorAll('[data-submenu-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetId = this.getAttribute('data-submenu-toggle');
                const submenu = document.getElementById(targetId);
                const arrow = this.querySelector('.submenu-arrow');
                if (!submenu) return;

                const isHidden = submenu.classList.contains('d-none');
                submenu.classList.toggle('d-none', !isHidden);
                this.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                if (arrow) {
                    arrow.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                if (typeof window.__updateSidebarThumb === 'function') {
                    window.__updateSidebarThumb();
                }
            });
        });

        // Scroll personalizado (sin barra nativa del navegador)
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
        window.__updateSidebarThumb = updateThumb;

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
