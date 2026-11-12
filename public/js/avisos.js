const mensajes = document.querySelectorAll('[data-aviso]');

if (mensajes.length > 0) {
    const contenedor = document.createElement('div');
    contenedor.className = 'avisos';
    mensajes[0].before(contenedor);

    mensajes.forEach((mensaje) => {
        const aviso = document.createElement('div');
        aviso.className = 'aviso';
        aviso.append(mensaje);
        const cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'aviso-cerrar';
        cerrar.textContent = 'Cerrar aviso';
        cerrar.addEventListener('click', () => {
            aviso.remove();
            if (!contenedor.hasChildNodes()) {
                contenedor.remove();
            }
        });
        aviso.append(cerrar);
        contenedor.append(aviso);
    });
}
