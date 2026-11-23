@extends('layouts.chart')
@section('titulo', 'Reportes de desempeño capturas catastro')
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
        background: rgba(255, 255, 255, 0.75);
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
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
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

    .h3, h3 {
        font-size: 1.25rem !important;
    }

    .h5, h5 {
        font-size: 1rem !important;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 1.2rem;
        margin-top: 1rem;
    }
</style>

@section('contenido')

    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card shadow-lg" id="filtros">
                <div class="card-body">
                    <h1 class="font-weight-bold text-gray-800" style="font-size: 1.5rem; margin-bottom: 1.2rem;">GRÁFICAS DE DESEMPEÑO CAPTURAS CATASTRO</h1>
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
                            <label for="tipoCatastro" class="form-label font-weight-bold" style="font-size: 0.85rem;">Tipo de Catastro:</label>
                            <select id="tipoCatastro" name="tipoCatastro" class="form-control">
                                <option value="">Todos</option>
                                <option value="Urbano">Catastro Urbano</option>
                                <option value="Rustico">Catastro Rústico</option>
                                <option value="Sin Estudio">Catastro Sin Estudio</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-3 col-lg-6 mb-2">
                            <label class="form-label d-none d-lg-block" style="visibility: hidden; margin-bottom: 8px;">Reportes:</label>
                            <div class="d-flex flex-wrap" style="gap: 8px;">
                                <button type="button" id="btnDescargarExcel" class="btn btn-success"
                                    style="background-color: #28a745; border-color: #28a745; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; flex: 1 1 140px;"
                                    title="Descargar Reporte Detallado (Excel)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        class="bi bi-file-earmark-excel" viewBox="0 0 16 16">
                                        <path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z" />
                                        <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.3 1.25c.085.13.11.307.11.95h2.07c-.642-.64-1.54-.83-2.18-.95zm.7 2.2V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V3.45H10z" />
                                    </svg>
                                    Excel Detallado
                                </button>
                                <button type="button" id="btnDescargarExcelDelegaciones" class="btn btn-success"
                                    style="background-color: #218838; border-color: #218838; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; flex: 1 1 140px;"
                                    title="Descargar Reporte por Municipios (Excel)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 11.293 4L9.293 0zM9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1zM5.884 6.68 8 9.219l2.116-2.54a.5.5 0 1 1 .768.641L8.651 10l2.233 2.68a.5.5 0 0 1-.768.64L8 10.781l-2.116 2.54a.5.5 0 0 1-.768-.641L7.349 10 5.116 7.32a.5.5 0 1 1 .768-.64z" />
                                    </svg>
                                    Municipios
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

    <!-- Formulario oculto para descarga -->
    <form action="{{ route('admin.analytics_catastro.reportes.descargar_excel') }}" method="POST" id="formDescargarExcel" style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelFechaInicio">
        <input type="hidden" name="fin" id="excelFechaFin">
        <input type="hidden" name="tipo_catastro" id="excelTipoCatastro">
    </form>

    <!-- Formulario oculto para descarga por municipios -->
    <form action="{{ route('admin.analytics_catastro.reportes.descargar_excel_municipios') }}" method="POST" id="formDescargarExcelDelegaciones" style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelDelegacionesFechaInicio">
        <input type="hidden" name="fin" id="excelDelegacionesFechaFin">
    </form>

    <!-- Formulario oculto para descarga por dia -->
    <form action="{{ route('admin.analytics_catastro.reportes.descargar_excel_dias') }}" method="POST" id="formDescargarExcelDias" style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelDiasFechaInicio">
        <input type="hidden" name="fin" id="excelDiasFechaFin">
    </form>

    <div class="contenedor-kpis">
        <!-- Fila de KPI Cards Dinámicas (Fila 1: PDFs, Fila 2: Imágenes) -->
        <div class="d-flex flex-wrap mb-4" id="kpiCardsContainer" style="width: 100%; margin: 0 -0.5rem;"></div>

        <!-- ========================================== -->
        <!-- SECCIÓN 1: ANÁLISIS DE ARCHIVOS (PDFs) -->
        <!-- ========================================== -->
        <div class="section-title text-primary d-flex align-items-center">
            <i class="mdi mdi-file-pdf-box me-2" style="font-size: 1.4rem;"></i>
            <span>Desempeño de Archivos (PDFs) - Captura Manual vs OCR</span>
        </div>

        <div class="row mb-4">
            <!-- Gráfica 1: Productividad General al Día (Archivos - PDFs) -->
            <div class="col-12 col-lg-12 mb-3" style="min-height: 480px">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold">Productividad General al Día (Archivos PDFs)</h5>
                        <div class="d-flex" style="gap: 12px; font-size: 0.88rem; font-weight: bold;">
                            <span style="color: #0284c7;" id="lblTotalDiasCaptura">Captura: 0</span>
                            <span style="color: #7c3aed;" id="lblTotalDiasOcr">OCR: 0</span>
                            <span style="color: #1f2937;" id="totalDias">Total: 0</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 380px; width: 100%;">
                            <canvas id="graficaDias"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica 2: Desempeño por Municipio (Archivos - PDFs) -->
            <div class="col-12 col-lg-12 mb-3" style="min-height: 500px">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold">Desempeño por Municipio (Archivos PDFs: Captura Manual vs OCR)</h5>
                        <span id="totalDelegacionesArchivos" style="font-weight: bold; font-size: 0.88rem;">
                            <span style="color: #0284c7;" id="lblMunCapArchivos">Captura: 0</span> | 
                            <span style="color: #7c3aed;" id="lblMunOcrArchivos">OCR: 0</span> | 
                            <span style="color: #1f2937;" id="lblMunTotArchivos">Total: 0</span>
                        </span>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 400px; width: 100%;">
                            <canvas id="graficaMunicipiosArchivos"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECCIÓN 2: ANÁLISIS DE IMÁGENES / PÁGINAS -->
        <!-- ========================================== -->
        <div class="section-title text-pink-600 d-flex align-items-center" style="color: #db2777 !important; border-color: #fbcfe8;">
            <i class="mdi mdi-image-multiple me-2" style="font-size: 1.4rem;"></i>
            <span>Desempeño de Imágenes / Páginas - Captura Manual vs OCR</span>
        </div>

        <div class="row mb-4">
            <!-- Gráfica 3: Productividad General al Día (Imágenes/Páginas) -->
            <div class="col-12 col-lg-12 mb-3" style="min-height: 480px">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold">Productividad General al Día (Imágenes / Páginas)</h5>
                        <div class="d-flex" style="gap: 12px; font-size: 0.88rem; font-weight: bold;">
                            <span style="color: #0284c7;" id="lblTotalPagsCaptura">Captura: 0</span>
                            <span style="color: #7c3aed;" id="lblTotalPagsOcr">OCR: 0</span>
                            <span style="color: #db2777;" id="totalPaginasDias">Total: 0</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 380px; width: 100%;">
                            <canvas id="graficaPaginasDias"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica 4: Desempeño por Municipio (Imágenes/Páginas) -->
            <div class="col-12 col-lg-12 mb-3" style="min-height: 500px">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold">Desempeño por Municipio (Imágenes / Páginas: Captura Manual vs OCR)</h5>
                        <span id="totalDelegacionesImagenes" style="font-weight: bold; font-size: 0.88rem;">
                            <span style="color: #0284c7;" id="lblMunCapImagenes">Captura: 0</span> | 
                            <span style="color: #7c3aed;" id="lblMunOcrImagenes">OCR: 0</span> | 
                            <span style="color: #db2777;" id="lblMunTotImagenes">Total: 0</span>
                        </span>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 400px; width: 100%;">
                            <canvas id="graficaMunicipiosImagenes"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECCIÓN 3: DISTRIBUCIÓN Y TURNOS -->
        <!-- ========================================== -->
        <div class="section-title text-gray-700 d-flex align-items-center" style="border-color: #e5e7eb;">
            <i class="mdi mdi-chart-pie me-2" style="font-size: 1.4rem;"></i>
            <span>Distribución de Acciones, Tipos y Turnos</span>
        </div>

        <div class="row">
            <!-- Gráfica 5: Acciones Realizadas en Captura Manual (Pastel) -->
            <div class="col-12 col-md-4 mb-3" style="height: 450px">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold">Acciones Captura Manual</h5>
                        <span id="totalAccionesGlobal" style="font-weight: bold; color: #6c757d; font-size: 0.9rem;">Total: 0</span>
                    </div>
                    <div class="card-body d-flex justify-content-center align-items-center" style="max-height: 380px;">
                        <div style="min-width: 200px; display: flex; justify-content: center; width: 100%;">
                            <canvas id="graficaAccionesGlobal" style="max-height: 320px !important;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica 6: Distribución por Tipo de Catastro (Dona) -->
            <div class="col-12 col-md-4 mb-3" style="height: 450px">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold">Tipo de Catastro</h5>
                        <span id="totalPorTipo" style="font-weight: bold; color: #6c757d; font-size: 0.9rem;">Total: 0</span>
                    </div>
                    <div class="card-body d-flex justify-content-center align-items-center" style="max-height: 380px;">
                        <div style="min-width: 200px; display: flex; justify-content: center; width: 100%;">
                            <canvas id="graficaTipoCatastro" style="max-height: 320px !important;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica 7: Productividad por Turnos (Dona) -->
            <div class="col-12 col-md-4 mb-3" style="height: 450px">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 font-weight-bold">Productividad por Turnos</h5>
                        <span id="totalTurnos" style="font-weight: bold; color: #6c757d; font-size: 0.9rem;">Total: 0</span>
                    </div>
                    <div class="card-body d-flex justify-content-center align-items-center" style="max-height: 380px;">
                        <div style="min-width: 200px; display: flex; justify-content: center; width: 100%;">
                            <canvas id="graficaTurnos" style="max-height: 320px !important;"></canvas>
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
            const tipoCatastroInput = document.getElementById('tipoCatastro');

            let chartDias;
            let chartPaginasDias;
            let chartMunicipiosArchivos;
            let chartMunicipiosImagenes;
            let chartAccionesGlobal;
            let chartTipoCatastro;
            let chartTurnos;

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
            tipoCatastroInput.addEventListener('change', cargarDashboard);

            // Reportes
            const btnDescargarExcel = document.getElementById('btnDescargarExcel');
            btnDescargarExcel.addEventListener('click', function () {
                document.getElementById('excelFechaInicio').value = fechaInicioInput.value;
                document.getElementById('excelFechaFin').value = fechaFinInput.value;
                document.getElementById('excelTipoCatastro').value = tipoCatastroInput.value;
                document.getElementById('formDescargarExcel').submit();
            });

            const btnDescargarExcelDelegaciones = document.getElementById('btnDescargarExcelDelegaciones');
            if (btnDescargarExcelDelegaciones) {
                btnDescargarExcelDelegaciones.addEventListener('click', function () {
                    document.getElementById('excelDelegacionesFechaInicio').value = fechaInicioInput.value;
                    document.getElementById('excelDelegacionesFechaFin').value = fechaFinInput.value;
                    document.getElementById('formDescargarExcelDelegaciones').submit();
                });
            }

            const btnDescargarExcelDias = document.getElementById('btnDescargarExcelDias');
            if (btnDescargarExcelDias) {
                btnDescargarExcelDias.addEventListener('click', function () {
                    document.getElementById('excelDiasFechaInicio').value = fechaInicioInput.value;
                    document.getElementById('excelDiasFechaFin').value = fechaFinInput.value;
                    document.getElementById('formDescargarExcelDias').submit();
                });
            }

            // COLORES POR ACCIÓN
            function getColorByAction(accion) {
                const lower = accion.toLowerCase();
                if (lower.includes('validado')) return 'rgb(192, 255, 192)';
                if (lower.includes('renombrar') || lower.includes('renombrado')) return 'rgb(222, 184, 135)';
                if (lower.includes('eliminar') || lower.includes('eliminado')) return 'rgb(255, 128, 128)';
                return 'rgb(60, 179, 113)';
            }

            let abortController;

            function setCargando(cargando) {
                const selectoresGraficas = [
                    '#graficaDias',
                    '#graficaMunicipiosArchivos',
                    '#graficaPaginasDias',
                    '#graficaMunicipiosImagenes',
                    '#graficaAccionesGlobal',
                    '#graficaTipoCatastro',
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
                const tipo_catastro = tipoCatastroInput.value;

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

                    const res = await fetch('{{ route("admin.analytics_catastro.reportes.dashboard_catastro") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ inicio, fin, tipo_catastro }),
                        signal: signal
                    });

                    if (!res.ok) throw new Error('Error al cargar datos');

                    const data = await res.json();
                    console.log('DATA DASHBOARD CATASTRO', data);

                    renderDias(data.por_dia || []);
                    renderMunicipiosArchivos(data.por_municipio || []);
                    renderPaginasDias(data.paginas_por_dia || data.por_dia || []);
                    renderMunicipiosImagenes(data.por_municipio || []);
                    renderAccionesGlobal(data.acciones_global || []);
                    renderTipoCatastro(data.por_tipo || {});
                    renderTurnos(data.por_turno || []);
                    renderKpiCards(data.acciones_global || [], data.kpis || {});
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

            // 1. Gráfica de PDFs al Día (Captura Manual vs OCR)
            function renderDias(data) {
                const labels = data.map(x => x.fecha);
                const valCaptura = data.map(x => x.captura || 0);
                const valOcr = data.map(x => x.ocr || 0);
                const valTotal = data.map(x => x.total || 0);

                const sumCap = valCaptura.reduce((a, b) => a + b, 0);
                const sumOcr = valOcr.reduce((a, b) => a + b, 0);
                const sumTot = valTotal.reduce((a, b) => a + b, 0);

                document.getElementById('lblTotalDiasCaptura').textContent = 'Captura: ' + formatearNumero(sumCap);
                document.getElementById('lblTotalDiasOcr').textContent = 'OCR: ' + formatearNumero(sumOcr);
                document.getElementById('totalDias').textContent = 'Total: ' + formatearNumero(sumTot);

                if (chartDias) chartDias.destroy();

                chartDias = new Chart(document.getElementById('graficaDias'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Captura Manual (PDFs)',
                                data: valCaptura,
                                borderColor: '#0284c7',
                                backgroundColor: 'rgba(2, 132, 199, 0.15)',
                                pointRadius: 4,
                                borderWidth: 3,
                                fill: true,
                                tension: 0.2
                            },
                            {
                                label: 'OCR (PDFs)',
                                data: valOcr,
                                borderColor: '#7c3aed',
                                backgroundColor: 'rgba(124, 58, 237, 0.15)',
                                pointRadius: 4,
                                borderWidth: 3,
                                fill: true,
                                tension: 0.2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                formatter: (v) => v > 0 ? formatearNumero(v) : '',
                                font: { weight: 'bold', size: 10 }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: formatearNumero }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            // 2. Gráfica de Municipios (PDFs: Captura Manual vs OCR)
            function renderMunicipiosArchivos(data) {
                const top = (data || []).slice(0, 15);
                const labels = top.map(x => x.municipio);
                const cap = top.map(x => x.captura || 0);
                const ocr = top.map(x => x.ocr || 0);

                const totCap = data.reduce((a, b) => a + (b.captura || 0), 0);
                const totOcr = data.reduce((a, b) => a + (b.ocr || 0), 0);
                const totGeneral = data.reduce((a, b) => a + (b.total || 0), 0);

                document.getElementById('lblMunCapArchivos').textContent = 'Captura: ' + formatearNumero(totCap);
                document.getElementById('lblMunOcrArchivos').textContent = 'OCR: ' + formatearNumero(totOcr);
                document.getElementById('lblMunTotArchivos').textContent = 'Total: ' + formatearNumero(totGeneral);

                if (chartMunicipiosArchivos) chartMunicipiosArchivos.destroy();

                chartMunicipiosArchivos = new Chart(document.getElementById('graficaMunicipiosArchivos'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Captura Manual (PDFs)',
                                data: cap,
                                backgroundColor: '#38bdf8',
                                borderColor: '#0284c7',
                                borderWidth: 1
                            },
                            {
                                label: 'OCR (PDFs)',
                                data: ocr,
                                backgroundColor: '#a78bfa',
                                borderColor: '#7c3aed',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                stacked: true,
                                ticks: { maxRotation: 45, minRotation: 0 }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: { callback: formatearNumero }
                            }
                        },
                        plugins: {
                            legend: { position: 'top' },
                            datalabels: {
                                color: '#1f2937',
                                formatter: (val) => val > 0 ? formatearNumero(val) : '',
                                font: { weight: 'bold', size: 9 }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            // 3. Gráfica de Imágenes/Páginas al Día (Captura Manual vs OCR)
            function renderPaginasDias(data) {
                const labels = data.map(x => x.fecha);
                const pCaptura = data.map(x => x.captura_paginas || 0);
                const pOcr = data.map(x => x.ocr_paginas || 0);
                const pTotal = data.map(x => (x.total_paginas !== undefined ? x.total_paginas : (x.total || 0)));

                const sumCap = pCaptura.reduce((a, b) => a + b, 0);
                const sumOcr = pOcr.reduce((a, b) => a + b, 0);
                const sumTot = pTotal.reduce((a, b) => a + b, 0);

                document.getElementById('lblTotalPagsCaptura').textContent = 'Captura: ' + formatearNumero(sumCap);
                document.getElementById('lblTotalPagsOcr').textContent = 'OCR: ' + formatearNumero(sumOcr);
                document.getElementById('totalPaginasDias').textContent = 'Total: ' + formatearNumero(sumTot);

                if (chartPaginasDias) chartPaginasDias.destroy();

                chartPaginasDias = new Chart(document.getElementById('graficaPaginasDias'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Imágenes Captura Manual',
                                data: pCaptura,
                                borderColor: '#0284c7',
                                backgroundColor: 'rgba(2, 132, 199, 0.2)',
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                pointStyle: 'circle',
                                showLine: false,
                                borderWidth: 3,
                                tension: 0.1
                            },
                            {
                                label: 'Imágenes OCR',
                                data: pOcr,
                                borderColor: '#7c3aed',
                                backgroundColor: 'rgba(124, 58, 237, 0.2)',
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                pointStyle: 'triangle',
                                showLine: false,
                                borderWidth: 3,
                                tension: 0.1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' },
                            datalabels: {
                                anchor: 'end',
                                align: 'top',
                                formatter: (v) => v > 0 ? formatearNumero(v) : '',
                                font: { weight: 'bold', size: 9 }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: formatearNumero }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            // 4. Gráfica de Municipios (Imágenes: Captura Manual vs OCR)
            function renderMunicipiosImagenes(data) {
                const top = (data || []).slice(0, 15);
                const labels = top.map(x => x.municipio);
                const cap = top.map(x => x.captura_paginas || 0);
                const ocr = top.map(x => x.ocr_paginas || 0);

                const totCap = data.reduce((a, b) => a + (b.captura_paginas || 0), 0);
                const totOcr = data.reduce((a, b) => a + (b.ocr_paginas || 0), 0);
                const totGeneral = data.reduce((a, b) => a + (b.paginas || 0), 0);

                document.getElementById('lblMunCapImagenes').textContent = 'Captura: ' + formatearNumero(totCap);
                document.getElementById('lblMunOcrImagenes').textContent = 'OCR: ' + formatearNumero(totOcr);
                document.getElementById('lblMunTotImagenes').textContent = 'Total: ' + formatearNumero(totGeneral);

                if (chartMunicipiosImagenes) chartMunicipiosImagenes.destroy();

                chartMunicipiosImagenes = new Chart(document.getElementById('graficaMunicipiosImagenes'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Imágenes Captura Manual',
                                data: cap,
                                backgroundColor: '#f472b6',
                                borderColor: '#db2777',
                                borderWidth: 1
                            },
                            {
                                label: 'Imágenes OCR',
                                data: ocr,
                                backgroundColor: '#c084fc',
                                borderColor: '#9333ea',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                stacked: true,
                                ticks: { maxRotation: 45, minRotation: 0 }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: { callback: formatearNumero }
                            }
                        },
                        plugins: {
                            legend: { position: 'top' },
                            datalabels: {
                                color: '#1f2937',
                                formatter: (val) => val > 0 ? formatearNumero(val) : '',
                                font: { weight: 'bold', size: 9 }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            // 5. Acciones Captura Manual (Pastel)
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
                                    datosArray.forEach(val => { suma += val; });
                                    return suma > 0 ? (value * 100 / suma).toFixed(1) + "%" : "0%";
                                },
                                font: { weight: 'bold' }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            // 6. Tipo de Catastro (Dona)
            function renderTipoCatastro(data) {
                const labels = Object.keys(data);
                const valores = labels.map(k => (data[k].captura || 0) + (data[k].ocr || 0));
                const total = valores.reduce((a, b) => a + b, 0);

                document.getElementById('totalPorTipo').textContent = 'Total: ' + formatearNumero(total);

                if (chartTipoCatastro) chartTipoCatastro.destroy();

                chartTipoCatastro = new Chart(document.getElementById('graficaTipoCatastro'), {
                    type: 'doughnut',
                    data: {
                        labels: labels.map(l => `Catastro ${l}`),
                        datasets: [{
                            data: valores,
                            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#6b7280']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            datalabels: {
                                color: '#fff',
                                font: { weight: 'bold' },
                                formatter: (val) => {
                                    if (val === 0 || total === 0) return '';
                                    return ((val / total) * 100).toFixed(1) + "%";
                                }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            // 7. Turnos (Dona)
            function renderTurnos(data) {
                let etiquetas = [];
                let valores = [];

                if (Array.isArray(data)) {
                    etiquetas = data.map(x => x.turno);
                    valores = data.map(x => x.total);
                } else if (typeof data === 'object' && data !== null) {
                    etiquetas = Object.keys(data);
                    valores = Object.values(data).map(v => typeof v === 'object' && v !== null ? (v.total || 0) : v);
                }

                const total = valores.reduce((a, b) => a + b, 0);
                document.getElementById('totalTurnos').textContent = 'Total: ' + formatearNumero(total);

                const coloresTurnos = [
                    'rgb(255, 205, 86)',
                    'rgb(54, 162, 235)',
                    'rgb(153, 102, 255)',
                    'rgb(75, 192, 192)'
                ];

                if (chartTurnos) chartTurnos.destroy();

                chartTurnos = new Chart(document.getElementById('graficaTurnos'), {
                    type: 'doughnut',
                    data: {
                        labels: etiquetas,
                        datasets: [{
                            data: valores,
                            backgroundColor: coloresTurnos,
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            datalabels: {
                                color: '#000',
                                formatter: (value, ctx) => {
                                    let suma = 0;
                                    let datosArray = ctx.chart.data.datasets[0].data;
                                    datosArray.forEach(val => { suma += val; });
                                    return suma > 0 ? (value * 100 / suma).toFixed(1) + "%" : "0%";
                                },
                                font: { weight: 'bold' }
                            }
                        }
                    },
                    plugins: [ChartDataLabels]
                });
            }

            // KPI Cards Dinámicas (Fila 1: PDFs, Fila 2: Imágenes) - Sin Duplicados ni Recortes
            function renderKpiCards(accionesGlobal, kpis) {
                const container = document.getElementById('kpiCardsContainer');
                if (!container) return;
                container.innerHTML = '';

                let totalCalidad = 0;
                let totalCalidadImagenes = 0;

                const mapArchivos = {
                    'Validado': 0,
                    'Renombrar': 0,
                    'Eliminado': 0
                };

                const mapImagenes = {
                    'Validado': 0,
                    'Renombrar': 0,
                    'Eliminado': 0
                };

                accionesGlobal.forEach(x => {
                    let nombre = x.accion;
                    if (nombre in mapArchivos) {
                        mapArchivos[nombre] += x.total || 0;
                        mapImagenes[nombre] += x.paginas || 0;
                    }
                    if (nombre.toLowerCase().includes('validado') || nombre.toLowerCase().includes('renombrar')) {
                        totalCalidad += x.total || 0;
                        totalCalidadImagenes += x.paginas || 0;
                    }
                });

                const totalOcrPdfs = kpis.total_ocr || 0;
                const totalOcrPags = kpis.paginas_ocr || 0;
                const totalGenPdfs = kpis.total_general || 0;
                const totalGenPags = kpis.paginas_total || 0;

                // --- FILA 1: ARCHIVOS (PDFs) ---
                const rowPdfs = document.createElement('div');
                rowPdfs.className = 'row w-100 mb-2 g-2 mx-0';

                Object.keys(mapArchivos).forEach(accionName => {
                    const total = mapArchivos[accionName];
                    const color = getColorByAction(accionName);

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
                });

                // Card Verde de Calidad Catastro (PDFs)
                const calidadHtml = `
                    <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #059669, #047857); color: #ffffff;">
                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #a7f3d0; font-weight: 600; margin-bottom: 0.25rem;">Calidad Catastro</p>
                                <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalCalidad)}</h3>
                            </div>
                        </div>
                    </div>
                `;
                rowPdfs.insertAdjacentHTML('beforeend', calidadHtml);

                // Card Morada OCR (PDFs)
                const ocrPdfsHtml = `
                    <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #ffffff;">
                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #ddd6fe; font-weight: 600; margin-bottom: 0.25rem;">OCR (PDFs)</p>
                                <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalOcrPdfs)}</h3>
                            </div>
                        </div>
                    </div>
                `;
                rowPdfs.insertAdjacentHTML('beforeend', ocrPdfsHtml);

                // Card Total General (PDFs)
                const totalGenPdfsHtml = `
                    <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #1f2937, #111827); color: #ffffff;">
                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #9ca3af; font-weight: 600; margin-bottom: 0.25rem;">Total General (PDFs)</p>
                                <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalGenPdfs)}</h3>
                            </div>
                        </div>
                    </div>
                `;
                rowPdfs.insertAdjacentHTML('beforeend', totalGenPdfsHtml);
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

                // Card Verde de Imágenes en Calidad
                const calidadImagenesHtml = `
                    <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #059669, #047857); color: #ffffff;">
                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #a7f3d0; font-weight: 600; margin-bottom: 0.25rem;">Imágenes en Calidad</p>
                                <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalCalidadImagenes)}</h3>
                            </div>
                        </div>
                    </div>
                `;
                rowImagenes.insertAdjacentHTML('beforeend', calidadImagenesHtml);

                // Card Morada OCR (Imágenes)
                const ocrPagsHtml = `
                    <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #ffffff;">
                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #ddd6fe; font-weight: 600; margin-bottom: 0.25rem;">OCR (Imágenes)</p>
                                <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalOcrPags)}</h3>
                            </div>
                        </div>
                    </div>
                `;
                rowImagenes.insertAdjacentHTML('beforeend', ocrPagsHtml);

                // Card Rosa Total General (Imágenes)
                const totalGenPagsHtml = `
                    <div class="col-6 col-sm-4 col-md-4 col-lg-2 px-1 mb-3">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #db2777, #be185d); color: #ffffff;">
                            <div class="card-body p-3 d-flex flex-column justify-content-center">
                                <p class="text-xs uppercase tracking-wider font-semibold mb-1 text-truncate" style="font-size: 0.75rem; color: #fbcfe8; font-weight: 600; margin-bottom: 0.25rem;">Total General (Imágenes)</p>
                                <h3 class="text-2xl font-bold" style="font-size: 1.4rem; font-weight: 700; color: #ffffff; margin: 0;">${formatearNumero(totalGenPags)}</h3>
                            </div>
                        </div>
                    </div>
                `;
                rowImagenes.insertAdjacentHTML('beforeend', totalGenPagsHtml);
                container.appendChild(rowImagenes);
            }

            cargarDashboard();
        });
    </script>
@endsection