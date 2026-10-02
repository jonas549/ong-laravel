// Punto 1 de la tanda del 30/09: una organizadora con sesión abierta ve su
// propia organización rechazada como duplicada.
//
// EL CASO: la cuenta existe y entra, pero NO tiene organización enlazada
// (`organizations.user_id` no apunta a ella). Pasa con tres caminos del panel:
// crear un usuario organizador en Panel → Usuarios, cambiarle el rol a una
// cuenta de administración, o eliminar la ficha de su organización. Su
// organización, en cambio, sí está en el listado: importada y libre.
//
// Lo que veía:
//   - el paso 3 le pedía nombre y logo, como a quien no tiene ficha;
//   - al escribir su nombre, «Ya hay una organización registrada con ese
//     nombre… inicia sesión con la cuenta que la creó»;
//   - y al enviar, un error en vez de la actividad.
//
// Lo que se comprueba:
//   A · cuenta SIN organización + su organización libre en el listado:
//       la encuentra, la reclama con la sesión puesta y publica.
//   B · cuenta SIN organización y una organización nueva: la crea y publica.
//   C · cuenta CON organización completa (el control): se salta el paso 3 y
//       publica sin tocar la ficha.
//   D · el buscador no le dice «ya tiene cuenta, inicia sesión» a la dueña
//       de esa misma organización.
//
//   node pruebas/org-propia.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

const SELLO = Date.now();
const CLAVE = 'org-propia-2026';
const LOGO = 'storage/organizaciones/prueba.png';

const N = { correo: `natalia.${SELLO}@ejemplo.cl` };          // A: sin ficha, la suya libre
const M = { correo: `nueva.${SELLO}@ejemplo.cl` };            // B: sin ficha, nombre nuevo
const C = { correo: `completa.${SELLO}@ejemplo.cl` };         // C y D: ficha completa
const ORG_LIBRE = `Fundación Propia ${SELLO}`;
const ORG_NUEVA = `Agrupación Nueva ${SELLO}`;
const ORG_C = `Corporación Completa ${SELLO}`;

/** Un organizador como lo crea Panel → Usuarios: la cuenta y nada más. */
const cuenta = (c) => tinker(
  `$u = new App\\Models\\User(['name' => 'Prueba', 'email' => '${c.correo}', 'password' => bcrypt('${CLAVE}')]);`
  + ` $u->forceFill(['role' => App\\Models\\User::ROL_ORGANIZER, 'is_active' => true, 'email_verified_at' => now()])->save();`
  + ` echo $u->id;`
);

cuenta(N);
cuenta(M);
const idC = cuenta(C);

// La del listado: libre, con tipo y logo, como quedaron las importadas.
tinker(
  `App\\Models\\Organization::create(['user_id' => null, 'nombre' => '${ORG_LIBRE}', 'slug' => 'propia-${SELLO}',`
  + ` 'tipo' => 'Organización sin fines de lucro', 'logo_path' => '${LOGO}', 'activo' => true]); echo 'OK';`
);
tinker(
  `App\\Models\\Organization::create(['user_id' => ${idC}, 'nombre' => '${ORG_C}', 'slug' => 'completa-${SELLO}',`
  + ` 'tipo' => 'Organización sin fines de lucro', 'logo_path' => '${LOGO}', 'activo' => true]); echo 'OK';`
);

const limpiar = () => tinker(
  `foreach (['${N.correo}','${M.correo}','${C.correo}'] as $c) { $u = App\\Models\\User::where('email',$c)->first();`
  + ` if ($u) { foreach (App\\Models\\Organization::withTrashed()->where('user_id',$u->id)->get() as $o) {`
  + ` foreach ($o->activities()->withTrashed()->get() as $a) { $a->terms()->detach(); $a->statusLogs()->delete(); $a->forceDelete(); }`
  + ` $o->forceDelete(); } App\\Models\\AccessLog::where('user_id',$u->id)->delete(); $u->forceDelete(); } }`
  + ` App\\Models\\Organization::withTrashed()->whereIn('nombre',['${ORG_LIBRE}','${ORG_NUEVA}','${ORG_C}'])->forceDelete();`
  + ` echo 'LIMPIO';`
);

/** Lo que la base dice de una cuenta después de enviar. */
const enBase = (c) => JSON.parse(tinker(
  `$u = App\\Models\\User::where('email','${c.correo}')->first(); $o = $u->organization;`
  + ` echo json_encode(['org' => $o?->nombre, 'orgs' => App\\Models\\Organization::where('user_id',$u->id)->count(),`
  + ` 'actividades' => $o ? $o->activities()->count() : 0,`
  + ` 'conEseNombre' => App\\Models\\Organization::where('nombre', $o?->nombre ?? '-')->count()]);`
));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));

const CAMPOS = ['org_nombre', 'org_tipo_otro', 'org_unidad_educativa', 'org_logo'];
const pideEnPaso3 = () => p.evaluate((campos) => campos.filter((c) => {
  const e = document.querySelector(`[data-campo="${c}"]`);
  return e && e.getBoundingClientRect().height > 0;
}), CAMPOS);

const wiz = (f, ...args) => p.evaluate(f, ...args);
const estado = () => wiz(() => {
  const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
  return { salta3: d.saltaPaso3(), reclamando: d.reclamando, tomada: d.orgTomada?.nombre ?? null };
});

const entrarComo = async (c) => {
  const ctx = p.browserContext();
  for (const k of await p.cookies()) await p.deleteCookie(k);
  await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', c.correo);
  await p.type('input[type="password"]', CLAVE);
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('form[method="POST"]').submit()),
  ]);
  return ctx;
};

const abrirWizard = async () => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(300);
};

const irAPaso = async (n) => {
  await wiz((n) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = n; }, n);
  await esperar(250);
};

const valores = (sel) => p.$$eval(sel + ' option', (o) => o.map((x) => x.value).filter(Boolean));

/** Escribe el nombre en el buscador y devuelve las sugerencias que salen. */
const buscar = async (texto) => {
  await p.click('input[name="org_nombre"]', { clickCount: 3 });
  await p.keyboard.down('Control'); await p.keyboard.press('A'); await p.keyboard.up('Control');
  await p.keyboard.press('Backspace');
  await p.type('input[name="org_nombre"]', texto);
  // `artisan serve` atiende de una en una, y una consulta a Photon pendiente
  // retiene la del buscador: se espera a la respuesta, no un tiempo fijo.
  await p.waitForFunction((t) => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    return ! d.buscando && d.sugerencias.some((s) => s.nombre === t);
  }, { timeout: 10000 }, texto).catch(() => null);
  return wiz(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).sugerencias.map((s) => s.nombre));
};

const elegirSugerencia = async (nombre) => {
  await wiz((nombre) => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    d.elegirOrg(d.sugerencias.find((s) => s.nombre === nombre));
  }, nombre);
  await esperar(300);
};

const rellenarActividad = async (titulo) => {
  await irAPaso(4);
  await p.type('input[name="titulo"]', titulo);
  await p.type('textarea[name="descripcion"]', 'Actividad de la prueba de organización propia.');
  await p.type('input[name="fecha_inicio"]', '04122026');
  await p.select('select[name="region_id"]', (await valores('select[name="region_id"]'))[0]);
  await p.waitForFunction(() => [...document.querySelectorAll('select[name="commune_id"] option')].some((o) => o.value));
  await p.select('select[name="commune_id"]', (await valores('select[name="commune_id"]'))[0]);
  await p.type('input[name="direccion"]', 'Calle Falsa 123');
  await wiz(() => {
    for (const g of ['temas', 'caracteristicas', 'publicos']) document.querySelector(`[data-campo="${g}"] button.chip`).click();
  });
  await esperar(200);
};

/** Envía y devuelve a dónde fue a parar y qué errores pintó. */
const enviar = async () => {
  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => null),
    p.click('button[type="submit"]'),
  ]);
  await esperar(300);
  const avisos = await p.$$eval('.field-error, [data-resumen-errores] li', (n) => n
    .filter((e) => e.offsetParent !== null).map((e) => e.innerText.trim()).filter(Boolean));
  const titulo = await p.title();
  return { url: p.url().replace(B, ''), avisos: [...new Set(avisos)], titulo };
};

try {
  /* ═══════════════ A ════════════════════════════════════════════ */
  t('A — sin ficha enlazada, su organización libre en el listado');

  await entrarComo(N);
  di('La sesión queda abierta', ! p.url().includes('/login'), p.url().replace(B, ''));

  // Así está la cuenta en producción hasta que reclame la suya: sin ficha. Su
  // panel tiene que abrirse igual, no dar un 500 por la organización que falta.
  for (const ruta of ['/mi-cuenta/actividades', '/mi-cuenta/inscritos', '/mi-cuenta/evaluaciones', '/mi-cuenta/perfil']) {
    const res = await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
    di(`Sin ficha, ${ruta} abre`, res.status() === 200, String(res.status()));
  }

  await abrirWizard();
  await irAPaso(3);
  di('El paso 3 pide nombre (no tiene ficha)', (await pideEnPaso3()).includes('org_nombre'), (await pideEnPaso3()).join(','));

  const sugA = await buscar(ORG_LIBRE);
  di('El buscador la ofrece', sugA.includes(ORG_LIBRE), sugA.join(' | '));
  await elegirSugerencia(ORG_LIBRE);
  let e = await estado();
  di('Al elegirla, se reclama (no «ya tiene cuenta»)', e.reclamando && ! e.tomada, JSON.stringify(e));
  di('Reclamándola ya no pide el logo', ! (await pideEnPaso3()).includes('org_logo'), (await pideEnPaso3()).join(','));

  await rellenarActividad(`Actividad A ${SELLO}`);
  let r = await enviar();
  di('El envío llega a la pantalla final', /\/listo/.test(r.url), r.url);
  di('Sin avisos de error', r.avisos.length === 0, r.avisos.join(' / ').slice(0, 160));
  let db = enBase(N);
  di('La organización del listado queda enlazada a su cuenta', db.org === ORG_LIBRE && db.orgs === 1, JSON.stringify(db));
  di('No se creó un duplicado', db.conEseNombre === 1, `${db.conEseNombre} con ese nombre`);
  di('Y la actividad cuelga de ella', db.actividades === 1);

  // Ya enlazada, la segunda vez es la de siempre: se salta el paso 3.
  await abrirWizard();
  e = await estado();
  di('Al volver, el wizard ya se salta el paso 3', e.salta3, JSON.stringify(e));

  /* ═══════════════ B ════════════════════════════════════════════ */
  t('B — sin ficha enlazada, organización nueva');

  await entrarComo(M);
  await abrirWizard();
  await wiz(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).tipo = 'Otra'; });
  await irAPaso(3);
  await buscar(ORG_NUEVA);
  await p.type('input[name="org_tipo_otro"]', 'Junta de vecinos');
  await rellenarActividad(`Actividad B ${SELLO}`);
  r = await enviar();
  di('El envío llega a la pantalla final', /\/listo/.test(r.url), r.url);
  di('Sin avisos de error', r.avisos.length === 0, r.avisos.join(' / ').slice(0, 160));
  db = enBase(M);
  di('Se creó su organización y queda enlazada', db.org === ORG_NUEVA && db.orgs === 1, JSON.stringify(db));
  di('Con la actividad dentro', db.actividades === 1);

  /* ═══════════════ C ════════════════════════════════════════════ */
  t('C — ficha completa (el control)');

  await entrarComo(C);
  await abrirWizard();
  e = await estado();
  di('Se salta el paso 3', e.salta3, JSON.stringify(e));
  await rellenarActividad(`Actividad C ${SELLO}`);
  r = await enviar();
  di('El envío llega a la pantalla final', /\/listo/.test(r.url), r.url);
  di('Sin avisos de error', r.avisos.length === 0, r.avisos.join(' / ').slice(0, 160));
  db = enBase(C);
  di('Sigue con su única organización, sin duplicar', db.org === ORG_C && db.orgs === 1 && db.conEseNombre === 1, JSON.stringify(db));

  /* ═══════════════ D ════════════════════════════════════════════ */
  t('D — el buscador y la dueña');

  const r2 = await p.evaluate(async (q) => (await (await fetch(`/organizaciones/buscar?q=${encodeURIComponent(q)}`,
    { headers: { Accept: 'application/json' } })).json()).organizaciones, ORG_C);
  const propia = r2.find((o) => o.nombre === ORG_C);
  di('Su organización sale marcada como propia', propia?.propia === true, JSON.stringify(propia));

  await abrirWizard();
  await irAPaso(3);
  // El campo va escondido con la ficha completa; se fuerza para probar el aviso.
  await wiz((n) => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    d.elegirOrg({ id: 0, nombre: n, libre: false, propia: true });
  }, ORG_C);
  e = await estado();
  di('Elegir la propia no dice «ya tiene cuenta, inicia sesión»', ! e.tomada, JSON.stringify(e));

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
