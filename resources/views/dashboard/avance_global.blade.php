@extends('layouts.app')
@section('titulo', 'Dashboard de Avance Global — Entregables')

@section('contenido')
<div class="space-y-6 pb-8">
    <!-- Encabezado -->
    <div class="flex flex-wrap items-center justify-between gap-4 bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center">
                    <i class="fa-solid fa-gauge-high"></i>
                </span>
                Dashboard Ejecutivo de Avance Global
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Meta fija de entregables (Sellos): <strong>{{ number_format($metaEntregables) }} PDFs</strong>
                ({{ number_format($metaPaginas) }} páginas) · Índice total: {{ number_format($totalIndice) }} ({{ number_format($totalEliminados) }} eliminados excluidos)
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.analytics_sellos.reportes.buscar_archivos') }}"
               class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition flex items-center gap-1.5">
                <i class="fa-solid fa-magnifying-glass"></i> Explorador PDF
            </a>
            <a href="{{ route('admin.aprobacion.index') }}"
               class="px-3.5 py-2 rounded-xl bg-sky-700 hover:bg-sky-800 text-white text-xs font-semibold transition flex items-center gap-1.5">
                <i class="fa-solid fa-check-double"></i> Aprobación de Renombres
            </a>
        </div>
    </div>

    <!-- Tarjetas KPI principales -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Meta Entregables</span>
                <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-bullseye"></i></span>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 mt-2">{{ number_format($metaEntregables) }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ number_format($metaPaginas) }} páginas totales</div>
        </div>

        <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Trabajados (Avance)</span>
                <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <div class="text-2xl font-extrabold text-emerald-700 mt-2">{{ number_format($totalTrabajados) }}</div>
            <div class="text-xs text-slate-600 mt-1">
                <strong>{{ number_format($porcentajeAvance, 3) }}%</strong> ·
                Ren: {{ number_format($renombrados) }} · Val: {{ number_format($validados) }} · Inc: {{ number_format($inconsistencias) }}
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700">Faltantes</span>
                <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-hourglass-half"></i></span>
            </div>
            <div class="text-2xl font-extrabold text-amber-700 mt-2">{{ number_format($faltantes) }}</div>
            <div class="text-xs text-slate-600 mt-1">
                <strong>{{ number_format(100 - $porcentajeAvance, 3) }}%</strong> pendiente por capturar
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-sky-200 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-sky-700">Actividad Hoy / En Vivo</span>
                <span class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center"><i class="fa-solid fa-bolt"></i></span>
            </div>
            <div class="text-2xl font-extrabold text-sky-700 mt-2">{{ number_format($hoyTotal) }}</div>
            <div class="text-xs text-slate-600 mt-1">
                En captura ahora: <strong>{{ $enCapturaActiva }}</strong> · Promedio: <strong>{{ $promedioSegundos }}s/PDF</strong>
            </div>
        </div>
    </div>

    <!-- Barra de progreso global -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <div class="flex flex-wrap justify-between items-center mb-2 gap-2">
            <span class="text-sm font-bold text-slate-800">Progreso Global de Entregables</span>
            <span class="text-xs font-mono font-bold text-sky-700">
                {{ number_format($totalTrabajados) }} / {{ number_format($metaEntregables) }} PDFs ({{ number_format($porcentajeAvance, 3) }}%)
                · Páginas: {{ number_format($paginasTrabajadas) }} / {{ number_format($metaPaginas) }} ({{ number_format($porcentajePaginas, 3) }}%)
            </span>
        </div>
        <div class="w-full h-4 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
            <div class="h-full bg-gradient-to-r from-sky-600 to-emerald-500 transition-all duration-500"
                 style="width: {{ min(100, max($totalTrabajados > 0 ? 0.5 : 0, $porcentajeAvance)) }}%"></div>
        </div>
        <div class="flex flex-wrap gap-4 mt-3 text-xs text-slate-600">
            <span><i class="fa-solid fa-square text-emerald-500 mr-1"></i> Renombrados: <strong>{{ number_format($renombrados) }}</strong></span>
            <span><i class="fa-solid fa-square text-sky-500 mr-1"></i> Validados: <strong>{{ number_format($validados) }}</strong></span>
            <span><i class="fa-solid fa-square text-rose-500 mr-1"></i> Inconsistencias: <strong>{{ number_format($inconsistencias) }}</strong></span>
            <span><i class="fa-solid fa-square text-indigo-500 mr-1"></i> Aplicados en Stellum: <strong>{{ number_format($aplicadosStellum) }}</strong></span>
        </div>
    </div>

    <!-- Gráficas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h3 class="text-sm font-bold text-slate-800 mb-3">
                <i class="fa-solid fa-chart-column text-sky-600 mr-1.5"></i> Captura Diaria (Últimos 14 días)
            </h3>
            <div class="h-64">
                <canvas id="chartTendencia"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h3 class="text-sm font-bold text-slate-800 mb-3">
                <i class="fa-solid fa-chart-pie text-emerald-600 mr-1.5"></i> Distribución de Avance
            </h3>
            <div class="h-64 flex items-center justify-center">
                <canvas id="chartDistribucion"></canvas>
            </div>
        </div>
    </div>

    <!-- Avance por Delegación + Top Capturistas -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800">
                    <i class="fa-solid fa-building-columns text-sky-700 mr-1.5"></i> Avance por Delegación (18 Delegaciones)
                </h3>
                <span class="text-xs text-slate-500">Ordenado por volumen total</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-2.5">Delegación</th>
                            <th class="px-3 py-2.5 text-right">Meta</th>
                            <th class="px-3 py-2.5 text-right">Renomb.</th>
                            <th class="px-3 py-2.5 text-right">Valid.</th>
                            <th class="px-3 py-2.5 text-right">Incons.</th>
                            <th class="px-3 py-2.5 text-right">Faltantes</th>
                            <th class="px-4 py-2.5 w-36">Avance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($delegacionesAvance as $del)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $del['nombre'] }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-slate-600">{{ number_format($del['meta']) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-emerald-700 font-semibold">{{ number_format($del['renombrados']) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-sky-700 font-semibold">{{ number_format($del['validados']) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-rose-600">{{ number_format($del['inconsistencias']) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-amber-700 font-semibold">{{ number_format($del['faltantes']) }}</td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-500 rounded-full" style="width: {{ min(100, $del['porcentaje']) }}%"></div>
                                        </div>
                                        <span class="font-mono text-[11px] font-bold text-slate-700 w-12 text-right">{{ number_format($del['porcentaje'], 2) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-200">
                <h3 class="text-sm font-bold text-slate-800">
                    <i class="fa-solid fa-ranking-star text-amber-500 mr-1.5"></i> Top Capturistas (Sellos)
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-2.5">Usuario</th>
                            <th class="px-3 py-2.5 text-right">PDFs</th>
                            <th class="px-3 py-2.5 text-right">Págs</th>
                            <th class="px-3 py-2.5 text-right">Prom.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($topCapturistas as $cap)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $cap->nombre }}</td>
                                <td class="px-3 py-2.5 text-right font-mono font-bold text-emerald-700">{{ number_format($cap->total) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-slate-600">{{ number_format($cap->paginas) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-slate-500">{{ $cap->prom_seg ?? 0 }}s</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-400">Sin capturas registradas aún.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tendencia = @json($tendenciaDias);

    const ctxT = document.getElementById('chartTendencia');
    if (ctxT) {
        new Chart(ctxT, {
            type: 'bar',
            data: {
                labels: tendencia.map(d => d.dia),
                datasets: [
                    { label: 'Renombrados', data: tendencia.map(d => d.renombrados), backgroundColor: '#10b981', borderRadius: 4 },
                    { label: 'Validados', data: tendencia.map(d => d.validados), backgroundColor: '#0284c7', borderRadius: 4 },
                    { label: 'Inconsistencias', data: tendencia.map(d => d.inconsistencias), backgroundColor: '#f43f5e', borderRadius: 4 },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
            }
        });
    }

    const ctxD = document.getElementById('chartDistribucion');
    if (ctxD) {
        new Chart(ctxD, {
            type: 'doughnut',
            data: {
                labels: ['Renombrados', 'Validados', 'Inconsistencias', 'Faltantes'],
                datasets: [{
                    data: [{{ $renombrados }}, {{ $validados }}, {{ $inconsistencias }}, {{ $faltantes }}],
                    backgroundColor: ['#10b981', '#0284c7', '#f43f5e', '#e2e8f0'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
});
</script>
@endsection
