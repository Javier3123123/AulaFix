/**
 * Coordina el panel del cliente y conserva el listado PHP como mejora progresiva.
 */
import {
    actualizarFechasRelativas,
    filtrar,
    leerIncidencias,
    ordenar,
    pintarTabla,
    resumir,
} from './vistas.js';
import { borrarBorrador, guardarPreferencias, leerPreferencias } from './preferencias.js';

/**
 * Inicia el panel cuando la página contiene sus datos y controles.
 * @returns {void}
 */
function iniciarPanel() {
    const errorPanel = document.getElementById('error-panel-incidencias');

    /**
     * Muestra un error del panel sin ocultar la tabla que generó el servidor.
     * @param {string} mensaje Texto que se muestra a la persona usuaria.
     * @param {unknown} error Error técnico que se registra en la consola.
     * @returns {void}
     */
    function mostrarError(mensaje, error) {
        errorPanel.textContent = mensaje;
        errorPanel.hidden = false;
        console.error(mensaje, error);
    }

    if (document.body.dataset.borrarBorrador === 'true') {
        try {
            borrarBorrador();
        } catch (error) {
            mostrarError('La incidencia se guardó, pero no se pudo borrar el borrador de este navegador.', error);
        }
    }

    const datosElemento = document.getElementById('datos-incidencias');
    const panel = document.getElementById('panel-incidencias');
    if (!datosElemento || !panel) {
        return;
    }

    const vistaServidor = document.getElementById('vista-servidor');
    let incidencias;
    try {
        incidencias = leerIncidencias(datosElemento.textContent);
    } catch (error) {
        mostrarError('No se pudo preparar el panel. Puedes consultar el listado del servidor.', error);
        return;
    }

    let preferenciasGuardadas = {};
    try {
        preferenciasGuardadas = leerPreferencias();
    } catch (error) {
        mostrarError('El panel funciona, pero no se pudieron recuperar sus preferencias.', error);
    }

    const pestañas = panel.querySelector('.pestanas-incidencias');
    const tabs = Array.from(panel.querySelectorAll('[role="tab"]'));
    const busqueda = document.getElementById('buscar-incidencia');
    const filtroPrioridad = document.getElementById('filtrar-prioridad');
    const tabla = document.getElementById('tabla-cliente');
    const filas = document.getElementById('filas-incidencias');
    const contador = document.getElementById('contador-incidencias');
    const mensajeVacio = document.getElementById('sin-resultados');
    const panelResultados = document.getElementById('panel-resultados');

    if (!vistaServidor || !pestañas || !busqueda || !filtroPrioridad || !tabla
        || !filas || !contador || !mensajeVacio || !panelResultados) {
        mostrarError('No se pudo preparar el panel porque falta un elemento de la página.', null);
        return;
    }

    const estado = normalizarPreferencias(preferenciasGuardadas);
    busqueda.value = estado.busqueda;
    filtroPrioridad.value = estado.prioridad;
    actualizarResumen();
    actualizarPestanas();
    actualizarOrden();

    try {
        renderizar();
    } catch (error) {
        mostrarError('No se pudo mostrar el panel. Puedes consultar el listado del servidor.', error);
        return;
    }

    panel.hidden = false;
    vistaServidor.hidden = true;
    pestañas.addEventListener('click', manejarClickPestanas);
    pestañas.addEventListener('keydown', manejarTecladoPestanas);
    busqueda.addEventListener('input', manejarBusqueda);
    filtroPrioridad.addEventListener('change', manejarPrioridad);
    tabla.addEventListener('click', manejarOrden);

    const intervaloFechas = window.setInterval(actualizarFechas, 60_000);
    window.addEventListener('pagehide', detenerIntervalo, { once: true });

    /**
     * Valida las preferencias almacenadas y completa valores ausentes con los iniciales.
     * @param {Record<string, unknown>} preferencias
     * @returns {{pestana:string, busqueda:string, prioridad:string, orden:string, direccion:string}}
     */
    function normalizarPreferencias(preferencias) {
        const estadosValidos = ['todas', 'abierta', 'en_curso', 'resuelta'];
        const prioridadesValidas = ['todas', 'baja', 'media', 'alta'];
        const ordenesValidos = ['fecha', 'equipo', 'prioridad'];
        return {
            pestana: estadosValidos.includes(preferencias.pestana) ? preferencias.pestana : 'todas',
            busqueda: typeof preferencias.busqueda === 'string' ? preferencias.busqueda : '',
            prioridad: prioridadesValidas.includes(preferencias.prioridad) ? preferencias.prioridad : 'todas',
            orden: ordenesValidos.includes(preferencias.orden) ? preferencias.orden : 'fecha',
            direccion: preferencias.direccion === 'asc' ? 'asc' : 'desc',
        };
    }

    /**
     * Actualiza los cuatro resúmenes con los datos completos, sin aplicar filtros.
     * @returns {void}
     */
    function actualizarResumen() {
        const totales = resumir(incidencias);
        document.getElementById('total-abiertas').textContent = String(totales.abiertas);
        document.getElementById('total-en-curso').textContent = String(totales.enCurso);
        document.getElementById('total-resueltas').textContent = String(totales.resueltas);
        document.getElementById('total-urgentes').textContent = String(totales.urgentes);
    }

    /**
     * Actualiza la pestaña activa y el nombre accesible de su panel.
     * @returns {void}
     */
    function actualizarPestanas() {
        for (const tab of tabs) {
            const activa = tab.dataset.estado === estado.pestana;
            tab.setAttribute('aria-selected', String(activa));
            tab.tabIndex = activa ? 0 : -1;
            if (activa) {
                panelResultados.setAttribute('aria-labelledby', tab.id);
            }
        }
    }

    /**
     * Actualiza el indicador de orden en la cabecera de la tabla.
     * @returns {void}
     */
    function actualizarOrden() {
        for (const cabecera of panel.querySelectorAll('[data-campo-orden]')) {
            const boton = cabecera.querySelector('[data-orden]');
            const activa = cabecera.dataset.campoOrden === estado.orden;
            cabecera.setAttribute(
                'aria-sort',
                activa ? (estado.direccion === 'asc' ? 'ascending' : 'descending') : 'none'
            );
            boton.setAttribute(
                'aria-label',
                `Ordenar por ${nombreOrden(cabecera.dataset.campoOrden)}${activa
                    ? `, ${estado.direccion === 'asc' ? 'ascendente' : 'descendente'}`
                    : ''}`
            );
        }
    }

    /**
     * Aplica filtros y orden, y después actualiza tabla, contador y estado vacío.
     * @returns {void}
     */
    function renderizar() {
        const resultadoFiltrado = filtrar(incidencias, estado);
        const resultadoOrdenado = ordenar(resultadoFiltrado, estado.orden, estado.direccion);
        pintarTabla(filas, resultadoOrdenado);

        const cantidad = resultadoOrdenado.length;
        contador.textContent = `Mostrando ${cantidad} de ${incidencias.length} ${
            incidencias.length === 1 ? 'incidencia' : 'incidencias'
        }.`;
        mensajeVacio.textContent = incidencias.length === 0
            ? 'Todavía no hay incidencias registradas.'
            : 'No hay incidencias que coincidan con los filtros.';
        mensajeVacio.hidden = cantidad !== 0;
    }

    /**
     * Activa la pestaña pulsada mediante delegación desde su contenedor.
     * @param {MouseEvent} evento
     * @returns {void}
     */
    function manejarClickPestanas(evento) {
        if (!(evento.target instanceof Element)) {
            return;
        }
        const tab = evento.target.closest('[role="tab"]');
        if (!tab || !pestañas.contains(tab)) {
            return;
        }
        activarPestana(tab);
    }

    /**
     * Permite recorrer las pestañas con flechas, Inicio y Fin.
     * @param {KeyboardEvent} evento
     * @returns {void}
     */
    function manejarTecladoPestanas(evento) {
        if (!(evento.target instanceof Element)) {
            return;
        }
        const tabActual = evento.target.closest('[role="tab"]');
        const indice = tabs.indexOf(tabActual);
        if (indice < 0) {
            return;
        }

        let siguiente = null;
        if (evento.key === 'ArrowRight') {
            siguiente = tabs[(indice + 1) % tabs.length];
        } else if (evento.key === 'ArrowLeft') {
            siguiente = tabs[(indice - 1 + tabs.length) % tabs.length];
        } else if (evento.key === 'Home') {
            siguiente = tabs[0];
        } else if (evento.key === 'End') {
            siguiente = tabs[tabs.length - 1];
        }

        if (siguiente) {
            evento.preventDefault();
            siguiente.focus();
            activarPestana(siguiente);
        }
    }

    /**
     * Aplica el cambio de pestaña y lo recuerda.
     * @param {HTMLButtonElement} tab
     * @returns {void}
     */
    function activarPestana(tab) {
        estado.pestana = tab.dataset.estado;
        actualizarPestanas();
        persistirPreferencias();
        renderizar();
    }

    /**
     * Guarda el texto de búsqueda y actualiza las filas mientras se escribe.
     * @returns {void}
     */
    function manejarBusqueda() {
        estado.busqueda = busqueda.value;
        persistirPreferencias();
        renderizar();
    }

    /**
     * Guarda la prioridad seleccionada y actualiza las filas.
     * @returns {void}
     */
    function manejarPrioridad() {
        estado.prioridad = filtroPrioridad.value;
        persistirPreferencias();
        renderizar();
    }

    /**
     * Ordena desde la cabecera usando delegación y closest.
     * @param {MouseEvent} evento
     * @returns {void}
     */
    function manejarOrden(evento) {
        if (!(evento.target instanceof Element)) {
            return;
        }
        const boton = evento.target.closest('button[data-orden]');
        if (!boton || !tabla.contains(boton)) {
            return;
        }

        if (estado.orden === boton.dataset.orden) {
            estado.direccion = estado.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            estado.orden = boton.dataset.orden;
            estado.direccion = estado.orden === 'fecha' ? 'desc' : 'asc';
        }
        actualizarOrden();
        persistirPreferencias();
        renderizar();
    }

    /**
     * Escribe solo filtros y orden en el almacenamiento local.
     * @returns {void}
     */
    function persistirPreferencias() {
        try {
            guardarPreferencias(estado);
        } catch (error) {
            mostrarError('El panel funciona, pero no se pudieron guardar sus preferencias.', error);
        }
    }

    /**
     * Refresca las fechas relativas sin reconstruir el contenido de la tabla.
     * @returns {void}
     */
    function actualizarFechas() {
        actualizarFechasRelativas(filas);
    }

    /**
     * Detiene el temporizador al abandonar la página.
     * @returns {void}
     */
    function detenerIntervalo() {
        window.clearInterval(intervaloFechas);
    }

    /**
     * Devuelve el nombre visible de un campo ordenable.
     * @param {string} campo
     * @returns {string}
     */
    function nombreOrden(campo) {
        if (campo === 'equipo') {
            return 'equipo';
        }
        if (campo === 'prioridad') {
            return 'prioridad';
        }
        return 'fecha';
    }

}

iniciarPanel();
