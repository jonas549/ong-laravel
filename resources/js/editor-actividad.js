import { guiaDeErrores } from './formularios';

/*
 * El editor de una actividad en «Mi cuenta».
 *
 * Vivía en un <script> dentro de la vista, igual que el wizard. Se trajo aquí al
 * darle la misma guía de errores: es la pantalla que más van a usar los
 * organizadores, y era la única que se había quedado con el aviso viejo —«hay N
 * datos por corregir», sin decir cuáles—.
 *
 * A diferencia del wizard, aquí no hay pasos: la guía no define `irAlPaso`, y
 * las llamadas de dentro están escritas con `?.` justamente para eso.
 */
export const editorActividad = (inicial) => ({
    ...guiaDeErrores(inicial.errores ?? []),

    sel: {
        temas: inicial.temas.map(Number),
        caracteristicas: inicial.caracteristicas.map(Number),
        publicos: inicial.publicos.map(Number),
        accesos: inicial.accesos.map(Number),
    },
    limites: inicial.limites,

    formato: inicial.formato,
    sinFecha: inicial.sinFecha,
    abierta: inicial.abierta,
    insc: inicial.insc,
    descLen: inicial.descLen,

    colaboradores: inicial.colaboradores,
    colab: inicial.colaboradores.length > 0,

    modalCancelar: false,

    rutaDirecciones: inicial.rutaDirecciones ?? '/direcciones/buscar',
    raiz: null,

    init() {
        // `$el` sólo es la raíz aquí dentro; ver la regla de panel.js.
        this.raiz = this.$el;
        this.iniciarGuia(this.$el);
    },

    marcado(grupo, id) {
        return this.sel[grupo].includes(id);
    },

    // Igual que en el wizard: al pasarse del tope, sale el más antiguo.
    alternar(grupo, id) {
        const lista = this.sel[grupo];
        const i = lista.indexOf(id);

        if (i !== -1) {
            lista.splice(i, 1);
        } else {
            const tope = this.limites[grupo];

            if (tope && lista.length >= tope) {
                lista.shift();
            }

            lista.push(id);
        }

        // Los hidden que llevan la selección los pinta un x-for: hasta el
        // siguiente tick la caja sigue pareciendo vacía.
        this.$nextTick(() => this.revisarCampo(grupo));
    },

    /** Cuántos hay elegidos, para la marca que va junto al grupo. */
    cuantos(grupo) {
        return this.sel[grupo].length;
    },

    activarColab() {
        this.colab = true;

        if (this.colaboradores.length === 0) {
            this.colaboradores.push({ nombre: '', tipo: '' });
        }
    },

    /* ─────────────────────────── sugerencias de dirección (P16) ── */

    /*
     * La dirección sigue siendo texto libre y sigue mandando lo que se
     * escriba. Lo que añade esto es que, al elegir una sugerencia, se guarda
     * además el PUNTO: latitud y longitud. Con el punto, el enlace del mapa de
     * la ficha lleva al sitio exacto en vez de a lo que el buscador adivine de
     * una cadena como «Metro Salvador, salida norte».
     *
     * **No bloquea nada.** Si el servicio no responde, la lista se queda vacía
     * y el campo funciona como el primer día.
     */
    sugerenciasDir: [],
    dirAbiertas: false,
    buscandoDir: false,
    latitud: inicial.latitud ?? '',
    longitud: inicial.longitud ?? '',
    temporizadorDir: null,

    escribirDireccion(valor) {
        /*
         * Cambiar la dirección a mano invalida el punto: lo que hay escrito ya
         * no es la sugerencia que se eligió, y dejar las coordenadas viejas
         * pondría el mapa en un sitio que no corresponde al texto. Es peor que
         * no tener punto.
         */
        this.olvidarPunto();

        clearTimeout(this.temporizadorDir);

        if (valor.trim().length < 3) {
            this.sugerenciasDir = [];
            this.dirAbiertas = false;

            return;
        }

        // Un respiro entre teclas: detrás hay un servicio de fuera.
        this.temporizadorDir = setTimeout(() => this.pedirDirecciones(valor), 320);
    },

    async pedirDirecciones(valor) {
        this.buscandoDir = true;

        try {
            const r = await fetch(`${this.rutaDirecciones}?q=${encodeURIComponent(valor)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!r.ok) throw new Error(r.status);

            const datos = await r.json();

            // Puede haber llegado tarde: si ya se escribió otra cosa, se tira.
            if (valor !== this.campoDireccion()?.value) return;

            this.sugerenciasDir = datos.direcciones ?? [];
            this.dirAbiertas = this.sugerenciasDir.length > 0;
        } catch {
            this.sugerenciasDir = [];
            this.dirAbiertas = false;
        } finally {
            this.buscandoDir = false;
        }
    },

    elegirDireccion(d) {
        const campo = this.campoDireccion();

        if (campo) {
            campo.value = d.etiqueta;
            campo.dispatchEvent(new Event('input', { bubbles: true }));
        }

        // El punto, después de escribir: el `input` de arriba dispara
        // `escribirDireccion`, que lo olvidaría.
        this.latitud = d.latitud;
        this.longitud = d.longitud;

        this.dirAbiertas = false;
        this.sugerenciasDir = [];
        this.revisarCampo('direccion');
    },

    olvidarPunto() {
        this.latitud = '';
        this.longitud = '';
    },

    get tienePunto() {
        return this.latitud !== '' && this.longitud !== '';
    },

    campoDireccion() {
        return this.raiz?.querySelector('input[name="direccion"]');
    },
});
