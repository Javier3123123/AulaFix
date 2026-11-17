/**
 * Transformaciones de datos y pintado DOM del panel de incidencias.
 */
import { Incidencia } from './incidencia.js';
import { formatearFechaCompleta, formatearFechaRelativa } from './fechas.js';

// Las etiquetas visibles se mantienen separadas de los valores estables del JSON.
const etiquetas = {
    estados: {
        abierta: 'Abierta',
        en_curso: 'En curso',
        resuelta: 'Resuelta',
    },
    categorias: {
        hardware: 'Hardware',
        software: 'Software',
        red: 'Red',
        otro: 'Otro',
    },
    prioridades: {
        baja: 'Baja',
        media: 'Media',
        alta: 'Alta',
    },
};

// Los pesos permiten ordenar prioridades de menor a mayor sin depender de sus etiquetas.
const pesosPrioridad = { baja: 1, media: 2, alta: 3 };

/**
 * Convierte el JSON de la página en modelos validados.
 * @param {string} textoJson JSON incrustado por el servidor.
 * @returns {Incidencia[]}
 * @throws {Error} Si el JSON o alguno de sus registros no es válido.
 */
export function leerIncidencias(textoJson) {
    const datos = JSON.parse(textoJson);
    if (!Array.isArray(datos)) {
        throw new TypeError('El JSON de incidencias debe ser una lista.');
    }

    const incidencias = [];
    for (const dato of datos) {
        incidencias.push(new Incidencia(dato));
    }
    return incidencias;
}

/**
 * Cuenta incidencias por estado y urgencia con una sola reducción.
 * @param {Incidencia[]} lista
 * @returns {{abiertas:number, enCurso:number, resueltas:number, urgentes:number}}
 */
export function resumir(lista) {
    return lista.reduce(acumularResumen, {
        abiertas: 0,
        enCurso: 0,
        resueltas: 0,
        urgentes: 0,
    });
}

/**
 * Actualiza un resumen con una incidencia.
 * @param {{abiertas:number, enCurso:number, resueltas:number, urgentes:number}} resumen
 * @param {Incidencia} incidencia
 * @returns {{abiertas:number, enCurso:number, resueltas:number, urgentes:number}}
 */
function acumularResumen(resumen, incidencia) {
    if (incidencia.estado === 'abierta') {
        resumen.abiertas += 1;
    } else if (incidencia.estado === 'en_curso') {
        resumen.enCurso += 1;
    } else {
        resumen.resueltas += 1;
    }

    if (incidencia.esUrgente) {
        resumen.urgentes += 1;
    }
    return resumen;
}

/**
 * Filtra sin modificar la lista original por estado, prioridad y texto.
 * @param {Incidencia[]} lista
 * @param {{pestana:string, prioridad:string, busqueda:string}} criterios
 * @returns {Incidencia[]}
 */
export function filtrar(lista, criterios) {
    const termino = normalizar(criterios.busqueda.trim());
    /**
     * Comprueba si una incidencia coincide con los filtros activos.
     * @param {Incidencia} incidencia
     * @returns {boolean}
     */
    function coincideCriterios(incidencia) {
        const coincideEstado = criterios.pestana === 'todas' || incidencia.estado === criterios.pestana;
        const coincidePrioridad = criterios.prioridad === 'todas'
            || incidencia.prioridad === criterios.prioridad;
        // La búsqueda cubre equipo, descripción y autor, sin distinguir mayúsculas ni tildes.
        const texto = normalizar(`${incidencia.equipo} ${incidencia.descripcion} ${incidencia.autor}`);
        return coincideEstado && coincidePrioridad && texto.includes(termino);
    }

    return lista.filter(coincideCriterios);
}

/**
 * Ordena una copia para conservar el orden original recibido del servidor.
 * @param {Incidencia[]} lista
 * @param {'fecha'|'equipo'|'prioridad'} campo
 * @param {'asc'|'desc'} direccion
 * @returns {Incidencia[]}
 */
export function ordenar(lista, campo, direccion) {
    const resultado = [...lista];
    /**
     * Compara dos incidencias según el campo y la dirección solicitados.
     * @param {Incidencia} primera
     * @param {Incidencia} segunda
     * @returns {number}
     */
    function comparar(primera, segunda) {
        let comparacion = 0;
        if (campo === 'equipo') {
            comparacion = primera.equipo.localeCompare(segunda.equipo, 'es', { sensitivity: 'base' });
        } else if (campo === 'prioridad') {
            comparacion = pesosPrioridad[primera.prioridad] - pesosPrioridad[segunda.prioridad];
        } else {
            comparacion = Date.parse(primera.creada_en) - Date.parse(segunda.creada_en);
        }

        // El ID hace determinista el orden cuando dos registros coinciden en el campo elegido.
        if (comparacion === 0) {
            comparacion = primera.id - segunda.id;
        }
        return direccion === 'asc' ? comparacion : -comparacion;
    }
    resultado.sort(comparar);
    return resultado;
}

/**
 * Inserta filas seguras usando textContent y un fragmento, sin interpretar datos como HTML.
 * @param {HTMLTableSectionElement} cuerpo
 * @param {Incidencia[]} lista
 * @returns {void}
 */
export function pintarTabla(cuerpo, lista) {
    const fragmento = document.createDocumentFragment();
    for (const incidencia of lista) {
        fragmento.append(crearFila(incidencia));
    }
    cuerpo.replaceChildren(fragmento);
}

/**
 * Actualiza los textos relativos sin reconstruir la tabla.
 * @param {ParentNode} contenedor
 * @returns {void}
 */
export function actualizarFechasRelativas(contenedor) {
    const ahora = new Date();
    for (const elemento of contenedor.querySelectorAll('time[datetime]')) {
        const fecha = new Date(elemento.dateTime);
        const relativa = formatearFechaRelativa(fecha, ahora);
        const completa = formatearFechaCompleta(fecha);
        elemento.textContent = relativa;
        elemento.title = completa;
        elemento.setAttribute('aria-label', `${relativa} (${completa})`);
    }
}

/**
 * Normaliza mayúsculas y tildes para facilitar la búsqueda.
 * @param {string} texto
 * @returns {string}
 */
function normalizar(texto) {
    return texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('es');
}

/**
 * Crea una fila con nodos de texto para evitar interpretar descripciones como HTML.
 * @param {Incidencia} incidencia
 * @returns {HTMLTableRowElement}
 */
function crearFila(incidencia) {
    const fila = document.createElement('tr');
    if (incidencia.esUrgente) {
        fila.classList.add('incidencia-urgente');
    }

    const celdaId = document.createElement('td');
    const enlace = document.createElement('a');
    enlace.href = `incidencia.php?id=${encodeURIComponent(String(incidencia.id))}`;
    enlace.textContent = String(incidencia.id);
    celdaId.append(enlace);
    fila.append(celdaId);
    fila.append(crearCelda(incidencia.equipo));
    fila.append(crearCelda(etiquetas.categorias[incidencia.categoria]));
    fila.append(crearCelda(etiquetas.prioridades[incidencia.prioridad]));
    const celdaDescripcion = crearCelda(incidencia.descripcion);
    celdaDescripcion.classList.add('descripcion-celda');
    fila.append(celdaDescripcion);

    const celdaEstado = crearCelda(etiquetas.estados[incidencia.estado]);
    if (incidencia.esUrgente) {
        const etiquetaUrgente = document.createElement('span');
        etiquetaUrgente.className = 'etiqueta-urgente';
        etiquetaUrgente.textContent = 'Urgente sin resolver';
        celdaEstado.append(document.createTextNode(' '), etiquetaUrgente);
    }
    fila.append(celdaEstado);

    const fecha = new Date(incidencia.creada_en);
    const relativa = formatearFechaRelativa(fecha);
    const completa = formatearFechaCompleta(fecha);
    const elementoFecha = document.createElement('time');
    elementoFecha.dateTime = incidencia.creada_en;
    elementoFecha.title = completa;
    elementoFecha.setAttribute('aria-label', `${relativa} (${completa})`);
    elementoFecha.textContent = relativa;
    const celdaFecha = document.createElement('td');
    celdaFecha.append(elementoFecha);
    fila.append(celdaFecha);
    fila.append(crearCelda(incidencia.autor));
    return fila;
}

/**
 * Crea una celda cuyo contenido siempre se trata como texto.
 * @param {string} texto
 * @returns {HTMLTableCellElement}
 */
function crearCelda(texto) {
    const celda = document.createElement('td');
    celda.textContent = texto;
    return celda;
}