// El botón «Cuenta que eres parte» en el correo de confirmación de
// inscripción (decisión del 02/10 sobre el punto 7 del 30/09). En Chrome.
//
// Se inscribe de verdad, procesa la cola y lee el correo en Mailpit: que traiga
// el botón con el enlace a /soy-parte de esa actividad, que traiga también los
// enlaces del calendario (faltaban en las plantillas creadas antes del 02/09) y
// que el enlace del botón abra la pantalla. No adjunta ninguna imagen.
//
// Necesita Mailpit en el 1025/8025 y la cola en `database`.
//
//   node pruebas/correo-soy-parte.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const MAILPIT = process.env.DPS_MAILPIT ?? 'http://127.0.0.1:8025';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').pop();
const artisan = (...a) => execFileSync(PHP, ['artisan', ...a], { encoding: 'utf8' });

const SELLO = Date.now();
const CORREO = `correo.soyparte.${SELLO}@ejemplo.cl`;

const ids = JSON.parse(tinker(
  `$o = App\\Models\\Organization::create(['nombre' => 'Org Correo ${SELLO}', 'slug' => 'correo-${SELLO}', 'activo' => true]);`
  + ` $a = new App\\Models\\Activity; $a->forceFill(['organization_id' => $o->id, 'titulo' => 'Actividad del correo ${SELLO}', 'descripcion' => 'x',`
  + ` 'slug' => 'actividad-del-correo-${SELLO}', 'estado' => 'publicada', 'published_at' => now(), 'formato' => 'Presencial',`
  + ` 'inscripcion_habilitada' => true, 'abierta_publico' => true, 'fecha_inicio' => now()->addDays(15)->toDateString(), 'hora_inicio' => '10:00'])->save();`
  + ` echo json_encode(['org' => $o->id, 'slug' => $a->slug]);`
));

const limpiar = () => tinker(
  `$o = App\\Models\\Organization::find(${ids.org}); foreach ($o?->activities()->withTrashed()->get() ?? [] as $a) { $a->registrations()->forceDelete(); $a->statusLogs()->delete(); $a->forceDelete(); }`
  + ` $o?->forceDelete(); echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });

try {
  t('Inscribirse y leer el correo');
  await p.goto(`${B}/actividades/${ids.slug}`, { waitUntil: 'networkidle2' });
  await p.type('#r-nombre', 'Persona del Correo');
  await p.type('#r-correo', CORREO);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('form[action*="inscribirse"] button[type=submit]')]);
  di('la inscripción lleva a /soy-parte', p.url().endsWith(`/actividades/${ids.slug}/soy-parte`));

  artisan('queue:work', '--stop-when-empty', '--tries=1');
  let msg = null;
  for (let i = 0; i < 10 && ! msg; i++) {
    const r = await (await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${CORREO}"`)}`)).json();
    msg = r.messages?.find((m) => /Te esperamos/.test(m.Subject));
    if (! msg) await esperar(500);
  }
  di('llega la confirmación', !! msg, msg?.Subject);
  const completo = msg ? await (await fetch(`${MAILPIT}/api/v1/message/${msg.ID}`)).json() : {};
  const html = completo.HTML ?? '';
  const enlace = html.match(/href="([^"]*\/soy-parte)"[^>]*>\s*Cuenta que eres parte/)?.[1]?.replace(/&amp;/g, '&');
  di('trae el botón «Cuenta que eres parte»', !! enlace, enlace);
  di('que lleva a /soy-parte de esa actividad', enlace?.endsWith(`/actividades/${ids.slug}/soy-parte`));
  di('sin adjuntos', (completo.Attachments ?? []).length === 0 && (completo.Inline ?? []).length === 0,
    `${(completo.Attachments ?? []).length} adjuntos, ${(completo.Inline ?? []).length} incrustados`);
  di('y con los enlaces del calendario', /calendar\.google\.com|calendario\.ics/.test(html));
  di('ningún marcador sin sustituir', ! /\{\{\s*bloque_/.test(html));

  t('El enlace del correo abre la pantalla');
  const destino = enlace?.replace(/^https?:\/\/[^/]+/, B);
  await p.deleteCookie(...(await p.cookies()));
  const r = await p.goto(destino, { waitUntil: 'networkidle2' });
  di('responde 200 sin sesión', r.status() === 200, String(r.status()));
  di('con la imagen y los botones', await p.$('[data-imagen-soy-parte]') !== null && await p.$('[data-descargar]') !== null);
  di('y sin la confirmación (no es la sesión de quien se inscribió)', await p.$('[data-confirmacion-inscripcion]') === null);
} catch (err) {
  di('La prueba terminó sin excepciones', false, String(err).slice(0, 200));
} finally {
  await nav.close();
  console.log('');
  console.log(limpiar());
  console.log(`\n${ok} OK · ${mal} MAL`);
  process.exit(mal ? 1 : 0);
}
