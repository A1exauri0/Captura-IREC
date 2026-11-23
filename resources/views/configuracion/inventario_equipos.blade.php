@extends('layouts.app')
@section('titulo', 'Inventario de Equipos & Estaciones (Sincronizado con Stellum)')

@section('pre-script')
    <link rel="stylesheet" href="{{ asset('css/equipos/inventario_equipos.css') }}?v={{ time() }}">
@endsection

@section('contenido')
<div class="modulo-computadoras">

    <!-- Barra de Navegación Superior del Módulo -->
    <header class="barra-navegacion rounded-lg shadow-sm mb-3">
        <div class="contenedor-nav py-2 px-3">
            <div class="logo-seccion">
                <div class="logo-icono" style="font-size: 2rem;">💻</div>
                <div class="logo-titulo">
                    <h1 class="h4 mb-0 font-weight-bold text-dark">Inventario de Equipos & Estaciones (Sincronizado con Stellum)</h1>
                    <p class="text-muted small mb-0">Control de formateo, preparación, entrega y marcas de tiempo</p>
                </div>
            </div>
            <div class="acciones-nav d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
                <button type="button" id="btnAlternarKpis" class="btn btn-outline-secondary btn-sm" title="Mostrar u ocultar indicadores rápidos (KPIs)">
                    <span id="iconoAlternarKpis">📊</span> <span id="textoAlternarKpis">Ocultar KPIs</span>
                </button>
                <a href="{{ route('admin.equipos.exportar_excel') }}" class="btn btn-success btn-sm" id="btnDescargarExcel" title="Descargar libro de Excel multi-hoja con Laptops, PC, Monitores y Cajas">
                    <span>📊</span> Descargar Excel
                </a>
                <button type="button" id="btnNuevoEquipo" class="btn btn-primary btn-sm">
                    <span>➕</span> Registrar Equipo
                </button>
            </div>
        </div>
    </header>

    <!-- Contenedor Principal -->
    <main class="contenedor-principal">

        <!-- Panel Sincronizado: Asignación de PC / Laptop a Usuarios de Captura-IREC -->
        <section class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-2.5" style="gap: 8px;">
                <div>
                    <i class="fa-solid fa-computer text-sky-700 mr-1.5"></i>
                    <strong>Asignación de PC / Laptop por Usuario</strong>
                    <span class="badge badge-info ml-1">Sincronizado en vivo con Stellum</span>
                </div>
                <button type="button" class="btn btn-sm btn-primary" style="background-color:#034d6d;border-color:#034d6d;" data-toggle="modal" data-target="#modalAsignarPcUsuario">
                    <i class="fa-solid fa-link mr-1"></i> Asignar PC a Usuario
                </button>
            </div>
            <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                <table class="table table-sm table-hover mb-0 text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
                        <tr>
                            <th class="px-3">Usuario</th>
                            <th>Turno</th>
                            <th>Equipo Asignado (No. Serie)</th>
                            <th>Tipo / Marca / Ubicación</th>
                            <th class="text-right px-3">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $u)
                            <tr>
                                <td class="px-3 font-weight-bold text-slate-800">{{ $u->name }} <span class="text-xs text-muted">({{ $u->user }})</span></td>
                                <td class="text-xs">{{ $u->turnoRelacion->nombre ?? $u->turno ?? 'Matutino' }}</td>
                                <td>
                                    @if($u->equipo_no_serie)
                                        <span class="badge badge-primary" style="background-color:#034d6d;">{{ $u->equipo_no_serie }}</span>
                                    @else
                                        <span class="text-xs text-muted">Sin equipo asignado</span>
                                    @endif
                                </td>
                                <td class="text-xs text-muted">
                                    @if($u->equipo_no_serie)
                                        {{ $u->equipo_tipo ?: 'PC' }} {{ $u->equipo_marca ? '· ' . $u->equipo_marca : '' }} {{ $u->equipo_ubicacion ? '· ' . $u->equipo_ubicacion : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right px-3">
                                    @if($u->equipo_no_serie)
                                        <form method="POST" action="{{ route('admin.equipos.desasignar_usuario', $u->id) }}" class="d-inline" onsubmit="return confirm('¿Liberar equipo {{ $u->equipo_no_serie }} de {{ $u->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2">Liberar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Modal Asignar PC de Stellum a Usuario -->
        <div class="modal fade" id="modalAsignarPcUsuario" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('admin.equipos.asignar_usuario') }}" class="modal-content border-0 shadow-lg">
                    @csrf
                    <div class="modal-header bg-slate-900 text-white">
                        <h5 class="modal-title text-base font-weight-bold"><i class="fa-solid fa-laptop-medical mr-2 text-sky-400"></i>Asignar Equipo de Stellum a Usuario</h5>
                        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="text-xs font-weight-bold">Usuario</label>
                            <select name="user_id" class="form-control" required>
                                <option value="">-- Seleccionar usuario --</option>
                                @foreach($usuarios as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->user }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="text-xs font-weight-bold">Seleccionar PC / Laptop desde Stellum</label>
                            <select id="selectEquipoStellumModal" class="form-control" onchange="seleccionarEquipoStellum(this)">
                                <option value="">-- Cargando equipos desde Stellum... --</option>
                            </select>
                            <small class="text-muted">Al asignar, se guardará en Captura-IREC y actualizará el préstamo en Stellum automáticamente.</small>
                        </div>
                        <div class="form-group mb-0">
                            <label class="text-xs font-weight-bold">No. Serie (auto-llenado o manual)</label>
                            <input type="text" name="equipo_no_serie" id="inputEquipoSerieModal" class="form-control" required placeholder="Ej. MZ02XXXX">
                            <input type="hidden" name="equipo_tipo" id="inputEquipoTipoModal">
                            <input type="hidden" name="equipo_tabla" id="inputEquipoTablaModal">
                            <input type="hidden" name="equipo_id_stellum" id="inputEquipoIdStellumModal">
                            <input type="hidden" name="equipo_marca" id="inputEquipoMarcaModal">
                            <input type="hidden" name="equipo_ubicacion" id="inputEquipoUbicacionModal">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm" style="background-color:#034d6d;border-color:#034d6d;">Guardar Asignación</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Métricas / Estadísticas Rápidas -->
        <section class="grid-estadisticas" id="seccionKpis">
            <div class="tarjeta-stat">
                <div class="icono-stat stat-azul">💻</div>
                <div class="info-stat">
                    <div id="statTotalEquipos" class="valor">0</div>
                    <div class="etiqueta">Total de Equipos</div>
                </div>
            </div>

            <div class="tarjeta-stat">
                <div class="icono-stat stat-naranja">⏳</div>
                <div class="info-stat">
                    <div id="statEnFormateo" class="valor">0</div>
                    <div class="etiqueta">En Formateo</div>
                </div>
            </div>

            <div class="tarjeta-stat">
                <div class="icono-stat stat-verde">✔</div>
                <div class="info-stat">
                    <div id="statFormateadas" class="valor">0</div>
                    <div class="etiqueta">Formateadas</div>
                </div>
            </div>

            <div class="tarjeta-stat">
                <div class="icono-stat stat-cyan">📦</div>
                <div class="info-stat">
                    <div id="statListas" class="valor">0</div>
                    <div class="etiqueta">Listas para Entrega</div>
                </div>
            </div>

            <div class="tarjeta-stat">
                <div class="icono-stat stat-morado">🚚</div>
                <div class="info-stat">
                    <div id="statEntregadas" class="valor">0</div>
                    <div class="etiqueta">Entregadas</div>
                </div>
            </div>
        </section>

        <!-- Pestañas por Tipo de Tabla -->
        <div class="seccion-pestanas">
            <button class="pestana-boton activa" data-pestana="todas">
                <span>📋 Todos los Equipos</span>
                <span id="contadorTodas" class="contador-badge">0</span>
            </button>
            <button class="pestana-boton" data-pestana="laptops">
                <span>💻 Laptops</span>
                <span id="contadorLaptops" class="contador-badge">0</span>
            </button>
            <button class="pestana-boton" data-pestana="computadoras">
                <span>🖥️ Computadoras (PC)</span>
                <span id="contadorPCs" class="contador-badge">0</span>
            </button>
            <button class="pestana-boton" data-pestana="monitores">
                <span>🖥️ Monitores</span>
                <span id="contadorMonitores" class="contador-badge">0</span>
            </button>
            <button class="pestana-boton" data-pestana="cajas">
                <span>📦 Cajas</span>
                <span id="contadorCajas" class="contador-badge">0</span>
            </button>
        </div>

        <!-- Panel de Búsqueda y Filtros Globales -->
        <section class="panel-filtros">
            <div class="grupo-busqueda">
                <span class="icono-busqueda">🔍</span>
                <input type="text" id="inputBuscar" placeholder="Buscar por no. de serie, observaciones o marca...">
            </div>

            <div class="grupo-selectores">
                <select id="filtroMarca" class="selector-filtro">
                    <option value="">Todas las Marcas</option>
                </select>

                <select id="filtroUbicacion" class="selector-filtro">
                    <option value="">Todas las Ubicaciones</option>
                    <option value="Versalles">Versalles</option>
                    <option value="5 de mayo - sala 2">5 de mayo - sala 2</option>
                    <option value="5 de mayo - sala 3">5 de mayo - sala 3</option>
                    <option value="RPP">RPP</option>
                    <option value="IREC">IREC</option>
                </select>

                <select id="filtroTecnico" class="selector-filtro">
                    <option value="">Todos los Técnicos</option>
                </select>

                <select id="filtroEstado" class="selector-filtro">
                    <option value="">Todos los Estados</option>
                    <option value="en_proceso">⏳ En Formateo</option>
                    <option value="formateada">✔ Formateada</option>
                    <option value="error">✖ Con Error</option>
                    <option value="lista">📦 Lista</option>
                    <option value="entregada">🚚 Entregada</option>
                </select>
            </div>
        </section>

        <!-- Tabla Principal de Registros -->
        <section class="contenedor-tabla">
            <!-- Barra Superior de Conteo y Estado de Filtrado -->
            <div class="barra-estado-tabla">
                <div class="info-conteo-filtrados">
                    <span class="icono-contador">📊</span>
                    <span id="contadorFiltrados" class="texto-contador-filtrados">Cargando equipos...</span>
                    <span id="badgeFiltroActivo" class="badge-filtro-activo" style="display: none;">Filtro activo</span>
                </div>
                <div class="acciones-conteo-tabla">
                    <button type="button" id="btnEdicionMasiva" class="btn-edicion-masiva" style="display: none;" onclick="abrirModalEdicionMasiva()" title="Modificar en masa los equipos seleccionados">
                        ⚡ Cambios masivos (<span id="contadorMasivo">0</span>)
                    </button>
                    <button type="button" id="btnQuitarSeleccionPase" class="btn-quitar-seleccion" style="display: none;" onclick="desmarcarTodoPaseLista()" title="Quitar la selección de filas verdes marcadas en el pase de lista">
                        ⬜ Quitar selección (<span id="contadorPaseLista">0</span>)
                    </button>
                    <button type="button" id="btnLimpiarFiltrosRapido" class="btn-limpiar-rapido" style="display: none;" onclick="limpiarTodosLosFiltros()">
                        ✕ Limpiar filtros
                    </button>
                    <button type="button" id="btnRestablecerAnchos" class="btn-limpiar-rapido" onclick="restablecerAnchosColumnas()" title="Restablecer el ancho de las columnas a su tamaño por defecto">
                        ↔️ Restablecer anchos
                    </button>
                    <div class="dropdown-columnas-wrapper" style="position: relative; display: inline-block;">
                        <button type="button" id="btnMenuColumnas" class="btn-limpiar-rapido" onclick="alternarMenuColumnas(event)" title="Mostrar u ocultar columnas de la tabla">
                            👁️ Columnas
                        </button>
                        <div id="menuSelectorColumnas" class="menu-selector-columnas" style="display: none;">
                            <div class="menu-columnas-header">
                                <strong>Columnas visibles</strong>
                                <button type="button" class="btn-link-sm" onclick="restablecerColumnasVisibles()">Restablecer</button>
                            </div>
                            <div class="menu-columnas-lista" id="listaColumnasVisibles"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra Superior Sincronizada de Scroll Horizontal -->
            <div class="tabla-scroll-superior" id="tablaScrollSuperior" title="Barra superior de desplazamiento horizontal">
                <div class="tabla-scroll-superior-riel" id="tablaScrollSuperiorRiel"></div>
            </div>

            <div class="tabla-scroll" id="contenedorTablaScroll">
                <table class="tabla-equipos">
                    <thead>
                        <tr id="filaEncabezados">
                            <th id="colEncabezadoPaseLista" class="col-pase-lista" style="width: 44px; text-align: center;" title="Pase de lista (Marcar/Desmarcar fila verde)">
                                <input type="checkbox" id="checkPaseListaTodos" title="Marcar/Desmarcar todas las filas visibles" onchange="alternarTodosPaseLista(this.checked)">
                            </th>
                            <th id="colEncabezadoSerie" class="col-serie">No. Serie</th>
                            <th id="colEncabezadoMarca" class="col-marca">Marca</th>
                            <th id="colEncabezadoUbicacion" class="col-ubicacion">Ubicación</th>
                            <th id="colEncabezadoTecnico" class="col-tecnico">Formateado Por</th>
                            <th id="colEncabezadoObs" class="col-obs">Observaciones</th>
                            <th id="colEncabezadoFormateada" class="col-formateada">Formateada</th>
                            <th id="colEncabezadoTiempoFormateo" class="col-tiempo-formateo">Timestamp Formateo / Lista</th>
                            <th id="colEncabezadoCaja" class="col-caja">Caja</th>
                            <th id="colEncabezadoCargador" class="col-cargador">Cargador</th>
                            <th id="colEncabezadoCables" class="col-cables">Cables</th>
                            <th id="colEncabezadoMouse" class="col-mouse">Mouse</th>
                            <th id="colEncabezadoTeclado" class="col-teclado">Teclado</th>
                            <th id="colEncabezadoLista" class="col-lista">Lista</th>
                            <th id="colEncabezadoTipo" class="col-tipo">Tipo</th>
                            <th id="colEncabezadoEntregada" class="col-entregada">Entregada</th>
                            <th id="colEncabezadoTiempoEntrega" class="col-tiempo-entrega">Timestamp Entrega</th>
                            <th id="colEncabezadoPrestada" class="col-prestada" title="Persona a quien se le prestó el equipo">Préstamo</th>
                            <th id="colEncabezadoAcciones" class="col-acciones" style="text-align: center;">Acciones</th>
                        </tr>
                        <!-- Fila de Filtros para Todas las Columnas -->
                        <tr class="fila-filtros-tabla" id="filaFiltrosTabla">
                            <th id="colFiltroPaseLista" class="col-pase-lista" style="text-align: center;" title="Quitar selección verde de pase de lista">
                                <button type="button" class="btn-limpiar-pase-th" title="Quitar selección verde" onclick="desmarcarTodoPaseLista()">⬜</button>
                            </th>
                            <th id="colFiltroSerie" class="col-serie">
                                <input type="text" id="filtroColSerie" class="control-filtro-col" placeholder="Filtrar serie...">
                            </th>
                            <th id="colFiltroMarca" class="col-marca">
                                <select id="filtroColMarca" class="control-filtro-col">
                                    <option value="">Todas</option>
                                </select>
                            </th>
                            <th id="colFiltroUbicacion" class="col-ubicacion">
                                <select id="filtroColUbicacion" class="control-filtro-col">
                                    <option value="">Todas</option>
                                    <option value="Versalles">Versalles</option>
                                    <option value="5 de mayo - sala 2">5 de mayo - sala 2</option>
                                    <option value="5 de mayo - sala 3">5 de mayo - sala 3</option>
                                    <option value="RPP">RPP</option>
                                    <option value="IREC">IREC</option>
                                </select>
                            </th>
                            <th id="colFiltroTecnico" class="col-tecnico">
                                <select id="filtroColTecnico" class="control-filtro-col">
                                    <option value="">Todos</option>
                                    <option value="ADRIAN">A - Adrián</option>
                                    <option value="KERUUM">K - Keruum</option>
                                    <option value="sin">Sin asignar</option>
                                </select>
                            </th>
                            <th id="colFiltroObs" class="col-obs">
                                <select id="filtroColObs" class="control-filtro-col">
                                    <option value="">Todas</option>
                                    <option value="con">Con obs.</option>
                                    <option value="sin">Sin obs.</option>
                                </select>
                            </th>
                            <th id="colFiltroFormateada" class="col-formateada">
                                <select id="filtroColFormateada" class="control-filtro-col">
                                    <option value="">Todos</option>
                                    <option value="formateada">✔ Lista</option>
                                    <option value="en_proceso">⏳ Reloj</option>
                                    <option value="error">✖ Error</option>
                                    <option value="pendiente">- Pendiente</option>
                                </select>
                            </th>
                            <th id="colFiltroTiempoFormateo" class="col-tiempo-formateo">
                                <input type="text" id="filtroColTiempoFormateo" class="control-filtro-col" placeholder="Filtrar fecha...">
                            </th>
                            <th id="colFiltroCaja" class="col-caja">
                                <select id="filtroColCaja" class="control-filtro-col">
                                    <option value="">Todas</option>
                                    <option value="1">✔ Con caja</option>
                                    <option value="sin_serie">📦 Caja sin serie</option>
                                    <option value="0">✖ Sin caja</option>
                                </select>
                            </th>
                            <th id="colFiltroCargador" class="col-cargador">
                                <select id="filtroColCargador" class="control-filtro-col">
                                    <option value="">Todos</option>
                                    <option value="si">✔ Sí</option>
                                    <option value="advertencia">⚠️ Adv.</option>
                                    <option value="no">✖ No</option>
                                </select>
                            </th>
                            <th id="colFiltroCables" class="col-cables">
                                <select id="filtroColCables" class="control-filtro-col">
                                    <option value="">Todos</option>
                                    <option value="1">✔ Sí</option>
                                    <option value="0">✖ No</option>
                                </select>
                            </th>
                            <th id="colFiltroMouse" class="col-mouse">
                                <select id="filtroColMouse" class="control-filtro-col">
                                    <option value="">Todos</option>
                                    <option value="1">✔ Sí</option>
                                    <option value="0">✖ No</option>
                                </select>
                            </th>
                            <th id="colFiltroTeclado" class="col-teclado">
                                <select id="filtroColTeclado" class="control-filtro-col">
                                    <option value="">Todos</option>
                                    <option value="1">✔ Sí</option>
                                    <option value="0">✖ No</option>
                                </select>
                            </th>
                            <th id="colFiltroLista" class="col-lista">
                                <select id="filtroColLista" class="control-filtro-col">
                                    <option value="">Todas</option>
                                    <option value="1">✔ Lista</option>
                                    <option value="0">- No lista</option>
                                </select>
                            </th>
                            <th id="colFiltroTipo" class="col-tipo">
                                <select id="filtroColTipo" class="control-filtro-col">
                                    <option value="">Todos</option>
                                    <option value="laptop">💻 Laptop</option>
                                    <option value="PC">🖥️ PC</option>
                                    <option value="monitor">🖥️ Monitor</option>
                                    <option value="caja">📦 Caja</option>
                                </select>
                            </th>
                            <th id="colFiltroEntregada" class="col-entregada">
                                <select id="filtroColEntregada" class="control-filtro-col">
                                    <option value="">Todas</option>
                                    <option value="entregada">✔ Entregada</option>
                                    <option value="en_proceso">⏳ En proceso</option>
                                    <option value="pendiente">- Pendiente</option>
                                </select>
                            </th>
                            <th id="colFiltroTiempoEntrega" class="col-tiempo-entrega">
                                <input type="text" id="filtroColTiempoEntrega" class="control-filtro-col" placeholder="Filtrar fecha...">
                            </th>
                            <th id="colFiltroPrestada" class="col-prestada">
                                <input type="text" id="filtroColPrestada" class="control-filtro-col" placeholder="Filtrar préstamo / nombre...">
                            </th>
                            <th id="colFiltroAcciones" class="col-acciones" style="text-align: center;">
                                <button type="button" id="btnLimpiarFiltrosCol" class="btn-limpiar-filtros" title="Limpiar todos los filtros de las columnas">🧹</button>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTablaEquipos">
                        <!-- Filas renderizadas dinámicamente con JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Barra Inferior de Conteo y Resumen -->
            <div class="pie-info-tabla">
                <span id="contadorPieFiltrados">Cargando información...</span>
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <span class="texto-ayuda-pie">↔️ Pasa el mouse por el borde derecho de cualquier columna para modificar su ancho</span>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="restablecerAnchosColumnas()" title="Restablecer tamaño original de las columnas" style="padding: 0.2rem 0.55rem; font-size: 0.75rem;">
                        🔄 Restablecer anchos
                    </button>
                </div>
            </div>
        </section>

        <!-- Leyenda Informativa de Íconos -->
        <section class="seccion-leyenda">
            <span style="font-weight: 700; color: #334155;">Guía de estados e íconos:</span>
            <div class="item-leyenda">
                <span class="icono-estado estado-check">✔</span>
                <span>Completado / Sí</span>
            </div>
            <div class="item-leyenda">
                <span class="icono-estado estado-reloj">⏳</span>
                <span>En proceso / Reloj (Inicio de formateo o entrega)</span>
            </div>
            <div class="item-leyenda">
                <span class="icono-estado estado-alerta">⚠️</span>
                <span>Cargador con observación o advertencia</span>
            </div>
            <div class="item-leyenda">
                <span class="icono-estado estado-cruz">✖</span>
                <span>Sin accesorio o Error</span>
            </div>
            <div class="item-leyenda">
                <span class="tag-tiempo formateo">⏱️ Timestamp</span>
                <span>Hora de inicio de formateo</span>
            </div>
            <div class="item-leyenda">
                <span class="tag-tiempo lista">📦 Timestamp</span>
                <span>Hora en que quedó lista</span>
            </div>
            <div class="item-leyenda">
                <span class="tag-tiempo entrega">🚚 Timestamp</span>
                <span>Hora de entrega</span>
            </div>
        </section>

    </main>

    <!-- Modal Compacto para Registrar / Editar Equipo -->
    <div id="modalEquipo" class="modal-overlay">
        <div class="modal-contenedor-compacto">
            <div class="modal-encabezado">
                <h3 id="modalTitulo" class="h5 mb-0 font-weight-bold">Registrar Equipo</h3>
                <button type="button" id="btnCerrarModal" class="btn-cerrar-modal">&times;</button>
            </div>

            <form id="formEquipo">
                <div class="modal-cuerpo-compacto">
                    <input type="hidden" id="equipoId">

                    <div class="grid-formulario-compacto">
                        <!-- Tipo de Equipo -->
                        <div class="grupo-campo">
                            <label for="formularioTipo">Tipo de Registro *</label>
                            <select id="formularioTipo" required>
                                <option value="laptop">💻 Laptop</option>
                                <option value="PC">🖥️ PC (Escritorio)</option>
                                <option value="monitor">🖥️ Monitor</option>
                                <option value="caja">📦 Caja</option>
                            </select>
                        </div>

                        <!-- Tipo de Caja (PC, Laptop o Monitor) -->
                        <div class="grupo-campo" id="contenedorCampoTipoCaja" style="display: none;">
                            <label for="tipoCajaSelect">Tipo de Caja *</label>
                            <select id="tipoCajaSelect">
                                <option value="laptop">💻 Laptop</option>
                                <option value="PC">🖥️ PC</option>
                                <option value="monitor">🖥️ Monitor</option>
                            </select>
                        </div>

                        <!-- Número de Serie -->
                        <div class="grupo-campo">
                            <label for="noSerie">Número de Serie *</label>
                            <input type="text" id="noSerie" placeholder="Ej. 7N93W64 o 250315000001" required autocomplete="off">
                            <div id="avisoSerieDuplicado" class="alerta-serie-duplicado" style="display: none;"></div>
                            <div id="avisoCajaEncontrada" class="alerta-caja-encontrada" style="display: none;"></div>
                        </div>

                        <!-- Marca -->
                        <div class="grupo-campo">
                            <label for="marca">Marca *</label>
                            <select id="marca" required>
                                <option value="">-- Seleccionar Marca --</option>
                                <option value="DELL">DELL</option>
                                <option value="LENOVO">LENOVO</option>
                                <option value="LENOVO THINKCENTRE">LENOVO THINKCENTRE</option>
                                <option value="ACTEK">ACTEK</option>
                                <option value="HUAWEI">HUAWEI</option>
                                <option value="HYUNDAI">HYUNDAI</option>
                                <option value="LANIX">LANIX</option>
                                <option value="HP">HP</option>
                                <option value="ASUS">ASUS</option>
                                <option value="ACER">ACER</option>
                                <option value="APPLE">APPLE</option>
                                <option value="OTRA">OTRA</option>
                            </select>
                        </div>

                        <!-- Ubicación -->
                        <div class="grupo-campo" id="contenedorCampoUbicacion">
                            <label for="ubicacion">Ubicación *</label>
                            <select id="ubicacion" required>
                                <option value="Versalles" selected>Versalles</option>
                                <option value="5 de mayo - sala 2">5 de mayo - sala 2</option>
                                <option value="5 de mayo - sala 3">5 de mayo - sala 3</option>
                                <option value="RPP">RPP</option>
                                <option value="IREC">IREC</option>
                            </select>
                        </div>

                        <!-- Técnico -->
                        <div class="grupo-campo" id="contenedorCampoTecnico">
                            <label for="formateadoPor">Técnico (Formateado Por)</label>
                            <select id="formateadoPor">
                                <option value="">Sin asignar</option>
                                <option value="ADRIAN">ADRIAN (A)</option>
                                <option value="KERUUM">KERUUM (K)</option>
                            </select>
                        </div>

                        <!-- Estado de Formateo -->
                        <div class="grupo-campo" id="contenedorCampoFormateo">
                            <label for="formateadaSelect">Estado de Formateo</label>
                            <select id="formateadaSelect">
                                <option value="pendiente">Pendiente</option>
                                <option value="en_proceso">⏳ En Formateo</option>
                                <option value="formateada">✔ Formateada</option>
                                <option value="error">❌ Error al formatear</option>
                            </select>
                        </div>

                        <!-- Cargador (Laptop) -->
                        <div class="grupo-campo" id="contenedorCampoCargador">
                            <label for="cargadorSelect">Cargador (Laptop)</label>
                            <select id="cargadorSelect">
                                <option value="si">✔ Con cargador</option>
                                <option value="advertencia">⚠️ Con advertencia</option>
                                <option value="no">✖ Sin cargador</option>
                            </select>
                        </div>

                        <!-- Estado de Entrega -->
                        <div class="grupo-campo" id="contenedorCampoEntrega">
                            <label for="entregadaSelect">Estado de Entrega</label>
                            <select id="entregadaSelect">
                                <option value="pendiente">Pendiente</option>
                                <option value="en_proceso">⏳ En Proceso</option>
                                <option value="entregada">✔ Entregada</option>
                            </select>
                        </div>

                        <!-- Checkboxes generales y específicos -->
                        <div class="grupo-campo campo-completo" id="contenedorCheckboxesHardware">
                            <div class="grupo-checkboxes">
                                <label class="item-checkbox" id="contenedorCampoCajaCheck">
                                    <input type="checkbox" id="cajaCheck">
                                    <span>📦 Tiene Caja</span>
                                </label>

                                <label class="item-checkbox" id="contenedorCampoCajaSinSerie">
                                    <input type="checkbox" id="cajaSinSerieCheck">
                                    <span>📦 Caja sin número de serie</span>
                                </label>

                                <label class="item-checkbox" id="contenedorCampoCables">
                                    <input type="checkbox" id="cablesCheck">
                                    <span>🔌 Cables</span>
                                </label>

                                <label class="item-checkbox" id="contenedorCampoMouse">
                                    <input type="checkbox" id="mouseCheck">
                                    <span>🖱️ Mouse</span>
                                </label>

                                <label class="item-checkbox" id="contenedorCampoTeclado">
                                    <input type="checkbox" id="tecladoCheck">
                                    <span>⌨️ Teclado</span>
                                </label>

                                <label class="item-checkbox">
                                    <input type="checkbox" id="listaCheck">
                                    <span>✅ Lista</span>
                                </label>
                            </div>
                        </div>

                        <!-- Préstamo / Prestado a -->
                        <div class="grupo-campo" id="contenedorCampoPrestadoA">
                            <label for="prestadoA">Prestado a (Nombre de persona)</label>
                            <input type="text" id="prestadoA" placeholder="Ej. Juan Pérez (Dejar vacío si está disponible)" autocomplete="off">
                        </div>

                        <!-- Observaciones / Comentario -->
                        <div class="grupo-campo campo-completo">
                            <label for="observaciones" id="labelObservaciones">Observaciones (Opcional)</label>
                            <input type="text" id="observaciones" placeholder="Ej. FALTA ESPUMA SUPERIOR, SIN MOUSE, etc.">
                        </div>
                    </div>
                </div>

                <div class="modal-pie-compacto">
                    <button type="button" id="btnCancelarModal" class="btn btn-secondary btn-sm">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">💾 Guardar Equipo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Confirmación para Cambiar Estado -->
    <div id="modalConfirmacionEstado" class="modal-overlay">
        <div class="modal-confirmacion-box">
            <div class="confirmacion-header">
                <div id="confirmacionIcono" class="confirmacion-icono">⚙️</div>
                <div id="confirmacionTitulo" class="confirmacion-titulo">Cambiar Estado</div>
                <div id="confirmacionSubtitulo" class="confirmacion-subtitulo">Equipo: <strong id="confirmacionSerie"></strong></div>
            </div>

            <div id="avisoFormateoEstado" style="display: none;"></div>

            <div id="contenedorOpcionesEstado" class="lista-opciones-estado">
                <!-- Llenado dinámicamente con JavaScript -->
            </div>

            <div class="acciones-confirmacion">
                <button type="button" id="btnCancelarConfirmacion" class="btn btn-secondary btn-sm">Cancelar</button>
                <button type="button" id="btnAceptarConfirmacion" class="btn btn-primary btn-sm">Confirmar Cambio</button>
            </div>
        </div>
    </div>

    <!-- Modal de Advertencia de Formateo previo a marcar como Listo -->
    <div id="modalAdvertenciaFormateo" class="modal-overlay modal-advertencia-zindex">
        <div class="modal-confirmacion-box modal-box-advertencia">
            <div class="confirmacion-header">
                <div class="confirmacion-icono">⚠️</div>
                <div class="confirmacion-titulo">Equipo No Formateado</div>
                <div class="confirmacion-subtitulo">Validación de Estado</div>
            </div>

            <div class="alerta-advertencia-formateo">
                <div class="alerta-icono-aviso">⚠️</div>
                <div class="alerta-texto-aviso">
                    <strong>¡Atención!</strong> El equipo aún no ha sido marcado como <b>Formateada</b>.
                    <div class="alerta-pregunta-aviso">¿Cómo deseas actualizarlo? Puedes ponerle ambos estados a la vez.</div>
                </div>
            </div>

            <div class="acciones-confirmacion" style="flex-direction: column; gap: 0.5rem; margin-top: 1.25rem;">
                <button type="button" id="btnFormateadaYListaModal" class="btn btn-success" style="justify-content: center; font-weight: 600;">
                    ✔ Poner Lista y Formateada (ambos a la vez)
                </button>
                <div style="display: flex; gap: 0.5rem; width: 100%;">
                    <button type="button" id="btnAceptarFormateoModal" class="btn btn-secondary btn-sm" style="flex: 1; justify-content: center;">
                        ⚡ Solo Formateada
                    </button>
                    <button type="button" id="btnRechazarFormateoModal" class="btn btn-secondary btn-sm" style="flex: 1; justify-content: center;">
                        ✖ Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar o Editar Observación -->
    <div id="modalObservacion" class="modal-overlay">
        <div class="modal-obs-box">
            <div class="modal-encabezado" style="padding: 0.85rem 0.5rem; margin-bottom: 0.85rem;">
                <h3 id="modalObsTitulo" style="font-size: 1rem;">Observación del Equipo</h3>
                <button type="button" id="btnCerrarModalObs" class="btn-cerrar-modal">&times;</button>
            </div>

            <form id="formObservacion">
                <input type="hidden" id="obsTabla">
                <input type="hidden" id="obsId">

                <div class="grupo-campo" style="margin-bottom: 1rem;">
                    <label for="inputTextoObservacion" style="font-size: 0.825rem; font-weight: 600;">Escribe la observación:</label>
                    <textarea id="inputTextoObservacion" rows="3" placeholder="Ej. FALTA ESPUMA SUPERIOR, TECLA SUELTA, SIN MOUSE..." style="width: 100%; font-size: 0.875rem; padding: 0.6rem; border: 1px solid var(--color-borde); border-radius: 8px; outline: none;"></textarea>
                </div>

                <div class="acciones-confirmacion">
                    <button type="button" id="btnLimpiarObs" class="btn btn-secondary btn-sm" title="Borrar observación">Limpiar</button>
                    <button type="button" id="btnCancelarObs" class="btn btn-secondary btn-sm">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">💾 Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Asignar o Finalizar Préstamo de Equipo -->
    <div id="modalPrestamo" class="modal-overlay">
        <div class="modal-obs-box">
            <div class="modal-encabezado" style="padding: 0.85rem 0.5rem; margin-bottom: 0.85rem;">
                <h3 id="modalPrestamoTitulo" style="font-size: 1rem; display: flex; align-items: center; gap: 0.4rem;">
                    <span>🤝</span> Préstamo de Equipo
                </h3>
                <button type="button" id="btnCerrarModalPrestamo" class="btn-cerrar-modal" onclick="cerrarModalPrestamo()">&times;</button>
            </div>

            <form id="formPrestamo" onsubmit="guardarPrestamoDesdeModal(event)">
                <input type="hidden" id="prestamoTabla">
                <input type="hidden" id="prestamoId">

                <div class="grupo-campo" style="margin-bottom: 1rem;">
                    <label for="inputTextoPrestamo" style="font-size: 0.825rem; font-weight: 600;">¿A quién se le presta este equipo?</label>
                    <input type="text" id="inputTextoPrestamo" placeholder="Ej. Juan Pérez, Auditoría, Lic. Gómez..." style="width: 100%; font-size: 0.875rem; padding: 0.6rem; border: 1px solid var(--color-borde); border-radius: 8px; outline: none;" autocomplete="off">
                    <small style="color: #64748b; font-size: 0.75rem; margin-top: 5px; display: block;">
                        Escribe el nombre de la persona para marcarlo como <b>Prestada</b>. Si el equipo ya fue devuelto, haz clic en "Quitar Préstamo".
                    </small>
                </div>

                <div class="acciones-confirmacion">
                    <button type="button" id="btnQuitarPrestamo" class="btn btn-secondary btn-sm" onclick="quitarPrestamo()" style="display: none; color: #dc2626;" title="Finalizar préstamo y marcar como disponible">Quitar Préstamo</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="cerrarModalPrestamo()">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">💾 Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Edición Masiva de Equipos -->
    <div id="modalEdicionMasiva" class="modal-overlay">
        <div class="modal-contenedor-compacto modal-masivo-box">
            <div class="modal-encabezado">
                <div>
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;" class="h5 font-weight-bold">
                        <span>⚡</span> Edición Masiva de Equipos
                    </h3>
                    <span class="subtitulo-modal-masivo text-muted small">Modificando <strong id="spanCantidadMasiva">0</strong> equipos seleccionados</span>
                </div>
                <button type="button" class="btn-cerrar-modal" onclick="cerrarModalEdicionMasiva()">&times;</button>
            </div>

            <form id="formEdicionMasiva" onsubmit="guardarEdicionMasiva(event)">
                <div class="modal-cuerpo-compacto modal-cuerpo-scrollable">
                    <div class="aviso-edicion-masiva">
                        ℹ️ <strong>Instrucciones:</strong> Solo los campos donde selecciones un nuevo valor serán modificados en todos los equipos seleccionados. Los campos que permanezcan en <em>-- Sin cambios --</em> conservarán su valor actual intacto.
                    </div>

                    <!-- SECCIÓN: DATOS GENERALES -->
                    <div class="seccion-masiva-titulo">📋 Datos Generales</div>
                    <div class="grid-formulario-compacto">
                        <div class="campo-formulario">
                            <label for="masivoUbicacion">Ubicación</label>
                            <select id="masivoUbicacion" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="Versalles">Versalles</option>
                                <option value="5 de mayo - sala 2">5 de mayo - sala 2</option>
                                <option value="5 de mayo - sala 3">5 de mayo - sala 3</option>
                                <option value="RPP">RPP</option>
                                <option value="IREC">IREC</option>
                            </select>
                        </div>

                        <div class="campo-formulario">
                            <label for="masivoMarca">Marca</label>
                            <select id="masivoMarca" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <optgroup label="Laptops">
                                    <option value="LENOVO">LENOVO</option>
                                    <option value="DELL">DELL</option>
                                    <option value="HP">HP</option>
                                    <option value="HUAWEI">HUAWEI</option>
                                    <option value="HYUNDAI">HYUNDAI</option>
                                    <option value="LANIX">LANIX</option>
                                    <option value="ASUS">ASUS</option>
                                    <option value="ACER">ACER</option>
                                    <option value="APPLE">APPLE</option>
                                </optgroup>
                                <optgroup label="Computadoras de Escritorio (PC)">
                                    <option value="LENOVO THINKCENTRE">LENOVO THINKCENTRE</option>
                                    <option value="ACTEK">ACTEK</option>
                                </optgroup>
                                <optgroup label="Monitores">
                                    <option value="LENOVO THINKVISION">LENOVO THINKVISION</option>
                                    <option value="ACTECK">ACTECK</option>
                                    <option value="LG">LG</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="campo-formulario">
                            <label for="masivoTecnico">Técnico (Formateado por)</label>
                            <select id="masivoTecnico" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="ADRIAN">ADRIAN</option>
                                <option value="KERUUM">KERUUM</option>
                                <option value="__limpiar__">❌ Limpiar técnico (Dejar en blanco)</option>
                            </select>
                        </div>

                        <div class="campo-formulario">
                            <label for="masivoEntregada">Entrega</label>
                            <select id="masivoEntregada" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="entregada">✔ Entregada</option>
                                <option value="pendiente">⏳ Pendiente</option>
                            </select>
                        </div>
                    </div>

                    <!-- SECCIÓN: ESTADO DE FORMATEO Y LISTO -->
                    <div class="seccion-masiva-titulo" style="margin-top: 1rem;">⚙️ Estado de Formateo y Preparación</div>
                    <div class="grid-formulario-compacto">
                        <div class="campo-formulario">
                            <label for="masivoFormateada">Estado de Formateo</label>
                            <select id="masivoFormateada" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="pendiente">⏳ Pendiente (Sin iniciar)</option>
                                <option value="en_proceso">⏱️ En Formateo (Registra hora actual)</option>
                                <option value="formateada">✔ Formateada (Completada)</option>
                                <option value="error">❌ Error al formatear</option>
                            </select>
                        </div>

                        <div class="campo-formulario">
                            <label for="masivoLista">Estado de Preparación (Listo)</label>
                            <select id="masivoLista" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="formateada_y_lista">✅ Marcar Lista y Formateada (ambos a la vez)</option>
                                <option value="1">✔ Marcar como Lista</option>
                                <option value="0">✖ No Lista / Pendiente</option>
                            </select>
                        </div>
                    </div>

                    <!-- SECCIÓN: HARDWARE Y ACCESORIOS -->
                    <div class="seccion-masiva-titulo" style="margin-top: 1rem;">📦 Hardware y Accesorios</div>
                    <div class="grid-formulario-compacto">
                        <div class="campo-formulario">
                            <label for="masivoCaja">Caja</label>
                            <select id="masivoCaja" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="1">✔ Con Caja (con serie)</option>
                                <option value="sin_serie">📦 En caja sin número de serie</option>
                                <option value="0">✖ Sin Caja</option>
                            </select>
                        </div>

                        <div class="campo-formulario">
                            <label for="masivoCargador">Cargador (Laptops)</label>
                            <select id="masivoCargador" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="si">✔ Con Cargador</option>
                                <option value="advertencia">⚠️ Con Advertencia / Genérico</option>
                                <option value="no">✖ Sin Cargador</option>
                            </select>
                        </div>

                        <div class="campo-formulario">
                            <label for="masivoCables">Cables (PC / Monitor)</label>
                            <select id="masivoCables" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="1">✔ Con Cables</option>
                                <option value="0">✖ Sin Cables</option>
                            </select>
                        </div>

                        <div class="campo-formulario">
                            <label for="masivoMouse">Mouse (PC)</label>
                            <select id="masivoMouse" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="1">✔ Con Mouse</option>
                                <option value="0">✖ Sin Mouse</option>
                            </select>
                        </div>

                        <div class="campo-formulario">
                            <label for="masivoTeclado">Teclado (PC)</label>
                            <select id="masivoTeclado" class="control-formulario">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="1">✔ Con Teclado</option>
                                <option value="0">✖ Sin Teclado</option>
                            </select>
                        </div>
                    </div>

                    <!-- SECCIÓN: PRÉSTAMO -->
                    <div class="seccion-masiva-titulo" style="margin-top: 1rem;">🤝 Préstamo de Equipos</div>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 0.5rem;">
                        <div class="campo-formulario">
                            <label for="masivoPrestadoAModo">Acción para Préstamo</label>
                            <select id="masivoPrestadoAModo" class="control-formulario" onchange="alternarCampoTextoPrestamoMasivo(this.value)">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar actual) --</option>
                                <option value="asignar">🤝 Asignar préstamo a una persona</option>
                                <option value="limpiar">✅ Finalizar préstamo (Marcar como disponibles)</option>
                            </select>
                        </div>
                        <div class="campo-formulario" id="contenedorTextoPrestamoMasivo" style="display: none;">
                            <label for="masivoPrestadoANombre">Nombre de la persona</label>
                            <input type="text" id="masivoPrestadoANombre" class="control-formulario" placeholder="Ej. Juan Pérez, Auditoría..." autocomplete="off">
                        </div>
                    </div>

                    <!-- SECCIÓN: OBSERVACIONES -->
                    <div class="seccion-masiva-titulo" style="margin-top: 1rem;">📝 Observaciones / Comentarios</div>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 0.5rem;">
                        <div class="campo-formulario">
                            <label for="masivoObsModo">Acción para Observaciones</label>
                            <select id="masivoObsModo" class="control-formulario" onchange="alternarCampoTextoObsMasivo(this.value)">
                                <option value="__sin_cambio__">-- Sin cambios (Conservar observaciones actuales) --</option>
                                <option value="reemplazar">✏️ Reemplazar observaciones con nuevo texto</option>
                                <option value="agregar">➕ Agregar al final de las observaciones existentes</option>
                                <option value="limpiar">🗑️ Borrar / Vaciar observaciones</option>
                            </select>
                        </div>
                        <div class="campo-formulario" id="contenedorTextoObsMasivo" style="display: none;">
                            <label for="masivoObsTexto">Texto de Observación</label>
                            <textarea id="masivoObsTexto" class="control-formulario" rows="2" placeholder="Escribe el texto de la observación..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-pie-compacto">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalEdicionMasiva()">Cancelar</button>
                    <button type="submit" id="btnConfirmarEdicionMasiva" class="btn btn-primary" style="font-weight: 600;">
                        ⚡ Aplicar Cambios Masivos
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Contenedor para Notificaciones Flotantes (Toasts) -->
    <div id="contenedorToast" class="contenedor-toast"></div>

</div>
@endsection

@section('pos-script')
    <script>
        window.URL_API_EQUIPOS = "{{ route('admin.equipos.api') }}";
        window.URL_EXPORTAR_EXCEL = "{{ route('admin.equipos.exportar_excel') }}";
    </script>
    <script src="{{ asset('js/equipos/inventario_equipos.js') }}?v={{ time() }}"></script>
    <script>
        function seleccionarEquipoStellum(sel) {
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) return;
            document.getElementById('inputEquipoSerieModal').value = opt.dataset.serie || '';
            document.getElementById('inputEquipoTipoModal').value = opt.dataset.tipo || 'PC';
            document.getElementById('inputEquipoTablaModal').value = opt.dataset.tabla || 'computadoras';
            document.getElementById('inputEquipoIdStellumModal').value = opt.dataset.id || '';
            document.getElementById('inputEquipoMarcaModal').value = opt.dataset.marca || '';
            document.getElementById('inputEquipoUbicacionModal').value = opt.dataset.ubicacion || '';
        }
        $('#modalAsignarPcUsuario').on('show.bs.modal', function () {
            const sel = document.getElementById('selectEquipoStellumModal');
            if (!sel) return;
            const lista = (typeof estadoApp !== 'undefined' && Array.isArray(estadoApp.equipos))
                ? estadoApp.equipos.filter(e => e.tabla_origen === 'computadoras' || e.tabla_origen === 'laptops')
                : [];
            if (lista.length === 0) {
                sel.innerHTML = '<option value="">-- Sin conexión a Stellum (ingresa No. Serie manual) --</option>';
                return;
            }
            sel.innerHTML = '<option value="">-- Selecciona una PC o Laptop (' + lista.length + ') --</option>';
            lista.forEach(e => {
                const o = document.createElement('option');
                o.value = e.no_serie;
                o.dataset.serie = e.no_serie || '';
                o.dataset.tipo = e.tipo || 'PC';
                o.dataset.tabla = e.tabla_origen || 'computadoras';
                o.dataset.id = e.id || '';
                o.dataset.marca = e.marca || '';
                o.dataset.ubicacion = e.ubicacion || '';
                o.textContent = `[${(e.tipo || 'PC').toUpperCase()}] ${e.no_serie} - ${e.marca || ''} (${e.ubicacion || ''})` + (e.prestado_a ? ` [Actual: ${e.prestado_a}]` : '');
                sel.appendChild(o);
            });
        });
    </script>
@endsection