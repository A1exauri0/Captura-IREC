<!DOCTYPE html>
<html lang="es" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Captura Sellos</title>
    <!-- PWA Manifest & Meta -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#1f2937">
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/favicon.png') }}">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- TailwindCSS compilado con Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Módulos JS de captura -->
    <script src="{{ asset('js/captura/capturaReglas.js') }}"></script>
    <script src="{{ asset('js/captura/capturaArchivos.js') }}"></script>
    <script src="{{ asset('js/captura/capturaAuditoria.js') }}"></script>
    <script src="{{ asset('js/captura/capturaProcesos.js') }}"></script>
</head>

<body
    class="h-screen w-screen overflow-hidden bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-sans selection:bg-brand selection:text-white">

    <!-- Bloqueo responsivo para celulares / pantallas pequeñas (< 768px) -->
    <div id="overlayBloqueoMovil" class="md:hidden fixed inset-0 z-[100] bg-slate-900 text-slate-100 flex items-center justify-center p-6">
        <div class="max-w-sm w-full bg-slate-800 border border-slate-700 rounded-2xl p-6 text-center shadow-2xl space-y-3">
            <div class="w-14 h-14 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center mx-auto text-2xl">
                <i class="fa-solid fa-desktop"></i>
            </div>
            <h2 class="text-lg font-bold text-white">Solo disponible en computadora</h2>
            <p class="text-xs text-slate-300">
                La Estación de Captura no puede utilizarse desde celulares ni pantallas pequeñas.
            </p>
            <button type="button" onclick="document.getElementById('logout-form').submit();"
                class="w-full py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs">
                <i class="fa-solid fa-right-from-bracket mr-1"></i> Cerrar Sesión
            </button>
        </div>
    </div>

    <!-- Bloqueo por horario laboral (controlado por servidor y frontend) -->
    <div id="overlayBloqueoHorario" class="hidden fixed inset-0 z-[90] bg-slate-950/90 backdrop-blur-sm flex items-center justify-center p-6">
        <div class="max-w-md w-full bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-2xl p-6 text-center shadow-2xl space-y-4">
            <div class="w-16 h-16 rounded-2xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto text-3xl">
                <i class="fa-solid fa-clock"></i>
            </div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Horario Laboral Finalizado / Bloqueado</h2>
            <p id="lblMensajeBloqueoHorario" class="text-sm text-slate-600 dark:text-slate-300">
                Te encuentras fuera de tu horario laboral asignado.
            </p>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-sky-700 dark:text-sky-300">
                <i class="fa-regular fa-clock"></i>
                <span>Hora servidor:</span>
                <span id="relojServidorBloqueo">--:--:--</span>
            </div>
            <div class="pt-2">
                <button type="button" onclick="document.getElementById('logout-form').submit();"
                    class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i> Cerrar Sesión
                </button>
            </div>
        </div>
    </div>

    <div class="flex flex-col h-full w-full">
        <!-- Barra de Filtros Visuales del PDF (estilo Stellum: Normal, Dark Reader, Alto Contraste, Tinta Tenue, B&N, Negativo + Sliders) -->
        <div id="barraFiltrosPdf" class="bg-slate-800 dark:bg-slate-950 text-slate-200 px-3 py-1 border-b border-slate-700 flex items-center justify-between text-xs shrink-0 gap-2 flex-wrap select-none">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-slate-400 font-semibold text-[11px]"><i class="fa-solid fa-wand-magic-sparkles text-amber-400 mr-1"></i>Filtro:</span>
                <button type="button" onclick="seleccionarPresetVisor('normal')" id="btnMainPreset-normal" class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-600 text-white transition">Normal</button>
                <button type="button" onclick="seleccionarPresetVisor('dark_reader')" id="btnMainPreset-dark_reader" class="px-2 py-0.5 rounded text-[10px] font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition flex items-center gap-1" title="Fondo oscuro cálido respetando tintas"><i class="fa-solid fa-moon text-indigo-400"></i> Dark Reader</button>
                <button type="button" onclick="seleccionarPresetVisor('alto_contraste')" id="btnMainPreset-alto_contraste" class="px-2 py-0.5 rounded text-[10px] font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition" title="Contraste 160%">Alto Contraste</button>
                <button type="button" onclick="seleccionarPresetVisor('tinta_tenue')" id="btnMainPreset-tinta_tenue" class="px-2 py-0.5 rounded text-[10px] font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition" title="Contraste 220% + B&N">Tinta Tenue</button>
                <button type="button" onclick="seleccionarPresetVisor('blanco_negro')" id="btnMainPreset-blanco_negro" class="px-2 py-0.5 rounded text-[10px] font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition" title="Blanco y negro nítido">B&amp;N Nítido</button>
                <button type="button" onclick="seleccionarPresetVisor('modo_oscuro')" id="btnMainPreset-modo_oscuro" class="px-2 py-0.5 rounded text-[10px] font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition" title="Negativo / Invertido">Negativo</button>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-1 bg-slate-900/70 px-1.5 py-0.5 rounded border border-slate-700">
                    <span class="text-slate-400 text-[10px]">Contraste:</span>
                    <input type="range" id="mainRngContraste" min="50" max="300" step="5" value="100" oninput="actualizarFiltroDesdeSlidersMain()" class="w-16 h-1 bg-slate-700 rounded cursor-pointer accent-sky-500">
                    <span id="mainLblContraste" class="font-mono text-sky-400 font-bold text-[10px] w-8 text-right">100%</span>
                </div>
                <div class="flex items-center gap-1 bg-slate-900/70 px-1.5 py-0.5 rounded border border-slate-700">
                    <span class="text-slate-400 text-[10px]">Brillo:</span>
                    <input type="range" id="mainRngBrillo" min="50" max="200" step="5" value="100" oninput="actualizarFiltroDesdeSlidersMain()" class="w-14 h-1 bg-slate-700 rounded cursor-pointer accent-amber-500">
                    <span id="mainLblBrillo" class="font-mono text-amber-400 font-bold text-[10px] w-8 text-right">100%</span>
                </div>
                <div class="flex items-center gap-1 bg-slate-900/70 px-1.5 py-0.5 rounded border border-slate-700">
                    <span class="text-slate-400 text-[10px]">Sepia:</span>
                    <input type="range" id="mainRngSepia" min="0" max="100" step="5" value="0" oninput="actualizarFiltroDesdeSlidersMain()" class="w-12 h-1 bg-slate-700 rounded cursor-pointer accent-amber-600">
                    <span id="mainLblSepia" class="font-mono text-amber-300 font-bold text-[10px] w-7 text-right">0%</span>
                </div>
                <button type="button" onclick="seleccionarPresetVisor('normal')" class="text-slate-400 hover:text-white text-[10px] px-1" title="Restablecer filtros a Normal">
                    <i class="fa-solid fa-rotate-left"></i>
                </button>
                <!-- Reloj grande del Servidor (arriba a la derecha) -->
                <div class="flex items-center gap-1.5 bg-slate-900 px-3 py-0.5 rounded-lg border border-sky-500/50 shadow-sm ml-1" title="Hora oficial del servidor ({{ $servidorZonaHoraria ?? 'America/Mexico_City' }})">
                    <i class="fa-regular fa-clock text-sky-400 text-sm"></i>
                    <span id="relojServidor" class="font-mono font-extrabold text-base sm:text-lg tracking-wider text-white">--:--:--</span>
                </div>
            </div>
        </div>

        <!-- Banner de Aviso en Tiempo Real (10 min antes de terminar turno) -->
        <div id="alertaFinTurno" class="hidden px-4 py-1.5 bg-amber-500 text-slate-950 font-bold text-xs flex items-center justify-between gap-2 shrink-0 shadow">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-bell fa-shake"></i>
                <span id="lblAlertaFinTurno">Tu turno está por finalizar.</span>
            </div>
            <span id="lblCuentaRegresivaTurno" class="font-mono font-extrabold px-2 py-0.5 rounded bg-slate-950/15">10:00</span>
        </div>

        <!-- Visor de PDF -->
        <iframe class="flex-grow w-full border-none bg-white dark:bg-slate-900" id="pdfViewer" src="about:blank"></iframe>

        <!-- Splitter -->
        <div
            class="h-1 bg-gray-300 dark:bg-gray-700 cursor-ns-resize hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors">
        </div>

        <!-- Panel de controles inferior (Reorganizado: solo inputs segmentados + flujo rápido de teclado) -->
        <div class="relative bg-gray-100 dark:bg-gray-800 border-t border-gray-300 dark:border-gray-700 px-6 py-3 flex flex-col gap-3 transition-colors duration-200">

            <!-- Reglas ocultas (mantiene Regla 2 Sellos siempre activa) -->
            <div id="panelReglas" class="hidden">
                <input type="radio" name="regla" id="rbRegla1">
                <input type="radio" name="regla" id="rbRegla2" checked>
            </div>
            <!-- Input maestro oculto para compatibilidad con CapturaReglas / CapturaProcesos -->
            <input type="text" id="txtNombre" autocomplete="off" class="hidden">

            <!-- FILA 1: Estado, Preview compacto y Utilidades (Sin botón Config ni rutas visibles) -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 min-w-0">
                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100" id="lblTotalPdf">Cargando PDF...</span>
                    <span class="px-2 py-0.5 rounded bg-sky-100 dark:bg-sky-900/50 text-sky-800 dark:text-sky-300 text-xs font-bold" id="lblCaptura">Primera captura</span>
                    <code id="lblPreviewNombreSellos" class="px-2.5 py-0.5 rounded bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 text-xs font-mono font-bold text-gray-800 dark:text-gray-100">—</code>
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300" id="lblEstado">Listo para capturar</span>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if(auth()->user()?->hasAnyRole(['superadmin', 'administrador']))
                        <button id="btnAdminSiguiente" type="button" title="Saltar al siguiente PDF libre (solo Administrador)"
                            class="h-8 px-2.5 text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white rounded border border-sky-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-forward-step"></i> Siguiente
                        </button>
                        <button id="btnAdminBuscarHash" type="button" title="Buscar un grupo de varios PDFs con el mismo hash (solo Administrador)"
                            class="h-8 px-2.5 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded border border-indigo-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-layer-group"></i> Mismo Hash
                        </button>
                    @endif

                    <!-- Rotación física con página editable -->
                    <div class="flex items-center gap-1 bg-white dark:bg-gray-900 px-2 py-1 rounded border border-gray-300 dark:border-gray-600">
                        <span class="text-xs font-bold text-gray-600 dark:text-gray-300 mr-1">Rotar:</span>
                        <button id="btnRotarIzquierda" title="Rotar página -90°"
                            class="w-7 h-7 flex items-center justify-center bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded border border-gray-300 dark:border-gray-600 text-xs">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                        <button id="btnRotarDerecha" title="Rotar página +90°"
                            class="w-7 h-7 flex items-center justify-center bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded border border-gray-300 dark:border-gray-600 text-xs">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                        <span class="text-xs text-gray-500 ml-1">Pág</span>
                        <input type="number" id="nudPagina" min="1" value="1"
                            class="w-12 px-1 py-0.5 text-center text-xs font-bold bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:border-brand">
                    </div>

                    <button id="btnModo" title="Cambiar tema"
                        class="h-8 px-2.5 text-xs font-semibold bg-[#404040] hover:bg-gray-600 text-white rounded border border-gray-700 flex items-center gap-1.5">
                        <i class="fa-solid fa-moon"></i> Oscuro
                    </button>
                    <button id="btnRefrescar" title="Refrescar visor"
                        class="w-8 h-8 flex items-center justify-center bg-[#404040] hover:bg-gray-600 text-white rounded border border-gray-700 text-xs">
                        <i class="fa-solid fa-sync"></i>
                    </button>
                    <button id="btnCerrarSesion"
                        class="h-8 px-3 text-xs font-bold bg-red-600 hover:bg-red-700 text-white rounded border border-red-800 flex items-center gap-1.5">
                        <i class="fa-solid fa-power-off"></i> Salir
                    </button>
                </div>
            </div>

            <!-- BARRA DE GRUPO POR MISMO HASH (visible solo cuando hay varios PDFs idénticos con distinto registro) -->
            <div id="barraGrupoHash" class="hidden flex flex-wrap items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-700 text-xs">
                <span class="font-bold text-indigo-800 dark:text-indigo-200 flex items-center gap-1.5">
                    <i class="fa-solid fa-layer-group"></i>
                    <span id="lblTituloGrupoHash">Mismo PDF (0 registros):</span>
                </span>
                <div id="contenedorBotonesHash" class="flex flex-wrap items-center gap-1.5"></div>
            </div>

            <!-- FILA 2: Solo los 9 Inputs de la Regla de Sellos (Form1.vb: Del, Secc, Libro, Tomo, Vol, Fracc, Año, Inscr, Bis) -->
            <div class="flex flex-wrap lg:flex-nowrap items-end gap-3">
                <div class="grid grid-cols-9 gap-2 flex-1 bg-white dark:bg-gray-900 p-2.5 rounded-xl border border-gray-300 dark:border-gray-700 shadow-sm">
                    <div>
                        <label for="selloDel" class="block text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-1 text-center">1. Del.</label>
                        <input type="text" id="selloDel" data-idx="0" maxlength="2" value="" autocomplete="off"
                            class="sello-seg w-full px-1.5 py-2 text-center font-mono font-bold text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand focus:bg-white dark:focus:bg-gray-700 focus:outline-none">
                    </div>
                    <div>
                        <label for="selloSecc" class="block text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-1 text-center">2. Secc.</label>
                        <input type="text" id="selloSecc" data-idx="1" maxlength="3" value="" autocomplete="off"
                            class="sello-seg w-full px-1.5 py-2 text-center font-mono font-bold text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand focus:bg-white dark:focus:bg-gray-700 focus:outline-none">
                    </div>
                    <div>
                        <label for="selloLibro" class="block text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-1 text-center">3. Libro</label>
                        <input type="text" id="selloLibro" data-idx="2" maxlength="6" value="" autocomplete="off"
                            class="sello-seg w-full px-1.5 py-2 text-center font-mono font-bold text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand focus:bg-white dark:focus:bg-gray-700 focus:outline-none">
                    </div>
                    <div class="relative">
                        <div id="menuSelloTomo" class="hidden absolute bottom-full mb-1.5 left-0 z-40 min-w-[240px] max-h-56 overflow-y-auto rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-xl py-1 text-left"></div>
                        <div class="flex items-center justify-center gap-1 mb-1">
                            <label for="selloTomo" class="text-[11px] font-bold text-gray-600 dark:text-gray-300" title="Escribe para filtrar, ↑/↓ para elegir, Tab/Enter para seleccionar o F2 para ver todo">4. Tomo</label>
                            <button type="button" id="btnToggleMenuTomo" tabindex="-1" title="Ver catálogo de Tomos (F2)" class="text-[10px] px-1 rounded bg-sky-100 dark:bg-sky-900/60 text-sky-700 dark:text-sky-300 hover:bg-sky-200">▾</button>
                        </div>
                        <input type="text" id="selloTomo" data-idx="3" maxlength="30" value="" autocomplete="off"
                            class="sello-seg w-full px-1.5 py-2 text-center font-mono font-bold text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand focus:bg-white dark:focus:bg-gray-700 focus:outline-none">
                    </div>
                    <div>
                        <label for="selloVol" class="block text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-1 text-center">5. Vol.</label>
                        <input type="text" id="selloVol" data-idx="4" maxlength="3" value="" autocomplete="off"
                            class="sello-seg w-full px-1.5 py-2 text-center font-mono font-bold text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand focus:bg-white dark:focus:bg-gray-700 focus:outline-none">
                    </div>
                    <div class="relative">
                        <div id="menuSelloFracc" class="hidden absolute bottom-full mb-1.5 left-0 z-40 min-w-[260px] max-h-56 overflow-y-auto rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-xl py-1 text-left"></div>
                        <div class="flex items-center justify-center gap-1 mb-1">
                            <label for="selloFracc" class="text-[11px] font-bold text-gray-600 dark:text-gray-300" title="Escribe para filtrar, ↑/↓ para elegir, Tab/Enter para seleccionar o F2 para ver todo">6. Fracc.</label>
                            <button type="button" id="btnToggleMenuFracc" tabindex="-1" title="Ver catálogo de Fraccionamiento (F2)" class="text-[10px] px-1 rounded bg-sky-100 dark:bg-sky-900/60 text-sky-700 dark:text-sky-300 hover:bg-sky-200">▾</button>
                        </div>
                        <input type="text" id="selloFracc" data-idx="5" maxlength="55" value="" autocomplete="off"
                            class="sello-seg w-full px-1.5 py-2 text-center font-mono font-bold text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand focus:bg-white dark:focus:bg-gray-700 focus:outline-none">
                    </div>
                    <div>
                        <label for="selloAnio" class="block text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-1 text-center">7. Año</label>
                        <input type="text" id="selloAnio" data-idx="6" maxlength="4" value="" autocomplete="off"
                            class="sello-seg w-full px-1.5 py-2 text-center font-mono font-bold text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand focus:bg-white dark:focus:bg-gray-700 focus:outline-none">
                    </div>
                    <div>
                        <label for="selloInscr" class="block text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-1 text-center">8. Inscr.</label>
                        <input type="text" id="selloInscr" data-idx="7" maxlength="4" value="" autocomplete="off"
                            class="sello-seg w-full px-1.5 py-2 text-center font-mono font-bold text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand focus:bg-white dark:focus:bg-gray-700 focus:outline-none">
                    </div>
                    <div>
                        <label for="selloBis" class="block text-[11px] font-bold text-gray-600 dark:text-gray-300 mb-1 text-center">9. Bis</label>
                        <input type="text" id="selloBis" data-idx="8" maxlength="3" value="" autocomplete="off"
                            class="sello-seg w-full px-1.5 py-2 text-center font-mono font-bold text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:border-brand focus:bg-white dark:focus:bg-gray-700 focus:outline-none">
                    </div>
                </div>

                <!-- Botones de Acción Principales -->
                <div class="flex items-center gap-2 shrink-0 pb-1">
                    <button id="btnRenombrar"
                        class="px-5 py-2.5 text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white disabled:opacity-40 disabled:cursor-not-allowed rounded-lg shadow transition-colors">
                        <i class="fa-solid fa-check mr-1"></i> Renombrar
                    </button>
                    {{-- Botón Duplicar comentado temporalmente
                    <button id="btnDuplicar"
                        class="px-4 py-2.5 text-sm font-bold bg-sky-600 hover:bg-sky-700 text-white disabled:opacity-40 disabled:cursor-not-allowed rounded-lg shadow transition-colors">
                        <i class="fa-solid fa-copy mr-1"></i> Duplicar
                    </button>
                    --}}
                    <button id="btnInconsistencia"
                        class="px-4 py-2.5 text-sm font-bold bg-amber-400 hover:bg-amber-500 text-black rounded-lg border border-amber-500 shadow transition-colors">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Inconsistencia
                    </button>
                    <button id="btnLimpiar" title="Limpiar campos"
                        class="px-3 py-2.5 text-sm font-semibold bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg border border-gray-300 dark:border-gray-600 transition-colors">
                        <i class="fa-solid fa-eraser"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Autorización en Tiempo Real (Reemplaza a PIN Maestro) -->
    <div id="modalPinMaestro" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
        <div
            class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-xl shadow-2xl w-[90%] max-w-[420px] p-6 border border-gray-300 dark:border-gray-700">
            <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                <i class="fa-solid fa-shield-halved text-brand text-2xl"></i>
                <div>
                    <h3 class="font-bold text-lg leading-tight">Autorización en Tiempo Real</h3>
                    <span class="text-xs text-gray-500">Supervisión en Vivo (WebSockets)</span>
                </div>
            </div>
            <div id="panelFormularioPeticion">
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                    Describa brevemente el motivo para enviar la petición de autorización al supervisor en tiempo real:
                </p>
                <input type="text" id="txtPinMaestro" placeholder="Motivo (ej. PDF ilegible / Inconsistencia)..." autocomplete="off"
                    class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded focus:outline-none focus:border-brand mb-4 text-gray-900 dark:text-white">
            </div>
            <div id="panelEsperandoResolucion" class="hidden text-center py-4">
                <i class="fa-solid fa-satellite-dish fa-beat-fade text-3xl text-amber-500 mb-3 block"></i>
                <p class="font-bold text-base text-gray-800 dark:text-gray-100" id="lblEstadoPeticionVivo">
                    Esperando autorización del supervisor...
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    La acción continuará automáticamente apenas sea autorizada.
                </p>
            </div>
            <p id="lblPinError" class="text-sm text-red-500 font-semibold mb-4 text-center hidden"></p>
            <div class="flex justify-end gap-2">
                <button id="btnPinCancelar"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded font-semibold transition-colors">
                    Cancelar
                </button>
                <button id="btnPinAceptar"
                    class="px-4 py-2 bg-brand text-white hover:bg-brand/90 rounded font-semibold transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Solicitar Autorización
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Confirmación y Alertas Genérico -->
    <div id="modalConfirmacion" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
        <div
            class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg shadow-xl w-[90%] max-w-[380px] p-5 border border-gray-300 dark:border-gray-700">
            <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                <i id="iconConfirmacion" class="fa-solid fa-circle-question text-brand text-2xl"></i>
                <h3 id="lblConfirmacionTitulo" class="font-bold text-lg text-gray-800 dark:text-gray-200">Confirmar</h3>
            </div>
            <p id="lblConfirmacionMensaje" class="text-sm text-gray-600 dark:text-gray-400 mb-5">
                ¿Está seguro de realizar esta acción?
            </p>
            <div class="flex justify-end gap-2">
                <button id="btnConfirmarCancelar"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded font-semibold text-gray-700 dark:text-gray-300 transition-colors">
                    Cancelar
                </button>
                <button id="btnConfirmarAceptar"
                    class="px-4 py-2 bg-brand text-white hover:bg-brand/90 rounded font-semibold transition-colors">
                    Aceptar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Procesando (Spinner de carga bloqueante) -->
    <div id="modalProcesando" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
        <div
            class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg shadow-xl w-[90%] max-w-[380px] p-5 border border-gray-300 dark:border-gray-700">
            <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                <i class="fa-solid fa-spinner fa-spin text-brand text-2xl"></i>
                <h3 id="lblProcesandoTitulo" class="font-bold text-lg text-gray-800 dark:text-gray-200">Procesando...</h3>
            </div>
            <p id="lblProcesandoMensaje" class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                Por favor espere un momento.
            </p>
        </div>
    </div>

    <!-- Modal Selección de Sesión (Directorio y Delegación) -->
    <div id="modalSeleccionSesion" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
        <div
            class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg shadow-xl w-[90%] max-w-[700px] p-6 max-h-[90vh] flex flex-col border border-gray-300 dark:border-gray-700">
            <!-- Cabecera -->
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                <div>
                    <h3 class="font-bold text-xl flex items-center gap-2">
                        <i class="fa-solid fa-folder-tree text-amber-500"></i> Selección de Directorio de Trabajo
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="lblUsuarioSesion">
                        Usuario: Administrador
                    </p>
                </div>
                <button id="btnSesionRefrescar" title="Refrescar Directorios"
                    class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors text-gray-600 dark:text-gray-300">
                    <i class="fa-solid fa-arrows-rotate text-lg"></i>
                </button>
            </div>

            <!-- Contenido dividido en tres paneles: Directorios/Entregas, Delegaciones y Filtro de Municipio/Localidad -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 flex-grow overflow-y-auto mb-4 min-h-[300px]">
                <!-- Panel 1: Directorios / Rutas PDF Configuradas -->
                <div
                    class="flex flex-col border border-gray-200 dark:border-gray-700 rounded p-3 bg-gray-50 dark:bg-gray-900/30">
                    <h4 class="font-bold text-xs text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wider">
                        1. Rutas y Entregas PDF (Stellum)
                    </h4>
                    <div id="lstDirectorios"
                        class="flex-grow overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700 max-h-[260px]">
                        <!-- Lista de directorios inyectada por JS -->
                    </div>
                </div>

                <!-- Panel 2: Delegaciones -->
                <div
                    class="flex flex-col border border-gray-200 dark:border-gray-700 rounded p-3 bg-gray-50 dark:bg-gray-900/30">
                    <h4 class="font-bold text-xs text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wider">
                        2. Delegaciones Disponibles
                    </h4>
                    <div id="lstDelegaciones"
                        class="flex-grow overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700 max-h-[260px]">
                        <!-- Lista de delegaciones inyectada por JS -->
                    </div>
                </div>

                <!-- Panel 3: Catálogo de Municipios y Localidades (Seeders) -->
                <div
                    class="flex flex-col border border-gray-200 dark:border-gray-700 rounded p-3 bg-gray-50 dark:bg-gray-900/30">
                    <h4 class="font-bold text-xs text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wider">
                        3. Catálogo Municipio / Localidad
                    </h4>
                    <select id="selMunicipioFiltro" class="w-full px-2.5 py-1.5 mb-2 text-xs rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                        <option value="">-- Seleccione Municipio (001-125) --</option>
                    </select>
                    <input type="text" id="txtBuscarLocalidad" placeholder="Filtrar localidad o clave..."
                        class="w-full px-2.5 py-1.5 mb-2 text-xs rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800">
                    <div id="lstLocalidades"
                        class="flex-grow overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700 max-h-[175px] text-xs">
                        <div class="text-gray-400 p-3 text-center">Seleccione un municipio para ver sus localidades.</div>
                    </div>
                </div>
            </div>

            <!-- Footer: Estado de conexión y acciones -->
            <div
                class="border-t border-gray-200 dark:border-gray-700 pt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div id="lblEstadoConexion" class="text-sm font-semibold text-gray-500">
                    Seleccione un directorio de trabajo.
                </div>
                <div class="flex gap-2 w-full sm:w-auto justify-end">
                    <button id="btnSesionCancelar"
                        class="px-5 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 rounded font-semibold transition-colors w-full sm:w-auto">
                        Cancelar
                    </button>
                    <button id="btnComenzar" disabled
                        class="px-5 py-2 bg-brand text-white hover:bg-brand/90 disabled:opacity-50 disabled:cursor-not-allowed rounded font-semibold transition-colors w-full sm:w-auto flex items-center justify-center gap-2">
                        <i class="fa-solid fa-play"></i> Comenzar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Input selector de archivo oculto -->
    <input type="file" id="fileSelector" accept="application/pdf" class="hidden">

    <script>
        // ==============================
        // CATÁLOGOS INYECTADOS DESDE LARAVEL
        // ==============================
        const catalogoAnios = @json($anios).map(String);
        const catalogoDelegaciones = @json($delegaciones);
        const catalogoMunicipios = @json($municipios ?? []);
        const usuarioNombre = "{{ Auth::user()->name ?? 'Administrador' }}";
        const usuarioId = {{ Auth::id() ?? 'null' }};

        // Configuración de Directorios y Entregas de Trabajo (desde config/captura_pdf.php)
        const directoriosPorDefecto = @json($directoriosPorDefecto ?? []);

        let directoriosConfig = directoriosPorDefecto.length > 0 ? directoriosPorDefecto : (JSON.parse(localStorage.getItem('val_directorios_config')) || []);

        // Inicializar selector de Municipios y Localidades
        document.addEventListener('DOMContentLoaded', () => {
            const selMun = document.getElementById('selMunicipioFiltro');
            const txtLoc = document.getElementById('txtBuscarLocalidad');
            const lstLoc = document.getElementById('lstLocalidades');

            if (selMun && catalogoMunicipios.length > 0) {
                catalogoMunicipios.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = `${m.clave} - ${m.nombre}`;
                    selMun.appendChild(opt);
                });

                async function cargarLocalidades() {
                    const munId = selMun.value;
                    const q = txtLoc ? txtLoc.value.trim() : '';
                    if (!munId && !q) {
                        lstLoc.innerHTML = '<div class="text-gray-400 p-3 text-center">Seleccione un municipio para ver sus localidades.</div>';
                        return;
                    }
                    lstLoc.innerHTML = '<div class="text-blue-500 p-3 text-center"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Cargando localidades...</div>';
                    try {
                        const res = await fetch(`/captura/localidades?municipio_id=${encodeURIComponent(munId)}&q=${encodeURIComponent(q)}`);
                        const data = await res.json();
                        if (!data.localidades || data.localidades.length === 0) {
                            lstLoc.innerHTML = '<div class="text-gray-400 p-3 text-center">Sin localidades encontradas.</div>';
                            return;
                        }
                        lstLoc.innerHTML = data.localidades.map(l => `
                            <div class="px-2.5 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 flex justify-between items-center">
                                <span class="font-medium text-gray-800 dark:text-gray-200">${l.nombre}</span>
                                <span class="px-1.5 py-0.5 bg-sky-100 dark:bg-sky-900 text-sky-800 dark:text-sky-200 rounded font-mono text-[11px]">${l.clave}</span>
                            </div>
                        `).join('');
                    } catch (e) {
                        lstLoc.innerHTML = '<div class="text-red-500 p-3 text-center">Error al consultar localidades.</div>';
                    }
                }

                selMun.addEventListener('change', cargarLocalidades);
                if (txtLoc) txtLoc.addEventListener('input', cargarLocalidades);
            }
        });
        let dirSeleccionado = null;
        let delSeleccionada = null;
        let tempDirSeleccionado = null;
        let tempDelSeleccionada = null;

        // ==============================
        // ESTADO DE SESIÓN DE TRABAJO (conectado al servidor)
        // ==============================
        let loteActualId = null;       // ID en tabla lotes_captura
        let loteActualNombre = '';     // Nombre del lote (ej. "Lote 1")
        let loteRutaRelativa = '';     // Ruta relativa dentro del disco
        let listaPdfs = [];            // Lista de archivos [{ruta, nombre}]
        let indiceActual = 0;          // Índice del PDF actual en la lista
        let historialCapturas = [];    // Historial de nombres capturados para doble confirmación
        let accionEnEspera = '';       // "Renombrar" o "Duplicar" (para confirmar)
        let pdfFueRenombrado = false;  // Indica si el PDF actual fue renombrado

        // Elementos del DOM
        const btnModo = document.getElementById('btnModo');
        const htmlEl = document.documentElement;
        const btnPantallaCompleta = document.getElementById('btnPantallaCompleta');
        const rbRegla1 = document.getElementById('rbRegla1');
        const rbRegla2 = document.getElementById('rbRegla2');
        const lblEstado = document.getElementById('lblEstado');
        const txtNombre = document.getElementById('txtNombre');
        const btnConfig = document.getElementById('btnConfig');
        const btnInconsistencia = document.getElementById('btnInconsistencia');
        const lblTotalPdf = document.getElementById('lblTotalPdf');

        // Modales
        const modalPinMaestro = document.getElementById('modalPinMaestro');
        const txtPinMaestro = document.getElementById('txtPinMaestro');
        const btnPinCancelar = document.getElementById('btnPinCancelar');
        const btnPinAceptar = document.getElementById('btnPinAceptar');

        const modalSeleccionSesion = document.getElementById('modalSeleccionSesion');
        const lblUsuarioSesion = document.getElementById('lblUsuarioSesion');
        const btnSesionRefrescar = document.getElementById('btnSesionRefrescar');
        const btnSesionCancelar = document.getElementById('btnSesionCancelar');
        const btnComenzar = document.getElementById('btnComenzar');

        // ==================================================
        // LOGICA DE AUTORIZACIÓN EN TIEMPO REAL (SIN PIN)
        // ==================================================
        let pinResolve = null;
        let pollPeticionTimer = null;

        function limpiarTimerPeticion() {
            if (pollPeticionTimer) {
                clearInterval(pollPeticionTimer);
                pollPeticionTimer = null;
            }
        }

        function solicitarPinMaestro(tipoPeticion = 'Inconsistencia') {
            return new Promise((resolve) => {
                pinResolve = resolve;
                limpiarTimerPeticion();
                document.getElementById('panelFormularioPeticion').classList.remove('hidden');
                document.getElementById('panelEsperandoResolucion').classList.add('hidden');
                document.getElementById('lblPinError').classList.add('hidden');
                btnPinAceptar.classList.remove('hidden');
                btnPinAceptar.disabled = false;
                txtPinMaestro.value = '';
                modalPinMaestro.classList.remove('hidden');
                txtPinMaestro.focus();
            });
        }

        async function procesarPin() {
            const pdfActual = (listaPdfs && listaPdfs[indiceActual]) ? listaPdfs[indiceActual] : null;
            const motivo = txtPinMaestro.value.trim() || 'Inconsistencia detectada en estación de captura';

            btnPinAceptar.disabled = true;
            document.getElementById('panelFormularioPeticion').classList.add('hidden');
            document.getElementById('panelEsperandoResolucion').classList.remove('hidden');
            btnPinAceptar.classList.add('hidden');

            try {
                const resp = await fetch('/autorizaciones/solicitar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        tipo_peticion: 'Inconsistencia',
                        accion_solicitada: 'Mover archivo a Inconsistencias',
                        disco: dirSeleccionado ? dirSeleccionado.id : 'irec',
                        lote: loteActualNombre || 'Lote',
                        archivo_nombre: pdfActual ? (pdfActual.nombre + '.pdf') : '-',
                        ruta_origen: pdfActual ? pdfActual.ruta : null,
                        motivo: motivo
                    })
                });
                const data = await resp.json();
                if (!data.ok) throw new Error(data.mensaje || 'Error al enviar solicitud');

                const peticionId = data.peticion_id;
                document.getElementById('lblEstadoPeticionVivo').innerText = `Petición #${peticionId} enviada. Esperando al supervisor...`;

                pollPeticionTimer = setInterval(async () => {
                    try {
                        const rEst = await fetch(`/autorizaciones/${peticionId}/estado`);
                        const dEst = await rEst.json();
                        if (dEst.estado === 'autorizada') {
                            limpiarTimerPeticion();
                            modalPinMaestro.classList.add('hidden');
                            if (pinResolve) pinResolve(true);
                        } else if (dEst.estado === 'rechazada') {
                            limpiarTimerPeticion();
                            const errEl = document.getElementById('lblPinError');
                            errEl.innerText = `Solicitud rechazada por ${dEst.supervisor || 'Supervisor'}: ${dEst.comentario_resolucion || ''}`;
                            errEl.classList.remove('hidden');
                            setTimeout(() => {
                                modalPinMaestro.classList.add('hidden');
                                if (pinResolve) pinResolve(false);
                            }, 2000);
                        }
                    } catch (e) {}
                }, 2000);
            } catch (err) {
                const errEl = document.getElementById('lblPinError');
                errEl.innerText = err.message;
                errEl.classList.remove('hidden');
                btnPinAceptar.disabled = false;
                btnPinAceptar.classList.remove('hidden');
            }
        }

        function cancelarPin() {
            limpiarTimerPeticion();
            modalPinMaestro.classList.add('hidden');
            if (pinResolve) pinResolve(false);
        }

        btnPinAceptar.addEventListener('click', procesarPin);
        txtPinMaestro.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                procesarPin();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancelarPin();
            }
        });
        btnPinCancelar.addEventListener('click', cancelarPin);

        // ==================================================
        // LOGICA CONFIRMACIÓN Y ALERTAS
        // ==================================================
        let confirmacionResolve = null;

        function solicitarConfirmacion(titulo, mensaje, icono = 'fa-circle-question', colorIcono = 'text-brand', mostrarCancelar = true) {
            return new Promise((resolve) => {
                confirmacionResolve = resolve;
                document.getElementById('lblConfirmacionTitulo').innerText = titulo;
                document.getElementById('lblConfirmacionMensaje').innerText = mensaje;

                const iconoEl = document.getElementById('iconConfirmacion');
                iconoEl.className = `fa-solid ${icono} ${colorIcono} text-2xl`;

                const btnCancelar = document.getElementById('btnConfirmarCancelar');
                if (mostrarCancelar) {
                    btnCancelar.classList.remove('hidden');
                } else {
                    btnCancelar.classList.add('hidden');
                }

                document.getElementById('modalConfirmacion').classList.remove('hidden');
            });
        }

        document.getElementById('btnConfirmarAceptar').addEventListener('click', () => {
            document.getElementById('modalConfirmacion').classList.add('hidden');
            if (confirmacionResolve) confirmacionResolve(true);
        });

        document.getElementById('btnConfirmarCancelar').addEventListener('click', () => {
            document.getElementById('modalConfirmacion').classList.add('hidden');
            if (confirmacionResolve) confirmacionResolve(false);
        });

        // ==================================================
        // SELECCIÓN DE SESIÓN
        // ==================================================
         function abrirSeleccionSesion() {
             lblUsuarioSesion.innerText = "Usuario: " + usuarioNombre;
             tempDirSeleccionado = null;
             tempDelSeleccionada = null;
             btnComenzar.disabled = true;
             document.getElementById('lblEstadoConexion').innerText = "Seleccione un directorio de trabajo.";
             document.getElementById('lblEstadoConexion').className = "text-sm font-semibold text-gray-500";
 
             renderDirectorios();
             document.getElementById('lstDelegaciones').innerHTML = '<div class="text-gray-400 text-sm p-4 text-center">Seleccione un directorio primero.</div>';
 
             modalSeleccionSesion.classList.remove('hidden');
         }

        /**
         * Renderiza dinámicamente la lista de directorios configurados en el panel izquierdo
         * del modal de selección de sesión de trabajo.
         */
        function renderDirectorios() {
            const lst = document.getElementById('lstDirectorios');
            lst.innerHTML = '';
            directoriosConfig.forEach(dir => {
                const btn = document.createElement('button');
                btn.className = "w-full text-left px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors flex items-center justify-between group rounded border-b border-gray-100 dark:border-gray-800";
                btn.innerHTML = `
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-folder text-amber-500 text-lg"></i>
                        <div>
                            <span class="font-bold block text-sm text-gray-800 dark:text-gray-200">${dir.nombre}</span>
                            <span class="text-xs text-gray-500 font-mono">${dir.ruta}</span>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-300 group-hover:text-gray-500 transition-colors"></i>
                `;
                btn.addEventListener('click', () => selectDirectorio(dir, btn));
                lst.appendChild(btn);
            });
        }

        /**
         * Maneja la selección de un directorio de trabajo específico.
         * Restablece la delegación activa y actualiza el estado de la conexión.
         * 
         * @param {object} dir - El directorio seleccionado.
         * @param {HTMLElement} element - El botón HTML clickeado para aplicarle estilos de enfoque.
         */
        function selectDirectorio(dir, element) {
            document.querySelectorAll('#lstDirectorios button').forEach(el => el.classList.remove('bg-brand/10', 'border-l-4', 'border-brand'));
            element.classList.add('bg-brand/10', 'border-l-4', 'border-brand');

            tempDirSeleccionado = dir;
            tempDelSeleccionada = null;
            btnComenzar.disabled = true;

            const lblConexion = document.getElementById('lblEstadoConexion');
            lblConexion.innerText = "Seleccione una delegación.";
            lblConexion.className = "text-sm font-semibold text-gray-500";

            renderDelegaciones();
        }

        /**
         * Renderiza la lista de delegaciones disponibles en el panel derecho del modal
         * una vez que se ha seleccionado un directorio de trabajo principal.
         */
        function renderDelegaciones() {
            const lst = document.getElementById('lstDelegaciones');
            lst.innerHTML = '';
            catalogoDelegaciones.forEach(del => {
                const btn = document.createElement('button');
                btn.className = "w-full text-left px-4 py-2.5 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors flex items-center justify-between group rounded border-b border-gray-100 dark:border-gray-800";
                btn.innerHTML = `
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-building text-sky-500"></i>
                        <div>
                            <span class="font-semibold block text-sm text-gray-800 dark:text-gray-200">${del.nombre}</span>
                            <span class="text-xs text-gray-500">Clave: ${del.clave}</span>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-gray-300 group-hover:text-gray-500 transition-colors"></i>
                `;
                btn.addEventListener('click', () => selectDelegacion(del, btn));
                lst.appendChild(btn);
            });
        }

        /**
         * Maneja el evento de selección de una delegación.
         * Confirma la conexión al directorio y habilita el botón de inicio de sesión.
         * 
         * @param {object} del - La delegación seleccionada.
         * @param {HTMLElement} element - El botón HTML seleccionado para aplicarle estilos de enfoque.
         */
        function selectDelegacion(del, element) {
            if (!tempDirSeleccionado) return;

            document.querySelectorAll('#lstDelegaciones button').forEach(el => el.classList.remove('bg-brand/10', 'border-l-4', 'border-brand'));
            element.classList.add('bg-brand/10', 'border-l-4', 'border-brand');

            tempDelSeleccionada = del;

            // Simulación de verificación de conexión del directorio
            const lblConexion = document.getElementById('lblEstadoConexion');
            lblConexion.innerText = `✔ Conexión exitosa a ${del.nombre}`;
            lblConexion.className = "text-sm font-semibold text-green-600 dark:text-green-400";

            btnComenzar.disabled = false;
        }

        btnComenzar.addEventListener('click', async () => {
            if (!tempDirSeleccionado || !tempDelSeleccionada) return;

            dirSeleccionado = tempDirSeleccionado;
            delSeleccionada = tempDelSeleccionada;

            // Guardar la configuración de sesión en localStorage
            localStorage.setItem('val_ultimo_directorio_id', dirSeleccionado.id);
            localStorage.setItem('val_ultima_delegacion_id', delSeleccionada.id);
            localStorage.setItem('val_delegacion_nombre', delSeleccionada.nombre);
            localStorage.setItem('val_delegacion_clave', delSeleccionada.clave);
            localStorage.setItem('val_directorio_nombre', dirSeleccionado.nombre);

            aplicarReglasDeDirectorio(dirSeleccionado);
            modalSeleccionSesion.classList.add('hidden');

            // Activar pantalla completa (requiere gesto de usuario)
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().then(() => {
                    const btnPC = document.getElementById('btnPantallaCompleta');
                    if (btnPC) btnPC.innerHTML = '<i class="fa-solid fa-compress"></i> Ventana';
                }).catch(() => {});
            }

            // Buscar lote libre en el servidor
            await cargarLoteDesdeServidor();
        });

        // ==================================================
        // CONTEXTO DE EJECUCIÓN PARA PROCESOS EXTERNOS
        // ==================================================
        const ctx = {
            get listaPdfs() { return listaPdfs; },
            set listaPdfs(v) { listaPdfs = v; },
            get indiceActual() { return indiceActual; },
            set indiceActual(v) { indiceActual = v; },
            get dirSeleccionado() { return dirSeleccionado; },
            set dirSeleccionado(v) { dirSeleccionado = v; },
            get delSeleccionada() { return delSeleccionada; },
            set delSeleccionada(v) { delSeleccionada = v; },
            get loteActualId() { return loteActualId; },
            set loteActualId(v) { loteActualId = v; },
            get loteActualNombre() { return loteActualNombre; },
            set loteActualNombre(v) { loteActualNombre = v; },
            get loteRutaRelativa() { return loteRutaRelativa; },
            set loteRutaRelativa(v) { loteRutaRelativa = v; },
            get historialCapturas() { return historialCapturas; },
            set historialCapturas(v) { historialCapturas = v; },
            get accionEnEspera() { return accionEnEspera; },
            set accionEnEspera(v) { accionEnEspera = v; },
            get pdfFueRenombrado() { return pdfFueRenombrado; },
            set pdfFueRenombrado(v) { pdfFueRenombrado = v; },

            txtNombre: txtNombre,
            lblEstado: lblEstado,
            lblTotalPdf: lblTotalPdf,
            rbRegla1: rbRegla1,
            rbRegla2: rbRegla2,
            nudPagina: document.getElementById('nudPagina'),
            catalogoAnios: catalogoAnios,

            solicitarPinMaestro: solicitarPinMaestro,
            solicitarConfirmacion: solicitarConfirmacion,
            abrirSeleccionSesion: abrirSeleccionSesion
        };

        // Redirigir la carga inicial de lote desde la sesión
        async function cargarLoteDesdeServidor() {
            await CapturaProcesos.cargarLoteDesdeServidor(ctx);
        }

        // Envoltura de validación para los listeners locales
        function validarInput() {
            return CapturaProcesos.validarInput(ctx);
        }

        btnSesionCancelar.addEventListener('click', () => {
            modalSeleccionSesion.classList.add('hidden');
        });

        btnSesionRefrescar.addEventListener('click', () => {
            tempDirSeleccionado = null;
            tempDelSeleccionada = null;
            btnComenzar.disabled = true;
            document.getElementById('lblEstadoConexion').innerText = "Seleccione un directorio de trabajo.";
            document.getElementById('lblEstadoConexion').className = "text-sm font-semibold text-gray-500";
            renderDirectorios();
            document.getElementById('lstDelegaciones').innerHTML = '<div class="text-gray-400 text-sm p-4 text-center">Seleccione un directorio primero.</div>';
        });

        function aplicarReglasDeDirectorio(dir) {
            // Un-check visual de reglas
            if (rbRegla1) rbRegla1.checked = false;
            if (rbRegla2) rbRegla2.checked = false;

            if (dir && dir.reglas) {
                if (dir.reglas.includes('Regla1')) {
                    if (rbRegla1) {
                        rbRegla1.checked = true;
                        rbRegla1.dispatchEvent(new Event('change'));
                    }
                } else if (dir.reglas.includes('Regla2')) {
                    if (rbRegla2) {
                        rbRegla2.checked = true;
                        rbRegla2.dispatchEvent(new Event('change'));
                    }
                }
            }
        }

        // Elementos del dropdown del engranaje
        const dropdownConfig = document.getElementById('dropdownConfig');
        const btnOptSesion = document.getElementById('btnOptSesion');
        const btnOptReglas = document.getElementById('btnOptReglas');
        const panelReglas = document.getElementById('panelReglas');

        // Toggle del menú flotante de configuración (eliminado para capturistas)
        btnConfig?.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownConfig?.classList.toggle('hidden');
        });

        document.addEventListener('click', () => {
            dropdownConfig?.classList.add('hidden');
        });

        btnOptSesion?.addEventListener('click', () => {
            abrirSeleccionSesion();
        });

        btnOptReglas?.addEventListener('click', () => {
            panelReglas?.classList.toggle('invisible');
            panelReglas?.classList.toggle('pointer-events-none');
        });

        // Registrar triggers de botones y acciones del flujo
        document.getElementById('btnRenombrar')?.addEventListener('click', () => CapturaProcesos.procesarRenombrar(ctx));
        // document.getElementById('btnDuplicar')?.addEventListener('click', () => CapturaProcesos.procesarDuplicar(ctx));
        document.getElementById('btnLimpiar')?.addEventListener('click', () => CapturaProcesos.reiniciarCaptura(ctx));
        document.getElementById('btnRefrescar')?.addEventListener('click', () => {
            if (ctx.listaPdfs && ctx.listaPdfs.length > 0 && ctx.indiceActual >= 0) {
                CapturaProcesos.cargarPdfActual(ctx);
            } else {
                CapturaProcesos.cargarLoteDesdeServidor(ctx);
            }
        });
        document.getElementById('btnCerrarSesion')?.addEventListener('click', () => CapturaProcesos.cerrarSesion(ctx));

        // Botones exclusivos de Administrador / Superadmin (Siguiente PDF y Buscar grupo por mismo Hash)
        async function ejecutarAccionAdminPdf(url, mensajeCarga) {
            const modalProc = document.getElementById('modalProcesando');
            const lblProc = document.getElementById('lblProcesandoMensaje');
            if (modalProc && lblProc) {
                lblProc.innerText = mensajeCarga;
                modalProc.classList.remove('hidden');
            }
            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({})
                });
                const data = await resp.json();
                console.log('[Captura-IREC] Respuesta acción admin:', data);
                if (!resp.ok || !data.ok) {
                    throw new Error(data.mensaje || 'No se pudo obtener el PDF.');
                }
                if (data.delegacion) {
                    ctx.delSeleccionada = data.delegacion;
                }
                ctx.loteActualId = data.lote_id;
                ctx.loteActualNombre = data.lote;
                ctx.listaPdfs = data.archivos || [];
                ctx.indiceActual = 0;
                ctx.lblTotalPdf.innerText = ctx.delSeleccionada ? ctx.delSeleccionada.nombre : 'Delegación';
                CapturaProcesos.reiniciarCaptura(ctx);
                CapturaProcesos.cargarPdfActual(ctx);
            } catch (err) {
                if (modalProc) modalProc.classList.add('hidden');
                ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">${err.message}</span>`;
            }
        }

        document.getElementById('btnAdminSiguiente')?.addEventListener('click', () => {
            ejecutarAccionAdminPdf("{{ route('admin.captura.adminSiguiente') }}", "Buscando siguiente PDF...");
        });
        document.getElementById('btnAdminBuscarHash')?.addEventListener('click', () => {
            ejecutarAccionAdminPdf("{{ route('admin.captura.adminBuscarMismoHash') }}", "Buscando grupo de PDFs con el mismo hash...");
        });

        // Rotación física de página con modal de confirmación y registro en auditoría
        async function confirmarYRotarPagina(grados) {
            const pagInput = document.getElementById('nudPagina');
            const pagina = Math.max(1, parseInt(pagInput?.value || '1', 10) || 1);
            const direccion = grados > 0 ? '90° a la derecha' : '90° a la izquierda';
            const confirmado = await ctx.solicitarConfirmacion(
                'Confirmar rotación física',
                `¿Deseas rotar físicamente la página ${pagina} del PDF (${direccion})? Este cambio modificará el archivo y quedará registrado en la auditoría.`,
                'fa-rotate',
                'text-amber-500'
            );
            if (!confirmado) return;
            CapturaProcesos.rotarPagina(ctx, grados);
        }

        document.getElementById('btnRotarIzquierda')?.addEventListener('click', () => confirmarYRotarPagina(-90));
        document.getElementById('btnRotarDerecha')?.addEventListener('click', () => confirmarYRotarPagina(90));

        // Replicación exacta del comportamiento de Form1.vb para los 9 campos de la Regla de Sellos
        (function initSegmentosSellos() {
            const ids = ['selloDel', 'selloSecc', 'selloLibro', 'selloTomo', 'selloVol', 'selloFracc', 'selloAnio', 'selloInscr', 'selloBis'];
            const campos = ids.map(id => document.getElementById(id)).filter(Boolean);
            const txtNombreEl = document.getElementById('txtNombre');
            const previewEl = document.getElementById('lblPreviewNombreSellos');
            if (!campos.length || !txtNombreEl) return;

            let isDeleting = false;

            function construirNombreSellos() {
                const valores = campos.map(c => (c.value || '').trim().toUpperCase());
                const hayAlguno = valores.some(v => v !== '');
                const nombreArmado = hayAlguno ? valores.join('_') : '';
                txtNombreEl.value = nombreArmado;
                if (previewEl) previewEl.textContent = hayAlguno ? nombreArmado : '—';
                txtNombreEl.dispatchEvent(new Event('input'));
            }

            const menusCatalogo = {
                3: document.getElementById('menuSelloTomo'),
                5: document.getElementById('menuSelloFracc'),
            };
            const estadoMenu = {
                3: { abierto: false, opciones: [], activo: 0 },
                5: { abierto: false, opciones: [], activo: 0 },
            };

            function obtenerListaCatalogo(idx) {
                if (idx === 3) return CapturaReglas.CATALOGO_TOMOS || [];
                if (idx === 5) return CapturaReglas.CATALOGO_FRACCIONAMIENTO || [];
                return [];
            }

            function filtrarOpcionesCatalogo(idx, valor, mostrarTodasSiVacio = false) {
                const lista = obtenerListaCatalogo(idx);
                const v = (valor || '').trim().toUpperCase();
                if (!v || /^\d+$/.test(v)) {
                    return mostrarTodasSiVacio ? lista.slice() : [];
                }
                const mNum = v.match(/^(\d+_)(.*)$/);
                const prefNum = mNum ? mNum[1] : '';
                const base = mNum ? mNum[2] : v;
                if (!base) {
                    return mostrarTodasSiVacio ? lista.map(p => prefNum + p) : [];
                }
                const empieza = lista.filter(p => p.startsWith(base));
                const contiene = lista.filter(p => !p.startsWith(base) && p.includes(base));
                return [...empieza, ...contiene].map(p => prefNum + p);
            }

            function cerrarMenuCatalogo(idx) {
                const menu = menusCatalogo[idx];
                const est = estadoMenu[idx];
                if (!menu || !est) return;
                est.abierto = false;
                est.opciones = [];
                est.activo = 0;
                menu.classList.add('hidden');
                menu.innerHTML = '';
            }

            function aplicarOpcionCatalogo(idx, opcion) {
                const campo = campos[idx];
                if (!campo || !opcion) return;
                const maxLen = parseInt(campo.getAttribute('maxlength') || '55', 10);
                campo.value = opcion.slice(0, maxLen);
                cerrarMenuCatalogo(idx);
                construirNombreSellos();
                campos[idx + 1]?.focus();
                campos[idx + 1]?.select();
            }

            function renderMenuCatalogo(idx, opciones) {
                const menu = menusCatalogo[idx];
                const est = estadoMenu[idx];
                if (!menu || !est) return;
                if (!opciones || opciones.length === 0) {
                    cerrarMenuCatalogo(idx);
                    return;
                }
                est.abierto = true;
                est.opciones = opciones;
                if (est.activo >= opciones.length || est.activo < 0) est.activo = 0;

                menu.innerHTML = '';
                const header = document.createElement('div');
                header.className = 'px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700 flex justify-between';
                header.innerHTML = `<span>${idx === 3 ? 'Catálogo Tomo' : 'Catálogo Fracc.'}</span><span>↑↓ · Tab/Enter</span>`;
                menu.appendChild(header);

                opciones.forEach((op, i) => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.dataset.optIdx = String(i);
                    const activo = i === est.activo;
                    item.className = 'w-full text-left px-2.5 py-1.5 text-xs font-mono font-bold transition-colors flex items-center justify-between ' +
                        (activo
                            ? 'bg-sky-600 text-white'
                            : 'text-gray-800 dark:text-gray-100 hover:bg-sky-50 dark:hover:bg-gray-700');
                    item.innerHTML = `<span>${op}</span>${activo ? '<span class="text-[10px] opacity-85 ml-2">Tab ↵</span>' : ''}`;
                    item.addEventListener('mousedown', (ev) => {
                        ev.preventDefault();
                        aplicarOpcionCatalogo(idx, op);
                    });
                    item.addEventListener('mouseenter', () => {
                        est.activo = i;
                        actualizarResaltadoMenu(idx);
                    });
                    menu.appendChild(item);
                });

                menu.classList.remove('hidden');
                const btnActivo = menu.querySelector(`button[data-opt-idx="${est.activo}"]`);
                btnActivo?.scrollIntoView({ block: 'nearest' });
            }

            function actualizarResaltadoMenu(idx) {
                const menu = menusCatalogo[idx];
                const est = estadoMenu[idx];
                if (!menu || !est || !est.abierto) return;
                menu.querySelectorAll('button[data-opt-idx]').forEach((btn) => {
                    const i = parseInt(btn.dataset.optIdx, 10);
                    const activo = i === est.activo;
                    btn.className = 'w-full text-left px-2.5 py-1.5 text-xs font-mono font-bold transition-colors flex items-center justify-between ' +
                        (activo
                            ? 'bg-sky-600 text-white'
                            : 'text-gray-800 dark:text-gray-100 hover:bg-sky-50 dark:hover:bg-gray-700');
                    btn.innerHTML = `<span>${est.opciones[i]}</span>${activo ? '<span class="text-[10px] opacity-85 ml-2">Tab ↵</span>' : ''}`;
                    if (activo) btn.scrollIntoView({ block: 'nearest' });
                });
            }

            function actualizarMenuCatalogo(idx, valor, mostrarTodasSiVacio = false) {
                if (idx !== 3 && idx !== 5) return;
                const opciones = filtrarOpcionesCatalogo(idx, valor, mostrarTodasSiVacio);
                estadoMenu[idx].activo = 0;
                renderMenuCatalogo(idx, opciones);
            }

            document.getElementById('btnToggleMenuTomo')?.addEventListener('mousedown', (e) => {
                e.preventDefault();
                if (estadoMenu[3].abierto) {
                    cerrarMenuCatalogo(3);
                } else {
                    campos[3]?.focus();
                    actualizarMenuCatalogo(3, campos[3]?.value || '', true);
                }
            });
            document.getElementById('btnToggleMenuFracc')?.addEventListener('mousedown', (e) => {
                e.preventDefault();
                if (estadoMenu[5].abierto) {
                    cerrarMenuCatalogo(5);
                } else {
                    campos[5]?.focus();
                    actualizarMenuCatalogo(5, campos[5]?.value || '', true);
                }
            });

            let mantenerCursorAlFinal = false;

            campos.forEach((campo, idx) => {
                campo.addEventListener('focus', () => {
                    if (mantenerCursorAlFinal) {
                        mantenerCursorAlFinal = false;
                        const len = campo.value.length;
                        campo.setSelectionRange(len, len);
                    } else {
                        campo.select();
                    }
                });

                // Filtrado interactivo de teclas idéntico a Form1.vb (TextBoxDinamico_KeyPress)
                campo.addEventListener('beforeinput', (e) => {
                    if (!e.data || e.inputType.startsWith('delete')) return;
                    let ch = CapturaReglas.sanitizarTexto(e.data);
                    if (ch === '') { e.preventDefault(); return; }

                    const esTomoOFracc = (idx === 3 || idx === 5);
                    const esBis = (idx === 8);

                    if (esBis) {
                        const start = campo.selectionStart ?? campo.value.length;
                        const end = campo.selectionEnd ?? campo.value.length;
                        const proyectado = (campo.value.slice(0, start) + ch + campo.value.slice(end)).toUpperCase();
                        if (!'BIS'.startsWith(proyectado) && !/^\d{1,2}$/.test(proyectado)) {
                            e.preventDefault();
                        }
                    } else if (esTomoOFracc) {
                        const chNorm = ch.replace(/\s+/g, '_');
                        if (!/^[A-ZÑ0-9_]+$/.test(chNorm)) {
                            e.preventDefault();
                            return;
                        }
                        if (idx === 3) {
                            const start = campo.selectionStart ?? campo.value.length;
                            const end = campo.selectionEnd ?? campo.value.length;
                            const proyectado = (campo.value.slice(0, start) + chNorm + campo.value.slice(end)).toUpperCase();
                            const base = CapturaReglas.extraerTextoBaseTomo(proyectado);
                            if (base.startsWith('F')) {
                                e.preventDefault();
                                return;
                            }
                        }
                    } else {
                        // Bloques numéricos estándar (Del, Secc, Libro, Vol, Año, Inscr)
                        if (!/^\d+$/.test(ch)) {
                            e.preventDefault();
                        }
                    }
                });

                campo.addEventListener('blur', () => {
                    if (idx === 3 || idx === 5) cerrarMenuCatalogo(idx);
                });

                campo.addEventListener('input', () => {
                    let limpio = CapturaReglas.sanitizarTexto(campo.value);
                    if (idx === 3 || idx === 5) {
                        limpio = limpio.replace(/\s+/g, '_');
                        actualizarMenuCatalogo(idx, limpio, false);
                    }
                    if (campo.value !== limpio) campo.value = limpio;

                    construirNombreSellos();

                    // Saltos automáticos idénticos a Form1.vb línea 5213
                    const val = campo.value;
                    const maxLen = parseInt(campo.getAttribute('maxlength') || '0', 10);
                    const esSaltoDosDigitos = !isDeleting && (
                        ((idx === 1 || idx === 3 || idx === 5) && /^\d{2}$/.test(val)) ||
                        ((idx === 2 || idx === 4) && /^0\d$/.test(val))
                    );

                    if (((maxLen > 0 && val.length >= maxLen) || esSaltoDosDigitos) && idx < campos.length - 1) {
                        if (idx === 3 || idx === 5) cerrarMenuCatalogo(idx);
                        campos[idx + 1].focus();
                        campos[idx + 1].select();
                    }
                });

                campo.addEventListener('keydown', (e) => {
                    isDeleting = (e.key === 'Backspace' || e.key === 'Delete');

                    // Manejo interactivo del menú desplegable de catálogo en Tomo (idx 3) y Fraccionamiento (idx 5)
                    if (idx === 3 || idx === 5) {
                        const est = estadoMenu[idx];

                        if (e.key === 'F2' || e.key === 'F4') {
                            e.preventDefault();
                            e.stopPropagation();
                            if (est.abierto) cerrarMenuCatalogo(idx);
                            else actualizarMenuCatalogo(idx, campo.value, true);
                            return;
                        }

                        if (est.abierto && est.opciones.length > 0) {
                            if (e.key === 'ArrowDown') {
                                e.preventDefault();
                                e.stopPropagation();
                                est.activo = (est.activo + 1) % est.opciones.length;
                                actualizarResaltadoMenu(idx);
                                return;
                            }
                            if (e.key === 'ArrowUp') {
                                e.preventDefault();
                                e.stopPropagation();
                                est.activo = (est.activo - 1 + est.opciones.length) % est.opciones.length;
                                actualizarResaltadoMenu(idx);
                                return;
                            }
                            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                                e.preventDefault();
                                e.stopPropagation();
                                aplicarOpcionCatalogo(idx, est.opciones[est.activo]);
                                return;
                            }
                            if (e.key === 'Escape') {
                                e.preventDefault();
                                e.stopPropagation();
                                cerrarMenuCatalogo(idx);
                                return;
                            }
                        } else if (e.key === 'ArrowDown') {
                            // Abrir el catálogo completo con flecha abajo en Tomo / Fracc
                            e.preventDefault();
                            e.stopPropagation();
                            actualizarMenuCatalogo(idx, campo.value, true);
                            return;
                        }
                    }

                    // Avanzar: Tab, +, Enter, ArrowDown, ArrowRight al final
                    const avanza = (e.key === 'Tab' && !e.shiftKey) || e.key === '+' || e.key === 'Enter' || e.key === 'ArrowDown' ||
                        (e.key === 'ArrowRight' && (campo.selectionStart >= campo.value.length || campo.selectionEnd - campo.selectionStart === campo.value.length));

                    // Retroceder: Shift+Tab, ArrowUp, ArrowLeft al inicio, Backspace vacío
                    const retrocede = (e.key === 'Tab' && e.shiftKey) || e.key === 'ArrowUp' ||
                        (e.key === 'ArrowLeft' && campo.selectionStart === 0) ||
                        (e.key === 'Backspace' && campo.value === '');

                    if (avanza) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (idx < campos.length - 1) {
                            campos[idx + 1].focus();
                            campos[idx + 1].select();
                        } else {
                            const btnRen = document.getElementById('btnRenombrar');
                            if (btnRen && !btnRen.disabled) {
                                if (e.key === 'Enter') btnRen.click();
                                else btnRen.focus();
                            } else {
                                campos[0].focus();
                            }
                        }
                    } else if (retrocede && idx > 0) {
                        e.preventDefault();
                        e.stopPropagation();
                        const prev = campos[idx - 1];
                        if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'ArrowLeft') {
                            mantenerCursorAlFinal = true;
                            prev.focus();
                            const len = prev.value.length;
                            prev.setSelectionRange(len, len);
                        } else {
                            prev.focus();
                            prev.select();
                        }
                    }
                });
            });

            // Al presionar Limpiar, vaciar todos los segmentos y enfocar el primer campo
            document.getElementById('btnLimpiar')?.addEventListener('click', () => {
                campos.forEach(c => { c.value = ''; });
                construirNombreSellos();
                campos[0]?.focus();
            });

            construirNombreSellos();
            setTimeout(() => { campos[0]?.focus(); }, 250);
        })();

        // Botón Pantalla Completa eliminado por requerimiento operativo

        document.addEventListener('fullscreenchange', () => {
            const btnPC = document.getElementById('btnPantallaCompleta');
            if (btnPC) {
                if (document.fullscreenElement) {
                    btnPC.innerHTML = '<i class="fa-solid fa-compress"></i> Ventana';
                } else {
                    btnPC.innerHTML = '<i class="fa-solid fa-expand"></i> Pantalla';
                }
            }
        });

        // ==================================================
        // MODO CLARO / OSCURO (persistente) + FILTROS DE VISIÓN DEL PDF
        // ==================================================
        function aplicarTemaCaptura(tema) {
            if (tema === 'dark') {
                htmlEl.classList.remove('light');
                htmlEl.classList.add('dark');
                if (btnModo) btnModo.innerHTML = '<i class="fa-solid fa-sun"></i> Claro';
            } else {
                htmlEl.classList.remove('dark');
                htmlEl.classList.add('light');
                if (btnModo) btnModo.innerHTML = '<i class="fa-solid fa-moon"></i> Oscuro';
            }
            localStorage.setItem('val_tema_captura', tema);
        }

        aplicarTemaCaptura(localStorage.getItem('val_tema_captura') || 'light');

        btnModo?.addEventListener('click', () => {
            aplicarTemaCaptura(htmlEl.classList.contains('dark') ? 'light' : 'dark');
        });

        let filtroVisorEstado = {
            preset: 'normal',
            contraste: 100,
            brillo: 100,
            sepia: 0,
            invertir: false,
            hue_rotate: false,
            grises: false
        };

        try {
            const guardado = JSON.parse(localStorage.getItem('val_filtro_visor_pdf') || 'null');
            if (guardado && typeof guardado === 'object') {
                filtroVisorEstado = { ...filtroVisorEstado, ...guardado };
            }
        } catch (_) {}

        function aplicarFiltroVisorMain() {
            const visor = document.getElementById('pdfViewer');
            const partes = [];
            if (filtroVisorEstado.invertir) partes.push('invert(1)');
            if (filtroVisorEstado.hue_rotate) partes.push('hue-rotate(180deg)');
            if (filtroVisorEstado.grises) partes.push('grayscale(1)');
            if (filtroVisorEstado.sepia > 0) partes.push(`sepia(${filtroVisorEstado.sepia}%)`);
            partes.push(`contrast(${filtroVisorEstado.contraste}%)`);
            partes.push(`brightness(${filtroVisorEstado.brillo}%)`);

            if (visor) {
                visor.style.filter = filtroVisorEstado.preset === 'normal' ? 'none' : partes.join(' ');
            }

            const rngC = document.getElementById('mainRngContraste');
            const rngB = document.getElementById('mainRngBrillo');
            const rngS = document.getElementById('mainRngSepia');
            const lblC = document.getElementById('mainLblContraste');
            const lblB = document.getElementById('mainLblBrillo');
            const lblS = document.getElementById('mainLblSepia');
            if (rngC) rngC.value = filtroVisorEstado.contraste;
            if (rngB) rngB.value = filtroVisorEstado.brillo;
            if (rngS) rngS.value = filtroVisorEstado.sepia;
            if (lblC) lblC.textContent = filtroVisorEstado.contraste + '%';
            if (lblB) lblB.textContent = filtroVisorEstado.brillo + '%';
            if (lblS) lblS.textContent = filtroVisorEstado.sepia + '%';

            ['normal', 'dark_reader', 'alto_contraste', 'tinta_tenue', 'blanco_negro', 'modo_oscuro'].forEach(p => {
                const btn = document.getElementById('btnMainPreset-' + p);
                if (!btn) return;
                if (filtroVisorEstado.preset === p) {
                    btn.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-sky-600 text-white transition flex items-center gap-1';
                } else {
                    btn.className = 'px-2 py-0.5 rounded text-[10px] font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition flex items-center gap-1';
                }
            });

            localStorage.setItem('val_filtro_visor_pdf', JSON.stringify(filtroVisorEstado));
        }

        window.seleccionarPresetVisor = function (preset) {
            const PRESETS = {
                normal:         { contraste: 100, brillo: 100, invertir: false, hue_rotate: false, sepia: 0,  grises: false },
                dark_reader:    { contraste: 105, brillo: 92,  invertir: true,  hue_rotate: true,  sepia: 30, grises: false },
                alto_contraste: { contraste: 160, brillo: 95,  invertir: false, hue_rotate: false, sepia: 0,  grises: false },
                tinta_tenue:    { contraste: 220, brillo: 90,  invertir: false, hue_rotate: false, sepia: 0,  grises: true  },
                blanco_negro:   { contraste: 180, brillo: 100, invertir: false, hue_rotate: false, sepia: 0,  grises: true  },
                modo_oscuro:    { contraste: 120, brillo: 95,  invertir: true,  hue_rotate: false, sepia: 0,  grises: false }
            };
            const cfg = PRESETS[preset] || PRESETS.normal;
            filtroVisorEstado = { preset, ...cfg };
            aplicarFiltroVisorMain();
        };

        window.actualizarFiltroDesdeSlidersMain = function () {
            const rngC = document.getElementById('mainRngContraste');
            const rngB = document.getElementById('mainRngBrillo');
            const rngS = document.getElementById('mainRngSepia');
            if (!rngC || !rngB) return;
            filtroVisorEstado.preset = 'personalizado';
            filtroVisorEstado.contraste = parseInt(rngC.value, 10);
            filtroVisorEstado.brillo = parseInt(rngB.value, 10);
            filtroVisorEstado.sepia = rngS ? parseInt(rngS.value, 10) : 0;
            aplicarFiltroVisorMain();
        };

        aplicarFiltroVisorMain();

        if (rbRegla1) {
            rbRegla1.addEventListener('change', () => {
                if (rbRegla1.checked) {
                    lblEstado.innerHTML = "Ejemplo: 01_2024_123456 (Folio de 1 a 6 dígitos)";
                    txtNombre.maxLength = 14;
                    validarInput();
                }
            });
        }

        if (rbRegla2) {
            rbRegla2.addEventListener('change', () => {
                if (rbRegla2.checked) {
                    lblEstado.innerHTML = "Ejemplo: 01_01_01_01_00_00_2011_0090_00";
                    txtNombre.maxLength = 100;
                    validarInput();
                }
            });
        }

        txtNombre.addEventListener('paste', e => e.preventDefault());
        txtNombre.addEventListener('copy', e => e.preventDefault());
        txtNombre.addEventListener('cut', e => e.preventDefault());
        txtNombre.addEventListener('contextmenu', e => e.preventDefault());
        txtNombre.addEventListener('keypress', e => {
            if (!/[0-9_\-]/.test(e.key) && e.key !== 'Enter') {
                e.preventDefault();
            }
        });

        let valorAnterior = "";
        txtNombre.addEventListener('input', function (e) {
            let val = this.value;
            val = val.replace(/-/g, '_');

            // Auto-guiones usando el módulo externo CapturaReglas
            val = CapturaReglas.aplicarAutoGuiones(val, valorAnterior, rbRegla1 ? rbRegla1.checked : false, rbRegla2 ? rbRegla2.checked : false);

            this.value = val;
            valorAnterior = val;
            validarInput();
        });

        // Escuchar Enter en el input de captura
        txtNombre.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (accionEnEspera === 'Duplicar') {
                    CapturaProcesos.procesarDuplicar(ctx);
                } else {
                    CapturaProcesos.procesarRenombrar(ctx);
                }
            }
        });

        // Escuchar la tecla Tab para atrapar el foco entre el input y los botones de captura
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                // Solo atrapar si hay un lote activo (sesión iniciada)
                if (!loteActualId) return;

                const input = document.getElementById('txtNombre');
                const btnRenombrar = document.getElementById('btnRenombrar');
                const btnDuplicar = document.getElementById('btnDuplicar');

                // Obtener elementos habilitados del ciclo de captura
                const elementosFoco = [];
                if (input) elementosFoco.push(input);
                if (btnRenombrar && !btnRenombrar.disabled) elementosFoco.push(btnRenombrar);
                if (btnDuplicar && !btnDuplicar.disabled) elementosFoco.push(btnDuplicar);

                if (elementosFoco.length === 0) return;

                const indice = elementosFoco.indexOf(document.activeElement);

                if (e.shiftKey) {
                    // Shift + Tab: Navegar hacia atrás
                    e.preventDefault();
                    if (indice <= 0) {
                        elementosFoco[elementosFoco.length - 1].focus();
                    } else {
                        elementosFoco[indice - 1].focus();
                    }
                } else {
                    // Tab: Navegar hacia adelante
                    e.preventDefault();
                    if (indice === -1 || indice >= elementosFoco.length - 1) {
                        elementosFoco[0].focus();
                    } else {
                        elementosFoco[indice + 1].focus();
                    }
                }
            }
        });

        // Búsqueda completamente automática de lote al abrir la página (auto-selección de regla por asignación de usuario)
        const ASIGNACION_USUARIO = @json($asignacionUsuario ?? null);
        const CATALOGO_INCONSISTENCIAS = @json($catalogoInconsistencias ?? []);
        const ESTADO_HORARIO = @json($estadoHorario ?? ['permitido' => true, 'en_horario' => true, 'mensaje' => null]);
        const SERVIDOR_TZ = @json($servidorZonaHoraria ?? 'America/Mexico_City');
        const SERVIDOR_OFFSET_MS = {{ (int) ($servidorTimestampMs ?? (now()->timestamp * 1000)) }} - Date.now();

        // Reloj en vivo sincronizado con la hora oficial del servidor + aviso 10 min antes del fin de turno
        (function iniciarRelojServidor() {
            const fmt = new Intl.DateTimeFormat('es-MX', {
                timeZone: SERVIDOR_TZ,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            let avisoDiezMinMostrado = false;

            function calcularSegundosRestantesTurno(horaTexto) {
                if (!ESTADO_HORARIO || !ESTADO_HORARIO.hora_salida) return null;
                const [hA, mA, sA] = horaTexto.split(':').map(n => parseInt(n, 10) || 0);
                const [hS, mS] = String(ESTADO_HORARIO.hora_salida).split(':').map(n => parseInt(n, 10) || 0);
                const segActual = hA * 3600 + mA * 60 + sA;
                let segSalida = hS * 3600 + mS * 60;

                if (ESTADO_HORARIO.cruza_medianoche && segActual >= 12 * 3600 && segSalida < 12 * 3600) {
                    segSalida += 24 * 3600;
                }
                return segSalida - segActual;
            }

            function actualizarReloj() {
                const ahoraSrv = new Date(Date.now() + SERVIDOR_OFFSET_MS);
                const texto = fmt.format(ahoraSrv);
                const el1 = document.getElementById('relojServidor');
                const el2 = document.getElementById('relojServidorBloqueo');
                if (el1) el1.textContent = texto;
                if (el2) el2.textContent = texto;

                // Verificar si faltan <= 10 minutos para terminar el turno
                const rest = calcularSegundosRestantesTurno(texto);
                const banner = document.getElementById('alertaFinTurno');
                const lblBanner = document.getElementById('lblAlertaFinTurno');
                const lblCuenta = document.getElementById('lblCuentaRegresivaTurno');

                if (rest !== null && banner && lblBanner && lblCuenta) {
                    if (rest > 0 && rest <= 600) {
                        const mm = String(Math.floor(rest / 60)).padStart(2, '0');
                        const ss = String(rest % 60).padStart(2, '0');
                        banner.classList.remove('hidden', 'bg-rose-600', 'text-white');
                        banner.classList.add('bg-amber-500', 'text-slate-950');
                        lblBanner.textContent = `Atención: Tu turno (${ESTADO_HORARIO.turno_texto || ESTADO_HORARIO.hora_salida}) termina a las ${ESTADO_HORARIO.hora_salida}.`;
                        lblCuenta.textContent = `Faltan ${mm}:${ss}`;

                        if (!avisoDiezMinMostrado) {
                            avisoDiezMinMostrado = true;
                            if (typeof solicitarConfirmacion === 'function') {
                                solicitarConfirmacion(
                                    'Aviso de fin de turno',
                                    `Faltan menos de 10 minutos para que termine tu turno (${ESTADO_HORARIO.turno_texto || ESTADO_HORARIO.hora_salida}).`,
                                    'fa-clock',
                                    'text-amber-500',
                                    false
                                );
                            }
                        }
                    } else if (rest <= 0 && rest > -1800 && ESTADO_HORARIO.permitido) {
                        banner.classList.remove('hidden', 'bg-amber-500', 'text-slate-950');
                        banner.classList.add('bg-rose-600', 'text-white');
                        lblBanner.textContent = `Tu turno (${ESTADO_HORARIO.hora_salida}) ya terminó. Puedes guardar este último PDF; al guardarlo se bloqueará la estación.`;
                        lblCuenta.textContent = '00:00';
                    }
                }
            }
            actualizarReloj();
            setInterval(actualizarReloj, 1000);
        })();

        // Bloqueo total de la interfaz de captura por horario laboral
        window.bloquearPorHorario = function (mensaje) {
            const overlay = document.getElementById('overlayBloqueoHorario');
            const lblMsg = document.getElementById('lblMensajeBloqueoHorario');
            const visor = document.getElementById('pdfViewer');
            if (visor) visor.src = 'about:blank';
            document.querySelectorAll('.sello-seg, #btnRenombrar, #btnInconsistencia, #btnLimpiar, #btnRotarIzquierda, #btnRotarDerecha').forEach(el => {
                el.disabled = true;
            });
            if (lblMsg && mensaje) lblMsg.textContent = mensaje;
            if (overlay) overlay.classList.remove('hidden');
        };

        window.addEventListener('DOMContentLoaded', () => {
            const ultimoDirId = localStorage.getItem('val_ultimo_directorio_id');
            const ultimaDelId = localStorage.getItem('val_ultima_delegacion_id');

            let dir = directoriosConfig.find(d => d.id === ultimoDirId) || directoriosConfig[0] || { id: 'irec', nombre: 'IREC', reglas: ['Regla2'] };
            let del = catalogoDelegaciones.find(d => String(d.id) === String(ultimaDelId)) || null;

            // Auto-seleccionar tipo de archivo, regla y delegación desde asignaciones_captura_usuario
            if (ASIGNACION_USUARIO) {
                const tipoAsig = String(ASIGNACION_USUARIO.tipo_documento || '').toUpperCase();
                const dirAsig = directoriosConfig.find(d => String(d.tipo || '').toUpperCase() === tipoAsig);
                if (dirAsig) {
                    dir = dirAsig;
                }
                if (ASIGNACION_USUARIO.delegacion_id) {
                    const delAsig = catalogoDelegaciones.find(d => String(d.id) === String(ASIGNACION_USUARIO.delegacion_id));
                    if (delAsig) del = delAsig;
                }
            }

            dirSeleccionado = dir;
            delSeleccionada = del;
            aplicarReglasDeDirectorio(dir);

            // Forzar selección automática de regla según tabla de asignaciones
            if (ASIGNACION_USUARIO && ASIGNACION_USUARIO.regla_automatica) {
                const rb1 = document.getElementById('rbRegla1');
                const rb2 = document.getElementById('rbRegla2');
                if (ASIGNACION_USUARIO.regla_automatica === 'Regla2' && rb2) {
                    rb2.checked = true;
                    if (rb1) rb1.checked = false;
                } else if (rb1) {
                    rb1.checked = true;
                    if (rb2) rb2.checked = false;
                }
            }

            // Interceptar botón Inconsistencia para usar el catálogo R01-R09
            const btnInc = document.getElementById('btnInconsistencia');
            const modalInc = document.getElementById('modalInconsistenciaCatalogo');
            if (btnInc && modalInc) {
                const btnClon = btnInc.cloneNode(true);
                btnInc.parentNode.replaceChild(btnClon, btnInc);
                btnClon.addEventListener('click', () => {
                    modalInc.classList.remove('hidden');
                });
            }

            const selInc = document.getElementById('selectCodigoInconsistencia');
            const lblAccion = document.getElementById('lblAccionCorrectivaInc');
            if (selInc && lblAccion) {
                const actualizarInfoInc = () => {
                    const item = CATALOGO_INCONSISTENCIAS.find(c => c.codigo === selInc.value);
                    lblAccion.textContent = item ? `Acción correctiva: ${item.accion_correctiva}` : '';
                };
                selInc.addEventListener('change', actualizarInfoInc);
                actualizarInfoInc();
            }

            document.getElementById('btnCancelarIncCatalogo')?.addEventListener('click', () => {
                modalInc?.classList.add('hidden');
            });

            document.getElementById('btnConfirmarIncCatalogo')?.addEventListener('click', async () => {
                const codigo = document.getElementById('selectCodigoInconsistencia')?.value || 'R01';
                const nota = document.getElementById('txtNotaInconsistencia')?.value || '';
                const pdfActual = (typeof ctx !== 'undefined' && ctx.listaPdfs && ctx.listaPdfs[ctx.indiceActual]) ? ctx.listaPdfs[ctx.indiceActual] : null;
                try {
                    const resp = await fetch("{{ route('admin.captura.inconsistencia') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({
                            tipo_documento: ASIGNACION_USUARIO?.tipo_documento || 'sellos',
                            delegacion_id: delSeleccionada?.id || null,
                            sesion_id: pdfActual?.sesion_id || (typeof ctx !== 'undefined' ? ctx.loteActualId : null),
                            indice_pdf_id: pdfActual?.indice_pdf_id || null,
                            lote: (typeof ctx !== 'undefined' && ctx.loteActualNombre) ? ctx.loteActualNombre : 'LOTE',
                            ruta_origen: pdfActual?.ruta_origen || '',
                            archivo_original: pdfActual?.nombre || '',
                            paginas: pdfActual?.paginas || 1,
                            hash: pdfActual?.hash || null,
                            inconsistencia_codigo: codigo,
                            inconsistencia_comentario: nota,
                        })
                    });
                    const data = await resp.json();
                    modalInc?.classList.add('hidden');
                    if (data.ok && typeof CapturaProcesos !== 'undefined' && typeof ctx !== 'undefined') {
                        if (data.fuera_horario_despues_de_guardar) {
                            await ctx.solicitarConfirmacion(
                                'Fin de horario laboral',
                                data.aviso_horario || 'Tu turno ha finalizado. Este último registro se guardó correctamente, pero ya no podrás capturar más.',
                                'fa-clock',
                                'text-amber-500',
                                false
                            );
                            window.bloquearPorHorario(data.aviso_horario);
                            return;
                        }
                        CapturaProcesos.avanzarSiguiente?.(ctx);
                    }
                } catch (err) {
                    console.error('Error reportando inconsistencia:', err);
                }
            });

            // Si el usuario está fuera de su horario laboral (y no tiene un último PDF pendiente de guardar), bloquear de inmediato
            if (ESTADO_HORARIO && ESTADO_HORARIO.permitido === false) {
                window.bloquearPorHorario(ESTADO_HORARIO.mensaje);
                return;
            }

            // Iniciar búsqueda automática de PDF inmediatamente
            lblTotalPdf.innerText = del ? `${del.nombre} - Buscando PDF...` : 'Buscando PDF libre...';
            CapturaProcesos.cargarLoteDesdeServidor(ctx);
        });
    </script>

    <!-- Modal Catálogo de Inconsistencias (R01-R09) -->
    <div id="modalInconsistenciaCatalogo" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
        <div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-xl shadow-2xl w-[92%] max-w-[480px] p-6 border border-gray-300 dark:border-gray-700">
            <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation text-amber-500 text-2xl"></i>
                <div>
                    <h3 class="font-bold text-lg leading-tight">Reportar Inconsistencia</h3>
                    <span class="text-xs text-gray-500">Notifica al administrador en tiempo real (solo BD)</span>
                </div>
            </div>
            <div class="mb-3">
                <label class="block text-xs font-bold uppercase text-gray-600 dark:text-gray-300 mb-1">Código y Motivo de Rechazo</label>
                <select id="selectCodigoInconsistencia" class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded">
                    @foreach(($catalogoInconsistencias ?? []) as $inc)
                        <option value="{{ $inc->codigo }}">{{ $inc->codigo }} — {{ $inc->motivo_rechazo }}</option>
                    @endforeach
                </select>
                <p id="lblAccionCorrectivaInc" class="text-xs text-amber-600 dark:text-amber-400 font-semibold mt-1.5"></p>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold uppercase text-gray-600 dark:text-gray-300 mb-1">Observaciones adicionales (opcional)</label>
                <textarea id="txtNotaInconsistencia" rows="2" class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded" placeholder="Detalle adicional para el supervisor..."></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" id="btnCancelarIncCatalogo" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 rounded font-semibold text-sm">Cancelar</button>
                <button type="button" id="btnConfirmarIncCatalogo" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-black font-bold rounded text-sm">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Enviar Inconsistencia
                </button>
            </div>
        </div>
    </div>
    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
        @csrf
    </form>
</body>

</html>