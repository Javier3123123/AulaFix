/**
 * Agrupa los avisos de una página y añade un control para cerrarlos.
 */
const mensajes = document.querySelectorAll('[data-aviso]');
// Solo se crea el contenedor cuando la página tiene avisos.
if (mensajes.length > 0) {
    instalarContenedorAvisos(mensajes);
}

/**
 * Mueve los avisos a un contenedor común sin ocultarlos si no hay JavaScript.
 * @param {NodeListOf<Element>} listaMensajes
 * @returns {void}
 */
function instalarContenedorAvisos(listaMensajes) {
    const contenedor = document.createElement('div');
    contenedor.className = 'avisos';
    listaMensajes[0].before(contenedor);

    // Agrupa cada mensaje y le añade su propio botón para cerrarlo.
    for (const mensaje of listaMensajes) {
        const aviso = document.createElement('div');
        aviso.className = 'aviso';
        aviso.append(mensaje);
        aviso.append(crearBotonCerrar(aviso, contenedor));
        contenedor.append(aviso);
    }
}

/**
 * Crea el botón accesible que cierra un aviso.
 * @param {HTMLDivElement} aviso
 * @param {HTMLDivElement} contenedor
 * @returns {HTMLButtonElement}
 */
function crearBotonCerrar(aviso, contenedor) {
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'aviso-cerrar';
    boton.textContent = 'Cerrar aviso';
    boton.addEventListener('click', cerrarAviso.bind(null, aviso, contenedor));
    return boton;
}

/**
 * Quita un aviso y su contenedor cuando ya no queda ninguno.
 * @param {HTMLDivElement} aviso
 * @param {HTMLDivElement} contenedor
 * @returns {void}
 */
function cerrarAviso(aviso, contenedor) {
    aviso.remove();
    // Elimina también el contenedor para no dejar elementos vacíos en la página.
    if (!contenedor.hasChildNodes()) {
        contenedor.remove();
    }
}
