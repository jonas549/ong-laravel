// P18 — duplicar una sección del home.
//
// La decisión que hay detrás (D4 de la tanda anterior): la copia es **exacta y
// a la vez independiente**. Nace con todo el contenido de la original ya
// escrito en su propia fila, y a partir de ahí las dos se editan por separado.
//
// Eso último es lo que de verdad se comprueba aquí: que cambiar la copia no
// cambia la original. Un duplicado que comparta datos con su origen no es un
// duplicado, es un espejo, y se descubre cuando el cliente ya ha escrito.
//
//   node pruebas/duplicar-seccion.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultimaLinea = (texto) => texto.split('\n').filter((x) => x.trim()).pop().trim();

/* Se limpia lo que hubiera quedado de una pasada anterior. */
const limpiar = () => tinker(
  `App\\Models\\HomeSection::where('clave','like','%--%')->delete(); echo 'limpio';`
);

limpiar();

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1500, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', 'admin@ong-laravel.test');
await p.type('input[name="password"]', 'admin1234');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

const irAlListado = () => p.goto(`${B}/admin/paginas/home`, { waitUntil: 'networkidle2' });
const cuantasFilas = () => p.evaluate(() => document.querySelectorAll('.fila-seccion').length);

/* ═══════════════ El botón ════════════════════════════════════════ */

t('P18 — el botón «Duplicar», junto a «Editar» y «Esconder»');

await irAlListado();
const antes = await cuantasFilas();
di('El listado carga', antes === 13, `${antes} secciones`);

di('Hay botón de duplicar', await p.evaluate(() => [...document.querySelectorAll('button')]
  .some((b) => b.textContent.trim() === 'Duplicar')));

/*
 * Y NO en las ancladas. El hero y «¿Cómo participar?» están cosidos por un
 * margen negativo —la segunda se monta 96 px sobre la primera— así que una
 * copia de cualquiera de las dos se pondría encima de la otra. El encargo
 * pedía respetar eso.
 */
const enFijas = await p.evaluate(() => [...document.querySelectorAll('.fila-seccion')]
  .filter((f) => f.classList.contains('fila-seccion-fija'))
  .map((f) => /Duplicar/.test(f.innerText)));
di('**Pero no en las ancladas**', enFijas.length === 2 && enFijas.every((x) => x === false),
  `${enFijas.length} ancladas`);

// Y el servidor lo vuelve a comprobar: el botón es la cortesía, no la barrera.
const aMano = await p.evaluate(async (base) => {
  const doc = await (await fetch(base + '/admin/paginas/home', { credentials: 'same-origin' })).text();
  const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
  const r = await fetch(base + '/admin/paginas/home/hero/duplicar', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: '_token=' + encodeURIComponent(token),
  });

  return r.status;
}, B);
di('**Y duplicar el hero a mano se rechaza**', aMano === 403, `HTTP ${aMano}`);

/* ═══════════════ La copia ════════════════════════════════════════ */

t('La copia nace con el contenido de la original');

await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.evaluate(() => {
    const fila = [...document.querySelectorAll('.fila-seccion')].find((f) => /Cifras/.test(f.innerText));
    fila.querySelector('form[action*="duplicar"] button').click();
  }),
]);

di('Lleva directo a editar la copia', /paginas\/home\/cifras--2$/.test(p.url()), p.url().replace(B, ''));

const tituloCopia = await p.$eval('[name="titulo"]', (n) => n.value).catch(() => '');
const tituloOriginal = ultimaLinea(tinker(
  `$s = App\\Models\\HomeSection::where('clave','cifras')->first();`
  + ` echo $s->texto('titulo');`
));
di('**Y con el mismo texto que la original**', tituloCopia === tituloOriginal,
  `«${tituloCopia.slice(0, 40)}»`);

await irAlListado();
di('Hay una sección más', (await cuantasFilas()) === antes + 1, `${await cuantasFilas()} secciones`);
di('Marcada como copia', (await texto()).includes('(copia 2)'));

di('Y colocada justo detrás de la original', await p.evaluate(() => {
  const filas = [...document.querySelectorAll('.fila-seccion')].map((f) => f.innerText);
  const i = filas.findIndex((x) => /Cifras/.test(x) && ! /copia/.test(x));

  return i >= 0 && /copia 2/.test(filas[i + 1] ?? '');
}));

/* ═══════════════ Independiente ═══════════════════════════════════ */

t('**Y es independiente: cambiar una no cambia la otra**');

await p.goto(`${B}/admin/paginas/home/cifras--2`, { waitUntil: 'networkidle2' });
await p.evaluate(() => { document.querySelector('[name="titulo"]').value = ''; });
await p.type('[name="titulo"]', 'Titular sólo de la copia');
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.evaluate(() => [...document.querySelectorAll('button[type="submit"]')]
    .find((b) => /Publicar/i.test(b.textContent))?.click()),
]);

const trasEditar = ultimaLinea(tinker(
  `$o = App\\Models\\HomeSection::where('clave','cifras')->first();`
  + ` $c = App\\Models\\HomeSection::where('clave','cifras--2')->first();`
  + ` echo $o->texto('titulo').'||'.$c->texto('titulo');`
));
const [original, copia] = trasEditar.split('||');

di('La copia tiene el titular nuevo', copia === 'Titular sólo de la copia', copia);
di('**Y la original sigue con el suyo**', original === tituloOriginal, original.slice(0, 40));

/* ═══════════════ En el home ══════════════════════════════════════ */

t('Se pinta en el home, con el parcial de la original');

await p.goto(`${B}/`, { waitUntil: 'networkidle2' });
const cuerpo = await p.content();

di('La sección aparece dos veces', (cuerpo.match(/id="ediciones/g) ?? []).length === 2);
di('Con el titular de cada una', (await texto()).includes('Titular sólo de la copia'));
di('**Y las dos anclas son distintas**',
  /id="ediciones"/.test(cuerpo) && /id="ediciones-2"/.test(cuerpo));

/* ═══════════════ Se puede mover y apagar ═════════════════════════ */

t('La copia se arrastra y se esconde como cualquier otra');

await irAlListado();

// Se mueve al final por la misma ruta que usa el arrastre.
const movida = await p.evaluate(async (base) => {
  const doc = await (await fetch(base + '/admin/paginas/home', { credentials: 'same-origin' })).text();
  const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
  const claves = [...document.querySelectorAll('.fila-seccion')]
    .map((f) => f.getAttribute('data-clave'))
    .filter(Boolean);

  const sinCopia = claves.filter((c) => c !== 'cifras--2');
  const cuerpo = new URLSearchParams();
  cuerpo.append('_token', token);
  for (const c of [...sinCopia, 'cifras--2']) cuerpo.append('orden[]', c);

  const r = await fetch(base + '/admin/paginas/home/orden', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
    body: cuerpo.toString(),
  });

  return r.status;
}, B);

di('**Reordenar con la copia dentro no da 422**', movida === 200, `HTTP ${movida}`);

await irAlListado();
di('Y la copia se puede esconder', await p.evaluate(() => {
  const fila = [...document.querySelectorAll('.fila-seccion')].find((f) => /copia 2/.test(f.innerText));

  return !! fila && /Esconder|Mostrar/.test(fila.innerText);
}));

/* ═══════════════ Se puede deshacer ═══════════════════════════════ */

t('Y se puede borrar: duplicar no es una puerta de una sola dirección');

di('La copia tiene botón de eliminar', await p.evaluate(() => {
  const fila = [...document.querySelectorAll('.fila-seccion')].find((f) => /copia 2/.test(f.innerText));

  return !! fila && /Eliminar/i.test(fila.innerText);
}));

di('Las del catálogo NO', await p.evaluate(() => {
  const fila = [...document.querySelectorAll('.fila-seccion')].find((f) => /Cifras/.test(f.innerText) && ! /copia/.test(f.innerText));

  return !! fila && ! /Eliminar/i.test(fila.innerText);
}));

const borrada = ultimaLinea(tinker(
  `App\\Models\\HomeSection::where('clave','cifras--2')->delete();`
  + ` echo App\\Models\\HomeSection::where('clave','cifras--2')->count();`
));
di('Al borrarla desaparece', borrada === '0');

await p.goto(`${B}/`, { waitUntil: 'networkidle2' });
di('Y el home vuelve a tener una sola', ((await p.content()).match(/id="ediciones/g) ?? []).length === 1);

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

limpiar();

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
