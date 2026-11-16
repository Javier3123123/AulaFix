(() => {
    const form = document.querySelector('form[data-restaurar-borrador]');
    if (!form) {
        return;
    }

    const claveBorrador = 'aulafix.nueva-incidencia.borrador.v1';
    const equipo = form.querySelector('#equipo_id');
    const categoria = form.querySelector('#categoria');
    const prioridad = form.querySelectorAll('input[name="prioridad"]');
    const descripcion = form.querySelector('#descripcion');
    const contador = form.querySelector('#contador-descripcion');
    const estadoBorrador = document.getElementById('estado-borrador');
    let avisoAlmacenamientoMostrado = false;

    if (form.dataset.restaurarBorrador === 'true') {
        restaurarBorrador();
    } else {
        guardarBorrador();
    }

    actualizarContador();
    form.addEventListener('input', guardarCambios);
    form.addEventListener('change', guardarCambios);

    function guardarCambios() {
        actualizarContador();
        guardarBorrador();
    }

    function actualizarContador() {
        const cantidad = Array.from(descripcion.value).length;
        contador.textContent = `${cantidad} de 500 caracteres`;
    }

    function restaurarBorrador() {
        let borrador;
        try {
            const guardado = localStorage.getItem(claveBorrador);
            if (guardado === null) {
                return;
            }
            borrador = JSON.parse(guardado);
        } catch (error) {
            informarErrorAlmacenamiento('No se pudo recuperar el borrador.', error);
            return;
        }

        if (borrador === null || typeof borrador !== 'object') {
            informarErrorAlmacenamiento(
                'El borrador guardado no tiene un formato válido.',
                new TypeError('Formato de borrador no válido.')
            );
            return;
        }

        if (typeof borrador.equipoId === 'string'
            && Array.from(equipo.options).some((opcion) => opcion.value === borrador.equipoId)) {
            equipo.value = borrador.equipoId;
        }
        if (typeof borrador.categoria === 'string'
            && Array.from(categoria.options).some((opcion) => opcion.value === borrador.categoria)) {
            categoria.value = borrador.categoria;
        }
        if (typeof borrador.prioridad === 'string') {
            prioridad.forEach((opcion) => {
                opcion.checked = opcion.value === borrador.prioridad;
            });
        }
        if (typeof borrador.descripcion === 'string') {
            descripcion.value = Array.from(borrador.descripcion).slice(0, 500).join('');
        }
    }

    function guardarBorrador() {
        const datos = {
            equipoId: equipo.value,
            categoria: categoria.value,
            prioridad: Array.from(prioridad).find((opcion) => opcion.checked)?.value || '',
            descripcion: descripcion.value,
        };

        try {
            localStorage.setItem(claveBorrador, JSON.stringify(datos));
            estadoBorrador.hidden = true;
        } catch (error) {
            informarErrorAlmacenamiento('No se pudo guardar el borrador.', error);
        }
    }

    function informarErrorAlmacenamiento(mensaje, error) {
        if (!avisoAlmacenamientoMostrado) {
            console.warn(mensaje, error);
            avisoAlmacenamientoMostrado = true;
        }
        estadoBorrador.textContent = mensaje;
        estadoBorrador.hidden = false;
    }
})();