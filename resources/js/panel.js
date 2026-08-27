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
     * Aquí sí se usa `confirm()` y no el diálogo propio, a propósito: el envío
     * es síncrono y hay que decidir en el acto si se deja pasar. Abrir el
     * diálogo obligaría a cancelar el envío, esperar la respuesta y volver a
     * enviarlo, que es más piezas moviéndose para el mismo resultado.
     *
     * El diálogo propio sí se usa en las acciones de una sola fila, que es
     * donde se ven casi siempre.
     */
    confirmarAccion(evento) {
        const boton = evento.submitter;
        const texto = boton?.dataset?.confirmar;

        if (!texto) return true;

        return window.confirm(`${texto}\n\n${this.resumen()}.`);
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

    abrir(datos) {
        this.origen = document.activeElement;
        Object.assign(this, datos, { abierto: true });
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
        // Al abrirse, el foco entra al diálogo. Arranca en «Cancelar» y no en
        // el botón que borra: un Enter de más no puede ser lo que elimine.
        this.$watch('$store.confirmacion.abierto', (abierto) => {
            if (abierto) requestAnimationFrame(() => this.$refs.cancelar?.focus());
        });
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

    init() {
        this.entrada = this.$refs.entrada ?? null;
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

        if (pistas.requerido && v === '') {
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
