@extends('layouts.report')
@section('titulo', 'Reportes de desempeño capturas')
<!-- CSS de DataTables -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<style>
    .badge {
        font-weight: 600;
        color: #000000;
    }

    .bg-primary {
        background-color: #ffffff !important;
    }

    ::-webkit-scrollbar {
        width: 5px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #e2e2e5;
    }

    .card-header {
        background-color: #c2c2c282 !important;
    }

    .h3,
    h3 {
        font-size: 1.25rem !important;
    }

    .h5,
    h5 {
        font-size: 1rem !important;
    }
</style>

@section('contenido')

    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card shadow-lg" id="filtros">
                <div class="card-body">
                    <h1>REPORTE DE CAPTURAS DE SELLOS Y BOLETAS POR USUARIO</h1>
                    <div class="row mb-12 align-items-end" style="margin-bottom: 1rem;">
                        <div class="col-sm-6 col-md-3 col-lg-2 mb-2">
                            <label for="fechaInicio" class="form-label">Desde:</label>
                            <input type="date" id="fechaInicio" name="fechaInicio" class="form-control">
                        </div>

                        <div class="col-sm-6 col-md-3 col-lg-2 mb-2">
                            <label for="fechaFin" class="form-label">Hasta:</label>
                            <input type="date" id="fechaFin" name="fechaFin" class="form-control">
                        </div>

                        <div class="col-sm-6 col-md-3 col-lg-2 mb-2">
                            <label for="tipoSello" class="form-label">Tipo de Sello:</label>
                            <select id="tipoSello" name="tipoSello" class="form-control">
                                <option value="">Todos</option>
                                <option value="Sellos">Sellos</option>
                                <option value="Boleta de inscripcion">Boleta de inscripción</option>
                                <option value="Boleta de ingreso">Boleta de ingreso</option>
                                <option value="Boleta de rechazo">Boleta de rechazo</option>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-3 col-lg-1-5 col-xl mb-2">
                            <label class="form-label"
                                style="visibility: hidden; display: block; margin-bottom: 8px;">Detalle:</label>
                            <button type="button" id="btnDescargarExcel" class="btn btn-success btn-block"
                                style="background-color: #28a745; border-color: #28a745; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.85rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor"
                                    class="bi bi-file-earmark-excel" viewBox="0 0 16 16">
                                    <path
                                        d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z" />
                                    <path
                                        d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.3 1.25c.085.13.11.307.11.95h2.07c-.642-.64-1.54-.83-2.18-.95zm.7 2.2V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V3.45H10z" />
                                </svg>
                                Excel Detalle
                            </button>
                        </div>

                        <div class="col-sm-6 col-md-3 col-lg-1-5 col-xl mb-2">
                            <label class="form-label"
                                style="visibility: hidden; display: block; margin-bottom: 8px;">Resumen:</label>
                            <button type="button" id="btnDescargarExcelResumen" class="btn btn-info btn-block"
                                style="background-color: #117a8b; border-color: #117a8b; color: white; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.85rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor"
                                    class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                                    <path
                                        d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 11.293 4L9.293 0zM9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1zM5.884 6.68 8 9.219l2.116-2.54a.5.5 0 1 1 .768.641L8.651 10l2.233 2.68a.5.5 0 0 1-.768.64L8 10.781l-2.116 2.54a.5.5 0 0 1-.768-.641L7.349 10 5.116 7.32a.5.5 0 1 1 .768-.64z" />
                                </svg>
                                Excel Resumen
                            </button>
                        </div>

                        <div class="col-sm-6 col-md-3 col-lg-1-5 col-xl mb-2">
                            <label class="form-label"
                                style="visibility: hidden; display: block; margin-bottom: 8px;">General:</label>
                            <button type="button" id="btnDescargarExcelGeneral" class="btn btn-primary btn-block"
                                style="background-color: #007bff; border-color: #007bff; color: white; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.85rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor"
                                    class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                                    <path
                                        d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 11.293 4L9.293 0zM9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1zM5.884 6.68 8 9.219l2.116-2.54a.5.5 0 1 1 .768.641L8.651 10l2.233 2.68a.5.5 0 0 1-.768.64L8 10.781l-2.116 2.54a.5.5 0 0 1-.768-.641L7.349 10 5.116 7.32a.5.5 0 1 1 .768-.64z" />
                                </svg>
                                Excel General
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para descarga de detalle -->
    <form action="{{ route('admin.analytics_sellos.reportes.descargar_excel') }}" method="POST" id="formDescargarExcel"
        style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelFechaInicio">
        <input type="hidden" name="fin" id="excelFechaFin">
        <input type="hidden" name="tipo_sello" id="excelTipoSello">
    </form>

    <!-- Formulario oculto para descarga de resumen -->
    <form action="{{ route('admin.analytics_sellos.reportes.descargar_excel_resumen') }}" method="POST"
        id="formDescargarExcelResumen" style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelResumenFechaInicio">
        <input type="hidden" name="fin" id="excelResumenFechaFin">
        <input type="hidden" name="tipo_sello" id="excelResumenTipoSello">
    </form>

    <!-- Formulario oculto para descarga general -->
    <form action="{{ route('admin.analytics_capturas.reportes.descargar_excel_general') }}" method="POST"
        id="formDescargarExcelGeneral" style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelGeneralFechaInicio">
        <input type="hidden" name="fin" id="excelGeneralFechaFin">
        <input type="hidden" name="tipo_sello" id="excelGeneralTipoSello">
        <input type="hidden" name="tipo" value="sellos">
    </form>

    <!-- Formulario oculto para descarga de bono -->
    <form action="{{ route('admin.analytics_sellos.reportes.descargar_excel_bono') }}" method="POST"
        id="formDescargarExcelBono" style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelBonoFechaInicio">
        <input type="hidden" name="fin" id="excelBonoFechaFin">
        <input type="hidden" name="tipo_sello" id="excelBonoTipoSello">
    </form>

    <div class="mb-4">
        <div id="turnosCardsContainer" class="row w-100 mb-4 g-2 mx-0"></div>
    </div>

    <div class="contenedor-kpis">
        <div class="row">

            <!-- Detalle de Resumen de Auditoría (Tabla tipo Excel) - Hasta arriba -->
            <div class="col-12 col-md-12 mb-4">
                <div class="card shadow-lg">
                    <div class="card-header text-center">
                        <h5 class="mb-0 font-weight-bold">Resumen de Auditoría de Sellos y Boletas</h5>
                    </div>
                    <div class="card-body">
                        <!-- Contadores en fila -->
                        <div class="row text-center mb-3">
                            <div class="col-md-2 col-sm-4 mb-2">
                                <div class="p-1 rounded border"
                                    style="background-color: #E2EFDA; border-color: #c3e6cb; font-size: 0.8rem;">
                                    <span class="d-block font-weight-bold text-dark" id="lblValidados">Validados: 0</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 mb-2">
                                <div class="p-1 rounded border"
                                    style="background-color: #C0EBFF; border-color: #b8daff; font-size: 0.8rem;">
                                    <span class="d-block font-weight-bold text-dark" id="lblDuplicados">Duplicados: 0</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 mb-2">
                                <div class="p-1 rounded border"
                                    style="background-color: #DEB887; border-color: #ffeeba; color: #000 !important; font-size: 0.8rem;">
                                    <span class="d-block font-weight-bold text-dark" id="lblRenombrados">Renombrados:
                                        0</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 mb-2">
                                <div class="p-1 rounded border"
                                    style="background-color: #FADBD8; border-color: #f5c6cb; font-size: 0.8rem;">
                                    <span class="d-block font-weight-bold text-dark" id="lblEliminados">Eliminados: 0</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 mb-2">
                                <div class="p-1 rounded border"
                                    style="background-color: #FFFFC0; border-color: #ffe082; font-size: 0.8rem;">
                                    <span class="d-block font-weight-bold text-dark"
                                        id="lblInconsistencias">Inconsistencias: 0</span>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-4 mb-2">
                                <div class="p-1 rounded border"
                                    style="background-color: #E2E3E5; border-color: #d6d8db; font-size: 0.8rem;">
                                    <span class="d-block font-weight-bold text-dark" id="lblTotalProcesados">Total Proc:
                                        0</span>
                                </div>
                            </div>
                        </div>

                        <!-- Filtros Locales de la Tabla -->
                        <div class="row mb-3 align-items-end">
                            <div class="col-sm-6 col-md-3 mb-2">
                                <label for="filtroTurno" class="form-label font-weight-bold"
                                    style="font-size: 0.8rem; margin-bottom: 4px;">Filtrar por Turno:</label>
                                <select id="filtroTurno" class="form-control form-control-sm"
                                    style="font-size: 0.8rem; height: calc(1.5em + .5rem + 2px);">
                                    <option value="">Todos los turnos</option>
                                    <option value="Matutino">Matutino</option>
                                    <option value="Vespertino">Vespertino</option>
                                    <!-- <option value="Mixto">Mixto</option> -->
                                    <option value="Nocturno">Nocturno</option>
                                </select>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table id="tablaAuditoria" class="table table-bordered table-sm"
                                style="width:100%; font-size: 0.8rem;">
                                <thead id="tablaAuditoriaHead" style="background-color: #4F81BD; color: white;">
                                    <!-- Carga dinámica del encabezado en Javascript -->
                                </thead>
                                <tbody>
                                    <!-- Carga dinámica por AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica de Acciones Totales por Usuario (Barras con Scroll) -->
            <div class="col-12 col-md-12 mb-3" style="height: 480px">
                <div class="card h-100 shadow-lg">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Acciones Totales por Usuario</h5>
                        <span id="totalUsuariosTotales" style="font-weight: bold; color: #36a2eb; font-size: 0.9rem;">Total:
                            0</span>
                    </div>
                    <div class="card-body" style="overflow-x: auto; overflow-y: hidden; min-height: 380px;">
                        <div id="contenedorGraficaUsuarios" style="min-width: 100%; height: 350px; position: relative;">
                            <canvas id="graficaUsuariosTotales" style="height: 350px !important;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica de Usuarios (Productividad Individual) -->
            <div class="col-12 col-md-12 mb-3" style="height: 500px">
                <div class="card h-100 shadow-lg">
                    <div class="card-header text-center">
                        <div class="row mb-0 align-items-center">
                            <div class="col-sm-6 col-md-4 text-left">
                                <h5 class="mb-0">Productividad del Usuario</h5>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <select id="selectUsuario" class="form-control">
                                    <option value="">Seleccione un usuario...</option>
                                </select>
                            </div>
                            <div class="col-sm-12 col-md-4 text-right">
                                <span id="lblTotalUsuario" style="font-weight: bold; color: darkslategrey;">Total: 0</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <!-- Contenedor centrado para la gráfica del usuario -->
                        <div style="min-width: 200px; width: 90%; margin: 0 auto;">
                            <canvas id="graficaUsuarioEspecifico" style="max-height: 350px !important;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const fechaInicioInput = document.getElementById('fechaInicio');
            const fechaFinInput = document.getElementById('fechaFin');
            const tipoSelloInput = document.getElementById('tipoSello');
            const selectUsuario = document.getElementById('selectUsuario');
            const lblTotalUsuario = document.getElementById('lblTotalUsuario');
            const btnDescargarExcel = document.getElementById('btnDescargarExcel');
            const filtroTurno = document.getElementById('filtroTurno');

            let chartUsuarioEspecifico;
            let chartUsuariosTotales;
            let dataTableInstance;
            let globalDataAccionesUsuario = {};
            let globalTablaResumen = [];
            let globalAccionesResumen = [];

            function formatearNumero(numero) {
                return Number(numero).toLocaleString('en-US');
            }

            // FECHAS POR DEFECTO
            const today = new Date();
            const primerDiaMes = new Date(today.getFullYear(), today.getMonth(), 1);
            const ultimoDiaMes = new Date(today.getFullYear(), today.getMonth() + 1, 0);

            const formatoFecha = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            fechaInicioInput.value = formatoFecha(primerDiaMes);
            fechaFinInput.value = formatoFecha(ultimoDiaMes);

            // EVENTOS
            fechaInicioInput.addEventListener('change', cargarDashboard);
            fechaFinInput.addEventListener('change', cargarDashboard);
            tipoSelloInput.addEventListener('change', cargarDashboard);
            selectUsuario.addEventListener('change', () => {
                renderUsuarioEspecifico(selectUsuario.value);
            });
            filtroTurno.addEventListener('change', aplicarFiltrosLocales);

            // Descarga Excel Detalle
            btnDescargarExcel.addEventListener('click', function () {
                document.getElementById('excelFechaInicio').value = fechaInicioInput.value;
                document.getElementById('excelFechaFin').value = fechaFinInput.value;
                document.getElementById('excelTipoSello').value = tipoSelloInput.value;
                document.getElementById('formDescargarExcel').submit();
            });

            // Descarga Excel Resumen
            const btnDescargarExcelResumen = document.getElementById('btnDescargarExcelResumen');
            if (btnDescargarExcelResumen) {
                btnDescargarExcelResumen.addEventListener('click', function () {
                    document.getElementById('excelResumenFechaInicio').value = fechaInicioInput.value;
                    document.getElementById('excelResumenFechaFin').value = fechaFinInput.value;
                    document.getElementById('excelResumenTipoSello').value = tipoSelloInput.value;
                    document.getElementById('formDescargarExcelResumen').submit();
                });
            }

            // Descarga Excel General
            const btnDescargarExcelGeneral = document.getElementById('btnDescargarExcelGeneral');
            if (btnDescargarExcelGeneral) {
                btnDescargarExcelGeneral.addEventListener('click', function () {
                    document.getElementById('excelGeneralFechaInicio').value = fechaInicioInput.value;
                    document.getElementById('excelGeneralFechaFin').value = fechaFinInput.value;
                    document.getElementById('formDescargarExcelGeneral').submit();
                });
            }

            // Descarga Excel Bono (toma el rango de fechas seleccionado en los inputs)
            const btnDescargarExcelBono = document.getElementById('btnDescargarExcelBono');
            if (btnDescargarExcelBono) {
                btnDescargarExcelBono.addEventListener('click', function () {
                    document.getElementById('excelBonoFechaInicio').value = fechaInicioInput.value;
                    document.getElementById('excelBonoFechaFin').value = fechaFinInput.value;
                    document.getElementById('excelBonoTipoSello').value = tipoSelloInput.value;
                    document.getElementById('formDescargarExcelBono').submit();
                });
            }

            // COLORES POR ACCIÓN
            function getColorByAction(accion) {
                const lower = String(accion || '').toLowerCase();
                if (lower.includes('valid')) return '#10b981';
                if (lower.includes('renombr')) return '#0284c7';
                if (lower.includes('inconsistencia') || lower.includes('recorte') || lower.includes('eliminar') || lower.includes('eliminado')) return '#ef4444';
                return '#64748b';
            }

            function renderTurnoCards(dataTurnos) {
                const container = document.getElementById('turnosCardsContainer');
                if (!container) return;

                const cards = [
                    { key: 'Matutino', metric: 'PDFs capturados', field: 'archivos', color: 'rgb(245, 158, 11)' },
                    { key: 'Vespertino', metric: 'PDFs capturados', field: 'archivos', color: 'rgb(14, 165, 233)' },
                    { key: 'Nocturno', metric: 'PDFs capturados', field: 'archivos', color: 'rgb(139, 92, 246)' },
                    { key: 'Matutino', metric: 'Imágenes trabajadas', field: 'imagenes', color: 'rgb(245, 158, 11)' },
                    { key: 'Vespertino', metric: 'Imágenes trabajadas', field: 'imagenes', color: 'rgb(14, 165, 233)' },
                    { key: 'Nocturno', metric: 'Imágenes trabajadas', field: 'imagenes', color: 'rgb(139, 92, 246)' },
                ];

                const obtenerTurno = (nombre) => dataTurnos.find(item => (item.turno || '').toLowerCase() === nombre.toLowerCase()) || { archivos: 0, imagenes: 0 };

                container.innerHTML = cards.map(card => {
                    const data = obtenerTurno(card.key);
                    return `
                            <div class="col-12 col-sm-4 col-md-4 px-2 mb-3">
                                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #ffffff, #f9fafb); border-left: 4px solid ${card.color} !important;">
                                    <div class="card-body p-3 d-flex flex-column justify-content-center">
                                        <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #6b7280; font-weight: 600; margin-bottom: 0.25rem;">${card.key} - ${card.metric}</p>
                                        <h3 class="text-2xl font-bold" style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin: 0;">${formatearNumero(data[card.field] || 0)}</h3>
                                    </div>
                                </div>
                            </div>
                        `;
                }).join('');
            }

            let abortController;

            async function cargarDashboard() {
                const inicio = fechaInicioInput.value;
                const fin = fechaFinInput.value;
                const tipo_sello = tipoSelloInput.value;

                if (!inicio || !fin) return;

                if (abortController) {
                    abortController.abort();
                }
                abortController = new AbortController();
                const signal = abortController.signal;

                try {
                    const contenedor = document.querySelector('.contenedor-kpis');
                    if (contenedor) contenedor.style.opacity = '0.5';

                    const res = await fetch('{{ route("admin.analytics_sellos.reportes.dashboard_sellos") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ inicio, fin, tipo_sello, detallado: true }),
                        signal: signal
                    });

                    if (!res.ok) throw new Error('Error al cargar datos');

                    const data = await res.json();
                    console.log('DATA DASHBOARD', data);

                    globalDataAccionesUsuario = data.acciones_por_usuario || {};
                    globalTablaResumen = data.tabla_resumen || [];
                    globalAccionesResumen = data.acciones_resumen || [];

                    // Llenar combo de usuarios
                    const previousUser = selectUsuario.value;
                    selectUsuario.innerHTML = '<option value="">Seleccione un usuario...</option>';
                    (data.usuarios_unicos || []).forEach(usu => {
                        const option = document.createElement('option');
                        option.value = usu;
                        option.textContent = usu;
                        selectUsuario.appendChild(option);
                    });

                    if (data.usuarios_unicos && data.usuarios_unicos.length > 0) {
                        selectUsuario.value = previousUser && data.usuarios_unicos.includes(previousUser) ? previousUser : data.usuarios_unicos[0];
                        renderUsuarioEspecifico(selectUsuario.value);
                    } else {
                        renderUsuarioEspecifico('');
                    }

                    filtroTurno.value = ''; // Resetear turno al cargar nuevo rango

                    renderTurnoCards(data.por_turno_detalle || []);
                    renderUsuariosTotales(data.por_usuario || []);
                    aplicarFiltrosLocales();
                } catch (error) {
                    if (error.name === 'AbortError') {
                        console.log('Petición abortada');
                        return;
                    }
                    console.error('Error al cargar dashboard:', error);
                } finally {
                    if (!signal.aborted) {
                        const contenedor = document.querySelector('.contenedor-kpis');
                        if (contenedor) contenedor.style.opacity = '1';
                    }
                }
            }

            function renderUsuarioEspecifico(usuario) {
                if (chartUsuarioEspecifico) chartUsuarioEspecifico.destroy();

                if (!usuario || !globalDataAccionesUsuario[usuario]) {
                    lblTotalUsuario.textContent = 'Total: 0';
                    chartUsuarioEspecifico = new Chart(document.getElementById('graficaUsuarioEspecifico'), { type: 'bar', data: { labels: [], datasets: [] } });
                    return;
                }

                const datosUsuario = globalDataAccionesUsuario[usuario];
                lblTotalUsuario.textContent = 'Total: ' + formatearNumero(datosUsuario.total);

                const accionesArray = Object.keys(datosUsuario.acciones).map(key => {
                    return { accion: key, valor: datosUsuario.acciones[key] };
                }).sort((a, b) => b.valor - a.valor);

                const labels = accionesArray.map(x => x.accion);
                const valores = accionesArray.map(x => x.valor);
                const bgColors = labels.map(l => getColorByAction(l));

                chartUsuarioEspecifico = new Chart(document.getElementById('graficaUsuarioEspecifico'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Acciones Realizadas',
                            data: valores,
                            backgroundColor: bgColors,
                            borderColor: bgColors.map(c => c.replace('rgb', 'rgba').replace(')', ', 1)')),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        plugins: {
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                formatter: formatearNumero,
                                font: { weight: 'bold' }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: formatearNumero
                                }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            function renderUsuariosTotales(data) {
                const labels = data.map(x => x.usuario);
                const valores = data.map(x => x.total);

                const total = valores.reduce((a, b) => a + b, 0);
                document.getElementById('totalUsuariosTotales').textContent = 'Total: ' + formatearNumero(total);

                if (chartUsuariosTotales) chartUsuariosTotales.destroy();

                // Ajustar ancho del contenedor de la grafica dinamicamente
                const numUsuarios = data.length;
                const parentWidth = document.getElementById('contenedorGraficaUsuarios').parentNode.clientWidth;
                // 60px por usuario para que no queden amontonados
                const widthCalculado = Math.max(numUsuarios * 60, parentWidth);
                document.getElementById('contenedorGraficaUsuarios').style.width = widthCalculado + 'px';

                chartUsuariosTotales = new Chart(document.getElementById('graficaUsuariosTotales'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Archivos procesados',
                            data: valores,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgb(54, 162, 235)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                ticks: {
                                    display: true, // Mostrar nombre completo de los usuarios
                                    autoSkip: false, // No omitir etiquetas
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: formatearNumero
                                }
                            }
                        },
                        plugins: {
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                formatter: formatearNumero,
                                font: { weight: 'bold' }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            function aplicarFiltrosLocales() {
                const turnoSel = filtroTurno.value.toLowerCase();

                const datosFiltrados = globalTablaResumen.filter(row => {
                    const matchesTurno = !turnoSel || (row.Turno || '').toLowerCase().includes(turnoSel);
                    return matchesTurno;
                });

                renderizarTablaResumen(datosFiltrados, globalAccionesResumen);
            }

            function renderizarTablaResumen(tablaResumen, accionesResumen) {
                if (dataTableInstance) {
                    dataTableInstance.destroy();
                }

                // Generar Cabeceras Dinámicas (Formato Excel)
                const thead = document.getElementById('tablaAuditoriaHead');
                thead.innerHTML = '';
                const trHead = document.createElement('tr');
                trHead.innerHTML = `
                                    <th style="background-color: #4F81BD; color: white;">Fecha</th>
                                    <th style="background-color: #4F81BD; color: white;">PCs</th>
                                    <th style="background-color: #4F81BD; color: white;">Usuario</th>
                                    <th style="background-color: #4F81BD; color: white;">Turno</th>
                                `;

                accionesResumen.forEach(acc => {
                    const th = document.createElement('th');
                    th.style.backgroundColor = '#4F81BD';
                    th.style.color = 'white';
                    th.textContent = acc;
                    trHead.appendChild(th);
                });

                trHead.innerHTML += `
                                    <th style="background-color: #4F81BD; color: white;">Total Sellos</th>
                                    <th style="background-color: #4F81BD; color: white;">Total Registros</th>
                                    <th style="background-color: #4F81BD; color: white;">Total</th>
                                `;
                thead.appendChild(trHead);

                // Llenar Filas
                const tbody = document.querySelector('#tablaAuditoria tbody');
                tbody.innerHTML = '';

                // Calcular contadores
                let validados = 0;
                let duplicados = 0;
                let renombrados = 0;
                let eliminados = 0;
                let inconsistencias = 0;

                tablaResumen.forEach(row => {
                    // Colores de fila idénticos al Excel:
                    // Calidad: #FADBD8, Matutino: #E2EFDA, Vespertino: #FFF2CC, Nocturno: #DDEBF7, Default: #F2F2F2
                    let rowBgColor = '#F2F2F2';
                    const categoria = (row.Categoria || '').toLowerCase();
                    const turno = (row.Turno || '').toLowerCase();

                    if (categoria.includes('calidad')) {
                        rowBgColor = '#FADBD8';
                    } else if (turno.includes('matutino')) {
                        rowBgColor = '#E2EFDA';
                    } else if (turno.includes('vespertino')) {
                        rowBgColor = '#FFF2CC';
                    } else if (turno.includes('nocturno')) {
                        rowBgColor = '#DDEBF7';
                    }

                    const tr = document.createElement('tr');
                    tr.style.backgroundColor = rowBgColor;

                    let rowHtml = `
                                        <td style="font-weight: 500;">${row.Fecha || ''}</td>
                                        <td>${row.PC || ''}</td>
                                        <td style="font-weight: 500;">${row.Usuario || ''}</td>
                                        <td>${row.Turno || ''}</td>
                                    `;

                    accionesResumen.forEach(acc => {
                        const cant = row.Acciones[acc] || 0;
                        rowHtml += `<td class="text-center font-weight-bold" style="font-size: 0.95rem;">${cant > 0 ? cant : ''}</td>`;

                        // Sumar a contadores globales
                        const lowerAcc = acc.toLowerCase();
                        if (lowerAcc.includes('validado')) validados += cant;
                        else if (lowerAcc.includes('duplicar') || lowerAcc.includes('duplicado')) duplicados += cant;
                        else if (lowerAcc.includes('renombrar') || lowerAcc.includes('renombrado')) renombrados += cant;
                        else if (lowerAcc.includes('eliminar') || lowerAcc.includes('eliminado')) eliminados += cant;
                        else if (lowerAcc.includes('inconsistencia')) inconsistencias += cant;
                    });

                    const totalSellos = row.TotalSellos !== undefined ? row.TotalSellos : (row.TotalArchivos || 0);
                    const totalRegistros = row.TotalRegistros !== undefined ? row.TotalRegistros : 0;
                    const totalAmbas = row.TotalAmbas !== undefined ? row.TotalAmbas : (totalSellos + totalRegistros);

                    rowHtml += `
                                        <td class="text-center font-weight-bold" style="background-color: rgba(0,0,0,0.03);">${totalSellos}</td>
                                        <td class="text-center font-weight-bold" style="background-color: rgba(0,0,0,0.03);">${totalRegistros}</td>
                                        <td class="text-center font-weight-bold" style="background-color: rgba(0,0,0,0.06); color: #034D6D;">${totalAmbas}</td>
                                    `;

                    tr.innerHTML = rowHtml;
                    tbody.appendChild(tr);
                });

                // Actualizar etiquetas de contadores
                document.getElementById('lblValidados').textContent = `Validados: ${validados}`;
                document.getElementById('lblDuplicados').textContent = `Duplicados: ${duplicados}`;
                document.getElementById('lblRenombrados').textContent = `Renombrados: ${renombrados}`;
                document.getElementById('lblEliminados').textContent = `Eliminados: ${eliminados}`;
                document.getElementById('lblInconsistencias').textContent = `Inconsistencias: ${inconsistencias}`;
                document.getElementById('lblTotalProcesados').textContent = `Total Proc: ${validados + duplicados}`;

                // Inicializar DataTable
                dataTableInstance = $('#tablaAuditoria').DataTable({
                    responsive: false,
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/Spanish.json"
                    },
                    order: [[0, "desc"]]
                });
            }

            cargarDashboard();
        });
    </script>
@endsection