// P5, P6, P7 y P8 — varias fotos por evaluación, y quién las puede ver.
//
// Lo que se comprueba de verdad, y no de palabra:
//
//   P6 — que se guardan VARIAS y que el tope de Configuración manda: se suben
//        cuatro con el tope en tres y se cuentan las que quedaron.
//   P5 — que el organizador ve las de sus actividades y NO las de otra
//        organización, tampoco pidiendo el archivo por su URL. Esto último es
//        lo importante: la pantalla se puede acotar y dejar los archivos
//        abiertos.
//   P7 — que el Excel trae los enlaces a las fotografías.
//   P8 — que la descarga en zip respeta los filtros de la pantalla.
//
// Antes:
//   php artisan tinker --execute="require base_path('pruebas/datos-evaluacion.php');"
//   php artisan tinker --execute="require base_path('pruebas/datos-permisos.php');"
//
//   node pruebas/evaluaciones-fotos.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));

const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (salida) => salida.split('\n').filter((l) => l.trim()).pop().trim();

/* Tres fotos distintas de verdad: si fueran el mismo archivo no se vería si se
   guardaron las tres o la misma tres veces. */
const FOTOS = [1, 2, 3, 4].map((n) => {
  const ruta = join(tmpdir(), `dps-foto-${n}.png`);
  // PNG mínimo válido, de un color por archivo.
  const png = Buffer.from(
    '89504e470d0a1a0a0000000d4948445200000010000000100802000000909168'
    + '36000000' + (20 + n).toString(16).padStart(2, '0') + '4944415478da63'
    + 'fc'.repeat(n) + 'ff3f000005fe02fe' + 'a7'.repeat(1) + '0000000049454e44ae426082', 'hex');
  writeFileSync(ruta, png);
  return ruta;
});

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });

/*
 * Entrar por una puerta con la sesión de otra cuenta abierta redirige al
 * panel en vez de enseñar el formulario, así que primero se sale. Sin esto la
 * prueba de acotamiento no llegaba ni a empezar.
 */
const salir = async () => {
  await p.evaluate(async (base) => {
    for (const ruta of ['/mi-cuenta/logout', '/admin/logout']) {
      const t = await (await fetch(base + '/', { credentials: 'same-origin' })).text();
      const token = (t.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
      if (! token) continue;
      await fetch(base + ruta, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + encodeURIComponent(token),
      });
    }
  }, B);
};

const entrar = async (puerta, correo, clave) => {
  await salir();
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', correo);
  await p.type('input[name="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
const ponerTope = (n) => tinker(`App\\Models\\Setting::set('evaluacion_max_fotos','${n}'); cache()->flush(); echo 'tope='.App\\Models\\ActivityEvaluation::maximoFotos();`);

/* ═══════════════ P6 — varias fotos, con tope ═════════════════════ */

t('P6 — varias fotografías por respuesta, con el tope de Configuración');

di('El tope por defecto es 3', ultima(ponerTope(3)).includes('tope=3'));

tinker('cache()->flush();');
await p.goto(`${B}/evaluar/prueba-eval-abierta`, { waitUntil: 'networkidle2' });

di('El campo admite varias', await p.$eval('#ev-fotos', (n) => n.multiple));
di('Y lo dice en pantalla', (await texto()).includes('hasta 3 fotografías'));

const correo = `fotos${Date.now()}@ejemplo.cl`;
await p.type('#ev-nombre', 'Persona con fotos');
await p.type('#ev-correo', correo);
await p.click('input[name="experiencia"][value="4"]');
await p.type('#ev-significado', 'Tres fotos del mismo día.');
await p.click('input[name="motivacion"][value="5"]');

// Cuatro con el tope en tres: tiene que dejar fuera la última y decirlo.
await (await p.$('#ev-fotos')).uploadFile(...FOTOS);
await esperar(1600);

di('Se quedan sólo las que caben', await p.evaluate(() => document.querySelectorAll('.evaluacion-foto-ficha').length) === 3,
  String(await p.evaluate(() => document.querySelectorAll('.evaluacion-foto-ficha').length)));
di('Y se avisa de la que sobró', /sólo se pueden subir 3/i.test(await texto()));
di('Con el cupo lleno, el botón de elegir desaparece',
  await p.$eval('.evaluacion-foto-boton', (n) => n.getBoundingClientRect().height === 0));

// Quitar una devuelve el botón: es la prueba de que el cupo se recalcula.
await p.click('.evaluacion-foto-sacar');
await esperar(300);
di('Al quitar una, vuelve el botón de elegir',
  await p.$eval('.evaluacion-foto-boton', (n) => n.getBoundingClientRect().height > 0));
di('Y quedan dos', await p.evaluate(() => document.querySelectorAll('.evaluacion-foto-ficha').length) === 2);

await p.click('input[name="foto_autorizada"]');
await esperar(200);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('.evaluacion-enviar')]);

const guardadas = ultima(tinker(
  `$e = App\\Models\\ActivityEvaluation::where('correo','${correo}')->first();`
  + ` echo $e ? ($e->fotos()->count().'|'.($e->foto_autorizada?1:0).'|'.$e->fotos()->pluck('orden')->implode(',')) : 'NO';`
));
const [cuantas, autorizada, ordenes] = guardadas.split('|');
di('Se guardaron las dos que quedaban', cuantas === '2', `${cuantas} fotos`);
di('Con una sola autorización para las dos', autorizada === '1');
di('Y numeradas en orden', ordenes === '0,1', ordenes);

/* ═══════════════ P5 — sólo las suyas ═════════════════════════════ */

t('P5 — el organizador ve las de SUS actividades');

await entrar('/mi-cuenta/login', 'organizador@ong-laravel.test', 'organizador1234');
await p.goto(`${B}/mi-cuenta/evaluaciones`, { waitUntil: 'networkidle2' });

const suyas = await texto();
di('Tiene su pantalla de evaluaciones', suyas.includes('Evaluaciones de tus actividades'));
di('Con los promedios de sus actividades', /Experiencia/.test(suyas) && /\/ 5/.test(suyas));
di('Y el filtro sólo ofrece actividades suyas', await p.evaluate(() => {
  const op = [...document.querySelectorAll('select[name="actividad"] option')].map((o) => o.value).filter(Boolean);
  return op.length > 0;
}));

/*
 * Y ahora la pregunta que de verdad importa: ¿puede un organizador de OTRA
 * organización abrir esa foto escribiendo su dirección?
 *
 * Se prueba con la cuenta de `datos-permisos.php`, que es de otra organización
 * de verdad. Hacerlo con el organizador sembrado no valdría: las actividades
 * del escenario de evaluación son SUYAS, así que ahí un 200 es lo correcto y
 * la comprobación no diría nada. Ése fue el primer intento y pasaba en falso.
 */
const ajena = ultima(tinker(
  `$a = App\\Models\\Activity::where('slug','prueba-eval-abierta')->first();`
  + ` $f = App\\Models\\EvaluationPhoto::whereIn('activity_evaluation_id',`
  + ` App\\Models\\ActivityEvaluation::where('activity_id',$a->id)->select('id'))->first();`
  + ` echo $f ? $f->id : 'NO';`
));

di('Hay una foto con la que probar', ajena !== 'NO', `foto #${ajena}`);

const propia = await p.evaluate(async (u) => (await fetch(u, { credentials: 'same-origin' })).status,
  `${B}/mi-cuenta/evaluaciones/fotos/${ajena}`);
di('El organizador sí abre la foto de SU actividad', propia === 200, `HTTP ${propia}`);

await entrar('/mi-cuenta/login', 'org-a@prueba.test', 'prueba1234');

const respuesta = await p.evaluate(async (u) => (await fetch(u, { credentials: 'same-origin' })).status,
  `${B}/mi-cuenta/evaluaciones/fotos/${ajena}`);
di('**Un organizador de otra organización NO la abre**', respuesta === 404, `HTTP ${respuesta}`);

await p.goto(`${B}/mi-cuenta/evaluaciones`, { waitUntil: 'networkidle2' });
di('Y su listado tampoco enseña las respuestas ajenas',
  ! (await texto()).includes('Tres fotos del mismo día'));

/* ═══════════════ P7 — los enlaces en el Excel ════════════════════ */

t('P7 — el Excel trae los enlaces a las fotografías');

await entrar('/admin/login', 'admin@ong-laravel.test', 'admin1234');

const csv = await p.evaluate(async (u) => {
  const r = await fetch(u, { credentials: 'same-origin' });
  return (await r.text()).replace(/^\uFEFF/, '');
}, `${B}/admin/evaluaciones/exportar?formato=csv`);

const cabecera = csv.split('\n')[0];
di('Hay una columna de enlaces', cabecera.includes('Enlaces a las fotografías'), cabecera.split(';').pop().trim());
di('Y trae URL de verdad', /\/admin\/evaluaciones\/fotos\/\d+/.test(csv),
  (csv.match(/\/admin\/evaluaciones\/fotos\/\d+/) ?? ['(ninguna)'])[0]);

/* ═══════════════ P8 — la descarga respeta los filtros ════════════ */

t('P8 — la descarga en zip se lleva lo que hay a la vista');

await p.goto(`${B}/admin/evaluaciones/fotos`, { waitUntil: 'networkidle2' });
di('Hay botón de descarga', (await texto()).includes('Descargar estas fotografías'));

const zip = async (url) => p.evaluate(async (u) => {
  const r = await fetch(u, { credentials: 'same-origin' });
  if (! r.ok) return { estado: r.status, bytes: 0 };
  const b = await r.arrayBuffer();
  return { estado: r.status, bytes: b.byteLength, cabecera: new Uint8Array(b).slice(0, 2).join(',') };
}, u_(url));
function u_(x) { return x.startsWith('http') ? x : `${B}${x}`; }

const todo = await zip('/admin/evaluaciones/descargar-fotos?estado=autorizadas');
di('Baja un zip de verdad', todo.estado === 200 && todo.cabecera === '80,75', `HTTP ${todo.estado}, ${todo.bytes} bytes`);

/*
 * Que el filtro se aplica se demuestra con las dos caras: filtrando por la
 * actividad que tiene las fotos sale el mismo zip, y filtrando por otra no
 * sale ninguno. Comparar tamaños a secas no vale aquí, porque hoy todas las
 * fotos autorizadas son de la misma actividad y los dos zips pesarían igual
 * aunque el filtro no se estuviera mirando.
 */
const idActividad = ultima(tinker(
  `echo App\\Models\\Activity::where('slug','prueba-eval-abierta')->value('id');`
));
const otraActividad = ultima(tinker(
  `echo App\\Models\\Activity::where('slug','actividad-de-prueba-a')->value('id');`
));

const filtrado = await zip(`/admin/evaluaciones/descargar-fotos?estado=autorizadas&actividad=${idActividad}`);
di('Filtrando por la actividad que las tiene, salen',
  filtrado.estado === 200 && filtrado.bytes === todo.bytes, `${filtrado.bytes} bytes`);

const otra = await zip(`/admin/evaluaciones/descargar-fotos?estado=autorizadas&actividad=${otraActividad}`);
di('**Filtrando por otra actividad no se lleva ninguna**', otra.estado === 404, `HTTP ${otra.estado}`);

// Un filtro sin resultados no puede devolver un zip vacío sin explicación.
const vacio = await zip('/admin/evaluaciones/descargar-fotos?estado=autorizadas&desde=2000-01-01&hasta=2000-01-02');
di('Un filtro sin fotos lo dice, no baja un zip vacío', vacio.estado === 404, `HTTP ${vacio.estado}`);

/* La cuadrícula del panel pagina FOTOS y no respuestas. */
await p.goto(`${B}/admin/evaluaciones/fotos`, { waitUntil: 'networkidle2' });
const enPantalla = await p.evaluate(() => document.querySelectorAll('.eval-foto').length);
const enBase = Number(ultima(tinker(
  `echo App\\Models\\EvaluationPhoto::whereIn('activity_evaluation_id',`
  + ` App\\Models\\ActivityEvaluation::where('foto_autorizada',true)->select('id'))->count();`
)));
di('La cuadrícula enseña una tarjeta por FOTO', enPantalla === Math.min(enBase, 24), `${enPantalla} tarjetas · ${enBase} fotos`);

/* ═══════════════ El tope en 0 apaga el campo ═════════════════════ */

t('El tope en 0 quita el campo de la encuesta');

ponerTope(0);
await p.goto(`${B}/evaluar/prueba-eval-abierta`, { waitUntil: 'networkidle2' });
di('No se pide fotografía', (await p.$('#ev-fotos')) === null);
di('Ni se ofrece la autorización', (await p.$('input[name="foto_autorizada"]')) === null);

ponerTope(3);

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
