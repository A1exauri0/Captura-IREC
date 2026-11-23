/**
 * =========================================================================
 * MÓDULO: CAPTURA DE MUESTREO WEB (STELLUM)
 * Réplica de las reglas y flujo de muestreo de validacionPdf (Form1.vb / ServicioReglas.vb)
 * Ningún archivo físico se mueve ni renombra; todo se registra en base de datos.
 * =========================================================================
 */

const CapturaMuestreoApp = (function () {
    // Catálogos de palabras reservadas (idénticos a ModuloCatalogo.vb)
    const CATALOGO_TOMOS = [
        'CREDITO_AGRICOLA',
        'DOCUMENTOS_PRIVADOS',
        'ACTOS_Y_CONTRATOS',
        'ADQUISICIONES_DE_GOBIERNO',
        'AUXILIAR',
        'AUXILIAR_ACTOS_Y_CONTRATOS',
        'AUXILIAR_CREDITO_AGRICOLA',
        'AUXILIAR_DOCUMENTOS_PRIVADOS',
        'CANCELACION'
    ];

    const CATALOGO_FRACC = [
        'FRACCIONAMIENTOS_Y_COLONIAS',
        'FRACCIONAMIENTO',
        'CORETT',
        'RAN',
        'INFONAVIT'
    ];

    // Estado interno del módulo
    let estado = {
        colaPdfs: [],
        indiceActual: 0,
        controlActual: null,
        delegacionClave: '01',
        delegacionNombre: 'Tuxtla Gutierrez',
        esModoCatastro: false,
        reglaActualId: 'rbRegla2',
        totalMuestra: 0,
        procesadosMuestra: 0,
        paginasPdfActual: 1,
        inicioCapturaPdf: null,
        historialCapturas: [],
        cajasBloques: [],
        bloqueActivoCatalogo: null,
        localidadesActuales: [],
        cacheLocalidadesPorMun: {},
        itemsCatalogoModal: [],
        indiceSeleccionCatalogo: 0,
        procesandoPeticion: false,
        ocultarNombreArchivo: true,
        esAdmin: false,
        verificacionCalidad: {
            color: null,
            orientacion: null,
            cronologia: null,
            legible: null
        }
    };

    // Referencias DOM
    let dom = {};

    /**
     * Inicializa el sistema de captura de muestreo.
     */
    function inicializar() {
        mapearElementosDom();
        configurarEventosInterfaz();
        configurarDivisorSplitter();
        cargarColaMuestreo();
    }

    /**
     * Obtiene las referencias a los elementos del DOM.
     */
    function mapearElementosDom() {
        dom = {
            visorPdf: document.getElementById('visorPdf'),
            indicadorCargaVisor: document.getElementById('indicadorCargaVisor'),
            lblIndicadorTexto: document.getElementById('lblIndicadorTexto'),
            divisorVisor: document.getElementById('divisorVisor'),
            panelInferior: document.getElementById('panelInferior'),
            panelReglasRpp: document.getElementById('panelReglasRpp'),
            panelReglasCatastro: document.getElementById('panelReglasCatastro'),
            btnAlternarModoReglas: document.getElementById('btnAlternarModoReglas'),
            lblTotalPdf: document.getElementById('lblTotalPdf'),
            lblRutaMuestreo: document.getElementById('lblRutaMuestreo'),
            lblArchivoOriginal: document.getElementById('lblArchivoOriginal'),
            btnToggleNombreAdmin: document.getElementById('btnToggleNombreAdmin'),
            lblEstado: document.getElementById('lblEstado'),
            pnlContenedorDinamico: document.getElementById('pnlContenedorDinamico'),
            txtNombreOculto: document.getElementById('txtNombreOculto'),
            btnRenombrar: document.getElementById('btnRenombrar'),
            btnVerInscripcion: document.getElementById('btnVerInscripcion'),
            barraVerificacionCalidad: document.getElementById('barraVerificacionCalidad'),
            btnLimpiar: document.getElementById('btnLimpiar'),
            lblCaptura: document.getElementById('lblCaptura'),
            lblPaginasInfo: document.getElementById('lblPaginasInfo'),
            btnPantallaCompleta: document.getElementById('btnPantallaCompleta'),
            btnModo: document.getElementById('btnModo'),
            btnRefrescar: document.getElementById('btnRefrescar'),
            btnDelegacionManual: document.getElementById('btnDelegacionManual'),
            // Modal Confirmación
            modalConfirmacion: document.getElementById('modalConfirmacion'),
            iconConfirmacion: document.getElementById('iconConfirmacion'),
            lblConfirmacionTitulo: document.getElementById('lblConfirmacionTitulo'),
            lblConfirmacionMensaje: document.getElementById('lblConfirmacionMensaje'),
            btnConfirmarAceptar: document.getElementById('btnConfirmarAceptar'),
            btnConfirmarCancelar: document.getElementById('btnConfirmarCancelar'),
            // Modal Procesando
            modalProcesando: document.getElementById('modalProcesando'),
            lblProcesandoTitulo: document.getElementById('lblProcesandoTitulo'),
            lblProcesandoMensaje: document.getElementById('lblProcesandoMensaje'),
            // Modal Buscador de Catálogo
            modalCatalogo: document.getElementById('modalCatalogo'),
            lblCatalogoTitulo: document.getElementById('lblCatalogoTitulo'),
            txtBuscarCatalogo: document.getElementById('txtBuscarCatalogo'),
            listaCatalogoItems: document.getElementById('listaCatalogoItems'),
            btnCerrarCatalogo: document.getElementById('btnCerrarCatalogo'),
            // Modal Selección Manual de Delegación
            modalDelegacion: document.getElementById('modalDelegacion'),
            listaDelegacionesModal: document.getElementById('listaDelegacionesModal'),
            btnCerrarDelegacion: document.getElementById('btnCerrarDelegacion')
        };
    }

    /**
     * Configura los eventos de botones, teclado y radio buttons.
     */
    function configurarEventosInterfaz() {
        // Radio buttons de reglas
        const radiosReglas = document.querySelectorAll('input[name="reglaMuestreo"]');
        radiosReglas.forEach(rb => {
            rb.addEventListener('change', () => {
                if (rb.checked) {
                    estado.reglaActualId = rb.id;
                    generarBloquesCaptura();
                }
            });
        });

        // Botón para alternar entre reglas RPP y Catastro manualmente si se requiere
        if (dom.btnAlternarModoReglas) {
            dom.btnAlternarModoReglas.addEventListener('click', () => {
                alternarModoCatastro(!estado.esModoCatastro);
            });
        }

        // Botón Renombrar / Validar
        dom.btnRenombrar.addEventListener('click', () => {
            ejecutarValidacionDobleCaptura();
        });

        // Botón Limpiar
        dom.btnLimpiar.addEventListener('click', () => {
            limpiarBloquesEditables();
        });

        // Botón Ojo (Ver Inscripción y registrar Inspeccionado)
        dom.btnVerInscripcion.addEventListener('click', () => {
            inspeccionarInscripcionActual();
        });

        // Botones del checklist de verificación visual (Color, Orientación, Cronología, Legible)
        const botonesCheck = document.querySelectorAll('.btn-check-calidad');
        botonesCheck.forEach(btn => {
            btn.addEventListener('click', () => {
                const campo = btn.dataset.campo;
                const valor = btn.dataset.valor === '1';
                seleccionarPuntoVerificacion(campo, valor);
            });
        });

        // Botón Refrescar cola
        dom.btnRefrescar.addEventListener('click', () => {
            cargarColaMuestreo();
        });

        // Botón Modo Claro / Oscuro
        dom.btnModo.addEventListener('click', () => {
            const htmlEl = document.documentElement;
            if (htmlEl.classList.contains('dark')) {
                htmlEl.classList.remove('dark');
                htmlEl.classList.add('light');
                dom.btnModo.innerHTML = '<i class="fa-solid fa-moon"></i> Oscuro';
            } else {
                htmlEl.classList.remove('light');
                htmlEl.classList.add('dark');
                dom.btnModo.innerHTML = '<i class="fa-solid fa-sun"></i> Claro';
            }
            actualizarColoresTemaBloques();
        });

        // Botón Pantalla Completa
        dom.btnPantallaCompleta.addEventListener('click', () => {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen().catch(() => {});
            }
        });

        document.addEventListener('fullscreenchange', () => {
            if (document.fullscreenElement) {
                dom.btnPantallaCompleta.innerHTML = '<i class="fa-solid fa-compress"></i> Ventana';
            } else {
                dom.btnPantallaCompleta.innerHTML = '<i class="fa-solid fa-expand"></i> Pantalla';
            }
        });

        // Selector manual de delegación (en caso de requerir cambiar prefijo Del)
        if (dom.btnDelegacionManual) {
            dom.btnDelegacionManual.addEventListener('click', abrirModalDelegacion);
        }
        if (dom.btnCerrarDelegacion) {
            dom.btnCerrarDelegacion.addEventListener('click', () => {
                dom.modalDelegacion.classList.add('hidden');
            });
        }

        // Eventos del modal de catálogo
        dom.btnCerrarCatalogo.addEventListener('click', cerrarBuscadorCatalogo);
        dom.txtBuscarCatalogo.addEventListener('input', () => {
            filtrarItemsCatalogo(dom.txtBuscarCatalogo.value);
        });
        dom.txtBuscarCatalogo.addEventListener('keydown', manejarTeclasBuscadorCatalogo);
    }

    /**
     * Permite redimensionar verticalmente el visor de PDF arrastrando la barra divisoria.
     */
    function configurarDivisorSplitter() {
        if (!dom.divisorVisor || !dom.panelInferior) return;
        let arrastrando = false;

        dom.divisorVisor.addEventListener('mousedown', (e) => {
            arrastrando = true;
            document.body.style.userSelect = 'none';
            if (dom.contenedorVisorPdf) dom.contenedorVisorPdf.style.pointerEvents = 'none';
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!arrastrando) return;
            const alturaVentana = window.innerHeight;
            const nuevaAlturaInferior = Math.max(210, Math.min(480, alturaVentana - e.clientY));
            dom.panelInferior.style.height = nuevaAlturaInferior + 'px';
        });

        document.addEventListener('mouseup', () => {
            if (arrastrando) {
                arrastrando = false;
                document.body.style.userSelect = '';
                if (dom.contenedorVisorPdf) dom.contenedorVisorPdf.style.pointerEvents = '';
            }
        });
    }

    /**
     * Alterna entre el panel de reglas RPP y el panel de reglas Catastro.
     */
    function alternarModoCatastro(activarCatastro, reglaEspecificaId = null) {
        estado.esModoCatastro = activarCatastro;

        if (activarCatastro) {
            dom.panelReglasRpp.classList.add('hidden');
            dom.panelReglasCatastro.classList.remove('hidden');
            dom.btnAlternarModoReglas.innerHTML = '<i class="fa-solid fa-building-columns mr-1"></i> Modo: Catastro (Cambiar a RPP)';
            const idDestino = reglaEspecificaId || 'rbCatastroUrbano';
            const rb = document.getElementById(idDestino);
            if (rb) {
                rb.checked = true;
                estado.reglaActualId = idDestino;
            }
        } else {
            dom.panelReglasCatastro.classList.add('hidden');
            dom.panelReglasRpp.classList.remove('hidden');
            dom.btnAlternarModoReglas.innerHTML = '<i class="fa-solid fa-map-location-dot mr-1"></i> Modo: RPP (Cambiar a Catastro)';
            const idDestino = reglaEspecificaId || 'rbRegla2';
            const rb = document.getElementById(idDestino);
            if (rb) {
                rb.checked = true;
                estado.reglaActualId = idDestino;
            }
        }

        generarBloquesCaptura();
    }

    // =========================================================================
    // CARGA DE COLA DE MUESTREO Y ARCHIVO PDF DESDE EL SERVIDOR
    // =========================================================================

    /**
     * Solicita al servidor el lote de muestreo activo y sus archivos pendientes.
     */
    async function cargarColaMuestreo() {
        mostrarModalProcesando('Cargando muestreo...', 'Consultando archivos pendientes en el servidor.');

        try {
            const respuesta = await fetch(CONFIG_MUESTREO.rutas.obtenerCola, {
                headers: { 'Accept': 'application/json' }
            });
            const datos = await respuesta.json();
            ocultarModalProcesando();

            if (!datos.ok || !datos.archivos || datos.archivos.length === 0) {
                estado.colaPdfs = [];
                estado.indiceActual = 0;
                ocultarIndicadorCargaVisor();
                if (dom.contenedorVisorPdf) dom.contenedorVisorPdf.innerHTML = '';
                dom.lblTotalPdf.innerText = 'Sin muestreos pendientes';
                dom.lblRutaMuestreo.innerText = '';
                dom.lblArchivoOriginal.innerText = '';
                dom.lblEstado.innerText = datos.mensaje || 'No hay muestreos pendientes disponibles en este momento.';
                dom.lblEstado.className = 'text-base font-semibold text-amber-600 dark:text-amber-400 min-h-[24px]';
                dom.btnRenombrar.disabled = true;
                dom.btnVerInscripcion.disabled = true;
                generarBloquesCaptura();
                return;
            }

            estado.colaPdfs = datos.archivos;
            estado.indiceActual = 0;
            estado.controlActual = datos.control;
            estado.delegacionClave = datos.delegacion_clave || '01';
            estado.delegacionNombre = datos.delegacion_nombre || 'Tuxtla Gutierrez';
            estado.totalMuestra = datos.total_muestra || datos.archivos.length;
            estado.procesadosMuestra = datos.procesados_muestra || 0;
            estado.ocultarNombreArchivo = Boolean(datos.ocultar_nombre);
            estado.esAdmin = Boolean(datos.es_admin);

            // Configurar botón de alternar nombre para administradores
            configurarBotonNombreAdmin();

            // Configurar modo Catastro o RPP y regla sugerida según ruta_origen (igual que Form1.vb)
            alternarModoCatastro(Boolean(datos.es_modo_catastro), datos.regla_sugerida);

            cargarPdfActual();
        } catch (error) {
            ocultarModalProcesando();
            mostrarAlerta('Error de conexión', 'No se pudo obtener la cola de muestreo del servidor.', 'fa-triangle-exclamation', 'text-red-500');
        }
    }

    /**
     * Muestra el botón de alternar visibilidad de nombre solo si el usuario es administrador.
     */
    function configurarBotonNombreAdmin() {
        if (!dom.btnToggleNombreAdmin) return;
        if (estado.esAdmin) {
            dom.btnToggleNombreAdmin.classList.remove('hidden');
            actualizarTextoBotonNombreAdmin();
            if (!dom.btnToggleNombreAdmin.dataset.eventoAsignado) {
                dom.btnToggleNombreAdmin.dataset.eventoAsignado = '1';
                dom.btnToggleNombreAdmin.addEventListener('click', () => {
                    estado.ocultarNombreArchivo = !estado.ocultarNombreArchivo;
                    actualizarTextoBotonNombreAdmin();
                    actualizarTextosEncabezado();
                });
            }
        } else {
            dom.btnToggleNombreAdmin.classList.add('hidden');
        }
    }

    function actualizarTextoBotonNombreAdmin() {
        if (!dom.btnToggleNombreAdmin) return;
        if (estado.ocultarNombreArchivo) {
            dom.btnToggleNombreAdmin.innerHTML = '<i class="fa-solid fa-eye-slash mr-1 text-amber-500"></i><span>Modo capturista (Oculto)</span>';
            dom.btnToggleNombreAdmin.title = 'Nombres y rutas ocultas (vista capturista). Clic para mostrar.';
        } else {
            dom.btnToggleNombreAdmin.innerHTML = '<i class="fa-solid fa-eye mr-1 text-emerald-500"></i><span>Modo admin (Visible)</span>';
            dom.btnToggleNombreAdmin.title = 'Nombres y rutas visibles (vista admin). Clic para ocultar.';
        }
    }

    function actualizarEtiquetaArchivoActual() {
        if (!dom.lblArchivoOriginal) return;
        const itemActual = estado.colaPdfs[estado.indiceActual];
        if (!itemActual) {
            dom.lblArchivoOriginal.innerText = '';
            return;
        }
        if (estado.ocultarNombreArchivo) {
            dom.lblArchivoOriginal.innerText = '';
        } else {
            dom.lblArchivoOriginal.innerText = `Archivo: ${itemActual.nombre_archivo}`;
        }
    }

    /**
     * Actualiza el encabezado superior del muestreo.
     * Para capturistas solo muestra "Muestreo: X / Y" y oculta la ruta y delegación.
     */
    function actualizarTextosEncabezado() {
        const avanceActual = Math.min(estado.totalMuestra, estado.procesadosMuestra + 1);
        const nombreDirectorio = estado.controlActual ? (estado.controlActual.directorio || '') : '';

        if (estado.ocultarNombreArchivo) {
            dom.lblTotalPdf.innerText = `Muestreo: ${avanceActual} / ${estado.totalMuestra}`;
            if (dom.lblRutaMuestreo) {
                dom.lblRutaMuestreo.innerText = '';
                dom.lblRutaMuestreo.classList.add('hidden');
            }
        } else {
            dom.lblTotalPdf.innerText = `${estado.delegacionNombre} (${estado.delegacionClave}) - ${nombreDirectorio} - Muestreo: ${avanceActual} / ${estado.totalMuestra}`;
            if (dom.lblRutaMuestreo) {
                dom.lblRutaMuestreo.innerText = estado.controlActual ? (estado.controlActual.ruta_origen || '') : '';
                dom.lblRutaMuestreo.classList.remove('hidden');
            }
        }
        actualizarEtiquetaArchivoActual();
    }

    /**
     * Carga en el visor el PDF correspondiente al índice actual de la cola en el visor nativo.
     * Muestra un indicador sutil de carga no invasivo y permite visualizar de inmediato las páginas iniciales.
     */
    function cargarPdfActual() {
        if (estado.indiceActual >= estado.colaPdfs.length) {
            cargarColaMuestreo();
            return;
        }

        const itemActual = estado.colaPdfs[estado.indiceActual];
        const nombreOriginalLimpio = normalizarNombreOriginal(itemActual.nombre_limpio || '');

        // Configurar modo Catastro o RPP
        if (estado.esModoCatastro) {
            if (nombreOriginalLimpio.startsWith('1') && estado.reglaActualId !== 'rbCatastroRustico') {
                seleccionarRadioRegla('rbCatastroRustico');
            } else if (nombreOriginalLimpio.startsWith('2') && estado.reglaActualId !== 'rbCatastroSinEstudio') {
                seleccionarRadioRegla('rbCatastroSinEstudio');
            } else if (nombreOriginalLimpio.startsWith('0') && estado.reglaActualId !== 'rbCatastroUrbano') {
                seleccionarRadioRegla('rbCatastroUrbano');
            }
        } else {
            const matchDel = nombreOriginalLimpio.match(/^(\d{2})_/);
            if (matchDel) {
                const delEncontrada = CONFIG_MUESTREO.catalogoDelegaciones.find(d => d.clave === matchDel[1]);
                if (delEncontrada) {
                    estado.delegacionClave = delEncontrada.clave;
                    estado.delegacionNombre = delEncontrada.nombre;
                    actualizarBloqueDelegacionFijo();
                }
            }
        }

        // Reiniciar estado de captura actual
        estado.historialCapturas = [nombreOriginalLimpio];
        estado.inicioCapturaPdf = Date.now();
        estado.paginasPdfActual = itemActual.paginas ? parseInt(itemActual.paginas, 10) : 0;
        estado.conteoPaginasCompletado = Boolean(estado.paginasPdfActual > 0);
        estado.promesaConteoPaginas = null;

        // Actualizar etiquetas de estado y progreso respetando modo capturista/admin
        actualizarTextosEncabezado();

        dom.lblCaptura.innerText = 'Primera captura';
        dom.lblCaptura.className = 'text-lg font-bold text-gray-800 dark:text-gray-100';
        dom.btnVerInscripcion.disabled = false;

        reiniciarVerificacionCalidad();
        limpiarBloquesEditables();
        mostrarMensajeEjemploRegla();

        // Mostrar indicador claro de carga y atenuar visor para evitar confusión con el PDF anterior
        mostrarIndicadorCargaVisor('Cargando siguiente documento...');
        if (dom.visorPdf) {
            dom.visorPdf.style.opacity = '0.2';
        }

        // Construir URL del visor nativo de alta velocidad
        const flagOcultar = estado.ocultarNombreArchivo ? '1' : '0';
        const urlPdfBase = `${CONFIG_MUESTREO.rutas.verPdfBase}/${itemActual.id}?ocultar_nombre=${flagOcultar}&t=${Date.now()}`;
        const numPagina = (itemActual.pagina_muestra && parseInt(itemActual.pagina_muestra, 10) > 1) ? parseInt(itemActual.pagina_muestra, 10) : 1;
        const fragmento = `#page=${numPagina}&toolbar=1&navpanes=0&view=FitH`;

        if (dom.visorPdf) {
            let limpiado = false;
            let temporizadorSeguridad = null;

            const alCargar = () => {
                if (!limpiado) {
                    limpiado = true;
                    if (temporizadorSeguridad) {
                        clearTimeout(temporizadorSeguridad);
                    }
                    dom.visorPdf.removeEventListener('load', alCargar);
                    dom.visorPdf.style.opacity = '1';
                    ocultarIndicadorCargaVisor();
                    enfocarPrimerBloqueEditable();
                }
            };

            dom.visorPdf.addEventListener('load', alCargar);
            // Temporizador de seguridad en caso de fallo extremo de conexión (12s)
            temporizadorSeguridad = setTimeout(alCargar, 12000);
            dom.visorPdf.src = urlPdfBase + fragmento;
        }

        // Conteo de páginas y detección de DPI en segundo plano (si aún no están calculados)
        if (estado.conteoPaginasCompletado) {
            const textoMuestra = itemActual.pagina_muestra ? `Pág. muestra: ${itemActual.pagina_muestra} | ` : '';
            if (dom.lblPaginasInfo) {
                dom.lblPaginasInfo.innerText = `${textoMuestra}Total págs: ${estado.paginasPdfActual}`;
            }
            sincronizarBloquesConValidador();
            if (!itemActual.dpi) {
                estado.promesaConteoPaginas = obtenerInfoPaginasPdf(itemActual.id, itemActual.pagina_muestra);
            }
        } else {
            estado.promesaConteoPaginas = obtenerInfoPaginasPdf(itemActual.id, itemActual.pagina_muestra);
        }
    }

    function mostrarIndicadorCargaVisor(texto) {
        if (!dom.indicadorCargaVisor) return;
        if (dom.lblIndicadorTexto) dom.lblIndicadorTexto.innerText = texto || 'Cargando documento...';
        dom.indicadorCargaVisor.classList.remove('hidden');
    }

    function ocultarIndicadorCargaVisor() {
        if (!dom.indicadorCargaVisor) return;
        dom.indicadorCargaVisor.classList.add('hidden');
        if (dom.visorPdf) {
            dom.visorPdf.style.opacity = '1';
        }
    }

    /**
     * Calcula en segundo plano el total de páginas, DPI e integridad del PDF actual y actualiza el registro en la BD.
     */
    async function obtenerInfoPaginasPdf(idArchivo, paginaMuestra) {
        if (dom.lblPaginasInfo && !estado.conteoPaginasCompletado) {
            dom.lblPaginasInfo.innerText = 'Contando páginas...';
        }
        try {
            const controlador = new AbortController();
            const idTemporizador = setTimeout(() => controlador.abort(), 6000);
            const resp = await fetch(`${CONFIG_MUESTREO.rutas.infoPdfBase}/${idArchivo}`, {
                headers: { 'Accept': 'application/json' },
                signal: controlador.signal
            });
            clearTimeout(idTemporizador);
            const datos = await resp.json();

            // Si el PDF se detecta dañado automáticamente, enviarlo como dañado de inmediato sin capturar nada
            if (datos.danado === true) {
                const itemActual = estado.colaPdfs[estado.indiceActual];
                if (itemActual && itemActual.id === idArchivo && !estado.procesandoPeticion) {
                    await procesarPdfDanadoAutomatico(itemActual);
                    return false;
                }
            }

            if (datos.ok && datos.paginas > 0) {
                estado.paginasPdfActual = datos.paginas;
                estado.conteoPaginasCompletado = true;
                const itemEnCola = estado.colaPdfs.find(x => x.id === idArchivo);
                if (itemEnCola) {
                    itemEnCola.paginas = datos.paginas;
                    if (datos.dpi) {
                        itemEnCola.dpi = datos.dpi;
                    }
                }
            } else {
                estado.paginasPdfActual = parseInt(paginaMuestra, 10) || 1;
                estado.conteoPaginasCompletado = true;
            }
        } catch (e) {
            estado.paginasPdfActual = parseInt(paginaMuestra, 10) || 1;
            estado.conteoPaginasCompletado = true;
        }
        if (dom.lblPaginasInfo) {
            const textoMuestra = paginaMuestra ? `Pág. muestra: ${paginaMuestra} | ` : '';
            dom.lblPaginasInfo.innerText = `${textoMuestra}Total págs: ${estado.paginasPdfActual}`;
        }
        sincronizarBloquesConValidador();
        return estado.conteoPaginasCompletado;
    }

    /**
     * Reinicia el checklist de verificación visual (Color, Orientación, Cronología, Legible) a estado sin marcar.
     */
    function reiniciarVerificacionCalidad() {
        estado.verificacionCalidad = {
            color: null,
            orientacion: null,
            cronologia: null,
            legible: null
        };

        const campos = ['color', 'orientacion', 'cronologia', 'legible'];
        campos.forEach(campo => {
            const grupo = document.getElementById(`grupoCheck_${campo}`);
            if (grupo) {
                grupo.className = 'flex items-center gap-1.5 px-2 py-0.5 rounded bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 transition-colors';
            }
        });

        const botones = document.querySelectorAll('.btn-check-calidad');
        botones.forEach(btn => {
            const esSi = btn.dataset.valor === '1';
            btn.className = `btn-check-calidad w-6 h-6 rounded flex items-center justify-center text-xs font-bold border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 ${esSi ? 'hover:bg-emerald-100 dark:hover:bg-emerald-900/40' : 'hover:bg-red-100 dark:hover:bg-red-900/40'} transition-colors`;
        });
    }

    /**
     * Marca ✔ (true) o ✖ (false) en uno de los 4 puntos de verificación visual.
     */
    function seleccionarPuntoVerificacion(campo, valorBooleano) {
        if (!(campo in estado.verificacionCalidad)) return;
        estado.verificacionCalidad[campo] = valorBooleano;

        const grupo = document.getElementById(`grupoCheck_${campo}`);
        if (grupo) {
            grupo.className = 'flex items-center gap-1.5 px-2 py-0.5 rounded bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 transition-colors';
        }

        const botonesCampo = document.querySelectorAll(`.btn-check-calidad[data-campo="${campo}"]`);
        botonesCampo.forEach(btn => {
            const esBotonSi = btn.dataset.valor === '1';
            const activo = (esBotonSi && valorBooleano === true) || (!esBotonSi && valorBooleano === false);

            if (activo) {
                if (esBotonSi) {
                    btn.className = 'btn-check-calidad w-6 h-6 rounded flex items-center justify-center text-xs font-bold border border-emerald-700 bg-emerald-600 text-white shadow-sm transition-colors';
                } else {
                    btn.className = 'btn-check-calidad w-6 h-6 rounded flex items-center justify-center text-xs font-bold border border-red-700 bg-red-600 text-white shadow-sm transition-colors';
                }
            } else {
                btn.className = `btn-check-calidad w-6 h-6 rounded flex items-center justify-center text-xs font-bold border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 ${esBotonSi ? 'hover:bg-emerald-100 dark:hover:bg-emerald-900/40' : 'hover:bg-red-100 dark:hover:bg-red-900/40'} transition-colors`;
            }
        });
    }

    /**
     * Verifica que los 4 puntos de calidad hayan sido respondidos (✔ o ✖) y resalta los faltantes.
     */
    function validarPuntosVerificacion() {
        const nombresAmigables = {
            color: 'Color',
            orientacion: 'Orientación',
            cronologia: 'Cronología',
            legible: 'Legible'
        };
        const faltantes = [];

        Object.keys(nombresAmigables).forEach(campo => {
            const grupo = document.getElementById(`grupoCheck_${campo}`);
            if (estado.verificacionCalidad[campo] === null) {
                faltantes.push(nombresAmigables[campo]);
                if (grupo) {
                    grupo.className = 'flex items-center gap-1.5 px-2 py-0.5 rounded bg-red-50 dark:bg-red-950/40 border-2 border-red-500 transition-colors';
                }
            }
        });

        return {
            completo: faltantes.length === 0,
            faltantes: faltantes
        };
    }

    /**
     * Procesa y envía automáticamente un PDF detectado como dañado sin requerir captura.
     */
    async function procesarPdfDanadoAutomatico(itemActual) {
        ocultarIndicadorCargaVisor();
        await mostrarAlerta(
            'PDF Dañado',
            'Se detectó que este archivo PDF está dañado. Se registrará automáticamente como dañado y se avanzará al siguiente.',
            'fa-triangle-exclamation',
            'text-red-500'
        );
        await enviarPdfDanadoServidor(itemActual);
    }

    /**
     * Envía de inmediato el archivo actual con danado = true a la base de datos y avanza al siguiente PDF.
     */
    async function enviarPdfDanadoServidor(itemActual) {
        estado.procesandoPeticion = true;
        const segundosCaptura = estado.inicioCapturaPdf
            ? Math.max(1, Math.round((Date.now() - estado.inicioCapturaPdf) / 1000))
            : 1;

        mostrarModalProcesando('Registrando PDF dañado...', 'Guardando y cargando el siguiente documento.');

        try {
            const tokenCsrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const respuesta = await fetch(CONFIG_MUESTREO.rutas.confirmar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': tokenCsrf
                },
                body: JSON.stringify({
                    id: itemActual.id,
                    archivo_nuevo: itemActual.nombre_archivo || 'muestreo.pdf',
                    accion: 'Danado',
                    danado: true,
                    paginas: 0,
                    tiempo_captura: segundosCaptura
                })
            });

            const resultado = await respuesta.json();
            ocultarModalProcesando();
            estado.procesandoPeticion = false;

            if (!respuesta.ok || (!resultado.ok && !resultado.exito)) {
                mostrarAlerta('Error al guardar', resultado.error || 'No se pudo registrar el archivo dañado.', 'fa-circle-xmark', 'text-red-500');
                return;
            }

            estado.procesadosMuestra += 1;
            estado.indiceActual += 1;
            cargarPdfActual();
        } catch (error) {
            ocultarModalProcesando();
            estado.procesandoPeticion = false;
            mostrarAlerta('Error de red', 'No fue posible comunicarse con el servidor.', 'fa-triangle-exclamation', 'text-red-500');
        }
    }

    function seleccionarRadioRegla(idRadio) {
        const rb = document.getElementById(idRadio);
        if (rb) {
            rb.checked = true;
            estado.reglaActualId = idRadio;
            generarBloquesCaptura();
        }
    }

    // =========================================================================
    // GENERACIÓN DINÁMICA DE BLOQUES DE CAPTURA (Réplica exacta de Form1.vb)
    // =========================================================================

    /**
     * Define la estructura de bloques según la regla activa (igual que GenerarBloquesCaptura en Form1.vb).
     */
    function obtenerDefinicionBloques(reglaId) {
        const idDel = estado.delegacionClave || '01';

        switch (reglaId) {
            case 'rbRegla1':
                return [
                    { titulo: 'Del', maxLen: 2, soloLectura: true, valorFijo: idDel, ancho: 54, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Año', maxLen: 4, soloLectura: false, valorFijo: '', ancho: 68, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Registro', maxLen: 6, soloLectura: false, valorFijo: '', ancho: 96, esNumerico: true, colorTipo: 0 }
                ];

            case 'rbRegla2':
                return [
                    { titulo: 'Del', maxLen: 2, soloLectura: true, valorFijo: idDel, ancho: 48, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Secc', maxLen: 3, soloLectura: false, valorFijo: '', ancho: 54, esNumerico: true, colorTipo: 1 },
                    { titulo: 'Libro', maxLen: 6, soloLectura: false, valorFijo: '', ancho: 68, esNumerico: true, colorTipo: 1 },
                    { titulo: 'Tomo', maxLen: 30, soloLectura: false, valorFijo: '', ancho: 88, esNumerico: false, colorTipo: 1, tieneCatalogo: true, tipoCatalogo: 'Tomo' },
                    { titulo: 'Vol', maxLen: 3, soloLectura: false, valorFijo: '', ancho: 54, esNumerico: true, colorTipo: 1 },
                    { titulo: 'Fracc', maxLen: 55, soloLectura: false, valorFijo: '', ancho: 88, esNumerico: false, colorTipo: 1, tieneCatalogo: true, tipoCatalogo: 'Fracc' },
                    { titulo: 'Año', maxLen: 4, soloLectura: false, valorFijo: '', ancho: 68, esNumerico: true, colorTipo: 2 },
                    { titulo: 'Insc', maxLen: 4, soloLectura: false, valorFijo: '', ancho: 68, esNumerico: true, colorTipo: 2 },
                    { titulo: 'BIS', maxLen: 3, soloLectura: false, valorFijo: '', ancho: 58, esNumerico: false, colorTipo: 0 }
                ];

            case 'rbBoletaInscripcion':
                return [
                    { titulo: 'Del', maxLen: 2, soloLectura: true, valorFijo: idDel, ancho: 48, esNumerico: true, colorTipo: 0 },
                    { titulo: '05', maxLen: 2, soloLectura: true, valorFijo: '05', ancho: 48, esNumerico: true, colorTipo: 1 },
                    { titulo: 'Siger', maxLen: 2, soloLectura: false, valorFijo: '', ancho: 54, esNumerico: true, colorTipo: 1 },
                    { titulo: 'FME', maxLen: 10, soloLectura: false, valorFijo: '', ancho: 92, esNumerico: false, colorTipo: 1 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Año', maxLen: 4, soloLectura: false, valorFijo: '', ancho: 68, esNumerico: true, colorTipo: 2 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Ctrl', maxLen: 2, soloLectura: false, valorFijo: '', ancho: 54, esNumerico: true, colorTipo: 2 }
                ];

            case 'rbBoletaIngreso':
                return [
                    { titulo: 'Del', maxLen: 2, soloLectura: true, valorFijo: idDel, ancho: 48, esNumerico: true, colorTipo: 0 },
                    { titulo: '05', maxLen: 2, soloLectura: true, valorFijo: '05', ancho: 48, esNumerico: true, colorTipo: 1 },
                    { titulo: 'Siger', maxLen: 2, soloLectura: false, valorFijo: '', ancho: 54, esNumerico: true, colorTipo: 1 },
                    { titulo: 'FME', maxLen: 15, soloLectura: false, valorFijo: '', ancho: 100, esNumerico: false, colorTipo: 1 },
                    { titulo: 'NCI', maxLen: 12, soloLectura: false, valorFijo: '', ancho: 95, esNumerico: true, colorTipo: 1 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Año', maxLen: 4, soloLectura: false, valorFijo: '', ancho: 68, esNumerico: true, colorTipo: 2 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 }
                ];

            case 'rbBoletaRechazo':
                return [
                    { titulo: 'Del', maxLen: 2, soloLectura: true, valorFijo: idDel, ancho: 48, esNumerico: true, colorTipo: 0 },
                    { titulo: '05', maxLen: 2, soloLectura: true, valorFijo: '05', ancho: 48, esNumerico: true, colorTipo: 1 },
                    { titulo: 'Siger', maxLen: 2, soloLectura: false, valorFijo: '', ancho: 54, esNumerico: true, colorTipo: 1 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: 'NCI', maxLen: 12, soloLectura: false, valorFijo: '', ancho: 95, esNumerico: true, colorTipo: 1 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Año', maxLen: 4, soloLectura: false, valorFijo: '', ancho: 68, esNumerico: true, colorTipo: 2 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: '00', maxLen: 2, soloLectura: true, valorFijo: '00', ancho: 46, esNumerico: true, colorTipo: 0 }
                ];

            case 'rbCatastroUrbano':
                return [
                    { titulo: 'Clas', maxLen: 1, soloLectura: true, valorFijo: '0', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Mun', maxLen: 3, soloLectura: false, valorFijo: '', ancho: 64, esNumerico: true, colorTipo: 1, tieneCatalogo: true, tipoCatalogo: 'Mun' },
                    { titulo: 'Loc', maxLen: 4, soloLectura: false, valorFijo: '', ancho: 72, esNumerico: true, colorTipo: 1, tieneCatalogo: true, tipoCatalogo: 'Loc' },
                    { titulo: 'Sec', maxLen: 2, soloLectura: false, valorFijo: '', ancho: 54, esNumerico: true, colorTipo: 2 },
                    { titulo: 'Manz', maxLen: 3, soloLectura: false, valorFijo: '', ancho: 64, esNumerico: true, colorTipo: 2 },
                    { titulo: 'Pred', maxLen: 3, soloLectura: false, valorFijo: '', ancho: 64, esNumerico: true, colorTipo: 0 }
                ];

            case 'rbCatastroRustico':
                return [
                    { titulo: 'Clas', maxLen: 1, soloLectura: true, valorFijo: '1', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Mun', maxLen: 3, soloLectura: false, valorFijo: '', ancho: 64, esNumerico: true, colorTipo: 1, tieneCatalogo: true, tipoCatalogo: 'Mun' },
                    { titulo: 'Loc', maxLen: 4, soloLectura: false, valorFijo: '', ancho: 72, esNumerico: true, colorTipo: 1, tieneCatalogo: true, tipoCatalogo: 'Loc' },
                    { titulo: 'Registro', maxLen: 7, soloLectura: false, valorFijo: '', ancho: 110, esNumerico: true, colorTipo: 2 }
                ];

            case 'rbCatastroSinEstudio':
                return [
                    { titulo: 'Clas', maxLen: 1, soloLectura: true, valorFijo: '2', ancho: 46, esNumerico: true, colorTipo: 0 },
                    { titulo: 'Mun', maxLen: 3, soloLectura: false, valorFijo: '', ancho: 64, esNumerico: true, colorTipo: 1, tieneCatalogo: true, tipoCatalogo: 'Mun' },
                    { titulo: 'Loc', maxLen: 4, soloLectura: false, valorFijo: '', ancho: 72, esNumerico: true, colorTipo: 1, tieneCatalogo: true, tipoCatalogo: 'Loc' },
                    { titulo: 'Registro', maxLen: 7, soloLectura: false, valorFijo: '', ancho: 110, esNumerico: true, colorTipo: 2 }
                ];

            default:
                return [];
        }
    }

    /**
     * Construye en el DOM los bloques de captura para la regla seleccionada.
     */
    function generarBloquesCaptura() {
        dom.pnlContenedorDinamico.innerHTML = '';
        estado.cajasBloques = [];

        const definiciones = obtenerDefinicionBloques(estado.reglaActualId);

        definiciones.forEach((def, idx) => {
            const columna = document.createElement('div');
            columna.className = 'flex flex-col items-center';

            // Etiqueta superior del bloque
            const lbl = document.createElement('span');
            lbl.className = 'text-xs font-bold text-gray-600 dark:text-gray-300 mb-1 select-none';
            lbl.innerText = def.titulo;
            columna.appendChild(lbl);

            // Caja de texto del bloque
            const input = document.createElement('input');
            input.type = 'text';
            input.maxLength = def.maxLen;
            input.readOnly = def.soloLectura;
            input.value = def.valorFijo || '';
            input.autocomplete = 'off';
            input.spellcheck = false;
            input.style.width = def.ancho + 'px';
            input.dataset.indice = idx;
            input.dataset.titulo = def.titulo;
            input.dataset.esNumerico = def.esNumerico ? '1' : '0';
            input.dataset.colorTipo = def.colorTipo;
            input.dataset.soloLectura = def.soloLectura ? '1' : '0';
            input.dataset.tieneCatalogo = def.tieneCatalogo ? '1' : '0';
            input.dataset.tipoCatalogo = def.tipoCatalogo || '';

            aplicarClasesColorBloque(input, def);

            // Bloquear copiar/pegar como en Form1.vb
            input.addEventListener('paste', e => e.preventDefault());
            input.addEventListener('copy', e => e.preventDefault());
            input.addEventListener('cut', e => e.preventDefault());
            input.addEventListener('contextmenu', e => e.preventDefault());

            if (!def.soloLectura) {
                input.addEventListener('keypress', (e) => BloqueKeyPress(e, input, def));
                input.addEventListener('input', () => BloqueTextChanged(input, def));
                input.addEventListener('keydown', (e) => BloqueKeyDown(e, input, def));
            }

            columna.appendChild(input);
            estado.cajasBloques.push(input);

            // Si el bloque tiene catálogo (Tomo, Fracc, Mun, Loc), agregar botón desplegable
            if (def.tieneCatalogo) {
                const btnCat = document.createElement('button');
                btnCat.type = 'button';
                btnCat.tabIndex = -1;
                btnCat.style.width = def.ancho + 'px';
                btnCat.title = `Catálogo de ${def.titulo} (F2 o F4)`;
                btnCat.className = 'mt-1 py-0.5 text-[11px] font-bold bg-blue-50 hover:bg-blue-100 dark:bg-slate-700 dark:hover:bg-slate-600 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-slate-500 rounded transition-colors';
                btnCat.innerText = '🔍 ▼';
                btnCat.addEventListener('click', () => {
                    mostrarBuscadorCatalogo(input, def.tipoCatalogo);
                });
                columna.appendChild(btnCat);
            }

            dom.pnlContenedorDinamico.appendChild(columna);

            // Separador visual "_" entre bloques (para reglas RPP, igual que Form1.vb)
            if (idx < definiciones.length - 1) {
                const esCatastro = ['rbCatastroUrbano', 'rbCatastroRustico', 'rbCatastroSinEstudio'].includes(estado.reglaActualId);
                const sep = document.createElement('span');
                sep.className = 'text-xl font-bold text-gray-500 dark:text-gray-400 self-center mt-3 mx-0.5 select-none';
                if (!esCatastro) {
                    sep.innerText = '_';
                } else if (
                    (estado.reglaActualId === 'rbCatastroRustico' || estado.reglaActualId === 'rbCatastroSinEstudio') &&
                    idx === 2
                ) {
                    sep.innerText = '␣';
                } else {
                    sep.innerText = '';
                }
                dom.pnlContenedorDinamico.appendChild(sep);
            }
        });

        mostrarMensajeEjemploRegla();
        sincronizarBloquesConValidador();
        enfocarPrimerBloqueEditable();
    }

    /**
     * Aplica los colores de fondo de cada bloque igual que GenerarBloquesCaptura en Form1.vb.
     */
    function aplicarClasesColorBloque(input, def) {
        const esOscuro = document.documentElement.classList.contains('dark');
        let clasesBase = 'px-1.5 py-1.5 text-xl font-bold text-center rounded border-2 focus:outline-none focus:ring-2 focus:ring-brand transition-all ';

        if (def.soloLectura) {
            clasesBase += esOscuro
                ? 'bg-gray-800 text-gray-400 border-gray-700 cursor-not-allowed'
                : 'bg-gray-200 text-gray-500 border-gray-300 cursor-not-allowed';
        } else if (def.colorTipo === 1) {
            // Verde suave (Secc, Libro, Tomo, Vol, Fracc / Mun, Loc)
            clasesBase += esOscuro
                ? 'bg-[#1e3528] text-white border-emerald-700 focus:border-emerald-400'
                : 'bg-[#e6f5ea] text-gray-900 border-emerald-400 focus:border-brand';
        } else if (def.colorTipo === 2) {
            // Rosa suave (Año, Insc / Sec, Manz, Registro)
            clasesBase += esOscuro
                ? 'bg-[#3d232b] text-white border-rose-700 focus:border-rose-400'
                : 'bg-[#fce4ec] text-gray-900 border-rose-300 focus:border-brand';
        } else {
            clasesBase += esOscuro
                ? 'bg-gray-700 text-white border-gray-600 focus:border-brand'
                : 'bg-white text-gray-900 border-gray-300 focus:border-brand';
        }

        input.className = clasesBase;
    }

    function actualizarColoresTemaBloques() {
        const definiciones = obtenerDefinicionBloques(estado.reglaActualId);
        estado.cajasBloques.forEach((input, idx) => {
            if (definiciones[idx]) {
                aplicarClasesColorBloque(input, definiciones[idx]);
            }
        });
    }

    /**
     * Muestra en lblEstado el ejemplo correspondiente a la regla seleccionada.
     */
    function mostrarMensajeEjemploRegla() {
        let texto = '';
        switch (estado.reglaActualId) {
            case 'rbRegla1':
                texto = 'Ejemplo: 01_2024_123456 (Folio de 1 a 6 dígitos)';
                break;
            case 'rbRegla2':
                texto = 'Ejemplo: 01_01_01_01_00_00_2011_0090_00';
                break;
            case 'rbBoletaInscripcion':
                texto = 'Ejemplo: 01_05_01_FME123_00_00_2024_00_01';
                break;
            case 'rbBoletaIngreso':
                texto = 'Ejemplo: 01_05_01_FME123_123456_00_2024_00_00';
                break;
            case 'rbBoletaRechazo':
                texto = 'Ejemplo: 01_05_01_00_123456_00_2024_00_00';
                break;
            case 'rbCatastroUrbano':
                texto = 'Urbano: 0 + Mun(3) + Loc(4) + Sec(2) + Manz(3) + Pred(3)';
                break;
            case 'rbCatastroRustico':
                texto = 'Rústico: 1 + Mun(3) + Loc(4) + Espacio + Registro(1-7)';
                break;
            case 'rbCatastroSinEstudio':
                texto = 'Sin Estudio: 2 + Mun(3) + Loc(4) + Espacio + Registro(1-7)';
                break;
        }
        dom.lblEstado.innerText = texto;
        dom.lblEstado.className = 'text-base font-medium text-gray-700 dark:text-gray-300 min-h-[24px]';
    }

    /**
     * Filtra caracteres al presionar teclas en un bloque (idéntico a Bloque_KeyPress de Form1.vb).
     */
    function BloqueKeyPress(e, input, def) {
        if (e.key === 'Enter') return;

        if (def.esNumerico) {
            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
            }
        } else {
            if (def.titulo === 'FME') {
                if (!/^[a-zA-Z0-9\-]$/.test(e.key)) {
                    e.preventDefault();
                }
            } else {
                if (!/^[a-zA-Z0-9_\-]$/.test(e.key)) {
                    e.preventDefault();
                }
            }
        }
    }

    /**
     * Procesa el cambio de texto en un bloque y aplica el auto-salto inteligente (idéntico a Bloque_TextChanged de Form1.vb).
     */
    function BloqueTextChanged(input, def) {
        const posCursor = input.selectionStart;
        let valorLimpio = input.value.toUpperCase();
        if (def.titulo !== 'FME') {
            valorLimpio = valorLimpio.replace(/-/g, '_');
        }
        if (input.value !== valorLimpio) {
            input.value = valorLimpio;
            input.setSelectionRange(posCursor, posCursor);
        }

        // Si el bloque es Municipio (Mun), cargar localidades correspondientes al completar 3 dígitos
        if (def.titulo === 'Mun') {
            manejarCambioMunicipio(input.value);
        } else if (def.titulo === 'Loc' && input.value.length === 4) {
            mostrarNombreLocalidadEnEstado(input.value);
        }

        // Lógica de auto-salto inteligente (igual que Bloque_TextChanged en Form1.vb)
        let saltar = false;
        const texto = input.value;

        if (def.titulo === 'Secc') {
            if (texto.length === 2) saltar = true;
        } else if (def.titulo === 'Libro' || def.titulo === 'Vol') {
            if (texto.length === 2 && texto.startsWith('0')) {
                saltar = true;
            } else if (texto.length === def.maxLen) {
                saltar = true;
            }
        } else if (def.titulo === 'Tomo' || def.titulo === 'Fracc') {
            if (/^\d{2}$/.test(texto)) {
                saltar = true;
            } else if (texto.length === def.maxLen) {
                saltar = true;
            }
        } else if (!['Registro', 'BIS', 'FME', 'NCI'].includes(def.titulo)) {
            if (texto.length === def.maxLen) {
                saltar = true;
            }
        }

        // Ajustar ancho dinámico cuando Tomo o Fracc tienen texto largo
        if (def.titulo === 'Tomo' || def.titulo === 'Fracc') {
            const anchoCalc = Math.max(def.ancho, Math.min(280, texto.length * 11 + 24));
            input.style.width = anchoCalc + 'px';
        }

        if (saltar) {
            moverFocoBloque(input, 1);
        }

        sincronizarBloquesConValidador();
    }

    /**
     * Maneja la navegación por teclado entre bloques (Enter, Backspace, Flechas, Espacio, F2/F4).
     */
    function BloqueKeyDown(e, input, def) {
        // Abrir catálogo con F2, F4 o Alt + Flecha Abajo
        if (def.tieneCatalogo && (e.key === 'F2' || e.key === 'F4' || (e.altKey && e.key === 'ArrowDown'))) {
            e.preventDefault();
            mostrarBuscadorCatalogo(input, def.tipoCatalogo);
            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            const resultado = validarFormato(dom.txtNombreOculto.value.trim());
            if (resultado.esValido) {
                ejecutarValidacionDobleCaptura();
            } else {
                moverFocoBloque(input, 1);
            }
        } else if (e.key === 'Backspace' && input.value.length === 0) {
            e.preventDefault();
            moverFocoBloque(input, -1);
        } else if (e.key === 'ArrowLeft' && input.selectionStart === 0) {
            e.preventDefault();
            moverFocoBloque(input, -1);
        } else if (e.key === 'ArrowRight' && input.selectionStart === input.value.length) {
            e.preventDefault();
            moverFocoBloque(input, 1);
        } else if (e.key === ' ' && input.value.length > 0) {
            e.preventDefault();
            moverFocoBloque(input, 1);
        }
    }

    /**
     * Mueve el foco al siguiente o anterior bloque editable.
     */
    function moverFocoBloque(inputActual, direccion) {
        const indiceActual = estado.cajasBloques.indexOf(inputActual);
        let nuevoIndice = indiceActual + direccion;

        while (nuevoIndice >= 0 && nuevoIndice < estado.cajasBloques.length) {
            const candidato = estado.cajasBloques[nuevoIndice];
            if (!candidato.readOnly) {
                candidato.focus();
                candidato.select();
                break;
            }
            nuevoIndice += direccion;
        }
    }

    function enfocarPrimerBloqueEditable() {
        for (const input of estado.cajasBloques) {
            if (!input.readOnly) {
                input.focus();
                input.select();
                break;
            }
        }
    }

    function limpiarBloquesEditables() {
        const definiciones = obtenerDefinicionBloques(estado.reglaActualId);
        estado.cajasBloques.forEach((input, idx) => {
            if (!input.readOnly) {
                input.value = '';
                if (definiciones[idx]) {
                    input.style.width = definiciones[idx].ancho + 'px';
                }
            }
        });
        sincronizarBloquesConValidador();
        enfocarPrimerBloqueEditable();
    }

    function actualizarBloqueDelegacionFijo() {
        if (estado.cajasBloques.length > 0 && estado.cajasBloques[0].dataset.titulo === 'Del') {
            estado.cajasBloques[0].value = estado.delegacionClave;
            sincronizarBloquesConValidador();
        }
    }

    // =========================================================================
    // MOTOR DE REGLAS Y VALIDACIÓN (Réplica exacta de ServicioReglas.vb)
    // =========================================================================

    /**
     * Une los bloques en la cadena completa y ejecuta la validación en tiempo real.
     */
    function sincronizarBloquesConValidador() {
        const valores = estado.cajasBloques.map(b => b.value.trim());
        let cadenaUnida = '';

        if (estado.reglaActualId === 'rbCatastroUrbano') {
            cadenaUnida = valores.join('');
        } else if (estado.reglaActualId === 'rbCatastroRustico' || estado.reglaActualId === 'rbCatastroSinEstudio') {
            if (valores.length === 4) {
                cadenaUnida = `${valores[0]}${valores[1]}${valores[2]} ${valores[3]}`;
            } else {
                cadenaUnida = valores.join('');
            }
        } else {
            cadenaUnida = valores.join('_');
        }

        dom.txtNombreOculto.value = cadenaUnida;

        const algunEditableConTexto = estado.cajasBloques.some(b => !b.readOnly && b.value.trim().length > 0);
        if (!algunEditableConTexto) {
            dom.btnRenombrar.disabled = true;
            return;
        }

        const resultado = validarFormato(cadenaUnida);
        dom.btnRenombrar.disabled = !resultado.esValido || !estado.conteoPaginasCompletado;

        if (resultado.mensajeError) {
            dom.lblEstado.innerText = resultado.mensajeError;
            dom.lblEstado.className = 'text-base font-bold text-red-600 dark:text-red-400 min-h-[24px]';
        } else if (resultado.esValido) {
            if (!estado.conteoPaginasCompletado) {
                dom.lblEstado.innerText = 'Contando páginas...';
                dom.lblEstado.className = 'text-base font-bold text-amber-600 dark:text-amber-400 min-h-[24px]';
                return;
            }

            // Verificar si coincide con el nombre original del archivo
            const itemActual = estado.colaPdfs[estado.indiceActual];
            const originalNorm = itemActual ? normalizarNombreOriginal(itemActual.nombre_limpio) : '';
            const actualNorm = normalizarNombreOriginal(cadenaUnida);

            if (originalNorm && actualNorm.toLowerCase() === originalNorm.toLowerCase()) {
                dom.lblEstado.innerText = '✓ Nombre válido (Coincide con el original: se registrará como Validado)';
                dom.lblEstado.className = 'text-base font-bold text-emerald-600 dark:text-emerald-400 min-h-[24px]';
            } else {
                dom.lblEstado.innerText = `✓ Formato válido: ${cadenaUnida}`;
                dom.lblEstado.className = 'text-base font-bold text-blue-600 dark:text-blue-400 min-h-[24px]';
            }
        } else {
            mostrarMensajeEjemploRegla();
        }
    }

    /**
     * Valida una palabra reservada contra un catálogo (idéntico a ValidarPalabraReservada en ServicioReglas.vb).
     */
    function validarPalabraReservada(valor, catalogo, nombreCampo) {
        if (!/[A-Z]/.test(valor) || valor === 'BIS') {
            return { ok: true, error: '' };
        }

        const baseTexto = valor.replace(/_?\d+[A-Z]?$/, '');
        const coincideExactoOPrefijo = catalogo.some(c => c.startsWith(baseTexto));

        if (!coincideExactoOPrefijo) {
            const tokensLetras = valor.split(/[^A-Z]+/).filter(s => s.length > 0);
            const intentaEscribirPalabra = tokensLetras.some(s => s.length >= 3);
            if (intentaEscribirPalabra) {
                return {
                    ok: false,
                    error: `Error: Palabra no reconocida en ${nombreCampo}. Verifique su escritura.`
                };
            }
        }

        const esPalabraCompletaValida = catalogo.some(c => c === baseTexto);
        if (!esPalabraCompletaValida) {
            const tieneSufijoNumerico = /_?\d+[A-Z]?$/.test(valor);
            if (tieneSufijoNumerico && coincideExactoOPrefijo) {
                return {
                    ok: false,
                    error: `Error: La palabra reservada en ${nombreCampo} está incompleta.`
                };
            }
        }

        return { ok: true, error: '' };
    }

    /**
     * Valida el formato completo según la regla activa (idéntico a ValidarFormato en ServicioReglas.vb).
     */
    function validarFormato(texto) {
        let mensajeError = '';
        const partes = texto.split('_');
        const reglaId = estado.reglaActualId;

        if (reglaId === 'rbRegla1') {
            if (partes.length >= 2 && partes[1].length === 4) {
                if (!CONFIG_MUESTREO.catalogoAnios.includes(partes[1])) {
                    mensajeError = `Error: El año '${partes[1]}' no existe en el catálogo.`;
                }
            }
            const regex = /^\d{2}_\d{4}_\d{1,6}$/;
            const esValido = regex.test(texto) && mensajeError === '';
            return { esValido, mensajeError };
        }

        if (reglaId === 'rbRegla2') {
            if (partes.length >= 4 && partes[3].length > 0) {
                if (partes[3].toUpperCase().startsWith('F')) {
                    return {
                        esValido: false,
                        mensajeError: "Error: El campo Tomo no puede iniciar con 'F' (Fraccionamiento va en el bloque 6)."
                    };
                }
                const resTomo = validarPalabraReservada(partes[3], CATALOGO_TOMOS, 'Tomo (bloque 4)');
                if (!resTomo.ok) {
                    return { esValido: false, mensajeError: resTomo.error };
                }
            }

            if (partes.length >= 6 && partes[5].length > 0) {
                const resFracc = validarPalabraReservada(partes[5], CATALOGO_FRACC, 'Fraccionamiento (bloque 6)');
                if (!resFracc.ok) {
                    return { esValido: false, mensajeError: resFracc.error };
                }
            }

            if (partes.length >= 7 && partes[6].length === 4) {
                if (!CONFIG_MUESTREO.catalogoAnios.includes(partes[6])) {
                    mensajeError = `Error: El año '${partes[6]}' no existe en el catálogo.`;
                    return { esValido: false, mensajeError };
                }
            }

            const regex = /^\d{2}_\d{2,3}_\d{2,6}_[A-Z0-9_]{1,30}_\d{2,3}_[A-Z0-9_]{1,55}_\d{4}_\d{4}_(\d{2}|BIS)$/;
            let esValido = regex.test(texto) && partes.length === 9 && mensajeError === '';

            if (esValido) {
                for (let idx = 3; idx <= 5; idx += 2) {
                    const seg = partes[idx];
                    const cat = idx === 3 ? CATALOGO_TOMOS : CATALOGO_FRACC;
                    const baseSeg = seg.replace(/_?\d+[A-Z]?$/, '');
                    const tokens = seg.split(/[^A-Z]+/).filter(s => s.length > 0);
                    const tienePalabraLarga = tokens.some(s => s.length >= 3);
                    if (tienePalabraLarga && !cat.includes(baseSeg)) {
                        esValido = false;
                        mensajeError = 'Error: Palabra reservada incompleta o inválida.';
                    }
                }
            }

            return { esValido, mensajeError };
        }

        if (reglaId === 'rbBoletaInscripcion') {
            if (partes.length >= 7 && partes[6].length === 4) {
                if (!CONFIG_MUESTREO.catalogoAnios.includes(partes[6])) {
                    return { esValido: false, mensajeError: `Error: El año '${partes[6]}' no existe en el catálogo.` };
                }
            }
            const regex = /^\d{2}_05_\d{2}_[A-Z0-9\-]{1,10}_00_00_\d{4}_00_\d{2}$/;
            return { esValido: regex.test(texto) && partes.length === 9, mensajeError: '' };
        }

        if (reglaId === 'rbBoletaIngreso') {
            if (partes.length >= 7 && partes[6].length === 4) {
                if (!CONFIG_MUESTREO.catalogoAnios.includes(partes[6])) {
                    return { esValido: false, mensajeError: `Error: El año '${partes[6]}' no existe en el catálogo.` };
                }
            }
            const regex = /^\d{2}_\d{2,3}_\d{2}_[A-Z0-9\-]{1,15}_\d{1,12}_00_\d{4}_00_00$/;
            return { esValido: regex.test(texto) && partes.length === 9, mensajeError: '' };
        }

        if (reglaId === 'rbBoletaRechazo') {
            if (partes.length >= 7 && partes[6].length === 4) {
                if (!CONFIG_MUESTREO.catalogoAnios.includes(partes[6])) {
                    return { esValido: false, mensajeError: `Error: El año '${partes[6]}' no existe en el catálogo.` };
                }
            }
            const regex = /^\d{2}_\d{2,3}_\d{2}_00_\d{1,12}_00_\d{4}_00_00$/;
            return { esValido: regex.test(texto) && partes.length === 9, mensajeError: '' };
        }

        if (reglaId === 'rbCatastroUrbano') {
            const errorCat = validarExistenciaMunLocCatastro();
            if (errorCat) return { esValido: false, mensajeError: errorCat };
            const regex = /^0\d{15}$/;
            return { esValido: regex.test(texto), mensajeError: '' };
        }

        if (reglaId === 'rbCatastroRustico') {
            const errorCat = validarExistenciaMunLocCatastro();
            if (errorCat) return { esValido: false, mensajeError: errorCat };
            const regex = /^1\d{3}\d{4}[ _]\d{1,7}$/;
            return { esValido: regex.test(texto), mensajeError: '' };
        }

        if (reglaId === 'rbCatastroSinEstudio') {
            const errorCat = validarExistenciaMunLocCatastro();
            if (errorCat) return { esValido: false, mensajeError: errorCat };
            const regex = /^2\d{3}\d{4}[ _]\d{1,7}$/;
            return { esValido: regex.test(texto), mensajeError: '' };
        }

        return { esValido: false, mensajeError: '' };
    }

    /**
     * Verifica que las claves de Municipio y Localidad existan en el catálogo de Catastro.
     */
    function validarExistenciaMunLocCatastro() {
        const cajaMun = estado.cajasBloques.find(b => b.dataset.titulo === 'Mun');
        const cajaLoc = estado.cajasBloques.find(b => b.dataset.titulo === 'Loc');

        if (cajaMun && cajaMun.value.length === 3) {
            const existeMun = CONFIG_MUESTREO.catalogoMunicipios.some(m => m.clave === cajaMun.value);
            if (!existeMun) {
                return `Error: El municipio '${cajaMun.value}' no existe en el catálogo.`;
            }
        }
        if (cajaLoc && cajaLoc.value.length === 4 && estado.localidadesActuales.length > 0) {
            const existeLoc = estado.localidadesActuales.some(l => l.clave === cajaLoc.value);
            if (!existeLoc) {
                return `Error: La localidad '${cajaLoc.value}' no pertenece al municipio seleccionado.`;
            }
        }
        return '';
    }

    /**
     * Normaliza un nombre de archivo para comparar en Rústicos/Sin Estudio donde espacio o '_' son equivalentes.
     */
    function normalizarNombreOriginal(nombre) {
        const limpio = String(nombre || '').trim().replace(/\.pdf$/i, '');
        if (/^[12]\d{7}_\d{1,7}$/.test(limpio)) {
            return limpio.replace('_', ' ');
        }
        return limpio;
    }

    // =========================================================================
    // DOBLE CAPTURA / FAST-TRACK Y REGISTRO EN BASE DE DATOS (Form1.vb)
    // =========================================================================

    /**
     * Ejecuta la validación de captura o doble captura (idéntico a EjecutarValidacionDobleCaptura en Form1.vb).
     */
    async function ejecutarValidacionDobleCaptura() {
        if (estado.procesandoPeticion || estado.colaPdfs.length === 0) return;

        const estadoVerificacion = validarPuntosVerificacion();
        if (!estadoVerificacion.completo) {
            mostrarAlerta(
                'Verificación pendiente',
                `Debe marcar ✔ o ✖ en todos los puntos de verificación antes de confirmar.\nFaltan: ${estadoVerificacion.faltantes.join(', ')}.`,
                'fa-list-check',
                'text-amber-500'
            );
            return;
        }

        const capturaActual = dom.txtNombreOculto.value.trim();
        const validacion = validarFormato(capturaActual);
        if (!validacion.esValido) {
            mostrarAlerta('Formato inválido', validacion.mensajeError || 'Verifique los campos capturados.', 'fa-circle-xmark', 'text-red-500');
            return;
        }

        const itemActual = estado.colaPdfs[estado.indiceActual];
        const nombreOriginalNorm = normalizarNombreOriginal(itemActual.nombre_limpio);

        // Verificar si coincide con alguna captura previa en historialCapturas (posición 0 es el nombre original)
        const coincideConPrevio = estado.historialCapturas.some(
            c => c.toLowerCase() === capturaActual.toLowerCase()
        );

        if (coincideConPrevio) {
            // Asegurar conteo de páginas de respaldo sin bloquear la captura
            if (!estado.conteoPaginasCompletado || estado.paginasPdfActual <= 0) {
                estado.paginasPdfActual = parseInt(itemActual.paginas, 10) || parseInt(itemActual.pagina_muestra, 10) || 1;
                estado.conteoPaginasCompletado = true;
            }

            const cambioNombre = capturaActual.toLowerCase() !== nombreOriginalNorm.toLowerCase();
            const accionRegistrar = cambioNombre ? 'Renombrar' : 'Validado';

            const confirmado = await solicitarConfirmacion(
                'Confirmar Muestreo',
                `El documento se registrará como:\n"${capturaActual}.pdf"\n(${cambioNombre ? 'Renombrar - Con cambio' : 'Validado - Correcto'})\n\n¿El documento ha sido validado correctamente?`
            );

            if (!confirmado) {
                return;
            }

            await enviarConfirmacionServidor(itemActual, capturaActual, accionRegistrar);
        } else {
            // Si es la primera captura (historial tiene solo el nombre original en índice 0) y escribieron un nombre distinto:
            if (estado.historialCapturas.length === 1) {
                estado.historialCapturas.push(capturaActual);
                dom.lblCaptura.innerText = 'Confirmación';
                dom.lblCaptura.className = 'text-lg font-bold text-orange-500';
                limpiarBloquesEditables();
                dom.lblEstado.innerText = 'El nombre es distinto al original. Repítalo para confirmar.';
                dom.lblEstado.className = 'text-base font-bold text-orange-600 dark:text-orange-400 min-h-[24px]';
            } else {
                // La segunda captura no coincidió con la primera captura del nuevo nombre
                await mostrarAlerta(
                    'Capturas no coinciden',
                    'Las capturas no coinciden. Por seguridad, se reiniciará la captura de este archivo.',
                    'fa-triangle-exclamation',
                    'text-amber-500'
                );
                reiniciarEstadoCapturaActual();
            }
        }
    }

    /**
     * Reinicia el estado de doble captura para el archivo actual.
     */
    function reiniciarEstadoCapturaActual() {
        const itemActual = estado.colaPdfs[estado.indiceActual];
        const nombreOriginalNorm = itemActual ? normalizarNombreOriginal(itemActual.nombre_limpio) : '';
        estado.historialCapturas = [nombreOriginalNorm];
        dom.lblCaptura.innerText = 'Primera captura';
        dom.lblCaptura.className = 'text-lg font-bold text-gray-800 dark:text-gray-100';
        limpiarBloquesEditables();
        mostrarMensajeEjemploRegla();
    }

    /**
     * Envía la confirmación (Validado o Renombrar) al servidor para guardar en BD.
     */
    async function enviarConfirmacionServidor(itemActual, nuevoNombreSinExt, accion) {
        estado.procesandoPeticion = true;
        const segundosCaptura = estado.inicioCapturaPdf
            ? Math.max(1, Math.round((Date.now() - estado.inicioCapturaPdf) / 1000))
            : 1;

        mostrarModalProcesando('Guardando registro...', 'Registrando resultado del muestreo en la base de datos.');

        try {
            const tokenCsrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const respuesta = await fetch(CONFIG_MUESTREO.rutas.confirmar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': tokenCsrf
                },
                body: JSON.stringify({
                    id: itemActual.id,
                    archivo_nuevo: nuevoNombreSinExt + '.pdf',
                    accion: accion,
                    paginas: estado.paginasPdfActual || 1,
                    tiempo_captura: segundosCaptura,
                    danado: false,
                    color: estado.verificacionCalidad.color,
                    orientacion: estado.verificacionCalidad.orientacion,
                    cronologia: estado.verificacionCalidad.cronologia,
                    legible: estado.verificacionCalidad.legible
                })
            });

            const resultado = await respuesta.json();
            ocultarModalProcesando();
            estado.procesandoPeticion = false;

            if (!respuesta.ok || (!resultado.ok && !resultado.exito)) {
                mostrarAlerta('Error al guardar', resultado.error || 'No se pudo guardar el registro en la base de datos.', 'fa-circle-xmark', 'text-red-500');
                return;
            }

            // Avanzar al siguiente archivo de la cola
            estado.procesadosMuestra += 1;
            estado.indiceActual += 1;
            cargarPdfActual();
        } catch (error) {
            ocultarModalProcesando();
            estado.procesandoPeticion = false;
            mostrarAlerta('Error de red', 'No fue posible comunicarse con el servidor.', 'fa-triangle-exclamation', 'text-red-500');
        }
    }

    /**
     * Botón de Ojo (👁️): Muestra Sección e Inscripción del nombre original y registra acción 'Inspeccionado' en BD.
     */
    async function inspeccionarInscripcionActual() {
        if (estado.colaPdfs.length === 0 || estado.indiceActual >= estado.colaPdfs.length) return;

        const itemActual = estado.colaPdfs[estado.indiceActual];
        const nombreSinExt = itemActual.nombre_limpio || '';
        const partes = nombreSinExt.split('_');

        if (partes.length >= 2) {
            const inscripcion = partes[partes.length - 2];
            let textoMostrar = `📌 Inscripción: ${inscripcion}`;
            if (partes.length >= 8) {
                const seccion = partes[1];
                textoMostrar = `📌 Sección: ${seccion} | Inscripción: ${inscripcion}`;
            }
            dom.lblEstado.innerText = textoMostrar;
            dom.lblEstado.className = 'text-base font-bold text-indigo-600 dark:text-indigo-400 min-h-[24px]';

            // Registrar la acción 'Inspeccionado' en auditoria_muestreo (igual que btnVerInscripcion_Click en Form1.vb)
            const segundosCaptura = estado.inicioCapturaPdf
                ? Math.max(0, Math.round((Date.now() - estado.inicioCapturaPdf) / 1000))
                : 0;

            try {
                const tokenCsrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                await fetch(CONFIG_MUESTREO.rutas.confirmar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': tokenCsrf
                    },
                    body: JSON.stringify({
                        id: itemActual.id,
                        archivo_nuevo: itemActual.nombre_archivo,
                        accion: 'Inspeccionado',
                        paginas: estado.paginasPdfActual || 1,
                        tiempo_captura: segundosCaptura
                    })
                });
            } catch (e) {
                // Silencioso en caso de fallo de red secundario
            }
        } else {
            dom.lblEstado.innerText = 'El archivo no tiene el formato esperado para extraer la inscripción.';
            dom.lblEstado.className = 'text-base font-semibold text-amber-600 dark:text-amber-400 min-h-[24px]';
        }
    }

    // =========================================================================
    // CATÁLOGOS EMERGENTES (TOMO, FRACC, MUNICIPIOS Y LOCALIDADES)
    // =========================================================================

    /**
     * Cuando cambia el bloque Mun en Catastro, carga sus localidades y muestra el nombre del municipio.
     */
    async function manejarCambioMunicipio(claveMun) {
        const cajaLoc = estado.cajasBloques.find(b => b.dataset.titulo === 'Loc');
        if (cajaLoc) {
            cajaLoc.value = '';
        }

        if (claveMun.length !== 3) {
            estado.localidadesActuales = [];
            return;
        }

        const munObj = CONFIG_MUESTREO.catalogoMunicipios.find(m => m.clave === claveMun);
        if (!munObj) {
            estado.localidadesActuales = [];
            dom.lblEstado.innerText = `⚠️ Municipio (${claveMun}): NO ENCONTRADO`;
            dom.lblEstado.className = 'text-base font-bold text-red-600 dark:text-red-400 min-h-[24px]';
            return;
        }

        dom.lblEstado.innerText = `📍 Municipio (${munObj.clave}): ${munObj.nombre}`;
        dom.lblEstado.className = 'text-base font-bold text-emerald-700 dark:text-emerald-400 min-h-[24px]';

        if (estado.cacheLocalidadesPorMun[claveMun]) {
            estado.localidadesActuales = estado.cacheLocalidadesPorMun[claveMun];
            return;
        }

        try {
            const url = `${CONFIG_MUESTREO.rutas.obtenerLocalidades}?clave_municipio=${encodeURIComponent(claveMun)}&municipio_id=${encodeURIComponent(munObj.id || '')}`;
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const datos = await resp.json();
            if (datos.ok && Array.isArray(datos.localidades)) {
                estado.localidadesActuales = datos.localidades;
                estado.cacheLocalidadesPorMun[claveMun] = datos.localidades;
                sincronizarBloquesConValidador();
            }
        } catch (e) {
            estado.localidadesActuales = [];
        }
    }

    function mostrarNombreLocalidadEnEstado(claveLoc) {
        const locObj = estado.localidadesActuales.find(l => l.clave === claveLoc);
        if (locObj) {
            dom.lblEstado.innerText = `🏘️ Localidad (${locObj.clave}): ${locObj.nombre}`;
            dom.lblEstado.className = 'text-base font-bold text-emerald-700 dark:text-emerald-400 min-h-[24px]';
        }
    }

    /**
     * Abre el modal buscador de catálogo para Tomo, Fracc, Mun o Loc.
     */
    async function mostrarBuscadorCatalogo(inputDestino, tipoCatalogo) {
        estado.bloqueActivoCatalogo = inputDestino;
        let lista = [];
        let tituloModal = 'Catálogo';

        if (tipoCatalogo === 'Tomo') {
            tituloModal = 'Catálogo de Tomos Especiales';
            lista = CATALOGO_TOMOS.map(t => ({ valor: t, texto: t }));
        } else if (tipoCatalogo === 'Fracc') {
            tituloModal = 'Catálogo de Fraccionamientos';
            lista = CATALOGO_FRACC.map(f => ({ valor: f, texto: f }));
        } else if (tipoCatalogo === 'Mun') {
            tituloModal = 'Catálogo de Municipios (Catastro)';
            lista = CONFIG_MUESTREO.catalogoMunicipios.map(m => ({
                valor: m.clave,
                texto: `${m.clave} - ${m.nombre}`
            }));
        } else if (tipoCatalogo === 'Loc') {
            const cajaMun = estado.cajasBloques.find(b => b.dataset.titulo === 'Mun');
            if (!cajaMun || cajaMun.value.length !== 3) {
                mostrarAlerta('Municipio requerido', 'Primero ingrese o seleccione un Municipio válido (3 dígitos).', 'fa-circle-info', 'text-brand');
                if (cajaMun) cajaMun.focus();
                return;
            }
            if (estado.localidadesActuales.length === 0) {
                await manejarCambioMunicipio(cajaMun.value);
            }
            if (estado.localidadesActuales.length === 0) {
                mostrarAlerta('Sin localidades', 'No se encontraron localidades para el municipio indicado.', 'fa-triangle-exclamation', 'text-amber-500');
                return;
            }
            tituloModal = `Localidades del Municipio ${cajaMun.value}`;
            lista = estado.localidadesActuales.map(l => ({
                valor: l.clave,
                texto: `${l.clave} - ${l.nombre}`
            }));
        }

        estado.itemsCatalogoModal = lista;
        dom.lblCatalogoTitulo.innerText = tituloModal;
        dom.txtBuscarCatalogo.value = '';
        filtrarItemsCatalogo('');
        dom.modalCatalogo.classList.remove('hidden');
        dom.txtBuscarCatalogo.focus();
    }

    function filtrarItemsCatalogo(filtro) {
        const termino = filtro.trim().toUpperCase();
        const filtrados = termino === ''
            ? estado.itemsCatalogoModal
            : estado.itemsCatalogoModal.filter(item => item.texto.toUpperCase().includes(termino));

        dom.listaCatalogoItems.innerHTML = '';
        estado.indiceSeleccionCatalogo = 0;

        if (filtrados.length === 0) {
            const vacio = document.createElement('div');
            vacio.className = 'p-4 text-center text-sm text-gray-500 dark:text-gray-400';
            vacio.innerText = 'No se encontraron coincidencias.';
            dom.listaCatalogoItems.appendChild(vacio);
            return;
        }

        filtrados.forEach((item, idx) => {
            const fila = document.createElement('button');
            fila.type = 'button';
            fila.dataset.valor = item.valor;
            fila.dataset.indice = idx;
            fila.className = `w-full text-left px-4 py-2 text-sm font-semibold border-b border-gray-100 dark:border-gray-700 transition-colors ${
                idx === 0
                    ? 'bg-brand text-white'
                    : 'text-gray-800 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-gray-700'
            }`;
            fila.innerText = item.texto;
            fila.addEventListener('click', () => {
                seleccionarValorCatalogo(item.valor);
            });
            dom.listaCatalogoItems.appendChild(fila);
        });
    }

    function manejarTeclasBuscadorCatalogo(e) {
        const botones = Array.from(dom.listaCatalogoItems.querySelectorAll('button[data-valor]'));
        if (e.key === 'Escape') {
            e.preventDefault();
            cerrarBuscadorCatalogo();
            return;
        }
        if (botones.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            estado.indiceSeleccionCatalogo = Math.min(botones.length - 1, estado.indiceSeleccionCatalogo + 1);
            actualizarResaltadoCatalogo(botones);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            estado.indiceSeleccionCatalogo = Math.max(0, estado.indiceSeleccionCatalogo - 1);
            actualizarResaltadoCatalogo(botones);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const botonActivo = botones[estado.indiceSeleccionCatalogo] || botones[0];
            if (botonActivo) {
                seleccionarValorCatalogo(botonActivo.dataset.valor);
            }
        }
    }

    function actualizarResaltadoCatalogo(botones) {
        botones.forEach((btn, idx) => {
            if (idx === estado.indiceSeleccionCatalogo) {
                btn.className = 'w-full text-left px-4 py-2 text-sm font-semibold border-b border-gray-100 dark:border-gray-700 bg-brand text-white';
                btn.scrollIntoView({ block: 'nearest' });
            } else {
                btn.className = 'w-full text-left px-4 py-2 text-sm font-semibold border-b border-gray-100 dark:border-gray-700 text-gray-800 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-gray-700';
            }
        });
    }

    function seleccionarValorCatalogo(valorElegido) {
        const input = estado.bloqueActivoCatalogo;
        cerrarBuscadorCatalogo();
        if (!input) return;

        const tipoCat = input.dataset.tipoCatalogo;
        input.value = valorElegido;

        const definiciones = obtenerDefinicionBloques(estado.reglaActualId);
        const idx = parseInt(input.dataset.indice, 10);
        const def = definiciones[idx];

        if (tipoCat === 'Mun' || tipoCat === 'Loc') {
            BloqueTextChanged(input, def);
        } else {
            // En Tomo o Fracc, colocar el valor + "_" al final para que el capturista pueda añadir el número o borrar el guion
            input.value = valorElegido + '_';
            const anchoCalc = Math.max(def.ancho, Math.min(280, input.value.length * 11 + 24));
            input.style.width = anchoCalc + 'px';
            sincronizarBloquesConValidador();
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
    }

    function cerrarBuscadorCatalogo() {
        dom.modalCatalogo.classList.add('hidden');
        if (estado.bloqueActivoCatalogo) {
            estado.bloqueActivoCatalogo.focus();
        }
    }

    // =========================================================================
    // MODAL DE SELECCIÓN DE DELEGACIÓN Y MODALES DE CONFIRMACIÓN / ALERTA
    // =========================================================================

    function abrirModalDelegacion() {
        dom.listaDelegacionesModal.innerHTML = '';
        CONFIG_MUESTREO.catalogoDelegaciones.forEach(del => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const activo = del.clave === estado.delegacionClave;
            btn.className = `w-full text-left px-3 py-2 rounded text-sm font-semibold flex items-center justify-between transition-colors ${
                activo
                    ? 'bg-brand text-white'
                    : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200'
            }`;
            btn.innerHTML = `<span><strong>${del.clave}</strong> - ${del.nombre}</span>${activo ? '<i class="fa-solid fa-check"></i>' : ''}`;
            btn.addEventListener('click', () => {
                estado.delegacionClave = del.clave;
                estado.delegacionNombre = del.nombre;
                actualizarBloqueDelegacionFijo();
                dom.modalDelegacion.classList.add('hidden');
            });
            dom.listaDelegacionesModal.appendChild(btn);
        });
        dom.modalDelegacion.classList.remove('hidden');
    }

    function mostrarModalProcesando(titulo, mensaje) {
        dom.lblProcesandoTitulo.innerText = titulo;
        dom.lblProcesandoMensaje.innerText = mensaje;
        dom.modalProcesando.classList.remove('hidden');
    }

    function ocultarModalProcesando() {
        dom.modalProcesando.classList.add('hidden');
    }

    function solicitarConfirmacion(titulo, mensaje, icono = 'fa-circle-question', colorIcono = 'text-brand', mostrarCancelar = true) {
        return new Promise((resolve) => {
            dom.lblConfirmacionTitulo.innerText = titulo;
            dom.lblConfirmacionMensaje.innerText = mensaje;
            dom.iconConfirmacion.className = `fa-solid ${icono} ${colorIcono} text-2xl`;

            if (mostrarCancelar) {
                dom.btnConfirmarCancelar.classList.remove('hidden');
            } else {
                dom.btnConfirmarCancelar.classList.add('hidden');
            }

            dom.modalConfirmacion.classList.remove('hidden');
            dom.btnConfirmarAceptar.focus();

            const limpiarListeners = () => {
                dom.btnConfirmarAceptar.removeEventListener('click', alAceptar);
                dom.btnConfirmarCancelar.removeEventListener('click', alCancelar);
                document.removeEventListener('keydown', alTecla);
            };

            const alAceptar = () => {
                limpiarListeners();
                dom.modalConfirmacion.classList.add('hidden');
                resolve(true);
            };

            const alCancelar = () => {
                limpiarListeners();
                dom.modalConfirmacion.classList.add('hidden');
                resolve(false);
            };

            const alTecla = (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    alCancelar();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    alAceptar();
                }
            };

            dom.btnConfirmarAceptar.addEventListener('click', alAceptar);
            dom.btnConfirmarCancelar.addEventListener('click', alCancelar);
            document.addEventListener('keydown', alTecla);
        });
    }

    function mostrarAlerta(titulo, mensaje, icono = 'fa-circle-info', colorIcono = 'text-brand') {
        return solicitarConfirmacion(titulo, mensaje, icono, colorIcono, false);
    }

    return {
        inicializar
    };
})();

window.addEventListener('DOMContentLoaded', () => {
    CapturaMuestreoApp.inicializar();
});
