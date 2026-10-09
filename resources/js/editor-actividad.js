import { guiaDeErrores } from './formularios';

/** Photon no respondió: la dirección se guarda igual, sin el punto. */
const AVISO_DIR_SIN_SERVICIO = 'Ahora no podemos sugerir direcciones. Escríbela completa —calle, número y comuna—: se guarda igual.';

/*
 * El editor de una actividad en «Mi cuenta».
 *
 * Vivía en un <script> dentro de la vista, igual que el wizard. Se trajo aquí al
 * darle la misma guía de errores: es la pantalla que más van a usar los
 * organizadores, y era la única que se había quedado con el aviso viejo —«hay N
 * datos por corregir», sin decir cuáles—.
 *
 * Desde la tanda del 09/10 pinta los MISMOS campos que el paso 4 del wizard
 * (resources/views/public/partials/campos-actividad.blade.php), así que tiene
 * que dar el mismo estado y los mismos métodos que `wizard` para esa parte:
 * la lista está en la cabecera del parcial. Si se añade algo allí, va en los
 * dos componentes.
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
    },
    limites: inicial.limites,

    formato: inicial.formato,
    sinFecha: inicial.sinFecha,
    // Punto 5 del 05/10: «La actividad dura varios días» enseña la fecha de
    // término. Y punto 4: si la actividad ya pasó, la fecha no se cambia.
    varios: inicial.varios ?? false,
    fechaBloqueada: inicial.fechaBloqueada ?? false,
    // Punto 6 del 05/10: sustituye a «abierta», cuya pregunta salió del editor.
    cerrada: inicial.cerrada ?? false,
    insc: inicial.insc,
    acc: inicial.acc ?? false,
    descLen: inicial.descLen,

    // Colaboradores: sólo los nombres, en etiquetas, como en el wizard.
    colabs: inicial.colabs ?? [],
    colab: (inicial.colabs ?? []).length > 0,

    regionId: inicial.regionId ?? '',
    communeId: inicial.communeId ?? '',
    comunas: inicial.comunas ?? {},
    otrosId: inicial.otrosId ?? null,

    correoCuenta: inicial.correoCuenta ?? '',
    correoContacto: inicial.correoContacto ?? '',
    mismoCorreo: inicial.mismoCorreo ?? false,

    tipo: inicial.tipo ?? '',
    editaLaFicha: inicial.editaLaFicha ?? true,

    modalCancelar: false,

    rutaDirecciones: inicial.rutaDirecciones ?? '/direcciones/buscar',
    raiz: null,

    init() {
        // `$el` sólo es la raíz aquí dentro; ver la regla de panel.js.
        this.raiz = this.$el;
        this.iniciarGuia(this.$el);

        // Como en el wizard: marcar la casilla copia el correo de la cuenta.
        this.$watch('mismoCorreo', (activo) => {
            if (activo) this.correoContacto = this.correoCuenta;
        });
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

    // "¿Cuál?" solo aparece si el público marcado incluye "Otros".
    publicoOtros() {
        return this.otrosId !== null && this.sel.publicos.includes(Number(this.otrosId));
    },

    comunasDeRegion() {
        return this.comunas[this.regionId] ?? [];
    },

    cambiarRegion() {
        this.communeId = '';
    },

    agregarColaborador(e) {
        const v = e.target.value.trim();
        if (!v) return;
        this.colabs.push(v);
        e.target.value = '';
    },

    // Los trabajadores voluntarios son un dato de la ficha: sólo los cambia la
    // cuenta principal, y sólo de una empresa.
    esEmpresa() {
        return this.editaLaFicha && this.tipo === 'Empresa o institución privada';
    },

    // Los dos enlaces son de la ficha (varias cuentas por organización).
    fichaAjena() {
        return ! this.editaLaFicha;
    },

    /* ─────────────────────────── sugerencias de dirección (P16) ── */

    /*
     * La dirección sigue siendo texto libre y sigue mandando lo que se
     * escriba. Lo que añade esto es que, al elegir una sugerencia, se guarda
     * además el PUNTO: latitud y longitud. Con el punto, el enlace del mapa de
     * la ficha lleva al sitio exacto en vez de a lo que el buscador adivine de
     * una cadena como «Metro Salvador, salida norte».
     *
     * **No bloquea nada.** Si el servicio no responde, la lista se queda vacía,
     * se avisa debajo del campo y el campo funciona como el primer día.
     */
    sugerenciasDir: [],
    dirAbiertas: false,
    buscandoDir: false,
    /* Lo que se dice cuando no hay sugerencias o el servicio no responde. */
    avisoDir: '',
    latitud: inicial.latitud ?? '',
    longitud: inicial.longitud ?? '',
    temporizadorDir: null,
    /* Mientras se aplica una sugerencia, el campo no se busca a sí mismo. */
    aplicandoDireccion: false,

    escribirDireccion(valor) {
        /*
         * Si lo que acaba de escribir en el campo fuimos nosotros al aplicar
         * una sugerencia, aquí no hay nada que hacer.
         *
         * `elegirDireccion` tiene que lanzar un `input` —lo necesita la guía de
         * errores para quitar la marca de campo pendiente— y el manejador de
         * ese evento es este mismo método, que da por hecho que quien escribe
         * es una persona. Sin la bandera, elegir una sugerencia olvidaba el
         * punto recién guardado y programaba OTRA búsqueda, ahora con la
         * etiqueta entera: cuando esa segunda consulta devolvía algo, la lista
         * se volvía a abrir sola. De ahí que pareciera intermitente —depende de
         * si el geocodificador encuentra la etiqueta completa— y de ahí el C2.
         */
        if (this.aplicandoDireccion) return;

        /*
         * Cambiar la dirección a mano invalida el punto: lo que hay escrito ya
         * no es la sugerencia que se eligió, y dejar las coordenadas viejas
         * pondría el mapa en un sitio que no corresponde al texto. Es peor que
         * no tener punto.
         */
        this.olvidarPunto();
        this.avisoDir = '';

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

            /*
             * Que no se quede callado. Antes un fallo del geocodificador y
             * «no hay nada» se veían igual: desaparecía «Buscando…» y no
             * pasaba nada más, así que quien escribía no sabía si esperar.
             */
            if (datos.disponible === false) {
                this.avisoDir = AVISO_DIR_SIN_SERVICIO;
            } else if (! this.sugerenciasDir.length) {
                this.avisoDir = 'No encontramos sugerencias para esa dirección. Puedes dejarla tal cual.';
            }
        } catch {
            this.sugerenciasDir = [];
            this.dirAbiertas = false;

            if (valor === this.campoDireccion()?.value) this.avisoDir = AVISO_DIR_SIN_SERVICIO;
        } finally {
            this.buscandoDir = false;
        }
    },

    elegirDireccion(d) {
        const campo = this.campoDireccion();

        /*
         * La bandera cubre todo lo que provoque el `input` de aquí abajo. Se
         * baja en el `finally` para que un fallo a mitad no deje el buscador
         * mudo para el resto de la sesión.
         */
        this.aplicandoDireccion = true;

        try {
            // Y se cancela lo que hubiera en vuelo: una búsqueda programada
            // hace 300 ms llegaría después y reabriría la lista igual.
            clearTimeout(this.temporizadorDir);

            if (campo) {
                campo.value = d.etiqueta;
                campo.dispatchEvent(new Event('input', { bubbles: true }));
            }

            this.latitud = d.latitud;
            this.longitud = d.longitud;

            this.dirAbiertas = false;
            this.sugerenciasDir = [];
            this.revisarCampo('direccion');
        } finally {
            this.aplicandoDireccion = false;
        }
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
