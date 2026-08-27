import Alpine from 'alpinejs';
import { buscadorPanel, editorRico, editorSeccion, ordenSecciones } from './home-editor';

/*
 * Barra de pasos del wizard (publicar-actividad.html).
 *
 * Van en window y no dentro del componente porque las usan dos vistas: el
 * wizard, donde `paso` es estado de Alpine, y la pantalla de envío, donde
 * el paso es fijo en 5.
 *
 * Devuelven el style COMPLETO a propósito. Alpine, cuando el valor de
 * :style es un string, reemplaza el atributo entero en vez de fusionarlo,
 * así que mezclar style estático con x-bind:style borraba el estático: era
 * justo lo que dejaba los círculos convertidos en números sueltos.
 */

window.estiloPaso = (paso, n, navegable = true) => {
    const color = paso === n
        ? 'var(--naranjo-600)'
        : (paso > n ? 'var(--gris-700)' : '#b7babe');

    const clicable = navegable && n !== 5;

    return 'color:' + color + ';cursor:' + (clicable ? 'pointer' : 'default') + ';';
};

window.estiloCirculoPaso = (paso, n) => {
    const base = 'display:grid;place-items:center;width:28px;height:28px;'
        + 'border-radius:999px;font-size:12.5px;font-weight:800;';

    if (paso === n) {
        return base + 'background:var(--naranjo);color:#fff;border:1.5px solid var(--naranjo);';
    }

    if (paso > n) {
        return base + 'background:var(--naranjo-100);color:var(--naranjo-600);border:1.5px solid var(--naranjo);';
    }

    return base + 'background:#fff;color:#b7babe;border:1.5px solid #e6e8ea;';
};

/*
 * Secciones colapsables del menú del panel.
 *
 * El estado se guarda en localStorage para que el menú siga como lo dejaste al
 * cambiar de pantalla: con el árbol entero desplegado hay que hacer scroll para
 * llegar a lo de abajo, y eso cansa a la tercera vez.
 *
 * La sección que contiene la pantalla actual se abre siempre, aunque estuviera
 * cerrada: si no, al entrar por un enlace directo no se vería dónde estás.
 */
const CLAVE_MENU = 'dps.panel.menu';

const leerMenu = () => {
    try {
        return JSON.parse(localStorage.getItem(CLAVE_MENU)) || {};
    } catch {
        // Modo privado, almacenamiento bloqueado o un valor corrupto: se sigue
        // sin memoria, que es peor experiencia pero no un error.
        return {};
    }
};

const guardarMenu = (estado) => {
    try {
        localStorage.setItem(CLAVE_MENU, JSON.stringify(estado));
    } catch {
        /* sin memoria, pero el menú sigue funcionando */
    }
};

Alpine.data('seccionMenu', (clave, contieneLaPantallaActual) => ({
    abierta: contieneLaPantallaActual || leerMenu()[clave] === true,

    alternar() {
        this.abierta = !this.abierta;

        const estado = leerMenu();
        estado[clave] = this.abierta;
        guardarMenu(estado);
    },
}));

// El editor de contenido del home (bloque F). Vive en su propio archivo: son
// doscientas líneas que sólo usa el panel, y aquí sólo hace falta el registro.
Alpine.data('editorRico', editorRico);
Alpine.data('editorSeccion', editorSeccion);
Alpine.data('ordenSecciones', ordenSecciones);
Alpine.data('buscadorPanel', buscadorPanel);

window.Alpine = Alpine;
Alpine.start();

/*
 * Interacciones rescatadas del componentDidMount de index.html.
 * En el prototipo esto corría dentro de React; acá es JS plano.
 */

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ── Carruseles ───────────────────────────────────────────────

const trackOf = (key) => document.querySelector('.carousel[data-carousel="' + key + '"]');

const stepOf = (track) => {
    const first = track.firstElementChild;
    const gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap) || 0;
    return first ? first.getBoundingClientRect().width + gap : track.clientWidth;
};

const maxOf = (track) => track.scrollWidth - track.clientWidth - 2;

const animateTo = (track, target) => {
    if (reduce) {
        track.scrollLeft = target;
        return;
    }

    if (track.__tw) cancelAnimationFrame(track.__tw);

    const start = track.scrollLeft;
    const delta = target - start;
    const dur = 420;
    const t0 = performance.now();
    const ease = (t) => 1 - Math.pow(1 - t, 3);

    const tick = (now) => {
        const p = Math.min((now - t0) / dur, 1);
        track.scrollLeft = start + delta * ease(p);
        if (p < 1) {
            track.__tw = requestAnimationFrame(tick);
        } else {
            track.__tw = null;
        }
    };

    track.__tw = requestAnimationFrame(tick);
};

const syncDots = (key) => {
    const track = trackOf(key);
    const wrap = document.querySelector('[data-carousel-dots="' + key + '"]');
    if (!track || !wrap) return;

    const n = track.children.length;
    const active = track.scrollLeft >= maxOf(track)
        ? n - 1
        : Math.min(Math.round(track.scrollLeft / stepOf(track)), n - 1);

    if (wrap.children.length !== n) {
        wrap.innerHTML = '';
        for (let i = 0; i < n; i++) {
            const d = document.createElement('button');
            d.type = 'button';
            d.className = 'dot';
            d.dataset.carouselDot = key;
            d.dataset.idx = i;
            d.setAttribute('aria-label', 'Ir al elemento ' + (i + 1));
            wrap.appendChild(d);
        }
    }

    Array.from(wrap.children).forEach((d, i) => d.classList.toggle('on', i === active));
};

const go = (key, dir) => {
    const track = trackOf(key);
    if (!track) return;

    const s = stepOf(track);
    const max = maxOf(track);
    let target = track.scrollLeft + dir * s;

    if (dir > 0 && track.scrollLeft >= max) {
        target = 0;
    } else if (dir < 0 && track.scrollLeft <= 2) {
        target = max;
    }

    animateTo(track, Math.max(0, Math.min(target, max)));
    setTimeout(() => syncDots(key), 460);
};

document.addEventListener('click', (e) => {
    const nx = e.target.closest('[data-carousel-next]');
    const pv = e.target.closest('[data-carousel-prev]');
    const dot = e.target.closest('[data-carousel-dot]');

    if (nx) {
        go(nx.getAttribute('data-carousel-next'), 1);
    } else if (pv) {
        go(pv.getAttribute('data-carousel-prev'), -1);
    } else if (dot) {
        const key = dot.getAttribute('data-carousel-dot');
        const track = trackOf(key);
        const it = track && track.children[Number(dot.dataset.idx)];
        if (it) animateTo(track, it.offsetLeft - track.offsetLeft);
    }
});

/*
 * Deslizar un carrusel dispara decenas de eventos de scroll por segundo, y
 * syncDots lee scrollWidth, clientWidth y getBoundingClientRect: son lecturas
 * que fuerzan al navegador a recalcular la maquetación. Se agrupan en un solo
 * frame para no pagarlo en cada evento.
 */
let dotsPendientes = null;

document.addEventListener('scroll', (e) => {
    const t = e.target;
    if (!t || !t.classList || !t.classList.contains('carousel')) return;

    const key = t.getAttribute('data-carousel');
    if (dotsPendientes) return;

    dotsPendientes = requestAnimationFrame(() => {
        dotsPendientes = null;
        syncDots(key);
    });
}, true);

// ── Video diferido: el iframe no se carga hasta el click ─────

const playVideo = (host) => {
    const id = host.getAttribute('data-video');
    const src = 'https://www.youtube.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';

    const frame = document.createElement('iframe');
    frame.src = src;
    frame.title = 'Video';
    frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    frame.allowFullscreen = true;
    frame.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0;';

    host.innerHTML = '';
    host.appendChild(frame);
};

document.addEventListener('click', (e) => {
    const v = e.target.closest('[data-video]');
    if (v && v.querySelector('img')) playVideo(v);
});

document.addEventListener('keydown', (e) => {
    const t = e.target;
    if ((e.key === 'Enter' || e.key === ' ')
        && t.matches
        && t.matches('[data-video]')
        && t.querySelector('img')) {
        e.preventDefault();
        playVideo(t);
    }
});

// ── Reveal on scroll ─────────────────────────────────────────
// El prototipo hacía polling con setInterval cada 180 ms. Acá
// usamos IntersectionObserver, que no consume CPU en reposo.

const initReveal = () => {
    const revEls = Array.from(document.querySelectorAll('.reveal'));

    if (reduce || !('IntersectionObserver' in window)) {
        revEls.forEach((el) => el.classList.add('in'));
        return;
    }

    const io = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
            if (en.isIntersecting) {
                en.target.classList.add('in');
                io.unobserve(en.target);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px' });

    revEls.forEach((el) => io.observe(el));
};

// ── Contadores animados ──────────────────────────────────────

const initCounters = () => {
    const fmt = (n, miles) => (miles ? n.toLocaleString('es-CL') : String(n));

    const counters = Array.from(document.querySelectorAll('.count')).map((el) => {
        const raw = (el.getAttribute('data-raw') || el.textContent).trim();
        el.setAttribute('data-raw', raw);

        const m = raw.match(/^([^\d]*)([\d.,]+)([^\d]*)$/);
        if (!m) return null;

        const miles = m[2].includes('.') || m[2].includes(',');
        const target = parseInt(m[2].replace(/[.,]/g, ''), 10);

        if (!reduce) el.textContent = m[1] + fmt(0, miles) + m[3];

        return { el: el, prefix: m[1], suffix: m[3], target: target, miles: miles };
    }).filter(Boolean);

    if (reduce || !('IntersectionObserver' in window)) {
        counters.forEach((c) => {
            c.el.textContent = c.prefix + fmt(c.target, c.miles) + c.suffix;
        });
        return;
    }

    const fin = (c) => {
        c.el.textContent = c.prefix + fmt(c.target, c.miles) + c.suffix;
    };

    const run = (c) => {
        if (c.corriendo) return;
        c.corriendo = true;

        const dur = 1500;
        const t0 = performance.now();

        /*
         * Red de seguridad: si la animación no llega a terminar —requestAnimationFrame
         * no corre en una pestaña de fondo, ni cuando el navegador la pausa— el
         * contador se quedaba en cero para siempre. Con esto acaba siempre en su
         * número, que es lo único que no puede fallar.
         */
        const red = setTimeout(() => fin(c), dur + 400);

        const tick = (now) => {
            /*
             * `p` se acota por ABAJO además de por arriba. El instante que recibe
             * requestAnimationFrame es el del comienzo del fotograma, y puede ser
             * anterior al `performance.now()` de dos líneas más arriba: con `p`
             * negativo, la cúbica `1-(1-p)^3` sale negativa y el contador enseñaba
             * números en rojo tipo «-11.631 de 100.000» durante un fotograma.
             * Lo pilló el testing en producción.
             */
            const p = Math.min(Math.max((now - t0) / dur, 0), 1);
            const val = Math.round((1 - Math.pow(1 - p, 3)) * c.target);

            c.el.textContent = c.prefix + fmt(val, c.miles) + c.suffix;

            if (p < 1) {
                requestAnimationFrame(tick);
            } else {
                clearTimeout(red);
            }
        };

        requestAnimationFrame(tick);
    };

    const io = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
            if (!en.isIntersecting) return;
            const c = counters.find((x) => x.el === en.target);
            if (c) {
                run(c);
                io.unobserve(en.target);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px' });

    counters.forEach((c) => io.observe(c.el));

    /*
     * Y un empujón para quien no llega bajando.
     *
     * El observador se dispara al entrar en pantalla, pero llegar por un ancla
     * —#ediciones desde el menú— o por un scroll programático dejaba los
     * contadores en cero: el elemento ya estaba dentro antes de que el
     * observador empezara a mirar, o el salto fue tan directo que no hubo
     * fotograma intermedio. Al cambiar el ancla se revisa quién está visible y
     * se arranca lo que falte.
     */
    const arrancarVisibles = () => {
        counters.forEach((c) => {
            if (c.corriendo) return;

            const r = c.el.getBoundingClientRect();
            if (r.top < window.innerHeight && r.bottom > 0) {
                run(c);
                io.unobserve(c.el);
            }
        });
    };

    window.addEventListener('hashchange', () => setTimeout(arrancarVisibles, 400));
    setTimeout(arrancarVisibles, 600);
};

// ── Igualar altura de las tarjetas de participación ──────────

const eqHeights = () => {
    const cards = Array.from(document.querySelectorAll('.part-card'));
    if (!cards.length) return;

    cards.forEach((c) => { c.style.height = 'auto'; });

    const rows = new Map();
    cards.forEach((c) => {
        const top = Math.round(c.offsetTop);
        if (!rows.has(top)) rows.set(top, []);
        rows.get(top).push(c);
    });

    rows.forEach((group) => {
        const max = Math.max.apply(null, group.map((c) => c.offsetHeight));
        group.forEach((c) => { c.style.height = max + 'px'; });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initReveal();
    initCounters();

    document.querySelectorAll('[data-carousel-dots]').forEach((w) => {
        syncDots(w.getAttribute('data-carousel-dots'));
    });

    eqHeights();
    setTimeout(eqHeights, 300);
});

window.addEventListener('resize', eqHeights);
