// P1 — la ID como primera columna en TODAS las tablas del panel.
//
// Recorre el panel entero en Chrome de verdad y comprueba dos cosas por
// tabla: que el primer encabezado de datos dice «ID», y que la primera celda
// de la primera fila es un número. Lo segundo importa tanto como lo primero:
// una cabecera «ID» sobre una columna vacía es justo el fallo que esto busca.
//
//   node pruebas/ids-panel.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1600, height: 1000 });

await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', 'admin@ong-laravel.test');
await p.type('input[name="password"]', 'admin1234');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

/*
 * Lee la primera tabla con filas de la pantalla. Salta las columnas que no
 * son de datos —la casilla de selección y el tirador de arrastre— porque la
 * petición es que la ID sea la primera COLUMNA, y ésas dos no lo son.
 */
const leerTabla = (indice = 0) => p.evaluate((i) => {
  const tablas = [...document.querySelectorAll('table.tabla')];
  const tabla = tablas[i];
  if (! tabla) return null;

  const ths = [...tabla.querySelectorAll('thead th')];
  const esDeDatos = (th) => ! th.querySelector('input[type=checkbox]')
    && ! th.querySelector('.visualmente-oculto');
  const cabeceras = ths.filter(esDeDatos).map((th) => th.innerText.trim());

  const fila = tabla.querySelector('tbody tr');
  const tds = fila ? [...fila.querySelectorAll('td')] : [];
  // El colspan del estado vacío no es una fila de datos.
  const vacia = tds.length === 1 && tds[0].hasAttribute('colspan');
  const celdas = vacia ? [] : tds.filter((td) => ! td.querySelector('input[type=checkbox]')
    && td.getAttribute('aria-hidden') !== 'true').map((td) => td.innerText.trim());

  return { cabeceras, celdas, filas: vacia ? 0 : tabla.querySelectorAll('tbody tr').length, total: tablas.length };
}, indice);

const revisar = async (nombre, url, indice = 0) => {
  await p.goto(`${B}${url}`, { waitUntil: 'networkidle2' });
  const d = await leerTabla(indice);
  if (! d) { di(nombre, false, 'no hay tabla en la pantalla'); return; }
  const cabecera = (d.cabeceras[0] ?? '').replace(/[↕↑↓]/g, '').trim();
  di(`${nombre} — primera columna «ID»`, cabecera.toUpperCase() === 'ID', `dice «${cabecera}»`);
  if (d.filas === 0) { console.log(`     (sin filas: no se puede comprobar el valor)`); return; }
  di(`${nombre} — la primera celda es la ID`, /^\d+$/.test(d.celdas[0] ?? ''), `vale «${d.celdas[0]}»`);
};

t('Las tablas del panel de administración');

await revisar('Actividades', '/admin/actividades');
await revisar('Actividades → Pendientes', '/admin/actividades/pendientes');
await revisar('Actividades → Publicadas', '/admin/actividades/publicadas');
await revisar('Actividades → Canceladas', '/admin/actividades/canceladas');
await revisar('Evaluaciones', '/admin/evaluaciones');
await revisar('Inscripciones', '/admin/inscripciones');
await revisar('Organizaciones', '/admin/organizaciones');
await revisar('Usuarios → Administradores', '/admin/usuarios?rol=admin');
await revisar('Usuarios → Organizadores', '/admin/usuarios?rol=organizer');
await revisar('Contenido → Noticias', '/admin/contenido/noticias');
await revisar('Contenido → Ediciones', '/admin/contenido/ediciones');
await revisar('Contenido → Testimonios', '/admin/contenido/testimonios');
await revisar('Contenido → Partners', '/admin/contenido/partners');
await revisar('Contenido → Cifras', '/admin/contenido/cifras');
await revisar('Contenido → Tarjetas', '/admin/contenido/tarjetas');
await revisar('Páginas sueltas', '/admin/contenido/paginas');
await revisar('Catálogos → Temas', '/admin/taxonomias?grupo=tema');
await revisar('Catálogos → Características', '/admin/taxonomias?grupo=caracteristica');
await revisar('Catálogos → Públicos', '/admin/taxonomias?grupo=publico');
await revisar('Catálogos → Accesibilidad', '/admin/taxonomias?grupo=acceso');
await revisar('Regiones y comunas', '/admin/regiones');
await revisar('Registro de correos', '/admin/correos');
await revisar('Plantillas de correo', '/admin/plantillas');
/*
 * En accesos conviven dos tablas, y la de sospechosos sólo se pinta cuando hay
 * alguna: buscarla por posición fija da un falso fallo el día que no la hay.
 * Se localiza la del histórico por una cabecera que sólo tiene ella.
 */
await p.goto(`${B}/admin/accesos`, { waitUntil: 'networkidle2' });
const iAccesos = await p.evaluate(() => [...document.querySelectorAll('table.tabla')]
  .findIndex((t) => /dispositivo/i.test(t.innerText)));
await revisar('Registro de accesos (histórico)', '/admin/accesos', iAccesos);
await revisar('Escritorio — pendientes de revisión', '/admin', 0);
await revisar('Escritorio — últimas inscripciones', '/admin', 1);

t('La biblioteca de medios, que es una rejilla y no una tabla');

await p.goto(`${B}/admin/medios`, { waitUntil: 'networkidle2' });
const medios = await p.evaluate(() => {
  const ficha = document.querySelector('.ficha-medio');
  return ficha ? ficha.innerText.replace(/\s+/g, ' ') : null;
});
di('Cada ficha de medio enseña su ID', !! medios && /#\d+/.test(medios), medios ? (medios.match(/#\d+/) ?? ['(no)'])[0] : '(sin medios)');

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
