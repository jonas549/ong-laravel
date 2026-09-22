// Decodifica un QR REAL de produccion, despues del cambio de URL del punto 1.
//
// No comprueba que el archivo exista —eso no diria nada—: lee los pixeles con
// jsQR, igual que la camara de un telefono, y despues PIDE la direccion que
// salga para ver si responde. Un QR mal generado se ve perfecto y no escanea, y
// eso se descubre con cien carteles ya impresos.
//
//   node pruebas/qr-produccion.mjs
import puppeteer from 'puppeteer-core';
import jsQR from 'jsqr';
import { PNG } from 'pngjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'https://el-sitio-en-produccion';
// Los nombres de las variables de entorno los fija `credenciales.mjs`, que es
// el que usan todas. Aqui se llamaba DPS_ADMIN_CLAVE y nadie mas lo sabia.
import { ADMIN, CLAVE_ADMIN as CLAVE } from './credenciales.mjs';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(58)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 900 });

t(`QR real servido desde ${B}`);

await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', ADMIN);
await p.type('input[name="password"]', CLAVE);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
di('Entra al panel', ! p.url().includes('/login'), p.url());

// Una actividad publicada de verdad, la primera que haya.
await p.goto(`${B}/admin/actividades?estado=publicada`, { waitUntil: 'networkidle2' });
const id = await p.$$eval('a[href*="/admin/actividades/"]', (as) => as
  .map((a) => a.getAttribute('href')?.match(/\/admin\/actividades\/(\d+)$/)?.[1])
  .find(Boolean));
di('Hay una actividad publicada con la que probar', !! id, `id=${id}`);

// El PNG, descargado con la sesion del panel.
const png = await p.evaluate(async (url) => {
  const r = await fetch(url, { credentials: 'include' });
  const b = new Uint8Array(await r.arrayBuffer());
  return { estado: r.status, tipo: r.headers.get('content-type'), bytes: Array.from(b) };
}, `${B}/admin/actividades/${id}/qr.png`);

di('El QR se descarga', png.estado === 200, `${png.estado} · ${png.tipo}`);
di('Y es un PNG de verdad', (png.tipo ?? '').includes('image/png'), `${png.bytes.length} bytes`);

const imagen = PNG.sync.read(Buffer.from(png.bytes));
const leido = jsQR(new Uint8ClampedArray(imagen.data), imagen.width, imagen.height);

di('SE DECODIFICA, como lo haria una camara', leido !== null,
  `${imagen.width}x${imagen.height} → ${leido?.data ?? '(ilegible)'}`);

const url = leido?.data ?? '';
di('Y lo que lleva dentro es la encuesta, por slug', /\/evaluar\/[^/]+$/.test(url), url);
di('NO lleva la direccion nueva de la ficha: el QR no cambio',
  ! url.includes('/activity/'), url);
di('Y apunta al dominio de produccion, no a otro',
  url.startsWith(B), url);

// Lo que de verdad importa: que esa direccion responda.
const respuesta = await p.evaluate(async (u) => {
  const r = await fetch(u, { redirect: 'follow' });
  return { estado: r.status, final: r.url };
}, url);
di('La direccion del QR responde 200', respuesta.estado === 200, `${respuesta.estado} · ${respuesta.final}`);

// Y la encuesta que sale es la de ESA actividad.
await p.goto(url, { waitUntil: 'networkidle2' });
const hayFormulario = await p.evaluate(() =>
  !! document.querySelector('form') && /experiencia|evalu/i.test(document.body.innerText));
di('Y enseña la encuesta de esa actividad', hayFormulario, (await p.title()).slice(0, 70));

console.log('');
console.log(`${ok} bien · ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
