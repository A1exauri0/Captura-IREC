@php
    $pinDiario = \App\Models\PinAutorizacion::obtenerPinActivo();
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
        <!-- Indicador de estado WebSocket -->
        <span id="ws-status-indicator" class="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200 d-none d-md-inline-flex align-items-center">
            <span class="inline-block w-2 h-2 rounded-full bg-sky-500 mr-1.5"></span>Tiempo Real
        </span>

        <!-- Botón PIN Maestro de Uso Único -->
        <button type="button" class="btn btn-sm btn-outline-info d-flex align-items-center" data-toggle="modal" data-target="#pinDiarioModal" style="gap: 6px;">
            <i class="fa-solid fa-key"></i>
            <span class="d-none d-md-inline">PIN:</span>
            <strong id="navbarPinBadge">{{ $pinDiario }}</strong>
        </button>

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
                        <i class="fa-solid fa-user-clock mr-2 text-sky-600"></i> Gestionar Turnos
                    </a>
                    <a class="dropdown-item py-2 text-sm" href="#" data-toggle="modal" data-target="#pinDiarioModal">
                        <i class="fa-solid fa-lock mr-2 text-amber-600"></i> PIN de Autorización
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

<!-- Modal PIN de Autorización de Uso Único -->
<div class="modal fade" id="pinDiarioModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0f172a, #034d6d);">
                <h5 class="modal-title font-weight-bold">
                    <i class="fa-solid fa-lock mr-2"></i> PIN de Autorización (Uso Único)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center p-4 bg-light">
                <p class="text-muted mb-3 small">
                    Proporciona este PIN al capturista para autorizar una acción especial en la estación de captura.
                </p>
                <div class="p-4 my-2 bg-white rounded-xl shadow-sm border d-inline-block" style="min-width: 200px;">
                    <h1 id="txtPinActivo" class="display-4 font-weight-bold m-0" style="letter-spacing: 8px; color: #034d6d;">
                        {{ $pinDiario }}
                    </h1>
                </div>
            </div>
            <div class="modal-footer bg-white border-top-0 justify-content-center p-3">
                <button type="button" id="btnGenerarNuevoPin" class="btn btn-outline-primary px-4">
                    <i class="fa-solid fa-rotate mr-1"></i> Regenerar PIN
                </button>
                <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const btnRecargar = document.getElementById('btnGenerarNuevoPin');
        if (btnRecargar) {
            btnRecargar.addEventListener('click', function () {
                btnRecargar.disabled = true;
                fetch("{{ route('admin.usuarios.generarNuevoPin') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('txtPinActivo').textContent = data.pin;
                        const badge = document.getElementById('navbarPinBadge');
                        if (badge) badge.textContent = data.pin;
                    }
                })
                .finally(() => {
                    btnRecargar.disabled = false;
                });
            });
        }
    });
</script>
