// C1 — el buscador de organizaciones, también al crear cuenta.
//
// P9, P10 y P11 se hicieron en el paso 3 del wizard y la pantalla de crear
// cuenta de organizador se quedó con un campo de texto normal durante una
// tanda entera. Esta prueba comprueba **las dos pantallas contra el mismo
// listado de comprobaciones**: si alguien vuelve a tocar una sin la otra, esto
// lo dice.
//
//   node pruebas/registro-organizacion.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultimaLinea = (texto) => texto.split('\n').filter((x) => x.trim()).pop().trim();

/* Un nombre irrepetible por pasada: reclamar deja la organización tomada. */
const SELLO = Date.now();
const LIBRE = `Fundación Prueba Registro ${SELLO}`;
const TOMADA = 'Fundación Junto al Barrio';

tinker(
  `App\\Models\\Organization::create(['user_id' => null, 'nombre' => '${LIBRE}',`
  + ` 'tipo' => 'Institución educativa', 'activo' => true, 'verificada' => false]);`
  + ` echo 'ESCENARIO';`
);

const limpiar = () => tinker(
  `$o = App\\Models\\Organization::withTrashed()->where('nombre','${LIBRE}')->first();`
  + ` if ($o) { $u = $o->user; $o->forceDelete(); $u?->forceDelete(); }`
  + ` App\\Models\\Organization::withTrashed()->where('nombre','like','Registro suelto ${SELLO}%')->forceDelete();`
  + ` App\\Models\\User::where('email','like','reg${SELLO}%')->forceDelete();`
  + ` echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

const salir = async () => {
  if (! p.url().startsWith(B)) await p.goto(`${B}/`, { waitUntil: 'domcontentloaded' });

  await p.evaluate(async (base) => {
    for (const ruta of ['/mi-cuenta/logout', '/admin/logout']) {
      const doc = await (await fetch(base + '/', { credentials: 'same-origin' })).text();
      const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
      if (! token) continue;
      await fetch(base + ruta, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + encodeURIComponent(token),
      });
    }
  }, B);
};

/*
 * Las dos pantallas, contra la misma lista. La única diferencia es cómo se
 * llega y qué campo se esconde al reclamar: el wizard esconde el logo y el
 * registro, el tipo de organización.
 */
const PANTALLAS = [
  {
    nombre: 'Wizard (paso 3)',
    abrir: async () => {
      await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
      await p.waitForFunction(() => window.Alpine !== undefined);
      await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 3; });
      await esperar(300);
    },
    campoQueDesaparece: '[data-campo="org_logo"]',
  },
  {
    nombre: 'Crear cuenta de organizador',
    abrir: async () => {
      await salir();
      await p.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });
      await p.waitForFunction(() => window.Alpine !== undefined);
      await esperar(250);
    },
    campoQueDesaparece: 'select[name="org_tipo"]',
  },
];

for (const pantalla of PANTALLAS) {
  t(`C1 — ${pantalla.nombre}`);

  await pantalla.abrir();

  di('El nombre de la organización es un buscador',
    await p.$eval('input[name="org_nombre"]', (n) => n.getAttribute('role') === 'combobox'));

  await p.type('input[name="org_nombre"]', `Prueba Registro ${SELLO}`);
  const salieron = await p.waitForFunction(() => document.querySelectorAll('.org-sugerencia').length > 0, { timeout: 8000 })
    .then(() => true).catch(() => false);

  di('Escribir ofrece las organizaciones que ya están', salieron,
    salieron ? (await p.$$eval('.org-sugerencia', (n) => n.map((b) => b.innerText.replace(/\s+/g, ' ').trim())))[0] : '(ninguna)');

  if (! salieron) continue;

  di('Y las distingue: ésta está libre',
    await p.$eval('.org-sugerencia', (b) => /en el listado/i.test(b.innerText)));

  /* ── P10: reclamarla ── */
  await p.evaluate(() => document.querySelector('.org-sugerencia').click());
  await esperar(350);

  di('Al elegirla, su id viaja en el formulario',
    await p.$eval('input[name="org_id"]', (n) => Number(n.value) > 0),
    await p.$eval('input[name="org_id"]', (n) => n.value));
  di('El nombre queda escrito en el campo',
    await p.$eval('input[name="org_nombre"]', (n) => n.value) === LIBRE,
    await p.$eval('input[name="org_nombre"]', (n) => n.value));
  di('**Ya no se le piden sus datos**', await p.evaluate((sel) => {
    const campo = document.querySelector(sel);

    return ! campo || campo.getBoundingClientRect().height === 0;
  }, pantalla.campoQueDesaparece), pantalla.campoQueDesaparece);
  di('Se le dice que la encontramos', (await texto()).includes('Encontramos tu organización en nuestro listado'));

  /* ── Deshacerlo ── */
  await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => b.textContent.includes('No es ésta'))?.click());
  await esperar(300);
  di('Y puede deshacerlo: vuelven a pedirse', await p.evaluate((sel) => {
    const campo = document.querySelector(sel);

    return !! campo && campo.getBoundingClientRect().height > 0;
  }, pantalla.campoQueDesaparece));
  di('Y el id deja de viajar', await p.$eval('input[name="org_id"]', (n) => n.value === ''));

  /* ── P11: la que ya tiene cuenta ── */
  await pantalla.abrir();
  await p.type('input[name="org_nombre"]', 'Junto al Barrio');
  await p.waitForFunction(() => document.querySelectorAll('.org-sugerencia').length > 0, { timeout: 8000 }).catch(() => null);
  await p.evaluate((n) => {
    [...document.querySelectorAll('.org-sugerencia')].find((b) => b.innerText.includes(n))?.click();
  }, TOMADA);
  await esperar(300);

  di('Una que ya tiene cuenta lo dice', (await texto()).includes('ya tiene una cuenta'));
  di('Con enlace a iniciar sesión', await p.evaluate(() => !! [...document.querySelectorAll('a')]
    .find((a) => /inicia sesión/i.test(a.textContent) && (a.getAttribute('href') ?? '').includes('/mi-cuenta/login'))));
  di('Y a recuperar la contraseña', await p.evaluate(() => !! [...document.querySelectorAll('a')]
    .find((a) => /recupera la contraseña/i.test(a.textContent) && (a.getAttribute('href') ?? '').includes('recuperar'))));
  di('Y no se marca como reclamada', await p.$eval('input[name="org_id"]', (n) => n.value === ''));
}

/* ═══════════════ Reclamar de verdad, desde el registro ═══════════ */

t('C1 — crear la cuenta reclamando: sin duplicar y con el tipo de ella');

const idLibre = ultimaLinea(tinker(`echo App\\Models\\Organization::where('nombre','${LIBRE}')->value('id');`));
const cuantasAntes = Number(ultimaLinea(tinker(`echo App\\Models\\Organization::where('nombre','${LIBRE}')->count();`)));
const correo = `reg${SELLO}@ejemplo.cl`;

await salir();
await p.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });

const envio = await p.evaluate(async (d) => {
  const doc = await (await fetch(d.base + '/mi-cuenta/registro', { credentials: 'same-origin' })).text();
  const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];

  const f = new URLSearchParams();
  f.append('_token', token);
  f.append('org_nombre', d.nombre);
  f.append('org_id', d.orgId);
  // A propósito NO se manda org_tipo: la pantalla no lo pinta al reclamar.
  f.append('name', 'Persona de Prueba');
  f.append('email', d.correo);
  f.append('password', 'ClaveLarga123');
  f.append('password_confirmation', 'ClaveLarga123');

  const r = await fetch(d.base + '/mi-cuenta/registro', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: f.toString(),
  });

  return { url: r.url, cuerpo: (await r.text()).slice(0, 3000) };
}, { base: B, nombre: LIBRE, orgId: idLibre, correo });

di('La cuenta se crea', envio.url.includes('/mi-cuenta/actividades'), envio.url.replace(B, ''));

const resultado = ultimaLinea(tinker(
  `$o = App\\Models\\Organization::find(${idLibre});`
  + ` $u = App\\Models\\User::where('email','${correo}')->first();`
  + ` echo ($o->user_id === ($u->id ?? 0) ? 'RECLAMADA' : 'NO')`
  + ` .'|'.App\\Models\\Organization::where('nombre','${LIBRE}')->count()`
  + ` .'|'.$o->tipo;`
));
const [reclamada, cuantas, tipo] = resultado.split('|');

di('**Queda a nombre de la cuenta nueva**', reclamada === 'RECLAMADA');
di('**Y NO se creó un duplicado**', Number(cuantas) === cuantasAntes, `${cuantas} con ese nombre`);
di('Conserva su tipo, el del listado', tipo === 'Institución educativa', tipo);

/* ═══════════════ P11 en el registro: correo repetido ═════════════ */

t('C1 — un correo que ya tiene cuenta, con las dos salidas');

await salir();
const repetido = await p.evaluate(async (d) => {
  const doc = await (await fetch(d.base + '/mi-cuenta/registro', { credentials: 'same-origin' })).text();
  const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];

  const f = new URLSearchParams();
  f.append('_token', token);
  f.append('org_nombre', `Registro suelto ${d.sello}`);
  f.append('org_tipo', 'Organización sin fines de lucro');
  f.append('name', 'Otra Persona');
  f.append('email', d.correo);
  f.append('password', 'ClaveLarga123');
  f.append('password_confirmation', 'ClaveLarga123');

  const r = await fetch(d.base + '/mi-cuenta/registro', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: f.toString(),
  });

  return await r.text();
}, { base: B, correo, sello: SELLO });

di('Dice que el usuario ya existe', repetido.includes('Este usuario ya existe'));

// Y los enlaces, junto al campo: buscarlos en la página entera pasaría por el
// pie, que ya trae uno a iniciar sesión.
const bloque = (repetido.match(/Este usuario ya existe[\s\S]{0,700}?<\/label>/) ?? [''])[0];
di('Con enlace a iniciar sesión, pegado al campo', bloque.includes('/mi-cuenta/login'));
di('Y a recuperar la contraseña', bloque.includes('recuperar-contrasena'));

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

limpiar();

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
