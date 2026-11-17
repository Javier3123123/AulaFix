/**
 * Modelo inmutable de una incidencia recibida desde el contrato JSON.
 */
export class Incidencia {
    #fechaCreacion;

    /**
     * Valida y conserva los datos de una incidencia.
     * @param {{id:number, equipo:string, categoria:string, prioridad:string, descripcion:string, estado:string, autor:string, creada_en:string}} datos
     * @throws {TypeError} Si los datos no cumplen el contrato del panel.
     */
    constructor(datos) {
        // Los valores categóricos deben coincidir con las claves del JSON generado por PHP.
        const categorias = ['hardware', 'software', 'red', 'otro'];
        const prioridades = ['baja', 'media', 'alta'];
        const estados = ['abierta', 'en_curso', 'resuelta'];

        // Valida tipos y valores enumerados antes de crear el modelo.
        if (datos === null || typeof datos !== 'object' || Array.isArray(datos)
            || !Number.isInteger(datos.id)
            || typeof datos.equipo !== 'string'
            || !categorias.includes(datos.categoria)
            || !prioridades.includes(datos.prioridad)
            || typeof datos.descripcion !== 'string'
            || !estados.includes(datos.estado)
            || typeof datos.autor !== 'string'
            || typeof datos.creada_en !== 'string') {
            throw new TypeError('Los datos de la incidencia no cumplen el contrato JSON.');
        }

        const fechaCreacion = new Date(datos.creada_en);
        if (!Number.isFinite(fechaCreacion.getTime())) {
            throw new TypeError('La fecha de creación de la incidencia no es válida.');
        }

        this.id = datos.id;
        this.equipo = datos.equipo;
        this.categoria = datos.categoria;
        this.prioridad = datos.prioridad;
        this.descripcion = datos.descripcion;
        this.estado = datos.estado;
        this.autor = datos.autor;
        this.creada_en = datos.creada_en;
        // La fecha privada evita depender de una fecha mutable al calcular días abiertos.
        this.#fechaCreacion = fechaCreacion;

        // Los modelos se comparten entre filtros y vistas sin permitir cambios accidentales.
        Object.freeze(this);
    }

    /**
     * Indica si la incidencia tiene prioridad alta y no está resuelta.
     * @returns {boolean}
     */
    get esUrgente() {
        return this.prioridad === 'alta' && this.estado !== 'resuelta';
    }

    /**
     * Calcula los días completos que lleva abierta; una incidencia resuelta vale cero.
     * @returns {number}
     */
    get diasAbierta() {
        if (this.estado === 'resuelta') {
            return 0;
        }

        // Las diferencias negativas se limitan a cero si el reloj adelanta la creación.
        const milisegundosPorDia = 24 * 60 * 60 * 1000;
        return Math.max(0, Math.floor((Date.now() - this.#fechaCreacion.getTime()) / milisegundosPorDia));
    }
}