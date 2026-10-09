/*
 * Dos comodidades de campo, de la tanda del 11/09 (puntos 29 y 31).
 *
 * Las dos son MEJORA PROGRESIVA: si este archivo no llega a cargar, los campos
 * siguen siendo los de siempre y el formulario se envía igual. Ninguna de las
 * dos valida nada — lo que decide sigue siendo el servidor.
 *
 * Van aquí y no en `formularios.js` porque aquél es la guía de errores, que ya
 * es larga, y esto no tiene nada que ver con ella.
 */

/* ── Punto 31: el «https://» que el usuario no tiene por qué escribir ──
 *
 * Los campos de sitio web y red social son `type="url"`, así que «www.mi.cl»
 * los da por inválidos y el navegador corta el envío con su propio aviso, que
 * además sale en el idioma del navegador y no dice qué falta. Peor todavía
 * cuando pasaba la validación: quedaba un enlace guardado sin protocolo, y un
 * `href` sin protocolo lo resuelve el navegador como una ruta RELATIVA —
 * «www.mi.cl» acaba apuntando al propio dominio: /www.mi.cl.
 *
 * Se completa al salir del campo y no mientras se escribe: hacerlo en cada
 * pulsación mueve el cursor y pelea con quien está pegando una dirección.
 */
function completarProtocolo(campo) {
    const valor = campo.value.trim();

    if (! valor) return;

    // Ya trae esquema (http://, https://, mailto:…): no se toca.
    if (/^[a-z][a-z0-9+.-]*:/i.test(valor)) return;

    // Tampoco se toca lo que no parece un dominio todavía: alguien a medio
    // escribir no debe encontrarse un «https://» pegado delante.
    if (! /^[^\s/]+\.[^\s/]{2,}/.test(valor)) return;

    campo.value = 'https://' + valor;
    campo.dispatchEvent(new Event('input', { bubbles: true }));
}

/* ── Punto 29: ver lo que se escribió en una contraseña ──
 *
 * El botón se inyecta desde aquí y no se escribe en cada vista porque son ocho
 * campos repartidos en seis pantallas.
 *
 * Tres detalles que no son de estilo:
 *
 * - `type="button"`. Sin eso, dentro de un formulario es un botón de ENVIAR y
 *   pulsar «ver» manda el formulario a medio rellenar.
 * - El foco y la posición del cursor se restauran a mano: cambiar el `type` de
 *   un input lo reinicia en todos los navegadores.
 * - `aria-pressed` y el rótulo cambian con el estado; un icono mudo no le dice
 *   nada a quien usa lector de pantalla.
 */
const OJO = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
const OJO_TACHADO = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.7 6.2A9.9 9.9 0 0 1 12 6c6.5 0 10 7 10 7a15.6 15.6 0 0 1-3 3.8M6.2 6.7A15.7 15.7 0 0 0 2 13s3.5 7 10 7a9.8 9.8 0 0 0 4.3-1M3 3l18 18"/></svg>';

function ponerVisor(campo) {
    if (campo.dataset.visorPuesto) return;
    campo.dataset.visorPuesto = '1';

    /*
     * Envolver el campo lo MUEVE en el DOM, y mover un elemento con el foco se
     * lo quita. El aviso de «te equivocaste de puerta» deja el foco puesto en
     * la contraseña —que es justo el campo que falta— y esto se lo robaba, sin
     * ningún error: el cursor simplemente no estaba donde debía. Lo cazó
     * `login-puertas.mjs`.
     */
    const teniaElFoco = document.activeElement === campo;
    const cursor = teniaElFoco ? campo.selectionStart : null;

    const caja = document.createElement('span');
    caja.className = 'campo-con-visor';
    campo.parentNode.insertBefore(caja, campo);
    caja.appendChild(campo);

    if (teniaElFoco) {
        campo.focus();
        try { campo.setSelectionRange(cursor, cursor); } catch { /* no todos lo admiten */ }
    }

    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'campo-visor';
    boton.innerHTML = OJO;
    boton.setAttribute('aria-label', 'Mostrar la contraseña');
    boton.setAttribute('aria-pressed', 'false');
    boton.title = 'Mostrar la contraseña';
    caja.appendChild(boton);

    boton.addEventListener('click', () => {
        const mostrando = campo.type === 'text';
        const cursor = campo.selectionStart;

        campo.type = mostrando ? 'password' : 'text';
        boton.innerHTML = mostrando ? OJO : OJO_TACHADO;

        const rotulo = mostrando ? 'Mostrar la contraseña' : 'Ocultar la contraseña';
        boton.setAttribute('aria-label', rotulo);
        boton.setAttribute('aria-pressed', mostrando ? 'false' : 'true');
        boton.title = rotulo;

        // Cambiar el `type` pierde el foco y el cursor: se devuelven.
        campo.focus();
        try { campo.setSelectionRange(cursor, cursor); } catch { /* no todos lo admiten */ }
    });
}

/* ── El aviso del servidor se va cuando el campo queda bien (09/10) ──
 *
 * Los avisos en rojo que pinta el servidor (`@error(...)`) eran texto fijo: se
 * corregía la contraseña de siete letras, se escribía una buena, y el «debe
 * tener al menos 8 caracteres» seguía ahí hasta enviar. Es el mismo fallo que
 * la guía de errores vino a arreglar: lo que ya está bien no puede seguir en
 * rojo, porque entonces no se sabe qué queda.
 *
 * Qué es «queda bien» sin una segunda lista de reglas:
 *
 * - lo que el navegador sabe comprobar (`type="email"`, `required`,
 *   `minlength`), con `checkValidity()`;
 * - la contraseña nueva, al menos 8 caracteres —la regla `min:8` de todas las
 *   pantallas— y, si el campo de confirmación ya está escrito, que coincidan;
 * - `data-minimo` en el campo, para otro mínimo del servidor;
 * - y para lo que sólo sabe el servidor (`unique`, `exists`), que el valor sea
 *   otro que el rechazado. Si se vuelve a escribir el mismo, el aviso vuelve.
 *
 * Los avisos que ya maneja Alpine (`x-show`, `x-text`) no se tocan: ésos van y
 * vienen solos.
 */
const MINIMO_CLAVE = 8;

function controlDelAviso(aviso) {
    const visibles = 'input[name]:not([type="hidden"]):not([name$="_confirmation"]), select[name], textarea[name]';

    for (let caja = aviso.parentElement, nivel = 0; caja && nivel < 2; caja = caja.parentElement, nivel++) {
        const controles = [...caja.querySelectorAll(visibles)];

        if (controles.length) {
            return { caja, control: controles.find((c) => c.classList.contains('is-invalid')) ?? controles[0] };
        }

        // Un grupo de chips: no hay control a la vista, el valor va en hidden.
        if (caja.querySelector('template, input[type="hidden"][name$="[]"]')) return { caja, control: null };
    }

    return null;
}

function valorDe(control) {
    return control.type === 'checkbox' || control.type === 'radio' ? String(control.checked) : control.value.trim();
}

function valoresOcultos(caja) {
    return [...caja.querySelectorAll('input[type="hidden"][name]')].map((c) => c.value).sort().join(',');
}

function seguirAviso(aviso) {
    if (aviso.dataset.avisoSeguido || aviso.hasAttribute('x-show') || aviso.hasAttribute('x-text')) return;

    const enlace = controlDelAviso(aviso);

    if (! enlace) return;

    aviso.dataset.avisoSeguido = '1';

    const { caja, control } = enlace;

    if (! control) {
        // Chips: bien en cuanto hay alguno marcado y la selección cambió.
        // Alpine repinta los hidden después del clic, de ahí la espera.
        const rechazado = valoresOcultos(caja);

        caja.addEventListener('click', () => setTimeout(() => {
            const ahora = valoresOcultos(caja);

            aviso.hidden = ahora !== '' && ahora !== rechazado;
        }, 30));

        return;
    }

    const rechazado = valorDe(control);
    const confirmacion = control.form?.querySelector(`[name="${CSS.escape(control.name + '_confirmation')}"]`) ?? null;
    // Se mira ahora: el visor de contraseña le cambia el `type` a «text».
    const esClave = control.type === 'password' && control.autocomplete !== 'current-password';
    const minimo = Number(control.dataset.minimo || (esClave ? MINIMO_CLAVE : 0));

    const quedaBien = () => {
        const valor = valorDe(control);

        if (control.disabled) return true;
        if (valor === '' || valor === 'false') return false;
        if (control.checkValidity && ! control.checkValidity()) return false;
        if (minimo && control.value.length < minimo) return false;
        if (confirmacion && confirmacion.value !== '' && confirmacion.value !== control.value) return false;

        // La contraseña rechazada no vuelve en el formulario: lo escrito ya es otro.
        return esClave || valor !== rechazado;
    };

    const repasar = () => {
        const bien = quedaBien();

        aviso.hidden = bien;
        control.classList.toggle('is-invalid', ! bien);
        if (bien) control.removeAttribute('aria-invalid');
    };

    ['input', 'change'].forEach((evento) => {
        control.addEventListener(evento, repasar);
        confirmacion?.addEventListener(evento, repasar);
    });
}

export function montarCampos(raiz = document) {
    raiz.querySelectorAll('.field-error').forEach(seguirAviso);

    raiz.querySelectorAll('input[data-autoprotocolo]').forEach((campo) => {
        if (campo.dataset.protocoloPuesto) return;
        campo.dataset.protocoloPuesto = '1';
        campo.addEventListener('blur', () => completarProtocolo(campo));
    });

    // Los de SMTP no llevan visor: esa clave la escribe un administrador en una
    // pantalla de configuración y mostrarla no ayuda a nadie.
    raiz.querySelectorAll('input[type="password"]:not([data-sin-visor])').forEach(ponerVisor);
}

/* ── Placeholders cortos en el teléfono (04/10) ──
 *
 * Un placeholder más largo que su caja sale cortado a media palabra, y en un
 * campo no hay forma de partirlo en dos líneas. Donde el del escritorio no
 * cabe a 390 px, la vista pone en `data-placeholder-movil` una versión corta
 * que dice lo mismo, y aquí se cambia uno por otro según el ancho.
 */
const angosta = window.matchMedia('(max-width: 560px)');

function ajustarPlaceholders() {
    document.querySelectorAll('[data-placeholder-movil]').forEach((campo) => {
        campo.dataset.placeholderAncho ??= campo.placeholder;
        campo.placeholder = angosta.matches ? campo.dataset.placeholderMovil : campo.dataset.placeholderAncho;
    });
}

angosta.addEventListener?.('change', ajustarPlaceholders);

document.addEventListener('DOMContentLoaded', () => {
    montarCampos();
    ajustarPlaceholders();
});
