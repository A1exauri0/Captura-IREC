@extends('layouts.app')
@section('titulo', 'Dashboard de Avance Global — Entregables')

@section('contenido')
<style>
    @keyframes barraAvanceMovimiento {
        0% { background-position: 0 0; }
        100% { background-position: 40px 0; }
    }
    @keyframes barraBrillo {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(260%); }
    }
    .barra-avance-animada {
        background-image: linear-gradient(
            -45deg,
            rgba(255, 255, 255, 0.25) 25%,
            transparent 25%,
            transparent 50%,
            rgba(255, 255, 255, 0.25) 50%,
            rgba(255, 255, 255, 0.25) 75%,
            transparent 75%,
            transparent
        ), linear-gradient(90deg, #0284c7, #10b981);
        background-size: 40px 40px, 100% 100%;
        animation: barraAvanceMovimiento 1.3s linear infinite;
        position: relative;
        overflow: hidden;
    }
    .barra-avance-animada::after {
        content: "";
        position: absolute;
        inset: 0;
        width: 38%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.38), transparent);
        animation: barraBrillo 2.5s ease-in-out infinite;
    }
</style>

<div class="space-y-4 pb-8">
    <!-- Tarjetas KPI principales -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-slate-200 px-4 py-3 shadow-sm flex items-center justify-between gap-3">
            <div class="min-w-0">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Meta Entregables</span>
                <div class="text-2xl font-extrabold text-slate-900 leading-tight mt-0.5">{{ number_format($metaEntregables) }}</div>
                <div class="text-[11px] text-slate-500 mt-0.5 truncate">{{ number_format($metaPaginas) }} páginas totales</div>
            </div>
            <span class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-bullseye"></i>
            </span>
        </div>

        <div class="bg-white rounded-xl border border-emerald-200 px-4 py-3 shadow-sm flex items-center justify-between gap-3">
            <div class="min-w-0">
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 block">Trabajados (Avance)</span>
                <div id="kpiTrabajados" class="text-2xl font-extrabold text-emerald-700 leading-tight mt-0.5">{{ number_format($totalTrabajados) }}</div>
                <div id="kpiTrabajadosDetalle" class="text-[11px] text-slate-600 mt-0.5 truncate">
                    <strong>{{ number_format($porcentajeAvance, 3) }}%</strong> · Ren: {{ number_format($renombrados) }} · Val: {{ number_format($validados) }} · Inc: {{ number_format($inconsistencias) }}
                </div>
            </div>
            <span class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </span>
        </div>

        <div class="bg-white rounded-xl border border-amber-200 px-4 py-3 shadow-sm flex items-center justify-between gap-3">
            <div class="min-w-0">
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700 block">Faltantes</span>
                <div id="kpiFaltantes" class="text-2xl font-extrabold text-amber-700 leading-tight mt-0.5">{{ number_format($faltantes) }}</div>
                <div id="kpiFaltantesDetalle" class="text-[11px] text-slate-600 mt-0.5 truncate">
                    <strong>{{ number_format(100 - $porcentajeAvance, 3) }}%</strong> pendiente por capturar
                </div>
            </div>
            <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-hourglass-half"></i>
            </span>
        </div>

        <div class="bg-white rounded-xl border border-sky-200 px-4 py-3 shadow-sm flex items-center justify-between gap-3">
            <div class="min-w-0">
                <span class="text-[11px] font-bold uppercase tracking-wider text-sky-700 block">Actividad Hoy</span>
                <div id="kpiHoy" class="text-2xl font-extrabold text-sky-700 leading-tight mt-0.5">{{ number_format($hoyTotal) }}</div>
                <div id="kpiHoyDetalle" class="text-[11px] text-slate-600 mt-0.5 truncate">
                    En captura ahora: <strong>{{ $enCapturaActiva }}</strong> · Prom: <strong>{{ $promedioSegundos }}s/PDF</strong>
                </div>
            </div>
            <span class="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-bolt"></i>
            </span>
        </div>
    </div>

    <!-- Barra de progreso global -->
    <div class="bg-white rounded-2xl border border-slate-200 px-5 py-4 shadow-sm">
        <div class="flex flex-wrap justify-between items-center mb-2.5 gap-2">
            <span class="text-sm font-bold text-slate-800 flex items-center gap-2">
                Progreso Global de Entregables
                <span id="badgeLiveDashboard" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> EN VIVO
                </span>
            </span>
            <span id="lblResumenBarra" class="text-xs font-mono font-bold text-sky-700">
                {{ number_format($totalTrabajados) }} / {{ number_format($metaEntregables) }} PDFs ({{ number_format($porcentajeAvance, 3) }}%)
                · Páginas: {{ number_format($paginasTrabajadas) }} / {{ number_format($metaPaginas) }} ({{ number_format($porcentajePaginas, 3) }}%)
            </span>
        </div>
        <div class="relative w-full h-7 bg-slate-100 rounded-full overflow-hidden border border-slate-200 shadow-inner">
            <div id="barraProgresoGlobal" class="h-full rounded-full barra-avance-animada transition-all duration-700"
                 style="width: {{ min(100, max($totalTrabajados > 0 ? 1.8 : 0, $porcentajeAvance)) }}%"></div>
            <span id="lblPorcentajeBarra" class="absolute inset-0 flex items-center justify-center text-xs font-mono font-extrabold {{ $porcentajeAvance > 45 ? 'text-white' : 'text-slate-700' }} pointer-events-none">
                {{ number_format($porcentajeAvance, 3) }}% completado
            </span>
        </div>
        <div class="flex flex-wrap gap-4 mt-3 text-xs text-slate-600">
            <span><i class="fa-solid fa-square text-emerald-500 mr-1"></i> Validados: <strong id="lblCountVal">{{ number_format($validados) }}</strong></span>
            <span><i class="fa-solid fa-square text-sky-600 mr-1"></i> Renombrados: <strong id="lblCountRen">{{ number_format($renombrados) }}</strong></span>
            <span><i class="fa-solid fa-square text-red-500 mr-1"></i> Inconsistencias: <strong id="lblCountInc">{{ number_format($inconsistencias) }}</strong></span>
            <span><i class="fa-solid fa-square text-indigo-500 mr-1"></i> Aplicados en Stellum: <strong id="lblCountApl">{{ number_format($aplicadosStellum) }}</strong></span>
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
                <i class="fa-solid fa-chart-pie text-emerald-600 mr-1.5"></i> Acciones Realizadas
            </h3>
            <div class="h-64 flex items-center justify-center">
                @if(($renombrados + $validados + $inconsistencias) > 0)
                    <canvas id="chartDistribucion"></canvas>
                @else
                    <p class="text-xs text-slate-400 text-center">Sin acciones registradas todavía.</p>
                @endif
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
                    <tbody id="tbodyDelegacionesAvance" class="divide-y divide-slate-100">
                        @foreach($delegacionesAvance as $del)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $del['nombre'] }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-slate-600">{{ number_format($del['meta']) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-sky-700 font-semibold">{{ number_format($del['renombrados']) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-emerald-700 font-semibold">{{ number_format($del['validados']) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-red-600 font-semibold">{{ number_format($del['inconsistencias']) }}</td>
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
                    <tbody id="tbodyTopCapturistas" class="divide-y divide-slate-100">
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
    const fmtNum = (n) => Number(n || 0).toLocaleString('en-US');

    let chartTendencia = null;
    const ctxT = document.getElementById('chartTendencia');
    if (ctxT) {
        chartTendencia = new Chart(ctxT, {
            type: 'bar',
            data: {
                labels: tendencia.map(d => d.dia),
                datasets: [
                    { label: 'Validados', data: tendencia.map(d => d.validados), backgroundColor: '#10b981', borderRadius: 4 },
                    { label: 'Renombrados', data: tendencia.map(d => d.renombrados), backgroundColor: '#0284c7', borderRadius: 4 },
                    { label: 'Inconsistencias', data: tendencia.map(d => d.inconsistencias), backgroundColor: '#ef4444', borderRadius: 4 },
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

    let chartDistribucion = null;
    const ctxD = document.getElementById('chartDistribucion');
    if (ctxD) {
        chartDistribucion = new Chart(ctxD, {
            type: 'doughnut',
            data: {
                labels: ['Validados', 'Renombrados', 'Inconsistencias'],
                datasets: [{
                    data: [{{ $validados }}, {{ $renombrados }}, {{ $inconsistencias }}],
                    backgroundColor: ['#10b981', '#0284c7', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    async function actualizarDashboardVivo() {
        try {
            const resp = await fetch("{{ route('admin.dashboard.avance.datos') }}", {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            if (!resp.ok) return;
            const json = await resp.json();
            if (!json.ok || !json.datos) return;
            const d = json.datos;

            const pct = Number(d.porcentajeAvance || 0);
            const pctPag = Number(d.porcentajePaginas || 0);

            document.getElementById('kpiTrabajados').textContent = fmtNum(d.totalTrabajados);
            document.getElementById('kpiTrabajadosDetalle').innerHTML =
                `<strong>${pct.toFixed(3)}%</strong> · Ren: ${fmtNum(d.renombrados)} · Val: ${fmtNum(d.validados)} · Inc: ${fmtNum(d.inconsistencias)}`;
            document.getElementById('kpiFaltantes').textContent = fmtNum(d.faltantes);
            document.getElementById('kpiFaltantesDetalle').innerHTML =
                `<strong>${(100 - pct).toFixed(3)}%</strong> pendiente por capturar`;
            document.getElementById('kpiHoy').textContent = fmtNum(d.hoyTotal);
            document.getElementById('kpiHoyDetalle').innerHTML =
                `En captura ahora: <strong>${d.enCapturaActiva}</strong> · Prom: <strong>${d.promedioSegundos}s/PDF</strong>`;

            document.getElementById('lblResumenBarra').textContent =
                `${fmtNum(d.totalTrabajados)} / ${fmtNum(d.metaEntregables)} PDFs (${pct.toFixed(3)}%) · Páginas: ${fmtNum(d.paginasTrabajadas)} / ${fmtNum(d.metaPaginas)} (${pctPag.toFixed(3)}%)`;
            const barra = document.getElementById('barraProgresoGlobal');
            if (barra) {
                barra.style.width = `${Math.min(100, Math.max(d.totalTrabajados > 0 ? 1.8 : 0, pct))}%`;
            }
            document.getElementById('lblPorcentajeBarra').textContent = `${pct.toFixed(3)}% completado`;
            document.getElementById('lblCountRen').textContent = fmtNum(d.renombrados);
            document.getElementById('lblCountVal').textContent = fmtNum(d.validados);
            document.getElementById('lblCountInc').textContent = fmtNum(d.inconsistencias);
            document.getElementById('lblCountApl').textContent = fmtNum(d.aplicadosStellum);

            const badge = document.getElementById('badgeLiveDashboard');
            if (badge && json.actualizado_en) {
                badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> EN VIVO · ${json.actualizado_en}`;
            }

            if (chartTendencia && Array.isArray(d.tendenciaDias)) {
                chartTendencia.data.labels = d.tendenciaDias.map(x => x.dia);
                chartTendencia.data.datasets[0].data = d.tendenciaDias.map(x => x.validados);
                chartTendencia.data.datasets[1].data = d.tendenciaDias.map(x => x.renombrados);
                chartTendencia.data.datasets[2].data = d.tendenciaDias.map(x => x.inconsistencias);
                chartTendencia.update('none');
            }
            if (chartDistribucion) {
                chartDistribucion.data.datasets[0].data = [d.validados, d.renombrados, d.inconsistencias];
                chartDistribucion.update('none');
            }

            const tbDel = document.getElementById('tbodyDelegacionesAvance');
            if (tbDel && Array.isArray(d.delegacionesAvance)) {
                tbDel.innerHTML = d.delegacionesAvance.map(del => `
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-2.5 font-semibold text-slate-800">${del.nombre}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-slate-600">${fmtNum(del.meta)}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-sky-700 font-semibold">${fmtNum(del.renombrados)}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-emerald-700 font-semibold">${fmtNum(del.validados)}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-red-600 font-semibold">${fmtNum(del.inconsistencias)}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-amber-700 font-semibold">${fmtNum(del.faltantes)}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: ${Math.min(100, del.porcentaje)}%"></div>
                                </div>
                                <span class="font-mono text-[11px] font-bold text-slate-700 w-12 text-right">${Number(del.porcentaje).toFixed(2)}%</span>
                            </div>
                        </td>
                    </tr>
                `).join('');
            }

            const tbTop = document.getElementById('tbodyTopCapturistas');
            if (tbTop && Array.isArray(d.topCapturistas)) {
                tbTop.innerHTML = d.topCapturistas.length
                    ? d.topCapturistas.map(cap => `
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-2.5 font-semibold text-slate-800">${cap.nombre}</td>
                            <td class="px-3 py-2.5 text-right font-mono font-bold text-emerald-700">${fmtNum(cap.total)}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-slate-600">${fmtNum(cap.paginas)}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-slate-500">${cap.prom_seg ?? 0}s</td>
                        </tr>
                    `).join('')
                    : '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Sin capturas registradas aún.</td></tr>';
            }
        } catch (_) {}
    }

    // Actualización automática en vivo cada 5 segundos + escucha de WebSocket (Reverb) si está activo
    setInterval(actualizarDashboardVivo, 5000);
    if (window.Echo) {
        try {
            window.Echo.channel('lotes-captura').listen('.lote.actualizado', () => actualizarDashboardVivo());
        } catch (_) {}
    }
});
</script>
@endsection
