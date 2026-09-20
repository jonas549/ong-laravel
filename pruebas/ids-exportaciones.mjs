// P1 (segunda mitad) — la ID como primera columna de toda exportación.
//
// Descarga cada exportación del panel en CSV y mira la primera celda de la
// cabecera. Se pide CSV y no XLSX a propósito: el XLSX es un zip y habría que
// descomprimirlo para leer una cabecera; el CSV la enseña en la primera línea,
// y las dos salen del mismo `Exportador` con las mismas cabeceras.
//
//   node pruebas/ids-exportaciones.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(58)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', 'admin@ong-laravel.test');
await p.type('input[name="password"]', 'admin1234');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

// La descarga se pide desde la propia página para que viaje la sesión.
const primeraCabecera = (url) => p.evaluate(async (u) => {
  const r = await fetch(u, { credentials: 'same-origin' });
  if (! r.ok) return `HTTP ${r.status}`;
  const texto = await r.text();
  // El CSV lleva marca de orden de bytes para que Excel lo abra en UTF-8.
  return texto.replace(/^\uFEFF/, '').split('\n')[0].split(';')[0].trim();
}, `${B}${url}`);

console.log('');
console.log('=== La primera columna de cada exportación ===');
console.log('');

const casos = [
  ['Evaluaciones', '/admin/evaluaciones/exportar?formato=csv'],
  ['Organizaciones', '/admin/organizaciones/exportar?formato=csv'],
  ['Regiones y comunas', '/admin/regiones/exportar?formato=csv'],
  ['Catálogos → Temas', '/admin/taxonomias/exportar?grupo=tema&formato=csv'],
  ['Contenido → Noticias', '/admin/contenido/noticias/exportar?formato=csv'],
  ['Contenido → Partners', '/admin/contenido/partners/exportar?formato=csv'],
  ['Contenido → Testimonios', '/admin/contenido/testimonios/exportar?formato=csv'],
];

for (const [nombre, url] of casos) {
  const c = await primeraCabecera(url);
  di(nombre, c === 'ID', `dice «${c}»`);
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
