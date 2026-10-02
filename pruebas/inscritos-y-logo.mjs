// Puntos 5 y 6 de la tanda del 30/09. En Chrome.
//
//   5 · «Ver participantes inscritos» no sale en una actividad sin inscripción
//       previa, donde no puede haber nadie. Mismo criterio en la pestaña
//       Inscritos, en Panel → Actividades (la columna dice «—» y no 0) y en el
//       filtro por actividad de Panel → Inscripciones → Exportar. Una que tuvo
//       inscripción y la apagó con inscritos dentro sí sale: esos inscritos
//       existen.
//   6 · Una organización sin logo ve en su panel un aviso con enlace al
//       formulario del logo, que desaparece al subirlo. En el perfil no sale:
//       el formulario está ahí mismo.
//
//   node pruebas/inscritos-y-logo.mjs
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

const SELLO = Date.now();
const CORREO = `inscritos.${SELLO}@ejemplo.cl`;
const CLAVE = 'inscritos-logo-2026';

// La organización, sin logo, y sus tres actividades publicadas.
const ids = JSON.parse(tinker(
  `$u = new App\\Models\\User(['name' => 'Insc', 'email' => '${CORREO}', 'password' => bcrypt('${CLAVE}')]);`
  + ` $u->forceFill(['role' => App\\Models\\User::ROL_ORGANIZER, 'is_active' => true, 'email_verified_at' => now()])->save();`
  + ` $o = App\\Models\\Organization::create(['user_id' => $u->id, 'nombre' => 'Org Inscritos ${SELLO}', 'slug' => 'insc-${SELLO}',`
  + ` 'tipo' => 'Organización sin fines de lucro', 'logo_path' => null, 'activo' => true]);`
  + ` $mk = function ($t, $insc) use ($o) { $a = new App\\Models\\Activity; $a->forceFill(['organization_id' => $o->id, 'titulo' => $t,`
  + ` 'slug' => Illuminate\\Support\\Str::slug($t), 'estado' => 'publicada', 'published_at' => now(), 'formato' => 'Presencial', 'descripcion' => 'Prueba de inscritos.',`
  + ` 'inscripcion_habilitada' => $insc, 'fecha_inicio' => now()->addDays(20)->toDateString()])->save(); return $a; };`
  + ` $con = $mk('Con inscripcion ${SELLO}', true); $sin = $mk('Sin inscripcion ${SELLO}', false); $apagada = $mk('Apagada con inscritos ${SELLO}', false);`
  + ` App\\Models\\Registration::create(['activity_id' => $apagada->id, 'nombre' => 'Ana', 'correo' => 'ana.${SELLO}@ejemplo.cl', 'estado' => 'confirmado', 'token' => Illuminate\\Support\\Str::random(32)]);`
  + ` echo json_encode(['org' => $o->id, 'con' => $con->id, 'sin' => $sin->id, 'apagada' => $apagada->id]);`
).split('\n').pop());

const limpiar = () => tinker(
  `$u = App\\Models\\User::where('email','${CORREO}')->first(); if ($u) { $o = $u->organization;`
  + ` foreach ($o?->activities()->withTrashed()->get() ?? [] as $a) { $a->registrations()->forceDelete(); $a->statusLogs()->delete(); $a->forceDelete(); }`
  + ` $o?->forceDelete(); App\\Models\\AccessLog::where('user_id',$u->id)->delete(); $u->forceDelete(); } echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const entrar = async (ruta, correo, clave) => {
  for (const k of await p.cookies()) await p.deleteCookie(k);
  await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', correo);
  await p.type('input[type="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
};

const enlacesInscritos = () => p.evaluate(() => [...document.querySelectorAll('a')]
  .filter((a) => a.textContent.includes('Ver participantes inscritos') && a.getBoundingClientRect().height > 0)
  .map((a) => a.getAttribute('href')));

const avisoLogo = () => p.evaluate(() => {
  const a = document.querySelector('[data-aviso-logo]');
  if (! a || a.getBoundingClientRect().height === 0) return null;
  return { texto: a.innerText, href: a.querySelector('a')?.getAttribute('href') };
});

try {
  await entrar('/mi-cuenta/login', CORREO, CLAVE);

  t('5 · Mis actividades');
  await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
  const enl = await enlacesInscritos();
  const va = (id) => enl.some((h) => h.includes(`/actividades/${id}/`));
  di('con inscripción: sale el enlace', va(ids.con), enl.join(' '));
  di('sin inscripción: NO sale', ! va(ids.sin));
  di('la apagada con inscritos: sale (esos inscritos existen)', va(ids.apagada));

  t('5 · La pestaña Inscritos');
  await p.goto(`${B}/mi-cuenta/inscritos`, { waitUntil: 'networkidle2' });
  const res = await p.evaluate(() => document.querySelector('main').innerText);
  di('lista la de inscripción', res.includes(`Con inscripcion ${SELLO}`));
  di('no lista la que no la pide', ! res.includes(`Sin inscripcion ${SELLO}`));
  di('lista la apagada con inscritos', res.includes(`Apagada con inscritos ${SELLO}`));

  t('6 · El aviso del logo');
  for (const ruta of ['/mi-cuenta/actividades', '/mi-cuenta/inscritos', '/mi-cuenta/evaluaciones']) {
    await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
    const av = await avisoLogo();
    di(`sale en ${ruta}`, !! av && av.href?.endsWith('/mi-cuenta/perfil#logo-organizacion'), JSON.stringify(av));
  }
  await p.goto(`${B}/mi-cuenta/perfil`, { waitUntil: 'networkidle2' });
  di('no sale en el perfil, donde está el formulario', ! await avisoLogo());
  di('y el ancla del enlace existe en el perfil', await p.$('#logo-organizacion') !== null);

  await p.setViewport({ width: 390, height: 844 });
  await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
  di('en el teléfono se ve y no desborda', !! await avisoLogo() && await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
  await p.setViewport({ width: 1440, height: 1000 });

  // Se sube el logo por el formulario de verdad.
  await p.goto(`${B}/mi-cuenta/perfil#logo-organizacion`, { waitUntil: 'networkidle2' });
  const campo = await p.$('#logo-organizacion input[type="file"]');
  await campo.uploadFile('public/img/logo-fundacion-trascender.png');
  await new Promise((r) => setTimeout(r, 1500));
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => null),
    p.evaluate(() => document.querySelector('#logo-organizacion').requestSubmit()),
  ]);
  const logo = tinker(`echo App\\Models\\Organization::find(${ids.org})->logo_path ?? 'NULL';`).split('\n').pop();
  di('el logo se guarda desde el perfil', logo !== 'NULL', logo);
  await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
  di('y el aviso desaparece', ! await avisoLogo());

  t('5 · Panel de administración');
  await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
  await p.goto(`${B}/admin/actividades?q=${SELLO}`, { waitUntil: 'networkidle2' });
  const celdas = await p.evaluate(() => [...document.querySelectorAll('tbody tr')].map((tr) => ({
    t: tr.innerText, n: tr.querySelector('td.num')?.innerText.trim() })));
  const fila = (txt) => celdas.find((c) => c.t.includes(txt));
  di('la de inscripción enseña su número', fila(`Con inscripcion ${SELLO}`)?.n === '0', fila(`Con inscripcion ${SELLO}`)?.n);
  di('la que no la pide dice «—», no 0', fila(`Sin inscripcion ${SELLO}`)?.n === '—', fila(`Sin inscripcion ${SELLO}`)?.n);
  di('la apagada con inscritos enseña 1', fila(`Apagada con inscritos ${SELLO}`)?.n === '1', fila(`Apagada con inscritos ${SELLO}`)?.n);

  await p.goto(`${B}/admin/inscripciones/exportar`, { waitUntil: 'networkidle2' });
  const opciones = await p.evaluate(() => [...document.querySelectorAll('option')].map((o) => o.textContent.trim()));
  di('Exportar: el filtro ofrece la de inscripción', opciones.includes(`Con inscripcion ${SELLO}`));
  di('y no la que no la pide', ! opciones.includes(`Sin inscripcion ${SELLO}`));
  di('y sí la apagada con inscritos', opciones.includes(`Apagada con inscritos ${SELLO}`));

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
