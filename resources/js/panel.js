/*
 * Los componentes transversales del panel (bloque H).
 *
 * REGLA DE ESTE ARCHIVO, escrita donde se tropieza: en Alpine, `$el` es el
 * elemento del manejador que se esté ejecutando, NO la raíz del componente. Si
 * el `x-on:` vive en un hijo —una casilla, un campo, una fila— entonces `$el`
 * es ese hijo. Este proyecto se cayó cuatro veces por eso: el autoguardado, el
 * reordenar, el buscador y los círculos del wizard.
 *
 * Por eso **todo componente de aquí que necesite hablar de su raíz la guarda en
 * `init()`**, que es el único sitio donde `$el` sí lo es.
 */

import { token } from './csrf';

/* ─────────────────────────────────── selección múltiple en tablas ── */

export const tablaSeleccion = (que = 'registros') => ({
    marcadas: 0,
    raiz: null,

    init() {
        this.raiz = this.$el;
        this.recontar();
    },

    casillas() {
        return [...this.raiz.querySelectorAll('input[name="ids[]"]')];
    },

    recontar() {
        const todas = this.casillas();
        this.marcadas = todas.filter((c) => c.checked).length;

        // La casilla de la cabecera refleja lo que hay: marcada, vacía o a
        // medias. Sin el estado intermedio miente cuando hay selección parcial.
        const cabecera = this.$refs.todas;

        if (cabecera) {
            cabecera.checked = this.marcadas > 0 && this.marcadas === todas.length;
            cabecera.indeterminate = this.marcadas > 0 && this.marcadas < todas.length;
        }
    },

    marcarTodas(valor) {
        this.casillas().forEach((c) => { c.checked = valor; });
        this.recontar();
    },

    limpiar() {
        this.marcarTodas(false);
    },

    resumen() {
        return this.marcadas === 1
            ? `1 de ${this.casillas().length} seleccionado`
            : `${this.marcadas} de ${this.casillas().length} ${que} seleccionados`;
    },

    /**
     * Antes de una acción masiva destructiva, pregunta.
     *
     * Con el mismo diálogo que las acciones de una sola fila, no con el
     * `confirm()` del navegador: sale con la tipografía del sistema, no se
     * puede escribir en el castellano del proyecto, y ya se quitó de los otros
     * dos sitios donde estaba.
     *
     * El envío sí es síncrono, así que se corta, se pregunta, y al aceptar se
     * vuelve a enviar con `requestSubmit(boton)`, que conserva el `name` y el
     * `value` del botón pulsado y por tanto qué acción era. La marca
     * `data-confirmado` en el formulario es lo que evita que la segunda vuelta
     * pregunte otra vez.
     */
    confirmarAccion(evento) {
        const formulario = evento.target;
        const boton = evento.submitter;
        const texto = boton?.dataset?.confirmar;

        if (formulario.dataset.confirmado === '1') {
            delete formulario.dataset.confirmado;

            return true;
        }

        if (!texto) return true;

        this.$store.confirmacion.abrir({
            titulo: boton.textContent.trim(),
            texto: `${this.resumen()}. ${texto}`,
            confirmar: 'Sí, continuar',
            peligro: true,
            objetivo: boton,
        });

        return false;
    },
});

/* ──────────────────────────────────────── barra de filtros ── */

export const barraFiltros = () => ({
    listo: false,
    reloj: null,
    formulario: null,

    init() {
        this.formulario = this.$el;
        // Marca que Alpine está en pie: hasta entonces se ve el botón Filtrar,
        // para que el listado se pueda filtrar sin JavaScript.
        this.listo = true;
    },

    enviar() {
        clearTimeout(this.reloj);
        this.formulario.submit();
    },

    tecleo() {
        clearTimeout(this.reloj);

        const q = (this.$refs.buscador?.value ?? '').trim();

        // Con una sola letra no se busca: son miles de filas y ninguna pista.
        if (q.length === 1) return;

        this.reloj = setTimeout(() => this.enviar(), 450);
    },
});

/* ──────────────────────────── diálogo de confirmación ── */

export const almacenConfirmacion = {
    abierto: false,
    accion: '',
    metodo: 'DELETE',
    titulo: '',
    texto: '',
    confirmar: 'Sí, continuar',
    peligro: true,
    // Desde dónde se abrió, para devolverle el foco al cerrar.
    origen: null,
    /*
     * Botón de otro formulario que hay que reenviar al aceptar, en vez de
     * enviar el formulario del propio diálogo. Lo usan las acciones masivas de
     * la tabla, que llevan marcados los ids y no se pueden rehacer desde aquí.
     */
    objetivo: null,

    abrir(datos) {
        this.origen = document.activeElement;
        this.objetivo = null;
        Object.assign(this, datos, { abierto: true });
    },

    /**
     * Reenvía el formulario de quien preguntó.
     *
     * Sólo se usa cuando hay `objetivo`, es decir cuando quien pregunta es una
     * acción masiva de la tabla. En el caso normal el diálogo envía su propio
     * formulario sin pasar por aquí.
     *
     * `requestSubmit(boton)` conserva el `name` y el `value` del botón, que es
     * lo que dice qué acción masiva se pidió. La marca `data-confirmado` avisa
     * al manejador de que esta vuelta ya viene contestada.
     */
    aceptar() {
        const objetivo = this.objetivo;

        if (! objetivo) return;

        const formulario = objetivo.form;

        this.abierto = false;
        this.origen = null;
        this.objetivo = null;

        if (! formulario) return;

        formulario.dataset.confirmado = '1';
        formulario.requestSubmit(objetivo);
    },

    cerrar() {
        this.abierto = false;
        // El foco vuelve al botón que lo abrió: si no, quien navega con teclado
        // se queda en el <body> sin sitio desde el que seguir.
        this.origen?.focus?.();
        this.origen = null;
    },
};

export const dialogoConfirmar = () => ({
    init() {
        this.$watch('$store.confirmacion.abierto', (abierto) => {
            if (abierto) this.entrarAlDialogo();
        });
    },

    /**
     * Mete el foco dentro del diálogo, insistiendo hasta que entra.
     *
     * Un solo `requestAnimationFrame` no bastaba: en producción el foco se
     * quedaba en el botón «Borrar» que lo abrió, **fuera** del `role="dialog"`,
     * y había que pulsar Tab para entrar. Con teclado eso es grave: no sabes
     * dónde estás, y un Enter reflejo vuelve a abrir el diálogo en vez de
     * cancelarlo.
     *
     * El motivo es que Alpine aplica el `x-show` en su propio ciclo, y hasta que
     * el elemento no está visible no admite el foco. Se reintenta unos cuantos
     * fotogramas —no un temporizador largo, que se notaría— y se comprueba que
     * el foco acabó de verdad dentro. Si aun así no entra, se enfoca la caja del
     * diálogo, que lleva `tabindex="-1"` justo para eso.
     */
    entrarAlDialogo(intentos = 12) {
        const destino = this.$refs.cancelar;

        if (! destino || ! this.$store.confirmacion.abierto) return;

        destino.focus();

        if (document.activeElement === destino) return;

        if (intentos > 0) {
            requestAnimationFrame(() => this.entrarAlDialogo(intentos - 1));

            return;
        }

        // Último recurso: al menos que el foco esté dentro del diálogo.
        this.$refs.caja?.focus();
    },

    /**
     * Mantiene el tabulador dentro del diálogo.
     *
     * Alpine trae `x-trap` para esto, pero es un plugin aparte y el proyecto no
     * lo carga; traerlo entero por tres elementos no compensa. Son dos botones
     * y un ciclo.
     */
    ciclarFoco(evento) {
        const focos = [this.$refs.cancelar, this.$refs.aceptar].filter(Boolean);

        if (focos.length === 0) return;

        const primero = focos[0];
        const ultimo = focos[focos.length - 1];

        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    },
});

/* ───────────────────────────────────── avisos flash ── */

export const flash = (auto = false) => ({
    visible: true,

    init() {
        // Sólo los de éxito se van solos: un error hay que poder volver a
        // leerlo mientras se arregla lo que falló.
        if (auto) setTimeout(() => { this.visible = false; }, 8000);
    },
});

/* ─────────────────────────── validación en tiempo real ── */

export const campoValidado = (pistas) => ({
    aviso: '',
    tocado: false,
    entrada: null,
    // Si hace falta rellenarlo AHORA MISMO. Con `required_if` cambia al vuelo,
    // y de esto cuelga el asterisco de la etiqueta.
    obligatorio: false,

    init() {
        this.entrada = this.$refs.entrada ?? null;

        /*
         * Con `required_if`, el aviso depende de OTRO campo. Hay que volver a
         * mirar cuando aquél cambia: si no, se marca en rojo un campo que ya
         * dejó de hacer falta, o al revés.
         */
        this.obligatorio = this.haceFalta();

        const otro = this.deQuienDepende();

        if (! otro) return;

        const alCambiar = () => {
            this.obligatorio = this.haceFalta();

            if (this.tocado) this.revisar();
        };

        // `change` cubre los selectores y `input` los campos de texto.
        otro.addEventListener('change', alCambiar);
        otro.addEventListener('input', alCambiar);
    },

    /** El campo del que depende un `required_if`, dentro del mismo formulario. */
    deQuienDepende() {
        const nombre = pistas.requeridoSi?.campo;

        if (! nombre || ! this.entrada) return null;

        const formulario = this.entrada.form ?? document;

        return formulario.querySelector(`[name="${nombre}"]`);
    },

    /** ¿Hace falta rellenarlo, contando el `required_if`? */
    haceFalta() {
        if (pistas.requerido) return true;
        if (! pistas.requeridoSi) return false;

        const otro = this.deQuienDepende();

        return !! otro && pistas.requeridoSi.valores.includes(otro.value);
    },

    valor() {
        return (this.entrada?.value ?? '').trim();
    },

    /**
     * Mientras se escribe sólo se QUITA el aviso, nunca se pone.
     *
     * Marcar en rojo un campo vacío que aún se está rellenando es regañar antes
     * de tiempo. El aviso aparece al salir del campo; al volver a escribir se
     * borra en cuanto lo escrito lo resuelve.
     */
    alEscribir() {
        if (this.tocado && this.aviso) this.revisar();
    },

    revisar() {
        this.tocado = true;
        this.aviso = this.problema();
    },

    problema() {
        const v = this.valor();

        if (this.haceFalta() && v === '') {
            return `${pistas.etiqueta || 'Este campo'} no puede quedar vacío.`;
        }

        if (v === '') return '';

        if (pistas.formato === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) {
            return 'Eso no parece una dirección de correo.';
        }

        if (pistas.formato === 'url' && !/^https?:\/\/\S+\.\S+/i.test(v)) {
            return 'La dirección tiene que empezar por http:// o https://';
        }

        if (pistas.numero) {
            const n = Number(v.replace(',', '.'));

            if (Number.isNaN(n)) return 'Escribe un número.';
            if (pistas.min !== undefined && n < pistas.min) return `El mínimo es ${pistas.min}.`;
            if (pistas.max !== undefined && n > pistas.max) return `El máximo es ${pistas.max}.`;

            return '';
        }

        if (pistas.max !== undefined && v.length > pistas.max) {
            return `Se pasa por ${v.length - pistas.max} caracteres. El máximo es ${pistas.max}.`;
        }

        if (pistas.min !== undefined && v.length < pistas.min) {
            return `Faltan caracteres: el mínimo es ${pistas.min}.`;
        }

        return '';
    },

    restantes() {
        return (pistas.max ?? 0) - this.valor().length;
    },

    /** ¿Queda poco para el tope? Sólo entonces se enseña el contador. */
    cerca() {
        if (!pistas.max) return false;

        return this.restantes() <= Math.max(20, Math.round(pistas.max * 0.1));
    },
});

/* ─────────────────────────────────── estados de carga ── */

/**
 * Marca como ocupado lo que se acaba de pulsar.
 *
 * El panel se pinta en el servidor, así que casi todo son cargas de página
 * completas: el «estado de carga» que hace falta no es un esqueleto, es que el
 * botón que acabas de pulsar diga que está haciendo algo y no se pueda pulsar
 * dos veces. Un doble clic en «Publicar» ya provocó un 500 en este proyecto.
 *
 * Se engancha una sola vez a nivel de documento en vez de componente a
 * componente: así vale para todo formulario del panel, incluidos los que se
 * escriban después.
 */
export const iniciarEstadosDeCarga = () => {
    const ocupar = (boton) => {
        if (!boton || boton.dataset.ocupado === '1') return;

        boton.dataset.ocupado = '1';
        boton.dataset.textoOriginal = boton.textContent;

        const dice = boton.dataset.cargando;
        if (dice) boton.textContent = dice;

        boton.classList.add('esta-cargando');
        boton.setAttribute('aria-busy', 'true');

        /*
         * `disabled` NO se pone: un botón deshabilitado no envía su propio
         * `name`/`value`, y las acciones masivas los necesitan para saber qué
         * se pulsó. Se bloquea el segundo clic con `pointer-events` desde el
         * CSS y con la marca de arriba.
         */
    };

    document.addEventListener('submit', (e) => {
        const formulario = e.target;

        if (!(formulario instanceof HTMLFormElement) || formulario.dataset.sinCarga) return;

        // Un formulario que no valida no llega a irse; marcarlo lo dejaría
        // ocupado para siempre.
        if (!formulario.checkValidity?.()) return;

        ocupar(e.submitter ?? formulario.querySelector('button[type=submit]'));
    });

    // Las descargas (exportar) no navegan, así que hay que soltarlas solas.
    document.addEventListener('click', (e) => {
        const enlace = e.target.closest('a[data-cargando]');

        if (!enlace) return;

        ocupar(enlace);
        setTimeout(() => liberar(enlace), 4000);
    });

    /*
     * Ordenar y paginar son navegaciones normales, así que el navegador ya
     * enseña su indicador en la pestaña. Pero en una tabla eso queda lejos de
     * donde está mirando la mano: se pulsa un encabezado y durante medio
     * segundo no pasa nada visible. Se atenua la tabla, que es la respuesta
     * más barata a «te he oído».
     */
    document.addEventListener('click', (e) => {
        const enlace = e.target.closest('.col-orden, .paginacion-enlace');

        if (!enlace || enlace.tagName !== 'A') return;

        const tabla = enlace.closest('.panel-tabla') ?? document.querySelector('.panel-tabla');

        tabla?.classList.add('tabla-cargando');
        tabla?.setAttribute('aria-busy', 'true');
    });

    // Igual al filtrar, que también recarga.
    document.addEventListener('submit', (e) => {
        if (! (e.target instanceof HTMLFormElement) || ! e.target.classList.contains('panel-filtros')) return;

        const tabla = document.querySelector('.panel-tabla');

        tabla?.classList.add('tabla-cargando');
        tabla?.setAttribute('aria-busy', 'true');
    });

    // Al volver con «atras», la tabla vuelve de la cache atenuada.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('.tabla-cargando').forEach((t) => {
            t.classList.remove('tabla-cargando');
            t.removeAttribute('aria-busy');
        });
    });

    const liberar = (el) => {
        if (el.dataset.ocupado !== '1') return;

        el.dataset.ocupado = '0';
        if (el.dataset.textoOriginal) el.textContent = el.dataset.textoOriginal;
        el.classList.remove('esta-cargando');
        el.removeAttribute('aria-busy');
    };

    /*
     * Al volver con el botón «atrás», Chrome restaura la página desde su caché
     * tal cual estaba: con el botón todavía en «Guardando…» y sin poder
     * pulsarse. Hay que soltarlo a mano.
     */
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;

        document.querySelectorAll('[data-ocupado="1"]').forEach(liberar);
    });
};

/* ────────────────────────────────── reordenar arrastrando ── */

/**
 * Arrastrar filas de una tabla para cambiar su orden.
 *
 * Es hermano del que ordena las secciones del home, pero generico: sirve para
 * cualquier listado con columna `orden`. Se movio aqui en vez de copiarlo
 * porque el bloque G lo necesitaba en cuatro sitios mas.
 *
 * Como todo lo de este archivo, la raiz se guarda en `init()`: dentro de un
 * `x-on:dragend` puesto en un `<tr>`, `$el` es esa fila y no la tabla.
 */
export const filasOrdenables = (urlOrden) => ({
    arrastrando: null,
    guardando: false,
    error: '',
    cuerpo: null,

    init() {
        this.cuerpo = this.$el.querySelector('tbody');
    },

    empezar(evento, id) {
        this.arrastrando = String(id);
        evento.dataTransfer.effectAllowed = 'move';
        // Firefox no arranca el arrastre si no se escribe algo aqui.
        evento.dataTransfer.setData('text/plain', String(id));
    },

    /*
     * Se mueve el nodo en el DOM en vez de repintar con Alpine: repintar a
     * media operacion cancela el arrastre del navegador, porque el elemento que
     * se esta arrastrando deja de existir.
     */
    sobre(evento, id) {
        if (!this.arrastrando || String(id) === this.arrastrando) return;

        const origen = this.cuerpo?.querySelector(`[data-fila="${this.arrastrando}"]`);
        const destino = this.cuerpo?.querySelector(`[data-fila="${id}"]`);

        if (!origen || !destino) return;

        const caja = destino.getBoundingClientRect();
        const despues = evento.clientY > caja.top + caja.height / 2;

        destino.parentNode.insertBefore(origen, despues ? destino.nextSibling : destino);
    },

    async terminar() {
        if (!this.arrastrando) return;

        this.arrastrando = null;

        const orden = [...(this.cuerpo?.querySelectorAll('[data-fila]') ?? [])]
            .map((f) => f.getAttribute('data-fila'));

        if (!orden.length) {
            this.error = 'No se pudo leer el orden nuevo. Recarga la pagina.';

            return;
        }

        this.guardando = true;
        this.error = '';

        try {
            const cuerpo = new FormData();
            orden.forEach((id) => cuerpo.append('orden[]', id));

            const r = await fetch(urlOrden, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token(), Accept: 'application/json' },
                body: cuerpo,
            });

            if (!r.ok) throw new Error(String(r.status));
        } catch (e) {
            /*
             * El motivo va a la consola a proposito. Un `catch` mudo escondio
             * durante una tarde que aqui faltaba una funcion: en pantalla ponia
             * «no se pudo guardar» y parecia cosa del servidor.
             */
            console.error('No se pudo guardar el orden:', e);
            this.error = 'No se pudo guardar el orden. Recarga para ver como quedo de verdad.';
        } finally {
            this.guardando = false;
        }
    },
});
