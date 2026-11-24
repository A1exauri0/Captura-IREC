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

    /**
     * Reinicia el estado de captura para el PDF actual.
     * @param {object} ctx - Contexto de ejecución con referencias y estado.
     */
    function reiniciarCaptura(ctx) {
        ctx.accionEnEspera = "";
        ctx.historialCapturas = [];
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

        // Disparar evento input para sincronizar estado y valor anterior
        ctx.txtNombre.dispatchEvent(new Event('input'));

        if (ctx.loteActualId) {
            const primerSeg = document.getElementById('selloDel');
            if (primerSeg) primerSeg.focus();
            else ctx.txtNombre.focus();
        }

        const lblCaptura = document.getElementById("lblCaptura");
        if (lblCaptura) {
            lblCaptura.innerText = "Captura 1";
            lblCaptura.className = "text-lg font-bold";
        }

        const btnRen = document.getElementById("btnRenombrar");
        if (btnRen) {
            btnRen.disabled = true;
            btnRen.innerHTML = "Renombrar";
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

    /**
     * Si el PDF actual pertenece a un grupo de mismo hash, precarga automáticamente
     * el número de registro (Inscr.) en la casilla #selloInscr.
     */
    function precargarNumeroRegistroSiGrupoHash(ctx) {
        if (!ctx.listaPdfs || ctx.listaPdfs.length <= 1) return;
        const actual = ctx.listaPdfs[ctx.indiceActual];
        if (!actual || !actual.etiqueta_boton) return;
        const inputInscr = document.getElementById("selloInscr");
        if (inputInscr) {
            inputInscr.value = String(actual.etiqueta_boton).slice(0, 4);
            inputInscr.dispatchEvent(new Event("input"));
        }
    }

    /**
     * Renderiza la barra de botones cuando varios PDFs comparten el mismo hash
     * para permitir capturar múltiples nombres/registros viendo el mismo PDF.
     */
    function renderizarBarraGrupoHash(ctx) {
        const barra = document.getElementById("barraGrupoHash");
        const titulo = document.getElementById("lblTituloGrupoHash");
        const cont = document.getElementById("contenedorBotonesHash");
        if (!barra || !cont) return;

        if (!ctx.listaPdfs || ctx.listaPdfs.length <= 1) {
            barra.classList.add("hidden");
            cont.innerHTML = "";
            return;
        }

        barra.classList.remove("hidden");
        if (titulo) {
            titulo.textContent = `Mismo PDF (${ctx.listaPdfs.length} registros pendientes):`;
        }
        cont.innerHTML = "";

        ctx.listaPdfs.forEach((item, idx) => {
            const btn = document.createElement("button");
            btn.type = "button";
            const activo = idx === ctx.indiceActual;
            const etiqueta = item.etiqueta_boton || String(idx + 1).padStart(4, "0");
            btn.className = activo
                ? "px-2.5 py-1 rounded-md font-mono font-extrabold text-xs bg-sky-600 text-white shadow ring-2 ring-sky-400"
                : "px-2.5 py-1 rounded-md font-mono font-bold text-xs bg-white dark:bg-gray-800 text-indigo-800 dark:text-indigo-200 border border-indigo-300 dark:border-indigo-600 hover:bg-indigo-100 dark:hover:bg-indigo-900 transition";
            btn.textContent = etiqueta;
            btn.title = `Cambiar al registro ${etiqueta} (mismo PDF)`;
            btn.addEventListener("click", () => {
                if (ctx.indiceActual === idx) return;
                ctx.indiceActual = idx;
                ctx.loteActualId = item.sesion_id || ctx.loteActualId;
                ctx.loteActualNombre = item.nombre || ctx.loteActualNombre;
                renderizarBarraGrupoHash(ctx);
                reiniciarCaptura(ctx);
                precargarNumeroRegistroSiGrupoHash(ctx);
                ctx.historialCapturas.push((item.nombre || "").replace(/-/g, "_"));
                console.log("[Captura-IREC] Nombre del PDF:", item.nombre || null);
            });
            cont.appendChild(btn);
        });
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
                ctx.historialCapturas.push(pdf.nombre.replace(/-/g, "_"));
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
        const hashAnterior = anterior ? anterior.hash : null;

        ctx.listaPdfs.splice(ctx.indiceActual, 1);
        if (ctx.listaPdfs.length > 0) {
            if (ctx.indiceActual >= ctx.listaPdfs.length) {
                ctx.indiceActual = 0;
            }
            const siguiente = ctx.listaPdfs[ctx.indiceActual];
            ctx.loteActualId = siguiente.sesion_id || ctx.loteActualId;
            ctx.loteActualNombre = siguiente.nombre || ctx.loteActualNombre;

            // Si el siguiente PDF tiene exactamente el mismo hash, NO recargar el visor (cambio instantáneo)
            if (hashAnterior && siguiente && siguiente.hash && siguiente.hash === hashAnterior) {
                renderizarBarraGrupoHash(ctx);
                reiniciarCaptura(ctx);
                precargarNumeroRegistroSiGrupoHash(ctx);
                ctx.historialCapturas.push((siguiente.nombre || "").replace(/-/g, "_"));
                console.log("[Captura-IREC] Nombre del PDF:", siguiente.nombre || null);
                return;
            }
            cargarPdfActual(ctx);
        } else {
            renderizarBarraGrupoHash(ctx);
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
            // Verificar si hay una acción en espera que requiera validación de doble captura
            if (
                ctx.accionEnEspera === "Renombrar" ||
                ctx.accionEnEspera === "Duplicar"
            ) {
                const primerCaptura = ctx.historialCapturas[1];
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
            ctx.lblTotalPdf.innerText = `${nombreDelFinal}`;
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
                } catch (_) {}
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
        const capturado = ctx.txtNombre.value.trim().replace(/-/g, "_");

        if (!validarInput(ctx)) return;

        // Metadatos reales del registro de Stellum (indice_archivos_pdfs) para guardar en auditoria_sellos
        const metaPdf = {
            sesion_id: pdf.sesion_id || ctx.loteActualId || null,
            indice_pdf_id: pdf.indice_pdf_id || null,
            ruta_origen: pdf.ruta_origen || '',
            archivo_original: pdf.nombre || '',
            paginas: pdf.paginas || 1,
            hash: pdf.hash || null,
            tipo_documento: pdf.tipo || 'sellos',
            delegacion_id: ctx.delSeleccionada ? ctx.delSeleccionada.id : null,
        };

        // Caso A: Coincide exactamente con el nombre original en disco (ya tiene formato correcto con guiones bajos)
        if (capturado.toLowerCase() === pdf.nombre.toLowerCase()) {
            const rutaArchivoOriginal = pdf.ruta_origen || pdf.ruta;

            mostrarCargando("Validando captura...");
            ctx.lblEstado.innerHTML =
                '<span class="text-green-600 dark:text-green-400 font-bold">Validando... ✅</span>';

            try {
                const respVal = await CapturaArchivos.validar(
                    ctx.dirSeleccionado.id,
                    rutaArchivoOriginal,
                    ctx.delSeleccionada ? ctx.delSeleccionada.id : null,
                    ctx.loteActualNombre,
                    { ...metaPdf, detalles: 'Validado en primera captura' },
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

        // Caso B: Doble captura requerida
        if (ctx.historialCapturas.length === 1) {
            ctx.historialCapturas.push(capturado);
            ctx.accionEnEspera = "Renombrar";

            const lblCaptura = document.getElementById("lblCaptura");
            if (lblCaptura) {
                lblCaptura.innerText = "Segunda captura";
                lblCaptura.className = "text-lg font-bold text-amber-500";
            }
            ctx.lblEstado.innerText = "Confirme el nuevo nombre.";
            ctx.txtNombre.value = "";
            document.querySelectorAll('.sello-seg').forEach(el => { el.value = ''; });
            const primero = document.getElementById('selloDel');
            if (primero) primero.focus();
            else ctx.txtNombre.focus();
            const btnDup = document.getElementById("btnDuplicar");
            if (btnDup) btnDup.disabled = true;
            return;
        }

        if (
            ctx.historialCapturas.length === 2 &&
            ctx.accionEnEspera === "Renombrar"
        ) {
            const primerCaptura = ctx.historialCapturas[1];
            if (capturado.toLowerCase() === primerCaptura.toLowerCase()) {
                const rutaOriginal = pdf.ruta_origen || pdf.ruta;
                const nuevoNombre = capturado;

                ctx.lblEstado.innerHTML = '<span class="text-blue-500"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Procesando...</span>';
                const validado = await ctx.solicitarConfirmacion(
                    "Confirmar captura",
                    "¿Confirmas guardar el nuevo nombre propuesto en base de datos?",
                    "fa-circle-check",
                    "text-green-500",
                );

                if (!validado) return;

                const btnRen = document.getElementById("btnRenombrar");
                if (btnRen) {
                    btnRen.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Guardando...';
                    btnRen.disabled = true;
                }
                mostrarCargando("Guardando captura...");
                ctx.lblEstado.innerHTML =
                    '<span class="text-green-600 dark:text-green-400 font-bold"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Guardando...</span>';

                CapturaArchivos.renombrar(
                    ctx.dirSeleccionado.id,
                    rutaOriginal,
                    nuevoNombre,
                    { ...metaPdf, detalles: 'Renombrado y validado en doble captura' },
                )
                    .then(async (respRen) => {
                        if (respRen.ok) {
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

    // API pública
    return {
        reiniciarCaptura,
        cargarPdfActual,
        avanzarSiguiente,
        validarInput,
        cargarLoteDesdeServidor,
        procesarRenombrar,
        procesarDuplicar,
        moverInconsistencia,
        moverSellos,
        rotarPagina,
        cerrarSesion,
    };
})();
