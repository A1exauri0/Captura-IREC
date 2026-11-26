/**
 * capturaArchivos.js
 * Módulo de operaciones con archivos en el servidor.
 * Cada función hace un fetch al backend de Laravel y retorna una Promise.
 */
const CapturaArchivos = (() => {

    /**
     * Obtiene el token CSRF desde la meta tag de Laravel.
     * @returns {string}
     */
    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Ejecuta una petición POST autenticada al servidor.
     * @param {string} url   - Ruta relativa (ej. '/captura/renombrar').
     * @param {object} datos - Cuerpo JSON de la petición.
     * @returns {Promise<object>}
     */
    async function postJSON(url, datos) {
        const respuesta = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify(datos),
        });
        if (!respuesta.ok) {
            const errorTexto = await respuesta.text();
            throw new Error(`Error ${respuesta.status}: ${errorTexto}`);
        }
        return respuesta.json();
    }

    /**
     * Ejecuta una petición GET autenticada al servidor.
     * @param {string} url - Ruta con query params.
     * @returns {Promise<object>}
     */
    async function getJSON(url) {
        const respuesta = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });
        if (!respuesta.ok) {
            const errorTexto = await respuesta.text();
            throw new Error(`Error ${respuesta.status}: ${errorTexto}`);
        }
        return respuesta.json();
    }

    /**
     * Busca el siguiente archivo PDF libre en indice_archivos_pdfs (Stellum).
     * El backend lo bloquea en archivos_sesiones con estado 'en_captura'.
     *
     * @param {string} disco         - Identificador del directorio/entrega.
     * @param {number} delegacionId  - ID de la delegación en la BD.
     * @returns {Promise<{ ok, lote, archivos, mensaje }>}
     */
    async function buscarPdf(disco, delegacionId, opciones = {}) {
        const params = new URLSearchParams();
        if (disco) params.append('disco', disco);
        if (delegacionId) params.append('delegacion_id', delegacionId);
        if (opciones && opciones.prefetch) {
            params.append('prefetch', '1');
        }
        if (typeof window !== 'undefined' && window.MODO_OPERACION_CAPTURA === 'muestreo') {
            params.append('modo', 'muestreo');
        }
        return getJSON(`/captura/buscar-pdf?${params.toString()}`);
    }

    /**
     * Obtiene la URL para visualizar un PDF en el iframe.
     * @param {string} disco        - Disco de red.
     * @param {string} rutaRelativa - Ruta relativa del archivo dentro del disco.
     * @returns {string} URL completa para el src del iframe.
     */
    function urlVerPdf(disco, rutaRelativa) {
        return `/captura/ver-pdf?disco=${encodeURIComponent(disco)}&ruta=${encodeURIComponent(rutaRelativa)}`;
    }

    /**
     * Renombra un archivo PDF en su carpeta actual.
     *
     * @param {string} disco         - Disco de red.
     * @param {string} rutaOriginal  - Ruta relativa del archivo original.
     * @param {string} nuevoNombre   - Nuevo nombre sin extensión.
     * @returns {Promise<{ ok, rutaNueva, mensaje }>}
     */
    async function renombrar(disco, rutaOriginal, nuevoNombre, extra = {}) {
        return postJSON('/captura/renombrar', {
            disco,
            ruta_original: rutaOriginal,
            nuevo_nombre: nuevoNombre,
            ...extra,
        });
    }

    /**
     * Registra un sello adicional agregado sobre el mismo PDF y verifica en indice_archivos_pdfs
     * si ya existe un PDF con ese nombre para enviarlo a revisión indicando el motivo.
     */
    async function agregarSello(disco, rutaOriginal, nuevoNombre, extra = {}) {
        return postJSON('/captura/agregar-sello', {
            disco,
            ruta_original: rutaOriginal,
            nuevo_nombre: nuevoNombre,
            ...extra,
        });
    }

    /**
     * Duplica (copia) un archivo a la carpeta de Calidad con un nuevo nombre.
     *
     * @param {string} disco         - Disco de red.
     * @param {string} rutaOriginal  - Ruta relativa del archivo original.
     * @param {string} nuevoNombre   - Nombre de la copia sin extensión.
     * @param {number} delegacionId  - ID de la delegación.
     * @param {string} nombreLote    - Nombre del lote actual.
     * @returns {Promise<{ ok, mensaje }>}
     */
    async function duplicar(disco, rutaOriginal, nuevoNombre, delegacionId, nombreLote) {
        return postJSON('/captura/duplicar', {
            disco, ruta_original: rutaOriginal, nuevo_nombre: nuevoNombre,
            delegacion_id: delegacionId, nombre_lote: nombreLote,
        });
    }

    /**
     * Valida un archivo: lo mueve desde el lote a Calidad/{delegacion}/{año}/{lote}.
     * Se usa cuando el nombre ya es correcto (primera captura coincide con original).
     *
     * @param {string} disco         - Disco de red.
     * @param {string} rutaArchivo   - Ruta relativa del archivo.
     * @param {number} delegacionId  - ID de la delegación.
     * @param {string} nombreLote    - Nombre del lote actual.
     * @returns {Promise<{ ok, mensaje }>}
     */
    async function validar(disco, rutaArchivo, delegacionId, nombreLote, extra = {}) {
        return postJSON('/captura/validar', {
            disco,
            ruta_archivo: rutaArchivo,
            delegacion_id: delegacionId,
            nombre_lote: nombreLote,
            ...extra,
        });
    }

    /**
     * Mueve un archivo a la carpeta de Inconsistencias.
     *
     * @param {string} disco       - Disco de red.
     * @param {string} rutaArchivo - Ruta relativa del archivo.
     * @returns {Promise<{ ok, mensaje }>}
     */
    async function moverInconsistencia(disco, rutaArchivo) {
        return postJSON('/captura/inconsistencia', { disco, ruta_archivo: rutaArchivo });
    }

    /**
     * Mueve un archivo a la carpeta de Inconsistencias/Sellos (botón Mover).
     *
     * @param {string} disco       - Disco de red.
     * @param {string} rutaArchivo - Ruta relativa del archivo.
     * @returns {Promise<{ ok, mensaje }>}
     */
    async function moverSellos(disco, rutaArchivo) {
        return postJSON('/captura/mover-sellos', { disco, ruta_archivo: rutaArchivo });
    }

    /**
     * Marca el archivo actual para mover a Libros (solo registro en BD, sin movimiento físico).
     */
    async function moverLibros(disco, rutaArchivo, extra = {}) {
        return postJSON('/captura/mover-libros', {
            disco,
            ruta_archivo: rutaArchivo,
            ...extra,
        });
    }

    /**
     * Marca un archivo como eliminar en BD (sin borrar el archivo físico), previa autorización.
     */
    async function eliminar(disco, rutaArchivo, extra = {}) {
        return postJSON('/captura/eliminar', {
            disco,
            ruta_archivo: rutaArchivo,
            ...extra,
        });
    }

    /**
     * Pospone ("skipea") el PDF actual para liberarlo al día siguiente en el mismo turno del usuario.
     */
    async function posponerPdf(sesionId, motivo = '') {
        return postJSON('/captura/posponer-pdf', {
            sesion_id: sesionId,
            motivo,
        });
    }

    /**
     * Rota una página específica de un PDF.
     *
     * @param {string} disco       - Disco de red.
     * @param {string} rutaArchivo - Ruta relativa del archivo.
     * @param {number} pagina      - Número de página (1-indexed).
     * @param {number} grados      - Grados de rotación (90 o -90).
     * @returns {Promise<{ ok, mensaje }>}
     */
    async function rotar(disco, rutaArchivo, pagina, grados) {
        return postJSON('/captura/rotar', { disco, ruta_archivo: rutaArchivo, pagina, grados });
    }

    /**
     * Libera el lote actual en la BD (cuando el usuario cierra sesión o cambia).
     *
     * @param {number} loteId - ID del registro en lotes_captura.
     * @returns {Promise<{ ok }>}
     */
    async function liberarLote(loteId) {
        return postJSON('/captura/liberar-lote', { lote_id: loteId });
    }

    // API pública
    return {
        buscarPdf, urlVerPdf, renombrar, agregarSello, duplicar, validar,
        moverInconsistencia, moverSellos, moverLibros, eliminar, posponerPdf, rotar, liberarLote,
    };
})();
