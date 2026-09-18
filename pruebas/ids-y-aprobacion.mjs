// Puntos 36 (las ID a la vista) y 26 (el umbral de aprobación en el panel).
//
//   node pruebas/ids-y-aprobacion.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
await p.setViewport({ width: 1440, height: 900 });

const entrarComo = async (puerta, correo, clave) => {
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', correo);
  await p.type('input[name="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};

/* ═══════════════════════════ 36 — las ID, en el panel del admin ══════ */

t('Punto 36 — la ID de la actividad y la de la organización, en el admin');

await entrarComo('/admin/login', 'admin@ong-laravel.test', 'admin1234');

// La primera actividad del listado, sea cual sea.
await p.goto(`${B}/admin/actividades`, { waitUntil: 'networkidle2' });
const href = await p.$$eval('a[href*="/admin/actividades/"]',
  (as) => as.map((a) => a.getAttribute('href')).find((h) => /\/admin\/actividades\/\d+$/.test(h)));
di('Hay una actividad en el listado del admin', !! href, href ?? '(ninguna)');

// El listado da la URL absoluta, así que se usa tal cual.
await p.goto(href.startsWith('http') ? href : `${B}${href}`, { waitUntil: 'networkidle2' });
const idEsperada = href.match(/(\d+)$/)[1];

const fichaAdmin = await p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
di('La ficha enseña la ID de la actividad', fichaAdmin.includes(`ID de la actividad #`),
  (fichaAdmin.match(/ID de la actividad #\d+/) ?? ['(no está)'])[0]);
di('Y es la ID correcta, la de la URL',
  fichaAdmin.includes(`ID de la actividad #${idEsperada}`), `esperaba #${idEsperada}`);
di('Y enseña también la ID de la organización',
  /Organización .*· ID #\d+/.test(fichaAdmin), (fichaAdmin.match(/· ID #\d+/) ?? ['(no está)'])[0]);

/* ═══════════════════════════ 26 — el umbral, en Configuración ════════ */

t('Punto 26 — el umbral de aprobación es administrable');

await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });

di('El campo del umbral está en Configuración → General',
  (await p.$('[name="aprobacion_automatica_desde"]')) !== null);
di('Y es un número, no un sí/no',
  await p.$eval('[name="aprobacion_automatica_desde"]', (n) => n.type === 'number'),
  await p.$eval('[name="aprobacion_automatica_desde"]', (n) => n.type).catch(() => '(no está)'));
di('Vale 1, que es lo que ya corría en producción',
  (await p.$eval('[name="aprobacion_automatica_desde"]', (n) => n.value)) === '1',
  await p.$eval('[name="aprobacion_automatica_desde"]', (n) => n.value).catch(() => '?'));

// El interruptor general, que en producción no existía como fila.
di('Y el interruptor general sigue estando, ahora sí como fila de verdad',
  (await p.$('[name="aprobacion_automatica"]')) !== null);

/* ═══════════════════════════ 36 — la ID en el panel del organizador ══ */

t('Punto 36 — la ID en el panel del organizador');

// Cerrar sesión limpiando las cookies: `/salir` es POST y navegar a él da 404.
await p.browserContext().clearPermissionOverrides();
await (await p.createCDPSession()).send('Network.clearBrowserCookies');
await entrarComo('/mi-cuenta/login', 'organizador@ong-laravel.test', 'organizador1234');
await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });

const tarjetas = await p.$$eval('.actcard', (ns) => ns.map((n) => n.innerText.replace(/\s+/g, ' ')));
di('El organizador ve actividades', tarjetas.length > 0, `${tarjetas.length}`);
di('Cada tarjeta lleva su ID', tarjetas.length > 0 && tarjetas.every((x) => /#\d+/.test(x)),
  (tarjetas[0]?.match(/#\d+/) ?? ['(no está)'])[0]);

t('Sin errores de JavaScript');
di('La consola quedó limpia', errores.length === 0, errores.slice(0, 3).join(' · '));

console.log('');
console.log(`${ok} bien · ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
