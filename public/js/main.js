(() => {
    const claveBorrador = 'aulafix.nueva-incidencia.borrador.v1';
    const errorPanel = document.getElementById('error-panel-incidencias');

    if (document.body.dataset.borrarBorrador === 'true') {
        try {
            localStorage.removeItem(claveBorrador);
        } catch (error) {
            console.error('No se pudo borrar el borrador de la incidencia.', error);
            errorPanel.textContent = 'La incidencia se guardó, pero no se pudo borrar el borrador de este navegador.';
            errorPanel.hidden = false;
        }
    }

    const datosElemento = document.getElementById('datos-incidencias');
    const panel = document.getElementById('panel-incidencias');
    if (!datosElemento || !panel) {
        return;
    }

    const vistaServidor = document.getElementById('vista-servidor');
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
    let incidencias;

    try {
        incidencias = JSON.parse(datosElemento.textContent);
        if (!Array.isArray(incidencias) || !incidencias.every(esIncidenciaValida)) {
            throw new TypeError('El JSON de incidencias no tiene el formato esperado.');
        }
    } catch (error) {
        errorPanel.textContent = 'No se pudo preparar el panel. Puedes consultar el listado del servidor.';
        errorPanel.hidden = false;
        console.error('No se pudieron leer los datos del panel de incidencias.', error);
        return;
    }

    const estadosValidos = ['todas', 'abierta', 'en_curso', 'resuelta'];
    const prioridadesValidas = ['todas', 'baja', 'media', 'alta'];
    const ordenesValidos = ['fecha', 'equipo', 'prioridad'];
    const claveVista = 'aulafix.incidencias.v1';
    const tabs = Array.from(panel.querySelectorAll('[role="tab"]'));
    const busqueda = document.getElementById('buscar-incidencia');
    const filtroPrioridad = document.getElementById('filtrar-prioridad');
    const tablaResultados = document.getElementById('filas-incidencias');
    const contador = document.getElementById('contador-incidencias');
    const mensajeVacio = document.getElementById('sin-resultados');
    const panelResultados = document.getElementById('panel-resultados');
    const pesosPrioridad = { baja: 1, media: 2, alta: 3 };
    const formatoRelativo = new Intl.RelativeTimeFormat('es', { numeric: 'always' });
    const formatoCompleto = new Intl.DateTimeFormat('es', {
        dateStyle: 'long',
        timeStyle: 'short',
    });
    let avisoAlmacenamientoMostrado = false;
    const vista = cargarVista();
    const estado = {
        pestaña: estadosValidos.includes(vista.pestaña) ? vista.pestaña : 'todas',
        busqueda: typeof vista.busqueda === 'string' ? vista.busqueda : '',
        prioridad: prioridadesValidas.includes(vista.prioridad) ? vista.prioridad : 'todas',
        orden: ordenesValidos.includes(vista.orden) ? vista.orden : 'fecha',
        direccion: vista.direccion === 'asc' ? 'asc' : 'desc',
    };

    busqueda.value = estado.busqueda;
    filtroPrioridad.value = estado.prioridad;
    actualizarResumen();
    actualizarPestanas();
    actualizarOrden();
    panel.hidden = false;
    vistaServidor.hidden = true;
    renderizar();

    busqueda.addEventListener('input', () => {
        estado.busqueda = busqueda.value;
        guardarVista();
        renderizar();
    });

    filtroPrioridad.addEventListener('change', () => {
        estado.prioridad = filtroPrioridad.value;
        guardarVista();
        renderizar();
    });

    tabs.forEach((tab, indice) => {
        tab.addEventListener('click', () => activarPestana(tab));
        tab.addEventListener('keydown', (evento) => {
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
        });
    });

    panel.querySelectorAll('[data-orden]').forEach((boton) => {
        boton.addEventListener('click', () => {
            if (estado.orden === boton.dataset.orden) {
                estado.direccion = estado.direccion === 'asc' ? 'desc' : 'asc';
            } else {
                estado.orden = boton.dataset.orden;
                estado.direccion = estado.orden === 'fecha' ? 'desc' : 'asc';
            }
            actualizarOrden();
            guardarVista();
            renderizar();
        });
    });

    function esIncidenciaValida(incidencia) {
        return incidencia !== null
            && typeof incidencia === 'object'
            && Number.isInteger(incidencia.id)
            && typeof incidencia.equipo === 'string'
            && typeof incidencia.categoria === 'string'
            && typeof incidencia.prioridad === 'string'
            && typeof incidencia.descripcion === 'string'
            && typeof incidencia.estado === 'string'
            && typeof incidencia.autor === 'string'
            && typeof incidencia.creada_en === 'string'
            && Number.isFinite(Date.parse(incidencia.creada_en))
            && Object.prototype.hasOwnProperty.call(etiquetas.estados, incidencia.estado)
            && Object.prototype.hasOwnProperty.call(etiquetas.categorias, incidencia.categoria)
            && Object.prototype.hasOwnProperty.call(etiquetas.prioridades, incidencia.prioridad);
    }

    function cargarVista() {
        try {
            const guardada = localStorage.getItem(claveVista);
            if (guardada === null) {
                return {};
            }
            const vistaGuardada = JSON.parse(guardada);
            return vistaGuardada !== null && typeof vistaGuardada === 'object' && !Array.isArray(vistaGuardada)
                ? vistaGuardada
                : {};
        } catch (error) {
            informarErrorAlmacenamiento(error);
            return {};
        }
    }

    function guardarVista() {
        try {
            localStorage.setItem(claveVista, JSON.stringify(estado));
        } catch (error) {
            informarErrorAlmacenamiento(error);
        }
    }

    function informarErrorAlmacenamiento(error) {
        if (!avisoAlmacenamientoMostrado) {
            console.warn('No se pudieron guardar las preferencias del panel.', error);
            avisoAlmacenamientoMostrado = true;
        }
        errorPanel.textContent = 'El panel funciona, pero no se pudieron guardar sus preferencias en este navegador.';
        errorPanel.hidden = false;
    }

    function activarPestana(tab) {
        estado.pestaña = tab.dataset.estado;
        actualizarPestanas();
        guardarVista();
        renderizar();
    }

    function actualizarPestanas() {
        tabs.forEach((tab) => {
            const activa = tab.dataset.estado === estado.pestaña;
            tab.setAttribute('aria-selected', String(activa));
            tab.tabIndex = activa ? 0 : -1;
            if (activa) {
                panelResultados.setAttribute('aria-labelledby', tab.id);
            }
        });
    }

    function actualizarOrden() {
        panel.querySelectorAll('[data-campo-orden]').forEach((cabecera) => {
            const boton = cabecera.querySelector('[data-orden]');
            const activa = cabecera.dataset.campoOrden === estado.orden;
            cabecera.setAttribute(
                'aria-sort',
                activa ? (estado.direccion === 'asc' ? 'ascending' : 'descending') : 'none'
            );
            boton.setAttribute(
                'aria-label',
                `Ordenar por ${nombreOrden(cabecera.dataset.campoOrden)}${activa ? `, ${estado.direccion === 'asc' ? 'ascendente' : 'descendente'}` : ''}`
            );
        });
    }

    function actualizarResumen() {
        const resumen = incidencias.reduce((totales, incidencia) => {
            if (incidencia.estado === 'abierta') {
                totales.abiertas += 1;
            } else if (incidencia.estado === 'en_curso') {
                totales.enCurso += 1;
            } else if (incidencia.estado === 'resuelta') {
                totales.resueltas += 1;
            }
            if (incidencia.prioridad === 'alta' && incidencia.estado !== 'resuelta') {
                totales.urgentes += 1;
            }
            return totales;
        }, { abiertas: 0, enCurso: 0, resueltas: 0, urgentes: 0 });

        document.getElementById('total-abiertas').textContent = String(resumen.abiertas);
        document.getElementById('total-en-curso').textContent = String(resumen.enCurso);
        document.getElementById('total-resueltas').textContent = String(resumen.resueltas);
        document.getElementById('total-urgentes').textContent = String(resumen.urgentes);
    }

    function renderizar() {
        const termino = normalizar(busqueda.value.trim());
        const filtradas = incidencias.filter((incidencia) => {
            const coincideEstado = estado.pestaña === 'todas' || incidencia.estado === estado.pestaña;
            const coincidePrioridad = estado.prioridad === 'todas' || incidencia.prioridad === estado.prioridad;
            const texto = normalizar(`${incidencia.equipo} ${incidencia.descripcion} ${incidencia.autor}`);
            return coincideEstado && coincidePrioridad && texto.includes(termino);
        });

        filtradas.sort(compararIncidencias);
        tablaResultados.replaceChildren(...filtradas.map(crearFila));
        const cantidad = filtradas.length;
        contador.textContent = `Mostrando ${cantidad} de ${incidencias.length} ${incidencias.length === 1 ? 'incidencia' : 'incidencias'}.`;
        mensajeVacio.textContent = incidencias.length === 0
            ? 'Todavía no hay incidencias registradas.'
            : 'No hay incidencias que coincidan con los filtros.';
        mensajeVacio.hidden = cantidad !== 0;
    }

    function compararIncidencias(primera, segunda) {
        let comparacion = 0;
        if (estado.orden === 'equipo') {
            comparacion = primera.equipo.localeCompare(segunda.equipo, 'es', { sensitivity: 'base' });
        } else if (estado.orden === 'prioridad') {
            comparacion = pesosPrioridad[primera.prioridad] - pesosPrioridad[segunda.prioridad];
        } else {
            comparacion = Date.parse(primera.creada_en) - Date.parse(segunda.creada_en);
        }

        if (comparacion === 0) {
            comparacion = primera.id - segunda.id;
        }
        return estado.direccion === 'asc' ? comparacion : -comparacion;
    }

    function crearFila(incidencia) {
        const urgente = incidencia.prioridad === 'alta' && incidencia.estado !== 'resuelta';
        const fila = document.createElement('tr');
        if (urgente) {
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
        if (urgente) {
            const etiquetaUrgente = document.createElement('span');
            etiquetaUrgente.className = 'etiqueta-urgente';
            etiquetaUrgente.textContent = 'Urgente sin resolver';
            celdaEstado.append(document.createTextNode(' '), etiquetaUrgente);
        }
        fila.append(celdaEstado);

        const fecha = new Date(incidencia.creada_en);
        const fechaCompleta = formatoCompleto.format(fecha);
        const textoRelativo = formatoRelativoFecha(fecha);
        const elementoFecha = document.createElement('time');
        elementoFecha.dateTime = incidencia.creada_en;
        elementoFecha.title = fechaCompleta;
        elementoFecha.setAttribute('aria-label', `${textoRelativo} (${fechaCompleta})`);
        elementoFecha.textContent = textoRelativo;
        const celdaFecha = document.createElement('td');
        celdaFecha.append(elementoFecha);
        fila.append(celdaFecha);
        fila.append(crearCelda(incidencia.autor));
        return fila;
    }

    function crearCelda(texto) {
        const celda = document.createElement('td');
        celda.textContent = texto;
        return celda;
    }

    function formatoRelativoFecha(fecha) {
        const segundos = Math.round((fecha.getTime() - Date.now()) / 1000);
        const distancia = Math.abs(segundos);
        if (distancia < 60) {
            return formatoRelativo.format(segundos, 'second');
        }
        if (distancia < 3600) {
            return formatoRelativo.format(Math.round(segundos / 60), 'minute');
        }
        if (distancia < 86400) {
            return formatoRelativo.format(Math.round(segundos / 3600), 'hour');
        }
        return formatoRelativo.format(Math.round(segundos / 86400), 'day');
    }

    function normalizar(texto) {
        return texto
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('es');
    }

    function nombreOrden(orden) {
        if (orden === 'equipo') {
            return 'equipo';
        }
        if (orden === 'prioridad') {
            return 'prioridad';
        }
        return 'fecha';
    }
})();