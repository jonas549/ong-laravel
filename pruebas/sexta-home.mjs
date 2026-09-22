// Sexta tanda, bloque C: home, pie y listado.
//
//   C1 · el botón «¿Cómo quieres participar hoy?» no recorta su texto.
//   C2 · el logo del pie conserva su proporción (el fallo era de Safari:
//        ver la nota de partials/public/footer.blade.php; aquí se mira la
//        estructura que lo evita y la proporción en Chrome).
//   C3 · YouTube del pie lleva al canal de la Comunidad.
//   C4 · el logo de la Comunidad enlaza a su sitio en cabecera y pies.
//   C5 · «Quiero ser voluntario» lleva a Voluntariados Chile, en otra pestaña.
//   C6 · en el listado, «Ver actividad» destaca y la imagen abre la ficha.
//
//   node pruebas/sexta-home.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const abrir = async (ruta, ancho = 1440) => {
  await p.setViewport({ width: ancho, height: 900, isMobile: ancho < 800, hasTouch: ancho < 800 });
  await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
};

/* ═══════════════ C1 ═══════════════════════════════════════════ */

t('C1 — «¿Cómo quieres participar hoy?» cabe entero');

for (const ancho of [1440, 768, 390, 360, 320]) {
  await abrir('/', ancho);
  const r = await p.$eval('.hero-cta', (e) => {
    const c = e.getBoundingClientRect();
    const cs = getComputedStyle(e);
    // Lo que sobra entre el texto y el borde, por cada lado.
    const rango = document.createRange();
    rango.selectNodeContents(e);
    const texto = rango.getBoundingClientRect();
    return {
      recorta: e.scrollWidth > e.clientWidth + 1,
      izquierda: texto.left - c.left, derecha: c.right - texto.right,
      ancho: c.width, lineas: Math.round(c.height / parseFloat(cs.lineHeight) - 0.5),
      dentro: c.left >= 0 && c.right <= innerWidth,
    };
  });
  di(`${ancho} px: el texto no se recorta`, ! r.recorta && r.dentro, `${Math.round(r.ancho)} px de botón`);
  di(`${ancho} px: y no toca el borde (≥ 20 px a cada lado)`, r.izquierda >= 20 && r.derecha >= 20,
    `${Math.round(r.izquierda)} / ${Math.round(r.derecha)} px`);
}

/* ═══════════════ C2 ═══════════════════════════════════════════ */

t('C2 — el logo del pie, con su proporción');

for (const ancho of [1440, 390]) {
  await abrir('/', ancho);
  const r = await p.evaluate(async () => {
    const i = [...document.querySelectorAll('img')].find((x) => x.src.includes('dia-del-patrimonio-footer'));
    i.scrollIntoView();
    i.loading = 'eager';
    await i.decode().catch(() => {});
    const c = i.getBoundingClientRect();
    return {
      real: c.width / c.height, nat: i.naturalWidth / i.naturalHeight, ancho: c.width,
      // Lo que lo evita en Safari: la imagen no es hija directa del flex.
      padreFlex: getComputedStyle(i.parentElement).display.includes('flex'),
    };
  });
  di(`${ancho} px: proporción igual que el archivo`, Math.abs(r.real / r.nat - 1) < 0.01, `${r.real.toFixed(3)} / ${r.nat.toFixed(3)}`);
  di(`${ancho} px: dentro de una caja de bloque, no suelta en el flex`, ! r.padreFlex);
}

/* ═══════════════ C3 y C4 ══════════════════════════════════════ */

t('C3 y C4 — los enlaces del pie y la cabecera');

await abrir('/');
const enlaces = await p.evaluate(() => ({
  youtube: document.querySelector('footer a[aria-label="YouTube"]')?.href,
  youtubeFuera: document.querySelector('footer a[aria-label="YouTube"]')?.target === '_blank',
  cabecera: document.querySelector('header a.nav-cos, a.nav-cos')?.href,
  pie: [...document.querySelectorAll('footer img')].find((i) => i.src.includes('logo-cos-color'))?.closest('a')?.href,
}));
di('C3: YouTube lleva al canal de la Comunidad', enlaces.youtube === 'https://www.youtube.com/@comunidaddeorganizacioness1361', enlaces.youtube);
di('C3: en otra pestaña', enlaces.youtubeFuera);
di('C4: el logo de la cabecera, a comunidad-org.cl', enlaces.cabecera === 'https://comunidad-org.cl/', enlaces.cabecera);
di('C4: el del pie, también', enlaces.pie === 'https://comunidad-org.cl/', enlaces.pie);

await abrir('/publicar-actividad');
const compacto = await p.evaluate(() =>
  [...document.querySelectorAll('footer img')].find((i) => i.src.includes('logo-cos-color'))?.closest('a')?.href);
di('C4: y el del pie compacto (wizard, mi-cuenta)', compacto === 'https://comunidad-org.cl/', compacto);

/* ═══════════════ C5 ═══════════════════════════════════════════ */

t('C5 — «Quiero ser voluntario»');

await abrir('/');
const tarjeta = await p.evaluate(() => {
  const a = [...document.querySelectorAll('a.part-card')].find((x) => x.innerText.includes('Quiero ser voluntario'));
  return a ? { href: a.href, target: a.target, rel: a.rel } : null;
});
di('La tarjeta está en el home', !! tarjeta);
di('**Lleva a voluntariadoschile.cl/oportunidades**', tarjeta?.href === 'https://voluntariadoschile.cl/oportunidades', tarjeta?.href);
di('En otra pestaña, con noopener', tarjeta?.target === '_blank' && tarjeta?.rel.includes('noopener'));
di('Las tarjetas que llevan dentro del sitio, en la misma', await p.evaluate(() =>
  [...document.querySelectorAll('a.part-card')].filter((x) => x.getAttribute('href').startsWith('/')).every((x) => x.target === '')));

/* ═══════════════ C6 ═══════════════════════════════════════════ */

t('C6 — el listado de actividades');

await abrir('/actividades');
const c6 = await p.evaluate(() => {
  const card = document.querySelector('.act-card');
  const boton = [...card.querySelectorAll('a')].find((a) => a.innerText.trim().startsWith('Ver actividad'));
  const imagen = card.querySelector('.act-img').closest('a');
  const cs = getComputedStyle(boton);
  return {
    fondo: cs.backgroundColor, color: cs.color, clase: boton.className,
    botonHref: boton.href, imagenHref: imagen?.href, tab: imagen?.tabIndex,
  };
});
di('«Ver actividad» con relleno naranja', c6.clase.includes('btn-primary') && c6.fondo !== 'rgba(0, 0, 0, 0)', `${c6.fondo} / ${c6.color}`);
di('**La imagen es un enlace a la ficha**', !! c6.imagenHref && c6.imagenHref === c6.botonHref, c6.imagenHref?.replace(B, ''));
di('Fuera del orden de tabulación (el enlace es el botón)', c6.tab === -1);

await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.click('.act-card .act-img'),
]);
di('**Pulsar la imagen abre la ficha**', /\/activity\/\d+\//.test(p.url()), p.url().replace(B, ''));

await abrir('/actividades', 390);
di('A 390 px, sin desborde horizontal', await p.evaluate(() => document.documentElement.scrollWidth <= 390));

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
