// P12 — la marquesina de organizaciones participantes.
//
// El cliente reportó que «repite logos». Detrás de esa frase hay dos cosas
// distintas, y se comprueban las dos:
//
//   1. La fuente. Pasa a ser la tabla de ORGANIZACIONES; antes eran unas
//      pastillas de texto escritas a mano en `partners`, una lista paralela
//      que no tenía nada que ver con quién había publicado.
//   2. La repetición. La segunda pasada del carril existe y tiene que
//      existir: la animación desplaza -50% y sin ella el bucle da un salto.
//      Lo que no puede haber es el mismo nombre dos veces DENTRO de una
//      pasada, que es lo que pasaba con los duplicados de producción.
//
//   node pruebas/marquesina.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();

/*
 * La salida de `artisan` viene por la consola de Windows, que no devuelve
 * UTF-8: «Fundación» vuelve con la ó rota y comparar cadenas tal cual da un
 * falso fallo. Para saber si es el mismo nombre basta con compararlos sin
 * tildes y en minúsculas.
 */
const igualable = (texto) => (texto ?? '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/[^a-zA-Z0-9 ]/g, '')
  .replace(/\s+/g, ' ')
  .trim()
  .toLowerCase();

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
await p.goto(`${B}/`, { waitUntil: 'networkidle2' });

t('P12 — de dónde salen los nombres de la marquesina');

const chips = await p.$$eval('.marquee-track .logo-chip', (n) => n.map((c) => ({
  nombre: c.querySelector('span:last-child')?.textContent.trim(),
  duplicado: c.getAttribute('aria-hidden') === 'true',
})));

di('La marquesina se pinta', chips.length > 0, `${chips.length} pastillas`);

const primeraPasada = chips.filter((c) => ! c.duplicado).map((c) => c.nombre);
const segundaPasada = chips.filter((c) => c.duplicado).map((c) => c.nombre);
const enPantalla = primeraPasada.map(igualable);

di('Va en dos pasadas, que es lo que hace el bucle continuo',
  primeraPasada.length === segundaPasada.length && segundaPasada.length > 0,
  `${primeraPasada.length} + ${segundaPasada.length}`);

di('La copia está oculta a los lectores de pantalla',
  segundaPasada.length > 0 && chips.filter((c) => c.duplicado).length === segundaPasada.length);

/*
 * Lo que de verdad pedía el punto: dentro de una pasada, ningún nombre dos
 * veces. Antes salían los dos «deltadigital.cl» de producción, seguidos.
 */
const repetidos = primeraPasada.filter((n, i) => primeraPasada.indexOf(n) !== i);
di('**Ningún nombre se repite dentro de una pasada**', repetidos.length === 0,
  repetidos.length ? repetidos.join(' · ') : '');

const esperadas = ultima(tinker(
  "echo App\\Models\\Organization::where('activo',true)"
  + "->whereHas('activities', fn($q) => $q->where('estado','publicada'))"
  + "->orderBy('nombre')->pluck('nombre')->unique()->implode('||');"
)).split('||').filter(Boolean);

di('Los nombres son los de las organizaciones con actividad publicada',
  esperadas.length > 0 && esperadas.every((n) => enPantalla.includes(igualable(n))),
  `${esperadas.length} en la base · ${primeraPasada.length} en pantalla`);

di('Y son exactamente ésas, ni una de más',
  primeraPasada.length === esperadas.length, primeraPasada.join(' · '));

const pastillas = ultima(tinker(
  "echo App\\Models\\Partner::where('grupo','participante')->pluck('nombre')->implode('||');"
)).split('||').filter(Boolean);

const soloPastillas = pastillas.filter((n) => ! esperadas.some((e) => igualable(e) === igualable(n)));
di('Y ya NO son las pastillas escritas a mano',
  ! soloPastillas.some((n) => enPantalla.includes(igualable(n))),
  `${pastillas.length} pastillas viejas, ${soloPastillas.length} que no son organizaciones`);

t('Lo que ve el ojo: la vuelta del carril');

/*
 * El carril tiene que medir dos pasadas: es lo que hace que el -50% de la
 * animación caiga justo donde empieza la copia. Se mide contra una pasada y
 * no contra el ancho de la ventana, que con pocas organizaciones sembradas en
 * local daría un falso fallo.
 */
const medidas = await p.evaluate(() => {
  const todas = [...document.querySelectorAll('.marquee-track .logo-chip')];
  const visibles = todas.filter((c) => c.getAttribute('aria-hidden') !== 'true');
  const ancho = (lista) => lista.reduce((total, c) => total + c.getBoundingClientRect().width, 0);

  return { carril: document.querySelector('.marquee-track')?.scrollWidth ?? 0, pasada: ancho(visibles) };
});

di('El carril mide dos pasadas, que es lo que pide la animación',
  medidas.pasada > 0 && medidas.carril >= medidas.pasada * 1.9,
  `${Math.round(medidas.carril)} px · pasada ${Math.round(medidas.pasada)} px`);

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
