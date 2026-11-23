<!DOCTYPE html>
<html lang="es" class="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'Captura IREC - Sistema de Gestión y Auditoría')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Fonts: Plus Jakarta Sans & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Icons: Material Design Icons & FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.2.96/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap 4.6 CSS (compatibilidad con grillas y tablas de reportes) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <!-- TailwindCSS compilado localmente con Vite (sin CDN) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- jQuery, Bootstrap Bundle, Chart.js, SweetAlert2, Pusher & Echo -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>

    <style>
        :root {
            --sidebar-width: 265px;
            --navbar-height: 64px;
            --brand-primary: #034d6d;
            --brand-dark: #0f172a;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            margin: 0;
            overflow-x: hidden;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        #app-shell {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        #app-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: #f8fafc;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1040;
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 24px rgba(15, 23, 42, 0.15);
        }
        body.sidebar-collapsed #app-sidebar {
            transform: translateX(-100%);
        }
        #app-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-width: 0;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.sidebar-collapsed #app-main {
            margin-left: 0;
        }
        #app-navbar {
            height: var(--navbar-height);
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 1030;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        #app-content {
            padding: 1.5rem;
            flex: 1;
        }
        /* Modern Cards & Tables override for legacy views */
        .card {
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04) !important;
            background-color: #ffffff;
        }
        .card-header {
            background-color: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
            font-weight: 700;
            color: #0f172a !important;
            padding: 0.85rem 1.25rem;
            border-top-left-radius: 12px !important;
            border-top-right-radius: 12px !important;
        }
        .form-control {
            border-radius: 8px;
            border-color: #cbd5e1;
            font-size: 0.875rem;
        }
        .form-control:focus {
            border-color: #034d6d;
            box-shadow: 0 0 0 3px rgba(3, 77, 109, 0.15);
        }
        .btn {
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.15s ease;
        }
        @media (max-width: 991px) {
            #app-sidebar {
                transform: translateX(-100%);
            }
            body.sidebar-mobile-open #app-sidebar {
                transform: translateX(0);
            }
            #app-main {
                margin-left: 0;
            }
        }
    </style>
    @yield('pre-script')
    @stack('styles')
</head>
<body>
    <div id="app-shell">
        <!-- Sidebar Component -->
        @include('components.navigation.sidebar')

        <!-- Main Workspace -->
        <div id="app-main">
            <!-- Navbar Component -->
            @include('components.navigation.navbar')

            <!-- Dynamic Content -->
            <main id="app-content">
                <div class="container-fluid p-0">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show rounded-lg shadow-sm border-0 mb-3" role="alert">
                            <i class="fa-solid fa-circle-check mr-2"></i> {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show rounded-lg shadow-sm border-0 mb-3" role="alert">
                            <i class="fa-solid fa-triangle-exclamation mr-2"></i> {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        </div>
                    @endif

                    @yield('header-layout')
                    @yield('contenido')
                </div>
            </main>

            <!-- Footer -->
            <footer class="py-3 px-4 text-center text-muted small border-top bg-white">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
                    <span><strong>Captura-IREC</strong> &copy; {{ date('Y') }} — Sistema de Trazabilidad, Captura y Auditoría Documental</span>
                    <span class="badge badge-light border text-secondary">Meta Operativa: 800 capturas/día por operador</span>
                </div>
            </footer>
        </div>
    </div>

    <!-- Script de Inicialización Global (Sidebar + WebSockets Echo) -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btnToggle = document.getElementById('btn-toggle-sidebar');
            if (btnToggle) {
                btnToggle.addEventListener('click', function () {
                    if (window.innerWidth <= 991) {
                        document.body.classList.toggle('sidebar-mobile-open');
                    } else {
                        document.body.classList.toggle('sidebar-collapsed');
                    }
                });
            }

            // Inicializar Laravel Echo conectado a Laravel Reverb
            if (typeof Pusher !== 'undefined' && typeof Echo !== 'undefined') {
                try {
                    const reverbKey = '{{ config("broadcasting.connections.reverb.key", env("REVERB_APP_KEY", "")) }}';
                    const reverbHost = '{{ config("broadcasting.connections.reverb.options.host", env("REVERB_HOST", "127.0.0.1")) }}';
                    const reverbPort = {{ (int) config('broadcasting.connections.reverb.options.port', env('REVERB_PORT', 8080)) }};
                    const reverbScheme = '{{ config("broadcasting.connections.reverb.options.scheme", env("REVERB_SCHEME", "http")) }}';
                    const useTLS = reverbScheme === 'https';

                    if (reverbKey) {
                        Pusher.logToConsole = false;
                        window.Pusher = Pusher;
                        window.Echo = new Echo({
                            broadcaster: 'pusher',
                            key: reverbKey,
                            cluster: 'mt1',
                            wsHost: reverbHost || window.location.hostname,
                            wsPort: reverbPort,
                            wssPort: reverbPort,
                            forceTLS: useTLS,
                            encrypted: useTLS,
                            disableStats: true,
                            enabledTransports: useTLS ? ['ws', 'wss'] : ['ws'],
                        });

                        window.Echo.connector.pusher.connection.bind('connected', function () {
                            const badge = document.getElementById('ws-status-indicator');
                            if (badge) {
                                badge.innerHTML = '<span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>WS Activo';
                                badge.className = 'px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 d-none d-md-inline-flex align-items-center';
                            }
                        });

                        window.Echo.connector.pusher.connection.bind('unavailable', function () {
                            const badge = document.getElementById('ws-status-indicator');
                            if (badge) {
                                badge.innerHTML = '<span class="inline-block w-2 h-2 rounded-full bg-amber-400 mr-1.5"></span>WS Standby';
                                badge.className = 'px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 d-none d-md-inline-flex align-items-center';
                            }
                        });
                    }
                } catch (e) {
                    // Fallback silencioso si WebSocket no está iniciado
                }
            }
        });
    </script>

    @yield('pos-script')
    @stack('scripts')
</body>
</html>
