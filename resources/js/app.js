import Alpine from 'alpinejs';

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

document.addEventListener('scroll', (e) => {
    const t = e.target;
    if (t && t.classList && t.classList.contains('carousel')) {
        syncDots(t.getAttribute('data-carousel'));
    }
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

    const run = (c) => {
        const dur = 1500;
        const t0 = performance.now();
        const tick = (now) => {
            const p = Math.min((now - t0) / dur, 1);
            const val = Math.round((1 - Math.pow(1 - p, 3)) * c.target);
            c.el.textContent = c.prefix + fmt(val, c.miles) + c.suffix;
            if (p < 1) requestAnimationFrame(tick);
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
