@php
    $menuPath = resource_path('data/sidebar_menu.json');
    $menuSections = file_exists($menuPath)
        ? json_decode(file_get_contents($menuPath), true)
        : [];
    if (!is_array($menuSections)) {
        $menuSections = [];
    }
@endphp

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

    <!-- Navigation Links dinámicos desde JSON + Scroll Personalizado -->
    <div class="sidebar-scroll-wrapper flex-1 min-h-0">
        <nav id="sidebar-scroll-area" class="custom-scroll h-full overflow-y-auto px-3 py-4">
            @foreach($menuSections as $section)
                <div class="px-2.5 mb-1.5 text-slate-500 font-weight-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.08em;">
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
                                        class="w-100 border-0 bg-transparent d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors text-left {{ $isActive ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}"
                                        style="cursor: pointer; outline: none;">
                                    <i class="{{ $item['icon'] ?? 'fa-solid fa-circle' }} w-5 text-center mr-2.5 {{ $isActive ? 'text-sky-400' : ($item['icon_color'] ?? 'text-slate-400') }}"></i>
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    <i class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform duration-200 submenu-arrow"
                                       style="transform: rotate({{ $isActive ? '180deg' : '0deg' }});"></i>
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
                                               class="d-flex align-items-center px-2.5 py-1.5 rounded-md text-xs font-medium text-decoration-none transition-colors {{ $childActive ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800/70 hover:text-white' }}">
                                                <i class="{{ $child['icon'] ?? 'fa-solid fa-angle-right' }} w-4 text-center mr-2 {{ $childActive ? 'text-white' : 'text-slate-500' }}"></i>
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
                                   class="d-flex align-items-center px-3 py-2 rounded-lg text-sm font-medium text-decoration-none transition-colors {{ $isActive ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white' }}">
                                    <i class="{{ $item['icon'] ?? 'fa-solid fa-circle' }} w-5 text-center mr-2.5 {{ $isActive ? 'text-white' : ($item['icon_color'] ?? 'text-slate-400') }}"></i>
                                    <span>{{ $item['label'] }}</span>
                                    @if(!empty($item['badge']))
                                        <span class="ml-auto d-inline-flex align-items-center px-1.5 py-0.5 rounded {{ $item['badge_class'] ?? 'bg-sky-500/20 text-sky-300' }}" style="font-size: 0.6rem;">
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
