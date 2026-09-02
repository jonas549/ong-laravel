/*
 * Guía de errores de formulario: que el usuario SE ENTERE de lo que falta.
 *
 * Esto no valida nada que el servidor no valide ya. Lo que arregla es lo otro,
 * que es lo que llegó como «tira error aunque los campos estén completos»: la
 * validación funcionaba y el aviso existía, pero vivía a mitad de un formulario
 * de mil píxeles, en letra pequeña rosa, sin nada arriba que lo anunciara. Al
 * volver del POST la página aparecía por su comienzo y no había forma de saber
 * qué había fallado sin bajar a buscarlo campo por campo.
 *
 * REGLA DE ESTE ARCHIVO, la misma que la de panel.js: en Alpine `$el` es el
 * elemento del manejador que se está ejecutando, NO la raíz del componente. Por
 * eso el ámbito se guarda en `init()`, que es el único sitio donde `$el` sí lo
 * es. Este proyecto se ha caído cuatro veces por olvidarlo.
 *
 * ── Cómo sabe qué campos son obligatorios ──
 *
 * Leyendo el DOM, no una segunda lista de reglas. Cada campo obligatorio va
 * envuelto en una caja marcada así:
 *
 *     <div data-campo="publicos" data-etiqueta="Público beneficiado" data-obligatorio>
 *
 * y se considera relleno si dentro hay algún control con valor. Eso vale igual
 * para un <input>, para un <select> y para un grupo de chips, porque los chips
 * escriben su selección en <input type="hidden"> dentro de esa misma caja: sin
 * ninguno marcado no hay ningún hidden, y la caja sale vacía. Un solo criterio
 * para los tres, y **la lista de reglas sigue siendo la del servidor**, que es
 * la única forma de que el formulario no acabe diciendo una cosa y el servidor
 * otra. Lo que no se puede mirar desde aquí —un formato, un `unique`, un
 * `exists`— no se finge: lo dice el servidor al enviar, y entonces el resumen
 * se rellena con lo que él devolvió.
 */

/** Respeta a quien pidió que no le animen la pantalla. */
const desplazamiento = () => (
    window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
);

export const guiaDeErrores = (erroresIniciales = []) => ({
    /** [{ campo, etiqueta, paso, mensaje }] — del servidor o de la revisión previa. */
    errores: erroresIniciales,

    /** Dónde buscar los campos. En el wizard es la raíz, que abarca los 5 pasos. */
    ambito: null,

    /**
     * @param {HTMLElement} ambito  la raíz del componente, guardada en init()
     */
    iniciarGuia(ambito) {
        this.ambito = ambito;

        // Si venimos de un POST rechazado, la lista ya llega rellena desde el
        // servidor: hay que llevar al usuario hasta ella.
        if (this.errores.length > 0) {
            this.$nextTick(() => this.enseniarErrores());
        }
    },

    /* ─────────────────────────────────────────── lectura del DOM ── */

    /** Todas las cajas de campo, obligatorias o no. */
    cajas() {
        return [...this.ambito.querySelectorAll('[data-campo]')];
    },

    caja(nombre) {
        return this.ambito.querySelector('[data-campo="' + CSS.escape(nombre) + '"]');
    },

    /**
     * Los controles que llevan el valor de una caja.
     *
     * Los `hidden` cuentan, y son justo los que hacen que esto valga para los
     * chips: cada chip marcado deja uno dentro de la caja.
     */
    controles(caja) {
        return [...caja.querySelectorAll('input[name], select[name], textarea[name]')];
    },

    tieneValor(caja) {
        return this.controles(caja).some((control) => {
            if (control.disabled) return false;

            if (control.type === 'checkbox' || control.type === 'radio') {
                return control.checked;
            }

            return String(control.value ?? '').trim() !== '';
        });
    },

    /**
     * `required_without` leído del DOM.
     *
     * Tres campos del wizard —región, comuna y dirección— dejan de hacer
     * falta al marcar «disponible de forma permanente», porque una actividad
     * sin fecha puede no tener sitio fijo. La regla del servidor lo dice con
     * `required_without:sin_fecha_definida`; aquí se dice con
     * `data-obligatorio-salvo="sin_fecha_definida"` en la caja.
     *
     * **Esto no es opcional, es corrección.** Sin ello el formulario exige
     * más que el servidor: frena un envío que el servidor habría aceptado, y
     * lo frena pidiendo un campo que ya no hace falta. Es el mismo fallo que
     * toda esta guía viene a arreglar, con los papeles cambiados.
     *
     * La fecha no lo necesita porque ahí el `x-bind:disabled` ya la apaga, y
     * un control deshabilitado no cuenta.
     */
    loReleva(caja) {
        const nombre = caja.dataset.obligatorioSalvo;

        if (! nombre) return false;

        const otro = this.ambito.querySelector('[name="' + CSS.escape(nombre) + '"]');

        if (! otro) return false;

        return otro.type === 'checkbox' || otro.type === 'radio'
            ? otro.checked
            : String(otro.value ?? '').trim() !== '';
    },

    /**
     * ¿Este campo se le está pidiendo AHORA a esta persona?
     *
     * Hay campos que sólo aparecen según lo que se haya contestado antes —
     * «¿Cuál?» sale al marcar el público «Otros», la fecha se deshabilita con
     * «disponible de forma permanente»—. Exigir uno que no está en pantalla es
     * exactamente el fallo que se viene a arreglar, en su peor versión: un
     * error sobre un campo que no existe.
     *
     * Un PASO oculto es harina de otro costal y no cuenta como ocultarse: al
     * enviar se revisan los cinco pasos, no sólo el que se está mirando. Por eso
     * el recorrido hacia arriba salta los contenedores marcados con `data-paso`.
     */
    seLePide(caja) {
        for (let el = caja; el && el !== this.ambito; el = el.parentElement) {
            if (el.hasAttribute('data-paso')) continue;

            if (getComputedStyle(el).display === 'none') return false;
        }

        const controles = this.controles(caja);

        return controles.length === 0 || controles.some((c) => ! c.disabled);
    },

    pasoDe(caja) {
        const contenedor = caja.closest('[data-paso]');

        return contenedor ? Number(contenedor.dataset.paso) : null;
    },

    describir(caja) {
        return {
            campo: caja.dataset.campo,
            etiqueta: caja.dataset.etiqueta || caja.dataset.campo,
            paso: this.pasoDe(caja),
            mensaje: '',
        };
    },

    /* ─────────────────────────────────── revisión antes de enviar ── */

    /**
     * Los obligatorios que están vacíos, en el orden en que se ven.
     *
     * @param {number|null} paso  sólo los de ese paso, o todos si es null
     */
    camposQueFaltan(paso = null) {
        return this.cajas()
            .filter((caja) => caja.hasAttribute('data-obligatorio'))
            .filter((caja) => paso === null || this.pasoDe(caja) === paso)
            .filter((caja) => ! this.loReleva(caja))
            .filter((caja) => this.seLePide(caja) && ! this.tieneValor(caja))
            .map((caja) => this.describir(caja));
    },

    /**
     * Corta el envío si falta algo y lo enseña.
     *
     * Devuelve false para el `x-on:submit`; el servidor sigue validando después,
     * porque esto es una cortesía, no una barrera.
     */
    revisarAntesDeEnviar(evento) {
        const faltan = this.camposQueFaltan();

        if (faltan.length === 0) {
            this.errores = [];
            this.limpiarMarcas();

            return true;
        }

        evento.preventDefault();
        this.errores = faltan;
        this.enseniarErrores();

        return false;
    },

    /** Igual, pero de un solo paso: para el botón «Continuar →» del wizard. */
    revisarPaso(paso) {
        const faltan = this.camposQueFaltan(paso);

        this.errores = faltan;

        if (faltan.length === 0) {
            this.limpiarMarcas();

            return true;
        }

        this.enseniarErrores();

        return false;
    },

    /* ──────────────────────────────────────────── enseñar y saltar ── */

    tituloDeErrores() {
        return this.errores.length === 1
            ? 'Falta 1 campo por completar'
            : 'Faltan ' + this.errores.length + ' campos por completar';
    },

    /**
     * Lleva la pantalla al resumen y marca las cajas que fallan.
     *
     * Al resumen y no al primer campo: el resumen está justo encima del
     * formulario, dice cuántos faltan y los enumera, y cada uno de sus renglones
     * salta a su campo de un clic. Ir directamente al campo enseñaría uno de los
     * cuatro que faltan y escondería los otros tres.
     */
    enseniarErrores() {
        const primero = this.errores[0];

        if (primero?.paso) this.irAlPaso?.(primero.paso);

        this.$nextTick(() => {
            this.marcarCajas();

            const resumen = this.resumenVisible();

            if (! resumen) {
                // Sin resumen en pantalla, al campo: es peor, pero es mucho
                // mejor que dejarlo sin nada.
                if (primero) this.irAlCampo(primero.campo);

                return;
            }

            resumen.scrollIntoView({ behavior: desplazamiento(), block: 'center' });
            // `role="alert"` lo anuncia; el foco es para que quien navega con
            // teclado siga desde aquí y no desde donde estaba.
            resumen.focus({ preventScroll: true });
        });
    },

    resumenVisible() {
        return [...this.ambito.querySelectorAll('[data-resumen-errores]')]
            .find((el) => el.offsetParent !== null) ?? null;
    },

    /** Salta a un campo, cambiando de paso si hace falta, y le da el foco. */
    irAlCampo(nombre) {
        const caja = this.caja(nombre);

        if (! caja) return;

        const paso = this.pasoDe(caja);

        if (paso) this.irAlPaso?.(paso);

        this.$nextTick(() => {
            caja.scrollIntoView({ behavior: desplazamiento(), block: 'center' });

            // En un grupo de chips no hay control que enfocar —los valores van
            // en hidden—, así que se enfoca el primer chip.
            const foco = this.controles(caja).find((c) => ! c.disabled && c.type !== 'hidden')
                ?? caja.querySelector('button, [href], [tabindex]');

            foco?.focus({ preventScroll: true });
        });
    },

    /* ───────────────────────────────────────────────────── marcas ── */

    /**
     * El borde y el aviso de cada caja que falla.
     *
     * En la caja entera y no sólo en el texto de debajo: un grupo de chips no
     * tiene borde que poner en rosa, y era justo el que nadie veía.
     */
    marcarCajas() {
        this.limpiarMarcas();

        this.errores.forEach(({ campo }) => {
            const caja = this.caja(campo);

            if (! caja) return;

            caja.classList.add('campo-fallido');

            this.controles(caja)
                .filter((c) => c.type !== 'hidden')
                .forEach((c) => c.setAttribute('aria-invalid', 'true'));
        });
    },

    limpiarMarcas() {
        this.cajas().forEach((caja) => {
            caja.classList.remove('campo-fallido');

            this.controles(caja).forEach((c) => c.removeAttribute('aria-invalid'));
        });
    },

    /**
     * Al rellenar un campo se le quita la marca en el acto, y sale del resumen.
     *
     * Dejar en rojo lo que ya se corrigió es la otra mitad de no enterarse: no
     * se sabe qué queda.
     */
    /**
     * Repasa el resumen entero.
     *
     * Para cuando cambia algo que cambia QUÉ hace falta, y no sólo si un
     * campo está relleno: marcar «disponible de forma permanente» releva a
     * tres campos de golpe, y dejarlos en el resumen sería pedir algo que ya
     * no se pide.
     */
    repasar() {
        if (this.errores.length === 0) return;

        const siguenFaltando = this.camposQueFaltan().map((e) => e.campo);

        this.errores = this.errores.filter((e) => siguenFaltando.includes(e.campo));
        this.marcarCajas();
    },

    revisarCampo(nombre) {
        if (! nombre) return;

        const caja = this.caja(nombre);

        if (! caja || ! this.tieneValor(caja)) return;

        caja.classList.remove('campo-fallido');
        this.controles(caja).forEach((c) => c.removeAttribute('aria-invalid'));
        this.errores = this.errores.filter((e) => e.campo !== nombre);
    },
});

/* ═══════════════════════════════════ formulario suelto con guía ══ */

/**
 * Para los formularios que no son el wizard: uno solo, sin pasos.
 *
 * Se usa así, con la lista que devuelva el servidor:
 *     <form x-data="formularioGuiado(...)" x-on:submit="revisarAntesDeEnviar($event)">
 */
export const formularioGuiado = (errores = []) => ({
    ...guiaDeErrores(errores),

    init() {
        this.iniciarGuia(this.$el);
    },
});

/* ═════════════════════════════════════════════════ campo de fecha ══ */

/**
 * El campo de fecha: máscara al escribir, calendario al lado, pegado intacto.
 *
 * Sigue siendo `type="text"` a propósito. Se pasó a texto libre porque los
 * campos nativos de fecha **no dejan pegar**, y pegar la fecha desde otro sitio
 * es de lo que más se hace aquí. Pero quedó sin decir en qué orden van los
 * números, y ese silencio es el que hace dudar entre dd/mm/aaaa y mm/dd/aaaa.
 *
 * Así que el texto se queda y se le añaden las tres cosas que le faltaban:
 * el formato escrito en el hueco, una máscara que va poniendo las barras
 * sola, y un calendario de verdad —un `input[type=date]` transparente encima
 * del icono— que escribe en el campo de texto en vez de sustituirlo. El
 * pegado sigue entrando por el campo de texto, que es el que manda.
 *
 * Lo que se escriba se normaliza igual que en el servidor (`fechaIso` del
 * PublishActivityRequest acepta dd/mm/aaaa, con guiones, y también ISO), así
 * que ninguna de las dos puntas se vuelve más estricta que la otra.
 */
export const campoFecha = () => ({
    entrada: null,
    calendario: null,

    init() {
        this.entrada = this.$refs.fecha ?? null;
        this.calendario = this.$refs.calendario ?? null;
    },

    /**
     * Mientras se escribe: las barras se ponen solas.
     *
     * Borrar no se toca —reformatear mientras alguien borra le mueve el cursor
     * y no hay forma de corregir nada—, y pegar va por el camino de la
     * normalización, que entiende más formatos que la máscara.
     */
    alEscribir(evento) {
        const tipo = evento?.inputType ?? '';

        if (tipo.startsWith('delete')) return;

        if (tipo === 'insertFromPaste' || tipo === 'insertFromDrop') {
            this.normalizar();

            return;
        }

        const digitos = this.entrada.value.replace(/\D/g, '').slice(0, 8);

        this.entrada.value = this.conBarras(digitos);
    },

    conBarras(digitos) {
        let salida = digitos.slice(0, 2);

        if (digitos.length > 2) salida += ' / ' + digitos.slice(2, 4);
        if (digitos.length > 4) salida += ' / ' + digitos.slice(4, 8);

        return salida;
    },

    /**
     * Al salir del campo o al pegar: se deja en dd / mm / aaaa.
     *
     * Se prueban las dos lecturas posibles —día primero y año primero— y se
     * elige la que da una fecha que existe. Eso resuelve dos cosas: lo que
     * se pega en ISO desde otro sitio, y lo que se teclea en ISO a mano,
     * que la máscara habría dejado en «20 / 26 / 1204» —y 26 no es un mes—.
     *
     * Acepta los dígitos sueltos igual que los acepta el servidor: `4-12-2026`
     * son tres grupos separados, no ocho dígitos. Contarlos era lo que dejaba
     * el campo más estricto que la regla que lo valida, y eso no puede pasar:
     * `fechaIso` del PublishActivityRequest parte por `\D+`, sin contar nada.
     */
    normalizar() {
        const partes = this.entrada.value.split(/\D+/).filter(Boolean);
        const seguidos = partes.join('');

        const lecturas = [];

        // Partido en tres: 04/12/2026 y 4-12-2026, o al revés 2026-12-04.
        if (partes.length === 3) {
            lecturas.push(partes, [partes[2], partes[1], partes[0]]);
        }

        /*
         * Y por los ocho dígitos seguidos, que cubre dos casos: lo tecleado,
         * que la máscara deja sin separadores propios, y —esto es lo que se
         * escapaba— lo que la máscara partió MAL. Quien teclea 20261204 a mano
         * ve «20 / 26 / 1204», tres grupos que por separado no son ninguna
         * fecha; juntos sí lo son.
         */
        if (seguidos.length === 8) {
            lecturas.push(
                [seguidos.slice(0, 2), seguidos.slice(2, 4), seguidos.slice(4, 8)],
                [seguidos.slice(6, 8), seguidos.slice(4, 6), seguidos.slice(0, 4)],
            );
        }

        const elegida = lecturas.find((l) => this.esPosible(l));

        // Si no se entiende, se deja tal cual y lo dice el servidor. Mejor
        // eso que inventarse una fecha encima de lo que la persona escribió.
        if (! elegida) return;

        const [dia, mes, anio] = elegida;

        this.entrada.value = String(dia).padStart(2, '0')
            + ' / ' + String(mes).padStart(2, '0')
            + ' / ' + anio;
    },

    esPosible([dia, mes, anio]) {
        const d = Number(dia);
        const m = Number(mes);
        const a = Number(anio);

        return String(anio).length === 4
            && d >= 1 && d <= 31
            && m >= 1 && m <= 12
            && a >= 1900 && a <= 2999;
    },

    /** El calendario escribe en el campo de texto; no lo sustituye. */
    desdeCalendario() {
        const iso = this.calendario?.value;

        if (! iso) return;

        const [anio, mes, dia] = iso.split('-');

        this.entrada.value = dia + ' / ' + mes + ' / ' + anio;
        this.entrada.dispatchEvent(new Event('input', { bubbles: true }));
    },

    /** Y al revés: el calendario se abre por donde ya diga el campo. */
    sincronizarCalendario() {
        if (! this.calendario) return;

        const digitos = this.entrada.value.replace(/\D/g, '');

        if (digitos.length !== 8) return;

        const dia = digitos.slice(0, 2);
        const mes = digitos.slice(2, 4);
        const anio = digitos.slice(4, 8);

        this.calendario.value = anio + '-' + mes + '-' + dia;
    },
});

/* ══════════════════════════════════════════════════ campo de hora ══ */

/**
 * El campo de hora, con el mismo trato que el de fecha.
 *
 * Se quedó en texto libre con un `placeholder="HH:MM"` cuando la fecha ya tenía
 * máscara y calendario, y el cliente lo pidió el 2026-09-01. Aquí el formato no
 * es ambiguo —nadie duda de si 09:30 son las nueve y media— pero el placeholder
 * desaparece en cuanto se escribe la primera cifra, y sin selector hay que
 * teclear los dos puntos a mano.
 *
 * Sigue siendo `type="text"` por lo mismo que la fecha: los campos nativos de
 * hora no dejan pegar. El reloj va al lado, en un `input[type=time]`
 * transparente debajo del botón, y ESCRIBE en el campo de texto.
 *
 * Se normaliza igual que en el servidor, y un poco más generoso: `horaIso` de
 * los dos Form Requests exige un separador, así que «0930» a secas se le
 * atragantaba. Aquí se convierte antes de enviarlo, y de paso se le enseñó al
 * servidor a entenderlo por su cuenta para quien navegue sin JavaScript.
 */
export const campoHora = () => ({
    entrada: null,
    reloj: null,

    init() {
        this.entrada = this.$refs.hora ?? null;
        this.reloj = this.$refs.reloj ?? null;
    },

    /**
     * Al escribir: los dos puntos se ponen solos tras la hora.
     *
     * Borrar no se toca, por lo mismo que en la fecha: reformatear mientras
     * alguien borra le mueve el cursor y no hay forma de corregir nada.
     */
    alEscribir(evento) {
        const tipo = evento?.inputType ?? '';

        if (tipo.startsWith('delete')) return;

        if (tipo === 'insertFromPaste' || tipo === 'insertFromDrop') {
            this.normalizar();

            return;
        }

        const digitos = this.entrada.value.replace(/\D/g, '').slice(0, 4);

        this.entrada.value = digitos.length > 2
            ? digitos.slice(0, 2) + ':' + digitos.slice(2)
            : digitos;
    },

    /**
     * Al salir del campo o al pegar: se deja en HH:MM.
     *
     * Entiende lo que la gente escribe de verdad: «9» son las nueve en punto,
     * «930» y «0930» las nueve y media, y «9.30» o «9:30» lo mismo. Una hora
     * imposible se deja tal cual: la explica el servidor, que para eso tiene el
     * mensaje escrito, y no se inventa una encima de lo que puso la persona.
     */
    normalizar() {
        const texto = this.entrada.value.trim();

        if (texto === '') return;

        /*
         * Las mismas dos lecturas, y en el mismo orden, que `FechaEscrita::hora`
         * en el servidor. Primero la que lleva separador, que es la que además
         * recorta los segundos de un «13:45:00» pegado desde otro sitio; si se
         * mirasen sólo los dígitos, ese daría 134500 y no se entendería.
         */
        const conSeparador = texto.match(/^(\d{1,2})\D(\d{2})/);

        /*
         * Si no hay separador completo, valen las cifras a secas. Y aquí sí se
         * miran las cifras SUELTAS, no la cadena entera, porque la máscara deja
         * estados a medias: quien teclea «930» ve «93:0» según lo escribe, y eso
         * no es ni una cosa ni la otra. Sus tres cifras sí lo son.
         */
        const digitos = texto.replace(/\D/g, '');

        let hora, minuto;

        if (conSeparador) {
            [, hora, minuto] = conSeparador;
        } else if (digitos.length >= 1 && digitos.length <= 4) {
            // Con una o dos cifras es la hora en punto; con tres o cuatro, las
            // dos últimas son los minutos.
            [hora, minuto] = digitos.length <= 2
                ? [digitos, '00']
                : [digitos.slice(0, -2), digitos.slice(-2)];
        } else {
            return;
        }

        const h = Number(hora);
        const m = Number(minuto);

        // Una hora imposible se deja como se escribió: la explica el servidor,
        // que para eso tiene el mensaje puesto, y no se inventa otra encima.
        if (! (h >= 0 && h <= 23 && m >= 0 && m <= 59)) return;

        this.entrada.value = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    },

    /** El reloj escribe en el campo de texto; no lo sustituye. */
    desdeReloj() {
        const valor = this.reloj?.value;

        if (! valor) return;

        this.entrada.value = valor.slice(0, 5);
        this.entrada.dispatchEvent(new Event('input', { bubbles: true }));
    },

    /** Y al revés: el reloj se abre por donde ya diga el campo. */
    sincronizarReloj() {
        if (! this.reloj) return;

        this.normalizar();

        if (/^\d{2}:\d{2}$/.test(this.entrada.value)) {
            this.reloj.value = this.entrada.value;
        }
    },
});
