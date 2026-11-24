@extends('layouts.app')
@section('titulo', 'Búsqueda Global de Archivos PDF (Todos los Tipos)')

<style>
    .badge {
        font-weight: 700 !important;
        font-size: 0.72rem !important;
        padding: 4px 8px !important;
        border-radius: 6px !important;
    }
    .badge-renombrar { background-color: #d1e7dd !important; color: #0f5132 !important; border: 1px solid #badbcc !important; }
    .badge-validado { background-color: #cff4fc !important; color: #055160 !important; border: 1px solid #b6effb !important; }
    .badge-duplicar { background-color: #f8d7da !important; color: #842029 !important; border: 1px solid #f5c2c7 !important; }
    .badge-otro { background-color: #fff3cd !important; color: #664d03 !important; border: 1px solid #ffecb5 !important; }

    .table-busqueda {
        font-size: 0.82rem;
        width: 100%;
    }
    .table-busqueda th {
        background-color: #034d6d;
        color: #ffffff;
        font-weight: 600;
        vertical-align: middle;
        padding: 10px;
    }
    .table-busqueda td {
        padding: 8px 10px;
        vertical-align: middle;
        word-break: break-word;
    }
</style>

@section('contenido')
    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card shadow-sm" id="filtros">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <h2 class="h5 font-weight-bold m-0" style="color: #034d6d;">
                            <i class="fa-solid fa-magnifying-glass-chart mr-2"></i>BÚSQUEDA UNIFICADA DE ARCHIVOS PDF
                        </h2>
                        <span class="text-xs text-muted">Consulta simultánea en Registros, Sellos, Notarías y Catastro</span>
                    </div>
                    <form id="formFiltros" class="row align-items-end" onsubmit="return false;">
                        <div class="col-md-3 col-sm-12 mb-2">
                            <label for="inputBuscador" class="form-label font-weight-bold small mb-1">Nombre Original, Nuevo, Lote o Hash:</label>
                            <input type="text" id="inputBuscador" class="form-control form-control-sm" placeholder="Escriba nombre, folio, lote o hash..." autofocus>
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label for="estado_trabajo" class="form-label font-weight-bold small mb-1">Estado de Trabajo:</label>
                            <select id="estado_trabajo" name="estado_trabajo" class="form-control form-control-sm">
                                <option value="trabajados">Trabajados</option>
                                <option value="pendientes">Pendientes (Sin trabajar)</option>
                                <option value="en_captura">En captura (En curso)</option>
                                <option value="todos">Todos (Stellum + Trabajados)</option>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label for="tipo_archivo" class="form-label font-weight-bold small mb-1">Tipo de Archivo:</label>
                            <select id="tipo_archivo" name="tipo_archivo" class="form-control form-control-sm">
                                <option value="sellos">Sellos y Boletas</option>
                                <option value="">Todos los tipos</option>
                                <option value="registros">Registros</option>
                                <option value="notarias">Notarías</option>
                                <option value="catastro">Catastro</option>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label for="delegacion_id" class="form-label small mb-1">Delegación:</label>
                            <select id="delegacion_id" name="delegacion_id" class="form-control form-control-sm">
                                <option value="">Todas</option>
                                @foreach($delegaciones as $del)
                                    <option value="{{ $del->id }}">{{ $del->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-1 col-sm-6 mb-2">
                            <label for="fecha_inicio" class="form-label small mb-1">Desde:</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control form-control-sm" value="{{ date('Y-m-01') }}">
                        </div>

                        <div class="col-md-1 col-sm-6 mb-2">
                            <label for="fecha_fin" class="form-label small mb-1">Hasta:</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                        </div>

                        <div class="col-md-1 col-sm-12 mb-2">
                            <button type="button" id="btnBuscar" class="btn btn-sm btn-primary w-100 font-weight-bold" style="background-color: #034d6d; border-color: #034d6d;">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0 mb-4" style="overflow: hidden;">
                <div class="card-header py-2.5 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold" style="color: #034d6d; font-size: 0.95rem;">
                        <i class="fa-solid fa-table-list mr-2"></i>Resultados de Búsqueda Multi-Tabla
                    </h6>
                    <span class="badge badge-info" id="badgeResumen">Ingrese un término para buscar...</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-striped table-busqueda align-middle mb-0" id="tablaBusqueda">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th style="width: 100px;" class="text-center">Tipo</th>
                                    <th>Archivo Original</th>
                                    <th>Archivo Nuevo</th>
                                    <th>Rutas (Origen / Destino) & Hash</th>
                                    <th>Delegación / Lote</th>
                                    <th>Usuario</th>
                                    <th class="text-center">Acción</th>
                                    <th class="text-center">Estado PDF</th>
                                    <th class="text-center">Págs</th>
                                    <th class="text-center">Fecha / Hora</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyBusqueda">
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        Cargando resultados...
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
    function cargarBusqueda() {
        const busqueda = $('#inputBuscador').val().trim();
        const estado_trabajo = $('#estado_trabajo').val();
        const tipo_archivo = $('#tipo_archivo').val();
        const fecha_inicio = $('#fecha_inicio').val();
        const fecha_fin = $('#fecha_fin').val();
        const delegacion_id = $('#delegacion_id').val();

        $('#tbodyBusqueda').html(`
            <tr>
                <td colspan="11" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Buscando archivos (${estado_trabajo})...
                </td>
            </tr>
        `);

        $.ajax({
            url: "{{ route('admin.analytics_sellos.reportes.obtener_busqueda_archivos') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                busqueda: busqueda,
                estado_trabajo: estado_trabajo,
                tipo_archivo: tipo_archivo,
                fecha_inicio: fecha_inicio,
                fecha_fin: fecha_fin,
                delegacion_id: delegacion_id
            },
            success: function(res) {
                const registros = res.registros || [];
                $('#badgeResumen').text(`${registros.length} archivos encontrados`);

                if (registros.length === 0) {
                    $('#tbodyBusqueda').html(`
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                No se encontraron archivos con los filtros aplicados.
                            </td>
                        </tr>
                    `);
                    return;
                }

                let html = '';
                registros.forEach((row, idx) => {
                    const accionLower = (row.accion || '').toLowerCase();
                    let badgeAccion = `<span class="badge badge-otro">${(row.accion || 'OTRO').toUpperCase()}</span>`;
                    if (accionLower === 'pendiente') {
                        badgeAccion = `<span class="badge bg-slate-200 text-slate-700 border border-slate-300">PENDIENTE</span>`;
                    } else if (accionLower === 'en_captura') {
                        badgeAccion = `<span class="badge bg-blue-100 text-blue-800 border border-blue-300">EN CAPTURA</span>`;
                    } else if (accionLower.includes('renombrar') || accionLower.includes('renombrado')) {
                        badgeAccion = `<span class="badge badge-renombrar">RENOMBRADO</span>`;
                    } else if (accionLower.includes('validado') || accionLower.includes('validar')) {
                        badgeAccion = `<span class="badge badge-validado">VALIDADO</span>`;
                    } else if (accionLower.includes('inconsistencia')) {
                        badgeAccion = `<span class="badge badge-duplicar">INCONSISTENCIA</span>`;
                    } else if (accionLower.includes('duplicar') || accionLower.includes('duplicado')) {
                        badgeAccion = `<span class="badge badge-duplicar">DUPLICAR</span>`;
                    }

                    const tipoDoc = (row.tipo_documento || 'SELLOS').toUpperCase();
                    let badgeTipo = `<span class="badge bg-indigo-100 text-indigo-800 border border-indigo-200">${tipoDoc}</span>`;
                    if (tipoDoc === 'SELLOS') badgeTipo = `<span class="badge bg-emerald-100 text-emerald-800 border border-emerald-200">SELLOS</span>`;
                    if (tipoDoc === 'NOTARÍAS') badgeTipo = `<span class="badge bg-sky-100 text-sky-800 border border-sky-200">NOTARÍAS</span>`;
                    if (tipoDoc === 'CATASTRO') badgeTipo = `<span class="badge bg-amber-100 text-amber-800 border border-amber-200">CATASTRO</span>`;

                    const badgeDanado = row.archivo_danado
                        ? `<span class="badge bg-red-100 text-red-800 border border-red-300"><i class="fa-solid fa-triangle-exclamation mr-1"></i>DAÑADO</span>`
                        : `<span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200"><i class="fa-solid fa-check mr-1"></i>ÍNTEGRO</span>`;

                    const rutaOrig = row.ruta_origen ? `<div class="text-xs text-muted"><strong>Orig:</strong> ${row.ruta_origen}</div>` : '';
                    const rutaProp = row.ruta_propuesta ? `<div class="text-xs text-sky-700"><strong>Prop:</strong> ${row.ruta_propuesta}</div>` : '';
                    const rutaDest = row.ruta_destino ? `<div class="text-xs text-emerald-700"><strong>Dest:</strong> ${row.ruta_destino}</div>` : '';
                    const hashInfo = row.hash ? `<div class="text-xs font-mono text-slate-600">SHA256: ${row.hash.substring(0, 16)}...</div>` : '';

                    html += `
                        <tr>
                            <td class="text-center font-weight-bold text-muted">${idx + 1}</td>
                            <td class="text-center">${badgeTipo}</td>
                            <td class="font-weight-bold text-dark">${row.archivo_original || '-'}</td>
                            <td class="text-primary font-weight-bold">${row.archivo_nuevo || '-'}</td>
                            <td>${rutaOrig}${rutaProp}${rutaDest}${hashInfo || '<span class="text-xs text-muted">-</span>'}</td>
                            <td>
                                <div class="font-weight-bold small">${row.delegacion_nombre || '-'}</div>
                                <span class="badge bg-light text-dark border">${row.lote || 'N/A'}</span>
                            </td>
                            <td class="small">${row.usuario_nombre || '-'}</td>
                            <td class="text-center">${badgeAccion}</td>
                            <td class="text-center">${badgeDanado}</td>
                            <td class="text-center font-weight-bold">${row.paginas || 0}</td>
                            <td class="text-center small text-muted">${row.fecha_hora || row.created_at || '-'}</td>
                        </tr>
                    `;
                });

                $('#tbodyBusqueda').html(html);
            }
        });
    }

    $('#btnBuscar').click(cargarBusqueda);
    $('#inputBuscador').keypress(function(e) {
        if (e.which === 13) {
            e.preventDefault();
            cargarBusqueda();
        }
    });
    $('#estado_trabajo, #tipo_archivo, #fecha_inicio, #fecha_fin, #delegacion_id').change(cargarBusqueda);
    cargarBusqueda();
});
</script>
@endsection
