// Tanda del 05/10, punto 2 — las sugerencias de dirección en el teléfono.
//
// El desplegable reutilizaba las piezas del buscador de organizaciones, cuya
// columna derecha no se encoge ni se parte («Ya tiene cuenta» partido se lee
// como parte del nombre). Con una región larga esa columna se quedaba con todo
// el ancho y el nombre salía letra por letra, en vertical.
//
// Escribe una dirección de verdad (pasa por Photon), y mide en cada sugerencia:
// que el nombre ocupe UNA línea, que la región vaya al lado o debajo pero
// nunca encima, y que nada se salga de la lista. A 390 y 360 px, en el wizard y
// en el editor de mi-cuenta; y a 1440 px que siga al lado. Y que el buscador de
// organizaciones no haya cambiado: su estado sigue a la derecha y sin partir.
//
//   node pruebas/direcciones-movil.mjs
//
// Sólo lectura: no envía ningún formulario.
import puppeteer from 'puppeteer-core';
import { ORG, CLAVE_ORG } from './credenciales.mjs';

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

/** Lo que se ve de cada sugerencia abierta. */
const medir = () => p.evaluate(() => {
  const lista = [...document.querySelectorAll('ul.org-sugerencias')].find((u) => u.offsetParent && u.getBoundingClientRect().height > 0);
  if (! lista) return null;
  const caja = lista.getBoundingClientRect();
  const lineas = (el) => {
    const r = document.createRange();
    r.selectNodeContents(el);
    return new Set([...r.getClientRects()].map((x) => Math.round(x.top))).size;
  };

  return [...lista.querySelectorAll('.org-sugerencia')].map((b) => {
    const n = b.querySelector('.org-sugerencia-nombre').getBoundingClientRect();
    const c = b.querySelector('.org-sugerencia-estado').getBoundingClientRect();
    const btn = b.getBoundingClientRect();

    return {
      nombre: b.querySelector('.org-sugerencia-nombre').textContent,
      ciudad: b.querySelector('.org-sugerencia-estado').textContent,
      lineasNombre: lineas(b.querySelector('.org-sugerencia-nombre')),
      anchoNombre: Math.round(n.width),
      alLado: Math.abs(c.top - n.top) < 4,
      debajo: c.top >= n.bottom - 1,
      montados: ! (c.right <= n.left || c.left >= n.right || c.bottom <= n.top || c.top >= n.bottom),
      dentro: btn.left >= caja.left - 1 && btn.right <= caja.right + 1 && c.right <= btn.right + 1 && n.right <= btn.right + 1,
    };
  });
});

const escribirDireccion = async (texto) => {
  await p.$eval('input[name="direccion"]', (i) => { i.value = ''; i.focus(); });
  await p.type('input[name="direccion"]', texto, { delay: 30 });
  await p.waitForFunction(() => [...document.querySelectorAll('ul.org-sugerencias .org-sugerencia')].some((b) => b.offsetParent), { timeout: 15000 })
    .catch(() => null);
  await esperar(300);
};

const revisar = async (donde, ancho) => {
  const s = await medir();
  di(`${donde} a ${ancho}: se abre la lista`, !! s && s.length > 0, s ? `${s.length} sugerencia(s)` : '');
  if (! s || ! s.length) return;

  const rotas = s.filter((x) => x.lineasNombre > 1);
  di(`${donde} a ${ancho}: **cada nombre en una sola línea**`, rotas.length === 0,
    rotas.map((x) => `«${x.nombre}» en ${x.lineasNombre} líneas, ${x.anchoNombre}px`).join(' | ') || s.map((x) => x.anchoNombre + 'px').join(' '));
  di(`${donde} a ${ancho}: la región al lado o debajo, nunca encima`, s.every((x) => (x.alLado || x.debajo) && ! x.montados));
  di(`${donde} a ${ancho}: nada se sale de la lista`, s.every((x) => x.dentro));

  return s;
};

try {
  await p.setViewport({ width: 1440, height: 1000 });
  await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', ORG);
  await p.type('input[type="password"]', CLAVE_ORG);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  for (const ancho of [390, 360, 1440]) {
    t(`El wizard a ${ancho} px`);
    await p.setViewport({ width: ancho, height: 900, isMobile: ancho < 760, hasTouch: ancho < 760 });
    await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
    await p.waitForFunction(() => window.Alpine !== undefined);
    await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => b.innerText.includes('No, solo quiero difundir')).click());
    await esperar(400);
    await escribirDireccion('Ñuñoa');
    const s = await revisar('wizard', ancho);

    if (ancho === 1440 && s) {
      di('en escritorio, la región sigue al lado del nombre', s.every((x) => x.alLado));
    }
  }

  t('El editor de mi-cuenta a 390 px');
  await p.setViewport({ width: 390, height: 900, isMobile: true, hasTouch: true });
  await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
  const editar = await p.evaluate(() => [...document.querySelectorAll('a')].map((a) => a.href).find((h) => /\/mi-cuenta\/actividades\/\d+\/editar$/.test(h)));
  await p.goto(editar, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  const antes = await p.$eval('input[name="direccion"]', (i) => i.value);
  await escribirDireccion('Ñuñoa');
  await revisar('mi-cuenta', 390);
  await p.$eval('input[name="direccion"]', (i, v) => { i.value = v; }, antes);

  t('El buscador de organizaciones no cambió');
  /*
   * Mismas piezas, otro uso: su columna derecha («Ya tiene cuenta», «Libre»)
   * tiene que seguir a la derecha y en una línea. Se mira en el registro,
   * que es la pantalla pública que lo usa sin sesión.
   */
  const anon = await nav.createBrowserContext();
  const q = await anon.newPage();
  await q.setViewport({ width: 390, height: 900, isMobile: true, hasTouch: true });
  await q.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });
  const campo = await q.$('input[name="org_nombre"], input[name="nombre_organizacion"], [x-data] input[autocomplete="organization"]');
  if (campo) {
    await campo.type('Fundación', { delay: 30 });
    await q.waitForFunction(() => [...document.querySelectorAll('.org-sugerencia')].some((b) => b.offsetParent), { timeout: 10000 }).catch(() => null);
    await esperar(300);
    const org = await q.evaluate(() => [...document.querySelectorAll('.org-sugerencia')].filter((b) => b.offsetParent).map((b) => {
      const n = b.querySelector('.org-sugerencia-nombre').getBoundingClientRect();
      const e = b.querySelector('.org-sugerencia-estado');
      const r = document.createRange();
      r.selectNodeContents(e);
      return { alLado: Math.abs(e.getBoundingClientRect().top - n.top) < 4, lineas: new Set([...r.getClientRects()].map((x) => Math.round(x.top))).size,
        estilo: getComputedStyle(e).whiteSpace + '/' + getComputedStyle(e).flexShrink };
    }));
    di('hay sugerencias de organización', org.length > 0, `${org.length}`);
    di('su estado sigue en una línea y sin encogerse', org.length > 0 && org.every((x) => x.lineas === 1 && x.estilo === 'nowrap/0'), org[0]?.estilo);
    di('y al lado del nombre', org.length > 0 && org.every((x) => x.alLado));
  } else {
    di('el campo del buscador de organizaciones existe en el registro', false);
  }
  await anon.close();

  t('Consola');
  di('sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  await nav.close();
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
