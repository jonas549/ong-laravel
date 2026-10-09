// Panel → Usuarios ya no deja un organizador sin organización (decisión del
// 02/10 tras el punto 1 del 30/09, que fue justo eso). En Chrome.
//
//   · Crear un organizador pide su organización: una libre del listado se le
//     enlaza, un nombre nuevo se crea con la cuenta, una que ya tiene cuenta se
//     rechaza, y sin ninguna no se crea nada. Un administrador no la pide.
//   · Editar: una cuenta de organizador sin organización (de las de antes)
//     avisa y se arregla ahí mismo; pasar a alguien a organizador la pide.
//   · Una organización con cuenta no se puede eliminar, ni forzando el POST.
//
//   node pruebas/organizador-con-organizacion.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const F = '[data-crear-usuario]';
const E = '[data-editar-usuario]';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').pop();

const S = Date.now();
const C = (n) => `org.${n}.${S}@ejemplo.cl`;
const LIBRE = `Fundación Libre ${S}`;
const LIBRE2 = `Corporación Libre Dos ${S}`;
const TOMADA = `Organización Tomada ${S}`;
const NUEVA = `Agrupación Nueva Del Panel ${S}`;
const ARREGLO = `Junta Arreglada ${S}`;

const ids = JSON.parse(tinker(
  `$mk = fn ($n, $u = null) => App\\Models\\Organization::create(['user_id' => $u, 'nombre' => $n, 'activo' => true])->id;`
  + ` $mku = function ($c, $rol) { $u = new App\\Models\\User(['name' => 'Prueba', 'email' => $c, 'password' => bcrypt('clave-larga-2026')]);`
  + ` $u->forceFill(['role' => $rol, 'is_active' => true, 'email_verified_at' => now()])->save(); return $u->id; };`
  + ` $dueno = $mku('${C('dueno')}', 'organizer'); $huerfano = $mku('${C('huerfano')}', 'organizer'); $admin = $mku('${C('admin')}', 'admin');`
  + ` echo json_encode(['libre' => $mk('${LIBRE}'), 'libre2' => $mk('${LIBRE2}'), 'tomada' => $mk('${TOMADA}', $dueno), 'dueno' => $dueno, 'huerfano' => $huerfano, 'admin' => $admin]);`
));

const limpiar = () => tinker(
  `App\\Models\\Organization::withTrashed()->where('nombre', 'like', '%${S}')->forceDelete();`
  + ` foreach (App\\Models\\User::withTrashed()->where('email', 'like', 'org.%.${S}@ejemplo.cl')->get() as $u) { App\\Models\\AccessLog::where('user_id', $u->id)->delete(); $u->forceDelete(); }`
  + ` echo 'LIMPIO';`
);

const organizacionDe = (correo) => tinker(`echo App\\Models\\User::where('email', '${correo}')->first()?->organization?->nombre ?? (App\\Models\\User::where('email', '${correo}')->exists() ? 'SIN' : 'NO-EXISTE');`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const texto = () => p.evaluate(() => document.querySelector('main')?.innerText ?? document.body.innerText);

/** Rellena «Nuevo usuario»; `org` = nombre a escribir, `elegir` = si se elige de las sugerencias. */
const crear = async ({ correo, rol = 'organizer', org = '', elegir = false }) => {
  await p.goto(`${B}/admin/usuarios?rol=organizer`, { waitUntil: 'networkidle2' });
  await p.type(`${F} [name="name"]`, 'Persona de Prueba');
  await p.type(`${F} [name="email"]`, correo);
  await p.type(`${F} [name="password"]`, 'clave-larga-2026');
  await p.select(`${F} [name="role"]`, rol);
  await esperar(150);
  if (org) {
    await p.type(`${F} [name="org_nombre"]`, org);
    if (elegir) {
      await p.waitForFunction((F, n) => Alpine.$data(document.querySelector(F)).sugerencias.some((s) => s.nombre === n), { timeout: 10000 }, F, org).catch(() => null);
      await p.evaluate((F, n) => { const d = Alpine.$data(document.querySelector(F)); d.elegirOrg(d.sugerencias.find((s) => s.nombre === n)); }, F, org);
      await esperar(200);
    }
  }
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click(`${F} button[type="submit"]`)]);
  return texto();
};

try {
  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', ADMIN);
  await p.type('input[type="password"]', CLAVE_ADMIN);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  t('Crear un organizador');
  await p.goto(`${B}/admin/usuarios?rol=organizer`, { waitUntil: 'networkidle2' });
  di('con rol Organizador se ve el campo Organización', await p.$eval(`${F} [data-campo-organizacion]`, (e) => e.getBoundingClientRect().height > 0));
  await p.select(`${F} [name="role"]`, 'admin');
  await esperar(150);
  di('con Administración se esconde y no viaja', await p.$eval(`${F} [name="org_nombre"]`, (i) => i.disabled && i.closest('[data-campo-organizacion]').getBoundingClientRect().height === 0));

  let tx = await crear({ correo: C('sin') });
  di('sin organización, no se crea', organizacionDe(C('sin')) === 'NO-EXISTE');
  di('y lo dice junto al campo', tx.includes('Un organizador necesita su organización'));

  tx = await crear({ correo: C('libre'), org: LIBRE, elegir: true });
  di('eligiendo una libre del listado, queda enlazada', organizacionDe(C('libre')) === LIBRE, organizacionDe(C('libre')));
  di('sin duplicarla', tinker(`echo App\\Models\\Organization::where('nombre', '${LIBRE}')->count();`) === '1');
  di('y lo confirma', tx.includes(`con «${LIBRE}» como su organización`));

  await crear({ correo: C('nueva'), org: NUEVA });
  di('con un nombre nuevo, se crea con la cuenta', organizacionDe(C('nueva')) === NUEVA, organizacionDe(C('nueva')));

  // Escrita a mano sin elegirla: no se crea otra igual, se pide elegirla.
  tx = await crear({ correo: C('choca'), org: TOMADA });
  di('con el nombre de una que ya tiene cuenta, sin elegirla, no se crea', organizacionDe(C('choca')) === 'NO-EXISTE');
  di('y pide elegirla en las sugerencias', tx.includes('elígela en las sugerencias'));

  // Varias cuentas por organización: el administrador puede sumar una cuenta
  // a una que ya tiene, esté como esté el interruptor. La principal no cambia.
  tx = await crear({ correo: C('suma'), org: TOMADA, elegir: true });
  di('eligiéndola, la cuenta se suma a ella', organizacionDe(C('suma')) === TOMADA, organizacionDe(C('suma')));
  di('la principal sigue siendo la de antes', tinker(`echo App\\Models\\Organization::find(${ids.tomada})->user_id;`) === String(ids.dueno));
  di('y lo dice', tx.includes('Avisamos a su cuenta principal'));

  tx = await crear({ correo: C('escrita'), org: LIBRE2 });
  di('escribiendo a mano una libre sin elegirla, no crea otra igual', organizacionDe(C('escrita')) === 'NO-EXISTE'
    && tinker(`echo App\\Models\\Organization::where('nombre', '${LIBRE2}')->count();`) === '1');
  di('y pide elegirla en las sugerencias', tx.includes('elígela en las sugerencias'));

  await crear({ correo: C('adminnuevo'), rol: 'admin' });
  di('un administrador se crea sin organización', organizacionDe(C('adminnuevo')) === 'SIN');

  t('Una consulta de fondo no se lleva los avisos');
  // La carrera, a propósito: envío rechazado, una consulta del buscador antes
  // de recargar, y la recarga. Antes la página volvía vacía.
  await p.goto(`${B}/admin/usuarios?rol=organizer`, { waitUntil: 'networkidle2' });
  await p.evaluate(async (correo) => {
    const f = document.querySelector('[data-crear-usuario]');
    const datos = new URLSearchParams(new FormData(f));
    datos.set('name', 'Carrera'); datos.set('email', correo); datos.set('password', 'clave-larga-2026');
    datos.set('role', 'organizer'); datos.set('org_nombre', '');
    await fetch(f.action, { method: 'POST', body: datos, redirect: 'manual', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    await fetch('/organizaciones/buscar?q=Fundaci', { headers: { Accept: 'application/json' } });
  }, C('carrera'));
  await p.goto(`${B}/admin/usuarios?rol=organizer`, { waitUntil: 'networkidle2' });
  di('la página trae el aviso', (await texto()).includes('Un organizador necesita su organización'));
  di('y lo escrito', await p.$eval(`${F} [name="email"]`, (i) => i.value) === C('carrera'));
  await p.reload({ waitUntil: 'networkidle2' });
  di('y al recargar otra vez ya no (no se repite)', ! (await texto()).includes('Un organizador necesita su organización'));

  t('Editar: el organizador de antes, sin organización');
  await p.goto(`${B}/admin/usuarios/${ids.huerfano}/editar?rol=organizer`, { waitUntil: 'networkidle2' });
  di('la ficha avisa de que no tiene organización', await p.$('[data-sin-organizacion]') !== null);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click(`${E} button.btn-primary[type="submit"]`)]);
  di('guardar sin asignarle una no se acepta', (await texto()).includes('Un organizador necesita su organización') && organizacionDe(C('huerfano')) === 'SIN');
  await p.type(`${E} [name="org_nombre"]`, ARREGLO);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click(`${E} button.btn-primary[type="submit"]`)]);
  di('con una, queda arreglada', organizacionDe(C('huerfano')) === ARREGLO, organizacionDe(C('huerfano')));
  di('y la ficha ya no avisa', await p.$('[data-sin-organizacion]') === null);

  t('Editar: pasar un administrador a organizador');
  await p.goto(`${B}/admin/usuarios/${ids.admin}/editar?rol=admin`, { waitUntil: 'networkidle2' });
  await p.select(`${E} [name="role"]`, 'organizer');
  await esperar(150);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click(`${E} button.btn-primary[type="submit"]`)]);
  di('sin organización no se cambia el rol', tinker(`echo App\\Models\\User::find(${ids.admin})->role;`) === 'admin');
  await p.goto(`${B}/admin/usuarios/${ids.admin}/editar?rol=admin`, { waitUntil: 'networkidle2' });
  await p.select(`${E} [name="role"]`, 'organizer');
  await esperar(150);
  await p.type(`${E} [name="org_nombre"]`, LIBRE2);
  await p.waitForFunction((E, n) => Alpine.$data(document.querySelector(E)).sugerencias.some((s) => s.nombre === n), { timeout: 10000 }, E, LIBRE2).catch(() => null);
  await p.evaluate((E, n) => { const d = Alpine.$data(document.querySelector(E)); d.elegirOrg(d.sugerencias.find((s) => s.nombre === n)); }, E, LIBRE2);
  await esperar(200);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click(`${E} button.btn-primary[type="submit"]`)]);
  di('con una libre, pasa a organizador y queda enlazada', tinker(`echo App\\Models\\User::find(${ids.admin})->role;`) === 'organizer'
    && organizacionDe(C('admin')) === LIBRE2, organizacionDe(C('admin')));

  t('Una organización con cuenta no se elimina');
  await p.goto(`${B}/admin/organizaciones/${ids.tomada}/editar`, { waitUntil: 'networkidle2' });
  di('la ficha no ofrece eliminarla y dice por qué', await p.$('[data-no-eliminar-con-cuenta]') !== null);
  const r = await p.evaluate(async (u) => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const res = await fetch(u, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `_token=${token}&_method=DELETE` });
    return res.status;
  }, `${B}/admin/organizaciones/${ids.tomada}`);
  di('forzando el DELETE, sigue ahí', tinker(`echo App\\Models\\Organization::whereKey(${ids.tomada})->exists() ? 'SI' : 'NO';`) === 'SI', `respuesta ${r}`);

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
