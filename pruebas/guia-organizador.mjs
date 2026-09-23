// D1 de la sexta tanda — el correo con la guía para organizadores.
//
// Publica una actividad de verdad por el wizard, corre la cola y lee el correo
// en Mailpit: que llegue a la organización, con el enlace de Configuración →
// General y el botón hacia la guía. Y que con el enlace vacío no salga.
//
// Necesita Mailpit en el 1025/8025 (Laragon lo trae) y la cola en `database`.
//
//   node pruebas/guia-organizador.mjs
//
// Contra producción NO: publica una actividad y manda correos.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

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
const CORREO = `guia.${SELLO}@ejemplo.cl`;
const CLAVE = 'guia-organizador-2026';
const ORG = `Fundación Guía ${SELLO}`;
const TITULO = `Actividad con guía ${SELLO}`;

const guiaAntes = ultima(tinker(`echo App\\Models\\Setting::get('guia_organizador_url');`));

tinker(
  `$u = new App\\Models\\User(['name' => 'Persona Guía', 'email' => '${CORREO}', 'password' => bcrypt('${CLAVE}')]);`
  + ` $u->forceFill(['role' => App\\Models\\User::ROL_ORGANIZER, 'is_active' => true, 'email_verified_at' => now()])->save();`
  + ` App\\Models\\Organization::create(['user_id' => $u->id, 'nombre' => '${ORG}', 'slug' => 'guia-'.$u->id, 'activo' => true,`
  + ` 'tipo' => 'Organización sin fines de lucro', 'logo_path' => 'storage/organizaciones/prueba.png']); echo 'OK';`
);

const limpiar = () => tinker(
  `$a = App\\Models\\Activity::where('titulo','${TITULO}')->first(); if ($a) { $a->registrations()->forceDelete(); $a->forceDelete(); }`
  + ` $u = App\\Models\\User::where('email','${CORREO}')->first(); if ($u) { $u->organization?->forceDelete(); $u->delete(); }`
  + ` App\\Models\\AccessLog::where('email','${CORREO}')->delete();`
  + ` App\\Models\\Setting::where('clave','guia_organizador_url')->update(['valor' => '${guiaAntes}']); cache()->flush(); echo 'LIMPIO';`
);

const correosPara = async (destino) => {
  const r = await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${destino}"`)}`);
  return (await r.json()).messages ?? [];
};

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

try {
  t('Configuración → General tiene el enlace');

  // Punto 11 del 23/09: el enlace corto del ticket, que lleva al mismo diseño.
  di('El ajuste existe y trae el enlace del ticket',
    guiaAntes === 'https://canva.link/r3abo554dfga5g6', guiaAntes);
  di('La plantilla existe y está activa', ultima(tinker(
    `echo App\\Models\\EmailTemplate::porClave('guia_organizador') ? 'si' : 'no';`)) === 'si');

  t('Una organización registra una actividad');

  await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', CORREO);
  await p.type('input[type="password"]', CLAVE);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => b.innerText.includes('No, solo quiero difundir')).click());
  await esperar(300);

  await p.type('input[name="titulo"]', TITULO);
  await p.type('textarea[name="descripcion"]', 'Sembrada por pruebas/guia-organizador.mjs.');
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
  di('La actividad se registra', /\/listo/.test(p.url()), p.url().replace(B, ''));

  const guias = () => ultima(tinker(`echo App\\Models\\EmailLog::where('plantilla','guia_organizador')->where('to','like','%${CORREO}%')->count();`));

  /*
   * Punto 11 del 23/09: la guía sale al PUBLICAR, no al registrar. Una
   * organización nueva pasa por revisión con su primera actividad, así que al
   * registrarla todavía no hay guía.
   */
  const estado = ultima(tinker(`echo App\\Models\\Activity::where('titulo','${TITULO}')->value('estado');`));
  di('La primera actividad de la organización queda en revisión', estado === 'revision', estado);
  di('**Al registrarla NO sale la guía**', guias() === '0', `${guias()} fila(s)`);

  t('La ONG la aprueba: ahora sí');

  tinker(`$a = App\\Models\\Activity::where('titulo','${TITULO}')->first();`
    + ` app(App\\Services\\ActivityModerationService::class)->cambiar($a, 'publicada', App\\Models\\User::where('email','${ADMIN}')->first()); echo 'OK';`);
  di('**Al publicarla sale la guía**', guias() === '1', `${guias()} fila(s)`);

  // La cola la corre el planificador en el servidor; aquí, a mano.
  artisan('queue:work', '--stop-when-empty', '--tries=1');
  await esperar(800);

  // Vuelve a revisión (una edición) y se publica otra vez: la guía ya la tiene.
  tinker(`$a = App\\Models\\Activity::where('titulo','${TITULO}')->first(); $m = app(App\\Services\\ActivityModerationService::class);`
    + ` $m->cambiar($a, 'revision'); $m->cambiar($a->fresh(), 'publicada'); echo 'OK';`);
  di('Republicarla no la manda otra vez', guias() === '1', `${guias()} fila(s)`);
  const repetida = ultima(tinker(
    `$a = App\\Models\\Activity::where('titulo','${TITULO}')->first(); echo app(App\\Services\\CorreoTransaccional::class)->guiaOrganizador($a) ? 'si' : 'no';`));
  di('Ni aunque se pida a mano: una por actividad', repetida === 'no');
  artisan('queue:work', '--stop-when-empty', '--tries=1');
  await esperar(500);

  const correos = await correosPara(CORREO);
  const guia = correos.find((m) => /guía para organizar/i.test(m.Subject));
  di('**Llega a la organización**', !! guia, correos.map((m) => m.Subject).join(' | '));
  di('Con el título de su actividad en el asunto', guia?.Subject.includes(TITULO), guia?.Subject);

  if (guia) {
    const html = (await (await fetch(`${MAILPIT}/api/v1/message/${guia.ID}`)).json()).HTML;
    di('**Con el botón a la guía de Configuración**', html.includes(`href="${guiaAntes}"`));
    di('Y dice que ya está publicada, no que se avisará', html.includes('ya está publicada') && ! html.includes('te avisaremos'));
    di('Y el enlace a su cuenta', html.includes(`${B}/mi-cuenta/actividades`) || html.includes('/mi-cuenta/actividades'));
    di('Sin marcadores sin rellenar', ! /\{\{\s*\w+\s*\}\}/.test(html));
  }

  t('Con otro enlace, sale el nuevo; vacío, no sale');

  // Se olvida la que ya se mandó: si no, «una por actividad» no la dejaría salir.
  tinker(`App\\Models\\EmailLog::where('plantilla','guia_organizador')->where('to','like','%${CORREO}%')->delete();`
    + ` App\\Models\\Setting::where('clave','guia_organizador_url')->update(['valor' => 'https://ejemplo.cl/guia-${SELLO}']); cache()->flush(); echo 'OK';`);
  const conOtro = ultima(tinker(
    `$a = App\\Models\\Activity::where('titulo','${TITULO}')->first(); echo app(App\\Services\\CorreoTransaccional::class)->guiaOrganizador($a) ? 'si' : 'no';`));
  artisan('queue:work', '--stop-when-empty', '--tries=1');
  await esperar(800);
  const segundo = (await correosPara(CORREO)).filter((m) => /guía para organizar/i.test(m.Subject));
  let conEnlaceNuevo = false;
  for (const m of segundo) {
    const html = (await (await fetch(`${MAILPIT}/api/v1/message/${m.ID}`)).json()).HTML;
    if (html.includes(`https://ejemplo.cl/guia-${SELLO}`)) conEnlaceNuevo = true;
  }
  di('Cambiar el enlace en Configuración cambia el del correo', conOtro === 'si' && conEnlaceNuevo);

  tinker(`App\\Models\\Setting::where('clave','guia_organizador_url')->update(['valor' => '']); cache()->flush(); echo 'OK';`);
  const vacio = ultima(tinker(
    `$a = App\\Models\\Activity::where('titulo','${TITULO}')->first(); echo app(App\\Services\\CorreoTransaccional::class)->guiaOrganizador($a) ? 'si' : 'no';`));
  di('**Con el enlace vacío no sale**', vacio === 'no');

  t('El panel');

  tinker(`App\\Models\\Setting::where('clave','guia_organizador_url')->update(['valor' => '${guiaAntes}']); cache()->flush(); echo 'OK';`);
  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  // Otra sesión: la del organizador no entra al panel.
  const ctx = await nav.createBrowserContext();
  const a = await ctx.newPage();
  await a.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await a.type('input[name="email"]', ADMIN);
  await a.type('input[name="password"]', CLAVE_ADMIN);
  await Promise.all([a.waitForNavigation({ waitUntil: 'networkidle2' }), a.click('button[type="submit"]')]);
  await a.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
  di('Configuración → General enseña el campo', await a.evaluate(() =>
    document.body.innerText.includes('Enlace a la guía para organizadores')
    && [...document.querySelectorAll('input')].some((i) => i.value.includes('canva.link/r3abo554dfga5g6'))));
  await a.goto(`${B}/admin/plantillas`, { waitUntil: 'networkidle2' });
  di('Y la plantilla sale en Plantillas de correo, editable', await a.evaluate(() =>
    document.body.innerText.includes('Guía para organizadores')));
  await ctx.close();

  di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));
} finally {
  limpiar();
  await nav.close();
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
