/**
 * Formatos de fecha del panel. Intl mantiene los textos en español según el navegador.
 */
const formatoRelativo = new Intl.RelativeTimeFormat('es', { numeric: 'always' });
const formatoCompleto = new Intl.DateTimeFormat('es', {
    dateStyle: 'long',
    timeStyle: 'short',
});

/**
 * Formatea una fecha pasada o futura con la unidad relativa adecuada.
 * @param {Date} fecha Fecha que se quiere mostrar.
 * @param {Date} ahora Instante de referencia para calcular la diferencia.
 * @returns {string}
 * @throws {TypeError} Si alguno de los dos instantes no es una fecha válida.
 */
export function formatearFechaRelativa(fecha, ahora = new Date()) {
    if (!(fecha instanceof Date) || !Number.isFinite(fecha.getTime())
        || !(ahora instanceof Date) || !Number.isFinite(ahora.getTime())) {
        throw new TypeError('Se necesita una fecha válida para calcular el tiempo relativo.');
    }

    const diferencia = fecha.getTime() - ahora.getTime();
    const distancia = Math.abs(diferencia);
    // La distancia elige la unidad; el signo indica si la fecha está en el pasado o el futuro.
    if (distancia < 60 * 1000) {
        return formatoRelativo.format(Math.round(diferencia / 1000), 'second');
    }
    if (distancia < 60 * 60 * 1000) {
        return formatoRelativo.format(Math.round(diferencia / (60 * 1000)), 'minute');
    }
    if (distancia < 24 * 60 * 60 * 1000) {
        return formatoRelativo.format(Math.round(diferencia / (60 * 60 * 1000)), 'hour');
    }
    return formatoRelativo.format(Math.round(diferencia / (24 * 60 * 60 * 1000)), 'day');
}

/**
 * Devuelve la fecha localizada que acompaña al texto relativo.
 * @param {Date} fecha Instante que se mostrará en español.
 * @returns {string}
 * @throws {TypeError} Si el valor no es una fecha válida.
 */
export function formatearFechaCompleta(fecha) {
    if (!(fecha instanceof Date) || !Number.isFinite(fecha.getTime())) {
        throw new TypeError('Se necesita una fecha válida para mostrarla.');
    }
    return formatoCompleto.format(fecha);
}