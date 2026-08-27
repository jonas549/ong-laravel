/*
 * El editor de contenido del home (bloque F).
 *
 * Tres componentes de Alpine y ninguna librería de fuera: el editor rico, el
 * autoguardado del formulario y el arrastre de la lista de secciones. El
 * proyecto quitó support.js justo por descargar React y Babel de unpkg en cada
 * carga, y esto son unas doscientas líneas.
 *
 * Nada de aquí es una medida de seguridad. Lo que se escriba acaba pasando por
 * SanitizadorHtml en el servidor con su lista blanca, y se vuelve a limpiar al
 * pintarlo. Esto decide qué es cómodo escribir, no qué es seguro guardar.
 */

const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/* ─────────────────────────────────────────────── editor de texto rico ── */

export const editorRico = (clave) => ({
    clave,

    init() {
        /*
         * Los comandos de bloque de execCommand necesitan saber en qué
         * envoltorio trabajar. Un contenteditable que arranca con texto suelto,
         * sin un <p>, hace que la primera pulsación de Enter genere <div> en
         * unos navegadores y <br> en otros. Envolviéndolo desde el principio,
         * lo que sale es siempre <p>, que es lo único que la lista blanca deja
         * pasar como bloque.
         */
        if (!this.$refs.campo.querySelector('p, ul, ol, h3, h4, blockquote')) {
            const texto = this.$refs.campo.innerHTML.trim();
            this.$refs.campo.innerHTML = '<p>' + (texto || '<br>') + '</p>';
        }

        this.sincronizar();
    },

    /** Copia el HTML del editor al input que se envía de verdad. */
    sincronizar() {
        this.$refs.oculto.value = this.$refs.campo.innerHTML;
    },

    cambio() {
        this.sincronizar();
        // Avisa al formulario que lo envuelve para que reprograme el
        // autoguardado. El editor no sabe nada del formulario; sólo grita.
        this.$el.dispatchEvent(new CustomEvent('editor-cambiado', { bubbles: true }));
    },

    mandar(orden) {
        this.$refs.campo.focus();
        document.execCommand(orden, false, null);
        this.cambio();
    },

    bloque(etiqueta) {
        this.$refs.campo.focus();
        document.execCommand('formatBlock', false, '<' + etiqueta + '>');
        this.cambio();
    },

    /**
     * Un enlace.
     *
     * Se pide por prompt y no con un panel propio: es la única entrada de texto
     * que necesita el editor y montarle un diálogo con su posicionamiento y su
     * foco es más superficie de la que justifica.
     *
     * `javascript:` se rechaza aquí además de en el servidor. En el servidor es
     * donde cuenta; aquí es para que quien lo intente vea por qué no, en vez de
     * publicar y encontrarse el enlace desaparecido sin explicación.
     */
    enlazar() {
        const seleccion = window.getSelection();

        if (!seleccion || seleccion.isCollapsed) {
            window.alert('Selecciona primero el texto que quieres enlazar.');
            return;
        }

        const url = (window.prompt('¿A dónde lleva el enlace?', 'https://') || '').trim();

        if (!url) return;

        const esquema = /^([a-z][a-z0-9+.-]*):/i.exec(url);
        const permitidos = ['http', 'https', 'mailto', 'tel'];

        if (esquema && !permitidos.includes(esquema[1].toLowerCase())) {
            window.alert('Ese tipo de enlace no se admite. Usa una dirección http, https, mailto o tel, o una ruta del propio sitio como /actividades.');
            return;
        }

        this.$refs.campo.focus();
        document.execCommand('createLink', false, url);
        this.cambio();
    },

    /**
     * Pegar sin formato.
     *
     * Pegar desde Word trae <span style="mso-...">, tablas de maquetación y
     * tipografías en puntos. El servidor lo tira igual, pero entonces se ve una
     * cosa mientras se escribe y otra al publicar. Pegando texto plano, lo que
     * se ve es lo que va a quedar.
     */
    pegar(evento) {
        const texto = (evento.clipboardData || window.clipboardData).getData('text/plain');

        document.execCommand('insertText', false, texto);
        this.cambio();
    },

    /** Deja el texto seleccionado sin negritas, cursivas ni enlaces. */
    limpiarFormato() {
        this.$refs.campo.focus();
        document.execCommand('removeFormat', false, null);
        document.execCommand('unlink', false, null);
        this.cambio();
    },
});

/* ────────────────────────────────────────── autoguardado del formulario ── */

export const editorSeccion = (urlBorrador) => ({
    aviso: '',
    reloj: null,
    enVuelo: false,

    init() {
        // Los editores ricos avisan por un evento que sube por el DOM: así el
        // formulario no tiene que conocerlos uno a uno.
        this.$el.addEventListener('editor-cambiado', () => this.tocado());

        // Si alguien se va con cambios sin guardar, el navegador pregunta. El
        // autoguardado corre cada tres segundos, así que esto sólo salta en esa
        // ventana corta o si la petición falló.
        window.addEventListener('beforeunload', (e) => {
            if (this.reloj || this.enVuelo) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    },

    tocado() {
        this.aviso = 'Sin guardar…';
        clearTimeout(this.reloj);
        this.reloj = setTimeout(() => this.guardar(), 3000);
    },

    sincronizarRicos() {
        // Al publicar hay que asegurarse de que los ocultos llevan lo último;
        // el input de un contenteditable no se actualiza solo.
        this.$el.querySelectorAll('.editor-rico').forEach((editor) => {
            const campo = editor.querySelector('.editor-rico-campo');
            const oculto = editor.querySelector('input[type=hidden]');
            if (campo && oculto) oculto.value = campo.innerHTML;
        });

        // Se publica: ya no hay nada pendiente que avisar al salir.
        clearTimeout(this.reloj);
        this.reloj = null;
    },

    async guardar() {
        this.reloj = null;
        this.sincronizarRicosSinTocarReloj();
        this.enVuelo = true;
        this.aviso = 'Guardando…';

        try {
            const cuerpo = new FormData(this.$el);
            // El borrador es un POST propio; el _method=PUT del formulario
            // haría que Laravel lo tomara por la ruta de publicar.
            cuerpo.delete('_method');

            const r = await fetch(urlBorrador, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
                body: cuerpo,
            });

            if (!r.ok) throw new Error(String(r.status));

            const datos = await r.json();
            this.aviso = 'Borrador guardado a las ' + datos.cuando;
        } catch {
            // Se dice que no se guardó en vez de callarse: un autoguardado que
            // falla en silencio es peor que no tener autoguardado, porque la
            // persona sigue escribiendo creyendo que está a salvo.
            this.aviso = 'No se pudo guardar el borrador. Publica para no perder lo escrito.';
        } finally {
            this.enVuelo = false;
        }
    },

    sincronizarRicosSinTocarReloj() {
        this.$el.querySelectorAll('.editor-rico').forEach((editor) => {
            const campo = editor.querySelector('.editor-rico-campo');
            const oculto = editor.querySelector('input[type=hidden]');
            if (campo && oculto) oculto.value = campo.innerHTML;
        });
    },
});

/* ──────────────────────────────────────────── arrastre de la lista ── */

export const ordenSecciones = (claves, urlOrden) => ({
    orden: [...claves],
    arrastrando: null,
    guardando: false,
    error: '',

    empezar(evento, clave) {
        this.arrastrando = clave;
        evento.dataTransfer.effectAllowed = 'move';
        // Firefox no arranca el arrastre si no se escribe algo aquí.
        evento.dataTransfer.setData('text/plain', clave);
    },

    /**
     * Reordena mientras se arrastra, moviendo la fila de sitio en el DOM.
     *
     * Se mueve el nodo directamente en vez de repintar la lista con Alpine
     * porque repintar a media operación cancela el arrastre del navegador: el
     * elemento que se está arrastrando deja de existir.
     */
    sobre(evento, clave) {
        if (!this.arrastrando || clave === this.arrastrando) return;

        const lista = this.$el.closest('ul') || this.$el.parentElement;
        const origen = lista.querySelector('[data-clave="' + this.arrastrando + '"]');
        const destino = lista.querySelector('[data-clave="' + clave + '"]');

        if (!origen || !destino) return;

        const caja = destino.getBoundingClientRect();
        const despues = evento.clientY > caja.top + caja.height / 2;

        destino.parentNode.insertBefore(origen, despues ? destino.nextSibling : destino);
    },

    soltar() {
        this.terminar();
    },

    terminar() {
        if (!this.arrastrando) return;

        this.arrastrando = null;

        const lista = this.$el.querySelector('ul') || this.$el;
        this.orden = [...lista.querySelectorAll('[data-clave]')].map((li) => li.getAttribute('data-clave'));

        this.guardar();
    },

    async guardar() {
        this.guardando = true;
        this.error = '';

        try {
            const cuerpo = new FormData();
            this.orden.forEach((clave) => cuerpo.append('orden[]', clave));

            const r = await fetch(urlOrden, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
                body: cuerpo,
            });

            if (!r.ok) throw new Error(String(r.status));
        } catch {
            this.error = 'No se pudo guardar el orden. Recarga la página para ver cómo quedó de verdad.';
        } finally {
            this.guardando = false;
        }
    },
});
