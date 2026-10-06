// Tanda del 05/10 — los tres avisos al equipo, por la interfaz de verdad.
//
// Una organización nueva publica por el wizard (queda en revisión: aviso de
// «nueva en revisión»), la ONG la aprueba, la organización la edita dos veces
// desde mi-cuenta (un solo aviso de «editada»), y publica una segunda que sale
// sola (aviso de «publicada sin revisión»). Corre la cola y lee en Mailpit lo
// que llegó al buzón de avisos, que durante la prueba es uno propio para no
// mezclarse con otros correos.
//
// Necesita Mailpit en el 1025/8025 y la cola en `database`.
//
//   node pruebas/avisos-equipo.mjs
//
// Contra producción NO: publica actividades y manda correos.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const MAILPIT = process.env.DPS_MAILPIT ?? 'http://127.0.0.1:8025';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();
const artisan = (...a) => execFileSync(PHP, ['artisan', ...a], { encoding: 'utf8' });

const SELLO = Date.now();
const CORREO = `avisos.${SELLO}@ejemplo.cl`;
const CLAVE = 'avisos-equipo-2026';
const ORG = `Fundación Avisos ${SELLO}`;
const BUZON = `equipo.${SELLO}@ejemplo.cl`;
const PRIMERA = `Primera con aviso ${SELLO}`;
const SEGUNDA = `Segunda con aviso ${SELLO}`;

const buzonAntes = ultima(tinker(`echo App\\Models\\Setting::get('avisos_email');`));
tinker(`App\\Models\\Setting::set('avisos_email', '${BUZON}'); cache()->flush(); echo 'OK';`);

tinker(
  `$u = new App\\Models\\User(['name' => 'Persona Avisos', 'email' => '${CORREO}', 'password' => bcrypt('${CLAVE}')]);`
  + ` $u->forceFill(['role' => App\\Models\\User::ROL_ORGANIZER, 'is_active' => true, 'email_verified_at' => now()])->save();`
  + ` App\\Models\\Organization::create(['user_id' => $u->id, 'nombre' => '${ORG}', 'slug' => 'avisos-'.$u->id, 'activo' => true,`
  + ` 'tipo' => 'Organización sin fines de lucro', 'logo_path' => 'storage/organizaciones/prueba.png']); echo 'OK';`
);

const limpiar = () => tinker(
  `foreach (['${PRIMERA}', '${SEGUNDA}'] as $t) { $a = App\\Models\\Activity::where('titulo', $t)->first(); if ($a) { $a->statusLogs()->delete(); $a->forceDelete(); } }`
  + ` $u = App\\Models\\User::where('email','${CORREO}')->first(); if ($u) { $u->organization?->forceDelete(); $u->delete(); }`
  + ` App\\Models\\AccessLog::where('email','${CORREO}')->delete();`
  + ` App\\Models\\Setting::set('avisos_email', '${buzonAntes}'); cache()->flush(); echo 'LIMPIO';`
);

const alBuzon = async () => {
  const r = await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${BUZON}"`)}`);
  return (await r.json()).messages ?? [];
};
const html = async (m) => (await (await fetch(`${MAILPIT}/api/v1/message/${m.ID}`)).json()).HTML;
const id = (titulo) => ultima(tinker(`echo App\\Models\\Activity::where('titulo','${titulo}')->value('id');`));
const estado = (titulo) => ultima(tinker(`echo App\\Models\\Activity::where('titulo','${titulo}')->value('estado');`));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const publicar = async (titulo) => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => b.innerText.includes('No, solo quiero difundir')).click());
  await esperar(300);
  await p.type('input[name="titulo"]', titulo);
  await p.type('textarea[name="descripcion"]', 'Sembrada por pruebas/avisos-equipo.mjs.');
  await p.evaluate(() => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    for (const grupo of ['temas', 'caracteristicas', 'publicos']) {
      const b = [...document.querySelectorAll('button')].find((x) => x.getAttribute('x-on:click')?.startsWith(`alternar('${grupo}'`));
      if (b && d.sel[grupo].length === 0) b.click();
    }
    const casilla = document.querySelector('input[name="sin_fecha_definida"]');
    if (! casilla.checked) casilla.click();
  });
  await esperar(300);
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => null),
    p.evaluate(() => [...document.querySelectorAll('button[type="submit"]')].find((b) => /Publicar|Enviar/i.test(b.textContent)).click()),
  ]);
};

const guardarEdicion = async (aid, sufijo) => {
  await p.goto(`${B}/mi-cuenta/actividades/${aid}/editar`, { waitUntil: 'networkidle2' });
  await p.$eval('textarea[name="descripcion"]', (el, s) => { el.value += s; el.dispatchEvent(new Event('input', { bubbles: true })); }, sufijo);
  await Promise.all([p.waitForNavigation({ timeout: 20000 }), p.click(`form[action$="/${aid}"] button[type="submit"]`)]);
};

try {
  await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', CORREO);
  await p.type('input[type="password"]', CLAVE);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  t('1 · Primera actividad: queda en revisión y avisa al equipo');

  await publicar(PRIMERA);
  di('se registra', /\/listo/.test(p.url()), p.url().replace(B, ''));
  di('queda en revisión (es la primera de la organización)', estado(PRIMERA) === 'revision', estado(PRIMERA));

  t('2 · La ONG la aprueba; la organización la edita dos veces');

  tinker(`$a = App\\Models\\Activity::where('titulo','${PRIMERA}')->first();`
    + ` app(App\\Services\\ActivityModerationService::class)->cambiar($a, 'publicada', App\\Models\\User::where('email','${ADMIN}')->first()); echo 'OK';`);
  const aid = id(PRIMERA);
  await guardarEdicion(aid, ' Primer cambio.');
  di('el primer guardado se acepta', /guardada|cambios|actividades/.test(p.url()), p.url().replace(B, ''));
  await guardarEdicion(aid, ' Segundo cambio.');
  di('sigue publicada después de editar', estado(PRIMERA) === 'publicada', estado(PRIMERA));

  t('3 · Segunda actividad: sale publicada sola');

  await publicar(SEGUNDA);
  di('queda publicada sin revisión', estado(SEGUNDA) === 'publicada', estado(SEGUNDA));

  artisan('queue:work', '--stop-when-empty', '--tries=1');
  await esperar(1000);

  t('4 · Lo que llegó al buzón de avisos');

  const correos = await alBuzon();
  const asuntos = correos.map((m) => m.Subject);
  console.log('     ' + asuntos.join(' | '));
  const revision = correos.filter((m) => m.Subject === `Actividad para revisar: ${PRIMERA}`);
  const editada = correos.filter((m) => m.Subject === `Cambios en una actividad publicada: ${PRIMERA}`);
  const auto = correos.filter((m) => m.Subject === `Publicada sin revisión: ${SEGUNDA}`);

  di('**uno de «nueva en revisión»** por la primera', revision.length === 1, `${revision.length}`);
  di('**uno solo de «editada»** aunque se guardó dos veces', editada.length === 1, `${editada.length}`);
  di('**uno de «publicada sin revisión»** por la segunda', auto.length === 1, `${auto.length}`);
  di('ninguno de «nueva en revisión» por la segunda', ! asuntos.includes(`Actividad para revisar: ${SEGUNDA}`));
  di('y nada más en el buzón', correos.length === 3, `${correos.length} correo(s)`);

  if (revision[0]) {
    const h = await html(revision[0]);
    di('el de revisión dice la organización y su correo', h.includes(ORG) && h.includes(CORREO));
    di('y el motivo de la revisión', h.includes('es la primera actividad de esta organización'));
    di('y el botón lleva a la ficha del panel', h.includes(`/admin/actividades/${aid}`));
    di('sin marcadores sin rellenar', ! /\{\{\s*\w+\s*\}\}/.test(h));
  }
  if (auto[0]) {
    const h = await html(auto[0]);
    di('el de publicada sola enlaza al panel y al sitio', h.includes(`/admin/actividades/${id(SEGUNDA)}`) && h.includes('/activity/'));
    di('sin marcadores sin rellenar', ! /\{\{\s*\w+\s*\}\}/.test(h));
  }
  if (editada[0]) {
    const h = await html(editada[0]);
    di('el de editada avisa de que es uno al día', h.includes('una vez al día'));
    di('sin marcadores sin rellenar', ! /\{\{\s*\w+\s*\}\}/.test(h));
  }

  t('5 · El organizador sigue recibiendo lo suyo');

  const r = await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${CORREO}"`)}`);
  const suyos = ((await r.json()).messages ?? []).map((m) => m.Subject);
  di('«Recibimos tu actividad» por la primera', suyos.some((s) => /Recibimos/i.test(s)), suyos.join(' | '));
  di('«Tu actividad ya está publicada» por las dos', suyos.filter((s) => s === 'Tu actividad ya está publicada').length === 2);

  t('Consola');
  di('sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  await nav.close();
  console.log('  ' + ultima(limpiar()));
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
