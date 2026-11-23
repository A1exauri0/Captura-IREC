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

        if (ctx.delSeleccionada && ctx.delSeleccionada.clave) {
            ctx.txtNombre.value = ctx.delSeleccionada.clave + "_";
        } else {
            ctx.txtNombre.value = "";
        }
        ctx.txtNombre.classList.remove(
            "border-red-500",
            "border-green-500",
            "border-amber-500",
        );
        ctx.txtNombre.classList.add("border-gray-300", "dark:border-gray-600");

        // Disparar evento input para sincronizar estado y valor anterior
        ctx.txtNombre.dispatchEvent(new Event('input'));

        if (ctx.loteActualId) {
            ctx.txtNombre.focus();
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
     * Carga el PDF actual en el iframe visor.
     * @param {object} ctx - Contexto de ejecución.
     */
    function cargarPdfActual(ctx) {
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

        mostrarCargando("Cargando PDF...");

        // Verificar si el archivo está accesible antes de cargarlo en el visor
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error("El archivo no se encuentra o no es accesible.");
                }
                const visor = document.getElementById("pdfViewer");
                visor.src = "about:blank";
                visor.style.transform = "none";
                visor.dataset.rotation = "0";

                visor.onload = function () {
                    if (visor.src !== "about:blank" && visor.src !== "") {
                        ocultarCargando();
                        visor.onload = null;
                    }
                };

                setTimeout(() => {
                    visor.src = url + "&t=" + Date.now();
                }, 50);

                reiniciarCaptura(ctx);
                ctx.historialCapturas.push(pdf.nombre.replace(/-/g, "_"));
            })
            .catch(async err => {
                ocultarCargando();
                console.error("No se pudo cargar el PDF:", err);

                // Mostrar modal de confirmación si no se detecta el archivo
                const pasarSiguiente = await ctx.solicitarConfirmacion(
                    "Archivo no encontrado",
                    `No se pudo leer o encontrar el archivo '${pdf.nombre}.pdf'. ¿Desea pasar al siguiente?`,
                    "fa-triangle-exclamation",
                    "text-red-500"
                );
                if (pasarSiguiente) {
                    avanzarSiguiente(ctx);
                } else {
                    ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: El archivo no existe en el servidor.</span>`;
                }
            });
    }

    /**
     * Remueve el archivo actual de la cola y avanza al siguiente,
     * o busca un nuevo lote si se terminó.
     * @param {object} ctx - Contexto de ejecución.
     */
    async function avanzarSiguiente(ctx) {
        ctx.listaPdfs.splice(ctx.indiceActual, 1);
        if (ctx.listaPdfs.length > 0) {
            if (ctx.indiceActual >= ctx.listaPdfs.length) {
                ctx.indiceActual = ctx.listaPdfs.length - 1;
            }
            cargarPdfActual(ctx);
        } else {
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

            document.getElementById("btnRenombrar").disabled = true;
            document.getElementById("btnDuplicar").disabled = true;
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
                    document.getElementById("btnRenombrar").disabled = true;
                    document.getElementById("btnDuplicar").disabled = true;
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

            if (ctx.accionEnEspera === "Renombrar") {
                document.getElementById("btnRenombrar").disabled = false;
                document.getElementById("btnDuplicar").disabled = true;
            } else if (ctx.accionEnEspera === "Duplicar") {
                document.getElementById("btnRenombrar").disabled = true;
                document.getElementById("btnDuplicar").disabled = false;
            } else {
                document.getElementById("btnRenombrar").disabled = false;
                document.getElementById("btnDuplicar").disabled = false;
            }
            return true;
        } else {
            ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> ${resultado.mensaje}</span>`;
            ctx.txtNombre.classList.remove(
                "border-green-500",
                "border-amber-500",
            );
            ctx.txtNombre.classList.add("border-red-500");
            document.getElementById("btnRenombrar").disabled = true;
            document.getElementById("btnDuplicar").disabled = true;
            return false;
        }
    }

    /**
     * Busca un lote libre en el servidor y lo asigna al estado del contexto.
     * @param {object} ctx - Contexto de ejecución.
     */
    async function cargarLoteDesdeServidor(ctx) {
        if (!ctx.dirSeleccionado) {
            ctx.dirSeleccionado = { id: 'irec', nombre: 'IREC', ruta: '\\\\172.40.5.84\\irec', reglas: ['Regla1'] };
        }

        const delegacionId = ctx.delSeleccionada ? ctx.delSeleccionada.id : null;
        const nombreDel = ctx.delSeleccionada ? ctx.delSeleccionada.nombre : "Búsqueda Automática";

        ctx.lblTotalPdf.innerText = `${nombreDel} - Buscando lote...`;
        ctx.lblEstado.innerHTML = '<span class="text-blue-500"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Buscando lote libre...</span>';
        mostrarCargando("Buscando lote libre...");

        try {
            const resp = await CapturaArchivos.buscarLote(
                ctx.dirSeleccionado.id,
                delegacionId,
            );
            if (!resp.ok) {
                ctx.lblTotalPdf.innerText =
                    resp.mensaje || "Sin lotes disponibles";
                ctx.lblEstado.innerHTML =
                    '<span class="text-red-500 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> No hay más lotes</span>';
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
            ctx.lblTotalPdf.innerText = `${nombreDelFinal} - ${ctx.loteActualNombre}`;
            reiniciarCaptura(ctx);
            cargarPdfActual(ctx);
        } catch (err) {
            ctx.lblTotalPdf.innerText = "Error al buscar lote";
            ctx.lblEstado.innerHTML = `<span class="text-red-500">${err.message}</span>`;
        } finally {
            ocultarCargando();
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

        // Caso A: Coincide exactamente con el nombre original en disco (ya tiene formato correcto con guiones bajos)
        if (capturado.toLowerCase() === pdf.nombre.toLowerCase()) {
            const rutaArchivoOriginal = pdf.ruta;
            const nombreArchivoOriginal = pdf.nombre;

            // Avanzar inmediatamente en la interfaz
            avanzarSiguiente(ctx);
            ctx.lblEstado.innerHTML =
                '<span class="text-green-600 dark:text-green-400 font-bold">Validando... ✅</span>';

            // Ejecutar en segundo plano
            CapturaArchivos.validar(
                ctx.dirSeleccionado.id,
                rutaArchivoOriginal,
                ctx.delSeleccionada.id,
                ctx.loteActualNombre,
            )
                .then((resp) => {
                    if (resp.ok) {
                        CapturaAuditoria.registrar({
                            accion: "Validado",
                            disco: ctx.dirSeleccionado.id,
                            delegacion_id: ctx.delSeleccionada.id,
                            nombre_lote: ctx.loteActualNombre,
                            archivo_original: nombreArchivoOriginal + ".pdf",
                            archivo_nuevo: nombreArchivoOriginal + ".pdf",
                            detalles: "Validado en primera captura",
                            paginas: resp.paginas || 0,
                        });
                    }
                })
                .catch((err) => {
                    console.error("Error al validar: " + err.message);
                });
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
            ctx.txtNombre.focus();
            document.getElementById("btnDuplicar").disabled = true;
            return;
        }

        if (
            ctx.historialCapturas.length === 2 &&
            ctx.accionEnEspera === "Renombrar"
        ) {
            const primerCaptura = ctx.historialCapturas[1];
            if (capturado.toLowerCase() === primerCaptura.toLowerCase()) {
                const rutaOriginal = pdf.ruta;
                const nombreOriginal = pdf.nombre;
                const nuevoNombre = capturado;

                ctx.lblEstado.innerHTML = '<span class="text-blue-500"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Procesando...</span>';
                const validado = await ctx.solicitarConfirmacion(
                    "Validación",
                    "¿El documento ha sido validado correctamente?",
                    "fa-circle-check",
                    "text-green-500",
                );

                if (validado) {
                    const btnRen = document.getElementById("btnRenombrar");
                    if (btnRen) {
                        btnRen.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Renombrando...';
                        btnRen.disabled = true;
                    }
                    mostrarCargando("Renombrando y validando...");
                    ctx.lblEstado.innerHTML =
                        '<span class="text-green-600 dark:text-green-400 font-bold"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Renombrando y validando...</span>';

                    CapturaArchivos.renombrar(
                        ctx.dirSeleccionado.id,
                        rutaOriginal,
                        nuevoNombre,
                    )
                        .then((respRen) => {
                            if (respRen.ok) {
                                return CapturaArchivos.validar(
                                    ctx.dirSeleccionado.id,
                                    respRen.ruta_nueva,
                                    ctx.delSeleccionada.id,
                                    ctx.loteActualNombre,
                                ).then((respVal) => {
                                    if (respVal.ok) {
                                        CapturaAuditoria.registrar({
                                            accion: "Renombrar",
                                            disco: ctx.dirSeleccionado.id,
                                            delegacion_id:
                                                ctx.delSeleccionada.id,
                                            nombre_lote: ctx.loteActualNombre,
                                            archivo_original:
                                                nombreOriginal + ".pdf",
                                            archivo_nuevo: nuevoNombre + ".pdf",
                                            detalles: "Validado",
                                            paginas:
                                                respVal.paginas ||
                                                respRen.paginas ||
                                                0,
                                        });
                                    }
                                });
                            }
                        })
                        .then(() => {
                            avanzarSiguiente(ctx);
                        })
                        .catch((err) => {
                            console.error(
                                "Error en renombrar/validar: " + err.message,
                            );
                            ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
                        })
                        .finally(() => {
                            ocultarCargando();
                            if (btnRen) {
                                btnRen.innerHTML = "Renombrar";
                            }
                        });
                } else {
                    const btnRen = document.getElementById("btnRenombrar");
                    if (btnRen) {
                        btnRen.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Renombrando...';
                        btnRen.disabled = true;
                    }
                    mostrarCargando("Renombrando...");
                    ctx.lblEstado.innerHTML =
                        '<span class="text-green-600 dark:text-green-400 font-bold"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Renombrando...</span>';

                    CapturaArchivos.renombrar(
                        ctx.dirSeleccionado.id,
                        rutaOriginal,
                        nuevoNombre,
                    )
                        .then((respRen) => {
                            if (respRen.ok) {
                                CapturaAuditoria.registrar({
                                    accion: "Renombrar",
                                    disco: ctx.dirSeleccionado.id,
                                    delegacion_id: ctx.delSeleccionada.id,
                                    nombre_lote: ctx.loteActualNombre,
                                    archivo_original: nombreOriginal + ".pdf",
                                    archivo_nuevo: nuevoNombre + ".pdf",
                                    detalles: "Solo renombrado",
                                    paginas: respRen.paginas || 0,
                                });

                                // Modificar datos locales
                                pdf.nombre = nuevoNombre;
                                pdf.ruta = respRen.ruta_nueva || (rutaOriginal.substring(0, rutaOriginal.lastIndexOf("/")) + "/" + nuevoNombre + ".pdf");
                                ctx.pdfFueRenombrado = true;
                            }
                        })
                        .then(() => {
                            reiniciarCaptura(ctx);
                            cargarPdfActual(ctx);
                        })
                        .catch((err) => {
                            console.error("Error al renombrar: " + err.message);
                            ctx.lblEstado.innerHTML = `<span class="text-red-500 font-bold">Error: ${err.message}</span>`;
                        })
                        .finally(() => {
                            ocultarCargando();
                            if (btnRen) {
                                btnRen.innerHTML = "Renombrar";
                            }
                        });
                }
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
