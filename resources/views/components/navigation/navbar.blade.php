@php
    $pendientesAuthCount = \App\Models\PeticionAutorizacion::where('estado', 'pendiente')->count();
    $pendientesIncCount = 0;
    try {
        foreach (['auditoria_registros', 'auditoria_sellos', 'auditoria_notarias', 'auditoria_catastro'] as $tblInc) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($tblInc, 'inconsistencia_codigo')) {
                $pendientesIncCount += \Illuminate\Support\Facades\DB::table($tblInc)
                    ->whereNotNull('inconsistencia_codigo')
                    ->where(function ($q) {
                        $q->whereNull('inconsistencia_estado')->orWhere('inconsistencia_estado', 'pendiente');
                    })->count();
            }
        }
    } catch (\Throwable $e) {}
    $pendientesAuthCount += $pendientesIncCount;
    $notificacionesRecientes = \App\Models\PeticionAutorizacion::with('solicitante:id,name,user')
        ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
        ->orderByDesc('id')
        ->limit(8)
        ->get();
    $usuarioActual = auth()->user();
@endphp

<header id="app-navbar">
    <div class="d-flex align-items-center" style="gap: 14px;">
        <button type="button" id="btn-toggle-sidebar" class="btn btn-sm btn-light border text-slate-700 px-2.5 py-1.5" title="Mostrar / Ocultar Menú">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="d-none d-sm-flex flex-column">
            <span class="font-weight-bold text-slate-800 text-sm">@yield('titulo', 'Digitalización Chiapas 2026')</span>
            <span class="text-xs text-muted">Trazabilidad y Validación Documental</span>
        </div>
    </div>

    <div class="d-flex align-items-center" style="gap: 12px;">

        <!-- Botón de Notificaciones con lista desplegable -->
        <div class="dropdown">
            <button type="button"
                    id="btnNotificacionesNavbar"
                    class="btn btn-sm btn-light border position-relative d-flex align-items-center justify-content-center px-2.5 py-1.5 text-slate-700"
                    data-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false"
                    title="Notificaciones">
                <i class="fa-regular fa-bell text-base"></i>
                <span id="navbarAuthPendientesCount"
                      class="badge badge-pill badge-danger position-absolute"
                      style="top: -6px; right: -6px; font-size: 0.65rem; {{ $pendientesAuthCount > 0 ? 'display:inline-block;' : 'display:none;' }}">
                    {{ $pendientesAuthCount }}
                </span>
            </button>

            <div class="dropdown-menu dropdown-menu-right shadow-lg border rounded-xl p-0 mt-2"
                 aria-labelledby="btnNotificacionesNavbar"
                 style="width: 340px; max-width: 92vw; overflow: hidden;">
                <div class="px-3 py-2.5 bg-slate-50 border-bottom d-flex align-items-center justify-content-between">
                    <span class="font-weight-bold text-xs uppercase text-slate-700">
                        <i class="fa-solid fa-bell text-sky-700 mr-1.5"></i> Notificaciones
                    </span>
                    <span id="navbarNotifBadgeText" class="badge badge-info">
                        {{ $pendientesAuthCount }} pendientes
                    </span>
                </div>

                <div id="navbarNotifList" class="custom-scroll" style="max-height: 310px; overflow-y: auto;">
                    @forelse($notificacionesRecientes as $notif)
                        <a href="{{ route('admin.autorizaciones.index') }}"
                           class="dropdown-item py-2.5 px-3 border-bottom d-flex align-items-start text-wrap"
                           style="gap: 10px; white-space: normal;">
                            <div class="mt-0.5">
                                @if($notif->estado === 'pendiente')
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-amber-100 text-amber-700" style="width: 28px; height: 28px;">
                                        <i class="fa-solid fa-clock text-xs"></i>
                                    </span>
                                @elseif($notif->estado === 'autorizada')
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-emerald-100 text-emerald-700" style="width: 28px; height: 28px;">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </span>
                                @else
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-rose-100 text-rose-700" style="width: 28px; height: 28px;">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </span>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="font-weight-bold text-xs text-slate-800 text-truncate">
                                        {{ $notif->solicitante->name ?? ('Usuario #' . $notif->user_id) }}
                                    </span>
                                    <span class="text-muted" style="font-size: 10px;">
                                        {{ $notif->created_at ? $notif->created_at->diffForHumans() : '' }}
                                    </span>
                                </div>
                                <div class="text-xs text-slate-600 mt-0.5">
                                    <strong>{{ $notif->tipo_peticion }}</strong> — {{ $notif->archivo_nombre ?: ($notif->lote ?: 'Solicitud general') }}
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="py-4 px-3 text-center text-muted text-xs" id="navbarNotifEmpty">
                            <i class="fa-regular fa-bell-slash d-block mb-1 text-lg text-slate-300"></i>
                            Sin notificaciones recientes.
                        </div>
                    @endforelse
                </div>

                <div class="px-3 py-2 bg-slate-50 border-top d-flex justify-content-between">
                    <a href="{{ route('admin.autorizaciones.index') }}" class="text-xs font-weight-bold text-sky-700 text-decoration-none">
                        Autorizaciones
                    </a>
                    <a href="{{ route('admin.inconsistencias.index') }}" class="text-xs font-weight-bold text-amber-700 text-decoration-none">
                        Inconsistencias ({{ $pendientesIncCount }}) <i class="fa-solid fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Usuario autenticado -->
        @auth
            <div class="dropdown">
                <button class="btn btn-sm btn-light border d-flex align-items-center px-2.5 py-1.5 dropdown-toggle" type="button" data-toggle="dropdown">
                    <i class="fa-solid fa-circle-user text-sky-700 text-lg mr-2"></i>
                    <div class="text-left d-none d-sm-block mr-1" style="line-height: 1.15;">
                        <div class="font-weight-bold text-xs text-slate-800">{{ $usuarioActual->name ?? $usuarioActual->user }}</div>
                        <div class="text-muted" style="font-size: 10px;">
                            Turno: {{ $usuarioActual->turnoRelacion->nombre ?? $usuarioActual->turno ?? 'Matutino' }}
                        </div>
                    </div>
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow-lg border-0 rounded-xl mt-2">
                    <a class="dropdown-item py-2 text-sm" href="{{ route('admin.turnos.index') }}">
                        <i class="fa-solid fa-user-clock mr-2 text-sky-600"></i> Turnos Adaptativos
                    </a>
                    <a class="dropdown-item py-2 text-sm" href="{{ route('admin.autorizaciones.index') }}">
                        <i class="fa-solid fa-shield-halved mr-2 text-amber-600"></i> Autorizaciones
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item py-2 text-sm text-danger font-weight-bold" href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('navbar-logout-form').submit();">
                        <i class="fa-solid fa-right-from-bracket mr-2"></i> Cerrar Sesión
                    </a>
                    <form id="navbar-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        @endauth
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        function agregarNotificacionNavbar(peticion) {
            const badge = document.getElementById('navbarAuthPendientesCount');
            const badgeText = document.getElementById('navbarNotifBadgeText');
            const list = document.getElementById('navbarNotifList');
            const empty = document.getElementById('navbarNotifEmpty');

            if (badge) {
                const actual = parseInt(badge.textContent || '0', 10) || 0;
                const nuevo = actual + 1;
                badge.textContent = nuevo;
                badge.style.display = 'inline-block';
                if (badgeText) badgeText.textContent = nuevo + ' pendientes';
            }
            if (empty) empty.remove();
            if (list) {
                const html = `
                    <a href="{{ route('admin.autorizaciones.index') }}" class="dropdown-item py-2.5 px-3 border-bottom d-flex align-items-start text-wrap bg-amber-50/40" style="gap: 10px; white-space: normal;">
                        <div class="mt-0.5">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-amber-100 text-amber-700" style="width: 28px; height: 28px;">
                                <i class="fa-solid fa-clock text-xs"></i>
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="font-weight-bold text-xs text-slate-800 text-truncate">${peticion.capturista || 'Capturista'}</span>
                                <span class="text-muted" style="font-size: 10px;">Ahora</span>
                            </div>
                            <div class="text-xs text-slate-600 mt-0.5">
                                <strong>${peticion.tipo_peticion || 'Autorización'}</strong> — ${peticion.archivo_nombre || peticion.lote || 'Solicitud'}
                            </div>
                        </div>
                    </a>`;
                list.insertAdjacentHTML('afterbegin', html);
            }
        }

        if (typeof window.Echo !== 'undefined') {
            try {
                window.Echo.channel('autorizaciones-captura')
                    .listen('.peticion.actualizada', function (e) {
                        if (e.evento === 'nueva_peticion') {
                            agregarNotificacionNavbar(e.peticion || {});
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'info',
                                    title: `Nueva petición de ${e.peticion.capturista} (${e.peticion.tipo_peticion})`,
                                    showConfirmButton: true,
                                    confirmButtonText: 'Ver',
                                    timer: 6000
                                }).then((r) => {
                                    if (r.isConfirmed) {
                                        window.location.href = "{{ route('admin.autorizaciones.index') }}";
                                    }
                                });
                            }
                        }
                    })
                    .listen('.inconsistencia.reportada', function (e) {
                        const inc = e.inconsistencia || {};
                        agregarNotificacionNavbar({
                            capturista: inc.capturista || 'Capturista',
                            tipo_peticion: 'Inconsistencia ' + (inc.inconsistencia_codigo || ''),
                            archivo_nombre: inc.archivo_original || inc.lote || 'Archivo'
                        });
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'warning',
                                title: `Inconsistencia ${inc.inconsistencia_codigo || ''} reportada por ${inc.capturista || 'Capturista'}`,
                                showConfirmButton: true,
                                confirmButtonText: 'Revisar',
                                timer: 7000
                            }).then((r) => {
                                if (r.isConfirmed) {
                                    window.location.href = "{{ route('admin.inconsistencias.index') }}";
                                }
                            });
                        }
                    });
            } catch (err) {}
        }
    });
</script>
