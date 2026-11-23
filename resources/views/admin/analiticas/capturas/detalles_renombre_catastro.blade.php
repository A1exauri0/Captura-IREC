@extends('layouts.report', ['tipoSeccionReporte' => 'renombres'])
@section('titulo', 'Reportes de desempeño capturas catastro')

<style>
    .badge {
        font-weight: 600;
        color: #000000;
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

    .h3,
    h3 {
        font-size: 1.25rem !important;
    }

    .h5,
    h5 {
        font-size: 1rem !important;
    }

    #modalEditarRenombre {
        width: 85% !important;
        max-height: 85% !important;
        height: 85% !important;
        top: 7% !important;
        left: 7.5% !important;
        right: 7.5% !important;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 12px 35px rgba(0,0,0,0.35) !important;
        z-index: 99999 !important;
    }
    .modal-overlay {
        z-index: 99998 !important;
    }

    .pagination {
        display: flex;
        padding-left: 0;
        list-style: none;
        border-radius: 0.25rem;
        gap: 4px;
    }
    .pagination li a, .pagination li span {
        position: relative;
        display: block;
        padding: 0.5rem 0.75rem;
        margin-left: -1px;
        line-height: 1.25;
        color: #034d6d;
        background-color: #fff;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        text-decoration: none;
    }
    .pagination li.active span {
        z-index: 3;
        color: #fff;
        background-color: #034d6d;
        border-color: #034d6d;
    }
    .pagination li.disabled span {
        color: #6c757d;
        pointer-events: none;
        background-color: #fff;
        border-color: #dee2e6;
    }
    .pagination li a:hover {
        background-color: #e9ecef;
        border-color: #dee2e6;
        color: #023850;
    }
</style>

@section('contenido')

    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card shadow-lg" id="filtros">
                <div class="card-body">
                    <h1>DETALLE DE RENOMBRE DE <span id="btnPalabraCatastro" style="user-select: none;">CATASTRO</span></h1>
                    <div class="row mb-12 align-items-end" style="margin-bottom: 1rem;">
                        <div class="col-sm-6 col-md-2 mb-2">
                            <label for="fechaInicio" class="form-label">Desde:</label>
                            <input type="date" id="fechaInicio" name="fechaInicio" class="form-control" value="{{ $inicio }}">
                        </div>

                        <div class="col-sm-6 col-md-2 mb-2">
                            <label for="fechaFin" class="form-label">Hasta:</label>
                            <input type="date" id="fechaFin" name="fechaFin" class="form-control" value="{{ $fin }}">
                        </div>

                        <div class="col-sm-6 col-md-2 mb-2">
                            <label for="tipoCatastro" class="form-label">Tipo de Catastro:</label>
                            <select id="tipoCatastro" name="tipoCatastro" class="form-control">
                                <option value="" {{ ($tipoCatastro ?? '') == '' ? 'selected' : '' }}>Todos</option>
                                <option value="Urbano" {{ ($tipoCatastro ?? '') == 'Urbano' ? 'selected' : '' }}>Catastro Urbano</option>
                                <option value="Rustico" {{ ($tipoCatastro ?? '') == 'Rustico' ? 'selected' : '' }}>Catastro Rústico</option>
                                <option value="Sin Estudio" {{ ($tipoCatastro ?? '') == 'Sin Estudio' ? 'selected' : '' }}>Catastro Sin Estudio</option>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-3 col-lg-2 mb-2">
                            <label for="usuarioSelect" class="form-label">Usuario:</label>
                            <select id="usuarioSelect" name="usuarioSelect" class="form-control">
                                <option value="" {{ ($userId ?? '') == '' ? 'selected' : '' }}>Todos los usuarios</option>
                                @foreach($usuarios as $usr)
                                    <option value="{{ $usr->id }}" {{ ($userId ?? '') == $usr->id ? 'selected' : '' }}>
                                        {{ $usr->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-3 col-lg-2 mb-2">
                            <label for="buscadorInput" class="form-label">Buscar Archivo:</label>
                            <input type="text" id="buscadorInput" class="form-control" placeholder="Original o Nuevo..." value="{{ $search }}">
                        </div>

                        <div class="col-sm-6 col-md-2 mb-2">
                            <label class="form-label" style="visibility: hidden; display: block; margin-bottom: 8px;">Descargar:</label>
                            <button type="button" id="btnDescargarExcel" class="btn btn-success btn-block"
                                style="background-color: #28a745; border-color: #28a745; height: calc(1.5em + .75rem + 2px); font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="bi bi-file-earmark-excel" viewBox="0 0 16 16">
                                    <path d="M5.884 6.68a.5.5 0 1 0-.768.64L7.349 10l-2.233 2.68a.5.5 0 0 0 .768.64L8 10.781l2.116 2.54a.5.5 0 0 0 .768-.641L8.651 10l2.233-2.68a.5.5 0 0 0-.768-.64L8 9.219l-2.116-2.54z" />
                                    <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.3 1.25c.085.13.11.307.11.95h2.07c-.642-.64-1.54-.83-2.18-.95zm.7 2.2V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V3.45H10z" />
                                </svg>
                                Descargar Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para descarga -->
    <form action="{{ route('admin.analytics_catastro.reportes.descargar_excel') }}" method="POST" id="formDescargarExcel"
        style="display: none;">
        @csrf
        <input type="hidden" name="inicio" id="excelFechaInicio">
        <input type="hidden" name="fin" id="excelFechaFin">
        <input type="hidden" name="tipo_catastro" id="excelTipoCatastro">
        <input type="hidden" name="user_id" id="excelUserId">
        <input type="hidden" name="search" id="excelSearch">
    </form>

    <!-- Acordeón de Registros Agrupados -->
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 12px;">
                <h5 class="m-0 font-weight-bold" style="color: #475569;">
                    Registros Totales Encontrados: {{ count($registrosArr) }}
                </h5>
            </div>

            @forelse($agrupados as $fecha => $usuariosDeFecha)
                @php
                    $fechaFormateada = \Carbon\Carbon::parse($fecha)->format('d/m/Y');
                @endphp
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden; border-left: 5px solid #034d6d !important;">
                    <div class="card-header py-3 d-flex align-items-center justify-content-between" style="background-color: #f1f5f9 !important; border-bottom: 1px solid #e2e8f0;">
                        <h6 class="m-0 font-weight-bold" style="color: #034d6d; font-size: 1.05rem;">
                            <i class="mdi mdi-calendar-range mr-2"></i>Día Laboral: {{ $fechaFormateada }}
                        </h6>
                        @if(isset($registrosPaginados) && $registrosPaginados && count($registrosArr) > 0)
                            @php
                                $tActual = $registrosArr[0]->Turno ?: 'Matutino';
                                $badgeStyle = '';
                                if (stripos($tActual, 'vespertino') !== false) {
                                    $badgeStyle = 'background-color: #d97706; color: white;';
                                } elseif (stripos($tActual, 'nocturno') !== false) {
                                    $badgeStyle = 'background-color: #4b5563; color: white;';
                                } else {
                                    $badgeStyle = 'background-color: #2563eb; color: white;';
                                }
                            @endphp
                            <span class="badge font-weight-bold px-3 py-2 text-xs" style="border-radius: 6px; {{ $badgeStyle }}">
                                Turno: {{ $tActual }}
                            </span>
                        @endif
                    </div>
                    <div class="card-body p-3">
                        <div class="accordion" id="accordion_{{ Str::slug($fecha) }}">
                            @foreach($usuariosDeFecha as $userName => $registrosUsuario)
                                @php
                                    $primerReg = $registrosUsuario->first();
                                    $turno = $primerReg->Turno ?: 'Matutino';
                                    $cant = count($registrosUsuario);
                                    $accordionId = "collapse_" . Str::slug($fecha . '_' . $userName);
                                    $headingId = "heading_" . Str::slug($fecha . '_' . $userName);
                                @endphp
                                <div class="card border mb-2" style="border-radius: 8px; overflow: hidden;">
                                    <div class="card-header p-0" id="{{ $headingId }}" style="background-color: #ffffff !important;">
                                        <button class="btn btn-link btn-block text-left p-3 d-flex justify-content-between align-items-center font-weight-semibold" 
                                            type="button" 
                                            data-toggle="collapse" 
                                            data-target="#{{ $accordionId }}" 
                                            aria-expanded="false" 
                                            aria-controls="{{ $accordionId }}"
                                            style="color: #1e293b; text-decoration: none; font-size: 0.95rem;">
                                            <span>
                                                <i class="mdi mdi-account mr-2 text-secondary"></i><strong>{{ $userName }}</strong> 
                                                <span class="badge badge-pill ml-2" style="
                                                    @if(stripos($turno, 'vespertino') !== false) background-color: #fef3c7; color: #d97706;
                                                    @elseif(stripos($turno, 'matutino') !== false) background-color: #dbeafe; color: #2563eb;
                                                    @else background-color: #f3f4f6; color: #4b5563; @endif">
                                                    {{ $turno }}
                                                </span>
                                            </span>
                                            <span style="color: #64748b; font-size: 0.9rem;">
                                                <strong>{{ $cant }}</strong> registros
                                                <i class="mdi mdi-chevron-down ml-2"></i>
                                            </span>
                                        </button>
                                    </div>
                                    <div id="{{ $accordionId }}" class="collapse" aria-labelledby="{{ $headingId }}" data-parent="#accordion_{{ Str::slug($fecha) }}">
                                        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                                            <table class="table table-striped table-hover mb-0" style="width: 100%;">
                                                <thead>
                                                    <tr style="background-color: #f8fafc; color: #475569; font-weight: 700; border-bottom: 2px solid #e2e8f0; font-size: 0.9rem;">
                                                        <th class="py-3 px-4" style="width: 35%;">Archivo Original</th>
                                                        <th class="py-3 px-4" style="width: 35%;">Archivo Nuevo</th>
                                                        <th class="py-3 px-3" style="width: 10%;">Tipo</th>
                                                        <th class="py-3 px-3" style="width: 10%;">Acción</th>
                                                        <th class="py-3 px-3 text-center" style="width: 5%;">PDF</th>
                                                        <th class="py-3 px-4 text-right" style="width: 5%;">Fecha y Hora</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($registrosUsuario as $reg)
                                                        <tr style="font-size: 0.9rem;">
                                                            <td class="py-3 px-4" style="width: 35%; word-break: break-all; white-space: normal; color: #4b5563;">
                                                                {{ $reg->ArchivoOriginal }}
                                                            </td>
                                                            <td class="py-3 px-4 font-weight-semibold" style="width: 35%; word-break: break-all; white-space: normal; color: #111827;">
                                                                {{ $reg->ArchivoNuevo ?: '-' }}
                                                            </td>
                                                            <td class="py-3 px-3" style="width: 10%;">
                                                                <span class="badge text-xs p-1" style="background-color: #e0e7ff; color: #3730a3;">
                                                                    {{ $reg->Tipo ?: 'Urbano' }}
                                                                </span>
                                                            </td>
                                                            <td class="py-3 px-3" style="width: 10%;">
                                                                @php
                                                                    $accionLower = strtolower($reg->Accion);
                                                                    $badgeBg = '#f1f5f9';
                                                                    $badgeColor = '#475569';
                                                                    
                                                                    if (strpos($accionLower, 'renombrar') !== false || strpos($accionLower, 'renombrado') !== false || strpos($accionLower, 'renombre') !== false) {
                                                                        $badgeBg = '#ffedd5';
                                                                        $badgeColor = '#ea580c';
                                                                    } elseif (strpos($accionLower, 'validado') !== false || strpos($accionLower, 'validar') !== false || strpos($accionLower, 'valida') !== false) {
                                                                        $badgeBg = '#dcfce7';
                                                                        $badgeColor = '#16a34a';
                                                                    } elseif (strpos($accionLower, 'elimina') !== false) {
                                                                        $badgeBg = '#fee2e2';
                                                                        $badgeColor = '#b91c1c';
                                                                    }
                                                                @endphp
                                                                <span class="badge text-xs p-1" style="font-weight: 600; background-color: {{ $badgeBg }}; color: {{ $badgeColor }};">
                                                                    {{ ucwords(strtolower($reg->Accion)) }}
                                                                </span>
                                                            </td>
                                                            <td class="py-3 px-3 text-center" style="width: 5%;">
                                                                @if(!empty($reg->Ruta))
                                                                    <div class="d-flex align-items-center justify-content-center" style="gap: 8px;">
                                                                        <a href="{{ route('admin.analytics_catastro.reportes.ver_pdf', $reg->Id) }}" target="_blank" class="text-danger" title="Ver PDF" style="font-size: 1.45rem; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                                                            <i class="mdi mdi-file-pdf-box"></i>
                                                                        </a>
                                                                        <button type="button" class="btn btn-link text-primary p-0 btn-editar-renombre" 
                                                                            title="Editar Renombre" 
                                                                            data-id="{{ $reg->Id }}"
                                                                            data-ruta-pdf="{{ route('admin.analytics_catastro.reportes.ver_pdf', $reg->Id) }}"
                                                                            data-archivo-nuevo="{{ $reg->ArchivoNuevo ?: $reg->ArchivoOriginal }}"
                                                                            data-tipo="{{ $reg->Tipo }}"
                                                                            style="font-size: 1.25rem; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.2s;" 
                                                                            onmouseover="this.style.transform='scale(1.2)'" 
                                                                            onmouseout="this.style.transform='scale(1)'">
                                                                            <i class="mdi mdi-pencil"></i>
                                                                        </button>
                                                                    </div>
                                                                @else
                                                                    <span class="text-muted" style="font-size: 0.8rem;">Sin Ruta</span>
                                                                @endif
                                                            </td>
                                                            <td class="py-3 px-4 text-right text-secondary" style="width: 5%;">
                                                                {{ \Carbon\Carbon::parse($reg->FechaHora)->format('H:i:s d/m/Y') }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="card shadow border-0 py-5 text-center text-muted" style="border-radius: 12px;">
                    No se encontraron registros de renombrado para los filtros especificados.
                </div>
            @endforelse

            @if(isset($registrosPaginados) && $registrosPaginados)
                <div class="d-flex justify-content-center mt-4 mb-5 card-pagination">
                    {{ $registrosPaginados->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal de Edición de Renombre (Materialize CSS) -->
    <div id="modalEditarRenombre" class="modal" style="width: 90%; max-height: 90%; height: 90%; display: none; border-radius: 12px; overflow: hidden;">
        <div class="modal-content" style="padding: 0; height: 100%; display: flex; flex-direction: column; background-color: #ffffff;">
            <div class="modal-header py-3 px-4 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #034d6d, #0284c7); color: white; min-height: 56px;">
                <h5 class="modal-title font-weight-bold m-0" style="font-size: 1.2rem; color: white;">
                    <i class="mdi mdi-pencil-box-multiple mr-2"></i>Editar Nombre de Archivo Catastro
                </h5>
                <button type="button" class="modal-close btn-flat text-white p-0" style="opacity: 0.8; font-size: 1.5rem; background: none; border: none; cursor: pointer; color: white; line-height: 1;">
                    &times;
                </button>
            </div>
            
            <div class="row no-gutters m-0" style="flex: 1; height: calc(100% - 56px);">
                <!-- Columna Izquierda: Visor PDF -->
                <div class="col border-right p-0" style="background-color: #f8fafc; height: 100%; max-width: 58%; flex: 0 0 58%;">
                    <iframe src="" id="iframePdfEditar" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
                <!-- Columna Derecha: Formulario de Inputs por Bloques -->
                <div class="col p-4 d-flex flex-column justify-content-between" style="background-color: #ffffff; height: 100%; max-width: 42%; flex: 0 0 42%; overflow-y: auto;">
                    <div>
                        <h6 class="font-weight-bold text-secondary mb-3" style="font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            Estructura de Nombre
                        </h6>
                        <div class="alert alert-info p-3 mb-4" style="border-radius: 8px; border-left: 4px solid #0284c7; background-color: #f0f9ff; color: #0369a1; font-size: 0.85rem; line-height: 1.4;">
                            <i class="mdi mdi-information-outline mr-1"></i>Modifique los campos o bloques correspondientes. La vista previa se actualizará automáticamente.
                        </div>
                        
                        <form id="formEditarRenombre">
                            <input type="hidden" id="editRegId" name="id">
                            
                            <!-- Contenedor dinámico de bloques -->
                            <div id="contenedorBloques" class="d-flex flex-wrap align-items-center justify-content-start mb-4" style="gap: 12px 6px;">
                                <!-- Inputs generados por JS -->
                            </div>

                            <div class="form-group mb-4">
                                <label class="font-weight-bold text-secondary" style="font-size: 0.85rem; display: block; margin-bottom: 8px;">Vista previa del nuevo nombre:</label>
                                <div id="vistaPreviaNombre" class="p-3 font-weight-bold border text-center" style="border-radius: 8px; background-color: #f8fafc; color: #0f172a; font-size: 1rem; border-color: #cbd5e1; word-break: break-all;">
                                    -
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="border-top pt-3 d-flex justify-content-end" style="gap: 10px;">
                        <button type="button" class="modal-close btn btn-secondary px-4 font-weight-bold" style="border-radius: 8px; background-color: #64748b; color: white;">Cancelar</button>
                        <button type="button" id="btnGuardarRenombre" class="btn btn-primary px-4 font-weight-bold" style="background: linear-gradient(135deg, #034d6d, #0284c7); border: none; border-radius: 8px; height: 36px; color: white; display: inline-flex; align-items: center; justify-content: center; box-shadow: none;">
                            <i class="mdi mdi-content-save mr-1"></i>Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function () {
            const fechaInicioInput = document.getElementById('fechaInicio');
            const fechaFinInput = document.getElementById('fechaFin');
            const tipoCatastroInput = document.getElementById('tipoCatastro');
            const usuarioSelectInput = document.getElementById('usuarioSelect');
            const buscadorInput = document.getElementById('buscadorInput');

            function aplicarFiltros() {
                const inicio = fechaInicioInput ? fechaInicioInput.value : '';
                const fin = fechaFinInput ? fechaFinInput.value : '';
                const tipo_catastro = tipoCatastroInput ? tipoCatastroInput.value : '';
                const user_id = usuarioSelectInput ? usuarioSelectInput.value : '';
                const search = buscadorInput ? buscadorInput.value.trim() : '';
                
                let url = `{{ route("admin.analytics_catastro.reportes.detalles_renombre") }}?inicio=${inicio}&fin=${fin}&tipo_catastro=${tipo_catastro}&user_id=${user_id}&search=${encodeURIComponent(search)}`;
                window.location.href = url;
            }

            if (fechaInicioInput) fechaInicioInput.addEventListener('change', aplicarFiltros);
            if (fechaFinInput) fechaFinInput.addEventListener('change', aplicarFiltros);
            if (tipoCatastroInput) tipoCatastroInput.addEventListener('change', aplicarFiltros);
            if (usuarioSelectInput) usuarioSelectInput.addEventListener('change', aplicarFiltros);

            if (buscadorInput) {
                buscadorInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        aplicarFiltros();
                    }
                });
            }

            const btnDescargarExcel = document.getElementById('btnDescargarExcel');
            if (btnDescargarExcel) {
                btnDescargarExcel.addEventListener('click', function () {
                    document.getElementById('excelFechaInicio').value = fechaInicioInput ? fechaInicioInput.value : '';
                    document.getElementById('excelFechaFin').value = fechaFinInput ? fechaFinInput.value : '';
                    document.getElementById('excelTipoCatastro').value = tipoCatastroInput ? tipoCatastroInput.value : '';
                    document.getElementById('excelUserId').value = usuarioSelectInput ? usuarioSelectInput.value : '';
                    document.getElementById('excelSearch').value = buscadorInput ? buscadorInput.value.trim() : '';
                    document.getElementById('formDescargarExcel').submit();
                });
            }

            // Edición de Renombre con Modal
            $('.btn-editar-renombre').on('click', function () {
                const id = $(this).data('id');
                const rutaPdf = $(this).data('ruta-pdf');
                const archivoNuevo = $(this).data('archivo-nuevo') || '';

                $('#editRegId').val(id);
                $('#iframePdfEditar').attr('src', rutaPdf);

                construirBloquesNombre(archivoNuevo);

                const modal = document.getElementById('modalEditarRenombre');
                if (typeof M !== 'undefined' && M.Modal) {
                    const instance = M.Modal.getInstance(modal) || M.Modal.init(modal, { dismissible: true });
                    instance.open();
                } else {
                    $('#modalEditarRenombre').show();
                }
            });

            function construirBloquesNombre(nombreCompleto) {
                const contenedor = document.getElementById('contenedorBloques');
                contenedor.innerHTML = '';

                let nombreSinExt = nombreCompleto.replace(/\.[^/.]+$/, "");
                let partes = nombreSinExt.split('_');

                if (partes.length <= 1) {
                    const div = document.createElement('div');
                    div.style.width = '100%';
                    div.innerHTML = `
                        <label class="text-xs font-weight-bold text-muted mb-1 d-block">Nombre del Archivo:</label>
                        <input type="text" class="form-control bloque-input" value="${nombreSinExt}" style="width: 100%; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 6px 10px; font-weight: bold; color: #1e293b;">
                    `;
                    contenedor.appendChild(div);
                } else {
                    partes.forEach((parte, idx) => {
                        const div = document.createElement('div');
                        div.className = 'd-flex align-items-center';
                        div.style.gap = '4px';

                        div.innerHTML = `
                            <div class="d-flex flex-column align-items-center">
                                <label class="text-xs font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">Bloque ${idx + 1}</label>
                                <input type="text" class="form-control text-center bloque-input" 
                                    data-index="${idx}" 
                                    value="${parte}" 
                                    style="width: ${Math.max(parte.length * 11 + 25, 65)}px; border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 6px 8px; font-weight: bold; color: #1e293b;">
                            </div>
                            ${idx < partes.length - 1 ? '<span class="font-weight-bold text-secondary mx-1" style="margin-top: 18px; font-size: 1.1rem;">_</span>' : ''}
                        `;
                        contenedor.appendChild(div);
                    });
                }

                $('.bloque-input').on('input', actualizarVistaPrevia);
                actualizarVistaPrevia();
            }

            function actualizarVistaPrevia() {
                const inputs = document.querySelectorAll('.bloque-input');
                let partes = [];
                inputs.forEach(input => {
                    partes.push(input.value.trim());
                });
                let nuevoNombre = partes.join('_') + '.pdf';
                document.getElementById('vistaPreviaNombre').textContent = nuevoNombre;
            }

            $('#btnGuardarRenombre').on('click', function () {
                const id = $('#editRegId').val();
                const nuevoNombre = document.getElementById('vistaPreviaNombre').textContent.trim();

                if (!id || !nuevoNombre || nuevoNombre === '-' || nuevoNombre === '.pdf') {
                    alert('Por favor verifique los bloques del nombre.');
                    return;
                }

                const btn = $(this);
                btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i>Guardando...');

                fetch('{{ route("admin.analytics_catastro.reportes.editar_renombre") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        id: id,
                        nombre_nuevo: nuevoNombre
                    })
                })
                .then(res => res.json())
                .then(data => {
                    btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i>Guardar Cambios');
                    if (data.ok) {
                        alert(data.mensaje || 'Archivo renombrado con éxito');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.mensaje || 'No se pudo renombrar el archivo'));
                    }
                })
                .catch(err => {
                    btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i>Guardar Cambios');
                    alert('Error en el servidor: ' + err.message);
                });
            });
        });
    </script>
@endsection