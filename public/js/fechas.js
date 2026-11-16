/**
 * Formatos de fecha del panel. Intl mantiene los textos en español según el navegador.
 */
const formatoRelativo = new Intl.RelativeTimeFormat('es', { numeric: 'always' });
const formatoCompleto = new Intl.DateTimeFormat('es', {
    dateStyle: 'long',
    timeStyle: 'short',
});

/**
 * Devuelve una fecha relativa en segundos, minutos, horas o días.
 * @param {Date} fecha Fecha que se quiere mostrar.
 * @param {Date} ahora Instante de referencia, útil también para las pruebas.
 * @returns {string}
 */
export function formatearFechaRelativa(fecha, ahora = new Date()) {
    if (!(fecha instanceof Date) || !Number.isFinite(fecha.getTime())
        || !(ahora instanceof Date) || !Number.isFinite(ahora.getTime())) {
        throw new TypeError('Se necesita una fecha válida para calcular el tiempo relativo.');
    }

    const diferencia = fecha.getTime() - ahora.getTime();
    const distancia = Math.abs(diferencia);
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
 * Devuelve la fecha completa para el atributo title y el nombre accesible.
 * @param {Date} fecha Fecha que se quiere mostrar.
 * @returns {string}
 */
export function formatearFechaCompleta(fecha) {
    if (!(fecha instanceof Date) || !Number.isFinite(fecha.getTime())) {
        throw new TypeError('Se necesita una fecha válida para mostrarla.');
    }
    return formatoCompleto.format(fecha);
}