// Los botones que disparan una descarga vuelven a su estado al terminar. En Chrome.
//
// EL BUG (04/10): «Descargar seleccionadas» se quedaba girando para siempre
// aunque el zip ya estuviera descargado. Una descarga no navega, y el estado de
// carga sólo se soltaba al navegar (o, en los exportadores, a los 4 s fijos,
// hubiera terminado o no). Ahora cada descarga lleva un testigo y el servidor
// devuelve una cookie con él cuando el archivo sale (`AvisaDescargaLista`).
//
// Se pulsa CADA botón de descarga del sitio y se comprueba, de cada uno:
//   · que se marca como ocupado al pulsarlo;
//   · que el archivo llega de verdad a la carpeta de descargas;
//   · que el botón se suelta solo, y por la cookie (en segundos), no por la
//     espera máxima de dos minutos.
//
// Siembra una organización con una actividad y dos fotos, y la borra al final.
//
//   node pruebas/botones-descarga.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

if (!/127\.0\.0\.1|localhost|\.test/.test(B)) {
  console.log('Esta prueba siembra datos: no se corre contra producción.');
  process.exit(1);
}

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').pop();

const SELLO = Date.now();
const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

const ids = JSON.parse(tinker(
  `$d = 'evaluaciones/pruebas-botones-${SELLO}'; Illuminate\\Support\\Facades\\Storage::disk('local')->put($d.'/f.png', base64_decode('${PNG}'));`
  + ` $o = App\\Models\\Organization::create(['nombre' => 'Org Botones ${SELLO}', 'slug' => 'botones-${SELLO}', 'activo' => true]);`
  + ` $a = new App\\Models\\Activity; $a->forceFill(['organization_id' => $o->id, 'titulo' => 'Botones ${SELLO}', 'descripcion' => 'x',`
  + ` 'slug' => 'botones-${SELLO}', 'estado' => 'publicada', 'published_at' => now(), 'formato' => 'Presencial'])->save();`
  + ` $e = App\\Models\\ActivityEvaluation::create(['activity_id' => $a->id, 'nombre' => 'Ana ${SELLO}', 'correo' => 'ana${SELLO}@ejemplo.cl',`
  + ` 'experiencia' => 5, 'motivacion' => 5, 'significado' => 'x', 'foto_autorizada' => true]);`
  + ` $f1 = $e->fotos()->create(['ruta' => $d.'/f.png', 'orden' => 1])->id; $e->fotos()->create(['ruta' => $d.'/f.png', 'orden' => 2]);`
  + ` $mia = App\\Models\\Activity::whereHas('organization.user', fn ($q) => $q->where('email', '${ORG}'))->where('estado', 'publicada')->conInscripcion()->value('id');`
  + ` echo json_encode(['dir' => $d, 'org' => $o->id, 'act' => $a->id, 'foto' => $f1, 'mia' => $mia]);`
));

const limpiar = () => tinker(
  `$o = App\\Models\\Organization::find(${ids.org}); foreach ($o?->activities ?? [] as $a) { foreach (App\\Models\\ActivityEvaluation::where('activity_id', $a->id)->get() as $e) { $e->fotos()->delete(); $e->delete(); } $a->statusLogs()->delete(); $a->forceDelete(); }`
  + ` $o?->forceDelete(); Illuminate\\Support\\Facades\\Storage::disk('local')->deleteDirectory('${ids.dir}'); echo 'LIMPIO';`
);

const CARPETA = mkdtempSync(join(tmpdir(), 'dps-descargas-'));
const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const encontrados = [];

/** Una pestaña con las descargas a la carpeta de la prueba. */
const pestana = async (contexto) => {
  const p = await contexto.newPage();
  p.on('pageerror', (e) => errores.push(String(e)));
  await p.setViewport({ width: 1440, height: 1000 });
  // Por el navegador y con el id del contexto: desde la pestaña, en un
  // contexto aislado, Chrome ignora la carpeta y no guarda nada. Y con un
  // nombre por descarga: bajar dos veces el mismo zip lo sobrescribiría.
  const cdp = await nav.target().createCDPSession();
  await cdp.send('Browser.setDownloadBehavior', { behavior: 'allowAndName', downloadPath: CARPETA, browserContextId: contexto.id });
  return p;
};

const archivos = () => readdirSync(CARPETA).filter((f) => !f.endsWith('.crdownload'));

/**
 * Pulsa un disparador y lo sigue hasta que se suelta.
 * `selector` se busca en la página; `cual` elige entre varios iguales.
 */
const probar = async (p, nombre, selector, cual = 0) => {
  const antes = archivos().length;
  const existe = await p.evaluate((s, i) => {
    const el = document.querySelectorAll(s)[i];
    if (!el) return false;
    el.scrollIntoView({ block: 'center' });
    el.click();
    return true;
  }, selector, cual);

  if (!existe) {
    di(`${nombre}: el botón existe`, false, selector);
    return;
  }

  encontrados.push(nombre);
  await esperar(60);

  const elemento = async () => p.evaluateHandle((s, i) => document.querySelectorAll(s)[i], selector, cual);
  const ocupado = async () => p.evaluate((el) => el?.classList.contains('esta-cargando') ?? false, await elemento());

  const seMarco = await ocupado();
  const t0 = Date.now();
  while ((await ocupado()) && Date.now() - t0 < 30000) await esperar(150);
  const tardo = (Date.now() - t0) / 1000;

  // El archivo, por si tarda en cerrarse en disco.
  const t1 = Date.now();
  while (archivos().length <= antes && Date.now() - t1 < 8000) await esperar(150);

  di(`${nombre}: se marca al pulsar`, seMarco);
  di(`${nombre}: el archivo llega`, archivos().length > antes, archivos().slice(antes).join(', ').slice(0, 50));
  di(`${nombre}: se suelta solo, en segundos`, !(await ocupado()) && tardo < 20, `${tardo.toFixed(1)} s`);
};

try {
  const admin = await nav.createBrowserContext();
  const p = await pestana(admin);

  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', ADMIN);
  await p.type('input[type="password"]', CLAVE_ADMIN);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  t('Fotografías de las evaluaciones');
  await p.goto(`${B}/admin/evaluaciones/fotos?q=${SELLO}`, { waitUntil: 'networkidle2' });
  await probar(p, 'Descargar estas fotografías', '[data-descargar-todas]');
  await p.click(`[data-marcar-foto][value="${ids.foto}"]`);
  await esperar(200);
  await probar(p, 'Descargar seleccionadas', '[data-descargar-seleccion]');
  di('Descargar seleccionadas: se puede volver a pulsar', await p.$eval('[data-descargar-seleccion]',
    (b) => getComputedStyle(b).pointerEvents !== 'none' && ! b.disabled));
  await probar(p, 'Descargar seleccionadas (segunda vez)', '[data-descargar-seleccion]');
  await probar(p, 'Descargar las de esta actividad', '[data-zip-actividad]');

  for (const [pantalla, ruta] of [
    ['Evaluaciones', '/admin/evaluaciones'],
    ['Organizaciones', '/admin/organizaciones'],
    ['Regiones', '/admin/regiones'],
    ['Taxonomías', '/admin/taxonomias'],
    ['Contenido (testimonios)', '/admin/contenido/testimonios'],
  ]) {
    t(`Exportar: ${pantalla}`);
    await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
    await probar(p, `${pantalla} XLSX`, '.panel-filtros-exportar a', 0);
    await probar(p, `${pantalla} CSV`, '.panel-filtros-exportar a', 1);
  }

  t('Exportar inscripciones');
  await p.goto(`${B}/admin/inscripciones/exportar`, { waitUntil: 'networkidle2' });
  if (await p.$('a[data-descarga]')) {
    await probar(p, 'Inscripciones: Descargar en Excel', 'a[data-descarga]');
  } else {
    console.log('  (no hay inscripciones en la base local: el botón no se pinta)');
  }

  t('QR de la actividad, en el panel');
  await p.goto(`${B}/admin/actividades/${ids.act}`, { waitUntil: 'networkidle2' });
  await probar(p, 'QR panel SVG', '.qr-botones a', 0);
  await probar(p, 'QR panel PNG', '.qr-botones a', 1);

  t('Un enlace que NO descarga sigue navegando normal');
  await p.goto(`${B}/admin/evaluaciones/fotos?q=${SELLO}`, { waitUntil: 'networkidle2' });
  const antesDeNavegar = p.url();
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => [...document.querySelectorAll('a.btn')].find((a) => /Ver respuestas/.test(a.textContent)).click())]);
  di('«Ver respuestas» lleva a su página', p.url() !== antesDeNavegar && /\/admin\/evaluaciones/.test(p.url()), p.url().replace(B, ''));

  t('Lo mismo, como organizador');
  const org = await nav.createBrowserContext();
  const q = await pestana(org);
  await q.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await q.type('[name="email"]', ORG);
  await q.type('[name="password"]', CLAVE_ORG);
  await Promise.all([q.waitForNavigation(), q.click('button[type="submit"]')]);

  if (ids.mia) {
    await q.goto(`${B}/mi-cuenta/actividades/${ids.mia}/participantes`, { waitUntil: 'networkidle2' });
    await probar(q, 'Participantes: Exportar lista', 'a[data-descarga]');
    await q.goto(`${B}/mi-cuenta/actividades/${ids.mia}/editar`, { waitUntil: 'networkidle2' });
    await probar(q, 'QR mi-cuenta SVG', '.qr-botones a', 0);
    await probar(q, 'QR mi-cuenta PNG', '.qr-botones a', 1);
  } else {
    console.log('  (el organizador sembrado no tiene una actividad publicada con inscripción)');
  }
} finally {
  console.log('');
  console.log(' ', limpiar());
  rmSync(CARPETA, { recursive: true, force: true });
}

t('Sin errores de JavaScript');
di('La consola quedó limpia', errores.length === 0, errores.slice(0, 2).join(' · '));

console.log('');
console.log(`  Botones de descarga probados: ${encontrados.length}`);
encontrados.forEach((n) => console.log(`    · ${n}`));
console.log('');
console.log(`${ok} bien · ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
