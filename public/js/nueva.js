/**
 * Cuenta la descripción y conserva un borrador local del formulario de alta.
 */
import { guardarBorrador, leerBorrador } from './preferencias.js';

const formulario = document.querySelector('form[data-restaurar-borrador]');
if (formulario) {
    iniciarFormulario(formulario);
}

/**
 * Conecta el contador, el borrador y la restauración de los campos.
 * @param {HTMLFormElement} form
 * @returns {void}
 */
function iniciarFormulario(form) {
    const equipo = form.querySelector('#equipo_id');
    const categoria = form.querySelector('#categoria');
    const prioridad = form.querySelectorAll('input[name="prioridad"]');
    const descripcion = form.querySelector('#descripcion');
    const contador = form.querySelector('#contador-descripcion');
    const estadoBorrador = document.getElementById('estado-borrador');
    let avisoAlmacenamientoMostrado = false;

    if (!equipo || !categoria || prioridad.length === 0 || !descripcion || !contador || !estadoBorrador) {
        console.error('No se pudo preparar el formulario de incidencias: faltan campos requeridos.');
        return;
    }

    if (form.dataset.restaurarBorrador === 'true') {
        restaurarBorrador();
    } else {
        guardarCambios();
    }
    actualizarContador();
    form.addEventListener('input', guardarCambios);
    form.addEventListener('change', guardarCambios);

    /**
     * Actualiza el contador y guarda los valores actuales.
     * @returns {void}
     */
    function guardarCambios() {
        actualizarContador();
        let seleccionada = '';
        for (const opcion of prioridad) {
            if (opcion.checked) {
                seleccionada = opcion.value;
                break;
            }
        }
        try {
            guardarBorrador({
                equipoId: equipo.value,
                categoria: categoria.value,
                prioridad: seleccionada,
                descripcion: descripcion.value,
            });
            estadoBorrador.hidden = true;
        } catch (error) {
            informarErrorAlmacenamiento('No se pudo guardar el borrador.', error);
        }
    }

    /**
     * Cuenta puntos de código para mantener el contador alineado con el servidor.
     * @returns {void}
     */
    function actualizarContador() {
        contador.textContent = `${Array.from(descripcion.value).length} de 500 caracteres`;
    }

    /**
     * Recupera un borrador válido y conserva solo opciones presentes en el formulario.
     * @returns {void}
     */
    function restaurarBorrador() {
        let borrador;
        try {
            borrador = leerBorrador();
        } catch (error) {
            informarErrorAlmacenamiento('No se pudo recuperar el borrador.', error);
            return;
        }
        if (borrador === null) {
            return;
        }
        if (!esBorradorValido(borrador)) {
            informarErrorAlmacenamiento(
                'El borrador guardado no tiene el formato esperado.',
                new TypeError('Formato de borrador no válido.')
            );
            return;
        }

        if (tieneOpcion(equipo, borrador.equipoId)) {
            equipo.value = borrador.equipoId;
        }
        if (tieneOpcion(categoria, borrador.categoria)) {
            categoria.value = borrador.categoria;
        }
        for (const opcion of prioridad) {
            opcion.checked = opcion.value === borrador.prioridad;
        }
        descripcion.value = Array.from(borrador.descripcion).slice(0, 500).join('');
    }

    /**
     * Comprueba que el objeto guardado tenga las propiedades esperadas.
     * @param {Record<string, unknown>} borrador
     * @returns {boolean}
     */
    function esBorradorValido(borrador) {
        return typeof borrador.equipoId === 'string'
            && typeof borrador.categoria === 'string'
            && typeof borrador.prioridad === 'string'
            && typeof borrador.descripcion === 'string';
    }

    /**
     * Informa de los fallos de almacenamiento sin impedir el uso del formulario.
     * @param {string} mensaje
     * @param {unknown} error
     * @returns {void}
     */
    function informarErrorAlmacenamiento(mensaje, error) {
        if (!avisoAlmacenamientoMostrado) {
            console.error(mensaje, error);
            avisoAlmacenamientoMostrado = true;
        }
        estadoBorrador.textContent = mensaje;
        estadoBorrador.hidden = false;
    }
}

/**
 * Comprueba que una opción de un desplegable siga disponible.
 * @param {HTMLSelectElement} desplegable
 * @param {string} valor
 * @returns {boolean}
 */
function tieneOpcion(desplegable, valor) {
    for (const opcion of desplegable.options) {
        if (opcion.value === valor) {
            return true;
        }
    }
    return false;
}
