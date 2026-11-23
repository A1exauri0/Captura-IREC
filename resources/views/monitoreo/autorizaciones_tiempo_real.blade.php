@extends('layouts.app')
@section('titulo', 'Centro de Autorizaciones en Tiempo Real')

<style>
    .kpi-card-auth {
        border-radius: 12px;
        padding: 16px 20px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        border-left: 4px solid #034d6d;
    }
    .badge-pendiente { background-color: #fef3c7 !important; color: #92400e !important; border: 1px solid #fde68a !important; }
    .badge-autorizada { background-color: #d1fae5 !important; color: #065f46 !important; border: 1px solid #a7f3d0 !important; }
    .badge-rechazada { background-color: #fee2e2 !important; color: #991b1b !important; border: 1px solid #fecaca !important; }
    .table-auth th {
        background-color: #034d6d;
        color: #ffffff;
        font-weight: 600;
        font-size: 0.82rem;
        vertical-align: middle;
    }
    .table-auth td {
        font-size: 0.84rem;
        vertical-align: middle;
    }
    .fila-pendiente {
        background-color: #fffbeb !important;
    }
</style>

@section('contenido')
    <!-- Encabezado y Filtros -->
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h5 font-weight-bold m-0" style="color: #034d6d;">
                        <i class="fa-solid fa-shield-halved mr-2"></i>CENTRO DE AUTORIZACIONES EN TIEMPO REAL
                    </h1>
                    <p class="text-xs text-muted m-0 mt-1">
                        Autorice o rechace al instante las solicitudes enviadas por los capturistas (Inconsistencias, Movimientos, Liberación de Lotes).
                    </p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-weight-bold bg-emerald-50 text-emerald-700 border border-emerald-200 d-inline-flex align-items-center">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>Canal WebSocket: autorizaciones-captura
                </span>
            </div>

            <div class="row align-items-end">
                <div class="col-sm-6 col-md-3 mb-2">
                    <label class="small font-weight-bold mb-1">Estado de la Petición:</label>
                    <select id="filtroEstadoPeticion" class="form-control form-control-sm">
                        <option value="">Todas las peticiones</option>
                        <option value="pendiente" selected>Pendientes por Resolver</option>
                        <option value="autorizada">Autorizadas</option>
                        <option value="rechazada">Rechazadas</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-3 mb-2">
                    <label class="small font-weight-bold mb-1">Tipo de Solicitud:</label>
                    <select id="filtroTipoPeticion" class="form-control form-control-sm">
                        <option value="">Todos los tipos</option>
                        <option value="Inconsistencia">Inconsistencia de PDF</option>
                        <option value="Mover a Sellos">Mover a Sellos</option>
                        <option value="Liberar Lote">Liberar Lote</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-3 mb-2">
                    <button type="button" id="btnRefrescarPeticiones" class="btn btn-sm btn-primary font-weight-bold px-4" style="background-color:#034d6d;border-color:#034d6d;">
                        <i class="fa-solid fa-rotate mr-1"></i> Actualizar Ahora
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas KPI -->
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <div class="kpi-card-auth" style="border-left-color: #f59e0b;">
                <span class="text-xs font-weight-bold uppercase text-muted d-block">Pendientes de Autorizar</span>
                <h3 class="font-weight-bold m-0 text-amber-600" id="kpiAuthPendientes">0</h3>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="kpi-card-auth" style="border-left-color: #10b981;">
                <span class="text-xs font-weight-bold uppercase text-muted d-block">Peticiones Autorizadas</span>
                <h3 class="font-weight-bold m-0 text-emerald-600" id="kpiAuthAutorizadas">0</h3>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="kpi-card-auth" style="border-left-color: #ef4444;">
                <span class="text-xs font-weight-bold uppercase text-muted d-block">Peticiones Rechazadas</span>
                <h3 class="font-weight-bold m-0 text-red-600" id="kpiAuthRechazadas">0</h3>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="kpi-card-auth" style="border-left-color: #034d6d;">
                <span class="text-xs font-weight-bold uppercase text-muted d-block">Total Histórico</span>
                <h3 class="font-weight-bold m-0 text-slate-800" id="kpiAuthTotal">0</h3>
            </div>
        </div>
    </div>

    <!-- Tabla de Peticiones -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-bell-concierge mr-2 text-sky-700"></i>Bandeja de Solicitudes de Capturistas</span>
            <span class="badge badge-info" id="badgeTotalPeticiones">Cargando...</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-auth mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 55px;">#ID</th>
                            <th>Capturista / Turno</th>
                            <th>Tipo de Petición</th>
                            <th>Archivo / Lote / Ruta</th>
                            <th>Motivo / Observación</th>
                            <th class="text-center">Integridad PDF</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Solicitado</th>
                            <th class="text-center" style="width: 210px;">Resolución en Tiempo Real</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPeticiones">
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-spinner fa-spin mr-2"></i> Cargando bandeja de autorizaciones...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script>
$(document).ready(function() {
    function cargarPeticiones() {
        const estado = $('#filtroEstadoPeticion').val();
        const tipo_peticion = $('#filtroTipoPeticion').val();

        $.ajax({
            url: "{{ route('admin.autorizaciones.listar') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                estado: estado,
                tipo_peticion: tipo_peticion
            },
            success: function(res) {
                const lista = res.peticiones || [];
                const kpis = res.kpis || { pendientes: 0, autorizadas: 0, rechazadas: 0, total: 0 };

                $('#kpiAuthPendientes').text(kpis.pendientes);
                $('#kpiAuthAutorizadas').text(kpis.autorizadas);
                $('#kpiAuthRechazadas').text(kpis.rechazadas);
                $('#kpiAuthTotal').text(kpis.total);
                $('#badgeTotalPeticiones').text(`${lista.length} solicitudes mostradas`);

                // Actualizar contador en el navbar superior
                const navCounter = document.getElementById('navbarAuthPendientesCount');
                if (navCounter) {
                    navCounter.textContent = kpis.pendientes;
                    navCounter.style.display = kpis.pendientes > 0 ? 'inline-block' : 'none';
                }

                if (lista.length === 0) {
                    $('#tbodyPeticiones').html(`
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-circle-check text-emerald-500 text-2xl d-block mb-2"></i>
                                No hay peticiones ${estado ? 'con estado "' + estado + '"' : ''} en este momento.
                            </td>
                        </tr>
                    `);
                    return;
                }

                let html = '';
                lista.forEach(p => {
                    const esPendiente = p.estado === 'pendiente';
                    let badgeEstado = `<span class="badge badge-pendiente px-2.5 py-1 rounded"><i class="fa-solid fa-clock mr-1"></i>PENDIENTE</span>`;
                    if (p.estado === 'autorizada') {
                        badgeEstado = `<span class="badge badge-autorizada px-2.5 py-1 rounded"><i class="fa-solid fa-check-circle mr-1"></i>AUTORIZADA</span>`;
                    } else if (p.estado === 'rechazada') {
                        badgeEstado = `<span class="badge badge-rechazada px-2.5 py-1 rounded"><i class="fa-solid fa-times-circle mr-1"></i>RECHAZADA</span>`;
                    }

                    const badgeDanado = p.archivo_danado
                        ? `<span class="badge bg-red-100 text-red-800 border border-red-300">DAÑADO</span>`
                        : `<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">OK (${p.paginas || 0} págs)</span>`;

                    let botonesAccion = '';
                    if (esPendiente) {
                        botonesAccion = `
                            <div class="d-flex justify-content-center" style="gap: 6px;">
                                <button type="button" class="btn btn-sm btn-success font-weight-bold btn-resolver-peticion"
                                        data-id="${p.id}" data-decision="autorizada" data-capturista="${p.capturista}">
                                    <i class="fa-solid fa-check mr-1"></i> Autorizar
                                </button>
                                <button type="button" class="btn btn-sm btn-danger font-weight-bold btn-resolver-peticion"
                                        data-id="${p.id}" data-decision="rechazada" data-capturista="${p.capturista}">
                                    <i class="fa-solid fa-xmark mr-1"></i> Rechazar
                                </button>
                            </div>
                        `;
                    } else {
                        botonesAccion = `
                            <div class="text-xs text-muted">
                                <div><strong>Por:</strong> ${p.supervisor || 'Supervisor'}</div>
                                <div>${p.resuelto_en || ''}</div>
                                ${p.comentario_resolucion ? `<div class="text-slate-700 italic">"${p.comentario_resolucion}"</div>` : ''}
                            </div>
                        `;
                    }

                    html += `
                        <tr class="${esPendiente ? 'fila-pendiente' : ''}">
                            <td class="text-center font-weight-bold text-slate-700">#${p.id}</td>
                            <td>
                                <div class="font-weight-bold text-dark">${p.capturista}</div>
                                <span class="badge badge-light border text-xs">${p.turno}</span>
                            </td>
                            <td>
                                <span class="font-weight-bold text-sky-800">${p.tipo_peticion}</span>
                                <div class="text-xs text-muted">${p.accion_solicitada || ''}</div>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">${p.archivo_nombre}</div>
                                <div class="text-xs text-muted">Lote: <strong>${p.lote}</strong> (${p.disco})</div>
                                ${p.ruta_origen ? `<div class="text-xs font-mono text-slate-500" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${p.ruta_origen}">${p.ruta_origen}</div>` : ''}
                            </td>
                            <td class="small">${p.motivo}</td>
                            <td class="text-center">${badgeDanado}</td>
                            <td class="text-center">${badgeEstado}</td>
                            <td class="text-center small text-muted">${p.creado_en}</td>
                            <td class="text-center">${botonesAccion}</td>
                        </tr>
                    `;
                });

                $('#tbodyPeticiones').html(html);
            }
        });
    }

    $(document).on('click', '.btn-resolver-peticion', function() {
        const id = $(this).data('id');
        const decision = $(this).data('decision');
        const capturista = $(this).data('capturista');

        Swal.fire({
            title: decision === 'autorizada' ? `¿Autorizar petición #${id}?` : `¿Rechazar petición #${id}?`,
            text: `Capturista: ${capturista}`,
            input: 'text',
            inputPlaceholder: 'Comentario u observación opcional...',
            icon: decision === 'autorizada' ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: decision === 'autorizada' ? '#10b981' : '#ef4444',
            confirmButtonText: decision === 'autorizada' ? 'Sí, Autorizar Ahora' : 'Sí, Rechazar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/autorizaciones/${id}/resolver`,
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        decision: decision,
                        comentario: result.value || ''
                    },
                    success: function(res) {
                        cargarPeticiones();
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: res.mensaje,
                            showConfirmButton: false,
                            timer: 2500
                        });
                    }
                });
            }
        });
    });

    $('#btnRefrescarPeticiones').click(cargarPeticiones);
    $('#filtroEstadoPeticion, #filtroTipoPeticion').change(cargarPeticiones);

    // Suscripción en tiempo real mediante WebSockets
    if (typeof window.Echo !== 'undefined') {
        try {
            window.Echo.channel('autorizaciones-captura')
                .listen('.peticion.actualizada', function(e) {
                    console.log('⚡ Evento WebSocket de autorización recibido:', e);
                    cargarPeticiones();
                });
        } catch (err) {
            console.warn('Error al suscribir a autorizaciones-captura:', err);
        }
    }

    // Respaldo automático cada 5 segundos
    setInterval(cargarPeticiones, 5000);
    cargarPeticiones();
});
</script>
@endsection
