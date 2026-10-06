// Ajustes del 06/10, punto 2 — la imagen predeterminada de las actividades.
//
// Se sube desde Configuración → General con el selector de la biblioteca de
// medios, y se usa en toda actividad que no trae la suya: ficha (escritorio y
// teléfono), tarjeta del listado, la imagen al compartir (og:image) y la
// imagen de difusión. Se reemplaza y se quita (vuelve el banner de siempre).
// Una actividad con imagen propia no cambia. Y la biblioteca sabe que está en
// uso, para no dejar borrarla sin avisar.
//
// Sube sus propias imágenes, siembra una actividad sin imagen y lo deja todo
// como estaba.
//
//   node pruebas/imagen-predeterminada.mjs      (desde la raíz del repo)
//
// Contra producción NO: sube archivos y cambia un ajuste.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => (s.split('\n').filter((l) => l.trim()).pop() ?? '').trim();

const SELLO = Date.now();
const BANNER = 'img/dps-banner-2560x1080-010726.jpg';

// Dos imágenes de verdad, fabricadas con GD, para subir y reemplazar.
const fabricar = (nombre, color) => {
  const ruta = join(tmpdir(), nombre).replace(/\\/g, '/');
  tinker(`$i = imagecreatetruecolor(1200, 600); imagefill($i, 0, 0, imagecolorallocate($i, ${color})); imagepng($i, '${ruta}'); echo 'OK';`);
  return ruta;
};
const UNA = fabricar(`predeterminada-una-${SELLO}.png`, '20, 120, 200');
const OTRA = fabricar(`predeterminada-otra-${SELLO}.png`, '200, 60, 20');

const ajusteAntes = ultima(tinker(`echo (string) App\\Models\\Setting::get('actividad_imagen_defecto');`));
const ids = JSON.parse(ultima(tinker(
  `$b = App\\Models\\Activity::where('estado','publicada')->whereNotNull('imagen_portada')->where('cerrada', false)->firstOrFail();`
  + ` $a = $b->replicate(); $a->forceFill(['titulo' => 'Sin imagen ${SELLO}', 'slug' => 'sin-imagen-${SELLO}', 'imagen_portada' => null, 'destacada' => false,`
  + ` 'fecha_inicio' => now()->addDays(30)->toDateString(), 'fecha_termino' => null, 'sin_fecha_definida' => false])->save(); $a->terms()->sync($b->terms->pluck('id'));`
  + ` echo json_encode(['sin' => $a->id, 'con' => $b->id, 'conImagen' => $b->imagen_portada,`
  + ` 'fichaSin' => route('activities.show', $a, false), 'fichaCon' => route('activities.show', $b, false)]);`)));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const pagina = async (ctx) => { const p = await ctx.newPage(); p.on('pageerror', (e) => errores.push(String(e))); await p.setViewport({ width: 1440, height: 900 }); return p; };

const ctxA = await nav.createBrowserContext();
const a = await pagina(ctxA);

// Lo que pinta la ficha: la imagen grande y el og:image.
const ficha = async (p, url, ancho = 1440) => {
  await p.setViewport({ width: ancho, height: 900, isMobile: ancho < 760 });
  await p.goto(`${B}${url}`, { waitUntil: 'networkidle2' });
  return p.evaluate(() => ({
    // La imagen grande de la ficha: la que lleva de `alt` el título (h1). Va
    // con carga diferida, así que se lee su `src` sin esperar a que cargue.
    img: [...document.querySelectorAll('img')].find((i) => i.alt && i.alt === document.querySelector('h1')?.textContent.trim())?.getAttribute('src') ?? '',
    og: document.querySelector('meta[property="og:image"]')?.content ?? '',
  }));
};
const ajuste = () => ultima(tinker(`echo (string) App\\Models\\Setting::get('actividad_imagen_defecto');`));

const subir = async (archivo) => {
  await a.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
  const caja = await a.$('input[type="hidden"][name="actividad_imagen_defecto"]');
  const raiz = await caja.evaluateHandle((i) => i.closest('.campo-medio'));
  await (await raiz.$('button')).click();   // «Elegir imagen» o «Cambiar»
  await esperar(600);
  const antes = await caja.evaluate((i) => i.value);
  const input = await raiz.$('input[type="file"]');
  await input.uploadFile(archivo);
  // Hasta que el campo tenga la ruta NUEVA: al reemplazar ya traía otra.
  await a.waitForFunction((i, v) => i.value.startsWith('storage/') && i.value !== v, { timeout: 20000 }, caja, antes);
  await Promise.all([a.waitForNavigation({ waitUntil: 'networkidle2' }), a.evaluate(() => [...document.querySelectorAll('button[type="submit"]')].find((b) => b.textContent.trim() === 'Guardar').click())]);
};

try {
  await a.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await a.type('input[type="email"]', ADMIN);
  await a.type('input[type="password"]', CLAVE_ADMIN);
  await Promise.all([a.waitForNavigation({ waitUntil: 'networkidle2' }), a.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  t('0 · Sin imagen predeterminada: el banner de siempre');
  tinker(`App\\Models\\Setting::set('actividad_imagen_defecto', ''); echo 'OK';`);
  const anon = await nav.createBrowserContext();
  const q = await pagina(anon);
  let f = await ficha(q, ids.fichaSin);
  di('la ficha sin imagen usa el banner', f.img.endsWith(BANNER), f.img);

  t('1 · Configuración → General');
  await a.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
  const texto = await a.evaluate(() => document.body.innerText);
  di('está el campo «Imagen predeterminada de las actividades»', texto.includes('Imagen predeterminada de las actividades'));
  di('con el selector de la biblioteca (subir, cambiar, quitar)', !! (await a.$('.campo-medio input[name="actividad_imagen_defecto"]')));

  await subir(UNA);
  const primera = ajuste();
  di('**subir una y guardar la deja puesta**', primera.startsWith('storage/medios/'), primera);

  t('2 · Se usa donde la actividad no trae la suya');
  f = await ficha(q, ids.fichaSin);
  di('**ficha en escritorio**', f.img.endsWith(primera), f.img);
  di('la imagen al compartir (og:image)', f.og.endsWith(primera), f.og);
  f = await ficha(q, ids.fichaSin, 390);
  di('**ficha en el teléfono**', f.img.endsWith(primera), f.img);
  await q.setViewport({ width: 1440, height: 900 });
  await q.goto(`${B}/actividades?q=${encodeURIComponent(`Sin imagen ${SELLO}`)}`, { waitUntil: 'networkidle2' });
  const tarjeta = await q.evaluate(() => document.querySelector('img.act-img')?.getAttribute('src') ?? '');
  di('la tarjeta del listado', tarjeta.endsWith(primera), tarjeta);
  const conPropia = await ficha(q, ids.fichaCon);
  di('una actividad con imagen propia no cambia', conPropia.img.endsWith(ids.conImagen), conPropia.img);

  // La imagen de difusión, como la genera su organización.
  const ctxO = await nav.createBrowserContext();
  const o = await pagina(ctxO);
  await o.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await o.type('input[type="email"]', ORG);
  await o.type('input[type="password"]', CLAVE_ORG);
  await Promise.all([o.waitForNavigation({ waitUntil: 'networkidle2' }), o.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
  const orgDeLaActividad = ultima(tinker(`echo App\\Models\\Activity::find(${ids.sin})->organization?->user?->email;`));
  if (orgDeLaActividad === ORG) {
    await o.goto(`${B}/mi-cuenta/actividades/${ids.sin}/difusion`, { waitUntil: 'networkidle2' });
    await o.waitForFunction(() => ['lista', 'error'].includes(Alpine.$data(document.querySelector('.difusion'))?.estado), { timeout: 20000 });
    const foto = await o.evaluate(() => Alpine.$data(document.querySelector('.difusion')).dibujo?.foto);
    di('la imagen de difusión la usa de foto', String(foto ?? '').endsWith(primera) || foto === true, String(foto));
  } else {
    di('la imagen de difusión la usa de foto (actividad del organizador sembrado)', false, `es de ${orgDeLaActividad}`);
  }
  await ctxO.close();

  t('3 · La biblioteca sabe que está en uso');
  const medio = ultima(tinker(`echo App\\Models\\Media::where('ruta', '${primera}')->value('id');`));
  const usos = ultima(tinker(`echo json_encode(app(App\\Services\\Biblioteca::class)->usos(App\\Models\\Media::find(${medio})));`));
  di('«dónde se usa» dice Configuración', usos.includes('Configuraci') && usos.includes('Imagen predeterminada'), usos);

  t('4 · Se reemplaza');
  await subir(OTRA);
  const segunda = ajuste();
  di('**cambiarla deja la nueva**', segunda.startsWith('storage/medios/') && segunda !== primera, segunda);
  f = await ficha(q, ids.fichaSin);
  di('y la ficha la usa', f.img.endsWith(segunda), f.img);

  t('5 · Se quita');
  await a.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
  await a.evaluate(() => [...document.querySelector('input[name="actividad_imagen_defecto"]').closest('.campo-medio').querySelectorAll('button')].find((b) => b.textContent.trim() === 'Quitar').click());
  await esperar(200);
  await Promise.all([a.waitForNavigation({ waitUntil: 'networkidle2' }), a.evaluate(() => [...document.querySelectorAll('button[type="submit"]')].find((b) => b.textContent.trim() === 'Guardar').click())]);
  di('**quitarla deja el ajuste vacío**', ajuste() === '', ajuste());
  f = await ficha(q, ids.fichaSin);
  di('y vuelve el banner de siempre', f.img.endsWith(BANNER), f.img);

  t('6 · Una ruta de fuera no se guarda');
  const r = await a.evaluate(async () => {
    const fd = new FormData(document.querySelector('form[action$="/admin/configuracion"]'));
    fd.set('actividad_imagen_defecto', 'https://otro-sitio.cl/foto.jpg');
    return (await fetch('/admin/configuracion', { method: 'POST', body: fd })).status;
  });
  di('un dominio ajeno se descarta', ajuste() === '', `${r} · «${ajuste()}»`);
  await anon.close();

  t('Consola');
  di('sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  await nav.close();
  console.log('  ' + ultima(tinker(
    `App\\Models\\Setting::set('actividad_imagen_defecto', '${ajusteAntes}');`
    + ` foreach (App\\Models\\Media::where('ruta', 'like', '%predeterminada-%-${SELLO}%')->get() as $m) { $m->borrarArchivo(); $m->delete(); }`
    + ` $a = App\\Models\\Activity::find(${ids.sin}); if ($a) { $a->terms()->detach(); $a->forceDelete(); } echo 'LIMPIO';`)));
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
