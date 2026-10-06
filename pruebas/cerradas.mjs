// Tanda del 05/10, punto 6 — actividades cerradas.
//
// La pregunta «¿Es una actividad cerrada para un público específico?» sólo
// aparece con «No» en «¿Requiere inscripción previa?», que no cambia. Una
// cerrada:
//   - no sale en /actividades, ni en el calendario, ni en el buscador, ni en
//     «otras actividades cerca»;
//   - tiene ficha por enlace directo, con «Esta es una actividad cerrada para
//     un público específico. Fue publicada para difusión», sin formulario y sin
//     invitar; el POST directo se rechaza;
//   - sigue generando su imagen de difusión y contando en el home;
//   - se ve y se edita con normalidad en el panel y en Mis actividades.
// Y las que ya existían quedan como no cerradas.
//
// Monta su propia organización (con una publicada antes, para que la
// aprobación automática publique sola) y la borra al terminar.
//
//   node pruebas/cerradas.mjs      (desde la raíz del repo)
//
// Contra producción NO: crea cuentas y actividades.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();
const json = (php) => JSON.parse(ultima(tinker(php)));

const SELLO = Date.now();
const CORREO = `cerradas.${SELLO}@ejemplo.cl`;
const CLAVE = 'cerradas-2026';
const CERRADA = `Cerrada ${SELLO}`;
const ABIERTA = `Abierta ${SELLO}`;
const MENSAJE = 'Esta es una actividad cerrada para un público específico. Fue publicada para difusión.';

const antesCerradas = +ultima(tinker(`echo App\\Models\\Activity::where('cerrada', true)->count();`));

const ids = json(
  `$u = new App\\Models\\User(['name' => 'Persona Cerradas', 'email' => '${CORREO}', 'password' => bcrypt('${CLAVE}')]);`
  + ` $u->forceFill(['role' => App\\Models\\User::ROL_ORGANIZER, 'is_active' => true, 'email_verified_at' => now()])->save();`
  + ` $o = App\\Models\\Organization::create(['user_id' => $u->id, 'nombre' => 'Fundación Cerradas ${SELLO}', 'slug' => 'cerradas-'.$u->id, 'activo' => true,`
  + ` 'tipo' => 'Organización sin fines de lucro', 'logo_path' => 'storage/organizaciones/prueba.png']);`
  + ` $base = App\\Models\\Activity::where('estado','publicada')->whereNotNull('region_id')->firstOrFail();`
  + ` $p = $base->replicate(); $p->forceFill(['organization_id' => $o->id, 'titulo' => 'Previa ${SELLO}', 'slug' => 'previa-${SELLO}', 'published_at' => now(), 'cerrada' => false])->save();`
  + ` echo json_encode(['org' => $o->id, 'region' => $base->region_id, 'comuna' => $base->commune_id, 'vecina' => $base->id]);`
);
const actividad = (titulo) => json(`echo json_encode(App\\Models\\Activity::where('titulo','${titulo}')->first()?->only(['id','estado','cerrada','inscripcion_habilitada','slug','fecha_inicio','region_id']));`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const pagina = async (ctx) => { const p = await ctx.newPage(); p.on('pageerror', (e) => errores.push(String(e))); await p.setViewport({ width: 1440, height: 1000 }); return p; };
const visible = (p, sel) => p.evaluate((s) => { const e = document.querySelector(s); return !! e && !! e.offsetParent; }, sel);

const ctxOrg = await nav.createBrowserContext();
const p = await pagina(ctxOrg);

const publicar = async (titulo, { insc, cerrada }) => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => b.innerText.includes('No, solo quiero difundir')).click());
  await esperar(300);
  await p.type('input[name="titulo"]', titulo);
  await p.type('textarea[name="descripcion"]', 'Sembrada por pruebas/cerradas.mjs.');
  await p.evaluate((r, c) => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    for (const grupo of ['temas', 'caracteristicas', 'publicos']) {
      const b = [...document.querySelectorAll('button')].find((x) => x.getAttribute('x-on:click')?.startsWith(`alternar('${grupo}'`));
      if (b && d.sel[grupo].length === 0) b.click();
    }
    d.regionId = String(r);
    setTimeout(() => { d.communeId = String(c); }, 50);
  }, ids.region, ids.comuna);
  await esperar(300);
  await p.type('input[name="direccion"]', 'Calle de prueba 123');
  await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).dirAbiertas = false; });
  const fecha = ultima(tinker(`echo now(App\\Support\\Fecha::zona())->addDays(25)->format('dmY');`));
  await p.type('input[name="fecha_inicio"]', fecha);
  // Inscripción previa y, si «No», la pregunta de cerrada, por sus botones.
  await p.evaluate((insc) => {
    const titulo = [...document.querySelectorAll('div')].find((d) => d.textContent.trim() === '¿Requiere inscripción previa?');
    [...titulo.nextElementSibling.querySelectorAll('button')].find((b) => b.textContent.trim() === (insc ? 'Sí' : 'No')).click();
  }, insc);
  await esperar(200);
  if (cerrada !== undefined) {
    await p.evaluate((c) => [...document.querySelectorAll('[data-pregunta-cerrada] button')].find((b) => b.textContent.trim() === (c ? 'Sí' : 'No')).click(), cerrada);
    await esperar(150);
  }
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => null),
    p.evaluate(() => [...document.querySelectorAll('button[type="submit"]')].find((b) => /Publicar|Enviar/i.test(b.textContent)).click()),
  ]);
};

try {
  await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', CORREO);
  await p.type('input[type="password"]', CLAVE);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  t('0 · Las que ya existían');
  di('**ninguna actividad anterior quedó cerrada**', antesCerradas === 0, `${antesCerradas}`);

  t('1 · La pregunta, en el wizard');
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => b.innerText.includes('No, solo quiero difundir')).click());
  await esperar(300);
  di('«¿Requiere inscripción previa?» sigue igual, Sí / No', await p.evaluate(() => {
    const t = [...document.querySelectorAll('div')].find((d) => d.textContent.trim() === '¿Requiere inscripción previa?');
    return !! t && [...t.nextElementSibling.querySelectorAll('button')].map((b) => b.textContent.trim()).join('/') === 'Sí/No';
  }));
  di('**con «Sí», la pregunta de cerrada no se ve**', ! (await visible(p, '[data-pregunta-cerrada]')));
  di('y no viaja (deshabilitada)', await p.$eval('[data-pregunta-cerrada] input[name="cerrada"]', (i) => i.disabled));
  await p.evaluate(() => [...[...document.querySelectorAll('div')].find((d) => d.textContent.trim() === '¿Requiere inscripción previa?').nextElementSibling.querySelectorAll('button')].find((b) => b.textContent.trim() === 'No').click());
  await esperar(200);
  di('**con «No», aparece**', await visible(p, '[data-pregunta-cerrada]'));
  const textoPregunta = await p.$eval('[data-pregunta-cerrada]', (e) => e.innerText);
  di('con la ayuda pedida', textoPregunta.includes('Elige Sí si solo quieres difundir tu actividad'), textoPregunta.replace(/\s+/g, ' '));
  di('y arranca en «No»', await p.$eval('[data-pregunta-cerrada] input[name="cerrada"]', (i) => i.value === '0' && ! i.disabled));

  t('2 · Publicar una cerrada');
  await publicar(CERRADA, { insc: false, cerrada: true });
  const c = actividad(CERRADA);
  di('se crea, publicada (la organización ya publicó antes)', c?.estado === 'publicada', JSON.stringify(c));
  di('**marcada como cerrada y sin inscripción**', c?.cerrada === true && c?.inscripcion_habilitada === false);

  // Con inscripción previa la pregunta no se ve ni viaja: nunca queda cerrada.
  const ABIERTA3 = ABIERTA;
  await publicar(ABIERTA3, { insc: true });
  const a3 = actividad(ABIERTA3);
  di('con inscripción previa nunca queda cerrada', a3?.cerrada === false && a3?.inscripcion_habilitada === true, JSON.stringify(a3));

  t('3 · Fuera del listado, del calendario y del buscador');
  const anon = await nav.createBrowserContext();
  const q = await pagina(anon);
  const textoDe = async (url) => { await q.goto(`${B}${url}`, { waitUntil: 'networkidle2' }); return q.evaluate(() => document.body.innerText); };
  di('**no sale en /actividades**', ! (await textoDe('/actividades')).includes(CERRADA));
  di('**ni buscándola por nombre**', ! (await textoDe(`/actividades?q=${encodeURIComponent(CERRADA)}`)).includes(CERRADA));
  const mes = c.fecha_inicio.slice(0, 7);
  const cal = await textoDe(`/actividades?vista=calendario&mes=${mes}`);
  di('**ni en el calendario de su mes**', ! cal.includes(CERRADA), mes);
  di('la abierta del mismo día sí sale en el calendario', cal.includes(ABIERTA3));
  const vecina = ultima(tinker(`echo route('activities.show', App\\Models\\Activity::find(${ids.vecina}), false);`));
  di('ni en «otras actividades cerca» de su región', ! (await textoDe(vecina)).includes(CERRADA));

  t('4 · Su ficha, por enlace directo');
  const ficha = ultima(tinker(`echo route('activities.show', App\\Models\\Activity::find(${c.id}), false);`));
  const f = await textoDe(ficha);
  di('**abre por enlace**', f.includes(CERRADA), ficha);
  di('**con el mensaje de cerrada**', f.includes(MENSAJE));
  di('sin formulario de inscripción', ! (await q.$('form[action*="inscribirse"]')));
  di('y sin invitar («Te esperamos», «No es necesario inscripción»)', ! f.includes('Te esperamos') && ! f.includes('No es necesario inscripción'));
  const r = await q.evaluate(async (url) => {
    const tok = document.querySelector('meta[name="csrf-token"]')?.content ?? document.querySelector('input[name="_token"]')?.value;
    const fd = new FormData(); fd.append('_token', tok); fd.append('nombre', 'Se cuela'); fd.append('correo', 'cuela@ejemplo.cl');
    return (await fetch(url, { method: 'POST', body: fd })).status;
  }, `${B}/actividades/${c.slug}/inscribirse`);
  di('el POST directo no inscribe a nadie', +ultima(tinker(`echo App\\Models\\Registration::where('activity_id', ${c.id})->count();`)) === 0, `${r}`);
  await anon.close();

  t('5 · Difusión y contadores');
  await p.goto(`${B}/mi-cuenta/actividades/${c.id}/difusion`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => ['lista', 'error'].includes(Alpine.$data(document.querySelector('.difusion'))?.estado), { timeout: 20000 });
  const dif = await p.evaluate(() => ({ estado: Alpine.$data(document.querySelector('.difusion')).estado, textos: JSON.parse(JSON.stringify(Alpine.$data(document.querySelector('.difusion')).dibujo?.textos ?? [])).map((x) => x.t) }));
  di('**sigue generando su imagen de difusión**', dif.estado === 'lista' && dif.textos.some((x) => x.includes(CERRADA.split(' ')[0])));
  di('con «Actividad cerrada» en la casilla de cupos', dif.textos.includes('Actividad cerrada'));
  const home = await (await fetch(`${B}/`)).text();
  const actual = parseInt([...home.matchAll(/class="count">([^<]*)<\/span> de/g)][0][1].replace(/\D/g, ''), 10);
  const base = parseInt(ultima(tinker(`echo App\\Models\\HomeSection::where('clave','meta')->first()?->texto('barra1_actual') ?? '500';`)).replace(/\D/g, ''), 10);
  const publicadas = +ultima(tinker(`echo App\\Models\\Activity::where('estado','publicada')->count();`));
  di('**sigue contando en el home**', actual === base + publicadas, `${actual} = ${base} + ${publicadas}`);

  t('6 · Mis actividades: se ve y se edita');
  await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
  di('sale en Mis actividades, con su etiqueta', await p.evaluate((t) => [...document.querySelectorAll('[data-cerrada]')].some((e) => e.closest('div[style]')?.parentElement?.innerText.includes(t)), CERRADA));
  await p.goto(`${B}/mi-cuenta/actividades/${c.id}/editar`, { waitUntil: 'networkidle2' });
  di('el editor ya no pregunta «¿Esta actividad es abierta al público?»', ! (await p.evaluate(() => document.body.innerText.includes('abierta al público'))));
  di('la pregunta de cerrada sale, marcada en «Sí»', (await visible(p, '[data-pregunta-cerrada]')) && (await p.$eval('[data-pregunta-cerrada] input[name="cerrada"]', (i) => i.value)) === '1');
  await p.evaluate(() => [...document.querySelectorAll('[data-pregunta-cerrada] button')].find((b) => b.textContent.trim() === 'No').click());
  await Promise.all([p.waitForNavigation({ timeout: 20000 }), p.click(`form[action$="/${c.id}"] button[type="submit"]`)]);
  di('**pasarla a no cerrada desde el editor**', actividad(CERRADA).cerrada === false);
  di('y entonces sí sale en /actividades', (await (await fetch(`${B}/actividades?q=${encodeURIComponent(CERRADA)}`)).text()).includes(CERRADA));
  await p.goto(`${B}/mi-cuenta/actividades/${c.id}/editar`, { waitUntil: 'networkidle2' });
  await p.evaluate(() => [...document.querySelectorAll('[data-pregunta-cerrada] button')].find((b) => b.textContent.trim() === 'Sí').click());
  await Promise.all([p.waitForNavigation({ timeout: 20000 }), p.click(`form[action$="/${c.id}"] button[type="submit"]`)]);
  di('y volver a cerrarla', actividad(CERRADA).cerrada === true);
  di('guardar ya no toca `abierta_publico`', ultima(tinker(`echo (int) App\\Models\\Activity::find(${c.id})->abierta_publico;`)) === '1');

  t('7 · El panel');
  const ctxAdmin = await nav.createBrowserContext();
  const a = await pagina(ctxAdmin);
  await a.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await a.type('input[type="email"]', ADMIN);
  await a.type('input[type="password"]', CLAVE_ADMIN);
  await Promise.all([a.waitForNavigation({ waitUntil: 'networkidle2' }), a.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
  await a.goto(`${B}/admin/actividades?q=${encodeURIComponent(CERRADA)}`, { waitUntil: 'networkidle2' });
  di('**sale en el listado del panel**', (await a.evaluate(() => document.body.innerText)).includes(CERRADA));
  await a.goto(`${B}/admin/actividades/${c.id}`, { waitUntil: 'networkidle2' });
  di('su ficha de revisión dice «No, es cerrada»', (await a.$eval('[data-inscripcion-label]', (e) => e.innerText)).includes('No, es cerrada'));
  await ctxAdmin.close();

  t('Consola');
  di('sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  await nav.close();
  console.log('  ' + ultima(tinker(
    `$o = App\\Models\\Organization::find(${ids.org}); if ($o) { foreach (App\\Models\\Activity::withTrashed()->where('organization_id', $o->id)->get() as $x) { $x->registrations()->forceDelete(); $x->statusLogs()->delete(); $x->terms()->detach(); $x->forceDelete(); } }`
    + ` $u = App\\Models\\User::where('email','${CORREO}')->first(); if ($u) { $u->organization?->forceDelete(); $u->delete(); } echo 'LIMPIO';`)));
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
