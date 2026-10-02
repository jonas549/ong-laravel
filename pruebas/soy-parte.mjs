// Punto 7 de la tanda del 30/09: «Soy parte del DPS» al terminar de
// inscribirse. En Chrome.
//
// Se inscribe de verdad en una actividad y comprueba la pantalla que sigue:
// la confirmación, la imagen fija, descargar, compartir (copiar en el
// escritorio, el diálogo nativo en el teléfono simulado), el texto del post
// con el enlace de APP_URL y el kit de difusión de Configuración → General.
//
//   node pruebas/soy-parte.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').pop();

const SELLO = Date.now();
const CORREO = `participante.${SELLO}@ejemplo.cl`;
const KIT = 'https://drive.google.com/drive/folders/kit-de-prueba';
const kitAntes = tinker("echo App\\Models\\Setting::where('clave','kit_difusion_url')->value('valor') ?? '';");
const APP_URL = tinker("echo rtrim(config('app.url'), '/');");
const TEXTO = `Me sumo a la celebración nacional del Día del Patrimonio Social. ¡Súmate tú también! ${APP_URL}`;

const ajustarKit = (v) => tinker(`App\\Models\\Setting::where('clave','kit_difusion_url')->update(['valor' => ${v === null ? 'null' : `'${v}'`}]);`
  + ` Illuminate\\Support\\Facades\\Cache::forget(App\\Models\\Setting::CACHE_KEY); echo 'ok';`);

const ids = JSON.parse(tinker(
  `$o = App\\Models\\Organization::create(['user_id' => null, 'nombre' => 'Org Soy Parte ${SELLO}', 'slug' => 'soy-parte-${SELLO}', 'tipo' => 'Organización sin fines de lucro', 'activo' => true]);`
  + ` $mk = function ($t, $estado) use ($o) { $a = new App\\Models\\Activity; $a->forceFill(['organization_id' => $o->id, 'titulo' => $t, 'descripcion' => 'Prueba.',`
  + ` 'slug' => Illuminate\\Support\\Str::slug($t), 'estado' => $estado, 'published_at' => $estado === 'publicada' ? now() : null, 'formato' => 'Presencial',`
  + ` 'inscripcion_habilitada' => true, 'abierta_publico' => true, 'cupos_totales' => 20, 'cupos_disponibles' => 20, 'fecha_inicio' => now()->addDays(20)->toDateString()])->save(); return $a; };`
  + ` $pub = $mk('Actividad soy parte ${SELLO}', 'publicada'); $rev = $mk('Actividad en revision ${SELLO}', 'revision');`
  + ` echo json_encode(['org' => $o->id, 'pub' => $pub->slug, 'pubId' => $pub->id, 'rev' => $rev->slug]);`
));
ajustarKit(KIT);

const limpiar = () => tinker(
  `$o = App\\Models\\Organization::find(${ids.org}); foreach ($o?->activities()->withTrashed()->get() ?? [] as $a) { $a->registrations()->forceDelete(); $a->statusLogs()->delete(); $a->forceDelete(); }`
  + ` $o?->forceDelete(); echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
await nav.defaultBrowserContext().overridePermissions(B, ['clipboard-read', 'clipboard-write', 'clipboard-sanitized-write']);
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

try {
  t('1 · Inscribirse lleva a la pantalla nueva');
  await p.goto(`${B}/actividades/${ids.pub}`, { waitUntil: 'networkidle2' });
  await p.type('#r-nombre', 'Participante de Prueba');
  await p.type('#r-correo', CORREO);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('form[action*="inscribirse"] button[type=submit]')]);
  di('llega a /soy-parte', p.url().endsWith(`/actividades/${ids.pub}/soy-parte`), p.url().replace(B, ''));
  const insc = tinker(`echo App\\Models\\Registration::where('correo','${CORREO}')->count();`);
  di('la inscripción quedó guardada', insc === '1', insc);
  const conf = await p.evaluate(() => document.querySelector('[data-confirmacion-inscripcion]')?.innerText ?? '');
  di('confirma la inscripción', conf.includes('Listo, guardamos tu inscripción'), conf.slice(0, 60));
  di('con el nombre de la actividad y su correo', conf.includes(`Actividad soy parte ${SELLO}`) && conf.includes(CORREO));

  t('2 · La imagen y descargarla');
  await p.waitForFunction(() => Alpine.$data(document.querySelector('.difusion'))?.archivo !== null, { timeout: 8000 }).catch(() => {});
  const img = await p.evaluate(async () => { const i = document.querySelector('[data-imagen-soy-parte]'); await i.decode(); return [i.naturalWidth, i.naturalHeight]; });
  di('la imagen se ve, a 1080×1350', img[0] === 1080 && img[1] === 1350, img.join('×'));
  const desc = await p.$eval('[data-descargar]', (a) => ({ href: a.getAttribute('href'), download: a.getAttribute('download') }));
  di('«Descargar imagen» es un enlace al archivo, con nombre propio', desc.href.endsWith('/img/soy-parte-del-dps.jpg') && desc.download.endsWith('.jpg'), JSON.stringify(desc));
  const r = await p.evaluate(async (h) => (await fetch(h)).status, desc.href);
  di('y el archivo existe', r === 200, String(r));

  t('3 · Compartir en el escritorio: copia la imagen');
  const btn = await p.$eval('[data-compartir]', (b) => ({ texto: b.textContent.trim(), visible: b.getBoundingClientRect().height > 0 }));
  di('el botón está y dice «Copiar imagen»', btn.visible && btn.texto === 'Copiar imagen', JSON.stringify(btn));
  await p.click('[data-compartir]');
  await esperar(800);
  const copia = await p.evaluate(async () => {
    const it = await navigator.clipboard.read();
    const tipo = it[0]?.types.find((x) => x.startsWith('image/'));
    const b = await it[0].getType(tipo);
    const bm = await createImageBitmap(b);
    return { tipo, w: bm.width, h: bm.height, aviso: document.querySelector('[data-aviso-compartir]')?.innerText };
  }).catch((e) => ({ error: String(e) }));
  di('la imagen queda en el portapapeles', copia.w === 1080 && copia.h === 1350, JSON.stringify(copia));
  di('y lo dice en pantalla', /copiada/i.test(copia.aviso ?? ''), copia.aviso);

  t('4 · El texto del post');
  di('dice el texto pedido, con el enlace al home', (await p.$eval('[data-texto-post]', (e) => e.innerText.trim())) === TEXTO, await p.$eval('[data-texto-post]', (e) => e.innerText.trim()));
  await p.click('[data-copiar-texto]');
  await esperar(300);
  di('«Copiar texto» lo copia', (await p.evaluate(() => navigator.clipboard.readText())) === TEXTO);
  di('y lo confirma en el botón', (await p.$eval('[data-copiar-texto]', (b) => b.innerText.trim())) === 'Texto copiado');

  t('5 · El kit de difusión');
  di('lleva al enlace de Configuración → General, en otra pestaña', await p.$eval('[data-kit]', (a) => a.href === 'https://drive.google.com/drive/folders/kit-de-prueba' && a.target === '_blank'));
  ajustarKit(null);
  await p.goto(`${B}/actividades/${ids.pub}/soy-parte`, { waitUntil: 'networkidle2' });
  di('sin enlace configurado, el botón no se pinta', await p.$('[data-kit]') === null);
  ajustarKit(KIT);

  t('6 · Sin acabar de inscribirse');
  di('sin la confirmación (no es de nadie)', await p.$('[data-confirmacion-inscripcion]') === null);
  di('pero sí la imagen y los botones', await p.$('[data-imagen-soy-parte]') !== null && await p.$('[data-descargar]') !== null);
  const r404 = await p.goto(`${B}/actividades/${ids.rev}/soy-parte`);
  di('una actividad sin publicar da 404', r404.status() === 404, String(r404.status()));

  t('7 · En el teléfono (simulado)');
  const tel = await nav.newPage();
  await tel.evaluateOnNewDocument(() => {
    const mm = window.matchMedia.bind(window);
    window.matchMedia = (q) => (q === '(pointer: coarse)' ? { matches: true, addEventListener() {}, removeEventListener() {} } : mm(q));
    window.__compartido = [];
    navigator.canShare = () => true;
    navigator.share = async (d) => { window.__compartido.push({ archivos: d.files?.map((f) => `${f.name}:${f.type}:${f.size > 0}`), texto: d.text }); };
  });
  await tel.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  await tel.goto(`${B}/actividades/${ids.pub}/soy-parte`, { waitUntil: 'networkidle2' });
  await tel.waitForFunction(() => Alpine.$data(document.querySelector('.difusion'))?.modo === 'compartir', { timeout: 8000 }).catch(() => {});
  di('dice «Compartir»', (await tel.$eval('[data-compartir]', (b) => b.textContent.trim())) === 'Compartir');
  await tel.click('[data-compartir]');
  await esperar(400);
  const comp = await tel.evaluate(() => window.__compartido);
  di('abre el compartir nativo con la imagen', /\.jpg:image\/jpeg:true$/.test(comp[0]?.archivos?.[0] ?? ''), JSON.stringify(comp));
  di('y el texto del post con el enlace', comp[0]?.texto === TEXTO, comp[0]?.texto);
  di('la pantalla no desborda', await tel.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));

  // Recién inscrito desde el teléfono, con un correo largo: la confirmación
  // tiene que caber (se salía por la derecha).
  await tel.goto(`${B}/actividades/${ids.pub}`, { waitUntil: 'networkidle2' });
  await tel.type('#r-nombre', 'Participante con correo largo');
  await tel.type('#r-correo', `un.correo.bastante.largo.${SELLO}@organizacion-de-ejemplo.cl`);
  await Promise.all([tel.waitForNavigation({ waitUntil: 'networkidle2' }), tel.click('form[action*="inscribirse"] button[type=submit]')]);
  const caja = await tel.evaluate(() => {
    const c = document.querySelector('[data-confirmacion-inscripcion]');
    const fuera = [...c.querySelectorAll('*')].filter((e) => e.getBoundingClientRect().right > c.getBoundingClientRect().right + 1);
    return { hay: !! c, derecha: Math.round(c.getBoundingClientRect().right), ancho: window.innerWidth, fuera: fuera.length,
      pagina: document.documentElement.scrollWidth <= window.innerWidth };
  });
  di('con la confirmación y un correo largo, nada se sale', caja.hay && caja.derecha <= caja.ancho && caja.fuera === 0 && caja.pagina, JSON.stringify(caja));
  await tel.close();
  await p.bringToFront();

  t('Consola');
  di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 3).join(' | '));
} catch (err) {
  di('La prueba terminó sin excepciones', false, String(err).slice(0, 200));
} finally {
  await nav.close();
  ajustarKit(kitAntes === '' ? null : kitAntes);
  console.log('');
  console.log(limpiar());
  console.log(`\n${ok} OK · ${mal} MAL`);
  process.exit(mal ? 1 : 0);
}
