// Puntos 8 y 9 de la tanda del 30/09: las fotografías de las evaluaciones en
// el panel. En Chrome.
//
//   8 · Casillas para marcar fotos y «Descargar seleccionadas»: el zip trae
//       sólo las marcadas, y nunca una sin autorizar aunque se cuele su id.
//   9 · Bajar las de una sola actividad: con el filtro (el botón la nombra y el
//       zip lleva su nombre) o con el enlace de cada foto.
//
// Los zip se abren de verdad con ZipArchive y se mira qué fotos traen.
//
//   node pruebas/fotos-seleccion.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { writeFileSync, unlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').pop();

const SELLO = Date.now();
const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

// Dos actividades; en A, dos fotos autorizadas y una sin autorizar; en B, dos autorizadas.
const ids = JSON.parse(tinker(
  `$d = 'evaluaciones/pruebas-zip-${SELLO}'; Illuminate\\Support\\Facades\\Storage::disk('local')->put($d.'/f.png', base64_decode('${PNG}'));`
  + ` $o = App\\Models\\Organization::create(['nombre' => 'Org Fotos ${SELLO}', 'slug' => 'fotos-${SELLO}', 'activo' => true]);`
  + ` $mk = function ($t) use ($o) { $a = new App\\Models\\Activity; $a->forceFill(['organization_id' => $o->id, 'titulo' => $t, 'descripcion' => 'x',`
  + ` 'slug' => Illuminate\\Support\\Str::slug($t), 'estado' => 'publicada', 'published_at' => now(), 'formato' => 'Presencial'])->save(); return $a; };`
  + ` $A = $mk('Taller de fotos A ${SELLO}'); $Bb = $mk('Recorrido fotos B ${SELLO}');`
  + ` $ev = function ($a, $nombre, $aut) { return App\\Models\\ActivityEvaluation::create(['activity_id' => $a->id, 'nombre' => $nombre, 'correo' => Illuminate\\Support\\Str::slug($nombre).'@ejemplo.cl',`
  + ` 'experiencia' => 5, 'motivacion' => 5, 'significado' => 'x', 'foto_autorizada' => $aut]); };`
  + ` $eA = $ev($A, 'Ana ${SELLO}', true); $eA2 = $ev($A, 'Nadia ${SELLO}', false); $eB = $ev($Bb, 'Beto ${SELLO}', true);`
  + ` $f = fn ($e, $n) => $e->fotos()->create(['ruta' => $d.'/f.png', 'orden' => $n])->id;`
  + ` echo json_encode(['dir' => $d, 'org' => $o->id, 'A' => $A->id, 'B' => $Bb->id, 'tituloA' => $A->titulo, 'slugA' => $A->slug,`
  + ` 'a1' => $f($eA, 1), 'a2' => $f($eA, 2), 'a3' => $f($eA2, 1), 'b1' => $f($eB, 1), 'b2' => $f($eB, 2)]);`
));

const limpiar = () => tinker(
  `$o = App\\Models\\Organization::find(${ids.org}); foreach ($o?->activities ?? [] as $a) { foreach (App\\Models\\ActivityEvaluation::where('activity_id', $a->id)->get() as $e) { $e->fotos()->delete(); $e->delete(); } $a->statusLogs()->delete(); $a->forceDelete(); }`
  + ` $o?->forceDelete(); Illuminate\\Support\\Facades\\Storage::disk('local')->deleteDirectory('${ids.dir}'); echo 'LIMPIO';`
);

/** Baja un zip con la sesión del navegador y devuelve los ids de foto que trae y su nombre. */
const abrirZip = async (url) => {
  const r = await p.evaluate(async (u) => {
    const res = await fetch(u);
    const buf = new Uint8Array(await res.arrayBuffer());
    let s = ''; for (const c of buf) s += String.fromCharCode(c);
    return { status: res.status, nombre: res.headers.get('content-disposition') ?? '', b64: btoa(s) };
  }, url);
  if (r.status !== 200) return { status: r.status, fotos: [], nombre: r.nombre };
  const ruta = join(tmpdir(), `fotos-${SELLO}-${Math.random().toString(36).slice(2)}.zip`).replace(/\\/g, '/');
  writeFileSync(ruta, Buffer.from(r.b64, 'base64'));
  const lista = tinker(`$z = new ZipArchive; $z->open('${ruta}'); $n = []; for ($i = 0; $i < $z->numFiles; $i++) $n[] = $z->getNameIndex($i); echo implode('|', $n);`);
  unlinkSync(ruta);
  const fotos = lista.split('|').filter((x) => x !== 'LEEME.txt').map((x) => Number(x.match(/-(\d+)\.\w+$/)?.[1])).sort((a, b) => a - b);
  return { status: 200, fotos, nombre: r.nombre, entradas: lista };
};

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const urlDelFormulario = () => p.evaluate(() => {
  const f = document.querySelector('[data-barra-seleccion]');
  return `${f.action}?${new URLSearchParams(new FormData(f))}`;
});

try {
  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', ADMIN);
  await p.type('input[type="password"]', CLAVE_ADMIN);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  t('8 · Marcar y bajar sólo las marcadas');
  await p.goto(`${B}/admin/evaluaciones/fotos?q=${SELLO}`, { waitUntil: 'networkidle2' });
  const casillas = await p.$$eval('[data-marcar-foto]', (c) => c.map((x) => x.value));
  di('cada foto tiene su casilla (4 autorizadas)', casillas.length === 4, casillas.join(','));
  di('sin marcar nada, el botón está desactivado', await p.$eval('[data-descargar-seleccion]', (b) => b.disabled));

  await p.click(`[data-marcar-foto][value="${ids.a1}"]`);
  await p.click(`[data-marcar-foto][value="${ids.b2}"]`);
  await esperar(200);
  di('al marcar dos, el botón se activa y lo cuenta', await p.evaluate(() => ! document.querySelector('[data-descargar-seleccion]').disabled
    && document.querySelector('[data-barra-seleccion]').innerText.includes('2 seleccionadas')));
  di('las marcadas se distinguen', (await p.$$eval('.eval-foto--marcada', (n) => n.length)) === 2);

  let zip = await abrirZip(await urlDelFormulario());
  di('el zip trae sólo esas dos', JSON.stringify(zip.fotos) === JSON.stringify([ids.a1, ids.b2].sort((a, b) => a - b)), zip.entradas);
  di('y su nombre dice que es una selección', /fotografias-seleccion-/.test(zip.nombre), zip.nombre);

  // Pulsar el botón de verdad no da error (la descarga sale del navegador).
  const resp = p.waitForResponse((r) => r.url().includes('/descargar-fotos'), { timeout: 10000 });
  await p.click('[data-descargar-seleccion]');
  const rr = await resp.catch(() => null);
  di('el botón pide el zip y el servidor lo da', rr?.status() === 200 && /zip/.test(rr?.headers()['content-type'] ?? ''), String(rr?.status()));
  await p.goto(`${B}/admin/evaluaciones/fotos?q=${SELLO}`, { waitUntil: 'networkidle2' });

  await p.click('[data-seleccionar-todas]');
  await esperar(200);
  di('«Seleccionar todas» marca las de la página', (await p.$$eval('[data-marcar-foto]:checked', (n) => n.length)) === 4);
  await p.click('[data-seleccionar-todas]');
  await esperar(200);
  di('y desmarcarla las quita', (await p.$$eval('[data-marcar-foto]:checked', (n) => n.length)) === 0);

  zip = await abrirZip(`${B}/admin/evaluaciones/descargar-fotos?q=${SELLO}&estado=autorizadas&fotos[]=${ids.a1}&fotos[]=${ids.a3}`);
  di('una sin autorizar colada a mano NO entra en el zip de autorizadas', JSON.stringify(zip.fotos) === JSON.stringify([ids.a1]), zip.entradas);

  t('9 · Las de una sola actividad');
  const enlace = await p.$eval(`[data-marcar-foto][value="${ids.a1}"]`, (c) => c.closest('.eval-foto').querySelector('[data-zip-actividad]')?.href ?? '');
  di('cada foto ofrece bajar las de su actividad', enlace.includes(`actividad=${ids.A}`), enlace.replace(B, ''));
  zip = await abrirZip(enlace);
  di('ese zip trae las autorizadas de esa actividad y nada más', JSON.stringify(zip.fotos) === JSON.stringify([ids.a1, ids.a2].sort((a, b) => a - b)), zip.entradas);
  di('y se llama como la actividad', zip.nombre.includes(ids.slugA), zip.nombre);

  await p.goto(`${B}/admin/evaluaciones/fotos?q=${SELLO}&actividad=${ids.A}`, { waitUntil: 'networkidle2' });
  const boton = await p.$eval('[data-descargar-todas]', (a) => ({ texto: a.innerText.trim(), href: a.href }));
  di('con el filtro, el botón nombra la actividad', boton.texto.includes('Taller de fotos A'), boton.texto);
  zip = await abrirZip(boton.href);
  di('y baja sólo las suyas', JSON.stringify(zip.fotos) === JSON.stringify([ids.a1, ids.a2].sort((a, b) => a - b)), zip.entradas);
  di('con el filtro puesto no sobra el enlace de cada foto', await p.$('[data-zip-actividad]') === null);

  await p.goto(`${B}/admin/evaluaciones/fotos?q=${SELLO}&actividad=${ids.A}&estado=sin-autorizar`, { waitUntil: 'networkidle2' });
  zip = await abrirZip(await p.$eval('[data-descargar-todas]', (a) => a.href));
  di('en la pestaña sin autorizar, baja la sin autorizar de esa actividad', JSON.stringify(zip.fotos) === JSON.stringify([ids.a3]), zip.entradas);

  t('Consola');
  di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 3).join(' | '));
} catch (err) {
  di('La prueba terminó sin excepciones', false, String(err).slice(0, 200));
} finally {
  await nav.close();
  console.log('');
  console.log(limpiar());
  console.log(`\n${ok} OK · ${mal} MAL`);
  process.exit(mal ? 1 : 0);
}
