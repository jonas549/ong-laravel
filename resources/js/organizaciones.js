/*
 * El buscador de organizaciones, compartido.
 *
 * Vivía dentro de `wizard.js`, para el paso 3 de «publica tu actividad». Se
 * sacó aquí al descubrir que la pantalla de crear cuenta de organizador
 * (`/mi-cuenta/registro`) seguía con un campo de texto normal: las mismas
 * reglas escritas dos veces habrían vuelto a separarse, y de hecho ya lo
 * habían hecho sin que nadie lo notara.
 *
 * **Regla al añadir un sitio nuevo que cree organizaciones:** monta este mixin
 * y el componente `<x-buscador-organizacion>`. No copies el comportamiento.
 *
 * Qué resuelve, y por qué cada cosa:
 *
 * - El cliente va a cargar de golpe el listado histórico de organizaciones
 *   participantes. Escribir «del» tiene que ofrecer las que ya están, para que
 *   nadie vuelva a crear la suya con una variante del nombre — que es
 *   exactamente como aparecieron los duplicados que hay hoy en producción.
 * - Se distinguen las libres de las que ya tienen cuenta en vez de esconder
 *   éstas: quien escribe el nombre de su organización y no la ve, la vuelve a
 *   crear. Verla y que le digan «ésta ya tiene cuenta, inicia sesión» le lleva
 *   a donde tiene que ir.
 *
 * Tres desenlaces al elegir una:
 *   libre  → se reclama: no se le vuelve a pedir nada suyo.
 *   tomada → ya tiene cuenta; se le manda a iniciar sesión.
 *   nada   → escribe un nombre nuevo y sigue el camino de siempre.
 *
 * Quien lo monta tiene que guardar la raíz del componente en `this.raiz`
 * dentro de su `init()`: aquí se usa para encontrar el campo, y `$el` dentro
 * del manejador de un botón es ese botón. Es la trampa de Alpine que ya
 * mordió cinco veces en este proyecto.
 */
export const buscadorOrganizaciones = (inicial = {}) => ({
    rutaOrganizaciones: inicial.rutaOrganizaciones ?? '/organizaciones/buscar',
    buscarOrg: inicial.buscarOrg ?? '',
    sugerencias: [],
    buscando: false,
    sugerenciasAbiertas: false,
    orgElegida: inicial.orgElegida ?? null,
    orgTomada: null,
    temporizadorOrg: null,

    /*
     * Si se está reclamando una organización que ya existía.
     *
     * **Es una propiedad y no un getter, y eso importa.** Este objeto se monta
     * con `...buscadorOrganizaciones(inicial)`, y el spread EVALÚA los getters
     * una sola vez y copia el resultado: `get reclamando()` llegaba al
     * componente como un `false` de piedra, así que al elegir una organización
     * libre no se escondía el logo ni salía el aviso. Se mantiene al día en
     * `elegirOrg()` y `soltarOrg()`, que son los dos únicos sitios que la
     * cambian.
     */
    reclamando: (inicial.orgElegida ?? null) !== null,

    /**
     * Pide sugerencias, con un respiro entre teclas.
     *
     * Sin el retardo, escribir «Fundación» son nueve peticiones y las
     * respuestas pueden llegar desordenadas: la de «Fund» después de la de
     * «Fundación», dejando en pantalla sugerencias de lo que ya no está
     * escrito. Se cancela la anterior en cada tecla.
     */
    escribirOrg(valor) {
        this.buscarOrg = valor;

        // Cambiar el nombre a mano deshace la elección: lo que hay escrito ya
        // no es la organización que se eligió.
        if (this.orgElegida && this.orgElegida.nombre !== valor) this.soltarOrg();
        this.orgTomada = null;

        clearTimeout(this.temporizadorOrg);

        if (valor.trim().length < 2) {
            this.sugerencias = [];
            this.sugerenciasAbiertas = false;

            return;
        }

        this.temporizadorOrg = setTimeout(() => this.pedirSugerencias(valor), 220);
    },

    async pedirSugerencias(valor) {
        this.buscando = true;

        try {
            const r = await fetch(`${this.rutaOrganizaciones}?q=${encodeURIComponent(valor)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!r.ok) throw new Error(r.status);

            const datos = await r.json();

            // Puede haber llegado tarde: si ya se escribió otra cosa, se tira.
            if (valor !== this.buscarOrg) return;

            this.sugerencias = datos.organizaciones ?? [];
            this.sugerenciasAbiertas = this.sugerencias.length > 0;
        } catch {
            /*
             * Un fallo del buscador no puede bloquear el formulario: es una
             * ayuda, no un requisito. Se apagan las sugerencias y se sigue
             * escribiendo el nombre a mano, que es el camino de siempre.
             */
            this.sugerencias = [];
            this.sugerenciasAbiertas = false;
        } finally {
            this.buscando = false;
        }
    },

    elegirOrg(org) {
        this.sugerenciasAbiertas = false;
        this.buscarOrg = org.nombre;

        if (!org.libre) {
            // Ya tiene cuenta. No se reclama: se le manda a iniciar sesión.
            this.orgElegida = null;
            this.reclamando = false;
            this.orgTomada = org;

            return;
        }

        this.orgTomada = null;
        this.orgElegida = org;
        this.reclamando = true;

        // El tipo viene con ella: la organización ya lo eligió en su día, y
        // volver a preguntarlo es parte de lo que P10 quita.
        if (org.tipo) this.tipo = org.tipo;

        /*
         * El campo puede no estar atado con `x-model` —en el registro lo
         * rellena el servidor— así que se escribe también en el DOM. Es
         * inofensivo donde sí hay atadura: el valor ya coincide.
         */
        const campo = this.campoOrganizacion();

        if (campo && campo.value !== org.nombre) campo.value = org.nombre;

        this.revisarCampo?.('org_nombre');
    },

    soltarOrg() {
        this.orgElegida = null;
        this.reclamando = false;
        this.orgTomada = null;
    },

    campoOrganizacion() {
        return this.raiz?.querySelector('input[name="org_nombre"]');
    },
});

/**
 * La pantalla de crear cuenta de organizador.
 *
 * Es el buscador de arriba más lo único que esa pantalla tiene de propio: el
 * tipo de organización, que ahí se elige a mano —en el wizard viene del paso
 * 2— y que al reclamar una organización deja de preguntarse.
 */
export const registroOrganizador = (inicial = {}) => ({
    ...buscadorOrganizaciones(inicial),

    tipo: inicial.tipo ?? '',
    raiz: null,

    init() {
        this.raiz = this.$el;
    },
});
