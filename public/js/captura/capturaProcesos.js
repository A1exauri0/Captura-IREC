/**
 * capturaProcesos.js
 * Módulo que encapsula los procesos de flujo de captura:
 * renombrado, duplicación, inconsistencias, movimiento, rotación y carga de lotes.
 */
const CapturaProcesos = (() => {
    function mostrarCargando(mensaje) {
        const modal = document.getElementById('modalProcesando');
        const lblMensaje = document.getElementById('lblProcesandoMensaje');
        if (modal && lblMensaje) {
            lblMensaje.innerText = mensaje;
            modal.classList.remove('hidden');
        }
    }

    function ocultarCargando() {
        const modal = document.getElementById('modalProcesando');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function normalizarNombreBase(nombre) {
        return String(nombre || "")
            .replace(/\.pdf$/i, "")
            .replace(/-/g, "_")
            .trim();
    }

    /**
     * Reinicia el estado de captura para el PDF actual.
     * @param {object} ctx - Contexto de ejecución con referencias y estado.
     */
    function reiniciarCaptura(ctx) {
        ctx.accionEnEspera = "";
        const pdfActual = (ctx.listaPdfs && ctx.indiceActual >= 0) ? ctx.listaPdfs[ctx.indiceActual] : null;
        ctx.historialCapturas = pdfActual ? [normalizarNombreBase(pdfActual.nombre)] : [];
        ctx.pdfFueRenombrado = false;

        // Limpiar todos los inputs segmentados de Sellos y su preview
        document.querySelectorAll('.sello-seg').forEach(el => { el.value = ''; });
        const previewEl = document.getElementById('lblPreviewNombreSellos');
        if (previewEl) previewEl.textContent = '—';

        ctx.txtNombre.value = "";
        ctx.txtNombre.classList.remove(
            "border-red-500",
            "border-green-500",
            "border-amber-500",
        );
        ctx.txtNombre.classList.add("border-gray-300", "dark:border-gray-600");

        // Aplicar delegación detectada automáticamente y bloquear campo Del. hasta nueva autorización
        if (typeof window.aplicarDelegacionDetectada === 'function') {
            window.aplicarDelegacionDetectada(ctx, false);
        }
        if (typeof window.ajustarTodosInputsDinamicos === 'function') {
            window.ajustarTodosInputsDinamicos();
        }

        // Disparar evento input para sincronizar estado y valor anterior
        ctx.txtNombre.dispatchEvent(new Event('input'));

        if (ctx.loteActualId) {
            const selloDel = document.getElementById('selloDel');
            const selloSecc = document.getElementById('selloSecc');
            if (selloDel && selloDel.readOnly && selloSecc) selloSecc.focus();
            else if (selloDel) selloDel.focus();
            else ctx.txtNombre.focus();
        }

        const lblCaptura = document.getElementById("lblCaptura");
        if (lblCaptura) {
            lblCaptura.innerText = "Primera captura";
            lblCaptura.className = "px-2 py-0.5 rounded bg-sky-100 dark:bg-sky-900/50 text-sky-800 dark:text-sky-300 text-xs font-bold";
        }

        const btnRen = document.getElementById("btnRenombrar");
        if (btnRen) {
            btnRen.disabled = true;
            btnRen.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Renombrar';
        }

        const btnDup = document.getElementById("btnDuplicar");
        if (btnDup) {
            btnDup.disabled = true;
            btnDup.innerHTML = "Duplicar";
        }

        // Restablecer el mensaje de estado según la regla activa
        if (ctx.lblEstado) {
            const esRegla1 = ctx.rbRegla1 && ctx.rbRegla1.checked;
            ctx.lblEstado.innerHTML = esRegla1
                ? "Ejemplo: 01_2024_123456 (Folio de 1 a 6 dígitos)"
                : "Ejemplo: 01_01_01_01_00_00_2011_0090_00";
        }
    }

    const IDS_INPUTS_SELLOS = [
        'selloDel',
        'selloSecc',
        'selloLibro',
        'selloTomo',
        'selloVol',
        'selloFracc',
        'selloAnio',
        'selloInscr',
        'selloBis',
    ];

    const NOMBRES_CAMPOS_SELLOS = [
        'Del',
        'Secc',
        'Libro',
        'Tomo',
        'Vol',
        'Fracc',
        'Año',
        'Inscr',
        'Bis',
    ];

    /**
     * Extrae los 9 segmentos de un nombre de archivo de Sellos (ej. 15_05_01_00_00_00_2000_0155_00).
     */
    function extraerSegmentosNombreSellos(nombre) {
        const limpio = normalizarNombreBase(nombre);
        const partes = limpio.split('_');
        if (partes.length === 9) {
            return partes;
        }
        if (partes.length > 9) {
            // Reconstruir cuando Tomo o Fracc tienen guiones bajos internos
            const del = partes[0];
            const secc = partes[1];
            const libro = partes[2];
            const bis = partes[partes.length - 1];
            const inscr = partes[partes.length - 2];
            const anio = partes[partes.length - 3];
            let idxVol = 4;
            for (let i = 4; i <= partes.length - 5; i++) {
                if (/^\d{2,3}$/.test(partes[i])) {
                    idxVol = i;
                    break;
                }
            }
            const tomo = partes.slice(3, idxVol).join('_');
            const vol = partes[idxVol] || '00';
            const fracc = partes.slice(idxVol + 1, partes.length - 3).join('_');
            return [del, secc, libro, tomo, vol, fracc, anio, inscr, bis];
        }
        return null;
    }

    /**
     * Detecta qué índices de campo (0..8) son diferentes entre los PDFs del grupo con el mismo hash.
     */
    function detectarIndicesDiferentesGrupoHash(listaPdfs) {
        if (!Array.isArray(listaPdfs) || listaPdfs.length <= 1) return [];
        const matriz = listaPdfs.map(item => extraerSegmentosNombreSellos(item.nombre)).filter(Boolean);
        if (matriz.length <= 1) return [];
        const diferentes = [];
        for (let i = 0; i < 9; i++) {
            const base = (matriz[0][i] || '').toUpperCase();
            if (matriz.some(seg => (seg[i] || '').toUpperCase() !== base)) {
                diferentes.push(i);
            }
        }
        return diferentes;
    }

    /**
     * Si el PDF actual pertenece a un grupo de mismo hash, precarga automáticamente
     * en sus inputs correspondientes el número de registro (#selloInscr) Y cualquier
     * campo que sea diferente entre los PDFs del grupo (por ejemplo Año 2000 vs 2001),
     * resaltando visualmente las casillas que cambian para que el usuario las identifique al instante.
     */
    function precargarNumeroRegistroSiGrupoHash(ctx) {
        IDS_INPUTS_SELLOS.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.classList.remove('ring-2', 'ring-amber-500', 'bg-amber-50', 'dark:bg-amber-950/40');
            }
        });

        if (!ctx.listaPdfs || ctx.listaPdfs.length <= 1) return;
        const actual = ctx.listaPdfs[ctx.indiceActual];
        if (!actual) return;

        const segsActual = extraerSegmentosNombreSellos(actual.nombre);
        const indicesDif = detectarIndicesDiferentesGrupoHash(ctx.listaPdfs);

        // Siempre llenar Inscr. (idx 7) y además cualquier campo que sea diferente entre los PDFs del mismo hash (ej. Año idx 6)
        const indicesALlenar = new Set([7, ...indicesDif]);

        indicesALlenar.forEach(idx => {
            const inputEl = document.getElementById(IDS_INPUTS_SELLOS[idx]);
            if (!inputEl) return;
            let valorCampo = '';
            if (segsActual && segsActual[idx] !== undefined) {
                valorCampo = segsActual[idx];
            } else if (idx === 7 && actual.etiqueta_boton) {
                valorCampo = String(actual.etiqueta_boton).slice(0, 4);
            }
            if (valorCampo !== '') {
                inputEl.value = valorCampo;
                if (indicesDif.includes(idx)) {
                    inputEl.classList.add('ring-2', 'ring-amber-500', 'bg-amber-50', 'dark:bg-amber-950/40');
                    inputEl.title = `Campo diferente en este grupo de mismo PDF (${NOMBRES_CAMPOS_SELLOS[idx]}: ${valorCampo})`;
                }
                inputEl.dispatchEvent(new Event("input"));
            }
        });
    }

    /**
     * Solicita autorización del supervisor para marcar como "eliminar" (solo en BD) un PDF duplicado
     * dentro de la barra de Mismo PDF.
     */
    async function eliminarPdfDeGrupoHash(ctx, idxEliminar) {
        if (!ctx.listaPdfs || idxEliminar < 0 || idxEliminar >= ctx.listaPdfs.length) return;
        const item = ctx.listaPdfs[idxEliminar];

        // Si es un sello agregado temporal que aún no se ha guardado en BD, quitarlo directamente sin pedir autorización
        if (item.es_sello_agregado) {
            ctx.listaPdfs.splice(idxEliminar, 1);
            if (ctx.listaPdfs.length === 0 || ctx.listaPdfs.every(p => p.capturado)) {
                ctx.listaPdfs = [];
                renderizarBarraGrupoHash(ctx);
                await cargarLoteDesdeServidor(ctx);
                return;
            }
            if (ctx.indiceActual >= ctx.listaPdfs.length) {
                ctx.indiceActual = ctx.listaPdfs.length - 1;
            } else if (idxEliminar < ctx.indiceActual) {
                ctx.indiceActual = Math.max(0, ctx.indiceActual - 1);
            }
            const nuevoAct = ctx.listaPdfs[ctx.indiceActual];
            ctx.loteActualId = nuevoAct.sesion_id || ctx.loteActualId;
            ctx.loteActualNombre = nuevoAct.nombre || ctx.loteActualNombre;
            renderizarBarraGrupoHash(ctx);
            reiniciarCaptura(ctx);
            precargarNumeroRegistroSiGrupoHash(ctx);
            return;
        }

        const autorizado = await ctx.solicitarPinMaestro(
            'Eliminar PDF',
            `Marcar como eliminar en BD el registro duplicado: ${item.nombre}`
        );
        if (!autorizado) return;

        const metaPdf = {
            sesion_id: item.sesion_id || null,
            indice_pdf_id: item.indice_pdf_id || null,
            ruta_origen: item.ruta_origen || item.ruta || '',
            archivo_original: item.nombre || '',
            nuevo_nombre: item.nombre || '',
            paginas: item.paginas || 1,
            hash: item.hash || null,
            mismo_hash: true,
            tipo_documento: item.tipo || 'sellos',
            delegacion_id: ctx.delSeleccionada ? ctx.delSeleccionada.id : null,
            detalles: `Marcado como eliminar en grupo de mismo hash (${item.nombre}) con autorización del supervisor.`,
        };

        mostrarCargando("Marcando registro como Eliminar en BD...");
        try {
            const resp = await CapturaArchivos.eliminar(
                ctx.dirSeleccionado ? ctx.dirSeleccionado.id : 'irec',
                item.ruta_origen || item.ruta,
                metaPdf
            );
            ocultarCargando();
            if (!resp || !resp.ok) {
                throw new Error((resp && resp.mensaje) || 'No se pudo marcar el registro como eliminar.');
            }

            // Remover el elemento eliminado de la lista local
            ctx.listaPdfs.splice(idxEliminar, 1);

            if (ctx.listaPdfs.length === 0 || ctx.listaPdfs.every(p => p.capturado)) {
                ctx.listaPdfs = [];
                renderizarBarraGrupoHash(ctx);
                await cargarLoteDesdeServidor(ctx);
                return;
            }

            if (ctx.indiceActual >= ctx.listaPdfs.length) {
                ctx.indiceActual = ctx.listaPdfs.length - 1;
            } else if (idxEliminar < ctx.indiceActual) {
                ctx.indiceActual = Math.max(0, ctx.indiceActual - 1);
            }

            const nuevoActivo = ctx.listaPdfs[ctx.indiceActual];
            ctx.loteActualId = nuevoActivo.sesion_id || ctx.loteActualId;
            ctx.loteActualNombre = nuevoActivo.nombre || ctx.loteActualNombre;

            renderizarBarraGrupoHash(ctx);
            reiniciarCaptura(ctx);
            precargarNumeroRegistroSiGrupoHash(ctx);
        } catch (err) {
            ocultarCargando();
            console.error("Error al marcar como eliminar:", err);
            ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
        }
    }

    /**
     * Renderiza la barra de botones cuando varios PDFs comparten el mismo hash
     * para permitir capturar múltiples nombres/registros viendo el mismo PDF,
     * mostrando los campos que difieren y un botón "×" para marcar como eliminar con autorización.
     */
    function renderizarBarraGrupoHash(ctx) {
        const barra = document.getElementById("barraGrupoHash");
        const cont = document.getElementById("contenedorBotonesHash");
        if (!barra || !cont) return;

        // Mantener #barraGrupoHash siempre visible cuando existe #btnAgregarSello (incluso si solo hay 1 PDF)
        const tieneBtnAgregarSello = !!document.getElementById("btnAgregarSello");

        if (!ctx.listaPdfs || ctx.listaPdfs.length <= 1) {
            if (!tieneBtnAgregarSello) {
                barra.classList.add("hidden");
            } else {
                barra.classList.remove("hidden");
            }
            cont.innerHTML = "";
            return;
        }

        barra.classList.remove("hidden");
        cont.innerHTML = "";

        const indicesDif = detectarIndicesDiferentesGrupoHash(ctx.listaPdfs);

        ctx.listaPdfs.forEach((item, idx) => {
            const activo = idx === ctx.indiceActual;
            const segs = extraerSegmentosNombreSellos(item.nombre);
            let etiqueta = item.etiqueta_boton || String(idx + 1).padStart(4, "0");

            // Si difieren en otros campos además de Inscr (por ejemplo Año 2000 vs 2001), mostrarlo en el badge
            const extrasDif = indicesDif
                .filter(i => i !== 7 && segs && segs[i] !== undefined)
                .map(i => `${NOMBRES_CAMPOS_SELLOS[i]}: ${segs[i]}`);
            if (extrasDif.length > 0) {
                etiqueta = `${etiqueta} (${extrasDif.join(' · ')})`;
            }

            const wrapper = document.createElement("div");
            const yaCapturado = !!item.capturado;
            if (activo) {
                wrapper.className = yaCapturado
                    ? "inline-flex items-center rounded-md font-mono text-xs bg-emerald-600 text-white shadow ring-2 ring-emerald-400 overflow-hidden"
                    : "inline-flex items-center rounded-md font-mono text-xs bg-sky-600 text-white shadow ring-2 ring-sky-400 overflow-hidden";
            } else {
                wrapper.className = yaCapturado
                    ? "inline-flex items-center rounded-md font-mono text-xs bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-200 border border-emerald-400 dark:border-emerald-600 overflow-hidden"
                    : "inline-flex items-center rounded-md font-mono text-xs bg-white dark:bg-gray-800 text-indigo-800 dark:text-indigo-200 border border-indigo-300 dark:border-indigo-600 overflow-hidden";
            }

            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "px-2.5 py-1 font-extrabold hover:opacity-90 transition flex items-center gap-1";
            btn.innerHTML = yaCapturado
                ? `<i class="fa-solid fa-circle-check ${activo ? 'text-white' : 'text-emerald-600 dark:text-emerald-400'}"></i><span>${etiqueta}</span>`
                : `<span>${etiqueta}</span>`;
            btn.title = yaCapturado
                ? `${item.nombre || etiqueta} (Capturado ✓ — clic para volver a capturar)`
                : `Seleccionar ${item.nombre || etiqueta}`;
            btn.addEventListener("click", () => {
                console.log(" Nombre del PDF seleccionado:", item.nombre || null);
                console.log(
                    " PDFs del grupo (mismo hash):",
                    ctx.listaPdfs.map((p, i) => ({
                        orden: i + 1,
                        etiqueta: p.etiqueta_boton || String(i + 1).padStart(4, "0"),
                        nombre: p.nombre || null,
                        indice_pdf_id: p.indice_pdf_id || null,
                        seleccionado: i === idx,
                    }))
                );
                ctx.indiceActual = idx;
                ctx.loteActualId = item.sesion_id || ctx.loteActualId;
                ctx.loteActualNombre = item.nombre || ctx.loteActualNombre;
                renderizarBarraGrupoHash(ctx);
                reiniciarCaptura(ctx);
                precargarNumeroRegistroSiGrupoHash(ctx);
            });

            const btnEliminar = document.createElement("button");
            btnEliminar.type = "button";
            btnEliminar.className = activo
                ? "px-1.5 py-1 font-extrabold text-xs bg-sky-700 hover:bg-red-600 text-white border-l border-sky-400/50 transition"
                : "px-1.5 py-1 font-extrabold text-xs hover:bg-red-600 hover:text-white text-red-500 border-l border-indigo-200 dark:border-indigo-700 transition";
            btnEliminar.innerHTML = "&times;";
            btnEliminar.title = `Marcar ${item.nombre} como Eliminar (requiere autorización)`;
            btnEliminar.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                eliminarPdfDeGrupoHash(ctx, idx);
            });

            wrapper.appendChild(btn);
            wrapper.appendChild(btnEliminar);
            cont.appendChild(wrapper);
        });
    }

    /**
     * Agrega una nueva ranura de sello sobre el mismo PDF actual para registrar un sello adicional
     * (igual que cuando vienen varios PDFs con el mismo hash).
     */
    function agregarSelloAlPdfActual(ctx) {
        if (!ctx.listaPdfs || ctx.listaPdfs.length === 0) return;
        const pdfBase = ctx.listaPdfs[ctx.indiceActual] || ctx.listaPdfs[0];
        const cantAgregados = ctx.listaPdfs.filter(p => p.es_sello_agregado).length + 1;

        const nuevoSello = {
            sesion_id: pdfBase.sesion_id || ctx.loteActualId || null,
            indice_pdf_id: pdfBase.indice_pdf_id || null,
            ruta: pdfBase.ruta,
            ruta_origen: pdfBase.ruta_origen || pdfBase.ruta || '',
            nombre: '',
            nombre_base_pdf: pdfBase.nombre_base_pdf || pdfBase.nombre || '',
            etiqueta_boton: `+ Sello ${cantAgregados}`,
            paginas: pdfBase.paginas || 1,
            hash: pdfBase.hash || null,
            tipo: pdfBase.tipo || 'sellos',
            clasificacion: pdfBase.clasificacion || null,
            es_sello_agregado: true,
        };

        ctx.listaPdfs.push(nuevoSello);
        ctx.indiceActual = ctx.listaPdfs.length - 1;
        renderizarBarraGrupoHash(ctx);
        reiniciarCaptura(ctx);
        ctx.lblEstado.innerHTML = '<span class="text-indigo-600 dark:text-indigo-400 font-bold"><i class="fa-solid fa-plus-circle mr-1"></i> Nuevo sello sobre el mismo PDF: capture el nombre (se validará con doble captura).</span>';
        console.log("➕ Sello adicional agregado al grupo del PDF actual:", nuevoSello);
    }

    /**
     * Precarga en segundo plano el 2.º PDF que trabajará el capturista (reservándolo en archivos_sesiones
     * y descargando su Blob PDF en memoria) para que el cambio al terminar el actual sea instantáneo.
     */
    async function precargarSiguientePdfEnSegundoPlano(ctx, datosPrefetchExistentes = null) {
        if (typeof window !== 'undefined' && window.MODO_OPERACION_CAPTURA === 'muestreo') return;
        if (ctx.colaPrefetch && ctx.colaPrefetch.resp) return;
        if (ctx.precargandoEnCurso) return;

        ctx.precargandoEnCurso = true;
        try {
            const dirId = ctx.dirSeleccionado ? ctx.dirSeleccionado.id : 'irec';
            const resp = datosPrefetchExistentes || await CapturaArchivos.buscarPdf(dirId, null, { prefetch: true });
            if (!resp || !resp.ok || !Array.isArray(resp.archivos) || resp.archivos.length === 0) {
                return;
            }

            let blobUrl = null;
            try {
                const primerPdf = resp.archivos[0];
                const url = CapturaArchivos.urlVerPdf(dirId, primerPdf.ruta);
                const rBlob = await fetch(url, { credentials: 'same-origin' });
                if (rBlob.ok) {
                    const blob = await rBlob.blob();
                    blobUrl = URL.createObjectURL(blob);
                }
            } catch (eBlob) {
                console.warn("No se pudo pre-descargar el Blob del 2.º PDF en segundo plano:", eBlob);
            }

            ctx.colaPrefetch = { resp, blobUrl };
            console.log(
                "⚡ [2.º PDF en sesión listo en segundo plano]:",
                resp.archivos.map(a => a.nombre)
            );
        } catch (err) {
            console.warn("Aviso en precarga de 2.º PDF:", err);
        } finally {
            ctx.precargandoEnCurso = false;
        }
    }

    /**
     * Carga el PDF actual en el iframe visor.
     * @param {object} ctx - Contexto de ejecución.
     */
    function cargarPdfActual(ctx) {
        renderizarBarraGrupoHash(ctx);
        if (ctx.listaPdfs.length === 0 || ctx.indiceActual < 0) {
            ctx.lblEstado.innerHTML =
                '<span class="text-gray-500">No hay más PDFs en este lote</span>';
            document.getElementById("pdfViewer").src = "about:blank";
            return;
        }
        if (ctx.indiceActual >= ctx.listaPdfs.length) {
            ctx.indiceActual = ctx.listaPdfs.length - 1;
        }

        const pdf = ctx.listaPdfs[ctx.indiceActual];
        const url = CapturaArchivos.urlVerPdf(ctx.dirSeleccionado.id, pdf.ruta);

        console.log(' Nombre del PDF:', pdf.nombre || null);
        if (ctx.listaPdfs.length > 1) {
            console.log(
                ' Grupo de PDFs extraídos (mismo hash):',
                ctx.listaPdfs.map((p, i) => `${i + 1}. ${p.nombre}${i === ctx.indiceActual ? ' [ACTIVO]' : ''}`)
            );
        }
        console.log(' Registro PDF obtenido para captura:', {
            sesion_id: pdf.sesion_id || ctx.loteActualId || null,
            indice_pdf_id: pdf.indice_pdf_id || null,
            nombre_archivo: pdf.nombre || null,
            ruta_origen: pdf.ruta_origen || pdf.ruta || null,
            paginas: pdf.paginas || null,
            hash: pdf.hash || null,
            tipo: pdf.tipo || 'sellos',
            clasificacion: pdf.clasificacion || null,
            delegacion: ctx.delSeleccionada || null,
        });

        // Si el Blob ya fue pre-descargado en segundo plano, mostrarlo de forma instantánea (0 ms)
        if (ctx.blobPrecargadoActual) {
            const blobUrlListo = ctx.blobPrecargadoActual;
            ctx.blobPrecargadoActual = null;
            const visor = document.getElementById("pdfViewer");
            if (visor.dataset.blobUrl && visor.dataset.blobUrl !== blobUrlListo) {
                URL.revokeObjectURL(visor.dataset.blobUrl);
            }
            visor.dataset.blobUrl = blobUrlListo;
            visor.style.transform = "none";
            visor.dataset.rotation = "0";
            visor.src = blobUrlListo;
            ocultarCargando();
            reiniciarCaptura(ctx);
            precargarNumeroRegistroSiGrupoHash(ctx);
            precargarSiguientePdfEnSegundoPlano(ctx);
            return;
        }

        mostrarCargando("Cargando PDF...");

        // Descargar el PDF una sola vez como Blob y mostrarlo en el iframe (evita doble descarga de archivos grandes)
        fetch(url, { credentials: 'same-origin' })
            .then(async response => {
                if (!response.ok) {
                    const txt = await response.text().catch(() => '');
                    throw new Error(`HTTP ${response.status}: ${txt || 'El archivo no se encuentra o no es accesible.'}`);
                }
                return response.blob();
            })
            .then(blob => {
                const visor = document.getElementById("pdfViewer");
                if (visor.dataset.blobUrl) {
                    URL.revokeObjectURL(visor.dataset.blobUrl);
                }
                const blobUrl = URL.createObjectURL(blob);
                visor.dataset.blobUrl = blobUrl;
                visor.style.transform = "none";
                visor.dataset.rotation = "0";

                visor.onload = function () {
                    ocultarCargando();
                    visor.onload = null;
                };
                visor.src = blobUrl;
                // Fallback por si el visor PDF del navegador no dispara onload en blob:
                setTimeout(() => ocultarCargando(), 800);

                reiniciarCaptura(ctx);
                precargarNumeroRegistroSiGrupoHash(ctx);
                precargarSiguientePdfEnSegundoPlano(ctx);
            })
            .catch(async err => {
                ocultarCargando();
                console.error(" No se pudo cargar el PDF:", err);

                // Mostrar modal de confirmación si no se detecta el archivo
                const pasarSiguiente = await ctx.solicitarConfirmacion(
                    "Archivo no encontrado",
                    `No se pudo leer o encontrar el documento actual (${err.message}). ¿Desea pasar al siguiente?`,
                    "fa-triangle-exclamation",
                    "text-red-500"
                );
                if (pasarSiguiente) {
                    avanzarSiguiente(ctx);
                } else {
                    ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
                }
            });
    }

    /**
     * Remueve el archivo actual de la cola y avanza al siguiente,
     * o busca un nuevo lote si se terminó.
     * @param {object} ctx - Contexto de ejecución.
     */
    async function avanzarSiguiente(ctx) {
        const anterior = ctx.listaPdfs[ctx.indiceActual] || null;

        // Si hay múltiples registros en el mismo PDF (mismo hash o sellos agregados):
        // NO eliminar el badge capturado; marcarlo con palomita (capturado = true) para que pueda volver a capturarse si lo necesita,
        // y pasar al siguiente PDF físico únicamente cuando TODOS estén marcados como capturados.
        if (ctx.listaPdfs.length > 1 && anterior) {
            anterior.capturado = true;

            // Buscar el siguiente registro pendiente (!capturado) dentro del mismo PDF
            let siguienteIdx = -1;
            for (let offset = 1; offset <= ctx.listaPdfs.length; offset++) {
                const candIdx = (ctx.indiceActual + offset) % ctx.listaPdfs.length;
                if (!ctx.listaPdfs[candIdx].capturado) {
                    siguienteIdx = candIdx;
                    break;
                }
            }

            if (siguienteIdx !== -1) {
                ctx.indiceActual = siguienteIdx;
                const siguiente = ctx.listaPdfs[ctx.indiceActual];
                ctx.loteActualId = siguiente.sesion_id || ctx.loteActualId;
                ctx.loteActualNombre = siguiente.nombre || ctx.loteActualNombre;
                renderizarBarraGrupoHash(ctx);
                reiniciarCaptura(ctx);
                precargarNumeroRegistroSiGrupoHash(ctx);
                console.log(" Nombre del PDF:", siguiente.nombre || siguiente.etiqueta_boton || null);
                return;
            }

            // Todos los registros del grupo de mismo PDF ya tienen palomita -> avanzar al siguiente PDF físico
            ctx.listaPdfs = [];
        } else {
            ctx.listaPdfs.splice(ctx.indiceActual, 1);
        }

        if (ctx.listaPdfs.length > 0) {
            if (ctx.indiceActual >= ctx.listaPdfs.length) {
                ctx.indiceActual = 0;
            }
            const siguiente = ctx.listaPdfs[ctx.indiceActual];
            ctx.loteActualId = siguiente.sesion_id || ctx.loteActualId;
            ctx.loteActualNombre = siguiente.nombre || ctx.loteActualNombre;
            cargarPdfActual(ctx);
        } else {
            renderizarBarraGrupoHash(ctx);
            // Si ya tenemos el 2.º PDF precargado en sesión y memoria, promoverlo al instante
            if (ctx.colaPrefetch && ctx.colaPrefetch.resp) {
                const pre = ctx.colaPrefetch;
                ctx.colaPrefetch = null;
                const resp = pre.resp;
                if (resp.delegacion) {
                    ctx.delSeleccionada = resp.delegacion;
                    localStorage.setItem('val_ultima_delegacion_id', resp.delegacion.id);
                    localStorage.setItem('val_delegacion_nombre', resp.delegacion.nombre);
                    localStorage.setItem('val_delegacion_clave', resp.delegacion.clave);
                }
                ctx.loteActualId = resp.lote_id;
                ctx.loteActualNombre = resp.lote;
                ctx.loteRutaRelativa = resp.ruta_relativa;
                ctx.listaPdfs = resp.archivos;
                ctx.indiceActual = 0;
                ctx.blobPrecargadoActual = pre.blobUrl || null;
                const nombreDelFinal = ctx.delSeleccionada ? ctx.delSeleccionada.nombre : 'Delegación';
                ctx.lblTotalPdf.innerText = `${nombreDelFinal}`;
                cargarPdfActual(ctx);
                return;
            }
            await cargarLoteDesdeServidor(ctx);
        }
    }

    /**
     * Valida el texto ingresado en el input de captura según la regla activa.
     * @param {object} ctx - Contexto de ejecución.
     * @returns {boolean} true si el formato es correcto, false en caso contrario.
     */
    function validarInput(ctx) {
        if (!ctx.delSeleccionada) {
            const delId = localStorage.getItem("val_ultima_delegacion_id");
            const delClave = localStorage.getItem("val_delegacion_clave");
            const delNombre = localStorage.getItem("val_delegacion_nombre");

            if (delId) {
                ctx.delSeleccionada = {
                    id: delId,
                    clave: delClave,
                    nombre: delNombre,
                };
            } else {
                ctx.lblEstado.innerHTML =
                    '<span class="text-red-500 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Seleccione una delegación primero</span>';
                ctx.txtNombre.classList.add("border-red-500");
                ctx.txtNombre.classList.remove(
                    "border-green-500",
                    "border-gray-300",
                );
                return false;
            }
        }

        const val = ctx.txtNombre.value.trim();
        const prefijo = ctx.delSeleccionada ? ctx.delSeleccionada.clave + "_" : "";

        if (val === "" || val === prefijo) {
            ctx.txtNombre.classList.remove(
                "border-red-500",
                "border-green-500",
                "border-amber-500",
            );
            ctx.txtNombre.classList.add(
                "border-gray-300",
                "dark:border-gray-600",
            );
            ctx.lblEstado.innerHTML = ctx.rbRegla1.checked
                ? "Ejemplo: 01_2024_123456 (Folio de 1 a 6 dígitos)"
                : "Ejemplo: 01_01_01_01_00_00_2011_0090_00";

            const btnRen0 = document.getElementById("btnRenombrar");
            const btnDup0 = document.getElementById("btnDuplicar");
            if (btnRen0) btnRen0.disabled = true;
            if (btnDup0) btnDup0.disabled = true;
            return false;
        }

        const resultado = CapturaReglas.validarNombre(
            val,
            ctx.rbRegla1.checked,
            ctx.rbRegla2.checked,
            ctx.delSeleccionada.clave,
            ctx.delSeleccionada.nombre,
            ctx.catalogoAnios,
        );

        if (resultado.valido) {
            // En Duplicar se mantiene validación inmediata de confirmación;
            // en Renombrar (segunda captura) se permite capturar libremente para validar al enviar
            // contra la primera captura o contra el nombre original.
            if (ctx.accionEnEspera === "Duplicar") {
                const primerCaptura = ctx.historialCapturas[1] || "";
                if (val.toLowerCase() !== primerCaptura.toLowerCase()) {
                    ctx.lblEstado.innerHTML = `<span class="text-amber-500 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> No coincide con la primera captura</span>`;
                    ctx.txtNombre.classList.remove(
                        "border-green-500",
                        "border-red-500",
                    );
                    ctx.txtNombre.classList.add("border-amber-500");
                    const btnRen1 = document.getElementById("btnRenombrar");
                    const btnDup1 = document.getElementById("btnDuplicar");
                    if (btnRen1) btnRen1.disabled = true;
                    if (btnDup1) btnDup1.disabled = true;
                    return false;
                } else {
                    ctx.lblEstado.innerHTML = `<span class="text-green-600 dark:text-green-400 font-bold">La confirmación coincide ✅</span>`;
                    ctx.txtNombre.classList.add("border-green-500");
                    ctx.txtNombre.classList.remove(
                        "border-red-500",
                        "border-amber-500",
                        "border-gray-300",
                        "dark:border-gray-600",
                    );
                }
            } else if (ctx.accionEnEspera === "Renombrar") {
                ctx.lblEstado.innerHTML = `<span class="text-amber-600 dark:text-amber-400 font-bold">Segunda captura lista para validar</span>`;
                ctx.txtNombre.classList.add("border-amber-500");
                ctx.txtNombre.classList.remove(
                    "border-red-500",
                    "border-green-500",
                    "border-gray-300",
                    "dark:border-gray-600",
                );
            } else {
                ctx.lblEstado.innerHTML = `<span class="text-green-600 dark:text-green-400 font-bold">${resultado.mensaje}</span>`;
                ctx.txtNombre.classList.add("border-green-500");
                ctx.txtNombre.classList.remove(
                    "border-red-500",
                    "border-amber-500",
                    "border-gray-300",
                    "dark:border-gray-600",
                );
            }

            const btnRenOk = document.getElementById("btnRenombrar");
            const btnDupOk = document.getElementById("btnDuplicar");
            if (ctx.accionEnEspera === "Renombrar") {
                if (btnRenOk) btnRenOk.disabled = false;
                if (btnDupOk) btnDupOk.disabled = true;
            } else if (ctx.accionEnEspera === "Duplicar") {
                if (btnRenOk) btnRenOk.disabled = true;
                if (btnDupOk) btnDupOk.disabled = false;
            } else {
                if (btnRenOk) btnRenOk.disabled = false;
                if (btnDupOk) btnDupOk.disabled = false;
            }
            return true;
        } else {
            ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> ${resultado.mensaje}</span>`;
            ctx.txtNombre.classList.remove(
                "border-green-500",
                "border-amber-500",
            );
            ctx.txtNombre.classList.add("border-red-500");
            const btnRenErr = document.getElementById("btnRenombrar");
            const btnDupErr = document.getElementById("btnDuplicar");
            if (btnRenErr) btnRenErr.disabled = true;
            if (btnDupErr) btnDupErr.disabled = true;
            return false;
        }
    }

    /**
     * Busca un lote libre en el servidor y lo asigna al estado del contexto.
     * @param {object} ctx - Contexto de ejecución.
     */
    async function cargarLoteDesdeServidor(ctx) {
        if (!ctx.dirSeleccionado) {
            ctx.dirSeleccionado = { id: 'irec', nombre: 'IREC', reglas: ['Regla2'] };
        }

        const delegacionId = ctx.delSeleccionada ? ctx.delSeleccionada.id : null;
        const nombreDel = ctx.delSeleccionada ? ctx.delSeleccionada.nombre : "Búsqueda Automática";

        ctx.lblTotalPdf.innerText = `${nombreDel} - Buscando PDF...`;
        ctx.lblEstado.innerHTML = '<span class="text-blue-500"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Buscando PDF libre...</span>';
        mostrarCargando("Buscando PDF libre...");

        try {
            const resp = await CapturaArchivos.buscarPdf(
                ctx.dirSeleccionado.id,
                delegacionId,
            );
            console.log(' Respuesta /captura/buscar-pdf:', resp);
            if (!resp.ok) {
                ocultarCargando();
                ctx.lblTotalPdf.innerText =
                    resp.mensaje || "Sin archivos PDF disponibles";
                ctx.lblEstado.innerHTML =
                    `<span class="text-red-500 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> ${resp.mensaje || 'No hay más PDFs'}</span>`;
                document.getElementById("pdfViewer").src = "about:blank";
                return;
            }

            if (resp.delegacion) {
                ctx.delSeleccionada = resp.delegacion;
                localStorage.setItem('val_ultima_delegacion_id', resp.delegacion.id);
                localStorage.setItem('val_delegacion_nombre', resp.delegacion.nombre);
                localStorage.setItem('val_delegacion_clave', resp.delegacion.clave);
            }

            ctx.loteActualId = resp.lote_id;
            ctx.loteActualNombre = resp.lote;
            ctx.loteRutaRelativa = resp.ruta_relativa;
            ctx.listaPdfs = resp.archivos;
            ctx.indiceActual = 0;

            const nombreDelFinal = ctx.delSeleccionada ? ctx.delSeleccionada.nombre : 'Delegación';
            if (resp.modo === 'muestreo' && resp.muestreo_info) {
                const mi = resp.muestreo_info;
                ctx.lblTotalPdf.innerText = `${nombreDelFinal} · Muestreo #${mi.control_id} (${mi.revisados}/${mi.muestra} · Err: ${mi.errores_detectados}/${mi.errores_permitidos})`;
            } else {
                ctx.lblTotalPdf.innerText = `${nombreDelFinal}`;
            }
            if (resp.prefetch_data && (!ctx.colaPrefetch || !ctx.colaPrefetch.resp)) {
                precargarSiguientePdfEnSegundoPlano(ctx, resp.prefetch_data);
            }

            reiniciarCaptura(ctx);
            cargarPdfActual(ctx);
        } catch (err) {
            ocultarCargando();
            console.error(' Error en cargarLoteDesdeServidor:', err);
            if (String(err.message || '').includes('fuera_horario')) {
                let msg = 'Tu horario laboral ha finalizado o aún no inicia.';
                try {
                    const idxJson = err.message.indexOf('{');
                    if (idxJson !== -1) {
                        const parsed = JSON.parse(err.message.slice(idxJson));
                        if (parsed.mensaje) msg = parsed.mensaje;
                    }
                } catch (_) { }
                if (typeof window.bloquearPorHorario === 'function') {
                    window.bloquearPorHorario(msg);
                    return;
                }
            }
            ctx.lblTotalPdf.innerText = "Error al buscar PDF";
            ctx.lblEstado.innerHTML = `<span class="text-red-500">${err.message}</span>`;
        }
    }

    /**
     * Procesa la acción de Renombrar / Validar.
     * @param {object} ctx - Contexto de ejecución.
     */
    async function procesarRenombrar(ctx) {
        if (ctx.accionEnEspera === "Duplicar") return;
        if (ctx.listaPdfs.length === 0 || ctx.indiceActual < 0) return;
        const pdf = ctx.listaPdfs[ctx.indiceActual];
        const nombreOriginal = normalizarNombreBase(pdf.nombre);
        const capturado = normalizarNombreBase(ctx.txtNombre.value);

        if (!validarInput(ctx)) return;

        // Asegurar que historialCapturas tenga siempre el nombre original en índice 0
        if (!Array.isArray(ctx.historialCapturas) || ctx.historialCapturas.length === 0) {
            ctx.historialCapturas = [nombreOriginal];
        }

        // Metadatos reales del registro de Stellum (indice_archivos_pdfs) para guardar en auditoria_sellos o archivos_muestreos
        const metaPdf = {
            sesion_id: pdf.sesion_id || ctx.loteActualId || null,
            muestreo_item_id: pdf.muestreo_item_id || null,
            modo_operacion: (typeof window !== 'undefined' && window.MODO_OPERACION_CAPTURA) ? window.MODO_OPERACION_CAPTURA : 'captura',
            indice_pdf_id: pdf.indice_pdf_id || null,
            ruta_origen: pdf.ruta_origen || '',
            archivo_original: pdf.nombre || '',
            paginas: pdf.paginas || 1,
            hash: pdf.hash || null,
            mismo_hash: !!(ctx.listaPdfs && ctx.listaPdfs.length > 1),
            tipo_documento: pdf.tipo || 'sellos',
            delegacion_id: ctx.delSeleccionada ? ctx.delSeleccionada.id : null,
        };

        // Verificar si coincide exactamente con el nombre original o si solo varía por los ceros a la izquierda en el campo 3 (Libro)
        let coincideConOriginal = (capturado.toLowerCase() === nombreOriginal.toLowerCase());
        let notaCerosLibro = null;
        if (!coincideConOriginal && nombreOriginal !== '') {
            const pOrig = extraerSegmentosNombreSellos(nombreOriginal);
            const pCap  = extraerSegmentosNombreSellos(capturado);
            if (
                pOrig && pCap &&
                /^\d+$/.test(pOrig[2]) &&
                /^\d+$/.test(pCap[2]) &&
                parseInt(pOrig[2], 10) === parseInt(pCap[2], 10)
            ) {
                const restoIguales = pOrig.every((seg, i) => i === 2 || seg.toLowerCase() === (pCap[i] || '').toLowerCase());
                if (restoIguales) {
                    coincideConOriginal = true;
                    notaCerosLibro = `Validado por diferencia de ceros (0) en el campo Libro (${pOrig[2]} -> ${pCap[2]}).`;
                }
            }
        }

        // Caso A: Coincide con el nombre original (o solo varía por relleno de 0 en Libro)
        if (coincideConOriginal) {
            const rutaArchivoOriginal = pdf.ruta_origen || pdf.ruta;
            const detalleValidacion = notaCerosLibro
                ? notaCerosLibro
                : (ctx.accionEnEspera === "Renombrar"
                    ? 'Validado en segunda captura (coincide con nombre original)'
                    : 'Validado en primera captura');

            mostrarCargando("Validando captura...");
            ctx.lblEstado.innerHTML =
                '<span class="text-green-600 dark:text-green-400 font-bold">Validando... ✅</span>';

            try {
                const respVal = await CapturaArchivos.validar(
                    ctx.dirSeleccionado.id,
                    rutaArchivoOriginal,
                    ctx.delSeleccionada ? ctx.delSeleccionada.id : null,
                    ctx.loteActualNombre,
                    { ...metaPdf, nuevo_nombre: capturado, detalles: detalleValidacion },
                );
                ocultarCargando();
                reiniciarCaptura(ctx);

                if (respVal && respVal.fuera_horario_despues_de_guardar) {
                    await ctx.solicitarConfirmacion(
                        "Fin de horario laboral",
                        respVal.aviso_horario || "Tu turno ha finalizado. Este último registro se guardó correctamente, pero ya no podrás capturar más.",
                        "fa-clock",
                        "text-amber-500",
                        false,
                    );
                    if (typeof window.bloquearPorHorario === 'function') {
                        window.bloquearPorHorario(respVal.aviso_horario);
                    }
                    return;
                }

                avanzarSiguiente(ctx);
            } catch (err) {
                ocultarCargando();
                console.error("Error al validar: " + err.message);
                ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
            }
            return;
        }

        // Caso B: Primera captura distinta al nombre original -> solicitar segunda captura
        if (ctx.historialCapturas.length === 1) {
            ctx.historialCapturas.push(capturado);
            ctx.accionEnEspera = "Renombrar";

            const lblCaptura = document.getElementById("lblCaptura");
            if (lblCaptura) {
                lblCaptura.innerText = "Segunda captura";
                lblCaptura.className = "px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300 text-xs font-bold";
            }
            document.querySelectorAll('.sello-seg').forEach(el => { el.value = ''; });
            const previewEl = document.getElementById('lblPreviewNombreSellos');
            if (previewEl) previewEl.textContent = '—';
            ctx.txtNombre.value = "";
            if (typeof window.aplicarDelegacionDetectada === 'function') {
                window.aplicarDelegacionDetectada(ctx, !!ctx.delegacionDesbloqueada);
            }
            if (typeof window.ajustarTodosInputsDinamicos === 'function') {
                window.ajustarTodosInputsDinamicos();
            }
            ctx.txtNombre.dispatchEvent(new Event('input'));
            ctx.lblEstado.innerHTML = '<span class="text-amber-600 dark:text-amber-400 font-bold">Segunda captura: vuelva a capturar el nombre para confirmar.</span>';

            const selloDel = document.getElementById('selloDel');
            const selloSecc = document.getElementById('selloSecc');
            if (selloDel && selloDel.readOnly && selloSecc) selloSecc.focus();
            else if (selloDel) selloDel.focus();
            else ctx.txtNombre.focus();
            const btnDup = document.getElementById("btnDuplicar");
            if (btnDup) btnDup.disabled = true;
            return;
        }

        // Caso C: Segunda captura -> si coincide con primera captura, pasa directo con ese nombre;
        // si no coincide ni con la primera captura ni con el original (Caso A ya evaluado), mostrar alerta.
        if (
            ctx.historialCapturas.length >= 2 &&
            ctx.accionEnEspera === "Renombrar"
        ) {
            const primerCaptura = ctx.historialCapturas[1];
            if (capturado.toLowerCase() === primerCaptura.toLowerCase()) {
                const rutaOriginal = pdf.ruta_origen || pdf.ruta;
                const nuevoNombre = capturado;

                const btnRen = document.getElementById("btnRenombrar");
                if (btnRen) {
                    btnRen.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Guardando...';
                    btnRen.disabled = true;
                }
                mostrarCargando("Guardando captura...");
                ctx.lblEstado.innerHTML =
                    '<span class="text-green-600 dark:text-green-400 font-bold"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Guardando...</span>';

                const esAgregado = !!pdf.es_sello_agregado;
                const accionApi = esAgregado ? CapturaArchivos.agregarSello : CapturaArchivos.renombrar;
                const payloadExtra = {
                    ...metaPdf,
                    archivo_original: pdf.nombre_base_pdf || pdf.nombre || nuevoNombre,
                    quedan_pendientes_en_pdf: ctx.listaPdfs.length > 1,
                    detalles: esAgregado
                        ? 'Sello adicional agregado desde el mismo PDF en doble captura'
                        : 'Renombrado y validado en doble captura',
                };

                accionApi(
                    ctx.dirSeleccionado.id,
                    rutaOriginal,
                    nuevoNombre,
                    payloadExtra,
                )
                    .then(async (respRen) => {
                        if (respRen.ok) {
                            ocultarCargando();
                            if (respRen.revisar && (esAgregado || respRen.motivo_revision)) {
                                await ctx.solicitarConfirmacion(
                                    "Enviado a Revisión",
                                    respRen.motivo_revision || respRen.mensaje || "El registro fue enviado a revisión porque ya existe un PDF con ese nombre en indice_archivos_pdfs.",
                                    "fa-triangle-exclamation",
                                    "text-amber-500",
                                    false,
                                );
                            }
                            reiniciarCaptura(ctx);
                            if (respRen.fuera_horario_despues_de_guardar) {
                                ocultarCargando();
                                await ctx.solicitarConfirmacion(
                                    "Fin de horario laboral",
                                    respRen.aviso_horario || "Tu turno ha finalizado. Este último registro se guardó correctamente, pero ya no podrás capturar más.",
                                    "fa-clock",
                                    "text-amber-500",
                                    false,
                                );
                                if (typeof window.bloquearPorHorario === 'function') {
                                    window.bloquearPorHorario(respRen.aviso_horario);
                                }
                                return;
                            }
                            avanzarSiguiente(ctx);
                        }
                    })
                    .catch((err) => {
                        console.error("Error en renombrar: " + err.message);
                        ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
                    })
                    .finally(() => {
                        ocultarCargando();
                        if (btnRen) {
                            btnRen.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Renombrar';
                        }
                    });
                return;
            } else {
                await ctx.solicitarConfirmacion(
                    "No coinciden las capturas",
                    "Las capturas no coinciden entre sí ni con el nombre original. Verifique e intente de nuevo.",
                    "fa-triangle-exclamation",
                    "text-red-500",
                    false,
                );
                reiniciarCaptura(ctx);
                precargarNumeroRegistroSiGrupoHash(ctx);
            }
        }
    }

    /**
     * Procesa la acción de Duplicar.
     * @param {object} ctx - Contexto de ejecución.
     */
    async function procesarDuplicar(ctx) {
        if (ctx.accionEnEspera === "Renombrar") return;
        if (ctx.listaPdfs.length === 0 || ctx.indiceActual < 0) return;
        const pdf = ctx.listaPdfs[ctx.indiceActual];
        const capturado = ctx.txtNombre.value.trim().replace(/-/g, "_");

        if (!validarInput(ctx)) return;

        if (ctx.historialCapturas.length === 1) {
            ctx.historialCapturas.push(capturado);
            ctx.accionEnEspera = "Duplicar";

            const lblCaptura = document.getElementById("lblCaptura");
            if (lblCaptura) {
                lblCaptura.innerText = "Confirmación";
                lblCaptura.className = "text-lg font-bold text-amber-500";
            }
            ctx.lblEstado.innerText = "Confirme el nombre para duplicar.";
            ctx.txtNombre.value = "";
            ctx.txtNombre.focus();
            document.getElementById("btnRenombrar").disabled = true;
            return;
        }

        if (
            ctx.historialCapturas.length === 2 &&
            ctx.accionEnEspera === "Duplicar"
        ) {
            const primerCaptura = ctx.historialCapturas[1];
            if (capturado.toLowerCase() === primerCaptura.toLowerCase()) {
                const rutaOriginal = pdf.ruta;
                const nombreOriginal = pdf.nombre;
                const nuevoNombreDuplicado = capturado;

                const btnDup = document.getElementById("btnDuplicar");
                if (btnDup) {
                    btnDup.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Duplicando...';
                    btnDup.disabled = true;
                }
                mostrarCargando("Duplicando...");
                ctx.lblEstado.innerHTML =
                    '<span class="text-green-600 dark:text-green-400 font-bold"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Duplicando...</span>';

                CapturaArchivos.duplicar(
                    ctx.dirSeleccionado.id,
                    rutaOriginal,
                    nuevoNombreDuplicado,
                    ctx.delSeleccionada.id,
                    ctx.loteActualNombre,
                )
                    .then((resp) => {
                        if (resp.ok) {
                            CapturaAuditoria.registrar({
                                id: resp.log_id || null, // avoid undefined ID
                                accion: "Duplicar",
                                disco: ctx.dirSeleccionado.id,
                                delegacion_id: ctx.delSeleccionada.id,
                                nombre_lote: ctx.loteActualNombre,
                                archivo_original: nombreOriginal + ".pdf",
                                archivo_nuevo: nuevoNombreDuplicado + ".pdf",
                                detalles: resp.duplicado_existente
                                    ? "Duplicado (ya existía en Calidad)"
                                    : "Copia enviada a Calidad",
                                paginas: resp.paginas || 0,
                            });
                        }
                    })
                    .then(() => {
                        // Limpieza de coincidencia redundante local de forma inmediata
                        const indexCoincidente = ctx.listaPdfs.findIndex(
                            (p) =>
                                p.nombre.replace(/-/g, "_").toLowerCase() ===
                                nuevoNombreDuplicado.toLowerCase() &&
                                p.ruta !== rutaOriginal,
                        );
                        if (indexCoincidente >= 0) {
                            ctx.listaPdfs.splice(indexCoincidente, 1);
                            if (indexCoincidente <= ctx.indiceActual) {
                                ctx.indiceActual = Math.max(0, ctx.indiceActual - 1);
                            }
                        }
                        reiniciarCaptura(ctx);
                        cargarPdfActual(ctx);
                    })
                    .catch((err) => {
                        console.error("Error al duplicar: " + err.message);
                        ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
                    })
                    .finally(() => {
                        ocultarCargando();
                        if (btnDup) {
                            btnDup.innerHTML = "Duplicar";
                        }
                    });
            } else {
                await ctx.solicitarConfirmacion(
                    "Error de Coincidencia",
                    "La confirmación no coincide con el nombre ingresado. Verifique e intente de nuevo.",
                    "fa-triangle-exclamation",
                    "text-red-500",
                    false,
                );
                reiniciarCaptura(ctx);
            }
        }
    }

    async function moverInconsistencia(ctx) {
        if (ctx.listaPdfs.length === 0 || ctx.indiceActual < 0) return;
        const pdf = ctx.listaPdfs[ctx.indiceActual];

        const autorizado = await ctx.solicitarPinMaestro();
        if (autorizado) {
            const rutaOriginal = pdf.ruta;
            const nombreOriginal = pdf.nombre;

            mostrarCargando("Moviendo a Inconsistencias...");
            ctx.lblEstado.innerHTML =
                '<span class="text-green-600 dark:text-green-400 font-bold"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Moviendo a Inconsistencias...</span>';

            CapturaArchivos.moverInconsistencia(
                ctx.dirSeleccionado.id,
                rutaOriginal,
            )
                .then((resp) => {
                    if (resp.ok) {
                        CapturaAuditoria.registrar({
                            accion: "Inconsistencia",
                            disco: ctx.dirSeleccionado.id,
                            delegacion_id: ctx.delSeleccionada.id,
                            nombre_lote: ctx.loteActualNombre,
                            archivo_original: nombreOriginal + ".pdf",
                            archivo_nuevo: nombreOriginal + ".pdf",
                            detalles: "Archivo movido a Inconsistencias",
                            paginas: resp.paginas || 0,
                        });
                    }
                })
                .then(() => {
                    avanzarSiguiente(ctx);
                })
                .catch((err) => {
                    console.error(
                        "Error al mover a inconsistencias: " + err.message,
                    );
                    ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
                })
                .finally(() => {
                    ocultarCargando();
                });
        }
    }

    /**
     * Mueve el archivo actual a la carpeta de Sellos.
     * @param {object} ctx - Contexto de ejecución.
     */
    async function moverSellos(ctx) {
        if (ctx.listaPdfs.length === 0 || ctx.indiceActual < 0) return;
        const pdf = ctx.listaPdfs[ctx.indiceActual];

        const seguro = await ctx.solicitarConfirmacion(
            "Mover a Sellos",
            `¿Está seguro de que desea mover el archivo '${pdf.nombre}.pdf' a la carpeta de Sellos?`,
            "fa-folder-open",
            "text-amber-500",
        );
        if (!seguro) {
            return;
        }

        const rutaOriginal = pdf.ruta;
        const nombreOriginal = pdf.nombre;

        mostrarCargando("Moviendo a Sellos...");
        ctx.lblEstado.innerHTML =
            '<span class="text-green-600 dark:text-green-400 font-bold"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Moviendo a Sellos...</span>';

        CapturaArchivos.moverSellos(ctx.dirSeleccionado.id, rutaOriginal)
            .then((resp) => {
                if (resp.ok) {
                    CapturaAuditoria.registrar({
                        accion: "MoverInconsistencia",
                        disco: ctx.dirSeleccionado.id,
                        delegacion_id: ctx.delSeleccionada.id,
                        nombre_lote: ctx.loteActualNombre,
                        archivo_original: nombreOriginal + ".pdf",
                        archivo_nuevo: nombreOriginal + ".pdf",
                        detalles: "Enviado a Sellos",
                        paginas: resp.paginas || 0,
                    });
                }
            })
            .then(() => {
                avanzarSiguiente(ctx);
            })
            .catch((err) => {
                console.error("Error al mover a sellos: " + err.message);
                ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
            })
            .finally(() => {
                ocultarCargando();
            });
    }

    /**
     * Rota la primera página del PDF en el servidor o aplica rotación visual en CSS.
     * @param {object} ctx    - Contexto de ejecución.
     * @param {number} grados - Grados de rotación (-90 o 90).
     */
    async function rotarPagina(ctx, grados) {
        if (ctx.listaPdfs.length === 0 || ctx.indiceActual < 0) return;
        const pdf = ctx.listaPdfs[ctx.indiceActual];
        const pagina = parseInt(ctx.nudPagina.value) || 1;

        if (pagina !== 1) {
            await ctx.solicitarConfirmacion(
                "Rotar Página",
                "En el modo Captura solo se permite rotar la primera página.",
                "fa-triangle-exclamation",
                "text-amber-500",
                false,
            );
            return;
        }

        if (!ctx.dirSeleccionado) {
            ctx.lblEstado.innerHTML =
                '<span class="text-red-500 font-bold">Error: Sesión no iniciada</span>';
            return;
        }

        try {
            mostrarCargando("Rotando página...");
            ctx.lblEstado.innerHTML = '<span class="text-blue-500"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Rotando página...</span>';
            const resp = await CapturaArchivos.rotar(
                ctx.dirSeleccionado.id,
                pdf.ruta,
                pagina,
                grados,
            );
            if (resp.ok) {
                ctx.lblEstado.innerHTML = `<span class="text-green-600 dark:text-green-400 font-bold">Página ${pagina} rotada ${grados}° ✅</span>`;
                const visor = document.getElementById("pdfViewer");
                if (visor) {
                    const urlString = visor.src;
                    visor.src = "about:blank";
                    visor.style.transform = "none";
                    visor.dataset.rotation = "0";

                    // Mostrar cargando mientras se recarga el PDF rotado
                    mostrarCargando("Cargando PDF...");
                    visor.onload = function () {
                        if (visor.src !== "about:blank" && visor.src !== "") {
                            ocultarCargando();
                            visor.onload = null;
                        }
                    };

                    setTimeout(() => {
                        try {
                            const url = new URL(urlString, window.location.origin);
                            url.searchParams.set('t', Date.now());
                            visor.src = url.toString();
                        } catch (e) {
                            const base = urlString.split('?')[0];
                            const query = urlString.split('?')[1] || "";
                            const params = new URLSearchParams(query);
                            params.set('t', Date.now());
                            visor.src = base + "?" + params.toString();
                        }
                    }, 50);
                }
            } else {
                // Fallback visual con CSS
                ctx.lblEstado.innerHTML = `<span class="text-amber-500 font-bold">Rotación visual aplicada</span>`;
                const iframe = document.getElementById("pdfViewer");
                let rotActual =
                    parseInt(iframe.dataset.rotation || "0") + grados;
                iframe.style.transform = `rotate(${rotActual}deg)`;
                iframe.dataset.rotation = rotActual;
            }
        } catch (err) {
            ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
        } finally {
            ocultarCargando();
        }
    }

    /**
     * Libera el lote actual y cierra la sesión de captura.
     * @param {object} ctx - Contexto de ejecución.
     */
    async function cerrarSesion(ctx) {
        const seguro = await ctx.solicitarConfirmacion(
            "Cerrar Sesión",
            "¿Desea cerrar sesión y liberar el lote actual?",
            "fa-power-off",
            "text-red-500",
        );
        if (!seguro) return;

        if (ctx.loteActualId) {
            try {
                await CapturaArchivos.liberarLote(ctx.loteActualId);
            } catch (err) {
                console.error("Error al liberar lote:", err);
            }
        }

        ctx.loteActualId = null;
        ctx.loteActualNombre = "";
        ctx.loteRutaRelativa = "";
        ctx.listaPdfs = [];
        ctx.indiceActual = 0;

        reiniciarCaptura(ctx);

        document.getElementById("pdfViewer").src = "about:blank";
        ctx.lblTotalPdf.innerText = "Seleccione Sesión";

        // Enviar petición POST de logout vía AJAX y luego redireccionar al login
        try {
            const token =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content") || "";
            await fetch("/logout", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": token,
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({}),
            });
        } catch (err) {
            console.error("Error al cerrar sesión:", err);
        }

        window.location.href = "/login";
    }

    /**
     * Marca el archivo actual para Mover a Libros (solo en BD, previa confirmación, sin movimiento físico).
     * @param {object} ctx - Contexto de ejecución.
     */
    async function moverLibros(ctx) {
        if (!ctx.listaPdfs || ctx.listaPdfs.length === 0 || ctx.indiceActual < 0) return;
        const pdf = ctx.listaPdfs[ctx.indiceActual];

        const confirmar = await ctx.solicitarConfirmacion(
            "Mover a Libros",
            `¿Desea marcar el archivo '${pdf.nombre}' para mover a Libros?`,
            "fa-book",
            "text-indigo-500",
        );
        if (!confirmar) return;

        const rutaArchivoOriginal = pdf.ruta_origen || pdf.ruta;

        const metaPdf = {
            sesion_id: pdf.sesion_id || ctx.loteActualId || null,
            indice_pdf_id: pdf.indice_pdf_id || null,
            ruta_origen: pdf.ruta_origen || '',
            archivo_original: pdf.nombre || '',
            paginas: pdf.paginas || 1,
            hash: pdf.hash || null,
            mismo_hash: !!(ctx.listaPdfs && ctx.listaPdfs.length > 1),
            tipo_documento: pdf.tipo || 'sellos',
            delegacion_id: ctx.delSeleccionada ? ctx.delSeleccionada.id : null,
            detalles: 'Marcado para mover a Libros (solo registro en BD)',
        };

        mostrarCargando("Marcando para Libros...");
        ctx.lblEstado.innerHTML = '<span class="text-indigo-600 dark:text-indigo-400 font-bold"><i class="fa-solid fa-book mr-1"></i> Marcando para Libros...</span>';

        try {
            const resp = await CapturaArchivos.moverLibros(
                ctx.dirSeleccionado ? ctx.dirSeleccionado.id : 'irec',
                rutaArchivoOriginal,
                metaPdf,
            );
            ocultarCargando();
            if (!resp || !resp.ok) {
                throw new Error((resp && resp.mensaje) || 'No se pudo marcar para Libros.');
            }
            reiniciarCaptura(ctx);

            if (resp.fuera_horario_despues_de_guardar) {
                await ctx.solicitarConfirmacion(
                    "Fin de horario laboral",
                    resp.aviso_horario || "Tu turno ha finalizado. Este último registro se guardó correctamente, pero ya no podrás capturar más.",
                    "fa-clock",
                    "text-amber-500",
                    false,
                );
                if (typeof window.bloquearPorHorario === 'function') {
                    window.bloquearPorHorario(resp.aviso_horario);
                }
                return;
            }

            avanzarSiguiente(ctx);
        } catch (err) {
            ocultarCargando();
            console.error("Error en moverLibros:", err);
            ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
        }
    }

    /**
     * Solicita autorización en tiempo real al supervisor para posponer ("skipear") el PDF actual
     * y liberarlo al día siguiente en el mismo turno del usuario.
     */
    async function posponerPdf(ctx) {
        if (!ctx.listaPdfs || ctx.listaPdfs.length === 0 || ctx.indiceActual < 0) return;
        const pdf = ctx.listaPdfs[ctx.indiceActual];

        const autorizado = await ctx.solicitarPinMaestro(
            'Posponer PDF',
            'Posponer (skipear) PDF para revisarlo mañana en el mismo turno'
        );
        if (!autorizado) return;

        const sesionId = pdf.sesion_id || ctx.loteActualId || null;
        mostrarCargando("Posponiendo PDF para tu siguiente turno...");
        ctx.lblEstado.innerHTML = '<span class="text-amber-600 dark:text-amber-400 font-bold"><i class="fa-solid fa-clock-rotate-left mr-1"></i> Posponiendo PDF...</span>';

        try {
            const resp = await CapturaArchivos.posponerPdf(
                sesionId,
                'Pospuesto con autorización del supervisor para revisar en el siguiente turno'
            );
            ocultarCargando();
            if (!resp || !resp.ok) {
                throw new Error((resp && resp.mensaje) || 'No se pudo posponer el PDF.');
            }
            reiniciarCaptura(ctx);
            avanzarSiguiente(ctx);
        } catch (err) {
            ocultarCargando();
            console.error("Error al posponer PDF:", err);
            ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
        }
    }

    // API pública
    return {
        reiniciarCaptura,
        cargarPdfActual,
        avanzarSiguiente,
        validarInput,
        cargarLoteDesdeServidor,
        precargarSiguientePdfEnSegundoPlano,
        agregarSelloAlPdfActual,
        procesarRenombrar,
        procesarDuplicar,
        moverInconsistencia,
        moverSellos,
        moverLibros,
        posponerPdf,
        rotarPagina,
        cerrarSesion,
    };
})();
