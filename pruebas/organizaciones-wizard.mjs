// P9, P10 y P11 — el buscador de organizaciones del paso 3.
//
// El circuito que abre esto: el cliente carga su listado histórico de
// organizaciones SIN cuenta, cada una llega al wizard, se encuentra en el
// buscador y se pone su propia contraseña. Lo que se comprueba:
//
//   P9  — escribir dos letras ofrece las que ya están, distinguiendo las
//         libres de las que ya tienen cuenta.
//   P10 — al elegir una libre, no se le vuelve a pedir el logo ni el tipo, y
//         al publicar la actividad queda colgada de ESA organización, sin
//         crear un duplicado.
//   P11 — un correo que ya tiene cuenta lo dice con las dos salidas a mano.
//
// Y lo que no puede pasar: reclamar una organización que ya tiene dueño
// cambiando el número del campo oculto.
//
// Antes:
//   php artisan dps:importar-organizaciones --ejemplo
//
//   node pruebas/organizaciones-wizard.mjs
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
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();

/*
 * El escenario se monta aquí y se deshace al final.
 *
 * La primera versión usaba una organización del importador de ejemplo y sólo
 * pasaba una vez: al reclamarla deja de estar libre, y la segunda pasada
 * fallaba en cinco sitios sin que nada hubiera cambiado en el código. Un
 * nombre irrepetible por ejecución quita el problema de raíz.
 */
const SELLO = Date.now();
/*
 * «Fundación Junto …» y no «Fundación Prueba …»: desde la importación del
 * listado del cliente hay doscientas «FUNDACIÓN …», y el buscador sólo
 * devuelve ocho. Las dos tienen que caber juntas en esa primera tanda.
 */
const LIBRE = `Fundación Junto Prueba Libre ${SELLO}`;
const TOMADA = 'Fundación Junto al Barrio';

tinker(
  `App\\Models\\Organization::create(['user_id' => null, 'nombre' => '${LIBRE}',`
  + ` 'tipo' => 'Organización sin fines de lucro', 'activo' => true, 'verificada' => false]);`
  + ` echo 'ESCENARIO';`
);

const limpiar = () => {
  tinker(
    `$o = App\\Models\\Organization::withTrashed()->where('nombre','${LIBRE}')->first();`
    + ` if ($o) { $o->activities()->forceDelete(); $u = $o->user; $o->forceDelete(); $u?->forceDelete(); }`
    + ` App\\Models\\Organization::withTrashed()->where('nombre','Organización del correo repetido')->forceDelete();`
    + ` echo 'LIMPIO';`
  );
};

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

/** Abre el wizard y llega al paso 3, que es donde vive todo esto. */
const alPaso3 = async () => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  // Se salta la navegación por los pasos: lo que se prueba es el paso 3.
  /*
   * La raíz del wizard y no el primer `[x-data]` de la página: el header monta
   * el suyo, así que el primero es el menú y `paso` no existe ahí.
   */
  await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 3; });
  await esperar(250);
};

/* ═══════════════════ P9 — el buscador ════════════════════════════ */

t('P9 — escribir el nombre ofrece las organizaciones que ya están');

await alPaso3();

di('El campo de nombre es un buscador',
  await p.$eval('input[name="org_nombre"]', (n) => n.getAttribute('role') === 'combobox'));

// Una sola letra no busca: sería pasear la tabla entera.
await p.type('input[name="org_nombre"]', 'F');
await esperar(500);
di('Con una sola letra no sugiere nada', await p.$eval('.org-sugerencias', (n) => n.getBoundingClientRect().height === 0));

await p.type('input[name="org_nombre"]', 'undación Junto');
await p.waitForFunction(() => document.querySelectorAll('.org-sugerencia').length > 0, { timeout: 5000 });

const sugerencias = await p.$$eval('.org-sugerencia', (n) => n.map((b) => b.innerText.replace(/\s+/g, ' ').trim()));
di('Sugiere las que coinciden', sugerencias.length >= 2, sugerencias.join(' | '));
di('Distingue la que está libre', sugerencias.some((s) => s.includes(LIBRE) && /en el listado/i.test(s)));
di('Y la que ya tiene cuenta', sugerencias.some((s) => s.includes(TOMADA) && /ya tiene cuenta/i.test(s)));

/* ═══════════════════ P11 (su otra cara) — la tomada ══════════════ */

t('Elegir una que ya tiene cuenta manda a iniciar sesión');

await p.evaluate((n) => {
  [...document.querySelectorAll('.org-sugerencia')].find((b) => b.innerText.includes(n))?.click();
}, TOMADA);
await esperar(300);

const avisoTomada = await texto();
di('Dice que ya tiene cuenta', avisoTomada.includes('ya tiene una cuenta'));
di('Con enlace a iniciar sesión', await p.evaluate(() => !! [...document.querySelectorAll('a')]
  .find((a) => /inicia sesión/i.test(a.textContent) && a.getAttribute('href').includes('/mi-cuenta/login'))));
di('Y a recuperar la contraseña', await p.evaluate(() => !! [...document.querySelectorAll('a')]
  .find((a) => /recupera la contraseña/i.test(a.textContent) && a.getAttribute('href').includes('recuperar'))));
di('No se marca como reclamada', await p.$eval('input[name="org_id"]', (n) => n.value === ''));

/* ═══════════════════ P10 — reclamar una libre ════════════════════ */

t('P10 — al elegir una libre, no se le vuelve a pedir nada suyo');

await alPaso3();
await p.type('input[name="org_nombre"]', `Prueba Libre ${SELLO}`);
await p.waitForFunction(() => document.querySelectorAll('.org-sugerencia').length > 0, { timeout: 5000 });
await p.evaluate((n) => {
  [...document.querySelectorAll('.org-sugerencia')].find((b) => b.innerText.includes(n))?.click();
}, LIBRE);
await esperar(300);

di('El nombre queda escrito', await p.$eval('input[name="org_nombre"]', (n) => n.value) === LIBRE);
di('Y su id viaja en el formulario', await p.$eval('input[name="org_id"]', (n) => Number(n.value) > 0),
  await p.$eval('input[name="org_id"]', (n) => n.value));

di('**Ya no se le pide el logo**', await p.evaluate(() => {
  const campo = document.querySelector('input[name="org_logo"]');
  const caja = campo?.closest('[x-show]');
  return ! caja || caja.getBoundingClientRect().height === 0;
}));
di('Se le dice que la encontramos', (await texto()).includes('Encontramos tu organización en nuestro listado'));
di('Y puede deshacerlo', (await texto()).includes('No es ésta'));

await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => b.textContent.includes('No es ésta'))?.click());
await esperar(250);
di('Al deshacerlo vuelve a pedirse el logo', await p.evaluate(() => {
  const caja = document.querySelector('input[name="org_logo"]')?.closest('[x-show]');
  return !! caja && caja.getBoundingClientRect().height > 0;
}));
di('Y el id deja de viajar', await p.$eval('input[name="org_id"]', (n) => n.value === ''));

/* ═══════════════════ Reclamar de verdad ══════════════════════════ */

t('Publicar reclamando la organización: una cuenta nueva, sin duplicar');

const idLibre = ultima(tinker(
  `echo App\\Models\\Organization::where('nombre','${LIBRE}')->value('id');`
));
const cuantasAntes = Number(ultima(tinker(
  `echo App\\Models\\Organization::where('nombre','${LIBRE}')->count();`
)));

const correo = `reclama${Date.now()}@ejemplo.cl`;

/*
 * El wizard entero por HTTP: rellenar los cinco pasos a mano en el navegador
 * es otra prueba (`campos-formulario.mjs`) y aquí lo que importa es a qué
 * organización acaba colgada la actividad.
 */
const d0 = {
  tema: ultima(tinker("echo App\\Models\\TaxonomyTerm::where('grupo','tema')->value('id');")),
  carac: ultima(tinker("echo App\\Models\\TaxonomyTerm::where('grupo','caracteristica')->value('id');")),
  publico: ultima(tinker("echo App\\Models\\TaxonomyTerm::where('grupo','publico')->value('id');")),
};

const envio = await p.evaluate(async (d) => {
  const doc = await (await fetch(d.base + '/publicar-actividad', { credentials: 'same-origin' })).text();
  const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];

  const f = new FormData();
  f.append('_token', token);
  f.append('org_nombre', d.nombre);
  f.append('org_id', d.orgId);
  f.append('org_tipo', 'Organización sin fines de lucro');
  f.append('email', d.correo);
  f.append('password', 'ClaveLarga123');
  f.append('password_confirmation', 'ClaveLarga123');
  f.append('titulo', 'Actividad de organización reclamada');
  f.append('descripcion', 'Prueba del circuito de reclamar una organización del listado.');
  f.append('formato', 'Online');
  f.append('sin_fecha_definida', '1');
  // Con corchetes: son arrays y la regla los valida como tales.
  f.append('temas[]', d.tema);
  f.append('caracteristicas[]', d.carac);
  f.append('publicos[]', d.publico);

  const r = await fetch(d.base + '/publicar-actividad', { method: 'POST', body: f, credentials: 'same-origin' });

  return { estado: r.status, url: r.url, cuerpo: (await r.text()).slice(0, 4000) };
}, {
  base: B,
  nombre: LIBRE,
  orgId: idLibre,
  correo,
  tema: ultima(tinker("echo App\\Models\\TaxonomyTerm::where('grupo','tema')->value('id');")),
  carac: ultima(tinker("echo App\\Models\\TaxonomyTerm::where('grupo','caracteristica')->value('id');")),
  publico: ultima(tinker("echo App\\Models\\TaxonomyTerm::where('grupo','publico')->value('id');")),
});

di('El envío se acepta', envio.estado === 200 && envio.url.includes('/listo'), `${envio.estado} → ${envio.url.replace(B, '')}`);

const resultado = ultima(tinker(
  `$o = App\\Models\\Organization::find(${idLibre});`
  + ` $u = App\\Models\\User::where('email','${correo}')->first();`
  + ` echo ($o->user_id === ($u->id ?? 0) ? 'RECLAMADA' : 'NO')`
  + ` .'|'.App\\Models\\Organization::where('nombre','${LIBRE}')->count()`
  + ` .'|'.$o->activities()->count();`
));
const [reclamada, cuantas, actividades] = resultado.split('|');

di('**La organización queda a nombre de la cuenta nueva**', reclamada === 'RECLAMADA');
di('**Y NO se creó un duplicado**', Number(cuantas) === cuantasAntes, `${cuantas} con ese nombre`);
di('La actividad cuelga de ella', Number(actividades) >= 1, `${actividades} actividades`);
di('Ya no se ofrece como libre', ! JSON.parse(await p.evaluate(async (u) => (await fetch(u)).text(),
  `${B}/organizaciones/buscar?q=Prueba%20Libre%20${SELLO}`)).organizaciones.some((o) => o.libre && o.nombre === LIBRE));

/* ═══════════════════ P11 — el correo repetido ════════════════════ */

t('P11 — un correo que ya tiene cuenta lo dice, y dice a dónde ir');

/*
 * Primero se cierra la sesión. Publicar deja al nuevo usuario dentro
 * (`Auth::login` tras crear la cuenta), y con sesión abierta el wizard ni
 * siquiera pide correo: la regla pasa a `prohibited` y el error sería otro.
 */
await p.evaluate(async (base) => {
  const doc = await (await fetch(base + '/', { credentials: 'same-origin' })).text();
  const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
  await fetch(base + '/mi-cuenta/logout', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: '_token=' + encodeURIComponent(token),
  });
}, B);

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });

const repetido = await p.evaluate(async (d) => {
  const doc = await (await fetch(d.base + '/publicar-actividad', { credentials: 'same-origin' })).text();
  const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];

  const f = new FormData();
  f.append('_token', token);
  f.append('org_nombre', 'Organización del correo repetido');
  f.append('org_tipo', 'Organización sin fines de lucro');
  f.append('email', d.correo);
  f.append('password', 'ClaveLarga123');
  f.append('password_confirmation', 'ClaveLarga123');
  f.append('titulo', 'Otra actividad');
  f.append('descripcion', 'Prueba del correo repetido.');
  f.append('formato', 'Online');
  f.append('sin_fecha_definida', '1');
  f.append('temas[]', d.tema);
  f.append('caracteristicas[]', d.carac);
  f.append('publicos[]', d.publico);

  const r = await fetch(d.base + '/publicar-actividad', { method: 'POST', body: f, credentials: 'same-origin' });

  return await r.text();
}, { base: B, correo, tema: d0.tema, carac: d0.carac, publico: d0.publico });

di('Dice que el usuario ya existe', repetido.includes('Este usuario ya existe'));

/*
 * Y los dos enlaces tienen que estar JUNTO AL CAMPO, no sólo en algún sitio de
 * la página: el pie ya trae un enlace a iniciar sesión, así que buscarlos en
 * el HTML entero pasaría aunque no se hubiera pintado nada.
 */
const bloqueEnlaces = (repetido.match(
  /Este usuario ya existe[\s\S]{0,600}?<\/label>/
) ?? [''])[0];
di('Con enlace a iniciar sesión, pegado al campo', bloqueEnlaces.includes('/mi-cuenta/login'));
di('Y a recuperar la contraseña', bloqueEnlaces.includes('recuperar-contrasena'));

/* ═══════════════════ No se puede robar una organización ══════════ */

t('Un id cambiado a mano no reclama una organización ajena');

const idTomada = ultima(tinker(`echo App\\Models\\Organization::where('nombre','${TOMADA}')->value('id');`));
const duenioAntes = ultima(tinker(`echo App\\Models\\Organization::find(${idTomada})->user_id;`));

const robo = await p.evaluate(async (d) => {
  const doc = await (await fetch(d.base + '/publicar-actividad', { credentials: 'same-origin' })).text();
  const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];

  const f = new FormData();
  f.append('_token', token);
  f.append('org_nombre', 'Intento de robo');
  f.append('org_id', d.idTomada);
  f.append('org_tipo', 'Organización sin fines de lucro');
  f.append('email', 'ladron' + Date.now() + '@ejemplo.cl');
  f.append('password', 'ClaveLarga123');
  f.append('password_confirmation', 'ClaveLarga123');
  f.append('titulo', 'Actividad robada');
  f.append('descripcion', 'No debería llegar a ninguna parte.');
  f.append('formato', 'Online');
  f.append('sin_fecha_definida', '1');

  const r = await fetch(d.base + '/publicar-actividad', { method: 'POST', body: f, credentials: 'same-origin' });

  return { url: r.url, cuerpo: await r.text() };
}, { base: B, idTomada });

di('El formulario lo rechaza', ! robo.url.includes('/listo'));
di('**Y la organización sigue teniendo su mismo dueño**',
  ultima(tinker(`echo App\\Models\\Organization::find(${idTomada})->user_id;`)) === duenioAntes);

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

limpiar();

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
