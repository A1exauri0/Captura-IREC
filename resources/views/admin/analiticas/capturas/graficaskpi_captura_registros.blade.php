@extends('layouts.chart')
@section('titulo', 'Reportes de desempeño capturas')
<!-- CSS de DataTables -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css?v={{ time() }}" rel="stylesheet">

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

    @keyframes animEntradaModal {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }
</style>

@section('contenido')

    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card shadow-lg" id="filtros">
                <div class="card-body">
                    <h1>GRÁFICAS DE DESEMPEÑO CAPTURAS REGISTROS</h1>
                    <div class="row mb-12 align-items-end" style="margin-bottom: 1rem;">
                        <div class="col-sm-6 col-md-3 mb-2">
                            <label for="fechaInicio" class="form-label font-weight-bold"
                                style="font-size: 0.85rem;">Desde:</label>
                            <input type="date" id="fechaInicio" name="fechaInicio" class="form-control">
                        </div>

                        <div class="col-sm-6 col-md-3 mb-2">
                            <label for="fechaFin" class="form-label font-weight-bold"
                                style="font-size: 0.85rem;">Hasta:</label>
                            <input type="date" id="fechaFin" name="fechaFin" class="form-control">
                        </div>

                        <div class="col-sm-6 col-md-3 mb-2">
                            <label class="form-label d-none d-md-block"
                                style="visibility: hidden; margin-bottom: 8px;">Excel:</label>
                            <button type="button" id="btnModalExcelDelegacion" class="btn btn-success btn-block"
                                style="background-color: #218838; border-color: #218838; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;"
                                title="Descargar Reporte Detallado por Delegación (Excel)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                                    <path
                                        d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 11.293 4L9.293 0zM9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1zM5.884 6.68 8 9.219l2.116-2.54a.5.5 0 1 1 .768.641L8.651 10l2.233 2.68a.5.5 0 0 1-.768.64L8 10.781l-2.116 2.54a.5.5 0 0 1-.768-.641L7.349 10 5.116 7.32a.5.5 0 1 1 .768-.64z" />
                                </svg>
                                Excel por Delegación
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para descarga por delegación -->
    <form action="{{ route('admin.analytics_capturas.reportes.descargar_excel_delegacion') }}" method="POST"
        id="formDescargarExcelDelegacion" style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="modalExcelFechaInicio">
        <input type="hidden" name="fin" id="modalExcelFechaFin">
        <input type="hidden" name="delegacion_id" id="modalExcelDelegacionId">
    </form>

    <!-- Modal Selección de Delegación (Overlay Personalizado) -->
    <div id="modalExcelDelegacion"
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 999999; align-items: center; justify-content: center;">
        <div
            style="max-width: 480px; width: 90%; background-color: #ffffff; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); overflow: hidden; animation: animEntradaModal 0.2s ease-out; margin: auto;">
            <div class="py-3 px-4 d-flex align-items-center justify-content-between"
                style="background: linear-gradient(135deg, #034d6d, #0284c7); color: white; min-height: 50px;">
                <h5 class="font-weight-bold m-0"
                    style="color: white; font-size: 1.05rem; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                        class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                        <path
                            d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 11.293 4L9.293 0zM9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1zM5.884 6.68 8 9.219l2.116-2.54a.5.5 0 1 1 .768.641L8.651 10l2.233 2.68a.5.5 0 0 1-.768.64L8 10.781l-2.116 2.54a.5.5 0 0 1-.768-.641L7.349 10 5.116 7.32a.5.5 0 1 1 .768-.64z" />
                    </svg>
                    Excel por Delegación
                </h5>
                <button type="button" class="modal-close-btn"
                    style="opacity: 0.8; font-size: 1.4rem; background: none; border: none; cursor: pointer; color: white; line-height: 1;">
                    &times;
                </button>
            </div>
            <div class="px-4 py-3" style="background-color: #ffffff;">
                <div class="alert alert-info py-2 px-3 mb-3"
                    style="border-radius: 8px; font-size: 0.85rem; background-color: #f0f9ff; color: #0369a1; border-left: 4px solid #0284c7; line-height: 1.4; margin-bottom: 12px;">
                    <strong id="modalFechasTexto">Rango seleccionado: -</strong>
                </div>

                <div class="form-group mb-0">
                    <label for="selectDelegacionModal" class="font-weight-bold text-secondary"
                        style="font-size: 0.85rem; margin-bottom: 4px; display: block;">
                        Seleccione la Delegación:
                    </label>
                    <select id="selectDelegacionModal" class="form-control"
                        style="border-radius: 8px; border: 1.5px solid #cbd5e1; height: 38px; font-size: 0.9rem; display: block !important; width: 100%;">
                        <option value="todas">Todas las delegaciones </option>
                        @if(isset($delegaciones))
                            @foreach($delegaciones as $del)
                                <option value="{{ $del->id }}">{{ $del->nombre }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
            <div class="py-2 px-4 border-top d-flex justify-content-end align-items-center"
                style="background-color: #f8fafc; gap: 8px; min-height: 52px;">
                <button type="button" class="modal-close-btn btn btn-secondary font-weight-bold"
                    style="border-radius: 8px; background-color: #64748b; color: white; border: none; padding: 5px 14px; font-size: 0.85rem; height: 34px;">
                    Cancelar
                </button>
                <button type="button" id="btnConfirmarDescargaExcel" class="btn btn-success font-weight-bold"
                    style="background-color: #218838; border-color: #218838; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; padding: 5px 16px; font-size: 0.85rem; height: 34px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                        class="bi bi-download" viewBox="0 0 16 16">
                        <path
                            d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 1 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z" />
                        <path
                            d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z" />
                    </svg>
                    Descargar
                </button>
            </div>
        </div>
    </div>

    <div class="contenedor-kpis">
        <div class="row">
            <!-- Fila de KPI Cards -->
            <div class="d-flex flex-wrap mb-4" id="kpiCardsContainer" style="width: 100%; margin: 0 -0.5rem;"></div>

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

            // COLORES POR ACCIÓN
            function getColorByAction(accion) {
                const lower = accion.toLowerCase();
                if (lower.includes('validado')) return 'rgb(192, 255, 192)';
                if (lower.includes('renombrar') || lower.includes('renombrado')) return 'rgb(222, 184, 135)';
                if (lower.includes('eliminar') || lower.includes('eliminado')) return 'rgb(255, 128, 128)';
                if (lower.includes('inconsistencia')) return 'rgb(255, 255, 192)';
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

                    const res = await fetch('{{ route("admin.analytics_capturas.reportes.dashboard_capturas") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ inicio, fin }),
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
                const labels = data.map(x => x.turno);
                const valores = data.map(x => x.total);

                const total = valores.reduce((a, b) => a + b, 0);
                document.getElementById('totalTurnos').textContent = 'Total: ' + formatearNumero(total);

                const bgColors = labels.map(l => {
                    const lower = l.toLowerCase();
                    if (lower.includes('matutino')) return 'rgba(255, 206, 86, 0.7)';
                    if (lower.includes('vespertino')) return 'rgba(75, 192, 192, 0.7)';
                    if (lower.includes('nocturno')) return 'rgba(153, 102, 255, 0.7)';
                    return 'rgba(201, 203, 207, 0.7)';
                });

                if (chartTurnos) chartTurnos.destroy();

                chartTurnos = new Chart(document.getElementById('graficaTurnos'), {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{
                            data: valores,
                            backgroundColor: bgColors,
                            borderWidth: 1
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
                                    return value > 0 ? `${formatearNumero(value)} (${porcentaje})` : '';
                                },
                                font: { weight: 'bold' }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            function renderAccionesGlobal(data) {
                const labels = data.map(x => x.accion);
                const valores = data.map(x => x.total);

                const total = valores.reduce((a, b) => a + b, 0);
                document.getElementById('totalAccionesGlobal').textContent = 'Total: ' + formatearNumero(total);

                const bgColors = labels.map(l => getColorByAction(l));

                if (chartAccionesGlobal) chartAccionesGlobal.destroy();

                chartAccionesGlobal = new Chart(document.getElementById('graficaAccionesGlobal'), {
                    type: 'pie',
                    data: {
                        labels,
                        datasets: [{
                            data: valores,
                            backgroundColor: bgColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Permitir ajustar el tamaño al contenedor
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

                const mapAcciones = {
                    'Validado': 0,
                    'Renombrar': 0,
                    'Duplicar': 0,
                    'Eliminado': 0,
                    'Inconsistencia': 0
                };

                accionesGlobal.forEach(x => {
                    const nombre = x.accion;
                    if (nombre in mapAcciones) {
                        mapAcciones[nombre] = x.total;
                    }
                    if (nombre.toLowerCase().includes('validado') || nombre.toLowerCase().includes('duplicar')) {
                        totalCalidad += x.total;
                        totalCalidadImagenes += x.paginas || 0;
                    }
                });

                Object.keys(mapAcciones).forEach(accionName => {
                    const total = mapAcciones[accionName];
                    const color = getColorByAction(accionName);

                    const cardHtml = `
                                        <div class="mb-3 px-2" style="flex: 1 1 0px; min-width: 150px;">
                                            <div class="card border-0 shadow-sm" style="border-radius: 12px; background: linear-gradient(135deg, #ffffff, #f9fafb); border-left: 4px solid ${color} !important; height: 100%;">
                                                <div class="card-body p-3 d-flex flex-column justify-content-center">
                                                    <p class="text-xs uppercase tracking-wider font-semibold mb-1" style="font-size: 0.75rem; color: #6b7280; font-weight: 600; margin-bottom: 0.25rem;">${accionName}</p>
                                                    <h3 class="text-2xl font-bold" style="font-size: 1.5rem; font-weight: 700; color: #1f2937; margin: 0;">${formatearNumero(total)}</h3>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                    container.insertAdjacentHTML('beforeend', cardHtml);
                });

                const calidadHtml = `
                                    <div class="mb-3 px-2" style="flex: 1 1 0px; min-width: 150px;">
                                        <div class="card border-0 shadow-sm" style="border-radius: 12px; background: linear-gradient(135deg, #059669, #047857); color: #ffffff; height: 100%;">
                                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                                <p class="text-xs uppercase tracking-wider font-semibold mb-1" style="font-size: 0.75rem; color: #a7f3d0; font-weight: 600; margin-bottom: 0.25rem;">Calidad Registros</p>
                                                <h3 class="text-2xl font-bold" style="font-size: 1.5rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalCalidad)}</h3>
                                            </div>
                                        </div>
                                    </div>
                                `;
                container.insertAdjacentHTML('beforeend', calidadHtml);

                const calidadImagenesHtml = `
                                    <div class="mb-3 px-2" style="flex: 1 1 0px; min-width: 150px;">
                                        <div class="card border-0 shadow-sm" style="border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #ffffff; height: 100%;">
                                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                                <p class="text-xs uppercase tracking-wider font-semibold mb-1" style="font-size: 0.75rem; color: #ddd6fe; font-weight: 600; margin-bottom: 0.25rem;">Imágenes en Calidad </p>
                                                <h3 class="text-2xl font-bold" style="font-size: 1.5rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalCalidadImagenes)}</h3>
                                            </div>
                                        </div>
                                    </div>
                                `;
                container.insertAdjacentHTML('beforeend', calidadImagenesHtml);
            }

            // GESTIÓN DE MODAL Y DESCARGA POR DELEGACIÓN
            const modalEl = document.getElementById('modalExcelDelegacion');
            const btnModalExcel = document.getElementById('btnModalExcelDelegacion');
            const btnConfirmar = document.getElementById('btnConfirmarDescargaExcel');
            const closeBtns = document.querySelectorAll('.modal-close-btn');

            function abrirModalDelegacion() {
                const inicio = fechaInicioInput.value;
                const fin = fechaFinInput.value;

                if (!inicio || !fin) {
                    alert('Por favor seleccione un rango de fechas válido.');
                    return;
                }

                const formatearFechaDisplay = (f) => {
                    if (!f) return '-';
                    const parts = f.split('-');
                    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
                    return f;
                };

                document.getElementById('modalFechasTexto').textContent = `Rango seleccionado: ${formatearFechaDisplay(inicio)} al ${formatearFechaDisplay(fin)}`;
                modalEl.style.display = 'flex';
            }

            function cerrarModalDelegacion() {
                modalEl.style.display = 'none';
            }

            if (btnModalExcel) {
                btnModalExcel.addEventListener('click', abrirModalDelegacion);
            }

            closeBtns.forEach(btn => {
                btn.addEventListener('click', cerrarModalDelegacion);
            });

            if (modalEl) {
                modalEl.addEventListener('click', function (e) {
                    if (e.target === modalEl) {
                        cerrarModalDelegacion();
                    }
                });
            }

            if (btnConfirmar) {
                btnConfirmar.addEventListener('click', function () {
                    const inicio = fechaInicioInput.value;
                    const fin = fechaFinInput.value;
                    const delegacionId = document.getElementById('selectDelegacionModal').value;

                    document.getElementById('modalExcelFechaInicio').value = inicio;
                    document.getElementById('modalExcelFechaFin').value = fin;
                    document.getElementById('modalExcelDelegacionId').value = delegacionId;

                    document.getElementById('formDescargarExcelDelegacion').submit();
                    cerrarModalDelegacion();
                });
            }

            cargarDashboard();
        });
    </script>
@endsection