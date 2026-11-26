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

    /**
     * =========================================================================
     * CATÁLOGO DE DETECCIÓN AUTOMÁTICA: LIBRO -> FRACCIONAMIENTOS Y COLONIAS
     * =========================================================================
     * TODO [CATÁLOGO PENDIENTE]:
     * Cuando se cuente con el catálogo oficial que relaciona el número de Libro
     * (o Delegación + Sección + Libro) con "FRACCIONAMIENTOS_Y_COLONIAS" u otro
     * elemento de CATALOGO_FRACCIONAMIENTO, registrar aquí las equivalencias.
     *
     * Ejemplo de estructura soportada:
     *   '01_01_0045': 'FRACCIONAMIENTOS_Y_COLONIAS', // Por Delegación_Sección_Libro
     *   '0045':       'FRACCIONAMIENTOS_Y_COLONIAS', // O directamente por número de Libro
     */
    const CATALOGO_FRACCIONAMIENTO_POR_LIBRO = {
        // '0001': 'FRACCIONAMIENTOS_Y_COLONIAS',
    };

    /**
     * Detecta automáticamente si un número de Libro corresponde a un Fraccionamiento/Colonia
     * del catálogo para autoseleccionarlo en la casilla 6 (Fracc.).
     *
     * @param {string} libroValor      - Valor capturado en 3. Libro.
     * @param {string} delegacionClave - Clave de 2 dígitos en 1. Del. (opcional).
     * @param {string} seccionValor    - Valor en 2. Secc. (opcional).
     * @returns {string|null} Nombre del fraccionamiento del catálogo o null si no aplica.
     */
    function detectarFraccionamientoPorLibro(libroValor, delegacionClave = '', seccionValor = '') {
        const lib = String(libroValor || '').trim();
        if (!lib) return null;
        const claveCompuesta = `${delegacionClave}_${seccionValor}_${lib}`;
        if (CATALOGO_FRACCIONAMIENTO_POR_LIBRO[claveCompuesta]) {
            return CATALOGO_FRACCIONAMIENTO_POR_LIBRO[claveCompuesta];
        }
        if (CATALOGO_FRACCIONAMIENTO_POR_LIBRO[lib]) {
            return CATALOGO_FRACCIONAMIENTO_POR_LIBRO[lib];
        }
        return null;
    }

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

        // --- REGLA 2 (Sellos) — ServicioReglas.vb + reglas dinámicas de dígitos ---
        if (esRegla2 || !esRegla1) {
            if (total < 9) {
                return { valido: false, mensaje: 'Deben ser al menos 9 bloques separados por guion bajo' };
            }

            const cfg = (typeof window !== 'undefined' && window.REGLAS_DIGITOS_SELLOS) ? window.REGLAS_DIGITOS_SELLOS : {};
            const rDel   = cfg.delegacion      || { min: 2, max: 2 };
            const rSecc  = cfg.seccion         || { min: 2, max: 3 };
            const rLibro = cfg.libro           || { min: 2, max: 6 };
            const rTomo  = cfg.tomo            || { min: 1, max: 30 };
            const rVol   = cfg.volumen         || { min: 2, max: 3 };
            const rFracc = cfg.fraccionamiento || { min: 1, max: 55 };
            const rAnio  = cfg.anio            || { min: 4, max: 4 };
            const rInscr = cfg.inscripcion     || { min: 4, max: 4 };
            const rBis   = cfg.bis             || { min: 2, max: 2 };

            const reDel   = new RegExp(`^\\d{${rDel.min},${rDel.max}}$`);
            const reSecc  = new RegExp(`^\\d{${rSecc.min},${rSecc.max}}$`);
            const reLibro = new RegExp(`^\\d{${rLibro.min},${rLibro.max}}$`);
            const reVol   = new RegExp(`^\\d{${rVol.min},${rVol.max}}$`);
            const reAnio  = new RegExp(`^\\d{${rAnio.min},${rAnio.max}}$`);
            const reInscr = new RegExp(`^\\d{${rInscr.min},${rInscr.max}}$`);
            const reBis   = new RegExp(`^(\\d{${rBis.min},${rBis.max}}|BIS)$`);

            // Bloque 1 (Delegación)
            if (!reDel.test(partes[0])) {
                return { valido: false, mensaje: `Bloque 1 (Delegación) debe ser de ${rDel.min === rDel.max ? rDel.min : (rDel.min + ' a ' + rDel.max)} dígitos` };
            }
            if (delegClave && partes[0] !== delegClave) {
                return { valido: false, mensaje: `Bloque 1 debe coincidir con la delegación (${delegClave} - ${delegNombre || ''})` };
            }

            // Bloque 2 (Sección)
            if (!reSecc.test(partes[1])) {
                return { valido: false, mensaje: `Bloque 2 (Sección) debe ser de ${rSecc.min === rSecc.max ? rSecc.min : (rSecc.min + ' a ' + rSecc.max)} dígitos` };
            }

            // Bloque 3 (Libro)
            if (!reLibro.test(partes[2])) {
                return { valido: false, mensaje: `Bloque 3 (Libro) debe ser de ${rLibro.min === rLibro.max ? rLibro.min : (rLibro.min + ' a ' + rLibro.max)} dígitos` };
            }

            // Bloque 9 (BIS, último)
            if (!reBis.test(partes[total - 1])) {
                return { valido: false, mensaje: "Bloque 9 (BIS) debe ser numérico o la palabra 'BIS'" };
            }

            // Bloque 8 (Inscripción, penúltimo)
            if (!reInscr.test(partes[total - 2])) {
                return { valido: false, mensaje: `Bloque 8 (Inscripción) debe ser de ${rInscr.min === rInscr.max ? rInscr.min : (rInscr.min + ' a ' + rInscr.max)} dígitos rellenado con ceros` };
            }

            // Bloque 7 (Año, antepenúltimo)
            const anio = partes[total - 3];
            if (!reAnio.test(anio)) {
                return { valido: false, mensaje: `Bloque 7 (Año) debe ser de ${rAnio.min} dígitos` };
            }
            if (Array.isArray(catalogoAnios) && catalogoAnios.length > 0 && !catalogoAnios.includes(anio)) {
                return { valido: false, mensaje: `El año ${anio} no es válido.` };
            }

            // Ubicar Volumen (bloque 5), Tomo (bloque 4) y Fraccionamiento (bloque 6)
            let idxVol = -1;
            let tomoStr = '';
            let fracStr = '';
            for (let i = 4; i <= total - 5; i++) {
                if (reVol.test(partes[i])) {
                    const tempTomo = partes.slice(3, i).join('_');
                    const tempFrac = partes.slice(i + 1, total - 3).join('_');
                    if (tempTomo.length >= rTomo.min && tempTomo.length <= rTomo.max && tempFrac.length >= rFracc.min && tempFrac.length <= rFracc.max) {
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
        CATALOGO_FRACCIONAMIENTO_POR_LIBRO,
        detectarFraccionamientoPorLibro,
        extraerTextoBaseTomo,
        sanitizarTexto,
        validarNombre,
        aplicarAutoGuiones,
    };
})();
