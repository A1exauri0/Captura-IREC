@extends('layouts.chart')
@section('titulo', 'Reportes de desempeño capturas')
<!-- CSS de DataTables -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<style>
    .spinner-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10;
        border-radius: 8px;
        transition: opacity 0.3s ease;
    }

    .spinner-circle {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #4f46e5;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

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
                    <h1>GRÁFICAS DE DESEMPEÑO CAPTURAS SELLOS</h1>
                    <div class="row mb-12 align-items-end" style="margin-bottom: 1rem;">
                        <div class="col-12 col-sm-6 col-md-3 col-lg-2 mb-2">
                            <label for="fechaInicio" class="form-label font-weight-bold" style="font-size: 0.85rem;">Desde:</label>
                            <input type="date" id="fechaInicio" name="fechaInicio" class="form-control">
                        </div>

                        <div class="col-12 col-sm-6 col-md-3 col-lg-2 mb-2">
                            <label for="fechaFin" class="form-label font-weight-bold" style="font-size: 0.85rem;">Hasta:</label>
                            <input type="date" id="fechaFin" name="fechaFin" class="form-control">
                        </div>

                        <div class="col-12 col-sm-6 col-md-3 col-lg-2 mb-2">
                            <label for="tipoSello" class="form-label font-weight-bold" style="font-size: 0.85rem;">Tipo de Sello:</label>
                            <select id="tipoSello" name="tipoSello" class="form-control">
                                <option value="">Todos</option>
                                <option value="Sellos">Sellos</option>
                                <option value="Boleta de inscripcion">Boleta de inscripción</option>
                                <option value="Boleta de ingreso">Boleta de ingreso</option>
                                <option value="Boleta de rechazo">Boleta de rechazo</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-3 col-lg-6 mb-2">
                            <label class="form-label d-none d-lg-block"
                                style="visibility: hidden; margin-bottom: 8px;">Reportes:</label>
                            <div class="d-flex flex-wrap" style="gap: 8px;">
                                <button type="button" id="btnDescargarExcel" class="btn btn-success"
                                    style="background-color: #28a745; border-color: #28a745; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; flex: 1 1 140px;"
                                    title="Descargar Reporte Detallado (Excel)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        class="bi bi-file-earmark-excel" viewBox="0 0 16 16">
                                        <path
                                            d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z" />
                                        <path
                                            d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.3 1.25c.085.13.11.307.11.95h2.07c-.642-.64-1.54-.83-2.18-.95zm.7 2.2V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V3.45H10z" />
                                    </svg>
                                    Excel Detallado
                                </button>
                                <button type="button" id="btnDescargarExcelDelegaciones" class="btn btn-success"
                                    style="background-color: #218838; border-color: #218838; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; flex: 1 1 140px;"
                                    title="Descargar Reporte por Delegaciones (Excel)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                                        <path
                                            d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 11.293 4L9.293 0zM9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1zM5.884 6.68 8 9.219l2.116-2.54a.5.5 0 1 1 .768.641L8.651 10l2.233 2.68a.5.5 0 0 1-.768.64L8 10.781l-2.116 2.54a.5.5 0 0 1-.768-.641L7.349 10 5.116 7.32a.5.5 0 1 1 .768-.64z" />
                                    </svg>
                                    Delegaciones
                                </button>
                                <button type="button" id="btnDescargarExcelDias" class="btn btn-success"
                                    style="background-color: #1e7e34; border-color: #1e7e34; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; flex: 1 1 140px;"
                                    title="Descargar Reporte de Captura por Día (Excel)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        class="bi bi-calendar-check-fill" viewBox="0 0 16 16">
                                        <path d="M4 .5a.5.5 0 0 0-1 0V1H2a2 2 0 0 0-2 2v1h16V3a2 2 0 0 0-2-2h-1V.5a.5.5 0 0 0-1 0V1H4V.5zM16 14a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V5h16v9zm-5.146-5.146a.5.5 0 0 0-.708-.708L8 10.293 6.854 9.146a.5.5 0 1 0-.708.708L7.293 11l-1.147 1.146a.5.5 0 0 0 .708.708L8 11.707l1.146 1.147z"/>
                                    </svg>
                                    Excel (por día)
                                </button>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para descarga -->
    <form action="{{ route('admin.analytics_sellos.reportes.descargar_excel') }}" method="POST" id="formDescargarExcel"
        style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelFechaInicio">
        <input type="hidden" name="fin" id="excelFechaFin">
        <input type="hidden" name="tipo_sello" id="excelTipoSello">
    </form>

    <!-- Formulario oculto para descarga por delegaciones -->
    <form action="{{ route('admin.analytics_sellos.reportes.descargar_excel_delegaciones') }}" method="POST"
        id="formDescargarExcelDelegaciones" style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelDelegacionesFechaInicio">
        <input type="hidden" name="fin" id="excelDelegacionesFechaFin">
        <input type="hidden" name="tipo_sello" id="excelDelegacionesTipoSello">
    </form>

    <!-- Formulario oculto para descarga por dia -->
    <form action="{{ route('admin.analytics_sellos.reportes.descargar_excel_dias') }}" method="POST"
        id="formDescargarExcelDias" style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelDiasFechaInicio">
        <input type="hidden" name="fin" id="excelDiasFechaFin">
        <input type="hidden" name="tipo_sello" id="excelDiasTipoSello">
    </form>

    <div class="contenedor-kpis">
        <!-- Fila de KPI Cards -->
        <div class="d-flex flex-wrap mb-4" id="kpiCardsContainer" style="width: 100%; margin: 0 -0.5rem;"></div>

        <div class="row">

            <!-- Gráfica de Productividad General al Día (Archivos) (Líneas) -->
            <div class="col-12 col-md-12 mb-3" style="height: 500px">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Productividad General al Día (Archivos)</h5>
                        <span id="totalDias" style="font-weight: bold; color: #4bc0c0; font-size: 0.9rem;">Total: 0</span>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <!-- Contenedor centrado para la gráfica por días -->
                        <div style="min-width: 200px; width: 90%; margin: 0 auto;">
                            <canvas id="graficaDias" style="max-height: 350px !important;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica de Productividad General al Día (Imágenes) (Puntos) -->
            <div class="col-12 col-md-12 mb-3" style="height: 500px">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Productividad General al Día (Imágenes/Páginas)</h5>
                        <span id="totalPaginasDias" style="font-weight: bold; color: #ff6384; font-size: 0.9rem;">Total:
                            0</span>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <!-- Contenedor centrado para la gráfica de puntos de imágenes -->
                        <div style="min-width: 200px; width: 90%; margin: 0 auto;">
                            <canvas id="graficaPaginasDias" style="max-height: 350px !important;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica de Desempeño por Delegación (Barras Dobles) -->
            <div class="col-12 col-md-12 mb-3">
                <div class="card" style="height: 500px;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Desempeño por Delegación (Archivos e Imágenes)</h5>
                        <span id="totalDelegaciones" style="font-weight: bold; font-size: 0.9rem;"><span
                                style="color: #36a2eb;">Archivos: 0</span> | <span style="color: #ff6384;">Imágenes:
                                0</span></span>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 400px; width: 100%;">
                            <canvas id="graficaDelegaciones"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica de Acciones Globales (Pastel) -->
            <div class="col-12 col-md-6 mb-3" style="height: 450px">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Acciones Realizadas (General)</h5>
                        <span id="totalAccionesGlobal" style="font-weight: bold; color: #6c757d; font-size: 0.9rem;">Total:
                            0</span>
                    </div>
                    <div class="card-body" style="max-height: 380px; overflow: hidden;">
                        <!-- Contenedor para centrar y limitar la altura del gráfico de pastel -->
                        <div style="min-width: 200px; display: flex; justify-content: center; width: 100%;">
                            <canvas id="graficaAccionesGlobal" style="max-height: 320px !important;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica de Productividad por Turnos (Dona) -->
            <div class="col-12 col-md-6 mb-3" style="height: 450px">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Productividad por Turnos</h5>
                        <span id="totalTurnos" style="font-weight: bold; color: #6c757d; font-size: 0.9rem;">Total: 0</span>
                    </div>
                    <div class="card-body" style="max-height: 380px; overflow: hidden;">
                        <div style="min-width: 200px; display: flex; justify-content: center; width: 100%;">
                            <canvas id="graficaTurnos" style="max-height: 320px !important;"></canvas>
                        </div>
                    </div>
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

            let chartDias;
            let chartPaginasDias;
            let chartTurnos;
            let chartAccionesGlobal;
            let chartDelegaciones;

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

            // Reportes
            const btnDescargarExcel = document.getElementById('btnDescargarExcel');
            btnDescargarExcel.addEventListener('click', function () {
                document.getElementById('excelFechaInicio').value = fechaInicioInput.value;
                document.getElementById('excelFechaFin').value = fechaFinInput.value;
                document.getElementById('excelTipoSello').value = tipoSelloInput.value;
                document.getElementById('formDescargarExcel').submit();
            });

            const btnDescargarExcelDelegaciones = document.getElementById('btnDescargarExcelDelegaciones');
            if (btnDescargarExcelDelegaciones) {
                btnDescargarExcelDelegaciones.addEventListener('click', function () {
                    document.getElementById('excelDelegacionesFechaInicio').value = fechaInicioInput.value;
                    document.getElementById('excelDelegacionesFechaFin').value = fechaFinInput.value;
                    document.getElementById('excelDelegacionesTipoSello').value = tipoSelloInput.value;
                    document.getElementById('formDescargarExcelDelegaciones').submit();
                });
            }

            const btnDescargarExcelDias = document.getElementById('btnDescargarExcelDias');
            if (btnDescargarExcelDias) {
                btnDescargarExcelDias.addEventListener('click', function () {
                    document.getElementById('excelDiasFechaInicio').value = fechaInicioInput.value;
                    document.getElementById('excelDiasFechaFin').value = fechaFinInput.value;
                    document.getElementById('excelDiasTipoSello').value = tipoSelloInput.value;
                    document.getElementById('formDescargarExcelDias').submit();
                });
            }

            // COLORES POR ACCIÓN
            function getColorByAction(accion) {
                const lower = accion.toLowerCase();
                if (lower.includes('validado')) return 'rgb(192, 255, 192)';
                if (lower.includes('renombrar') || lower.includes('renombrado')) return 'rgb(222, 184, 135)';
                if (lower.includes('eliminar') || lower.includes('eliminado')) return 'rgb(255, 128, 128)';
                if (lower.includes('recorte') || lower.includes('inconsistencia')) return 'rgb(255, 255, 192)';
                if (lower.includes('duplicar') || lower.includes('duplicado')) return 'rgb(192, 235, 255)';
                return 'rgb(60, 179, 113)';
            }

            let abortController;

            function setCargando(cargando) {
                const selectoresGraficas = [
                    '#graficaDias',
                    '#graficaPaginasDias',
                    '#graficaDelegaciones',
                    '#graficaAccionesGlobal',
                    '#graficaTurnos'
                ];

                selectoresGraficas.forEach(sel => {
                    const canvas = document.querySelector(sel);
                    if (!canvas) return;

                    const contenedorPadre = canvas.parentElement;
                    if (!contenedorPadre) return;

                    contenedorPadre.style.position = 'relative';

                    let spinner = contenedorPadre.querySelector('.spinner-overlay');

                    if (cargando) {
                        if (!spinner) {
                            spinner = document.createElement('div');
                            spinner.className = 'spinner-overlay';
                            spinner.innerHTML = '<div class="spinner-circle"></div>';
                            contenedorPadre.appendChild(spinner);
                        }
                        spinner.style.display = 'flex';
                        spinner.style.opacity = '1';
                    } else {
                        if (spinner) {
                            spinner.style.display = 'none';
                            spinner.style.opacity = '0';
                        }
                    }
                });
            }

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
                    setCargando(true);

                    const res = await fetch('{{ route("admin.analytics_sellos.reportes.dashboard_sellos") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ inicio, fin, tipo_sello }),
                        signal: signal
                    });

                    if (!res.ok) throw new Error('Error al cargar datos');

                    const data = await res.json();
                    console.log('DATA DASHBOARD', data);

                    renderDias(data.por_dia || []);
                    renderPaginasDias(data.paginas_por_dia || []);
                    renderTurnos(data.por_turno || []);
                    renderAccionesGlobal(data.acciones_global || []);
                    renderDelegaciones(data.por_delegacion || []);
                    renderKpiCards(data.acciones_global || []);
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
                        setCargando(false);
                    }
                }
            }

            function renderDias(data) {
                const labels = data.map(x => x.fecha);
                const valores = data.map(x => x.total);

                const total = valores.reduce((a, b) => a + b, 0);
                document.getElementById('totalDias').textContent = 'Total: ' + formatearNumero(total);

                if (chartDias) chartDias.destroy();

                chartDias = new Chart(document.getElementById('graficaDias'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Archivos procesados',
                            data: valores,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            pointRadius: 5,
                            borderWidth: 3,
                            tension: 0.1
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

            function renderPaginasDias(data) {
                const labels = data.map(x => x.fecha);
                const valores = data.map(x => x.total);

                const total = valores.reduce((a, b) => a + b, 0);
                document.getElementById('totalPaginasDias').textContent = 'Total: ' + formatearNumero(total);

                if (chartPaginasDias) chartPaginasDias.destroy();

                chartPaginasDias = new Chart(document.getElementById('graficaPaginasDias'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Imágenes procesadas',
                            data: valores,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            pointRadius: 5,
                            borderWidth: 3,
                            tension: 0.1
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

            function renderTurnos(data) {
                const etiquetas = data.map(x => x.turno);
                const valores = data.map(x => x.total);

                const total = valores.reduce((a, b) => a + b, 0);
                document.getElementById('totalTurnos').textContent = 'Total: ' + formatearNumero(total);

                if (chartTurnos) chartTurnos.destroy();

                chartTurnos = new Chart(document.getElementById('graficaTurnos'), {
                    type: 'doughnut',
                    data: {
                        labels: etiquetas,
                        datasets: [{
                            data: valores,
                            backgroundColor: [
                                'rgba(255, 205, 86, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(153, 102, 255, 0.7)',
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(75, 192, 192, 0.7)'
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            datalabels: {
                                color: '#000',
                                formatter: (value, ctx) => {
                                    let suma = 0;
                                    let datosArray = ctx.chart.data.datasets[0].data;
                                    datosArray.forEach(val => {
                                        suma += val;
                                    });
                                    let porcentaje = suma > 0 ? (value * 100 / suma).toFixed(1) + "%" : "0%";
                                    return porcentaje;
                                },
                                font: { weight: 'bold' }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        let etiqueta = context.label || '';
                                        if (etiqueta) {
                                            etiqueta += ': ';
                                        }
                                        if (context.parsed !== null) {
                                            etiqueta += formatearNumero(context.parsed);
                                        }
                                        return etiqueta;
                                    }
                                }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            function renderAccionesGlobal(data) {
                const etiquetas = data.map(x => x.accion);
                const valores = data.map(x => x.total);

                const total = valores.reduce((a, b) => a + b, 0);
                document.getElementById('totalAccionesGlobal').textContent = 'Total: ' + formatearNumero(total);

                const bgColors = etiquetas.map(l => getColorByAction(l));

                if (chartAccionesGlobal) chartAccionesGlobal.destroy();

                chartAccionesGlobal = new Chart(document.getElementById('graficaAccionesGlobal'), {
                    type: 'pie',
                    data: {
                        labels: etiquetas,
                        datasets: [{
                            data: valores,
                            backgroundColor: bgColors,
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            datalabels: {
                                color: '#000',
                                formatter: (value, ctx) => {
                                    let suma = 0;
                                    let datosArray = ctx.chart.data.datasets[0].data;
                                    datosArray.forEach(val => {
                                        suma += val;
                                    });
                                    let porcentaje = suma > 0 ? (value * 100 / suma).toFixed(1) + "%" : "0%";
                                    return porcentaje;
                                },
                                font: { weight: 'bold' }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        let etiqueta = context.label || '';
                                        if (etiqueta) {
                                            etiqueta += ': ';
                                        }
                                        if (context.parsed !== null) {
                                            etiqueta += formatearNumero(context.parsed);
                                        }
                                        return etiqueta;
                                    }
                                }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            function renderDelegaciones(data) {
                const etiquetas = data.map(x => x.delegacion);
                const totalArchivos = data.map(x => x.archivos);
                const totalImagenes = data.map(x => x.imagenes);

                const sumaArchivos = totalArchivos.reduce((a, b) => a + b, 0);
                const sumaImagenes = totalImagenes.reduce((a, b) => a + b, 0);
                document.getElementById('totalDelegaciones').innerHTML = `<span style="color: #36a2eb;">Archivos: ${formatearNumero(sumaArchivos)}</span> | <span style="color: #ff6384;">Imágenes: ${formatearNumero(sumaImagenes)}</span>`;

                if (chartDelegaciones) chartDelegaciones.destroy();

                chartDelegaciones = new Chart(document.getElementById('graficaDelegaciones'), {
                    type: 'bar',
                    data: {
                        labels: etiquetas,
                        datasets: [
                            {
                                label: 'Archivos Trabajados',
                                data: totalArchivos,
                                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                borderColor: 'rgb(54, 162, 235)',
                                borderWidth: 1
                            },
                            {
                                label: 'Imágenes Procesadas',
                                data: totalImagenes,
                                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                borderColor: 'rgb(255, 99, 132)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
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

            function renderKpiCards(accionesGlobal) {
                const container = document.getElementById('kpiCardsContainer');
                if (!container) return;
                container.innerHTML = '';

                let totalCalidad = 0;
                let totalCalidadImagenes = 0;

                const mapArchivos = {
                    'Validado': 0,
                    'Renombrar': 0,
                    'Duplicar': 0,
                    'Eliminado': 0,
                    'Recorte Hojas': 0
                };

                const mapImagenes = {
                    'Validado': 0,
                    'Renombrar': 0,
                    'Duplicar': 0,
                    'Eliminado': 0,
                    'Recorte Hojas': 0
                };

                accionesGlobal.forEach(x => {
                    let nombre = x.accion;
                    if (nombre.toLowerCase().includes('recorte') || nombre.toLowerCase().includes('inconsistencia')) {
                        nombre = 'Recorte Hojas';
                    }
                    if (nombre in mapArchivos) {
                        mapArchivos[nombre] += x.total || 0;
                        mapImagenes[nombre] += x.paginas || 0;
                    }
                    if (nombre.toLowerCase().includes('validado') || nombre.toLowerCase().includes('duplicar') || nombre.toLowerCase().includes('renombrar')) {
                        totalCalidad += x.total || 0;
                        totalCalidadImagenes += x.paginas || 0;
                    }
                });

                // --- FILA 1: ARCHIVOS (PDFs) ---
                const rowPdfs = document.createElement('div');
                rowPdfs.className = 'row w-100 mb-2 g-2 mx-0';

                let clickCountValidado = 0;
                let clickTimerValidado = null;

                Object.keys(mapArchivos).forEach(accionName => {
                    const total = mapArchivos[accionName];
                    const color = getColorByAction(accionName);
                    const isValidado = accionName === 'Validado';

                    const cardHtml = `
                        <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #ffffff, #f9fafb); border-left: 4px solid ${color} !important;">
                                <div class="card-body p-3 d-flex flex-column justify-content-center">
                                    <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #6b7280; font-weight: 600; margin-bottom: 0.25rem;" title="${accionName} (PDFs)">${accionName} (PDFs)</p>
                                    <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #1f2937; margin: 0;">${formatearNumero(total)}</h3>
                                </div>
                            </div>
                        </div>
                    `;
                    rowPdfs.insertAdjacentHTML('beforeend', cardHtml);

                    if (isValidado) {
                        const cardEl = rowPdfs.lastElementChild;
                        cardEl.addEventListener('click', function () {
                            clickCountValidado++;
                            clearTimeout(clickTimerValidado);
                            clickTimerValidado = setTimeout(() => {
                                clickCountValidado = 0;
                            }, 3000);

                            if (clickCountValidado >= 5) {
                                clickCountValidado = 0;
                                if (confirm('¿Deseas realizar el proceso de actualizacion?')) {
                                    fetch('{{ route("admin.analytics_sellos.reportes.reasignar_registros_kpi") }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                        }
                                    })
                                        .then(res => res.json())
                                        .then(resData => {
                                            alert(resData.message);
                                            cargarDashboard();
                                        })
                                        .catch(err => {
                                            alert('Error al reasignar: ' + err.message);
                                        });
                                }
                            }
                        });
                    }
                });

                // Card Verde de Calidad Sellos (Arriba)
                const calidadHtml = `
                    <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #059669, #047857); color: #ffffff;">
                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #a7f3d0; font-weight: 600; margin-bottom: 0.25rem;">Calidad Sellos</p>
                                <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalCalidad)}</h3>
                            </div>
                        </div>
                    </div>
                `;
                rowPdfs.insertAdjacentHTML('beforeend', calidadHtml);
                container.appendChild(rowPdfs);

                // --- FILA 2: IMÁGENES / PÁGINAS ---
                const rowImagenes = document.createElement('div');
                rowImagenes.className = 'row w-100 mb-2 g-2 mx-0';

                Object.keys(mapImagenes).forEach(accionName => {
                    const totalImg = mapImagenes[accionName];
                    const color = getColorByAction(accionName);

                    const cardHtml = `
                        <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #ffffff, #f9fafb); border-left: 4px solid ${color} !important;">
                                <div class="card-body p-3 d-flex flex-column justify-content-center">
                                    <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #6b7280; font-weight: 600; margin-bottom: 0.25rem;" title="${accionName} (Imágenes)">${accionName} (Imágenes)</p>
                                    <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #1f2937; margin: 0;">${formatearNumero(totalImg)}</h3>
                                </div>
                            </div>
                        </div>
                    `;
                    rowImagenes.insertAdjacentHTML('beforeend', cardHtml);
                });

                // Card Morada de Imágenes en Calidad (Abajo)
                const calidadImagenesHtml = `
                    <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #ffffff;">
                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #ddd6fe; font-weight: 600; margin-bottom: 0.25rem;">Imágenes en Calidad</p>
                                <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalCalidadImagenes)}</h3>
                            </div>
                        </div>
                    </div>
                `;
                rowImagenes.insertAdjacentHTML('beforeend', calidadImagenesHtml);
                container.appendChild(rowImagenes);
            }

            cargarDashboard();
        });
    </script>
@endsection