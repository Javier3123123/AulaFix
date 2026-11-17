/**
 * Acceso centralizado al almacenamiento local usado por el panel y el formulario.
 */
const CLAVE_PREFERENCIAS = 'aulafix.incidencias.v1';
const CLAVE_BORRADOR = 'aulafix.nueva-incidencia.borrador.v1';

// Las claves separan las preferencias del panel del contenido del borrador.

/**
 * Lee las preferencias del panel.
 * @returns {Record<string, unknown>}
 * @throws {Error} Si el navegador no permite leer o el JSON guardado es inválido.
 */
export function leerPreferencias() {
    const guardadas = window.localStorage.getItem(CLAVE_PREFERENCIAS);
    if (guardadas === null) {
        return {};
    }

    const preferencias = JSON.parse(guardadas);
    if (preferencias === null || typeof preferencias !== 'object' || Array.isArray(preferencias)) {
        throw new TypeError('Las preferencias guardadas no tienen el formato esperado.');
    }
    return preferencias;
}

/**
 * Guarda únicamente filtros y orden, nunca datos personales de incidencias.
 * @param {Record<string, string>} preferencias
 * @returns {void}
 * @throws {Error} Si el navegador no permite escribir en localStorage.
 */
export function guardarPreferencias(preferencias) {
    window.localStorage.setItem(CLAVE_PREFERENCIAS, JSON.stringify(preferencias));
}

/**
 * Lee el borrador del formulario.
 * @returns {Record<string, unknown>|null}
 * @throws {Error} Si el navegador no permite leer o el JSON guardado es inválido.
 */
export function leerBorrador() {
    const guardado = window.localStorage.getItem(CLAVE_BORRADOR);
    if (guardado === null) {
        return null;
    }

    const borrador = JSON.parse(guardado);
    if (borrador === null || typeof borrador !== 'object' || Array.isArray(borrador)) {
        throw new TypeError('El borrador guardado no tiene el formato esperado.');
    }
    return borrador;
}

/**
 * Guarda los campos escritos en el formulario de alta.
 * @param {Record<string, string>} borrador
 * @returns {void}
 * @throws {Error} Si el navegador no permite escribir en localStorage.
 */
export function guardarBorrador(borrador) {
    window.localStorage.setItem(CLAVE_BORRADOR, JSON.stringify(borrador));
}

/**
 * Elimina el borrador después de guardar una incidencia correctamente.
 * @returns {void}
 * @throws {Error} Si el navegador no permite modificar localStorage.
 */
export function borrarBorrador() {
    window.localStorage.removeItem(CLAVE_BORRADOR);
}