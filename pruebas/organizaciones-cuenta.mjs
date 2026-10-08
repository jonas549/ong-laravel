// Puntos 1, 2 y 7 del 08/10, en Chrome.
//
//   1 · Panel → Organizaciones → Nueva crea también la cuenta de acceso, en el
//       mismo paso, con las reglas de Panel → Usuarios: correo sin repetir,
//       avisado al salir del campo y rechazado al enviar. Sin la casilla, la
//       organización queda libre como antes.
//   2 · Listado y ficha: la cuenta (nombre, correo, alta), los demás correos de
//       contacto usados en la ficha y en sus actividades, y la fecha en que se
//       creó la organización.
//   7 · Mis actividades: el botón de editar enseña «Editar actividad» al pasar
//       por encima y al enfocarlo con el teclado.
//
//   node pruebas/organizaciones-cuenta.mjs
//
// Contra producción NO: crea organizaciones, cuentas y una actividad.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').pop();

const SELLO = Date.now();
const CON_CUENTA = `Org Con Cuenta ${SELLO}`;
const SIN_CUENTA = `Org Sin Cuenta ${SELLO}`;
const CORREO = `cuenta.${SELLO}@ejemplo.cl`;
const CLAVE = `Clave-${SELLO}`;
const CONTACTO_ACT = `contacto.act.${SELLO}@ejemplo.cl`;

const limpiar = () => tinker(
  `foreach (App\\Models\\Organization::withTrashed()->whereIn('nombre', ['${CON_CUENTA}', '${SIN_CUENTA}'])->get() as $o) {`
  + ` $o->activities()->withTrashed()->forceDelete(); $u = $o->user; $o->forceDelete(); $u?->forceDelete(); }`
  + ` App\\Models\\User::withTrashed()->where('email', '${CORREO}')->forceDelete(); echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const ctx = await nav.createBrowserContext();
const p = await ctx.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const seVe = (sel) => p.evaluate((s) => {
  const n = document.querySelector(s);
  return !! n && getComputedStyle(n).display !== 'none' && n.getBoundingClientRect().height > 0;
}, sel);

const enviar = () => Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.click('[data-crear-organizacion] button[type="submit"]'),
]);

try {
  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', ADMIN);
  await p.type('input[type="password"]', CLAVE_ADMIN);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  t('1 · Crear organización con su cuenta de acceso');

  await p.goto(`${B}/admin/organizaciones/crear`, { waitUntil: 'networkidle2' });
  di('hay una casilla «Cuenta de acceso», desmarcada', await p.$eval('[name="crear_cuenta"]', (n) => ! n.checked));
  di('sin marcar, los campos de la cuenta no se ven', ! await seVe('#c-email'));
  di('y van desactivados (no viajan)', await p.$eval('#c-email', (n) => n.disabled));

  await p.type('[data-crear-organizacion] [name="nombre"]', CON_CUENTA);
  await p.click('[name="crear_cuenta"]');
  await esperar(200);
  di('al marcarla aparecen nombre, correo y contraseña', await seVe('#c-name') && await seVe('#c-email') && await seVe('#c-password'));

  // Un correo que ya tiene cuenta: el del administrador.
  await p.type('#c-name', 'Persona de Prueba');
  await p.type('#c-email', ADMIN);
  await p.click('#c-password');
  await esperar(900);
  di('**un correo que ya existe se avisa al salir del campo**', await seVe('[data-correo-existe]'));

  await p.type('#c-password', CLAVE);
  await enviar();
  const errorServidor = await p.evaluate(() => document.body.innerText.includes('Ya existe una cuenta con ese correo.'));
  di('**y el envío lo rechaza con su mensaje**', errorServidor);
  di('sin crear la organización', tinker(`echo App\\Models\\Organization::where('nombre', '${CON_CUENTA}')->count();`) === '0');
  di('vuelve con la casilla marcada y lo escrito', await p.$eval('[name="crear_cuenta"]', (n) => n.checked)
    && await p.$eval('[data-crear-organizacion] [name="nombre"]', (n, v) => n.value === v, CON_CUENTA));

  await p.$eval('#c-email', (n) => { n.value = ''; });
  await p.type('#c-email', CORREO);
  await p.click('#c-password');
  await esperar(900);
  di('con un correo libre, el aviso se va', ! await seVe('[data-correo-existe]'));
  await p.type('#c-password', CLAVE);
  await enviar();

  const creada = JSON.parse(tinker(
    `$o = App\\Models\\Organization::where('nombre', '${CON_CUENTA}')->first(); $u = $o?->user;`
    + ` echo json_encode(['org' => $o?->id, 'correo' => $u?->email, 'rol' => $u?->role, 'activa' => $u?->is_active, 'verificado' => (bool) $u?->email_verified_at, 'contacto' => $o?->correo_contacto]);`));
  di('**la organización queda creada con su cuenta**', creada.org && creada.correo === CORREO, JSON.stringify(creada));
  di('la cuenta es de organizador, activa y verificada', creada.rol === 'organizer' && creada.activa === true && creada.verificado === true);
  di('el correo de contacto, sin escribir, es el de la cuenta', creada.contacto === CORREO);
  di('lo dice al guardar', await p.evaluate((c) => document.body.innerText.includes(`con la cuenta de acceso ${c}`), CORREO));

  t('1 · Sin la casilla, como antes: libre');

  await p.goto(`${B}/admin/organizaciones/crear`, { waitUntil: 'networkidle2' });
  await p.type('[data-crear-organizacion] [name="nombre"]', SIN_CUENTA);
  await enviar();
  di('queda sin cuenta, reclamable', tinker(`echo App\\Models\\Organization::where('nombre', '${SIN_CUENTA}')->whereNull('user_id')->count();`) === '1');

  t('2 · Listado y ficha: cuenta, correos y fecha de creación');

  // Una actividad con otro correo de contacto, y la ficha con un tercero.
  tinker(
    `$o = App\\Models\\Organization::find(${creada.org}); $o->update(['correo_contacto' => 'ficha.${SELLO}@ejemplo.cl']);`
    + ` $a = new App\\Models\\Activity; $a->forceFill(['organization_id' => $o->id, 'titulo' => 'Act cuenta ${SELLO}', 'descripcion' => 'x', 'slug' => 'act-cuenta-${SELLO}',`
    + ` 'estado' => 'borrador', 'formato' => 'Presencial', 'correo_contacto' => '${CONTACTO_ACT}', 'fecha_inicio' => now()->addDays(9)->toDateString()])->save();`
    + ` $b = $a->replicate(); $b->forceFill(['slug' => 'act-cuenta-b-${SELLO}', 'correo_contacto' => strtoupper('${CONTACTO_ACT}')])->save(); echo 'ok';`);

  await p.goto(`${B}/admin/organizaciones?q=${encodeURIComponent(CON_CUENTA)}`, { waitUntil: 'networkidle2' });
  const celda = await p.$eval('[data-correos-organizacion]', (n) => n.innerText);
  di('el listado enseña el correo de la cuenta', celda.includes(CORREO), celda.replace(/\s+/g, ' '));
  di('y los otros correos usados (ficha y actividad)', celda.includes(`ficha.${SELLO}@ejemplo.cl`) && celda.includes(CONTACTO_ACT));
  di('sin repetir el mismo correo en mayúsculas', celda.toLowerCase().split(CONTACTO_ACT).length === 2);
  // El texto, no `innerText`: la cabecera va en mayúsculas por CSS.
  const cabeceras = await p.$$eval('thead th', (n) => n.map((x) => x.textContent.replace(/\s+/g, ' ').trim()));
  const col = cabeceras.findIndex((c) => /^Creada/i.test(c));
  di('hay columna «Creada»', col >= 0, cabeceras.join(' | '));
  const fechaFila = await p.evaluate((i) => document.querySelector('tbody tr').children[i]?.textContent.trim() ?? '', col);
  const esperada = tinker(`echo App\\Support\\Fecha::corta(App\\Models\\Organization::find(${creada.org})->created_at);`);
  di('con la fecha en que se creó', fechaFila === esperada, `${fechaFila} = ${esperada}`);

  await p.goto(`${B}/admin/organizaciones/${creada.org}/editar`, { waitUntil: 'networkidle2' });
  const cuenta = await p.$eval('[data-cuenta-ficha]', (n) => n.innerText);
  di('la ficha enseña nombre, correo y alta de la cuenta', cuenta.includes('Persona de Prueba') && cuenta.includes(CORREO) && /Alta:/.test(cuenta), cuenta.replace(/\s+/g, ' '));
  const otros = await p.$eval('[data-correos-ficha]', (n) => n.innerText);
  di('y los otros correos de contacto', otros.includes(CONTACTO_ACT) && otros.includes(`ficha.${SELLO}@ejemplo.cl`) && ! otros.includes(CORREO));
  di('y cuándo se creó la organización', await p.evaluate(() => document.body.innerText.includes('Organización creada')));

  t('7 · Tooltip del botón de editar (panel del organizador)');

  const p2 = await (await nav.createBrowserContext()).newPage();
  await p2.setViewport({ width: 1440, height: 1000 });
  await p2.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p2.type('input[type="email"]', ORG);
  await p2.type('input[type="password"]', CLAVE_ORG);
  await Promise.all([p2.waitForNavigation({ waitUntil: 'networkidle2' }), p2.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
  await p2.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });

  const tip = () => p2.$eval('a.sqbtn.con-tooltip', (a) => {
    const s = getComputedStyle(a, '::after');
    return { texto: s.content, opacidad: parseFloat(s.opacity) };
  });
  const antes = await tip();
  di('el botón de editar lleva el tooltip «Editar actividad»', antes.texto === '"Editar actividad"', antes.texto);
  di('escondido mientras no se pasa por encima', antes.opacidad === 0, String(antes.opacidad));
  await p2.hover('a.sqbtn.con-tooltip');
  await esperar(400);
  di('**al pasar por encima se ve**', (await tip()).opacidad === 1);
  await p2.mouse.move(5, 5);
  await p2.keyboard.press('Tab');
  await p2.evaluate(() => document.activeElement.blur());
  await p2.focus('a.sqbtn.con-tooltip');
  await p2.keyboard.press('Shift');
  await esperar(400);
  di('y al enfocarlo con el teclado', (await tip()).opacidad === 1);
  di('el nombre accesible sigue siendo «Editar actividad»', await p2.$eval('a.sqbtn.con-tooltip', (a) => a.getAttribute('aria-label') === 'Editar actividad'));
  di('sin el `title` nativo, que saldría encima del propio', await p2.$eval('a.sqbtn.con-tooltip', (a) => ! a.hasAttribute('title')));

  di('Sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  await nav.close();
  console.log(`\n  (${limpiar()})`);
}

console.log(`\n  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
