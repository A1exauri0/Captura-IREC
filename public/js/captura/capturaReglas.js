/**
 * capturaReglas.js
 * Módulo de validación de nombres de archivo según Regla 1 (Registros) y Regla 2 (Sellos).
 * Incluye auto-guiones y formato inteligente al escribir.
 */
const CapturaReglas = (() => {

    /**
     * Valida el nombre completo del archivo según la regla activa.
     *
     * @param {string}   nombre        - Nombre sin extensión a validar.
     * @param {boolean}  esRegla1      - true si la regla activa es Registros.
     * @param {boolean}  esRegla2      - true si la regla activa es Sellos.
     * @param {string}   delegClave    - Clave de 2 dígitos de la delegación seleccionada.
     * @param {string}   delegNombre   - Nombre de la delegación (para mensajes).
     * @param {string[]} catalogoAnios - Lista de años válidos.
     * @returns {{ valido: boolean, mensaje: string }}
     */
    function validarNombre(nombre, esRegla1, esRegla2, delegClave, delegNombre, catalogoAnios) {
        // Rechazar doble guion bajo
        if (nombre.includes('__')) {
            return { valido: false, mensaje: 'Error: doble guion bajo' };
        }

        const partes = nombre.split('_');

        // --- REGLA 1 (Registros): XX_XXXX_XXXXXX ---
        if (esRegla1) {
            if (!/^\d{2}_\d{4}_\d{1,6}$/.test(nombre)) {
                return { valido: false, mensaje: 'Formato inválido. Ej: 01_2024_123456' };
            }

            if (delegClave) {
                const prefijo = delegClave + '_';
                if (!nombre.startsWith(prefijo)) {
                    return { valido: false, mensaje: `Debe iniciar con ${prefijo} (${delegNombre})` };
                }
            }

            const anio = partes[1];
            if (!catalogoAnios.includes(anio)) {
                return { valido: false, mensaje: `Año ${anio} no es válido.` };
            }

            return { valido: true, mensaje: 'Formato correcto ✅' };
        }

        // --- REGLA 2 (Sellos): estructura larga con bloques de 2 y 4 dígitos ---
        if (esRegla2) {
            const total = partes.length;

            if (total < 9) {
                return { valido: false, mensaje: 'Faltan bloques' };
            }

            // Posición final: 2 dígitos
            if (!/^\d{2}$/.test(partes[total - 1])) {
                return { valido: false, mensaje: 'Error en posición final' };
            }

            // Folio (penúltima): 4 dígitos
            if (!/^\d{4}$/.test(partes[total - 2])) {
                return { valido: false, mensaje: 'Error en folio' };
            }

            // Año (antepenúltima): 4 dígitos
            if (!/^\d{4}$/.test(partes[total - 3])) {
                return { valido: false, mensaje: 'Error en año' };
            }

            const anio = partes[total - 3];
            if (!catalogoAnios.includes(anio)) {
                return { valido: false, mensaje: `Año ${anio} no es válido.` };
            }

            // Posiciones 1-3: bloques de 2 dígitos
            for (let i = 0; i <= 2; i++) {
                if (!/^\d{2}$/.test(partes[i])) {
                    return { valido: false, mensaje: `Error en posición ${i + 1}` };
                }
            }

            // Posición 4: bloque dinámico de 2 dígitos (min 1 caracter numérico)
            if (partes.length > 3 && !/^\d+$/.test(partes[3])) {
                return { valido: false, mensaje: 'Error en posición 4' };
            }

            // Buscar posición 5: primer bloque de 2 dígitos después del índice 3
            let idx = 3;
            let encontroPos5 = false;
            while (idx < total - 3) {
                if (/^\d{2}$/.test(partes[idx])) {
                    encontroPos5 = true;
                    break;
                }
                idx++;
            }

            if (!encontroPos5) {
                return { valido: false, mensaje: 'No se encontró posición 5' };
            }

            idx += 1;

            // Posición 6: bloques entre posición 5 y año
            if (idx >= total - 3) {
                return { valido: false, mensaje: 'Falta posición 6' };
            }

            const bloquesPos6 = (total - 3) - idx;
            if (bloquesPos6 <= 0) {
                return { valido: false, mensaje: 'Error en posición 6' };
            }
            if (bloquesPos6 > 10) {
                return { valido: false, mensaje: 'Exceso de bloques en posición 6' };
            }

            return { valido: true, mensaje: 'Formato correcto ✅' };
        }

        return { valido: false, mensaje: 'Selecciona una regla de validación' };
    }

    /**
     * Aplica auto-guiones inteligentes al texto del input según la regla activa.
     * Se ejecuta en cada evento 'input' del campo de nombre.
     *
     * @param {string}  valor       - Valor actual del input.
     * @param {string}  valorAnterior - Valor previo (para detectar si se está escribiendo o borrando).
     * @param {boolean} esRegla1    - true si Regla 1 activa.
     * @param {boolean} esRegla2    - true si Regla 2 activa.
     * @returns {string} Valor formateado con auto-guiones.
     */
    function aplicarAutoGuiones(valor, valorAnterior, esRegla1, esRegla2) {
        // Convertir guiones medios a bajos
        let val = valor.replace(/-/g, '_');

        // Solo insertar si se está escribiendo (no borrando)
        if (val.length <= valorAnterior.length) return val;

        if (esRegla1) {
            if (val.length === 2 && !val.includes('_')) {
                val += '_';
            } else if (val.length === 7 && (val.match(/_/g) || []).length === 1) {
                val += '_';
            }
        } else if (esRegla2) {
            const posiciones = [2, 5, 8, 11, 14, 17, 22, 27];
            if (posiciones.includes(val.length)) {
                val += '_';
            }
        }

        return val;
    }

    // API pública del módulo
    return { validarNombre, aplicarAutoGuiones };
})();
