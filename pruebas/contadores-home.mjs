// Tanda del 05/10, punto 3 — las dos barras de «¡Súmate y juntos llegaremos
// más lejos!», dinámicas.
//
// Lo que se ve tiene que ser la base del panel MÁS lo contado en la base:
// actividades publicadas en la primera, inscripciones sin las canceladas en la
// segunda. Se comprueba leyendo el home, y luego moviendo la base —publicar,
// inscribir, cancelar— y mirando otra vez: un número escrito a mano puede
// coincidir por casualidad, uno que se mueve con la base no.
//
// Cambiar la base desde el panel también tiene que notarse. Al terminar deja
// todo como estaba.
//
//   node pruebas/contadores-home.mjs
//
// Contra producción NO: publica, inscribe y toca la sección del home.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();
const num = (s) => parseInt(String(s).replace(/\D+/g, ''), 10);

const contar = () => JSON.parse(ultima(tinker(
  `echo json_encode(['act' => App\\Models\\Activity::where('estado','publicada')->count(), 'ins' => App\\Models\\Registration::activas()->count()]);`)));

// Lo que pinta el servidor, antes de que el JavaScript lo anime.
const leerHome = async () => {
  const html = await (await fetch(`${B}/`)).text();
  const m = [...html.matchAll(/class="count">([^<]*)<\/span> de ([^<]*)/g)].map((x) => ({ actual: num(x[1]), meta: num(x[2]) }));
  const w = [...html.matchAll(/class="barfill" style="width:(\d+)%/g)].map((x) => +x[1]);

  return { barras: m, anchos: w };
};

const fila = ultima(tinker(`$s = App\\Models\\HomeSection::where('clave','meta')->first(); echo $s ? json_encode($s->contenido) : 'null';`));
const contenidoAntes = fila === 'null' ? null : fila;
const base1 = num(ultima(tinker(`echo App\\Models\\HomeSection::where('clave','meta')->first()?->texto('barra1_actual') ?? '500';`)));
const base2 = num(ultima(tinker(`echo App\\Models\\HomeSection::where('clave','meta')->first()?->texto('barra2_actual') ?? '50000';`)));

const SELLO = Date.now();
let actividadPrueba = null;

try {
  t('1 · Lo que se ve es base + lo contado');

  let c = contar();
  let h = await leerHome();
  di('se pintan las dos barras', h.barras.length === 2, JSON.stringify(h.barras));
  di(`actividades: ${base1} + ${c.act} publicadas`, h.barras[0]?.actual === base1 + c.act, `${h.barras[0]?.actual}`);
  di(`personas: ${base2} + ${c.ins} inscripciones`, h.barras[1]?.actual === base2 + c.ins, `${h.barras[1]?.actual}`);
  di('las metas siguen siendo las del panel', h.barras[0]?.meta === 1000 && h.barras[1]?.meta === 100000, JSON.stringify(h.barras.map((b) => b.meta)));
  const pct = (b) => Math.round(Math.min(100, b.actual / b.meta * 100));
  di('el largo de cada barra sale del total y la meta', h.anchos[0] === pct(h.barras[0]) && h.anchos[1] === pct(h.barras[1]), JSON.stringify(h.anchos));

  t('2 · Se mueve con la base');

  // Una actividad en borrador de la organización sembrada, que se publica.
  actividadPrueba = ultima(tinker(
    `$o = App\\Models\\Activity::where('estado','publicada')->firstOrFail();`
    + ` $a = $o->replicate(); $a->forceFill(['titulo' => 'Contador ${SELLO}', 'slug' => 'contador-${SELLO}', 'estado' => 'borrador', 'published_at' => null])->save(); echo $a->id;`));

  const antes = (await leerHome()).barras;
  tinker(`App\\Models\\Activity::whereKey(${actividadPrueba})->update(['estado' => 'publicada', 'published_at' => now()]); echo 'OK';`);
  h = await leerHome();
  di('publicar una actividad suma 1 a la primera barra', h.barras[0].actual === antes[0].actual + 1, `${antes[0].actual} → ${h.barras[0].actual}`);

  const reg = ultima(tinker(
    `$r = App\\Models\\Registration::create(['activity_id' => ${actividadPrueba}, 'nombre' => 'Contador', 'correo' => 'contador.${SELLO}@ejemplo.cl', 'estado' => 'confirmado', 'token' => Illuminate\\Support\\Str::random(40)]); echo $r->id;`));
  h = await leerHome();
  di('una inscripción suma 1 a la segunda', h.barras[1].actual === antes[1].actual + 1, `${antes[1].actual} → ${h.barras[1].actual}`);

  tinker(`App\\Models\\Registration::whereKey(${reg})->update(['estado' => 'cancelado']); echo 'OK';`);
  h = await leerHome();
  di('cancelarla la descuenta', h.barras[1].actual === antes[1].actual, `${h.barras[1].actual}`);

  tinker(`App\\Models\\Activity::whereKey(${actividadPrueba})->update(['estado' => 'cancelada']); echo 'OK';`);
  h = await leerHome();
  di('una actividad cancelada deja de contar', h.barras[0].actual === antes[0].actual, `${h.barras[0].actual}`);

  t('3 · La base y la meta se cambian desde el panel');

  const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
  const p = await nav.newPage();
  await p.setViewport({ width: 1440, height: 1000 });
  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', ADMIN);
  await p.type('input[type="password"]', CLAVE_ADMIN);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
  await p.goto(`${B}/admin/paginas/home/meta`, { waitUntil: 'networkidle2' });
  const textoPanel = await p.evaluate(() => document.body.innerText);
  di('el panel rotula la base como «de años anteriores»', textoPanel.includes('Primera barra — base de años anteriores') && textoPanel.includes('Segunda barra — base de años anteriores'));
  di('y explica que se suma lo de esta edición', textoPanel.includes('más lo de esta edición'));
  di('la meta sigue siendo un campo del panel', textoPanel.includes('Primera barra — meta') && textoPanel.includes('Segunda barra — meta'));

  // Se cambian en el formulario de la sección y se publica con su botón.
  for (const [campo, valor] of [['barra1_actual', '700'], ['barra1_meta', '2.000']]) {
    await p.$eval(`[name="${campo}"]`, (el) => { el.value = ''; });
    await p.type(`[name="${campo}"]`, valor);
  }
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => [...document.querySelectorAll('button[type="submit"]')].find((b) => b.textContent.trim() === 'Publicar').click())]);
  c = contar();
  h = await leerHome();
  di('con base 700 y meta 2.000, se ve 700 + lo contado de 2.000', h.barras[0].actual === 700 + c.act && h.barras[0].meta === 2000, `${h.barras[0].actual} de ${h.barras[0].meta}`);

  t('4 · En pantalla, después de la animación');

  await p.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  await p.goto(`${B}/`, { waitUntil: 'networkidle2' });
  await p.evaluate(() => { document.documentElement.style.scrollBehavior = 'auto'; document.querySelector('.barfill').scrollIntoView({ block: 'center' }); });
  await new Promise((r) => setTimeout(r, 3500));
  const visto = await p.evaluate(() => [...document.querySelectorAll('.barfill')].length === 2
    && [...document.querySelectorAll('.count')].filter((e) => e.closest('section')?.querySelector('.barfill')).map((e) => e.textContent));
  di('el contador termina en el total, con puntos de miles', Array.isArray(visto) && num(visto[0]) === 700 + c.act && num(visto[1]) === base2 + c.ins, JSON.stringify(visto));
  await nav.close();
} finally {
  if (actividadPrueba) {
    tinker(`App\\Models\\Registration::where('activity_id', ${actividadPrueba})->forceDelete(); App\\Models\\Activity::whereKey(${actividadPrueba})->forceDelete(); echo 'OK';`);
  }
  if (contenidoAntes !== null) {
    tinker(`$s = App\\Models\\HomeSection::where('clave','meta')->first(); $s->contenido = json_decode(base64_decode('${Buffer.from(contenidoAntes).toString('base64')}'), true); $s->save(); echo 'OK';`);
  } else {
    tinker(`$s = App\\Models\\HomeSection::where('clave','meta')->first(); if ($s) { $s->contenido = null; $s->save(); } echo 'OK';`);
  }
  console.log('  (actividad de prueba borrada y sección «Súmate a la meta» devuelta como estaba)');
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
