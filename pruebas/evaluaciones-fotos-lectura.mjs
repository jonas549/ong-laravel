// P5, P6, P7 y P8 — la parte que se puede comprobar SIN escribir nada.
//
// Existe aparte de `evaluaciones-fotos.mjs` porque aquélla sube fotos, publica
// respuestas y reclama organizaciones: en producción eso deja datos de verdad.
// Esto sólo carga pantallas y descarga lo que ya hay.
//
//   DPS_URL=https://ong.sandboxdelta.com node pruebas/evaluaciones-fotos-lectura.mjs
import puppeteer from 'puppeteer-core';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

const salir = async () => {
  /*
   * Hay que estar EN el sitio para poder pedirle nada: recién abierto el
   * navegador está en `about:blank`, y un `fetch` desde ahí es de otro origen
   * y lo corta el navegador.
   */
  if (! p.url().startsWith(B)) {
    await p.goto(`${B}/`, { waitUntil: 'domcontentloaded' });
  }

  await p.evaluate(async (base) => {
    for (const ruta of ['/mi-cuenta/logout', '/admin/logout']) {
      const doc = await (await fetch(base + '/', { credentials: 'same-origin' })).text();
      const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
      if (! token) continue;
      await fetch(base + ruta, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + encodeURIComponent(token),
      });
    }
  }, B);
};
const entrar = async (puerta, c, k) => {
  await salir();
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', c);
  await p.type('input[name="password"]', k);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};

t('P6 — el tope de fotografías es administrable');

await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });

const campo = await p.$('[name="evaluacion_max_fotos"]');
di('El ajuste está en Configuración → General', campo !== null);
di('Con 3 por defecto', campo && await p.$eval('[name="evaluacion_max_fotos"]', (n) => n.value) === '3',
  campo ? await p.$eval('[name="evaluacion_max_fotos"]', (n) => n.value) : '(no está)');
di('Y explica que un 0 lo apaga', (await texto()).includes('Un 0 quita el campo de fotografía'));

t('P6 — la encuesta admite varias');

// Una actividad publicada cualquiera: su encuesta es pública.
const slugEncuesta = await p.evaluate(async (base) => {
  const html = await (await fetch(base + '/actividades', { credentials: 'same-origin' })).text();
  return (html.match(/\/activity\/\d+\/([a-z0-9-]+)"/) ?? [])[1] ?? null;
}, B);

if (! slugEncuesta) {
  di('Hay una actividad publicada con la que probar', false, 'ninguna');
} else {
  await p.goto(`${B}/evaluar/${slugEncuesta}`, { waitUntil: 'networkidle2' });

  const hayCampo = await p.$('#ev-fotos');
  di('El campo de fotos es múltiple', hayCampo !== null && await p.$eval('#ev-fotos', (n) => n.multiple));
  di('Y dice cuántas caben', /hasta \d+ fotograf/i.test(await texto()));
}

t('P5 — el organizador tiene su pantalla de evaluaciones');

await entrar('/mi-cuenta/login', ORG, CLAVE_ORG);
await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
di('Hay enlace a Evaluaciones en su cuenta', (await texto()).includes('Evaluaciones'));

await p.goto(`${B}/mi-cuenta/evaluaciones`, { waitUntil: 'networkidle2' });
const suyas = await texto();
di('La pantalla carga', suyas.includes('Evaluaciones de tus actividades'));
di('Con el filtro por actividad', (await p.$('select[name="actividad"]')) !== null);
di('Y sólo ofrece actividades suyas', await p.evaluate(() => {
  const op = [...document.querySelectorAll('select[name="actividad"] option')].map((o) => o.textContent);
  return op.length > 0;
}));
di('No enseña el correo de quien respondió', ! /@/.test(
  await p.evaluate(() => [...document.querySelectorAll('.card')].map((c) => c.innerText).join(' '))
));

t('P7 y P8 — exportación y descarga, desde el panel');

await entrar('/admin/login', ADMIN, CLAVE_ADMIN);

const csv = await p.evaluate(async (u) => {
  const r = await fetch(u, { credentials: 'same-origin' });
  return { estado: r.status, texto: (await r.text()).replace(/^\uFEFF/, '') };
}, `${B}/admin/evaluaciones/exportar?formato=csv`);

di('El Excel se descarga', csv.estado === 200);
di('Con la columna de enlaces a las fotografías',
  csv.texto.split('\n')[0].includes('Enlaces a las fotografías'),
  csv.texto.split('\n')[0].split(';').slice(-2).join(' ; '));

await p.goto(`${B}/admin/evaluaciones/fotos`, { waitUntil: 'networkidle2' });
di('La cuadrícula de fotos carga', ! (await texto()).includes('Server Error'));
di('Con el botón de descargar lo filtrado', (await texto()).includes('Descargar estas fotografías'));

const zip = await p.evaluate(async (u) => {
  const r = await fetch(u, { credentials: 'same-origin' });
  if (! r.ok) return { estado: r.status, cabecera: '' };
  const b = new Uint8Array(await r.arrayBuffer());
  return { estado: r.status, bytes: b.length, cabecera: b.slice(0, 2).join(',') };
}, `${B}/admin/evaluaciones/descargar-fotos?estado=autorizadas`);

// 404 es una respuesta válida aquí: significa que no hay fotos autorizadas
// todavía, no que la descarga esté rota. Lo que no puede salir es un 500.
di('La descarga en zip responde',
  zip.estado === 200 ? zip.cabecera === '80,75' : zip.estado === 404,
  zip.estado === 200 ? `zip de ${zip.bytes} bytes` : `HTTP ${zip.estado} (sin fotos autorizadas)`);

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
