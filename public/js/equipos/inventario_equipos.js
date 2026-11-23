/**
 * Aplicación de Gestión de Computadoras y Laptops
 * Adaptado para integración en Stellum Laravel
 */

function obtenerUrlApi(params = '') {
    const base = window.URL_API_EQUIPOS || (window.location.pathname.replace(/\/$/, '') + '/api');
    if (!params) return base;
    return base + (base.includes('?') ? '&' : '?') + params;
}

function fetchConCsrf(url, opciones = {}) {
    opciones.headers = Object.assign({
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    }, opciones.headers || {});
    return fetch(url, opciones);
}

// Marcas oficiales según requerimiento:
// Para PCs son únicamente LENOVO THINKCENTRE y ACTEK. Las demás son laptops y monitores.
const MARCAS_PC = ['LENOVO THINKCENTRE', 'ACTEK'];
const MARCAS_LAPTOP = ['DELL', 'LENOVO', 'HUAWEI', 'HYUNDAI', 'LANIX', 'HP', 'ASUS', 'ACER', 'APPLE', 'OTRA'];
const MARCAS_MONITOR = ['LENOVO THINKVISION', 'ACTECK', 'LG'];

// Caracteres iniciales más comunes por marca para autorellenar número de serie
const PREFIJOS_SERIE_POR_MARCA = {
    'LANIX': '2503150000',
    'LENOVO': 'PF5',
    'HUAWEI': '2NZBB26',
    'HYUNDAI': 'IP012415CA10SG',
    'ASUS': 'S9N0CV',
    'LENOVO THINKCENTRE': 'MZ02',
    'LENOVO THINKVISION': 'VYP',
    'ACTEK': '1107253577',
    'ACTECK': '11122594452608',
    'LG': '403NT'
};

// Estado global de la aplicación
const estadoApp = {
    pestanaActiva: 'todas', // 'todas', 'laptops', 'computadoras', 'monitores', 'cajas'
    terminoBusqueda: '',
    filtroMarca: '',
    filtroUbicacion: '',
    filtroTecnico: '',
    filtroEstado: '',
    filtrosColumnas: {
        serie: '',
        marca: '',
        ubicacion: '',
        tecnico: '',
        obs: '',
        formateada: '',
        tiempoFormateo: '',
        caja: '',
        cargador: '',
        cables: '',
        mouse: '',
        teclado: '',
        lista: '',
        tipo: '',
        entregada: '',
        tiempoEntrega: '',
        prestada: ''
    },
    equipos: [],
    resumen: null,
    estadoEnEdicion: null, // Para el modal de confirmación de cambio de estado
    seleccionadosPaseLista: new Set() // Conjunto de claves para pase de lista visual (fila verde)
};

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    configurarToggleKpis();
    configurarScrollHorizontal();
    configurarEventos();
    configurarRedimensionColumnas();
    aplicarEstilosColumnasOcultas();
    cargarResumen();
    cargarEquipos();
});

// Configuración de listeners de eventos
function configurarEventos() {
    // Pestañas
    document.querySelectorAll('.pestana-boton').forEach(boton => {
        boton.addEventListener('click', () => {
            document.querySelectorAll('.pestana-boton').forEach(b => b.classList.remove('activa'));
            boton.classList.add('activa');
            estadoApp.pestanaActiva = boton.dataset.pestana;
            cargarEquipos();
        });
    });

    // Búsqueda general superior en tiempo real con debounce
    const inputBuscar = document.getElementById('inputBuscar');
    if (inputBuscar) {
        let temporizador;
        inputBuscar.addEventListener('input', (e) => {
            clearTimeout(temporizador);
            temporizador = setTimeout(() => {
                estadoApp.terminoBusqueda = e.target.value.trim();
                renderizarTabla();
            }, 200);
        });
    }

    // Filtros de selección superiores
    const filtroMarca = document.getElementById('filtroMarca');
    if (filtroMarca) {
        filtroMarca.addEventListener('change', (e) => {
            estadoApp.filtroMarca = e.target.value;
            // Sincronizar con el filtro de columna de marca
            const colMarca = document.getElementById('filtroColMarca');
            if (colMarca) colMarca.value = e.target.value;
            renderizarTabla();
        });
    }

    const filtroUbicacion = document.getElementById('filtroUbicacion');
    if (filtroUbicacion) {
        filtroUbicacion.addEventListener('change', (e) => {
            estadoApp.filtroUbicacion = e.target.value;
            const colUbi = document.getElementById('filtroColUbicacion');
            if (colUbi) colUbi.value = e.target.value;
            renderizarTabla();
        });
    }

    const filtroTecnico = document.getElementById('filtroTecnico');
    if (filtroTecnico) {
        filtroTecnico.addEventListener('change', (e) => {
            estadoApp.filtroTecnico = e.target.value;
            const colTec = document.getElementById('filtroColTecnico');
            if (colTec) colTec.value = e.target.value;
            renderizarTabla();
        });
    }

    const filtroEstado = document.getElementById('filtroEstado');
    if (filtroEstado) {
        filtroEstado.addEventListener('change', (e) => {
            estadoApp.filtroEstado = e.target.value;
            renderizarTabla();
        });
    }

    // =========================================================================
    // FILTROS PARA TODAS LAS COLUMNAS DE LA TABLA
    // =========================================================================
    const mapeoInputsTexto = [
        { id: 'filtroColSerie', campo: 'serie' },
        { id: 'filtroColTiempoFormateo', campo: 'tiempoFormateo' },
        { id: 'filtroColTiempoEntrega', campo: 'tiempoEntrega' },
        { id: 'filtroColPrestada', campo: 'prestada' }
    ];

    mapeoInputsTexto.forEach(item => {
        const elemento = document.getElementById(item.id);
        if (elemento) {
            let temporizadorCol;
            elemento.addEventListener('input', (e) => {
                clearTimeout(temporizadorCol);
                temporizadorCol = setTimeout(() => {
                    const val = e.target.value.trim();
                    estadoApp.filtrosColumnas[item.campo] = val;
                    if (val !== '') {
                        elemento.classList.add('activo');
                    } else {
                        elemento.classList.remove('activo');
                    }
                    renderizarTabla();
                }, 150);
            });
        }
    });

    const mapeoSelects = [
        { id: 'filtroColMarca', campo: 'marca' },
        { id: 'filtroColUbicacion', campo: 'ubicacion' },
        { id: 'filtroColTecnico', campo: 'tecnico' },
        { id: 'filtroColObs', campo: 'obs' },
        { id: 'filtroColFormateada', campo: 'formateada' },
        { id: 'filtroColCaja', campo: 'caja' },
        { id: 'filtroColCargador', campo: 'cargador' },
        { id: 'filtroColCables', campo: 'cables' },
        { id: 'filtroColMouse', campo: 'mouse' },
        { id: 'filtroColTeclado', campo: 'teclado' },
        { id: 'filtroColLista', campo: 'lista' },
        { id: 'filtroColTipo', campo: 'tipo' },
        { id: 'filtroColEntregada', campo: 'entregada' }
    ];

    mapeoSelects.forEach(item => {
        const elemento = document.getElementById(item.id);
        if (elemento) {
            elemento.addEventListener('change', (e) => {
                const val = e.target.value;
                estadoApp.filtrosColumnas[item.campo] = val;
                if (val !== '') {
                    elemento.classList.add('activo');
                } else {
                    elemento.classList.remove('activo');
                }
                renderizarTabla();
            });
        }
    });

    // Botón para limpiar todos los filtros de columnas
    const btnLimpiarFiltrosCol = document.getElementById('btnLimpiarFiltrosCol');
    if (btnLimpiarFiltrosCol) {
        btnLimpiarFiltrosCol.addEventListener('click', limpiarTodosLosFiltros);
    }

    // Modal de Equipo (Registrar / Editar)
    const btnNuevoEquipo = document.getElementById('btnNuevoEquipo');
    if (btnNuevoEquipo) {
        btnNuevoEquipo.addEventListener('click', () => abrirModalEquipo());
    }

    const btnCerrarModal = document.getElementById('btnCerrarModal');
    if (btnCerrarModal) {
        btnCerrarModal.addEventListener('click', cerrarModalEquipo);
    }

    const btnCancelarModal = document.getElementById('btnCancelarModal');
    if (btnCancelarModal) {
        btnCancelarModal.addEventListener('click', cerrarModalEquipo);
    }

    // Cambio de tipo en formulario para adaptar campos específicos
    const selectTipo = document.getElementById('formularioTipo');
    if (selectTipo) {
        selectTipo.addEventListener('change', () => {
            alternarCamposPorTipo();
            verificarSerieDuplicadoEnTiempoReal();
        });
    }

    // Validación de número de serie en tiempo real
    const inputNoSerie = document.getElementById('noSerie');
    if (inputNoSerie) {
        let temporizadorVerif;
        inputNoSerie.addEventListener('input', () => {
            clearTimeout(temporizadorVerif);
            temporizadorVerif = setTimeout(() => {
                verificarSerieDuplicadoEnTiempoReal();
            }, 250);
        });
        inputNoSerie.addEventListener('blur', () => {
            verificarSerieDuplicadoEnTiempoReal();
        });
    }

    // Envío del formulario de equipo
    const formEquipo = document.getElementById('formEquipo');
    if (formEquipo) {
        formEquipo.addEventListener('submit', guardarEquipo);
    }

    // Listener para cambiar marcas dinámicamente si cambia el tipo de caja
    const selTipoCajaModal = document.getElementById('tipoCajaSelect');
    if (selTipoCajaModal) {
        selTipoCajaModal.addEventListener('change', () => {
            actualizarOpcionesMarcaModal();
            verificarSerieDuplicadoEnTiempoReal();
        });
    }

    // Listener para cambiar marcas dinámicamente si cambia formularioTipo
    const selFormularioTipo = document.getElementById('formularioTipo');
    if (selFormularioTipo) {
        selFormularioTipo.addEventListener('change', () => {
            alternarCamposPorTipo();
            actualizarOpcionesMarcaModal();
            const selectMarca = document.getElementById('marca');
            if (selectMarca) {
                aplicarPrefijoSeriePorMarca(selectMarca.value);
            }
            verificarSerieDuplicadoEnTiempoReal();
        });
    }

    // Listener para autorellenar número de serie al cambiar de marca en el modal
    const selMarcaModal = document.getElementById('marca');
    if (selMarcaModal) {
        selMarcaModal.addEventListener('change', () => {
            const idEquipo = document.getElementById('equipoId') ? document.getElementById('equipoId').value : '';
            // Si es nuevo registro se aplica siempre; si es edición solo si está vacío o tiene un prefijo conocido
            if (!idEquipo) {
                aplicarPrefijoSeriePorMarca(selMarcaModal.value);
            } else {
                aplicarPrefijoSeriePorMarca(selMarcaModal.value, false);
            }
            verificarSerieDuplicadoEnTiempoReal();
        });
    }

    // Sincronización entre checkbox de Caja y Caja sin número de serie
    const chkCajaModal = document.getElementById('cajaCheck');
    const chkCajaSinSerieModal = document.getElementById('cajaSinSerieCheck');
    if (chkCajaModal && chkCajaSinSerieModal) {
        chkCajaSinSerieModal.addEventListener('change', () => {
            if (chkCajaSinSerieModal.checked) {
                chkCajaModal.checked = true;
            }
        });
        chkCajaModal.addEventListener('change', () => {
            if (!chkCajaModal.checked) {
                chkCajaSinSerieModal.checked = false;
            }
        });
    }

    // Validación al marcar lista en formulario de equipo
    const checkLista = document.getElementById('listaCheck');
    const selectFormateada = document.getElementById('formateadaSelect');

    if (checkLista && selectFormateada) {
        checkLista.addEventListener('change', () => {
            const tipoForm = document.getElementById('formularioTipo') ? document.getElementById('formularioTipo').value : '';
            if (tipoForm === 'monitor') return; // Los monitores no se formatean
            if (checkLista.checked && selectFormateada.value !== 'formateada') {
                checkLista.checked = false; // Desmarcar hasta confirmar
                abrirModalAdvertenciaFormateo();
            }
        });

        selectFormateada.addEventListener('change', () => {
            const tipoForm = document.getElementById('formularioTipo') ? document.getElementById('formularioTipo').value : '';
            if (tipoForm === 'monitor') return;
            if (checkLista.checked && selectFormateada.value !== 'formateada') {
                checkLista.checked = false;
                mostrarToast('Se desmarcó "Lista" porque el equipo no está en estado formateada', 'advertencia');
            }
        });
    }

    // Botones del Modal de Advertencia de Formateo
    const btnFormateadaYListaModal = document.getElementById('btnFormateadaYListaModal');
    if (btnFormateadaYListaModal) {
        btnFormateadaYListaModal.addEventListener('click', () => {
            if (selectFormateada) selectFormateada.value = 'formateada';
            if (checkLista) checkLista.checked = true; // Ambos checks activos a la vez
            cerrarModalAdvertenciaFormateo();
            mostrarToast('Equipo marcado como Formateada y Listo a la vez', 'exito');
        });
    }

    const btnAceptarFormateoModal = document.getElementById('btnAceptarFormateoModal');
    if (btnAceptarFormateoModal) {
        btnAceptarFormateoModal.addEventListener('click', () => {
            if (selectFormateada) selectFormateada.value = 'formateada';
            if (checkLista) checkLista.checked = false; // Solo formateada
            cerrarModalAdvertenciaFormateo();
            mostrarToast('Equipo marcado como Formateada (Lista permanece sin marcar)', 'info');
        });
    }

    const btnRechazarFormateoModal = document.getElementById('btnRechazarFormateoModal');
    if (btnRechazarFormateoModal) {
        btnRechazarFormateoModal.addEventListener('click', () => {
            if (checkLista && selectFormateada.value !== 'formateada') checkLista.checked = false;
            cerrarModalAdvertenciaFormateo();
        });
    }

    const modalAdvFormateo = document.getElementById('modalAdvertenciaFormateo');
    if (modalAdvFormateo) {
        modalAdvFormateo.addEventListener('click', (e) => {
            if (e.target === modalAdvFormateo) {
                if (checkLista && selectFormateada.value !== 'formateada') checkLista.checked = false;
                cerrarModalAdvertenciaFormateo();
            }
        });
    }

    // Modal de Confirmación de Cambio de Estado
    const btnCancelarConfirmacion = document.getElementById('btnCancelarConfirmacion');
    if (btnCancelarConfirmacion) {
        btnCancelarConfirmacion.addEventListener('click', cerrarModalConfirmacion);
    }

    const btnAceptarConfirmacion = document.getElementById('btnAceptarConfirmacion');
    if (btnAceptarConfirmacion) {
        btnAceptarConfirmacion.addEventListener('click', aplicarCambioEstadoConfirmado);
    }

    // Modal de Observaciones
    const btnCerrarModalObs = document.getElementById('btnCerrarModalObs');
    if (btnCerrarModalObs) {
        btnCerrarModalObs.addEventListener('click', cerrarModalObservacion);
    }

    const btnCancelarObs = document.getElementById('btnCancelarObs');
    if (btnCancelarObs) {
        btnCancelarObs.addEventListener('click', cerrarModalObservacion);
    }

    const btnLimpiarObs = document.getElementById('btnLimpiarObs');
    if (btnLimpiarObs) {
        btnLimpiarObs.addEventListener('click', () => {
            document.getElementById('inputTextoObservacion').value = '';
        });
    }

    const formObservacion = document.getElementById('formObservacion');
    if (formObservacion) {
        formObservacion.addEventListener('submit', guardarObservacion);
    }

    // Cerrar menú selector de columnas al hacer clic fuera
    document.addEventListener('click', (e) => {
        const menu = document.getElementById('menuSelectorColumnas');
        const btn = document.getElementById('btnMenuColumnas');
        if (menu && menu.style.display !== 'none') {
            if (!menu.contains(e.target) && !btn?.contains(e.target)) {
                menu.style.display = 'none';
            }
        }
    });
}

// Limpiar todos los filtros (superiores y de columnas)
function limpiarTodosLosFiltros() {
    // Resetear estado
    Object.keys(estadoApp.filtrosColumnas).forEach(k => {
        estadoApp.filtrosColumnas[k] = '';
    });
    estadoApp.terminoBusqueda = '';
    estadoApp.filtroMarca = '';
    estadoApp.filtroUbicacion = '';
    estadoApp.filtroTecnico = '';
    estadoApp.filtroEstado = '';

    // Limpiar inputs visuales
    document.querySelectorAll('.control-filtro-col').forEach(elem => {
        elem.value = '';
        elem.classList.remove('activo');
    });

    const inputBuscar = document.getElementById('inputBuscar');
    if (inputBuscar) inputBuscar.value = '';

    const filtroMarca = document.getElementById('filtroMarca');
    if (filtroMarca) filtroMarca.value = '';

    const filtroUbicacion = document.getElementById('filtroUbicacion');
    if (filtroUbicacion) filtroUbicacion.value = '';

    const filtroTecnico = document.getElementById('filtroTecnico');
    if (filtroTecnico) filtroTecnico.value = '';

    const filtroEstado = document.getElementById('filtroEstado');
    if (filtroEstado) filtroEstado.value = '';

    renderizarTabla();
    mostrarToast('Filtros restablecidos', 'exito');
}

// Formatear fecha para visualización en tabla (DD/MM/AAAA HH:MM:SS)
function formatearFechaLegible(cadenaFecha) {
    if (!cadenaFecha) return '-';
    const partes = cadenaFecha.split(' ');
    if (partes.length === 2) {
        const fechaPartes = partes[0].split('-');
        if (fechaPartes.length === 3) {
            return `${fechaPartes[2]}/${fechaPartes[1]}/${fechaPartes[0]} ${partes[1]}`;
        }
    }
    return cadenaFecha;
}

// Cargar métricas y resumen
async function cargarResumen(reintento = 0) {
    try {
        const respuesta = await fetchConCsrf(obtenerUrlApi('accion=resumen'));
        const resultado = await respuesta.json();
        if (resultado.exito) {
            estadoApp.resumen = resultado;
            document.getElementById('statTotalEquipos').textContent = resultado.totales.equipos;
            document.getElementById('statEnFormateo').textContent = resultado.totales.en_formateo;
            document.getElementById('statFormateadas').textContent = resultado.totales.formateadas;
            document.getElementById('statListas').textContent = resultado.totales.listas;
            document.getElementById('statEntregadas').textContent = resultado.totales.entregadas;

            // Actualizar contadores en pestañas
            document.getElementById('contadorTodas').textContent = resultado.totales.equipos;
            document.getElementById('contadorLaptops').textContent = resultado.laptops.total;
            document.getElementById('contadorPCs').textContent = resultado.computadoras.total;
            const contMonitores = document.getElementById('contadorMonitores');
            if (contMonitores && resultado.monitores) {
                contMonitores.textContent = resultado.monitores.total;
            }
            const contCajas = document.getElementById('contadorCajas');
            if (contCajas && resultado.cajas) {
                contCajas.textContent = resultado.cajas.total;
            }
        }
    } catch (error) {
        if (reintento < 2) {
            setTimeout(() => cargarResumen(reintento + 1), 1000);
        }
    }
}

// Cargar listado de equipos desde el backend
async function cargarEquipos(reintento = 0) {
    const cuerpoTabla = document.getElementById('cuerpoTablaEquipos');
    cuerpoTabla.innerHTML = `<tr><td colspan="16" style="text-align: center; padding: 2rem; color: #64748b;">Iniciando y cargando equipos...</td></tr>`;
    actualizarContadorFiltrado(0, 0, true);

    try {
        const parametros = new URLSearchParams({
            accion: 'listar',
            tabla: estadoApp.pestanaActiva
        });

        const respuesta = await fetchConCsrf(obtenerUrlApi(parametros.toString()));
        const resultado = await respuesta.json();

        if (resultado.exito) {
            estadoApp.equipos = resultado.datos;
            actualizarSelectoresFiltro(resultado.filtros);
            renderizarTabla();
        } else {
            cuerpoTabla.innerHTML = `
                <tr>
                    <td colspan="16" style="text-align: center; padding: 2rem; color: #ef4444;">
                        <strong>Error al consultar la base de datos:</strong> ${resultado.mensaje || 'Error desconocido'}
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        if (reintento < 2) {
            setTimeout(() => cargarEquipos(reintento + 1), 1000);
        } else {
            console.error('Error al cargar equipos:', error);
            cuerpoTabla.innerHTML = `
                <tr>
                    <td colspan="16" style="text-align: center; padding: 2.5rem; color: #64748b;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">⚠️</div>
                        <strong style="color: #1e293b; font-size: 1rem;">Conectando con el servidor...</strong><br>
                        <span style="font-size: 0.85rem; color: #94a3b8;">El servidor se está inicializando en segundo plano.</span><br><br>
                        <button class="btn btn-primario btn-sm" onclick="cargarEquipos(0); cargarResumen(0);">🔄 Reintentar conexión</button>
                    </td>
                </tr>
            `;
        }
    }
}

// Actualizar opciones de filtros si no tienen valor seleccionado
function actualizarSelectoresFiltro(filtros) {
    if (!filtros) return;

    // Determinar lista de marcas a mostrar según la pestaña activa:
    // En 'computadoras' solo marcas de PC (LENOVO THINKCENTRE, ACTEK).
    // En 'laptops' solo marcas de laptop (las demás).
    // En 'todas' o 'cajas', todas las marcas.
    let marcasParaFiltro = filtros.marcas || [];
    if (estadoApp.pestanaActiva === 'computadoras') {
        marcasParaFiltro = MARCAS_PC;
    } else if (estadoApp.pestanaActiva === 'laptops') {
        marcasParaFiltro = marcasParaFiltro.filter(m => !MARCAS_PC.includes(m.toUpperCase()));
    }

    // Selector superior de marca
    const selectMarca = document.getElementById('filtroMarca');
    if (selectMarca) {
        const valorPrevio = estadoApp.filtroMarca || selectMarca.value;
        selectMarca.innerHTML = '<option value="">Todas las Marcas</option>';
        marcasParaFiltro.forEach(marca => {
            const opcion = document.createElement('option');
            opcion.value = marca;
            opcion.textContent = marca;
            if (valorPrevio === marca) opcion.selected = true;
            selectMarca.appendChild(opcion);
        });
        if (valorPrevio && !marcasParaFiltro.includes(valorPrevio)) {
            estadoApp.filtroMarca = '';
            selectMarca.value = '';
        }
    }

    // Selector de columna de marca
    const colMarca = document.getElementById('filtroColMarca');
    if (colMarca) {
        const valorPrevioCol = estadoApp.filtrosColumnas.marca || colMarca.value;
        colMarca.innerHTML = '<option value="">Todas</option>';
        marcasParaFiltro.forEach(marca => {
            const opcion = document.createElement('option');
            opcion.value = marca;
            opcion.textContent = marca;
            if (valorPrevioCol === marca) opcion.selected = true;
            colMarca.appendChild(opcion);
        });
        if (valorPrevioCol && !marcasParaFiltro.includes(valorPrevioCol)) {
            estadoApp.filtrosColumnas.marca = '';
            colMarca.value = '';
        }
    }

    // Selector superior de técnico
    const selectTecnico = document.getElementById('filtroTecnico');
    if (selectTecnico && selectTecnico.options.length <= 1 && filtros.tecnicos) {
        filtros.tecnicos.forEach(tecnico => {
            const opcion = document.createElement('option');
            opcion.value = tecnico;
            opcion.textContent = tecnico;
            selectTecnico.appendChild(opcion);
        });
    }
}

// Determinar modo de formulario (PC, monitor o laptop)
function obtenerModoFormulario() {
    const tipo = document.getElementById('formularioTipo') ? document.getElementById('formularioTipo').value : '';
    if (tipo === 'caja') {
        const selTipoCaja = document.getElementById('tipoCajaSelect');
        return selTipoCaja ? selTipoCaja.value : 'laptop';
    }
    return tipo;
}

// Determinar si el formulario o modal se encuentra en modo PC
function esModoPCFormulario() {
    return obtenerModoFormulario() === 'PC';
}

// Actualizar opciones de marca en el modal según sea PC, Monitor o Laptop
function actualizarOpcionesMarcaModal(valorSeleccionado = '') {
    const selectMarca = document.getElementById('marca');
    if (!selectMarca) return;

    const modo = obtenerModoFormulario();
    let marcasDisponibles = MARCAS_LAPTOP;
    let placeholder = '-- Seleccionar Marca de Laptop --';

    if (modo === 'PC') {
        marcasDisponibles = MARCAS_PC;
        placeholder = '-- Seleccionar Marca de PC --';
    } else if (modo === 'monitor') {
        marcasDisponibles = MARCAS_MONITOR;
        placeholder = '-- Seleccionar Marca de Monitor --';
    }

    const seleccionActual = valorSeleccionado || selectMarca.value;

    selectMarca.innerHTML = `<option value="">${placeholder}</option>`;
    marcasDisponibles.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m;
        opt.textContent = m;
        if (seleccionActual && seleccionActual.toUpperCase() === m.toUpperCase()) {
            opt.selected = true;
        }
        selectMarca.appendChild(opt);
    });

    // Si tiene un valor preexistente no estándar, conservarlo
    if (seleccionActual && !marcasDisponibles.some(m => m.toUpperCase() === seleccionActual.toUpperCase())) {
        const optExtra = document.createElement('option');
        optExtra.value = seleccionActual;
        optExtra.textContent = seleccionActual;
        optExtra.selected = true;
        selectMarca.appendChild(optExtra);
    }
}

// Autorellenar número de serie con los primeros caracteres más comunes de cada marca
function aplicarPrefijoSeriePorMarca(marca, forzar = false) {
    const inputSerie = document.getElementById('noSerie');
    if (!inputSerie) return;

    const marcaNormalizada = (marca || '').toUpperCase().trim();
    const nuevoPrefijo = PREFIJOS_SERIE_POR_MARCA[marcaNormalizada] || '';
    if (!nuevoPrefijo) return;

    const valorActual = inputSerie.value.trim();
    const prefijosLista = Object.values(PREFIJOS_SERIE_POR_MARCA);
    const esPrefijoConocido = prefijosLista.includes(valorActual);

    // Solo autorellenar si está vacío, o se fuerza, o el valor actual coincide con algún prefijo previo
    if (forzar || valorActual === '' || esPrefijoConocido) {
        inputSerie.value = nuevoPrefijo;
        setTimeout(() => {
            inputSerie.focus();
            inputSerie.setSelectionRange(nuevoPrefijo.length, nuevoPrefijo.length);
        }, 50);
    }
}

// Obtener clase de color para badge de marca según la planilla
function obtenerClaseMarca(marca, tipo) {
    const m = (marca || '').toUpperCase();
    if (m.includes('ACTEK')) return 'marca-actek';
    if (m.includes('DELL')) return 'marca-dell';
    if (m.includes('HUAWEI')) return 'marca-huawei';
    if (m.includes('HYUNDAI')) return 'marca-hyundai';
    if (m.includes('LANIX')) return 'marca-lanix';
    if (m.includes('LENOVO')) {
        return (tipo === 'PC' || m.includes('THINKCENTR')) ? 'marca-lenovo-pc' : 'marca-lenovo';
    }
    return 'marca-generica';
}

// Obtener clase de color para badge de ubicación
function obtenerClaseUbicacion(ubicacion) {
    const u = (ubicacion || '').toLowerCase();
    if (u.includes('versalles')) return 'ubicacion-versalles';
    if (u.includes('5 de mayo') || u.includes('mayo')) return 'ubicacion-5demayo';
    if (u.includes('rpp')) return 'ubicacion-rpp';
    if (u.includes('irec')) return 'ubicacion-irec';
    return 'ubicacion-generica';
}

// Filtrar equipos en memoria según todas las columnas y filtros activos
function obtenerEquiposFiltrados() {
    const f = estadoApp.filtrosColumnas;
    const buscarGeneral = estadoApp.terminoBusqueda.toLowerCase();

    return estadoApp.equipos.filter(equipo => {
        // 1. Búsqueda general
        if (buscarGeneral !== '') {
            const serie = (equipo.no_serie || '').toLowerCase();
            const marca = (equipo.marca || '').toLowerCase();
            const ubi = (equipo.ubicacion || '').toLowerCase();
            const obs = (equipo.observaciones || '').toLowerCase();
            const tec = (equipo.formateado_por || '').toLowerCase();
            const prestado = (equipo.prestado_a || '').toLowerCase();
            if (!serie.includes(buscarGeneral) && !marca.includes(buscarGeneral) && !ubi.includes(buscarGeneral) && !obs.includes(buscarGeneral) && !tec.includes(buscarGeneral) && !prestado.includes(buscarGeneral)) {
                return false;
            }
        }

        // 2. Filtros superiores
        if (estadoApp.filtroMarca && equipo.marca !== estadoApp.filtroMarca) {
            return false;
        }
        if (estadoApp.filtroUbicacion && equipo.ubicacion !== estadoApp.filtroUbicacion) {
            return false;
        }
        if (estadoApp.filtroTecnico && equipo.formateado_por !== estadoApp.filtroTecnico) {
            return false;
        }
        if (estadoApp.filtroEstado) {
            if (estadoApp.filtroEstado === 'lista' && equipo.lista != 1) return false;
            if (estadoApp.filtroEstado === 'entregada' && equipo.entregada !== 'entregada') return false;
            if (estadoApp.filtroEstado !== 'lista' && estadoApp.filtroEstado !== 'entregada' && equipo.formateada !== estadoApp.filtroEstado) return false;
        }

        // 3. Filtro Columna: No. Serie
        if (f.serie && !(equipo.no_serie || '').toLowerCase().includes(f.serie.toLowerCase())) {
            return false;
        }

        // 4. Filtro Columna: Marca
        if (f.marca && equipo.marca !== f.marca) {
            return false;
        }

        // Filtro Columna: Ubicación
        if (f.ubicacion && equipo.ubicacion !== f.ubicacion) {
            return false;
        }

        // 5. Filtro Columna: Técnico
        if (f.tecnico) {
            if (f.tecnico === 'sin') {
                if (equipo.formateado_por && equipo.formateado_por.trim() !== '') return false;
            } else {
                if (equipo.formateado_por !== f.tecnico) return false;
            }
        }

        // 6. Filtro Columna: Observaciones (con o sin)
        if (f.obs) {
            const tieneObs = equipo.observaciones && equipo.observaciones.trim() !== '';
            if (f.obs === 'con' && !tieneObs) return false;
            if (f.obs === 'sin' && tieneObs) return false;
        }

        // 7. Filtro Columna: Formateada
        if (f.formateada && equipo.formateada !== f.formateada) {
            return false;
        }

        // 8. Filtro Columna: Timestamp Formateo / Lista / Registro
        if (f.tiempoFormateo) {
            const busq = f.tiempoFormateo.toLowerCase();
            const f1 = (equipo.hora_inicio_formateo || '').toLowerCase();
            const f2 = (equipo.hora_lista || '').toLowerCase();
            const fc = (equipo.creado_en || '').toLowerCase();
            const leg1 = formatearFechaLegible(equipo.hora_inicio_formateo).toLowerCase();
            const leg2 = formatearFechaLegible(equipo.hora_lista).toLowerCase();
            const legC = formatearFechaLegible(equipo.creado_en).toLowerCase();
            if (!f1.includes(busq) && !f2.includes(busq) && !fc.includes(busq) && !leg1.includes(busq) && !leg2.includes(busq) && !legC.includes(busq)) {
                return false;
            }
        }

        // 9. Filtro Columna: Caja (o Tiene Equipo si es pestaña de Cajas)
        if (f.caja) {
            if (f.caja === 'sin_serie') {
                if (equipo.caja_sin_serie != 1) return false;
            } else if (f.caja === '1') {
                const tieneCaja = (equipo.caja == 1 || equipo.tiene_equipo_vinculado == 1 || equipo.tiene_caja_vinculada == 1);
                if (!tieneCaja) return false;
            } else if (f.caja === '0') {
                const tieneCaja = (equipo.caja == 1 || equipo.tiene_equipo_vinculado == 1 || equipo.tiene_caja_vinculada == 1 || equipo.caja_sin_serie == 1);
                if (tieneCaja) return false;
            }
        }

        // 10. Filtro Columna: Cargador (Laptops)
        if (f.cargador && equipo.cargador !== f.cargador) {
            return false;
        }

        // 11. Filtro Columna: Cables (PC / Monitor)
        if (f.cables && String(equipo.cables) !== f.cables) {
            return false;
        }

        // Filtro Columna: Mouse
        if (f.mouse && String(equipo.mouse) !== f.mouse) {
            return false;
        }

        // Filtro Columna: Teclado
        if (f.teclado && String(equipo.teclado || 0) !== f.teclado) {
            return false;
        }

        // 13. Filtro Columna: Lista
        if (f.lista && String(equipo.lista) !== f.lista) {
            return false;
        }

        // 14. Filtro Columna: Tipo
        if (f.tipo) {
            if (equipo.tabla_origen === 'cajas') {
                if (f.tipo.toLowerCase() === 'caja') {
                    // Es caja, pasa el filtro
                } else {
                    const tipoCaja = (equipo.tipo_caja || 'laptop').toLowerCase();
                    if (tipoCaja !== f.tipo.toLowerCase()) return false;
                }
            } else {
                const t = (equipo.tipo || '').toLowerCase();
                if (t !== f.tipo.toLowerCase()) return false;
            }
        }

        // 15. Filtro Columna: Entregada
        if (f.entregada && equipo.entregada !== f.entregada) {
            return false;
        }

        // 16. Filtro Columna: Timestamp Entrega
        if (f.tiempoEntrega) {
            const busq = f.tiempoEntrega.toLowerCase();
            const fEnt = (equipo.hora_entrega || '').toLowerCase();
            const legEnt = formatearFechaLegible(equipo.hora_entrega).toLowerCase();
            if (!fEnt.includes(busq) && !legEnt.includes(busq)) {
                return false;
            }
        }

        // 17. Filtro Columna: Préstamo
        if (f.prestada) {
            const busqPrest = f.prestada.toLowerCase().trim();
            const prestadoA = (equipo.prestado_a || '').toLowerCase();
            if (busqPrest === 'prestada' || busqPrest === 'prestado' || busqPrest === 'si') {
                if (prestadoA === '') return false;
            } else if (busqPrest === 'no' || busqPrest === 'disponible' || busqPrest === 'libre') {
                if (prestadoA !== '') return false;
            } else {
                if (!prestadoA.includes(busqPrest)) return false;
            }
        }

        return true;
    });
}

// Actualizar contador visual de elementos filtrados en la tabla
function actualizarContadorFiltrado(cantidadFiltrados, totalRegistros, estaCargando = false) {
    const elemContador = document.getElementById('contadorFiltrados');
    const elemBadgeFiltro = document.getElementById('badgeFiltroActivo');
    const elemBtnLimpiarRapido = document.getElementById('btnLimpiarFiltrosRapido');
    const elemContadorPie = document.getElementById('contadorPieFiltrados');

    if (estaCargando) {
        if (elemContador) elemContador.innerHTML = `<span>Cargando equipos...</span>`;
        if (elemContadorPie) elemContadorPie.innerHTML = `<span>Consultando base de datos...</span>`;
        if (elemBadgeFiltro) elemBadgeFiltro.style.display = 'none';
        if (elemBtnLimpiarRapido) elemBtnLimpiarRapido.style.display = 'none';
        return;
    }

    // Verificar si hay algún filtro de columna o general activo
    const hayFiltrosCol = Object.values(estadoApp.filtrosColumnas).some(v => (v || '').trim() !== '');
    const hayFiltrosGenerales = (estadoApp.terminoBusqueda.trim() !== '') ||
        (estadoApp.filtroMarca !== '') ||
        (estadoApp.filtroUbicacion !== '') ||
        (estadoApp.filtroTecnico !== '') ||
        (estadoApp.filtroEstado !== '');
    const hayFiltrosActivos = hayFiltrosCol || hayFiltrosGenerales;

    // Etiqueta del tipo de equipo según la pestaña activa
    let tipoTexto = 'equipos';
    if (estadoApp.pestanaActiva === 'laptops') tipoTexto = 'laptops';
    if (estadoApp.pestanaActiva === 'computadoras') tipoTexto = 'computadoras (PC)';
    if (estadoApp.pestanaActiva === 'cajas') tipoTexto = 'cajas';

    if (hayFiltrosActivos) {
        // Modo filtrado: indicar cuántos elementos coinciden del total
        if (elemContador) {
            elemContador.innerHTML = `Mostrando <strong class="resalte-conteo">${cantidadFiltrados}</strong> de <strong>${totalRegistros}</strong> ${tipoTexto}`;
        }
        if (elemContadorPie) {
            elemContadorPie.innerHTML = `Filtro aplicado: <strong>${cantidadFiltrados}</strong> de <strong>${totalRegistros}</strong> ${tipoTexto} encontrados`;
        }
        if (elemBadgeFiltro) {
            elemBadgeFiltro.style.display = 'inline-flex';
            elemBadgeFiltro.innerHTML = `Filtros activos (${cantidadFiltrados} coincidentes)`;
        }
        if (elemBtnLimpiarRapido) {
            elemBtnLimpiarRapido.style.display = 'inline-flex';
        }
    } else {
        // Sin filtros: mostrar el total de registros en la vista
        if (elemContador) {
            elemContador.innerHTML = `Total: <strong class="resalte-conteo">${totalRegistros}</strong> ${tipoTexto}`;
        }
        if (elemContadorPie) {
            elemContadorPie.innerHTML = `Mostrando todos los <strong>${totalRegistros}</strong> registros de la tabla`;
        }
        if (elemBadgeFiltro) {
            elemBadgeFiltro.style.display = 'none';
        }
        if (elemBtnLimpiarRapido) {
            elemBtnLimpiarRapido.style.display = 'none';
        }
    }
}

// Ajustar el orden físico de las columnas en thead y fila-filtros-tabla según la pestaña activa
function ajustarOrdenColumnasDOM(pestana) {
    const filaEncabezados = document.getElementById('filaEncabezados');
    const filaFiltros = document.getElementById('filaFiltrosTabla');
    if (!filaEncabezados || !filaFiltros) return;

    if (pestana === 'cajas') {
        // Orden requerido para Cajas:
        // 1. Pase Lista, 2. No. Serie, 3. Marca, 4. Ubicación, 5. Tipo de Caja, 6. Tiene Caja, 7. Comentario, 8. Timestamp (Registro), 9. Acciones
        const ordenCajasEncabezados = [
            'colEncabezadoPaseLista',
            'colEncabezadoSerie',
            'colEncabezadoMarca',
            'colEncabezadoUbicacion',
            'colEncabezadoTipo',
            'colEncabezadoCaja',
            'colEncabezadoObs',
            'colEncabezadoTiempoFormateo',
            'colEncabezadoAcciones'
        ];
        const ordenCajasFiltros = [
            'colFiltroPaseLista',
            'colFiltroSerie',
            'colFiltroMarca',
            'colFiltroUbicacion',
            'colFiltroTipo',
            'colFiltroCaja',
            'colFiltroObs',
            'colFiltroTiempoFormateo',
            'colFiltroAcciones'
        ];
        ordenCajasEncabezados.forEach(id => {
            const el = document.getElementById(id);
            if (el) filaEncabezados.appendChild(el);
        });
        ordenCajasFiltros.forEach(id => {
            const el = document.getElementById(id);
            if (el) filaFiltros.appendChild(el);
        });
    } else {
        // Orden estándar para las demás pestañas
        const ordenEstandarEncabezados = [
            'colEncabezadoPaseLista',
            'colEncabezadoSerie',
            'colEncabezadoMarca',
            'colEncabezadoUbicacion',
            'colEncabezadoTecnico',
            'colEncabezadoObs',
            'colEncabezadoFormateada',
            'colEncabezadoTiempoFormateo',
            'colEncabezadoCaja',
            'colEncabezadoCargador',
            'colEncabezadoCables',
            'colMouse',
            'colEncabezadoMouse',
            'colEncabezadoTeclado',
            'colEncabezadoLista',
            'colEncabezadoTipo',
            'colEncabezadoEntregada',
            'colEncabezadoTiempoEntrega',
            'colEncabezadoPrestada',
            'colEncabezadoAcciones'
        ];
        const ordenEstandarFiltros = [
            'colFiltroPaseLista',
            'colFiltroSerie',
            'colFiltroMarca',
            'colFiltroUbicacion',
            'colFiltroTecnico',
            'colFiltroObs',
            'colFiltroFormateada',
            'colFiltroTiempoFormateo',
            'colFiltroCaja',
            'colFiltroCargador',
            'colFiltroCables',
            'colFiltroMouse',
            'colFiltroTeclado',
            'colFiltroLista',
            'colFiltroTipo',
            'colFiltroEntregada',
            'colFiltroTiempoEntrega',
            'colFiltroPrestada',
            'colFiltroAcciones'
        ];
        ordenEstandarEncabezados.forEach(id => {
            const el = document.getElementById(id);
            if (el) filaEncabezados.appendChild(el);
        });
        ordenEstandarFiltros.forEach(id => {
            const el = document.getElementById(id);
            if (el) filaFiltros.appendChild(el);
        });
    }
}

// Renderizar tabla HTML
function renderizarTabla() {
    const cuerpoTabla = document.getElementById('cuerpoTablaEquipos');
    const esCajas = estadoApp.pestanaActiva === 'cajas';
    const esMonitores = estadoApp.pestanaActiva === 'monitores';
    const esSoloPC = estadoApp.pestanaActiva === 'computadoras';
    const esSoloLaptop = estadoApp.pestanaActiva === 'laptops';

    // Ajustar el orden físico de las columnas en el DOM según la pestaña activa
    ajustarOrdenColumnasDOM(estadoApp.pestanaActiva);

    // Encabezados
    const colObs = document.getElementById('colEncabezadoObs');
    const colTiempoFormateo = document.getElementById('colEncabezadoTiempoFormateo');
    const colTecnico = document.getElementById('colEncabezadoTecnico');
    const colFormateada = document.getElementById('colEncabezadoFormateada');
    const colCaja = document.getElementById('colEncabezadoCaja');
    const colCargador = document.getElementById('colEncabezadoCargador');
    const colCables = document.getElementById('colEncabezadoCables');
    const colMouse = document.getElementById('colMouse') || document.getElementById('colEncabezadoMouse');
    const colTeclado = document.getElementById('colEncabezadoTeclado');
    const colLista = document.getElementById('colEncabezadoLista');
    const colTipo = document.getElementById('colEncabezadoTipo');
    const colEntregada = document.getElementById('colEncabezadoEntregada');
    const colTiempoEntrega = document.getElementById('colEncabezadoTiempoEntrega');
    const colPrestada = document.getElementById('colEncabezadoPrestada');

    // Celdas de filtros de columna
    const colFiltroTecnico = document.getElementById('colFiltroTecnico');
    const colFiltroFormateada = document.getElementById('colFiltroFormateada');
    const colFiltroCaja = document.getElementById('colFiltroCaja');
    const colFiltroCargador = document.getElementById('colFiltroCargador');
    const colFiltroCables = document.getElementById('colFiltroCables');
    const colFiltroMouse = document.getElementById('colFiltroMouse');
    const colFiltroTeclado = document.getElementById('colFiltroTeclado');
    const colFiltroLista = document.getElementById('colFiltroLista');
    const colFiltroTipo = document.getElementById('colFiltroTipo');
    const colFiltroEntregada = document.getElementById('colFiltroEntregada');
    const colFiltroTiempoEntrega = document.getElementById('colFiltroTiempoEntrega');
    const colFiltroPrestada = document.getElementById('colFiltroPrestada');

    if (esCajas) {
        // En cajas: No. Serie, Marca, Ubicación, Tipo de Caja, Tiene Caja, Comentario, Timestamp de Registro y Acciones
        if (colObs) colObs.textContent = 'Comentario';
        if (colTiempoFormateo) colTiempoFormateo.textContent = 'Timestamp (Registro)';

        // 1. Columna Tipo de Caja (Laptop, PC o Monitor)
        if (colTipo) {
            colTipo.textContent = 'Tipo de Caja';
            colTipo.style.display = '';
        }
        if (colFiltroTipo) {
            colFiltroTipo.style.display = '';
            const selTipo = document.getElementById('filtroColTipo');
            if (selTipo) {
                const valActual = selTipo.value;
                selTipo.innerHTML = `
                    <option value="">Todos</option>
                    <option value="laptop">💻 Laptop</option>
                    <option value="PC">🖥️ PC</option>
                    <option value="monitor">🖥️ Monitor</option>
                `;
                selTipo.value = valActual;
            }
        }

        // 2. Columna Tiene Caja
        if (colCaja) {
            colCaja.textContent = 'Tiene Caja';
            colCaja.style.display = '';
        }
        if (colFiltroCaja) {
            colFiltroCaja.style.display = '';
            const selCaja = document.getElementById('filtroColCaja');
            if (selCaja) {
                const valActual = selCaja.value;
                selCaja.innerHTML = `
                    <option value="">Todas</option>
                    <option value="1">✔ Con equipo</option>
                    <option value="0">- Sin equipo</option>
                `;
                selCaja.value = valActual;
            }
        }

        // Mostrar columnas de comentarios y timestamp de registro
        if (colObs) colObs.style.display = '';
        if (document.getElementById('colFiltroObs')) document.getElementById('colFiltroObs').style.display = '';
        if (colTiempoFormateo) colTiempoFormateo.style.display = '';
        if (document.getElementById('colFiltroTiempoFormateo')) document.getElementById('colFiltroTiempoFormateo').style.display = '';

        // Ocultar columnas irrelevantes para cajas
        const elementosAOcultar = [
            colTecnico, colFiltroTecnico,
            colFormateada, colFiltroFormateada,
            colCargador, colFiltroCargador,
            colCables, colFiltroCables,
            colMouse, colFiltroMouse,
            colTeclado, colFiltroTeclado,
            colLista, colFiltroLista,
            colEntregada, colFiltroEntregada,
            colTiempoEntrega, colFiltroTiempoEntrega,
            colPrestada, colFiltroPrestada
        ];
        elementosAOcultar.forEach(elem => { if (elem) elem.style.display = 'none'; });
    } else if (esMonitores) {
        // En monitores: No. Serie, Marca, Ubicación, Observaciones, Caja, Cables, Lista, Tipo, Préstamo, Acciones
        if (colObs) colObs.textContent = 'Observaciones';
        if (colTipo) colTipo.textContent = 'Tipo';
        if (colCaja) colCaja.textContent = 'Caja';
        if (colCables) colCables.textContent = 'Cables';
        if (colLista) colLista.textContent = 'Lista';

        if (colCaja) colCaja.style.display = '';
        if (colFiltroCaja) {
            colFiltroCaja.style.display = '';
            const selCaja = document.getElementById('filtroColCaja');
            if (selCaja && !selCaja.querySelector('option[value="0"]:not([value=""])')?.textContent.includes('Sin caja')) {
                const valActual = selCaja.value;
                selCaja.innerHTML = `
                    <option value="">Todas</option>
                    <option value="1">✔ Con caja</option>
                    <option value="0">✖ Sin caja</option>
                `;
                selCaja.value = valActual;
            }
        }
        if (colCables) colCables.style.display = '';
        if (colFiltroCables) colFiltroCables.style.display = '';
        if (colLista) colLista.style.display = '';
        if (colFiltroLista) colFiltroLista.style.display = '';
        if (colTipo) colTipo.style.display = '';
        if (colFiltroTipo) {
            colFiltroTipo.style.display = '';
            const selTipo = document.getElementById('filtroColTipo');
            if (selTipo && !selTipo.querySelector('option[value="caja"]')) {
                const valActual = selTipo.value;
                selTipo.innerHTML = `
                    <option value="">Todos</option>
                    <option value="laptop">💻 Laptop</option>
                    <option value="PC">🖥️ PC</option>
                    <option value="monitor">🖥️ Monitor</option>
                    <option value="caja">📦 Caja</option>
                `;
                selTipo.value = valActual;
            }
        }
        if (colPrestada) colPrestada.style.display = '';
        if (colFiltroPrestada) colFiltroPrestada.style.display = '';

        // Ocultar columnas irrelevantes para monitores
        const elementosAOcultar = [
            colTecnico, colFiltroTecnico,
            colFormateada, colFiltroFormateada,
            colTiempoFormateo, document.getElementById('colFiltroTiempoFormateo'),
            colCargador, colFiltroCargador,
            colMouse, colFiltroMouse,
            colTeclado, colFiltroTeclado,
            colEntregada, colFiltroEntregada,
            colTiempoEntrega, colFiltroTiempoEntrega
        ];
        elementosAOcultar.forEach(elem => { if (elem) elem.style.display = 'none'; });
    } else {
        // Pestañas de Laptops, PCs o Todas
        if (colObs) colObs.textContent = 'Observaciones';
        if (colTiempoFormateo) {
            colTiempoFormateo.textContent = 'Timestamp Formateo / Lista';
            colTiempoFormateo.style.display = '';
        }
        if (document.getElementById('colFiltroTiempoFormateo')) document.getElementById('colFiltroTiempoFormateo').style.display = '';
        if (colTipo) colTipo.textContent = 'Tipo';
        if (colCaja) colCaja.textContent = 'Caja';

        // Restaurar opciones completas en filtros si venía de Cajas
        const selTipo = document.getElementById('filtroColTipo');
        if (selTipo && !selTipo.querySelector('option[value="caja"]')) {
            const valActual = selTipo.value;
            selTipo.innerHTML = `
                <option value="">Todos</option>
                <option value="laptop">💻 Laptop</option>
                <option value="PC">🖥️ PC</option>
                <option value="monitor">🖥️ Monitor</option>
                <option value="caja">📦 Caja</option>
            `;
            selTipo.value = valActual;
        }

        const selCaja = document.getElementById('filtroColCaja');
        if (selCaja && !selCaja.querySelector('option[value="0"]:not([value=""])')?.textContent.includes('Sin caja')) {
            const valActual = selCaja.value;
            selCaja.innerHTML = `
                <option value="">Todas</option>
                <option value="1">✔ Con caja</option>
                <option value="0">✖ Sin caja</option>
            `;
            selCaja.value = valActual;
        }

        const elementosAMostrar = [
            colTecnico, colFiltroTecnico,
            colObs, document.getElementById('colFiltroObs'),
            colFormateada, colFiltroFormateada,
            colCaja, colFiltroCaja,
            colLista, colFiltroLista,
            colTipo, colFiltroTipo,
            colEntregada, colFiltroEntregada,
            colTiempoEntrega, colFiltroTiempoEntrega,
            colPrestada, colFiltroPrestada
        ];
        elementosAMostrar.forEach(elem => { if (elem) elem.style.display = ''; });

        if (esSoloPC) {
            if (colCargador) colCargador.style.display = 'none';
            if (colFiltroCargador) colFiltroCargador.style.display = 'none';
            if (colCables) colCables.style.display = '';
            if (colFiltroCables) colFiltroCables.style.display = '';
            if (colMouse) colMouse.style.display = '';
            if (colFiltroMouse) colFiltroMouse.style.display = '';
            if (colTeclado) colTeclado.style.display = '';
            if (colFiltroTeclado) colFiltroTeclado.style.display = '';
        } else if (esSoloLaptop) {
            if (colCargador) colCargador.style.display = '';
            if (colFiltroCargador) colFiltroCargador.style.display = '';
            if (colCables) colCables.style.display = 'none';
            if (colFiltroCables) colFiltroCables.style.display = 'none';
            if (colMouse) colMouse.style.display = 'none';
            if (colFiltroMouse) colFiltroMouse.style.display = 'none';
            if (colTeclado) colTeclado.style.display = 'none';
            if (colFiltroTeclado) colFiltroTeclado.style.display = 'none';
        } else {
            // Todas
            if (colCargador) colCargador.style.display = '';
            if (colFiltroCargador) colFiltroCargador.style.display = '';
            if (colCables) colCables.style.display = '';
            if (colFiltroCables) colFiltroCables.style.display = '';
            if (colMouse) colMouse.style.display = '';
            if (colFiltroMouse) colFiltroMouse.style.display = '';
            if (colTeclado) colTeclado.style.display = '';
            if (colFiltroTeclado) colFiltroTeclado.style.display = '';
        }
    }

    const equiposFiltrados = obtenerEquiposFiltrados();

    // Actualizar inmediatamente los contadores de la tabla
    actualizarContadorFiltrado(equiposFiltrados.length, estadoApp.equipos.length);

    if (equiposFiltrados.length === 0) {
        const totalColspan = esCajas ? 9 : (esMonitores ? 11 : 18);
        cuerpoTabla.innerHTML = `
            <tr>
                <td colspan="${totalColspan}" style="text-align: center; padding: 2.5rem; color: #64748b;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔍</div>
                    No se encontraron registros con los filtros seleccionados.<br><br>
                    <button class="btn btn-secundario btn-sm" onclick="limpiarTodosLosFiltros()">🧹 Limpiar filtros</button>
                </td>
            </tr>
        `;
        actualizarBotonQuitarSeleccion();
        return;
    }

    cuerpoTabla.innerHTML = equiposFiltrados.map(equipo => {
        const esCaja = equipo.tipo === 'caja' || equipo.tabla_origen === 'cajas';
        const esPC = equipo.tipo === 'PC' || equipo.tabla_origen === 'computadoras';
        const esMonitor = equipo.tipo === 'monitor' || equipo.tabla_origen === 'monitores';
        const claseMarca = obtenerClaseMarca(equipo.marca, equipo.tipo);
        const claseUbi = obtenerClaseUbicacion(equipo.ubicacion);
        const textoUbi = escaparHtml(equipo.ubicacion || 'Versalles');
        const claveEquipo = `${equipo.tabla_origen}_${equipo.id}`;
        const estaMarcadoPase = estadoApp.seleccionadosPaseLista.has(claveEquipo);

        // Préstamo: badge o botón prestar
        const celdaPrestamo = equipo.prestado_a
            ? `<span class="badge-prestada" onclick="abrirModalPrestamo('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', '${escaparHtml(equipo.prestado_a)}')" title="Prestada a ${escaparHtml(equipo.prestado_a)} (clic para ver, editar o devolver)">🤝 ${escaparHtml(equipo.prestado_a)}</span>`
            : `<button type="button" class="btn-prestar-equipo" onclick="abrirModalPrestamo('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', '')" title="Registrar préstamo de este equipo">+ Prestar</button>`;

        // Observaciones / Comentario: modal o tooltip
        let celdaObservaciones = '';
        const textoObs = equipo.observaciones || equipo.comentario || '';
        if (textoObs.trim() !== '') {
            const obsSegura = escaparHtml(textoObs);
            const obsCodificada = encodeURIComponent(textoObs);
            const tituloObs = esCaja ? 'Clic para editar comentario' : 'Clic para editar observación';
            celdaObservaciones = `
                <div class="contenedor-obs">
                    <span class="icono-obs" onclick="abrirModalObservacion('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', decodeURIComponent('${obsCodificada}'))" title="${tituloObs}">💬</span>
                    <div class="tooltip-obs">${obsSegura}</div>
                </div>
            `;
        } else {
            const tituloAgregar = esCaja ? 'Agregar comentario' : 'Agregar observación';
            celdaObservaciones = `
                <button class="btn-agregar-obs" onclick="abrirModalObservacion('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', '')" title="${tituloAgregar}">+</button>
            `;
        }

        // Si estamos viendo exclusivamente la pestaña de cajas, renderizamos la fila minimalista
        if (esCajas) {
            const textoTipoCaja = (equipo.tipo_caja === 'PC') ? '🖥️ PC' : (equipo.tipo_caja === 'monitor' ? '🖥️ Monitor' : '💻 Laptop');
            const claseTipoCaja = (equipo.tipo_caja === 'PC') ? 'pc' : (equipo.tipo_caja === 'monitor' ? 'monitor' : 'laptop');
            const tieneEquipo = equipo.tiene_equipo_vinculado == 1 || equipo.caja == 1;
            const celdaTieneEquipo = tieneEquipo
                ? `<td class="col-caja" style="text-align: center;"><span class="icono-estado estado-check" title="Equipo encontrado: ${escaparHtml(equipo.equipo_vinculado_desc || '')}">✔</span> ${equipo.equipo_vinculado_desc ? `<span class="badge-equipo-asignado">${escaparHtml(equipo.equipo_vinculado_desc)}</span>` : ''}</td>`
                : `<td class="col-caja" style="text-align: center;"><span class="icono-estado estado-vacio" title="Sin equipo registrado con este número de serie">-</span></td>`;

            return `
                <tr class="${estaMarcadoPase ? 'fila-pase-lista-verde' : ''}">
                    <td class="col-pase-lista" style="text-align: center;">
                        <input type="checkbox" class="check-pase-lista" data-clave="${claveEquipo}" ${estaMarcadoPase ? 'checked' : ''} onchange="alternarFilaPaseLista('${claveEquipo}', this)" title="Marcar/Desmarcar fila verde para pase de lista">
                    </td>
                    <td class="col-serie"><strong>${escaparHtml(equipo.no_serie)}</strong></td>
                    <td class="col-marca"><span class="badge-marca ${claseMarca}">${escaparHtml(equipo.marca)}</span></td>
                    <td class="col-ubicacion"><span class="badge-ubicacion ${claseUbi}">${textoUbi}</span></td>
                    <td class="col-tipo" style="text-align: center;">
                        <span class="badge-tipo-caja ${claseTipoCaja}">${textoTipoCaja}</span>
                    </td>
                    ${celdaTieneEquipo}
                    <td class="col-obs" style="text-align: center;">${celdaObservaciones}</td>
                    <td class="col-tiempo-formateo">
                        ${equipo.creado_en ? `<span class="tag-tiempo" style="background:#e0e7ff; color:#3730a3;" title="Fecha y hora de registro">⏱️ ${formatearFechaLegible(equipo.creado_en)}</span>` : '<span style="color: #94a3b8;">-</span>'}
                    </td>
                    <td class="col-acciones">
                        <div class="grupo-acciones-fila">
                            <button class="btn-accion-icono" onclick="editarEquipo('${equipo.tabla_origen}', ${equipo.id})" title="Editar caja">
                                ✏️
                            </button>
                            <button class="btn-accion-icono peligro" onclick="confirmarEliminar('${equipo.tabla_origen}', ${equipo.id}, '${equipo.no_serie}')" title="Eliminar caja">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }

        // Si estamos viendo exclusivamente la pestaña de Monitores
        if (esMonitores) {
            const tieneCajaVinculada = equipo.tiene_caja_vinculada == 1;
            const esCajaSinSerie = equipo.caja_sin_serie == 1;
            const tieneCaja = equipo.caja == 1 || tieneCajaVinculada || esCajaSinSerie;
            let tituloCaja = "Sin caja (clic para cambiar)";
            if (tieneCajaVinculada) {
                tituloCaja = `Con caja vinculada por serie (${escaparHtml(equipo.caja_marca_vinculada || '')} - clic para cambiar)`;
            } else if (tieneCaja) {
                tituloCaja = "Con caja (clic para cambiar)";
            }

            let iconoCaja = '';
            if (esCajaSinSerie) {
                iconoCaja = `<span class="badge-caja-sin-serie" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'caja', 'sin_serie')" title="En caja sin número de serie (clic para cambiar)">📦 Sin serie</span>`;
            } else if (tieneCaja) {
                iconoCaja = `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'caja', '1')" title="${tituloCaja}">✔</span>`;
            } else {
                iconoCaja = `<span class="icono-estado estado-cruz" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'caja', '0')" title="${tituloCaja}">✖</span>`;
            }

            const iconoCables = equipo.cables == 1
                ? `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'cables', '1')" title="Con cables (clic para cambiar)">✔</span>`
                : `<span class="icono-estado estado-cruz" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'cables', '0')" title="Sin cables (clic para cambiar)">✖</span>`;

            const iconoLista = equipo.lista == 1
                ? `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'lista', '1')" title="Monitor listo (clic para cambiar)">✔</span>`
                : `<span class="icono-estado estado-vacio" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'lista', '0')" title="No listo (clic para cambiar)">-</span>`;

            const iconoTipo = `<span title="Monitor" style="font-size: 1.15rem;">🖥️</span>`;

            return `
                <tr class="${estaMarcadoPase ? 'fila-pase-lista-verde' : ''}">
                    <td class="col-pase-lista" style="text-align: center;">
                        <input type="checkbox" class="check-pase-lista" data-clave="${claveEquipo}" ${estaMarcadoPase ? 'checked' : ''} onchange="alternarFilaPaseLista('${claveEquipo}', this)" title="Marcar/Desmarcar fila verde para pase de lista">
                    </td>
                    <td class="col-serie"><strong>${escaparHtml(equipo.no_serie)}</strong></td>
                    <td class="col-marca"><span class="badge-marca ${claseMarca}">${escaparHtml(equipo.marca)}</span></td>
                    <td class="col-ubicacion"><span class="badge-ubicacion ${claseUbi}">${textoUbi}</span></td>
                    <td class="col-obs" style="text-align: center;">${celdaObservaciones}</td>
                    <td class="col-caja" style="text-align: center;">${iconoCaja}</td>
                    <td class="col-cables" style="text-align: center;">${iconoCables}</td>
                    <td class="col-lista" style="text-align: center;">${iconoLista}</td>
                    <td class="col-tipo" style="text-align: center;">${iconoTipo}</td>
                    <td class="col-prestada" style="text-align: center;">${celdaPrestamo}</td>
                    <td class="col-acciones">
                        <div class="grupo-acciones-fila">
                            <button class="btn-accion-icono" onclick="editarEquipo('${equipo.tabla_origen}', ${equipo.id})" title="Editar monitor">
                                ✏️
                            </button>
                            <button class="btn-accion-icono peligro" onclick="confirmarEliminar('${equipo.tabla_origen}', ${equipo.id}, '${equipo.no_serie}')" title="Eliminar monitor">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }

        // Caso equipo normal o caja en pestaña 'Todas'
        if (esCaja) {
            const tieneEquipo = equipo.tiene_equipo_vinculado == 1 || equipo.caja == 1;
            const iconoCajaEnTodas = tieneEquipo
                ? `<span class="icono-estado estado-check" title="Equipo encontrado: ${escaparHtml(equipo.equipo_vinculado_desc || '')}">✔</span>`
                : `<span class="icono-estado estado-vacio" title="Sin equipo registrado con este número de serie">-</span>`;

            return `
                <tr class="${estaMarcadoPase ? 'fila-pase-lista-verde' : ''}">
                    <td class="col-pase-lista" style="text-align: center;">
                        <input type="checkbox" class="check-pase-lista" data-clave="${claveEquipo}" ${estaMarcadoPase ? 'checked' : ''} onchange="alternarFilaPaseLista('${claveEquipo}', this)" title="Marcar/Desmarcar fila verde para pase de lista">
                    </td>
                    <td class="col-serie"><strong>${escaparHtml(equipo.no_serie)}</strong></td>
                    <td class="col-marca"><span class="badge-marca ${claseMarca}">${escaparHtml(equipo.marca)}</span></td>
                    <td class="col-ubicacion"><span class="badge-ubicacion ${claseUbi}">${textoUbi}</span></td>
                    <td class="col-tecnico" style="text-align: center;"><span style="color: #cbd5e1;">-</span></td>
                    <td class="col-obs" style="text-align: center;">${celdaObservaciones}</td>
                    <td class="col-formateada" style="text-align: center;"><span style="color: #cbd5e1;">-</span></td>
                    <td class="col-tiempo-formateo">
                        ${equipo.creado_en ? `<span class="tag-tiempo" style="background:#e0e7ff; color:#3730a3;" title="Fecha de registro">📦 ${formatearFechaLegible(equipo.creado_en)}</span>` : '<span style="color: #94a3b8;">-</span>'}
                    </td>
                    <td class="col-caja" style="text-align: center;">${iconoCajaEnTodas}</td>
                    ${esSoloPC ? '' : '<td class="col-cargador" style="text-align: center;"><span style="color: #cbd5e1;">-</span></td>'}
                    ${esSoloLaptop ? '' : '<td class="col-cables" style="text-align: center;"><span style="color: #cbd5e1;">-</span></td>'}
                    ${esSoloLaptop ? '' : '<td class="col-mouse" style="text-align: center;"><span style="color: #cbd5e1;">-</span></td>'}
                    ${esSoloLaptop ? '' : '<td class="col-teclado" style="text-align: center;"><span style="color: #cbd5e1;">-</span></td>'}
                    <td class="col-lista" style="text-align: center;"><span style="color: #cbd5e1;">-</span></td>
                    <td class="col-tipo" style="text-align: center;"><span title="Caja" style="font-size: 1.15rem;">📦</span></td>
                    <td class="col-entregada" style="text-align: center;"><span style="color: #cbd5e1;">-</span></td>
                    <td class="col-tiempo-entrega"><span style="color: #94a3b8;">-</span></td>
                    <td class="col-prestada" style="text-align: center;"><span style="color: #cbd5e1;">-</span></td>
                    <td class="col-acciones">
                        <div class="grupo-acciones-fila">
                            <button class="btn-accion-icono" onclick="editarEquipo('${equipo.tabla_origen}', ${equipo.id})" title="Editar caja">
                                ✏️
                            </button>
                            <button class="btn-accion-icono peligro" onclick="confirmarEliminar('${equipo.tabla_origen}', ${equipo.id}, '${equipo.no_serie}')" title="Eliminar caja">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }

        // 1. Técnico: A de Adrian o K de Keruum con tooltip
        let badgeTecnico = '<span style="color: #cbd5e1;">-</span>';
        const nombreTec = (equipo.formateado_por || '').toUpperCase();
        if (nombreTec.includes('ADRIAN')) {
            badgeTecnico = `<span class="avatar-tecnico tecnico-a" title="Formateado por: ADRIAN">A</span>`;
        } else if (nombreTec.includes('KERUUM')) {
            badgeTecnico = `<span class="avatar-tecnico tecnico-k" title="Formateado por: KERUUM">K</span>`;
        } else if (nombreTec !== '') {
            badgeTecnico = `<span class="avatar-tecnico tecnico-gen" title="Formateado por: ${escaparHtml(equipo.formateado_por)}">${nombreTec.charAt(0)}</span>`;
        }

        // 3. Icono de Formateada con modal de confirmación
        let iconoFormateada = '<span style="color: #cbd5e1;">-</span>';
        if (!esMonitor) {
            iconoFormateada = `<span class="icono-estado estado-vacio" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'formateada', '${equipo.formateada}')" title="Pendiente (clic para cambiar)">-</span>`;
            if (equipo.formateada === 'formateada') {
                iconoFormateada = `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'formateada', '${equipo.formateada}')" title="Formateada completada (clic para cambiar)">✔</span>`;
            } else if (equipo.formateada === 'en_proceso') {
                iconoFormateada = `<span class="icono-estado estado-reloj" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'formateada', '${equipo.formateada}')" title="En formateo / Reloj (clic para cambiar)">⏳</span>`;
            } else if (equipo.formateada === 'error') {
                iconoFormateada = `<span class="icono-estado estado-cruz" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'formateada', '${equipo.formateada}')" title="Error al formatear (clic para cambiar)">✖</span>`;
            }
        }

        // 4. Icono de Caja: Palomita si tiene caja marcada O si existe una caja vinculada en la tabla cajas O caja sin serie
        const tieneCajaVinculada = equipo.tiene_caja_vinculada == 1;
        const esCajaSinSerie = equipo.caja_sin_serie == 1;
        const tieneCaja = equipo.caja == 1 || tieneCajaVinculada || esCajaSinSerie;
        let tituloCaja = "Sin caja (clic para cambiar)";
        if (tieneCajaVinculada) {
            tituloCaja = `Con caja vinculada por serie (${escaparHtml(equipo.caja_marca_vinculada || '')} - clic para cambiar)`;
        } else if (tieneCaja) {
            tituloCaja = "Con caja (clic para cambiar)";
        }

        let iconoCaja = '';
        if (esCajaSinSerie) {
            iconoCaja = `<span class="badge-caja-sin-serie" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'caja', 'sin_serie')" title="En caja sin número de serie (clic para cambiar)">📦 Sin serie</span>`;
        } else if (tieneCaja) {
            iconoCaja = `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'caja', '1')" title="${tituloCaja}">✔</span>`;
        } else {
            iconoCaja = `<span class="icono-estado estado-cruz" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'caja', '0')" title="${tituloCaja}">✖</span>`;
        }

        // 5. Icono de Cargador (Laptop) con modal de confirmación
        let iconoCargador = '<span class="icono-estado estado-vacio">-</span>';
        if (!esPC && !esMonitor) {
            if (equipo.cargador === 'si') {
                iconoCargador = `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'cargador', '${equipo.cargador}')" title="Con cargador (clic para cambiar)">✔</span>`;
            } else if (equipo.cargador === 'advertencia') {
                iconoCargador = `<span class="icono-estado estado-alerta" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'cargador', '${equipo.cargador}')" title="Con advertencia (clic para cambiar)">⚠️</span>`;
            } else {
                iconoCargador = `<span class="icono-estado estado-cruz" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'cargador', '${equipo.cargador}')" title="Sin cargador (clic para cambiar)">✖</span>`;
            }
        }

        // 6. Cables, Mouse y Teclado para PC y Monitores
        let iconoCables = '<span style="color:#94a3b8;">-</span>';
        let iconoMouse = '<span style="color:#94a3b8;">-</span>';
        let iconoTeclado = '<span style="color:#94a3b8;">-</span>';

        if (esPC || esMonitor) {
            iconoCables = equipo.cables == 1
                ? `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'cables', '1')" title="Con cables (clic para cambiar)">✔</span>`
                : `<span class="icono-estado estado-cruz" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'cables', '0')" title="Sin cables (clic para cambiar)">✖</span>`;
        }

        if (esPC) {
            iconoMouse = equipo.mouse == 1
                ? `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'mouse', '1')" title="Con mouse (clic para cambiar)">✔</span>`
                : `<span class="icono-estado estado-cruz" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'mouse', '0')" title="Sin mouse (clic para cambiar)">✖</span>`;

            iconoTeclado = equipo.teclado == 1
                ? `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'teclado', '1')" title="Con teclado (clic para cambiar)">✔</span>`
                : `<span class="icono-estado estado-cruz" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'teclado', '0')" title="Sin teclado (clic para cambiar)">✖</span>`;
        }

        // 7. Icono de Lista con modal de confirmación
        const iconoLista = equipo.lista == 1
            ? `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'lista', '1')" title="Equipo listo (clic para cambiar)">✔</span>`
            : `<span class="icono-estado estado-vacio" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'lista', '0')" title="No listo (clic para cambiar)">-</span>`;

        // 8. Icono de Tipo (Laptop 💻, PC 🖥️ o Monitor 🖥️)
        const iconoTipo = esMonitor
            ? `<span title="Monitor" style="font-size: 1.15rem;">🖥️</span>`
            : (esPC ? `<span title="PC Torre / ThinkCentre" style="font-size: 1.15rem;">🖥️</span>` : `<span title="Laptop" style="font-size: 1.15rem;">💻</span>`);

        // 9. Icono de Entregada con modal de confirmación
        let iconoEntregada = '<span style="color: #cbd5e1;">-</span>';
        if (!esMonitor) {
            iconoEntregada = `<span class="icono-estado estado-vacio" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'entregada', '${equipo.entregada}')" title="Pendiente de entrega (clic para cambiar)">-</span>`;
            if (equipo.entregada === 'entregada') {
                iconoEntregada = `<span class="icono-estado estado-check" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'entregada', '${equipo.entregada}')" title="Entregada (clic para cambiar)">✔</span>`;
            } else if (equipo.entregada === 'en_proceso') {
                iconoEntregada = `<span class="icono-estado estado-reloj" onclick="solicitarCambioEstado('${equipo.tabla_origen}', ${equipo.id}, '${escaparHtml(equipo.no_serie)}', 'entregada', '${equipo.entregada}')" title="En proceso de entrega (clic para cambiar)">⏳</span>`;
            }
        }

        // Celdas dinámicas según pestaña
        const tdCargador = (esSoloPC) ? '' : `<td class="col-cargador" style="text-align: center;">${iconoCargador}</td>`;
        const tdCables = (esSoloLaptop) ? '' : `<td class="col-cables" style="text-align: center;">${iconoCables}</td>`;
        const tdMouse = (esSoloLaptop) ? '' : `<td class="col-mouse" style="text-align: center;">${iconoMouse}</td>`;
        const tdTeclado = (esSoloLaptop) ? '' : `<td class="col-teclado" style="text-align: center;">${iconoTeclado}</td>`;

        return `
            <tr class="${estaMarcadoPase ? 'fila-pase-lista-verde' : ''}">
                <td class="col-pase-lista" style="text-align: center;">
                    <input type="checkbox" class="check-pase-lista" data-clave="${claveEquipo}" ${estaMarcadoPase ? 'checked' : ''} onchange="alternarFilaPaseLista('${claveEquipo}', this)" title="Marcar/Desmarcar fila verde para pase de lista">
                </td>
                <td class="col-serie"><strong>${escaparHtml(equipo.no_serie)}</strong></td>
                <td class="col-marca"><span class="badge-marca ${claseMarca}">${escaparHtml(equipo.marca)}</span></td>
                <td class="col-ubicacion"><span class="badge-ubicacion ${claseUbi}">${textoUbi}</span></td>
                <td class="col-tecnico" style="text-align: center;">${badgeTecnico}</td>
                <td class="col-obs" style="text-align: center;">${celdaObservaciones}</td>
                <td class="col-formateada" style="text-align: center;">${iconoFormateada}</td>
                <td class="col-tiempo-formateo">
                    <div class="celda-timestamp">
                        ${equipo.hora_inicio_formateo ? `<span class="tag-tiempo formateo" title="Hora que se puso a formatear">⏱️ ${formatearFechaLegible(equipo.hora_inicio_formateo)}</span>` : '<span style="color: #94a3b8;">-</span>'}
                        ${equipo.hora_lista ? `<span class="tag-tiempo lista" title="Hora que se dejó lista">📦 ${formatearFechaLegible(equipo.hora_lista)}</span>` : ''}
                    </div>
                </td>
                <td class="col-caja" style="text-align: center;">${iconoCaja}</td>
                ${tdCargador}
                ${tdCables}
                ${tdMouse}
                ${tdTeclado}
                <td class="col-lista" style="text-align: center;">${iconoLista}</td>
                <td class="col-tipo" style="text-align: center;">${iconoTipo}</td>
                <td class="col-entregada" style="text-align: center;">${iconoEntregada}</td>
                <td class="col-tiempo-entrega">
                    ${equipo.hora_entrega ? `<span class="tag-tiempo entrega" title="Hora de entrega">🚚 ${formatearFechaLegible(equipo.hora_entrega)}</span>` : '<span style="color: #94a3b8;">-</span>'}
                </td>
                <td class="col-prestada" style="text-align: center;">${celdaPrestamo}</td>
                <td class="col-acciones">
                    <div class="grupo-acciones-fila">
                        <button class="btn-accion-icono" onclick="editarEquipo('${equipo.tabla_origen}', ${equipo.id})" title="Editar equipo">
                            ✏️
                        </button>
                        <button class="btn-accion-icono peligro" onclick="confirmarEliminar('${equipo.tabla_origen}', ${equipo.id}, '${equipo.no_serie}')" title="Eliminar equipo">
                            🗑️
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    actualizarBotonQuitarSeleccion();
    configurarRedimensionColumnas();
    actualizarAnchoScrollHorizontal();
}

// ==========================================================================
// REDIMENSIONAMIENTO INTERACTIVO DE COLUMNAS (ARRASTRAR CON EL MOUSE)
// ==========================================================================
function configurarRedimensionColumnas() {
    const tabla = document.getElementById('tablaEquipos');
    if (!tabla) return;

    // Fila superior de encabezados
    const filaEncabezados = tabla.querySelector('thead tr:first-child');
    if (!filaEncabezados) return;

    const encabezados = filaEncabezados.querySelectorAll('th');

    // Recuperar anchos guardados en localStorage
    let anchosGuardados = {};
    try {
        const guardado = localStorage.getItem('anchos_columnas_equipos');
        if (guardado) {
            anchosGuardados = JSON.parse(guardado);
        }
    } catch (e) {
        console.warn('Error al leer anchos de columnas guardados:', e);
    }

    encabezados.forEach((th, indice) => {
        // La columna de checkbox de pase de lista se mantiene fija en 44px
        if (th.id === 'colEncabezadoPaseLista') {
            th.style.width = '44px';
            th.style.minWidth = '44px';
            return;
        }

        // Si la columna está oculta en la vista activa, salir
        if (th.style.display === 'none') {
            const resizerExistente = th.querySelector('.resizer-columna');
            if (resizerExistente) resizerExistente.remove();
            return;
        }

        const idColumna = th.id || `col_${indice}`;

        // Restaurar ancho guardado previamente si existe
        if (anchosGuardados[idColumna]) {
            const anchoPrevio = parseInt(anchosGuardados[idColumna], 10);
            if (!isNaN(anchoPrevio) && anchoPrevio >= 50) {
                th.style.width = `${anchoPrevio}px`;
                th.style.minWidth = `${anchoPrevio}px`;
            }
        }

        // Inyectar el tirador visual de redimensionamiento si aún no lo tiene
        let resizer = th.querySelector('.resizer-columna');
        if (!resizer) {
            resizer = document.createElement('div');
            resizer.className = 'resizer-columna';
            resizer.title = 'Arrastra hacia los lados para cambiar el ancho de esta columna';
            th.appendChild(resizer);
        }

        // Limpiar handler anterior para evitar duplicidad
        resizer.onmousedown = null;

        resizer.onmousedown = function (e) {
            e.preventDefault();
            e.stopPropagation();

            const xInicial = e.pageX;
            const anchoInicial = th.offsetWidth;

            resizer.classList.add('activo');
            document.body.classList.add('redimensionando-columna');

            function alMover(eMove) {
                const desplazamiento = eMove.pageX - xInicial;
                const nuevoAncho = Math.max(50, anchoInicial + desplazamiento);
                th.style.width = `${nuevoAncho}px`;
                th.style.minWidth = `${nuevoAncho}px`;
            }

            function alSoltar() {
                resizer.classList.remove('activo');
                document.body.classList.remove('redimensionando-columna');
                document.removeEventListener('mousemove', alMover);
                document.removeEventListener('mouseup', alSoltar);

                // Persistir el nuevo ancho
                try {
                    anchosGuardados[idColumna] = th.offsetWidth;
                    localStorage.setItem('anchos_columnas_equipos', JSON.stringify(anchosGuardados));
                } catch (err) {
                    console.warn('Error al guardar ancho de columna:', err);
                }
            }

            document.addEventListener('mousemove', alMover);
            document.addEventListener('mouseup', alSoltar);
        };
    });
}

// Restablecer anchos de columnas a valores automáticos originales
function restablecerAnchosColumnas() {
    try {
        localStorage.removeItem('anchos_columnas_equipos');
    } catch (e) { }

    const tabla = document.getElementById('tablaEquipos');
    if (tabla) {
        const encabezados = tabla.querySelectorAll('thead th');
        encabezados.forEach(th => {
            if (th.id !== 'colEncabezadoPaseLista') {
                th.style.width = '';
                th.style.minWidth = '';
            }
        });
    }
    renderizarTabla();
    mostrarToast('Anchos de columnas restablecidos', 'info');
}

// ==========================================================================
// FUNCIONES DE PASE DE LISTA VISUAL (CHECKBOX Y FILA VERDE)
// ==========================================================================

// Alternar selección individual de fila en pase de lista
function alternarFilaPaseLista(clave, inputCheckbox) {
    const fila = inputCheckbox.closest('tr');
    if (inputCheckbox.checked) {
        estadoApp.seleccionadosPaseLista.add(clave);
        if (fila) fila.classList.add('fila-pase-lista-verde');
    } else {
        estadoApp.seleccionadosPaseLista.delete(clave);
        if (fila) fila.classList.remove('fila-pase-lista-verde');
    }
    actualizarBotonQuitarSeleccion();
}

// Alternar todas las filas visibles actualmente
function alternarTodosPaseLista(marcarTodos) {
    const checkboxes = document.querySelectorAll('.check-pase-lista');
    checkboxes.forEach(chk => {
        chk.checked = marcarTodos;
        const clave = chk.dataset.clave;
        const fila = chk.closest('tr');
        if (marcarTodos) {
            if (clave) estadoApp.seleccionadosPaseLista.add(clave);
            if (fila) fila.classList.add('fila-pase-lista-verde');
        } else {
            if (clave) estadoApp.seleccionadosPaseLista.delete(clave);
            if (fila) fila.classList.remove('fila-pase-lista-verde');
        }
    });
    actualizarBotonQuitarSeleccion();
}

// Quitar toda la selección del pase de lista (desmarcar todas las filas verdes)
function desmarcarTodoPaseLista() {
    estadoApp.seleccionadosPaseLista.clear();
    document.querySelectorAll('.check-pase-lista').forEach(chk => {
        chk.checked = false;
        const fila = chk.closest('tr');
        if (fila) fila.classList.remove('fila-pase-lista-verde');
    });
    const checkTodos = document.getElementById('checkPaseListaTodos');
    if (checkTodos) checkTodos.checked = false;

    actualizarBotonQuitarSeleccion();
    mostrarToast('Selección de pase de lista quitada', 'info');
}

// Actualizar visibilidad y contador del botón "Quitar selección" y "Edición Masiva"
function actualizarBotonQuitarSeleccion() {
    const cantidad = estadoApp.seleccionadosPaseLista.size;
    const btnQuitar = document.getElementById('btnQuitarSeleccionPase');
    const spanContador = document.getElementById('contadorPaseLista');
    const btnMasivo = document.getElementById('btnEdicionMasiva');
    const spanMasivo = document.getElementById('contadorMasivo');
    const checkTodos = document.getElementById('checkPaseListaTodos');

    if (spanContador) {
        spanContador.textContent = cantidad;
    }
    if (spanMasivo) {
        spanMasivo.textContent = cantidad;
    }

    if (btnQuitar) {
        btnQuitar.style.display = cantidad > 0 ? 'inline-flex' : 'none';
    }
    if (btnMasivo) {
        btnMasivo.style.display = cantidad > 0 ? 'inline-flex' : 'none';
    }

    // Actualizar estado del checkbox del encabezado
    if (checkTodos) {
        const visibles = document.querySelectorAll('.check-pase-lista');
        if (visibles.length > 0) {
            const todasMarcadas = Array.from(visibles).every(chk => chk.checked);
            checkTodos.checked = todasMarcadas;
        } else {
            checkTodos.checked = false;
        }
    }
}

// --------------------------------------------------------------------------
// MODAL DE CONFIRMACIÓN PARA CAMBIO DE ESTADO
// --------------------------------------------------------------------------
function solicitarCambioEstado(tabla, id, noSerie, campo, valorActual) {
    estadoApp.estadoEnEdicion = {
        tabla: tabla,
        id: id,
        noSerie: noSerie,
        campo: campo,
        valorSeleccionado: valorActual
    };

    const modal = document.getElementById('modalConfirmacionEstado');
    const titulo = document.getElementById('confirmacionTitulo');
    const subtitulo = document.getElementById('confirmacionSerie');
    const icono = document.getElementById('confirmacionIcono');
    const avisoFormateo = document.getElementById('avisoFormateoEstado');
    const contenedorOpciones = document.getElementById('contenedorOpcionesEstado');

    // Ocultar aviso de formateo por defecto
    if (avisoFormateo) {
        avisoFormateo.style.display = 'none';
        avisoFormateo.innerHTML = '';
    }

    const descTipo = (tabla === 'computadoras') ? 'PC' : (tabla === 'monitores' ? 'Monitor' : 'Laptop');
    subtitulo.textContent = `${noSerie} (${descTipo})`;

    let opciones = [];

    // Buscar el equipo en memoria para conocer su estado de formateo
    const equipoEncontrado = estadoApp.equipos.find(e => String(e.id) === String(id) && e.tabla_origen === tabla);
    const estaFormateada = equipoEncontrado && equipoEncontrado.formateada === 'formateada';

    if (campo === 'formateada') {
        icono.textContent = '⏳';
        titulo.textContent = 'Cambiar Estado de Formateo';
        opciones = [
            { valor: 'pendiente', texto: 'Pendiente (Sin iniciar)', icono: '-' },
            { valor: 'en_proceso', texto: '⏳ En Formateo (Reloj / Registra hora actual)', icono: '⏳' },
            { valor: 'formateada', texto: '✔ Formateada (Completada)', icono: '✔' },
            { valor: 'error', texto: '❌ Error al formatear', icono: '❌' }
        ];
    } else if (campo === 'caja') {
        icono.textContent = '📦';
        titulo.textContent = 'Cambiar Estado de Caja';
        opciones = [
            { valor: '1', texto: '✔ Tiene Caja (con serie)', icono: '✔' },
            { valor: 'sin_serie', texto: '📦 Caja sin número de serie', icono: '📦' },
            { valor: '0', texto: '✖ Sin Caja', icono: '✖' }
        ];
    } else if (campo === 'cargador') {
        icono.textContent = '🔌';
        titulo.textContent = 'Cambiar Estado de Cargador';
        opciones = [
            { valor: 'si', texto: '✔ Con Cargador Completo', icono: '✔' },
            { valor: 'advertencia', texto: '⚠️ Con Advertencia / Incompleto', icono: '⚠️' },
            { valor: 'no', texto: '✖ Sin Cargador', icono: '✖' }
        ];
    } else if (campo === 'cables') {
        icono.textContent = '🔌';
        titulo.textContent = (tabla === 'monitores') ? 'Cambiar Estado de Cables (Monitor)' : 'Cambiar Estado de Cables (PC)';
        opciones = [
            { valor: '1', texto: '✔ Con Cables', icono: '✔' },
            { valor: '0', texto: '✖ Sin Cables', icono: '✖' }
        ];
    } else if (campo === 'mouse') {
        icono.textContent = '🖱️';
        titulo.textContent = 'Cambiar Estado de Mouse';
        opciones = [
            { valor: '1', texto: '✔ Con Mouse', icono: '✔' },
            { valor: '0', texto: '✖ Sin Mouse', icono: '✖' }
        ];
    } else if (campo === 'teclado') {
        icono.textContent = '⌨️';
        titulo.textContent = 'Cambiar Estado de Teclado';
        opciones = [
            { valor: '1', texto: '✔ Con Teclado', icono: '✔' },
            { valor: '0', texto: '✖ Sin Teclado', icono: '✖' }
        ];
    } else if (campo === 'lista') {
        const esMonitor = tabla === 'monitores';
        if (!estaFormateada && !esMonitor) {
            // Equipo NO formateado: Permitir marcar ambos a la vez o solo formateada
            icono.textContent = '✅';
            titulo.textContent = 'Marcar Equipo como Listo';
            if (avisoFormateo) {
                avisoFormateo.style.display = 'block';
                avisoFormateo.innerHTML = `
                    <div class="alerta-advertencia-formateo">
                        <div class="alerta-icono-aviso">ℹ️</div>
                        <div class="alerta-texto-aviso">
                            <strong>Nota:</strong> El equipo aún no tiene marcado el estado de <b>Formateada</b>.
                            <div class="alerta-pregunta-aviso">Puedes marcarlo como <b>Listo y Formateada</b> a la vez.</div>
                        </div>
                    </div>
                `;
            }

            opciones = [
                { valor: 'formateada_y_lista', texto: '✔ Marcar como Lista y Formateada (ambos a la vez)', icono: '✅' },
                { valor: 'solo_formateada', texto: '⚡ Solo marcar como Formateada (Lista pendiente)', icono: '⚡' },
                { valor: '0', texto: '✖ Cancelar / Mantener Pendiente', icono: '✖' }
            ];
            estadoApp.estadoEnEdicion.valorSeleccionado = 'formateada_y_lista';
        } else {
            icono.textContent = '✅';
            titulo.textContent = esMonitor ? 'Cambiar Estado de Monitor Listo' : 'Cambiar Estado Lista';
            opciones = [
                { valor: '1', texto: esMonitor ? '✔ Monitor Listo (Registra hora actual)' : '✔ Marcada como Lista (Registra hora actual)', icono: '✔' },
                { valor: '0', texto: '✖ No Listo / Pendiente', icono: '✖' }
            ];
        }
    } else if (campo === 'entregada') {
        icono.textContent = '🚚';
        titulo.textContent = 'Cambiar Estado de Entrega';
        opciones = [
            { valor: 'entregada', texto: '✔ Entregada al cliente (Registra hora actual)', icono: '✔' },
            { valor: 'en_proceso', texto: '⏳ En proceso de entrega', icono: '⏳' },
            { valor: 'pendiente', texto: '✖ Pendiente de entrega', icono: '-' }
        ];
    }

    // Renderizar opciones seleccionables
    contenedorOpciones.innerHTML = opciones.map(op => {
        const esActivo = String(op.valor) === String(estadoApp.estadoEnEdicion.valorSeleccionado);
        return `
            <div class="item-opcion-estado ${esActivo ? 'activo' : ''}" data-valor="${op.valor}" onclick="seleccionarOpcionEstado('${op.valor}')">
                <span>${op.icono}</span>
                <span>${op.texto}</span>
            </div>
        `;
    }).join('');

    modal.classList.add('activo');
}

function seleccionarOpcionEstado(valor) {
    if (!estadoApp.estadoEnEdicion) return;
    estadoApp.estadoEnEdicion.valorSeleccionado = valor;

    document.querySelectorAll('.item-opcion-estado').forEach(item => {
        if (item.dataset.valor === String(valor)) {
            item.classList.add('activo');
        } else {
            item.classList.remove('activo');
        }
    });
}

function cerrarModalConfirmacion() {
    document.getElementById('modalConfirmacionEstado').classList.remove('activo');
    estadoApp.estadoEnEdicion = null;
}

async function aplicarCambioEstadoConfirmado() {
    if (!estadoApp.estadoEnEdicion) return;

    const { tabla, id, campo, valorSeleccionado } = estadoApp.estadoEnEdicion;
    const marcarFormateada = (valorSeleccionado === 'solo_formateada' || valorSeleccionado === 'formateada_y_lista');
    const valorEnviar = valorSeleccionado;

    try {
        const respuesta = await fetchConCsrf(obtenerUrlApi('accion=actualizar_estado'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tabla: tabla,
                id: id,
                campo: campo,
                valor: valorEnviar,
                marcar_formateada: marcarFormateada
            })
        });

        const resultado = await respuesta.json();
        if (resultado.exito) {
            mostrarToast(resultado.mensaje, 'exito');
            cerrarModalConfirmacion();
            cargarResumen();
            cargarEquipos();
        } else {
            mostrarToast(resultado.mensaje, 'error');
        }
    } catch (error) {
        console.error('Error al aplicar cambio de estado:', error);
        mostrarToast('Error al conectar con el servidor', 'error');
    }
}

// --------------------------------------------------------------------------
// MODAL DE OBSERVACIONES
// --------------------------------------------------------------------------
function abrirModalObservacion(tabla, id, noSerie, observacionActual) {
    document.getElementById('obsTabla').value = tabla;
    document.getElementById('obsId').value = id;
    document.getElementById('inputTextoObservacion').value = observacionActual || '';
    const esCaja = tabla === 'cajas';
    document.getElementById('modalObsTitulo').textContent = esCaja ? `Comentario: ${noSerie}` : `Observación: ${noSerie}`;

    document.getElementById('modalObservacion').classList.add('activo');
}

function cerrarModalObservacion() {
    document.getElementById('modalObservacion').classList.remove('activo');
}

async function guardarObservacion(e) {
    e.preventDefault();
    const tabla = document.getElementById('obsTabla').value;
    const id = document.getElementById('obsId').value;
    const observaciones = document.getElementById('inputTextoObservacion').value.trim();

    try {
        const respuesta = await fetchConCsrf(obtenerUrlApi('accion=actualizar_observacion'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tabla, id, observaciones })
        });

        const resultado = await respuesta.json();
        if (resultado.exito) {
            mostrarToast(resultado.mensaje, 'exito');
            cerrarModalObservacion();
            cargarEquipos();
        } else {
            mostrarToast(resultado.mensaje, 'error');
        }
    } catch (error) {
        console.error('Error al guardar observación:', error);
        mostrarToast('Error al actualizar observación', 'error');
    }
}

// --------------------------------------------------------------------------
// VERIFICACIÓN DE NÚMERO DE SERIE DUPLICADO EN TIEMPO REAL
// --------------------------------------------------------------------------
async function verificarSerieDuplicadoEnTiempoReal() {
    const inputNoSerie = document.getElementById('noSerie');
    const avisoDuplicado = document.getElementById('avisoSerieDuplicado');
    const avisoCaja = document.getElementById('avisoCajaEncontrada');
    if (!inputNoSerie) return;

    const serie = inputNoSerie.value.trim();
    const idActual = document.getElementById('equipoId').value || 0;
    const tipo = document.getElementById('formularioTipo').value;
    let tabla = 'laptops';
    if (tipo === 'caja') tabla = 'cajas';
    else if (tipo === 'PC') tabla = 'computadoras';
    else if (tipo === 'monitor') tabla = 'monitores';

    if (serie === '') {
        if (avisoDuplicado) { avisoDuplicado.style.display = 'none'; avisoDuplicado.innerHTML = ''; }
        if (avisoCaja) { avisoCaja.style.display = 'none'; avisoCaja.innerHTML = ''; }
        inputNoSerie.classList.remove('input-serie-error');
        return;
    }

    try {
        const respuesta = await fetchConCsrf(obtenerUrlApi(`accion=verificar_serie&serie=${encodeURIComponent(serie)}&tabla=${tabla}&id=${idActual}`));
        const resultado = await respuesta.json();

        if (resultado.exito) {
            const tieneCaja = resultado.existe_caja && resultado.caja_encontrada;
            const tieneEquipo = resultado.existe_equipo && resultado.equipo_encontrado;

            if (tipo === 'caja') {
                // Modo registro de Caja
                // 1. Notificar si YA TENEMOS EL EQUIPO (Laptop, PC o Monitor) de este número de serie
                if (tieneEquipo && avisoCaja) {
                    const eq = resultado.equipo_encontrado;
                    const esPcEquipo = (eq.tipo === 'PC' || eq.tabla_origen === 'computadoras');
                    const esMonitorEquipo = (eq.tipo === 'monitor' || eq.tabla_origen === 'monitores');
                    let tipoTexto = '💻 Laptop';
                    let iconoTipo = '💻';
                    if (esPcEquipo) {
                        tipoTexto = '🖥️ PC (Escritorio)';
                        iconoTipo = '🖥️';
                    } else if (esMonitorEquipo) {
                        tipoTexto = '🖥️ Monitor';
                        iconoTipo = '🖥️';
                    }
                    const ubiTexto = eq.ubicacion ? ` en <b>${escaparHtml(eq.ubicacion)}</b>` : '';

                    avisoCaja.style.display = 'flex';
                    avisoCaja.innerHTML = `<span>${iconoTipo}</span> <span><strong>¡Tenemos el equipo de esta caja!</strong> Registrado como <b>${tipoTexto}</b> marca <b>${escaparHtml(eq.marca)}</b>${ubiTexto}.</span>`;

                    // Autocompletar Tipo de Caja según el tipo de equipo detectado
                    const selTipoCaja = document.getElementById('tipoCajaSelect');
                    if (selTipoCaja) {
                        selTipoCaja.value = esMonitorEquipo ? 'monitor' : (esPcEquipo ? 'PC' : 'laptop');
                    }

                    // Actualizar y autocompletar marcas para el tipo detectado
                    actualizarOpcionesMarcaModal(eq.marca || '');

                    // Autocompletar Ubicación según la del equipo
                    const selectUbicacion = document.getElementById('ubicacion');
                    if (selectUbicacion && eq.ubicacion) {
                        selectUbicacion.value = eq.ubicacion;
                    }
                } else if (avisoCaja) {
                    avisoCaja.style.display = 'none';
                    avisoCaja.innerHTML = '';
                }

                // 2. Notificar si la caja ya está registrada (duplicada)
                if (tieneCaja) {
                    const c = resultado.caja_encontrada;
                    const tipoTexto = (c.tipo_caja === 'PC') ? '🖥️ PC' : (c.tipo_caja === 'monitor' ? '🖥️ Monitor' : '💻 Laptop');
                    inputNoSerie.classList.add('input-serie-error');
                    if (avisoDuplicado) {
                        avisoDuplicado.style.display = 'flex';
                        avisoDuplicado.innerHTML = `<span>⚠️</span> <span><strong>¡Esta caja ya la tenemos!</strong> Ya se encuentra registrada como <b>${escaparHtml(c.marca)}</b> (${tipoTexto}).</span>`;
                    }
                } else {
                    inputNoSerie.classList.remove('input-serie-error');
                    if (avisoDuplicado) {
                        avisoDuplicado.style.display = 'none';
                        avisoDuplicado.innerHTML = '';
                    }
                }
            } else {
                // Modo registro de Equipo (Laptop, PC o Monitor)
                // 1. Notificar si YA TENEMOS LA CAJA de este número de serie
                if (tieneCaja && avisoCaja) {
                    const c = resultado.caja_encontrada;
                    const tipoTexto = (c.tipo_caja === 'PC') ? '🖥️ PC' : (c.tipo_caja === 'monitor' ? '🖥️ Monitor' : '💻 Laptop');
                    const comentarioTexto = c.comentario && c.comentario.trim() !== '' ? ` — <em>${escaparHtml(c.comentario)}</em>` : '';
                    avisoCaja.style.display = 'flex';
                    avisoCaja.innerHTML = `<span>📦</span> <span><strong>¡Tenemos la caja de este equipo!</strong> Registrada como <b>${escaparHtml(c.marca)}</b> (${tipoTexto})${comentarioTexto}.</span>`;

                    // Marcar automáticamente el checkbox de "Tiene Caja" y desmarcar caja sin serie
                    const checkCaja = document.getElementById('cajaCheck');
                    if (checkCaja) {
                        checkCaja.checked = true;
                    }
                    const checkCajaSinSerie = document.getElementById('cajaSinSerieCheck');
                    if (checkCajaSinSerie) {
                        checkCajaSinSerie.checked = false;
                    }
                } else if (avisoCaja) {
                    avisoCaja.style.display = 'none';
                    avisoCaja.innerHTML = '';
                }

                // 2. Notificar si el equipo ya está registrado en laptops, computadoras o monitores
                if (tieneEquipo) {
                    const eq = resultado.equipo_encontrado;
                    inputNoSerie.classList.add('input-serie-error');
                    if (avisoDuplicado) {
                        avisoDuplicado.style.display = 'flex';
                        avisoDuplicado.innerHTML = `<span>⚠️</span> <span><strong>¡Este equipo ya está registrado!</strong> Serie registrada en <b>${escaparHtml(eq.marca)}</b> (${eq.tipo}).</span>`;
                    }
                } else {
                    inputNoSerie.classList.remove('input-serie-error');
                    if (avisoDuplicado) { avisoDuplicado.style.display = 'none'; avisoDuplicado.innerHTML = ''; }
                }
            }
        }
    } catch (err) {
        console.error('Error al verificar serie:', err);
    }
}

// --------------------------------------------------------------------------
// MODAL DE REGISTRO COMPACTO Y RESPONSIVO
// --------------------------------------------------------------------------
function abrirModalEquipo(datos = null) {
    const modal = document.getElementById('modalEquipo');
    const form = document.getElementById('formEquipo');
    const titulo = document.getElementById('modalTitulo');
    const avisoDuplicado = document.getElementById('avisoSerieDuplicado');
    const avisoCaja = document.getElementById('avisoCajaEncontrada');
    const inputNoSerie = document.getElementById('noSerie');

    form.reset();

    // Limpiar alertas de duplicado y caja encontrada previas
    if (avisoDuplicado) {
        avisoDuplicado.style.display = 'none';
        avisoDuplicado.innerHTML = '';
    }
    if (avisoCaja) {
        avisoCaja.style.display = 'none';
        avisoCaja.innerHTML = '';
    }
    if (inputNoSerie) {
        inputNoSerie.classList.remove('input-serie-error');
    }

    if (datos) {
        const esCaja = datos.tipo === 'caja' || datos.tabla_origen === 'cajas';
        const esMonitor = datos.tipo === 'monitor' || datos.tabla_origen === 'monitores';
        const esPC = datos.tipo === 'PC' || datos.tabla_origen === 'computadoras';

        if (esCaja) {
            titulo.textContent = 'Editar Caja';
        } else if (esMonitor) {
            titulo.textContent = 'Editar Monitor';
        } else if (esPC) {
            titulo.textContent = 'Editar PC';
        } else {
            titulo.textContent = 'Editar Laptop';
        }

        document.getElementById('equipoId').value = datos.id || '';
        document.getElementById('formularioTipo').value = datos.tipo || (esCaja ? 'caja' : (esMonitor ? 'monitor' : (esPC ? 'PC' : 'laptop')));

        const selTipoCaja = document.getElementById('tipoCajaSelect');
        if (selTipoCaja) {
            selTipoCaja.value = datos.tipo_caja || 'laptop';
        }

        document.getElementById('noSerie').value = datos.no_serie || '';
        actualizarOpcionesMarcaModal(datos.marca || '');

        const selUbicacion = document.getElementById('ubicacion');
        if (selUbicacion) {
            selUbicacion.value = datos.ubicacion || 'Versalles';
        }

        document.getElementById('formateadoPor').value = datos.formateado_por || '';
        document.getElementById('observaciones').value = datos.observaciones || '';
        document.getElementById('formateadaSelect').value = datos.formateada || 'pendiente';
        document.getElementById('cajaCheck').checked = datos.caja == 1;
        const chkCajaSinSerie = document.getElementById('cajaSinSerieCheck');
        if (chkCajaSinSerie) chkCajaSinSerie.checked = datos.caja_sin_serie == 1;
        document.getElementById('cargadorSelect').value = datos.cargador || 'no';
        document.getElementById('cablesCheck').checked = datos.cables == 1;
        document.getElementById('mouseCheck').checked = datos.mouse == 1;
        const chkTeclado = document.getElementById('tecladoCheck');
        if (chkTeclado) chkTeclado.checked = datos.teclado == 1;
        document.getElementById('listaCheck').checked = datos.lista == 1;
        document.getElementById('entregadaSelect').value = datos.entregada || 'pendiente';
        const inputPrestadoA = document.getElementById('prestadoA');
        if (inputPrestadoA) inputPrestadoA.value = datos.prestado_a || '';
    } else {
        document.getElementById('equipoId').value = '';
        const inputPrestadoA = document.getElementById('prestadoA');
        if (inputPrestadoA) inputPrestadoA.value = '';

        const selUbicacion = document.getElementById('ubicacion');
        if (selUbicacion) selUbicacion.value = 'Versalles';

        const selTipoCaja = document.getElementById('tipoCajaSelect');
        if (selTipoCaja) selTipoCaja.value = 'laptop';

        if (estadoApp.pestanaActiva === 'cajas') {
            titulo.textContent = 'Registrar Caja';
            document.getElementById('formularioTipo').value = 'caja';
        } else if (estadoApp.pestanaActiva === 'monitores') {
            titulo.textContent = 'Registrar Monitor';
            document.getElementById('formularioTipo').value = 'monitor';
        } else if (estadoApp.pestanaActiva === 'computadoras') {
            titulo.textContent = 'Registrar PC';
            document.getElementById('formularioTipo').value = 'PC';
        } else {
            titulo.textContent = 'Registrar Laptop';
            document.getElementById('formularioTipo').value = 'laptop';
        }
        document.getElementById('cablesCheck').checked = false;
        document.getElementById('cajaCheck').checked = false;
        const chkCajaSinSerie = document.getElementById('cajaSinSerieCheck');
        if (chkCajaSinSerie) chkCajaSinSerie.checked = false;
        document.getElementById('mouseCheck').checked = false;
        const chkTeclado = document.getElementById('tecladoCheck');
        if (chkTeclado) chkTeclado.checked = false;
        actualizarOpcionesMarcaModal('');

        // Autorellenar prefijo según la marca inicial elegida por defecto
        const selectMarca = document.getElementById('marca');
        if (selectMarca && selectMarca.value) {
            aplicarPrefijoSeriePorMarca(selectMarca.value);
        }
    }

    alternarCamposPorTipo();
    modal.classList.add('activo');
}

function cerrarModalEquipo() {
    document.getElementById('modalEquipo').classList.remove('activo');
}

function abrirModalAdvertenciaFormateo() {
    const modalAdv = document.getElementById('modalAdvertenciaFormateo');
    if (modalAdv) modalAdv.classList.add('activo');
}

function cerrarModalAdvertenciaFormateo() {
    const modalAdv = document.getElementById('modalAdvertenciaFormateo');
    if (modalAdv) modalAdv.classList.remove('activo');
}

function alternarCamposPorTipo() {
    const tipo = document.getElementById('formularioTipo').value;
    const campoTipoCaja = document.getElementById('contenedorCampoTipoCaja');
    const campoCargador = document.getElementById('contenedorCampoCargador');
    const campoCables = document.getElementById('contenedorCampoCables');
    const campoMouse = document.getElementById('contenedorCampoMouse');
    const campoTeclado = document.getElementById('contenedorCampoTeclado');
    const campoTecnico = document.getElementById('contenedorCampoTecnico');
    const campoFormateo = document.getElementById('contenedorCampoFormateo');
    const campoEntrega = document.getElementById('contenedorCampoEntrega');
    const campoPrestadoA = document.getElementById('contenedorCampoPrestadoA');
    const contenedorCheckboxes = document.getElementById('contenedorCheckboxesHardware');
    const labelObs = document.getElementById('labelObservaciones');
    const inputObs = document.getElementById('observaciones');

    if (tipo === 'caja') {
        // En cajas únicamente se registra tipo_caja, número de serie, marca, comentario y timestamp
        if (campoTipoCaja) campoTipoCaja.style.display = '';
        if (campoTecnico) campoTecnico.style.display = 'none';
        if (campoFormateo) campoFormateo.style.display = 'none';
        if (campoCargador) campoCargador.style.display = 'none';
        if (campoEntrega) campoEntrega.style.display = 'none';
        if (campoPrestadoA) campoPrestadoA.style.display = 'none';
        if (contenedorCheckboxes) contenedorCheckboxes.style.display = 'none';
        if (campoTeclado) campoTeclado.style.display = 'none';
        if (labelObs) labelObs.textContent = 'Comentario (Opcional)';
        if (inputObs) inputObs.placeholder = 'Ej. Caja maltratada, sin unicel, nueva en bolsa...';
    } else if (tipo === 'monitor') {
        // En monitores: no_serie, marca, ubicación, cables, caja, lista y observaciones
        if (campoTipoCaja) campoTipoCaja.style.display = 'none';
        if (campoTecnico) campoTecnico.style.display = 'none';
        if (campoFormateo) campoFormateo.style.display = 'none';
        if (campoCargador) campoCargador.style.display = 'none';
        if (campoEntrega) campoEntrega.style.display = 'none';
        if (campoPrestadoA) campoPrestadoA.style.display = '';
        if (contenedorCheckboxes) contenedorCheckboxes.style.display = '';
        if (campoCables) campoCables.style.display = 'flex';
        if (campoMouse) campoMouse.style.display = 'none';
        if (campoTeclado) campoTeclado.style.display = 'none';
        if (labelObs) labelObs.textContent = 'Observaciones (Opcional)';
        if (inputObs) inputObs.placeholder = 'Ej. Sin base, pantalla rayada, cable VGA, etc.';
    } else {
        if (campoTipoCaja) campoTipoCaja.style.display = 'none';
        if (campoPrestadoA) campoPrestadoA.style.display = '';

        if (tipo === 'PC') {
            if (campoTecnico) campoTecnico.style.display = '';
            if (campoFormateo) campoFormateo.style.display = '';
            if (campoCargador) campoCargador.style.display = 'none';
            if (campoEntrega) campoEntrega.style.display = '';
            if (contenedorCheckboxes) contenedorCheckboxes.style.display = '';
            if (campoCables) campoCables.style.display = 'flex';
            if (campoMouse) campoMouse.style.display = 'flex';
            if (campoTeclado) campoTeclado.style.display = 'flex';
            if (labelObs) labelObs.textContent = 'Observaciones (Opcional)';
            if (inputObs) inputObs.placeholder = 'Ej. FALTA ESPUMA SUPERIOR, SIN MOUSE, etc.';
        } else {
            // Laptop
            if (campoTecnico) campoTecnico.style.display = '';
            if (campoFormateo) campoFormateo.style.display = '';
            if (campoCargador) campoCargador.style.display = '';
            if (campoEntrega) campoEntrega.style.display = '';
            if (contenedorCheckboxes) contenedorCheckboxes.style.display = '';
            if (campoCables) campoCables.style.display = 'none';
            if (campoMouse) campoMouse.style.display = 'none';
            if (campoTeclado) campoTeclado.style.display = 'none';
            if (labelObs) labelObs.textContent = 'Observaciones (Opcional)';
            if (inputObs) inputObs.placeholder = 'Ej. CARGADOR GENÉRICO, PANTALLA RAYADA, etc.';
        }
    }

    actualizarOpcionesMarcaModal();
}

// Guardar equipo o caja (Manejo de claves UNIQUE y serie duplicada)
async function guardarEquipo(e) {
    e.preventDefault();

    const tipo = document.getElementById('formularioTipo').value;
    const datos = {
        id: document.getElementById('equipoId').value,
        tipo: tipo,
        no_serie: document.getElementById('noSerie').value.trim(),
        marca: document.getElementById('marca').value.trim(),
        ubicacion: document.getElementById('ubicacion').value.trim() || 'Versalles',
        observaciones: document.getElementById('observaciones').value.trim()
    };

    const chkCajaSinSerie = document.getElementById('cajaSinSerieCheck');
    const esCajaSinSerie = chkCajaSinSerie && chkCajaSinSerie.checked ? 1 : 0;

    if (tipo === 'caja') {
        const selTipoCaja = document.getElementById('tipoCajaSelect');
        datos.tipo_caja = selTipoCaja ? selTipoCaja.value : 'laptop';
    } else if (tipo === 'monitor') {
        datos.caja = (document.getElementById('cajaCheck').checked || esCajaSinSerie) ? 1 : 0;
        datos.caja_sin_serie = esCajaSinSerie;
        datos.cables = document.getElementById('cablesCheck').checked ? 1 : 0;
        datos.lista = document.getElementById('listaCheck').checked ? 1 : 0;
    } else {
        datos.formateado_por = document.getElementById('formateadoPor').value.trim();
        datos.formateada = document.getElementById('formateadaSelect').value;
        datos.caja = (document.getElementById('cajaCheck').checked || esCajaSinSerie) ? 1 : 0;
        datos.caja_sin_serie = esCajaSinSerie;
        datos.cargador = document.getElementById('cargadorSelect').value;
        datos.cables = document.getElementById('cablesCheck').checked ? 1 : 0;
        datos.mouse = document.getElementById('mouseCheck').checked ? 1 : 0;
        const chkTeclado = document.getElementById('tecladoCheck');
        datos.teclado = chkTeclado ? (chkTeclado.checked ? 1 : 0) : 0;
        datos.lista = document.getElementById('listaCheck').checked ? 1 : 0;
        datos.entregada = document.getElementById('entregadaSelect').value;
    }

    if (tipo !== 'caja') {
        const inputPrestadoA = document.getElementById('prestadoA');
        datos.prestado_a = inputPrestadoA ? inputPrestadoA.value.trim() : '';
    }

    if (!datos.no_serie || !datos.marca) {
        mostrarToast('Número de serie y marca son campos requeridos.', 'error');
        return;
    }

    // Validación: No se puede marcar como listo si no ha sido formateado (solo laptops y PCs)
    if (tipo !== 'caja' && tipo !== 'monitor' && datos.lista == 1 && datos.formateada !== 'formateada') {
        abrirModalAdvertenciaFormateo();
        return;
    }

    try {
        const respuesta = await fetchConCsrf(obtenerUrlApi('accion=guardar'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        const resultado = await respuesta.json();
        if (resultado.exito) {
            mostrarToast(resultado.mensaje, 'exito');
            cerrarModalEquipo();
            cargarResumen();
            cargarEquipos();
        } else {
            // Manejo de error de clave duplicada
            mostrarToast(resultado.mensaje || 'Error al guardar', 'error');

            const inputNoSerie = document.getElementById('noSerie');
            const aviso = document.getElementById('avisoSerieDuplicado');
            if (inputNoSerie) {
                inputNoSerie.classList.add('input-serie-error');
                inputNoSerie.focus();
            }
            if (aviso) {
                aviso.style.display = 'flex';
                aviso.innerHTML = `<span>⚠️</span> <span><strong>Atención:</strong> ${escaparHtml(resultado.mensaje)}</span>`;
            }
        }
    } catch (error) {
        console.error('Error al guardar equipo:', error);
        mostrarToast('Error al comunicarse con el servidor.', 'error');
    }
}

// Cargar datos y abrir para editar
async function editarEquipo(tabla, id) {
    try {
        const respuesta = await fetchConCsrf(obtenerUrlApi(`accion=obtener&tabla=${tabla}&id=${id}`));
        const resultado = await respuesta.json();
        if (resultado.exito) {
            abrirModalEquipo(resultado.datos);
        } else {
            mostrarToast(resultado.mensaje, 'error');
        }
    } catch (error) {
        console.error('Error al obtener equipo:', error);
        mostrarToast('Error al consultar datos del equipo', 'error');
    }
}

// Confirmar y eliminar
function confirmarEliminar(tabla, id, noSerie) {
    if (confirm(`¿Estás seguro de que deseas eliminar el equipo con serie "${noSerie}"?`)) {
        eliminarEquipo(tabla, id);
    }
}

async function eliminarEquipo(tabla, id) {
    try {
        const respuesta = await fetchConCsrf(obtenerUrlApi('accion=eliminar'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tabla, id })
        });
        const resultado = await respuesta.json();
        if (resultado.exito) {
            mostrarToast(resultado.mensaje, 'exito');
            cargarResumen();
            cargarEquipos();
        } else {
            mostrarToast(resultado.mensaje, 'error');
        }
    } catch (error) {
        console.error('Error al eliminar equipo:', error);
        mostrarToast('Error al intentar eliminar el equipo.', 'error');
    }
}

// Utilidad para mostrar Toasts
function mostrarToast(mensaje, tipo = 'exito') {
    const contenedor = document.getElementById('contenedorToast');
    const toast = document.createElement('div');
    toast.className = `toast ${tipo}`;
    toast.innerHTML = `
        <span>${tipo === 'exito' ? '✅' : '⚠️'}</span>
        <span>${escaparHtml(mensaje)}</span>
    `;
    contenedor.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

// Escapar caracteres para prevenir inyecciones HTML
function escaparHtml(cadena) {
    if (cadena === null || cadena === undefined) return '';
    return String(cadena)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ==========================================================================
// REDIMENSIÓN DE COLUMNAS DE LA TABLA (RESIZABLE COLUMNS) Y PERSISTENCIA
// ==========================================================================
const CLAVE_STORAGE_ANCHOS = 'anchos_columnas_computadoras';

function configurarRedimensionColumnas() {
    const tabla = document.getElementById('tablaEquipos');
    if (!tabla) return;

    // 1. Cargar anchos previamente guardados
    let anchosGuardados = {};
    try {
        const guardado = localStorage.getItem(CLAVE_STORAGE_ANCHOS);
        if (guardado) {
            anchosGuardados = JSON.parse(guardado);
        }
    } catch (e) {
        console.warn('Error al cargar anchos de columnas:', e);
    }

    const celdasEncabezado = tabla.querySelectorAll('thead tr:first-child th');
    celdasEncabezado.forEach((th) => {
        const colId = th.id;
        if (!colId) return;

        // Aplicar ancho guardado si existe
        if (anchosGuardados[colId]) {
            th.style.width = `${anchosGuardados[colId]}px`;
            th.style.minWidth = `${anchosGuardados[colId]}px`;
        }

        // Evitar duplicar resizer
        let resizer = th.querySelector('.resizer-columna');
        if (!resizer) {
            resizer = document.createElement('div');
            resizer.className = 'resizer-columna';
            resizer.title = 'Arrastrar para ajustar ancho';
            th.appendChild(resizer);
        }

        // Asignar evento de arrastre
        resizer.onmousedown = (e) => {
            e.preventDefault();
            e.stopPropagation();

            const inicioX = e.pageX;
            const anchoInicial = th.offsetWidth;
            resizer.classList.add('activo');
            document.body.classList.add('redimensionando-columna');

            const alMoverMouse = (eventoMov) => {
                const deltaX = eventoMov.pageX - inicioX;
                const nuevoAncho = Math.max(50, anchoInicial + deltaX);
                th.style.width = `${nuevoAncho}px`;
                th.style.minWidth = `${nuevoAncho}px`;
            };

            const alSoltarMouse = () => {
                document.removeEventListener('mousemove', alMoverMouse);
                document.removeEventListener('mouseup', alSoltarMouse);
                resizer.classList.remove('activo');
                document.body.classList.remove('redimensionando-columna');

                // Persistir nuevo ancho en localStorage
                try {
                    let actuales = {};
                    const dataGuardada = localStorage.getItem(CLAVE_STORAGE_ANCHOS);
                    if (dataGuardada) actuales = JSON.parse(dataGuardada);
                    actuales[colId] = th.offsetWidth;
                    localStorage.setItem(CLAVE_STORAGE_ANCHOS, JSON.stringify(actuales));
                } catch (err) {
                    console.error('Error al guardar ancho de columna:', err);
                }
                actualizarAnchoScrollHorizontal();
            };

            document.addEventListener('mousemove', alMoverMouse);
            document.addEventListener('mouseup', alSoltarMouse);
        };
    });
}

// Restablecer anchos por defecto
function restablecerAnchosColumnas() {
    try {
        localStorage.removeItem(CLAVE_STORAGE_ANCHOS);
    } catch (e) {
        console.warn('Error al limpiar anchos en localStorage:', e);
    }

    const tabla = document.getElementById('tablaEquipos');
    if (tabla) {
        const celdasEncabezado = tabla.querySelectorAll('thead tr:first-child th');
        celdasEncabezado.forEach(th => {
            th.style.width = '';
            th.style.minWidth = '';
        });
    }

    mostrarToast('Ancho de columnas restablecido al tamaño por defecto.', 'exito');
    actualizarAnchoScrollHorizontal();
}

// ==========================================================================
// CONTROL DE VISIBILIDAD DE KPIS (MOSTRAR / OCULTAR)
// ==========================================================================
const CLAVE_STORAGE_KPIS = 'gestion_kpis_visibles';

function configurarToggleKpis() {
    const boton = document.getElementById('btnAlternarKpis');
    const icono = document.getElementById('iconoAlternarKpis');
    const texto = document.getElementById('textoAlternarKpis');
    const seccionKpis = document.querySelector('.grid-estadisticas');
    if (!boton || !seccionKpis) return;

    // Cargar preferencia guardada (por defecto visible = true)
    let kpisVisibles = localStorage.getItem(CLAVE_STORAGE_KPIS) !== 'false';

    function aplicarEstado(visible) {
        if (visible) {
            seccionKpis.classList.remove('oculto');
            document.body.classList.remove('kpis-ocultas');
            boton.classList.remove('activo-oculto');
            if (icono) icono.textContent = '📊';
            if (texto) texto.textContent = 'Ocultar KPIs';
            boton.title = 'Ocultar indicadores rápidos (KPIs)';
        } else {
            seccionKpis.classList.add('oculto');
            document.body.classList.add('kpis-ocultas');
            boton.classList.add('activo-oculto');
            if (icono) icono.textContent = '👁️';
            if (texto) texto.textContent = 'Mostrar KPIs';
            boton.title = 'Mostrar indicadores rápidos (KPIs)';
        }
        actualizarAnchoScrollHorizontal();
    }

    // Aplicar estado inicial
    aplicarEstado(kpisVisibles);

    // Evento de clic
    boton.addEventListener('click', () => {
        kpisVisibles = !kpisVisibles;
        try {
            localStorage.setItem(CLAVE_STORAGE_KPIS, kpisVisibles ? 'true' : 'false');
        } catch (e) {
            console.warn('Error al guardar estado de KPIs:', e);
        }
        aplicarEstado(kpisVisibles);
    });
}

// ==========================================================================
// SCROLL HORIZONTAL RESPONSIVO Y BARRA SUPERIOR SINCRONIZADA
// ==========================================================================
function configurarScrollHorizontal() {
    const contenedorTablaScroll = document.getElementById('contenedorTablaScroll') || document.querySelector('.tabla-scroll');
    const tablaScrollSuperior = document.getElementById('tablaScrollSuperior');

    if (!contenedorTablaScroll || !tablaScrollSuperior) return;

    let fuenteScrollActiva = null;
    let temporizadorScroll = null;

    // Sincronización instantánea y fluida sin trabas (passive: true)
    tablaScrollSuperior.addEventListener('scroll', () => {
        if (fuenteScrollActiva === 'tabla') return;
        fuenteScrollActiva = 'superior';
        contenedorTablaScroll.scrollLeft = tablaScrollSuperior.scrollLeft;
        clearTimeout(temporizadorScroll);
        temporizadorScroll = setTimeout(() => {
            fuenteScrollActiva = null;
        }, 50);
    }, { passive: true });

    contenedorTablaScroll.addEventListener('scroll', () => {
        if (fuenteScrollActiva === 'superior') return;
        fuenteScrollActiva = 'tabla';
        tablaScrollSuperior.scrollLeft = contenedorTablaScroll.scrollLeft;
        clearTimeout(temporizadorScroll);
        temporizadorScroll = setTimeout(() => {
            fuenteScrollActiva = null;
        }, 50);
    }, { passive: true });

    // Actualizar riel cuando cambie el tamaño de ventana
    window.addEventListener('resize', () => {
        actualizarAnchoScrollHorizontal();
    });

    actualizarAnchoScrollHorizontal();
}

// Actualizar el ancho del riel superior según el ancho real de la tabla
function actualizarAnchoScrollHorizontal() {
    const contenedorTablaScroll = document.getElementById('contenedorTablaScroll') || document.querySelector('.tabla-scroll');
    const tablaScrollSuperior = document.getElementById('tablaScrollSuperior');
    const rielSuperior = document.getElementById('tablaScrollSuperiorRiel');
    const tabla = document.querySelector('.tabla-equipos');

    if (!contenedorTablaScroll || !tablaScrollSuperior || !rielSuperior || !tabla) return;

    const anchoTotal = Math.max(tabla.scrollWidth, tabla.offsetWidth);
    rielSuperior.style.width = `${anchoTotal}px`;

    // Si la tabla no requiere scroll horizontal porque cabe completa en pantalla, ocultar la barra superior
    if (anchoTotal <= contenedorTablaScroll.clientWidth + 2) {
        tablaScrollSuperior.style.display = 'none';
    } else {
        tablaScrollSuperior.style.display = 'block';
        if (Math.abs(tablaScrollSuperior.scrollLeft - contenedorTablaScroll.scrollLeft) > 1) {
            tablaScrollSuperior.scrollLeft = contenedorTablaScroll.scrollLeft;
        }
    }
}

// ==========================================================================
// FUNCIONES DE EDICIÓN MASIVA DE EQUIPOS
// ==========================================================================

// Abrir el modal de edición masiva
function abrirModalEdicionMasiva() {
    const cantidad = estadoApp.seleccionadosPaseLista.size;
    if (cantidad === 0) {
        mostrarToast('Selecciona al menos un equipo en la tabla para editar masivamente', 'advertencia');
        return;
    }

    const spanCantidad = document.getElementById('spanCantidadMasiva');
    if (spanCantidad) {
        spanCantidad.textContent = cantidad;
    }

    // Resetear formulario a sus valores por defecto (-- Sin cambios --)
    const form = document.getElementById('formEdicionMasiva');
    if (form) {
        form.reset();
    }

    const contenedorObsTexto = document.getElementById('contenedorTextoObsMasivo');
    if (contenedorObsTexto) {
        contenedorObsTexto.style.display = 'none';
    }

    const contenedorPrestamoTexto = document.getElementById('contenedorTextoPrestamoMasivo');
    if (contenedorPrestamoTexto) {
        contenedorPrestamoTexto.style.display = 'none';
    }

    const modal = document.getElementById('modalEdicionMasiva');
    if (modal) {
        modal.classList.add('activo');
    }
}

// Cerrar el modal de edición masiva
function cerrarModalEdicionMasiva() {
    const modal = document.getElementById('modalEdicionMasiva');
    if (modal) {
        modal.classList.remove('activo');
    }
}

// Alternar visibilidad del textarea de observaciones según el modo seleccionado
function alternarCampoTextoObsMasivo(modo) {
    const contenedor = document.getElementById('contenedorTextoObsMasivo');
    const inputTexto = document.getElementById('masivoObsTexto');
    if (!contenedor) return;

    if (modo === 'reemplazar' || modo === 'agregar') {
        contenedor.style.display = 'block';
        if (inputTexto) inputTexto.focus();
    } else {
        contenedor.style.display = 'none';
        if (inputTexto) inputTexto.value = '';
    }
}

// Alternar visibilidad del input de asignación de préstamo según el modo seleccionado
function alternarCampoTextoPrestamoMasivo(modo) {
    const contenedor = document.getElementById('contenedorTextoPrestamoMasivo');
    const inputTexto = document.getElementById('masivoPrestadoANombre');
    if (!contenedor) return;

    if (modo === 'asignar') {
        contenedor.style.display = 'block';
        if (inputTexto) inputTexto.focus();
    } else {
        contenedor.style.display = 'none';
        if (inputTexto) inputTexto.value = '';
    }
}

// Enviar los cambios masivos seleccionados al backend
async function guardarEdicionMasiva(e) {
    e.preventDefault();

    const elementos = Array.from(estadoApp.seleccionadosPaseLista);
    if (elementos.length === 0) {
        mostrarToast('No hay equipos seleccionados para modificar', 'error');
        return;
    }

    // Recopilar campos
    const cambios = {};

    const ubi = document.getElementById('masivoUbicacion') ? document.getElementById('masivoUbicacion').value : '__sin_cambio__';
    if (ubi !== '__sin_cambio__') cambios.ubicacion = ubi;

    const marca = document.getElementById('masivoMarca') ? document.getElementById('masivoMarca').value : '__sin_cambio__';
    if (marca !== '__sin_cambio__') cambios.marca = marca;

    const tec = document.getElementById('masivoTecnico') ? document.getElementById('masivoTecnico').value : '__sin_cambio__';
    if (tec !== '__sin_cambio__') cambios.formateado_por = tec;

    const ent = document.getElementById('masivoEntregada') ? document.getElementById('masivoEntregada').value : '__sin_cambio__';
    if (ent !== '__sin_cambio__') cambios.entregada = ent;

    const form = document.getElementById('masivoFormateada') ? document.getElementById('masivoFormateada').value : '__sin_cambio__';
    if (form !== '__sin_cambio__') cambios.formateada = form;

    const lista = document.getElementById('masivoLista') ? document.getElementById('masivoLista').value : '__sin_cambio__';
    if (lista !== '__sin_cambio__') cambios.lista = lista;

    const caja = document.getElementById('masivoCaja') ? document.getElementById('masivoCaja').value : '__sin_cambio__';
    if (caja !== '__sin_cambio__') cambios.caja = caja;

    const carg = document.getElementById('masivoCargador') ? document.getElementById('masivoCargador').value : '__sin_cambio__';
    if (carg !== '__sin_cambio__') cambios.cargador = carg;

    const cables = document.getElementById('masivoCables') ? document.getElementById('masivoCables').value : '__sin_cambio__';
    if (cables !== '__sin_cambio__') cambios.cables = cables;

    const mouse = document.getElementById('masivoMouse') ? document.getElementById('masivoMouse').value : '__sin_cambio__';
    if (mouse !== '__sin_cambio__') cambios.mouse = mouse;

    const teclado = document.getElementById('masivoTeclado') ? document.getElementById('masivoTeclado').value : '__sin_cambio__';
    if (teclado !== '__sin_cambio__') cambios.teclado = teclado;

    const obsModo = document.getElementById('masivoObsModo') ? document.getElementById('masivoObsModo').value : '__sin_cambio__';
    if (obsModo !== '__sin_cambio__') {
        cambios.observaciones_modo = obsModo;
        cambios.observaciones_texto = document.getElementById('masivoObsTexto') ? document.getElementById('masivoObsTexto').value.trim() : '';
    }

    const prestadoModo = document.getElementById('masivoPrestadoAModo') ? document.getElementById('masivoPrestadoAModo').value : '__sin_cambio__';
    if (prestadoModo === 'asignar') {
        const nombrePrestado = document.getElementById('masivoPrestadoANombre') ? document.getElementById('masivoPrestadoANombre').value.trim() : '';
        if (nombrePrestado !== '') {
            cambios.prestado_a = nombrePrestado;
        }
    } else if (prestadoModo === 'limpiar') {
        cambios.prestado_a = '__limpiar__';
    }

    const cantidadCambios = Object.keys(cambios).length;
    if (cantidadCambios === 0) {
        mostrarToast('No has seleccionado ningún cambio. Selecciona un valor para al menos un campo.', 'advertencia');
        return;
    }

    const btnSubmit = document.getElementById('btnConfirmarEdicionMasiva');
    const textoOriginalBtn = btnSubmit ? btnSubmit.textContent : '';

    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.textContent = '⏳ Aplicando cambios...';
    }

    try {
        const respuesta = await fetchConCsrf(obtenerUrlApi('accion=edicion_masiva'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                elementos: elementos,
                cambios: cambios
            })
        });

        const resultado = await respuesta.json();
        if (resultado.exito) {
            mostrarToast(resultado.mensaje, 'exito');
            cerrarModalEdicionMasiva();
            desmarcarTodoPaseLista();
            cargarResumen();
            cargarEquipos();
        } else {
            mostrarToast(resultado.mensaje || 'Error al aplicar cambios masivos', 'error');
        }
    } catch (err) {
        console.error('Error en edición masiva:', err);
        mostrarToast('Error de comunicación con el servidor', 'error');
    } finally {
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.textContent = textoOriginalBtn;
        }
    }
}

// ==========================================================================
// FUNCIONES DE PRÉSTAMO DE EQUIPOS
// ==========================================================================

function abrirModalPrestamo(tabla, id, noSerie, prestadoActual = '') {
    const modal = document.getElementById('modalPrestamo');
    if (!modal) return;

    document.getElementById('prestamoTabla').value = tabla;
    document.getElementById('prestamoId').value = id;
    const inputTexto = document.getElementById('inputTextoPrestamo');
    if (inputTexto) {
        inputTexto.value = prestadoActual || '';
    }

    const titulo = document.getElementById('modalPrestamoTitulo');
    if (titulo) {
        titulo.innerHTML = `<span>🤝</span> Préstamo de Equipo (${escaparHtml(noSerie)})`;
    }

    const btnQuitar = document.getElementById('btnQuitarPrestamo');
    if (btnQuitar) {
        btnQuitar.style.display = (prestadoActual && prestadoActual.trim() !== '') ? 'inline-flex' : 'none';
    }

    modal.classList.add('activo');
    setTimeout(() => {
        if (inputTexto) inputTexto.focus();
    }, 100);
}

function cerrarModalPrestamo() {
    const modal = document.getElementById('modalPrestamo');
    if (modal) {
        modal.classList.remove('activo');
    }
}

async function guardarPrestamoDesdeModal(e) {
    if (e && e.preventDefault) e.preventDefault();

    const tabla = document.getElementById('prestamoTabla').value;
    const id = document.getElementById('prestamoId').value;
    const prestadoA = (document.getElementById('inputTextoPrestamo')?.value || '').trim();

    try {
        const respuesta = await fetchConCsrf(obtenerUrlApi('accion=actualizar_prestamo'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tabla, id, prestado_a: prestadoA })
        });
        const resultado = await respuesta.json();
        if (resultado.exito) {
            mostrarToast(resultado.mensaje || 'Préstamo actualizado', 'exito');
            cerrarModalPrestamo();
            cargarEquipos();
        } else {
            mostrarToast(resultado.mensaje || 'Error al actualizar préstamo', 'error');
        }
    } catch (err) {
        console.error('Error al guardar préstamo:', err);
        mostrarToast('Error al conectar con el servidor', 'error');
    }
}

async function quitarPrestamo() {
    const inputTexto = document.getElementById('inputTextoPrestamo');
    if (inputTexto) inputTexto.value = '';
    await guardarPrestamoDesdeModal();
}

// ==========================================================================
// GESTIÓN DE VISIBILIDAD DE COLUMNAS (MOSTRAR U OCULTAR)
// ==========================================================================

const CONFIG_COLUMNAS = [
    { clave: 'col-pase-lista', nombre: 'Pase de Lista' },
    { clave: 'col-serie', nombre: 'No. Serie' },
    { clave: 'col-marca', nombre: 'Marca' },
    { clave: 'col-ubicacion', nombre: 'Ubicación' },
    { clave: 'col-tecnico', nombre: 'Técnico' },
    { clave: 'col-obs', nombre: 'Observaciones / Comentario' },
    { clave: 'col-formateada', nombre: 'Formateada' },
    { clave: 'col-tiempo-formateo', nombre: 'Timestamp Formateo / Registro' },
    { clave: 'col-caja', nombre: 'Caja' },
    { clave: 'col-cargador', nombre: 'Cargador' },
    { clave: 'col-cables', nombre: 'Cables' },
    { clave: 'col-mouse', nombre: 'Mouse' },
    { clave: 'col-teclado', nombre: 'Teclado' },
    { clave: 'col-lista', nombre: 'Lista' },
    { clave: 'col-tipo', nombre: 'Tipo' },
    { clave: 'col-entregada', nombre: 'Entregada' },
    { clave: 'col-tiempo-entrega', nombre: 'Timestamp Entrega' },
    { clave: 'col-prestada', nombre: 'Préstamo' },
    { clave: 'col-acciones', nombre: 'Acciones' }
];

function obtenerColumnasOcultas() {
    try {
        const guardadas = localStorage.getItem('gc_columnas_ocultas');
        return guardadas ? JSON.parse(guardadas) : [];
    } catch (e) {
        return [];
    }
}

function guardarColumnasOcultas(ocultas) {
    try {
        localStorage.setItem('gc_columnas_ocultas', JSON.stringify(ocultas));
    } catch (e) {
        console.error('Error al guardar columnas ocultas:', e);
    }
}

function aplicarEstilosColumnasOcultas() {
    const ocultas = obtenerColumnasOcultas();
    let estilo = document.getElementById('estiloColumnasVisibles');
    if (!estilo) {
        estilo = document.createElement('style');
        estilo.id = 'estiloColumnasVisibles';
        document.head.appendChild(estilo);
    }
    if (!ocultas || ocultas.length === 0) {
        estilo.textContent = '';
        return;
    }
    const reglas = ocultas.map(clave => `.tabla-equipos .${clave} { display: none !important; }`).join('\n');
    estilo.textContent = reglas;
}

function alternarMenuColumnas(event) {
    if (event) event.stopPropagation();
    const menu = document.getElementById('menuSelectorColumnas');
    if (!menu) return;
    const estaVisible = menu.style.display !== 'none';
    if (estaVisible) {
        menu.style.display = 'none';
    } else {
        renderizarListaSelectorColumnas();
        menu.style.display = 'block';
    }
}

function renderizarListaSelectorColumnas() {
    const contenedor = document.getElementById('listaColumnasVisibles');
    if (!contenedor) return;

    const ocultas = new Set(obtenerColumnasOcultas());

    contenedor.innerHTML = CONFIG_COLUMNAS.map(col => {
        const estaVisible = !ocultas.has(col.clave);
        return `
            <label class="item-columna-toggle">
                <input type="checkbox" ${estaVisible ? 'checked' : ''} onchange="cambiarVisibilidadColumna('${col.clave}', this.checked)">
                <span>${col.nombre}</span>
            </label>
        `;
    }).join('');
}

function cambiarVisibilidadColumna(clave, visible) {
    let ocultas = obtenerColumnasOcultas();
    if (visible) {
        ocultas = ocultas.filter(c => c !== clave);
    } else {
        if (!ocultas.includes(clave)) {
            ocultas.push(clave);
        }
    }
    guardarColumnasOcultas(ocultas);
    aplicarEstilosColumnasOcultas();
}

function restablecerColumnasVisibles() {
    guardarColumnasOcultas([]);
    aplicarEstilosColumnasOcultas();
    renderizarListaSelectorColumnas();
    mostrarToast('Columnas visibles restablecidas', 'exito');
}
