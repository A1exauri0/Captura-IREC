/**
 * capturaAuditoria.js
 * Módulo de registro de auditoría al servidor Laravel.
 */
const CapturaAuditoria = (() => {

    /**
     * Obtiene el token CSRF desde la meta tag.
     * @returns {string}
     */
    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Registra una acción de auditoría en la tabla auditoria_capturas.
     * Se envía al backend sin bloquear la UI (fire-and-forget).
     *
     * @param {object} datos - Datos de la auditoría:
     *   - {string} accion           - Tipo de acción (Validado, Renombrar, Duplicar, Inconsistencia, etc.)
     *   - {string} disco            - Disco de red (irec, cats, rciv)
     *   - {number} delegacionId     - ID de la delegación
     *   - {string} nombreLote       - Nombre del lote
     *   - {string} archivoOriginal  - Nombre del archivo original
     *   - {string} archivoNuevo     - Nombre del archivo nuevo (si aplica)
     *   - {string} detalles         - Detalles adicionales
     *   - {number} paginas          - Total de páginas del PDF
     */
    function registrar(datos) {
        fetch('/captura/auditoria', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify(datos),
        }).catch(err => {
            console.error('Error al registrar auditoría:', err);
        });
    }

    // API pública
    return { registrar };
})();
