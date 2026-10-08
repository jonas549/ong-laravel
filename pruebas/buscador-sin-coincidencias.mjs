// Punto 5 del 08/10 — el buscador de organizaciones con CERO coincidencias.
//
// Era el caso que se escapaba: con un nombre que no está en el listado la
// lista se cerraba y no aparecía nada, así que el tercer desenlace del
// buscador —«escribe un nombre nuevo y sigue»— no se veía. Las pruebas del
// buscador (`organizaciones-wizard.mjs`, `registro-organizacion.mjs`) sólo
// miraban los casos con coincidencias.
//
// Se mira en las dos pantallas que montan `<x-buscador-organizacion>`: el
// paso 3 del wizard sin sesión y `/mi-cuenta/registro`. Sólo lee: no publica
// ni crea nada, así que se puede correr contra producción.
//
//   node pruebas/buscador-sin-coincidencias.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));

// Un nombre que no puede estar en ningún listado.
const INEXISTENTE = `Zzqx Organización Inexistente ${Date.now()}`;
// Y uno que sí está: la organización sembrada de local (y su prefijo existe
// también en el listado de producción).
const EXISTENTE = process.env.DPS_ORG_EXISTENTE ?? 'Fundación Junto';

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

/*
 * «Se ve» es que ocupa sitio en pantalla: `x-show` lo apaga con
 * `display:none`, y entonces no tiene caja.
 */
const seVe = (sel) => p.evaluate((s) => {
  const n = document.querySelector(s);
  if (! n) return false;
  const r = n.getBoundingClientRect();
  return getComputedStyle(n).display !== 'none' && r.height > 0 && r.width > 0;
}, sel);

const escribir = async (texto) => {
  await p.$eval('input[name="org_nombre"]', (n) => { n.value = ''; n.dispatchEvent(new Event('input', { bubbles: true })); });
  await p.type('input[name="org_nombre"]', texto);
  // El retardo entre teclas (220 ms) más la respuesta.
  await esperar(1300);
};

const comprobar = async (pantalla) => {
  di(`${pantalla}: al llegar no dice nada`, ! await seVe('[data-org-sin-coincidencias]'));

  await escribir(INEXISTENTE);
  di(`${pantalla}: **un nombre que no existe enseña la salida**`, await seVe('[data-org-sin-coincidencias]'));
  di(`${pantalla}: que dice que se registrará como nueva`, await p.evaluate(() =>
    /organización nueva/i.test(document.querySelector('[data-org-sin-coincidencias]')?.textContent ?? '')));
  di(`${pantalla}: sin lista de sugerencias abierta`, ! await seVe('.org-sugerencias'));
  di(`${pantalla}: no se marca ninguna organización`, await p.$eval('input[name="org_id"]', (n) => n.value === ''));
  di(`${pantalla}: lo escrito se queda en el campo`, await p.$eval('input[name="org_nombre"]', (n, v) => n.value === v, INEXISTENTE));

  await escribir(EXISTENTE);
  di(`${pantalla}: con coincidencias, la lista y no la salida`,
    await seVe('.org-sugerencias') && ! await seVe('[data-org-sin-coincidencias]'));

  // Seguir escribiendo hasta que deja de coincidir: el caso de quien
  // empieza igual que una del listado y la suya es otra.
  await p.type('input[name="org_nombre"]', ' Zzqx otra');
  await esperar(1300);
  di(`${pantalla}: seguir hasta cero coincidencias la vuelve a enseñar`,
    await seVe('[data-org-sin-coincidencias]') && ! await seVe('.org-sugerencias'));

  await escribir('Z');
  di(`${pantalla}: con una sola letra no busca ni dice nada`, ! await seVe('[data-org-sin-coincidencias]'));
};

t('Paso 3 del wizard, sin sesión');

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
// La raíz del wizard y no el primer `[x-data]`: el header monta el suyo.
await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 3; });
await esperar(300);
await comprobar('Wizard');

t('Crear cuenta de organizador (/mi-cuenta/registro)');

await p.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await comprobar('Registro');

di('Sin errores de JavaScript', errores.length === 0, errores.join(' | '));

await nav.close();

console.log('');
console.log(`${ok} OK, ${mal} MAL`);
process.exit(mal ? 1 : 0);
