/**
 * capturaReglas.js
 * Replicación exacta de la Regla 2 (Sellos) de Form1.vb + ServicioReglas.vb
 * con saneamiento de caracteres prohibidos y catálogo oficial de Tomos.
 */
const CapturaReglas = (() => {

    const CATALOGO_TOMOS = [
        'CREDITO_AGRICOLA',
        'DOCUMENTOS_PRIVADOS',
        'ACTOS_Y_CONTRATOS',
        'ADQUISICIONES_DE_GOBIERNO',
        'AUXILIAR',
        'AUXILIAR_ACTOS_Y_CONTRATOS',
        'AUXILIAR_CREDITO_AGRICOLA',
        'AUXILIAR_DOCUMENTOS_PRIVADOS',
        'CANCELACION',
    ];

    const CATALOGO_FRACCIONAMIENTO = [
        'FRACCIONAMIENTO',
        'FRACCIONAMIENTOS_Y_COLONIAS',
        'CORETT',
        'RAN',
        'INFONAVIT',
    ];

    function extraerTextoBaseTomo(tomoStr) {
        if (!tomoStr) return '';
        const match = String(tomoStr).match(/^\d+_(.+)$/);
        return match ? match[1] : String(tomoStr);
    }

    /**
     * Sanitiza texto según reglas generales: mayúsculas sin acentos (conservando Ñ),
     * sin caracteres prohibidos ni espacios dobles.
     */
    function sanitizarTexto(valor) {
        if (!valor) return '';
        let s = String(valor).toUpperCase();
        // Quitar acentos preservando la Ñ
        s = s.replace(/[ÁÀÄÂ]/g, 'A')
            .replace(/[ÉÈËÊ]/g, 'E')
            .replace(/[ÍÌÏÎ]/g, 'I')
            .replace(/[ÓÒÖÔ]/g, 'O')
            .replace(/[ÚÙÜÛ]/g, 'U');
        // Quitar caracteres prohibidos (' " < > | \ ; ~ ^ ` { } [ ])
        s = s.replace(/['"<>|\\;~^`{}\[\]\t\r\n]/g, '');
        return s;
    }

    /**
     * Valida el nombre completo según Regla 2 (Sellos) exactamente como ServicioReglas.ValidarNuevasReglas().
     */
    function validarNombre(nombre, esRegla1, esRegla2, delegClave, delegNombre, catalogoAnios) {
        if (!nombre || nombre.includes('__')) {
            return { valido: false, mensaje: 'Error: doble guion bajo o vacío' };
        }

        const partes = nombre.split('_');
        const total = partes.length;

        // --- REGLA 2 (Sellos) — ServicioReglas.vb ---
        if (esRegla2 || !esRegla1) {
            if (total < 9) {
                return { valido: false, mensaje: 'Deben ser al menos 9 bloques separados por guion bajo' };
            }

            // Bloque 1 (Delegación): 2 dígitos
            if (!/^\d{2}$/.test(partes[0])) {
                return { valido: false, mensaje: 'Bloque 1 (Delegación) debe ser de 2 dígitos' };
            }
            if (delegClave && partes[0] !== delegClave) {
                return { valido: false, mensaje: `Bloque 1 debe coincidir con la delegación (${delegClave} - ${delegNombre || ''})` };
            }

            // Bloque 2 (Sección): 2 a 3 dígitos
            if (!/^\d{2,3}$/.test(partes[1])) {
                return { valido: false, mensaje: 'Bloque 2 (Sección) debe ser de 2 a 3 dígitos' };
            }

            // Bloque 3 (Libro): 2 a 6 dígitos
            if (!/^\d{2,6}$/.test(partes[2])) {
                return { valido: false, mensaje: 'Bloque 3 (Libro) debe ser de 2 a 6 dígitos' };
            }

            // Bloque 9 (BIS, último): 2 dígitos o "BIS"
            if (!/^(\d{2}|BIS)$/.test(partes[total - 1])) {
                return { valido: false, mensaje: "Bloque 9 (BIS) debe ser de 2 dígitos o la palabra 'BIS'" };
            }

            // Bloque 8 (Inscripción, penúltimo): exactamente 4 dígitos
            if (!/^\d{4}$/.test(partes[total - 2])) {
                return { valido: false, mensaje: 'Bloque 8 (Inscripción) debe ser de 4 dígitos rellenado con ceros' };
            }

            // Bloque 7 (Año, antepenúltimo): 4 dígitos + catálogo de años (1850-2030)
            const anio = partes[total - 3];
            if (!/^\d{4}$/.test(anio)) {
                return { valido: false, mensaje: 'Bloque 7 (Año) debe ser de 4 dígitos' };
            }
            if (Array.isArray(catalogoAnios) && catalogoAnios.length > 0 && !catalogoAnios.includes(anio)) {
                return { valido: false, mensaje: `El año ${anio} no es válido.` };
            }

            // Ubicar Volumen (bloque 5: 2 a 3 dígitos), Tomo (bloque 4: 1-30 chars) y Fraccionamiento (bloque 6: 1-55 chars)
            let idxVol = -1;
            let tomoStr = '';
            let fracStr = '';
            for (let i = 4; i <= total - 5; i++) {
                if (/^\d{2,3}$/.test(partes[i])) {
                    const tempTomo = partes.slice(3, i).join('_');
                    const tempFrac = partes.slice(i + 1, total - 3).join('_');
                    if (tempTomo.length >= 1 && tempTomo.length <= 30 && tempFrac.length >= 1 && tempFrac.length <= 55) {
                        idxVol = i;
                        tomoStr = tempTomo;
                        fracStr = tempFrac;
                        break;
                    }
                }
            }

            if (idxVol === -1) {
                return {
                    valido: false,
                    mensaje: 'Bloque 5 (Volumen) debe ser de 2 a 3 dígitos entre Tomo (máx. 30) y Fraccionamiento (máx. 55)',
                };
            }

            // Validar palabras reservadas de Tomo (igual que ServicioReglas.vb)
            const tomoBase = extraerTextoBaseTomo(tomoStr);
            if (tomoBase.startsWith('F')) {
                return { valido: false, mensaje: 'Tomo inválido: no puede iniciar con F (reservado a Fraccionamiento)' };
            }

            if (tomoBase.length >= 2 && !/^\d+$/.test(tomoBase)) {
                const esAuxCompleto =
                    tomoBase.startsWith('AUXILIAR_ACTOS_Y_CONTRATOS') ||
                    tomoBase.startsWith('AUXILIAR_CREDITO_AGRICOLA') ||
                    tomoBase.startsWith('AUXILIAR_DOCUMENTOS_PRIVADOS');

                if (!esAuxCompleto && tomoBase.startsWith('AUXILIAR')) {
                    if (tomoBase.startsWith('AUXILIAR_C') && 'AUXILIAR_CREDITO_AGRICOLA'.startsWith(tomoBase)) {
                        return { valido: false, mensaje: "Tomo incorrecto: debe escribirse 'AUXILIAR_CREDITO_AGRICOLA' completo" };
                    }
                    if (tomoBase.startsWith('AUXILIAR_D') && 'AUXILIAR_DOCUMENTOS_PRIVADOS'.startsWith(tomoBase)) {
                        return { valido: false, mensaje: "Tomo incorrecto: debe escribirse 'AUXILIAR_DOCUMENTOS_PRIVADOS' completo" };
                    }
                    if (tomoBase.startsWith('AUXILIAR_A') && 'AUXILIAR_ACTOS_Y_CONTRATOS'.startsWith(tomoBase)) {
                        return { valido: false, mensaje: "Tomo incorrecto: debe escribirse 'AUXILIAR_ACTOS_Y_CONTRATOS' completo" };
                    }
                } else if (!esAuxCompleto) {
                    const primeraLetra = tomoBase.substring(0, 1);
                    const posibles = CATALOGO_TOMOS.filter(p => p.startsWith(primeraLetra));
                    if (posibles.length > 0 && !posibles.some(p => tomoBase === p)) {
                        const parcial = posibles.find(p => p.startsWith(tomoBase));
                        if (parcial) {
                            return { valido: false, mensaje: `Tomo incompleto: debe escribirse '${parcial}' completo` };
                        }
                    }
                }
            }

            return { valido: true, mensaje: 'Formato correcto ✅' };
        }

        // Regla 1 (Registros) conservada por compatibilidad
        if (!/^\d{2}_\d{4}_\d{1,6}$/.test(nombre)) {
            return { valido: false, mensaje: 'Formato inválido. Ej: 01_2024_123456' };
        }
        return { valido: true, mensaje: 'Formato correcto ✅' };
    }

    function aplicarAutoGuiones(valor) {
        return String(valor || '').replace(/-/g, '_');
    }

    return {
        CATALOGO_TOMOS,
        CATALOGO_FRACCIONAMIENTO,
        extraerTextoBaseTomo,
        sanitizarTexto,
        validarNombre,
        aplicarAutoGuiones,
    };
})();
