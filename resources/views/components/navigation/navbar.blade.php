@php
    $pendientesAuthCount = \App\Models\PeticionAutorizacion::where('estado', 'pendiente')->count();
    $usuarioActual = auth()->user();
@endphp

<header id="app-navbar">
    <div class="d-flex align-items-center" style="gap: 14px;">
        <button type="button" id="btn-toggle-sidebar" class="btn btn-sm btn-light border text-slate-700 px-2.5 py-1.5" title="Mostrar / Ocultar Menú">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="d-none d-sm-flex flex-column">
            <span class="font-weight-bold text-slate-800 text-sm">@yield('titulo', 'Panel de Control Captura IREC')</span>
            <span class="text-xs text-muted">Trazabilidad y Validación Documental</span>
        </div>
    </div>

    <div class="d-flex align-items-center" style="gap: 12px;">

        <!-- Botón de Autorizaciones en Tiempo Real -->
        <a href="{{ route('admin.autorizaciones.index') }}" class="btn btn-sm btn-outline-warning text-dark border d-flex align-items-center" style="gap: 6px; background-color: #fffbeb;">
            <i class="fa-solid fa-shield-halved text-amber-600"></i>
            <span class="d-none d-md-inline font-weight-bold text-xs">Autorizaciones</span>
            <span id="navbarAuthPendientesCount"
                  class="badge badge-pill badge-danger"
                  style="{{ $pendientesAuthCount > 0 ? 'display:inline-block;' : 'display:none;' }}">
                {{ $pendientesAuthCount }}
            </span>
        </a>

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
                        <i class="fa-solid fa-shield-halved mr-2 text-amber-600"></i> Autorizaciones en Tiempo Real
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
        if (typeof window.Echo !== 'undefined') {
            try {
                window.Echo.channel('autorizaciones-captura')
                    .listen('.peticion.actualizada', function (e) {
                        if (e.evento === 'nueva_peticion' && typeof Swal !== 'undefined') {
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
                    });
            } catch (err) {}
        }
    });
</script>
