@extends('layouts.admin')
@section('titulo', 'Gestión de Lotes Trabajándose')

<style>
    .badge {
        font-weight: 700 !important;
        font-size: 0.85rem !important;
        padding: 6px 12px !important;
        border-radius: 6px !important;
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
        color: #1e293b !important;
    }

    .h3, h3 { font-size: 1.25rem !important; }
    .h5, h5 { font-size: 1rem !important; }

    .badge-en-proceso { background-color: #fff3cd !important; color: #856404 !important; border: 1px solid #ffeeba !important; }
    .badge-completado { background-color: #d1e7dd !important; color: #0f5132 !important; border: 1px solid #badbcc !important; }
    .badge-pendiente { background-color: #e2e3e5 !important; color: #383d41 !important; border: 1px solid #d6d8d9 !important; }
    
    .table-lotes th {
        background-color: #034d6d;
        color: #ffffff;
        font-weight: 600;
        vertical-align: middle;
    }
    
    .kpi-box {
        border-radius: 8px;
        padding: 15px;
        background: #fff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #034d6d;
    }
</style>

@section('contenido')

    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card shadow-lg" id="filtros">
                <div class="card-body">
                    <h1>MONITOREO DE LOTES <span style="color: #034d6d;">TRABAJÁNDOSE</span></h1>
                    <form id="formFiltros" class="row mb-12 align-items-end" style="margin-bottom: 1rem;" onsubmit="return false;">
                        <div class="col-sm-6 col-md-2 mb-2">
                            <label for="filtroOrigen" class="form-label font-weight-bold">Origen / Tipo:</label>
                            <select id="filtroOrigen" class="form-control">
                                <option value="">Todos los orígenes</option>
                                <option value="captura">Registros / Sellos</option>
                                <option value="catastro">Catastro</option>
                                <option value="notaria">Notarías</option>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-3 mb-2">
                            <label for="filtroEstado" class="form-label font-weight-bold">Estado del Lote:</label>
                            <select id="filtroEstado" class="form-control">
                                <option value="">Todos los estados</option>
                                <option value="en_proceso" selected>En Proceso (Trabajándose)</option>
                                <option value="completado">Completados</option>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-3 mb-2">
                            <label for="filtroDelegacion" class="form-label font-weight-bold">Delegación / Notaría:</label>
                            <select id="filtroDelegacion" class="form-control">
                                <option value="">Todas las delegaciones</option>
                                @foreach($delegaciones as $del)
                                    <option value="{{ $del->id }}">{{ $del->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-2 mb-2">
                            <label for="filtroUsuario" class="form-label font-weight-bold">Usuario:</label>
                            <select id="filtroUsuario" class="form-control">
                                <option value="">Todos los usuarios</option>
                                @foreach($usuarios as $usr)
                                    <option value="{{ $usr->id }}">{{ $usr->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-2 mb-2">
                            <button type="button" id="btnRefrescar" class="btn btn-primary w-100 font-weight-bold" style="background-color: #034d6d; border-color: #034d6d;">
                                <i class="mdi mdi-refresh me-1"></i> Actualizar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas KPI -->
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <div class="kpi-box">
                <span class="text-muted font-weight-bold d-block text-uppercase" style="font-size: 0.8rem;">Total Lotes Registrados</span>
                <h3 class="font-weight-bold m-0 text-dark" id="kpiTotal">0</h3>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="kpi-box" style="border-left-color: #ffc107;">
                <span class="text-muted font-weight-bold d-block text-uppercase" style="font-size: 0.8rem;">Lotes en Proceso</span>
                <h3 class="font-weight-bold m-0 text-warning" id="kpiEnProceso">0</h3>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="kpi-box" style="border-left-color: #28a745;">
                <span class="text-muted font-weight-bold d-block text-uppercase" style="font-size: 0.8rem;">Lotes Completados</span>
                <h3 class="font-weight-bold m-0 text-success" id="kpiCompletados">0</h3>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="kpi-box" style="border-left-color: #0dcaf0;">
                <span class="text-muted font-weight-bold d-block text-uppercase" style="font-size: 0.8rem;">Usuarios Activos</span>
                <h3 class="font-weight-bold m-0 text-info" id="kpiUsuariosActivos">0</h3>
            </div>
        </div>
    </div>

    <!-- Tabla Principal -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header py-3 d-flex align-items-center justify-content-between" style="background-color: #f1f5f9 !important; border-bottom: 1px solid #e2e8f0;">
                    <h6 class="m-0 font-weight-bold" style="color: #034d6d; font-size: 1.05rem;">
                        <i class="mdi mdi-folder-sync mr-2"></i>Lotes en Captura / Asignados (Registros, Sellos, Catastro y Notarías)
                    </h6>
                    <span class="badge badge-info" style="font-size: 0.85rem;" id="badgeResumen">Cargando lotes...</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-lotes align-middle mb-0" id="tablaLotes">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th style="width: 110px;" class="text-center">Tipo</th>
                                    <th>Nombre del Lote / Volumen</th>
                                    <th>Delegación / Notaría</th>
                                    <th>Usuario que lo Trabaja</th>
                                    <th class="text-center">Estado</th>
                                    <th>Progreso / PC / Ruta</th>
                                    <th class="text-center">Tomado En</th>
                                    <th class="text-center">Última Actividad</th>
                                    <th style="width: 90px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyLotes">
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="mdi mdi-loading mdi-spin me-2 fs-4"></i> Cargando lotes trabajándose...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
$(document).ready(function() {

    function cargarLotes() {
        const origen = $('#filtroOrigen').val();
        const estado = $('#filtroEstado').val();
        const delegacion_id = $('#filtroDelegacion').val();
        const user_id = $('#filtroUsuario').val();

        $('#tbodyLotes').html(`
            <tr>
                <td colspan="10" class="text-center py-4 text-muted">
                    <i class="mdi mdi-loading mdi-spin me-2 fs-4"></i> Cargando información de lotes...
                </td>
            </tr>
        `);

        $.ajax({
            url: "{{ route('admin.analytics_sellos.reportes.obtener_lotes_trabajando') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                origen: origen,
                estado: estado,
                delegacion_id: delegacion_id,
                user_id: user_id
            },
            success: function(res) {
                const lotes = res.lotes || [];
                const kpis = res.kpis || { total: 0, en_proceso: 0, completados: 0, usuarios_activos: 0 };

                // Actualizar KPIs
                $('#kpiTotal').text(Number(kpis.total).toLocaleString('es-MX'));
                $('#kpiEnProceso').text(Number(kpis.en_proceso).toLocaleString('es-MX'));
                $('#kpiCompletados').text(Number(kpis.completados).toLocaleString('es-MX'));
                $('#kpiUsuariosActivos').text(Number(kpis.usuarios_activos).toLocaleString('es-MX'));

                $('#badgeResumen').text(`${lotes.length} lotes encontrados`);

                if (lotes.length === 0) {
                    $('#tbodyLotes').html(`
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="mdi mdi-check-all me-2 fs-4"></i> No hay lotes activos con los filtros seleccionados.
                            </td>
                        </tr>
                    `);
                    return;
                }

                let html = '';
                lotes.forEach((row, idx) => {
                    let badgeEstado = '';
                    const st = (row.estado || '').toLowerCase();

                    if (st === 'en_proceso') {
                        badgeEstado = `<span class="badge badge-en-proceso"><i class="mdi mdi-sync mdi-spin me-1"></i>EN PROCESO</span>`;
                    } else if (st === 'completado') {
                        badgeEstado = `<span class="badge badge-completado"><i class="mdi mdi-check-circle me-1"></i>COMPLETADO</span>`;
                    } else {
                        badgeEstado = `<span class="badge badge-pendiente">${st.toUpperCase()}</span>`;
                    }

                    let badgeDisco = row.disco && row.disco !== 'N/A' ? `<br><small class="text-muted"><i class="mdi mdi-harddisk me-1"></i>${row.disco}</small>` : '';

                    let badgeTipo = '';
                    let filaStyle = '';

                    if (row.es_notaria) {
                        badgeTipo = `<span class="badge" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 0.78rem; padding: 4px 8px;"><i class="mdi mdi-bank me-1"></i>NOTARÍA</span>`;
                        filaStyle = 'style="background-color: #f0f9ff;"';
                    } else if (row.es_catastro) {
                        badgeTipo = `<span class="badge" style="background-color: #ede9fe; color: #5b21b6; border: 1px solid #c4b5fd; font-size: 0.78rem; padding: 4px 8px;"><i class="mdi mdi-map-marker-radius me-1"></i>CATASTRO</span>`;
                        filaStyle = 'style="background-color: #faf5ff;"';
                    } else {
                        badgeTipo = `<span class="badge" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-size: 0.78rem; padding: 4px 8px;"><i class="mdi mdi-file-document-outline me-1"></i>CAPTURA</span>`;
                    }

                    html += `
                        <tr ${filaStyle}>
                            <td class="text-center font-weight-bold text-muted">${idx + 1}</td>
                            <td class="text-center">${badgeTipo}</td>
                            <td class="font-weight-bold text-dark">
                                ${row.nombre_lote}
                                ${badgeDisco}
                            </td>
                            <td class="font-weight-bold">${row.delegacion}</td>
                            <td class="text-primary font-weight-bold">
                                <i class="mdi mdi-account me-1"></i>${row.usuario}
                            </td>
                            <td class="text-center">${badgeEstado}</td>
                            <td class="small text-muted" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                ${row.progreso_archivo}
                            </td>
                            <td class="text-center small text-muted">${row.tomado_en}</td>
                            <td class="text-center small text-muted">${row.updated_at}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-danger font-weight-bold btn-eliminar-lote" data-id="${row.id}" data-tipo="${row.tipo_origen}" data-nombre="${row.nombre_lote}" title="Eliminar/Liberar Lote">
                                    <i class="mdi mdi-trash-can me-1"></i> Eliminar
                                </button>
                            </td>
                        </tr>
                    `;
                });

                $('#tbodyLotes').html(html);
            },
            error: function(err) {
                console.error("Error al obtener lotes:", err);
                $('#tbodyLotes').html(`
                    <tr>
                        <td colspan="10" class="text-center py-4 text-danger font-weight-bold">
                            <i class="mdi mdi-alert me-2 fs-4"></i> Error al cargar datos del servidor.
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Eliminar Lote
    $(document).on('click', '.btn-eliminar-lote', function() {
        const id = $(this).data('id');
        const tipo = $(this).data('tipo') || 'captura';
        const nombre = $(this).data('nombre');

        if (confirm(`¿Está seguro de eliminar el registro de lote '${nombre}'?\n\nEsto liberará el lote para que pueda volver a ser tomado por otro capturista.`)) {
            $.ajax({
                url: "{{ route('admin.analytics_sellos.reportes.eliminar_lote_trabajando') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    id: id,
                    tipo: tipo
                },
                success: function(res) {
                    if (res.exito) {
                        alert(res.mensaje);
                        cargarLotes();
                    } else {
                        alert(res.mensaje || "No se pudo eliminar el lote.");
                    }
                },
                error: function(err) {
                    alert("Error de conexión al intentar eliminar el lote.");
                }
            });
        }
    });

    // Eventos
    $('#btnRefrescar').click(cargarLotes);
    $('#filtroOrigen, #filtroEstado, #filtroDelegacion, #filtroUsuario').change(cargarLotes);

    // Suscripción en tiempo real mediante WebSockets (Laravel Echo)
    if (typeof window.Echo !== 'undefined') {
        try {
            window.Echo.channel('monitoreo-lotes')
                .listen('.lote.actualizado', function (e) {
                    console.log('⚡ Evento WebSocket recibido en monitoreo-lotes:', e);
                    cargarLotes();
                });
        } catch (err) {
            console.warn('No se pudo suscribir al canal monitoreo-lotes:', err);
        }
    }

    // Respaldo automático cada 15 segundos
    setInterval(cargarLotes, 15000);

    // Cargar datos iniciales
    cargarLotes();
});
</script>
@endsection