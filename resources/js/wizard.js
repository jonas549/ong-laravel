import { guiaDeErrores } from './formularios';
import { buscadorOrganizaciones } from './organizaciones';

/*
 * El wizard público de «publicar actividad».
 *
 * Vivía en un <script> dentro de wizard.blade.php. Se ha traído aquí al
 * añadirle la guía de errores: son dos componentes que se tienen que hablar
 * —la guía necesita saltar de paso para enseñar un campo del 3 estando en el
 * 4—, y componerlos desde el módulo es más claro que apilar objetos en una
 * cadena de plantilla.
 *
 * `estiloPaso` y `estiloCirculoPaso` se quedan donde estaban, en window: las
 * usan dos vistas, y una de ellas —la pantalla de envío— no monta este
 * componente.
 */
export const wizard = (inicial) => ({
    ...guiaDeErrores(inicial.errores ?? []),
    ...buscadorOrganizaciones(inicial),

    paso: inicial.paso,
    redirigir: false,

    /*
     * C4: con la ficha de la organización completa, el paso 3 no se pinta y la
     * navegación lo esquiva en los dos sentidos. El número del paso NO cambia
     * —«Tu actividad» sigue siendo el 4— para no tener que repasar las
     * referencias de la guía de errores, que trabaja con `data-paso`.
     */
    fichaCompleta: inicial.saltarPaso3 ?? false,

    /*
     * El tipo que tiene guardada su organización, para saber si lo ha cambiado
     * en el paso 2. Es null cuando no hay ficha —sin sesión—, y entonces
     * `tipoCambiado()` da true siempre, que es el comportamiento de siempre.
     */
    tipoDeLaFicha: inicial.tipoDeLaFicha ?? null,

    tipo: inicial.tipo,
    formato: inicial.formato,
    sinFecha: inicial.sinFecha,
    acc: inicial.acc,
    insc: inicial.insc,
    colab: inicial.colab,
    colabs: inicial.colabs,
    regionId: inicial.regionId ?? '',
    communeId: inicial.communeId ?? '',
    mismoCorreo: inicial.mismoCorreo,

    /*
     * Punto 28 de la tanda del 11/09. La casilla «usar el mismo correo»
     * existía y no rellenaba nada: marcarla no tenía ningún efecto visible y
     * el campo de contacto seguía vacío, así que o se escribía a mano o se
     * publicaba sin correo de contacto.
     *
     * Son dos estados y no uno porque hay que poder DESmarcar: si el de
     * contacto sólo reflejara al de la cuenta, al quitar la marca el usuario
     * se quedaría con el correo de la cuenta escrito y sin saber de dónde
     * salió. Guardando el suyo aparte, desmarcar le devuelve lo que tuviera.
     */
    correoCuenta: inicial.correoCuenta ?? '',
    correoContacto: inicial.correoContacto ?? '',
    descLen: inicial.descLen,

    comunas: inicial.comunas,
    otrosId: inicial.otrosId,
    limites: inicial.limites,

    sel: {
        temas: inicial.temas.map(Number),
        caracteristicas: inicial.caracteristicas.map(Number),
        publicos: inicial.publicos.map(Number),
    },

    /*
     * ── El buscador de organizaciones ──
     *
     * El comportamiento vive en `organizaciones.js` y lo comparten este paso 3
     * y la pantalla de crear cuenta de organizador. Aquí sólo quedan las rutas.
     */
    rutaOrganizaciones: inicial.rutaOrganizaciones ?? '/organizaciones/buscar',
    rutaEntrar: inicial.rutaEntrar ?? '/publicar-actividad/entrar',
    rutaDirecciones: inicial.rutaDirecciones ?? '/direcciones/buscar',

    init() {
        // `$el` sólo es la raíz aquí dentro. La guía busca los campos en los
        // cinco pasos, no sólo en el que se esté viendo, así que su ámbito es
        // la raíz entera y no el <form>.
        this.raiz = this.$el;
        this.iniciarGuia(this.$el);

        // Marcar la casilla copia el correo de la cuenta; desmarcarla devuelve
        // el que hubiera escrito. Y mientras está marcada, escribir en el de la
        // cuenta arrastra al de contacto, que es lo que se espera de un espejo.
        this.$watch('mismoCorreo', (activo) => {
            if (activo) this.correoContacto = this.correoCuenta;
        });

        this.$watch('correoCuenta', (valor) => {
            if (this.mismoCorreo) this.correoContacto = valor;
        });

        // Al abrir ya marcada —que es el valor por defecto— el espejo tiene que
        // estar puesto desde el principio, sin esperar a que nadie la toque.
        if (this.mismoCorreo && this.correoCuenta && ! this.correoContacto) {
            this.correoContacto = this.correoCuenta;
        }
    },

    /* ──────────────────────────────────────── navegación de pasos ── */

    /**
     * Si ha cambiado el tipo de organización respecto al que tiene guardado.
     *
     * Importa porque el tipo decide qué campos son obligatorios en el paso 3:
     * «Otra» pide describirse e «Institución educativa» pide la unidad. Son
     * datos que su ficha puede no tener, así que un cambio de tipo en el paso 2
     * vuelve a hacer falta el paso 3 aunque la ficha estuviera completa.
     */
    tipoCambiado() {
        return this.tipo !== this.tipoDeLaFicha;
    },

    /** Si el paso 3 no tiene nada que preguntar y se puede pasar de largo. */
    saltaPaso3() {
        return this.fichaCompleta && ! this.tipoCambiado();
    },

    /**
     * El número que se ENSEÑA en la barra para un paso.
     *
     * Con el 3 fuera, «Tu actividad» se enseña como el 3 aunque dentro siga
     * siendo el 4: dejar el hueco —1, 2, 4, 5— era decir que hay un paso que
     * no se está viendo.
     */
    numeroEnBarra(n) {
        return this.saltaPaso3() && n > 3 ? n - 1 : n;
    },

    /** Cambiar de paso a secas. Lo usa la guía para llevar a un campo. */
    irAlPaso(n) {
        this.paso = n;
    },

    /** Y con el salto arriba, que es lo que hace la barra de pasos. */
    irA(n) {
        /*
         * Si el 3 está saltado, nadie debe caer en él: ni con el botón de
         * «Continuar» del 2, ni volviendo desde el 4, ni pulsando la barra.
         * Se deja pasar de largo en la dirección en la que se iba.
         *
         * Sólo se esquiva el 3: si la ficha está completa, ese paso no tendría
         * ni un campo que enseñar y sería una pantalla vacía con un botón.
         */
        if (n === 3 && this.saltaPaso3()) {
            n = this.paso > 3 ? 2 : 4;
        }

        this.irAlPaso(n);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    /**
     * El botón «Continuar →».
     *
     * Revisa lo obligatorio de ESTE paso antes de dejar pasar. La barra de
     * pasos sigue navegando libre a propósito: ahí se va a mirar, y frenar a
     * quien vuelve al 3 para consultar un dato sería peor que el problema.
     */
    continuar(desde, hasta) {
        if (! this.revisarPaso(desde)) return;

        this.irA(hasta);
    },

    // Las dos opciones del modal hacen lo mismo que en el prototipo:
    // cerrar y seguir al paso 2.
    cerrarRedirigir() {
        this.redirigir = false;
        this.irA(2);
    },

    /* ───────────────────────────────────────────────── el resto ── */

    esOtra() { return this.tipo === 'Otra'; },
    esEmpresa() { return this.tipo === 'Empresa o institución privada'; },
    esEducativa() { return this.tipo === 'Institución educativa'; },

    marcado(grupo, id) {
        return this.sel[grupo].includes(id);
    },

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

        /*
         * Los chips escriben su valor en <input type="hidden"> que pinta un
         * x-for, así que hasta el siguiente tick la caja sigue pareciendo
         * vacía y la marca de error no se iría hasta el próximo envío.
         */
        this.$nextTick(() => this.revisarCampo(grupo));
    },

    /** Cuántos hay elegidos, para el contador que va junto al grupo. */
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

    /* ─────────────────────────── entrar sin salir del wizard (P14) ── */

    /*
     * Quien ya tiene cuenta puede identificarse a mitad de rellenar su
     * actividad. Lo importante no es el botón: es que **no pierda lo escrito**.
     *
     * Por eso la sesión se abre por `fetch` y la pantalla no se recarga. Lo
     * único que cambia es el bloque de «crea tu acceso», que pasa a decir a
     * qué cuenta se va a sumar la actividad, y los campos de la organización,
     * que se rellenan con los suyos.
     *
     * `conSesion` nace de lo que diga el servidor y a partir de ahí lo lleva
     * el componente: es lo que decide qué bloque se ve y si los campos de
     * correo y contraseña viajan o no.
     */
    conSesion: inicial.conSesion ?? false,
    accesoAbierto: false,
    accesoCorreo: '',
    accesoClave: '',
    accesoError: '',
    entrando: false,

    abrirAcceso() {
        this.accesoAbierto = true;
        this.accesoError = '';

        // El correo que ya hubiera escrito en el paso 3, de partida: casi
        // siempre es el mismo con el que se registró.
        if (! this.accesoCorreo && this.correoCuenta) this.accesoCorreo = this.correoCuenta;

        this.$nextTick(() => this.raiz?.querySelector('[data-acceso-correo]')?.focus());
    },

    cerrarAcceso() {
        this.accesoAbierto = false;
        this.accesoError = '';
        this.accesoClave = '';
    },

    async entrar() {
        if (this.entrando) return;

        this.entrando = true;
        this.accesoError = '';

        try {
            const respuesta = await fetch(this.rutaEntrar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ email: this.accesoCorreo, password: this.accesoClave }),
            });

            const datos = await respuesta.json().catch(() => ({}));

            if (! respuesta.ok) {
                /*
                 * 422 trae los mensajes de validación; cualquier otra cosa es
                 * un fallo que no sabemos explicar, y decir «no se pudo» es
                 * más honesto que inventar un motivo.
                 */
                this.accesoError = datos?.errors?.email?.[0]
                    ?? datos?.message
                    ?? 'No se pudo iniciar sesión. Inténtalo de nuevo.';

                return;
            }

            this.aplicarSesion(datos);
        } catch {
            this.accesoError = 'No se pudo conectar. Revisa tu conexión e inténtalo otra vez.';
        } finally {
            this.entrando = false;
        }
    },

    /**
     * Deja el formulario como si se hubiera entrado desde el principio.
     *
     * Lo escrito en el paso 4 —el título, la descripción, las fechas, los
     * chips— no se toca: esto sólo rellena lo que es de la organización, que
     * es justo lo que el wizard habría traído relleno.
     */
    aplicarSesion(datos) {
        this.conSesion = true;
        this.accesoAbierto = false;
        this.accesoClave = '';
        this.correoCuenta = datos.correo ?? '';

        /*
         * El token CSRF nuevo, antes que nada.
         *
         * Entrar regenera la sesión, y con ella el token. El formulario que la
         * persona tiene delante se pintó con el viejo, así que sin esto el
         * envío devuelve un 419 y pierde todo lo escrito: justo lo que este
         * punto venía a evitar. Lo encontró `pruebas/acceso-wizard.mjs` al
         * intentar publicar después de entrar a mitad.
         */
        if (datos.token) this.renovarToken(datos.token);

        const org = datos.organizacion;

        if (org) {
            this.buscarOrg = org.nombre ?? '';
            // Ya tiene organización: no hay nada que reclamar del listado.
            this.soltarOrg();

            if (org.tipo) this.tipo = org.tipo;

            this.rellenar('org_nombre', org.nombre);
            this.rellenar('org_tipo_otro', org.tipo_otro);
            this.rellenar('org_num_voluntarios', org.num_voluntarios);
            this.rellenar('org_unidad_educativa', org.unidad_educativa);
            this.rellenar('enlace_web', org.enlace_web);
            this.rellenar('enlace_red_social', org.enlace_red_social);
        }

        // Lo que estuviera marcado en rojo de un intento anterior ya no aplica.
        this.errores = [];
        this.limpiarMarcas();
    },

    /** Pone el token nuevo en el formulario y en la etiqueta de la cabecera. */
    renovarToken(token) {
        for (const campo of document.querySelectorAll('input[name="_token"]')) {
            campo.value = token;
        }

        const meta = document.querySelector('meta[name="csrf-token"]');

        // También el meta: de ahí lo leen las peticiones que van por fetch,
        // como el propio acceso o el autoguardado del panel.
        if (meta) meta.setAttribute('content', token);
    },

    rellenar(campo, valor) {
        const entrada = this.raiz?.querySelector(`[name="${campo}"]`);

        if (! entrada || valor === null || valor === undefined || valor === '') return;

        entrada.value = valor;
        entrada.dispatchEvent(new Event('input', { bubbles: true }));
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
