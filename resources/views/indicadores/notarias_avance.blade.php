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
                    <h1>GRÁFICAS DE DESEMPEÑO CAPTURAS NOTARÍAS</h1>
                    <div class="row mb-12" style="margin-bottom: 1rem;">
                        <div class="col-sm-6 col-md-3 mb-2">
                            <label for="fechaInicio" class="form-label">Desde:</label>
                            <input type="date" id="fechaInicio" name="fechaInicio" class="form-control">
                        </div>

                        <div class="col-sm-6 col-md-3 mb-2">
                            <label for="fechaFin" class="form-label">Hasta:</label>
                            <input type="date" id="fechaFin" name="fechaFin" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="contenedor-kpis">
        <!-- Fila de KPI Cards -->
        <div class="d-flex flex-wrap mb-4" id="kpiCardsContainer" style="width: 100%; margin: 0 -0.5rem;"></div>

        <div class="row">

            <!-- Gráfica de Productividad General al Día (Archivos PDF Capturados) (Líneas) -->
            <div class="col-12 col-md-12 mb-3" style="height: 500px">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Productividad General al Día (Archivos PDF Capturados)</h5>
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

            <!-- Gráfica de Productividad General al Día (Imágenes capturadas) (Puntos) -->
            <div class="col-12 col-md-12 mb-3" style="height: 500px">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Productividad General al Día (Imágenes capturadas)</h5>
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

            <!-- Gráfica de Desempeño por Notaría (Barras Dobles) -->
            <div class="col-12 col-md-12 mb-3">
                <div class="card" style="height: 500px;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Desempeño por Notaría (Archivos PDF e Imágenes capturadas)</h5>
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
                const lower = String(accion || '').toLowerCase();
                if (lower.includes('valid')) return '#10b981';
                if (lower.includes('renombr')) return '#0284c7';
                if (lower.includes('inconsistencia') || lower.includes('recorte') || lower.includes('eliminar') || lower.includes('eliminado')) return '#ef4444';
                return '#64748b';
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

                    const res = await fetch('{{ route("admin.analytics_digitalizacion.reportes.dashboard_capturas_notarias") }}', {
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
                    renderDelegaciones(data.por_notaria || []);
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
                const etiquetas = data.map(x => x.notaria);
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
                    'Inconsistencia': 0
                };

                accionesGlobal.forEach(x => {
                    const lower = String(x.accion || '').toLowerCase();
                    let nombre = x.accion;
                    if (lower.includes('valid')) nombre = 'Validado';
                    else if (lower.includes('renombr')) nombre = 'Renombrar';
                    else if (lower.includes('inconsistencia')) nombre = 'Inconsistencia';
                    if (nombre in mapAcciones) {
                        mapAcciones[nombre] += x.total;
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
            }

            cargarDashboard();
        });
    </script>
@endsection