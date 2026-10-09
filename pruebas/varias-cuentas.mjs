// Varias cuentas por organización — los circuitos de verdad, en Chrome y por
// HTTP con sesión.
//
//   1. Interruptor APAGADO: elegir una organización con cuenta manda a iniciar
//      sesión, y un `org_id` metido a mano no la reclama (lo de siempre).
//   2. ENCENDIDO, wizard sin sesión: la ofrece como «Puedes sumarte», no pide
//      nada de la ficha, esconde los enlaces de la organización; al enviar,
//      la cuenta se suma, la ficha NO cambia, la actividad va a revisión y la
//      principal recibe el aviso. Sin duplicados.
//   3. La cuenta sumada, con sesión: ve sólo lo suyo; la de la principal da
//      403; el perfil y el editor no le dejan tocar la ficha; el wizard se
//      salta los pasos 2 y 3.
//   4. ENCENDIDO, registro (/mi-cuenta/registro): también se suma.
//   5. La principal: ve lo suyo y no lo de las sumadas; sí edita la ficha.
//   6. Panel: la ficha de la organización lista las cuentas; «Hacer
//      principal» y «Sacar de la organización».
//   7. Volver a APAGAR: la sumada sigue entrando y publicando; nadie nuevo se
//      suma.
//
// Monta su propio escenario con un nombre irrepetible y lo deshace al final,
// devolviendo el interruptor a como estaba.
//
//   node pruebas/varias-cuentas.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(70)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();
const json = (php) => JSON.parse(ultima(tinker(php)));

const SELLO = Date.now();
// «Fundación Junto …»: con doscientas «FUNDACIÓN …» importadas, el buscador
// sólo devuelve ocho, y ésta tiene que salir entre ellas.
const ORG = `Fundación Junto Varias ${SELLO}`;
const CLAVE = 'ClaveLarga123';
const PRINCIPAL = `varias.principal.${SELLO}@ejemplo.cl`;
const SUMADA = `varias.sumada.${SELLO}@ejemplo.cl`;
const REGISTRADA = `varias.registro.${SELLO}@ejemplo.cl`;
const TARDIA = `varias.tardia.${SELLO}@ejemplo.cl`;
const PIRATA = 'https://pirata.example.cl';

const interruptor = (valor) => tinker(`App\\Models\\Setting::set('organizacion_varias_cuentas', '${valor}'); echo 'OK';`);
const interruptorAntes = ultima(tinker("echo App\\Models\\Setting::where('clave','organizacion_varias_cuentas')->value('valor') ?? 'FALTA';"));

if (interruptorAntes === 'FALTA') {
  console.log('Falta el ajuste organizacion_varias_cuentas: corre php artisan dps:instalar.');
  process.exit(1);
}

/* ─────────────────────────────── escenario ── */

const D = json(
  `$u = App\\Models\\User::create(['name' => 'Principal Varias', 'email' => '${PRINCIPAL}', 'password' => '${CLAVE}', 'role' => 'organizer', 'is_active' => true]);`
  + ` $u->forceFill(['email_verified_at' => now()])->save();`
  + ` $o = App\\Models\\Organization::create(['nombre' => '${ORG}', 'tipo' => 'Organización sin fines de lucro', 'activo' => true,`
  + ` 'enlace_web' => 'https://original.example.cl', 'enlace_red_social' => 'https://instagram.com/original', 'logo_path' => 'img/varias-${SELLO}.png']);`
  + ` $o->enlazarCuenta($u);`
  + ` $a = App\\Models\\Activity::whereNotNull('published_at')->first()->replicate();`
  + ` $a->forceFill(['organization_id' => $o->id, 'user_id' => $u->id, 'titulo' => 'Actividad de la principal ${SELLO}', 'slug' => 'varias-principal-${SELLO}',`
  + ` 'estado' => 'publicada', 'published_at' => now(), 'destacada' => false])->save();`
  + ` echo json_encode(['org' => $o->id, 'principal' => $u->id, 'actPrincipal' => $a->id]);`
);

const terminos = json(
  "echo json_encode(['tema' => App\\Models\\TaxonomyTerm::where('grupo','tema')->value('id'),"
  + " 'carac' => App\\Models\\TaxonomyTerm::where('grupo','caracteristica')->value('id'),"
  + " 'publico' => App\\Models\\TaxonomyTerm::where('grupo','publico')->value('id')]);"
);

const limpiar = () => tinker(
  `$o = App\\Models\\Organization::withTrashed()->where('nombre', '${ORG}')->first();`
  + ` if ($o) { foreach ($o->activities()->withTrashed()->get() as $a) { $a->registrations()->delete(); $a->forceDelete(); } }`
  + ` foreach (App\\Models\\User::withTrashed()->where('email', 'like', 'varias.%.${SELLO}@ejemplo.cl')->get() as $u) {`
  + ` App\\Models\\AccessLog::where('user_id', $u->id)->orWhere('actor_id', $u->id)->delete(); $u->forceDelete(); }`
  + ` $o?->forceDelete();`
  + ` App\\Models\\Setting::set('organizacion_varias_cuentas', '${interruptorAntes}'); echo 'LIMPIO';`
);

/* ─────────────────────────────── utilidades ── */

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];

/** Una pestaña en un contexto aislado: cookies propias, sesión propia. */
const pestana = async () => {
  const ctx = await nav.createBrowserContext();
  const p = await ctx.newPage();
  await p.setViewport({ width: 1440, height: 1000 });
  p.on('pageerror', (e) => errores.push(String(e)));
  return p;
};

/** Pide algo con la sesión de la pestaña, sin navegar. */
const pedir = (p, ruta, opciones = {}) => p.evaluate(async (d) => {
  const r = await fetch(d.base + d.ruta, { credentials: 'same-origin', redirect: 'follow', ...d.opciones });
  return { estado: r.status, url: r.url.replace(d.base, ''), texto: await r.text() };
}, { base: B, ruta, opciones });

const token = async (p, ruta = '/') => (await pedir(p, ruta)).texto.match(/name="csrf-token" content="([^"]+)"/)?.[1];

/** Un POST de formulario con la sesión de la pestaña. */
const enviar = (p, ruta, campos) => p.evaluate(async (d) => {
  const doc = await (await fetch(d.base + '/', { credentials: 'same-origin' })).text();
  const f = new FormData();
  f.append('_token', (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1]);
  for (const [k, v] of d.campos) f.append(k, v);
  const r = await fetch(d.base + d.ruta, { method: 'POST', body: f, credentials: 'same-origin' });
  return { estado: r.status, url: r.url.replace(d.base, ''), texto: await r.text() };
}, { base: B, ruta, campos: Object.entries(campos).flatMap(([k, v]) => Array.isArray(v) ? v.map((x) => [k, String(x)]) : [[k, String(v)]]) });

// Los avisos de error de una página devuelta, para decir por qué rebotó.
const fallos = (html) => [...html.matchAll(/class="field-error"[^>]*>([^<]+)</g)].map((m) => m[1].trim()).filter(Boolean).join(' | ');

const entrar = (p, ruta, email, password) => enviar(p, ruta, { email, password });

const actividad = (extra = {}) => ({
  titulo: `Actividad sumada ${SELLO}`,
  descripcion: 'Prueba de varias cuentas por organización.',
  formato: 'Online',
  sin_fecha_definida: '1',
  'temas[]': [terminos.tema],
  'caracteristicas[]': [terminos.carac],
  'publicos[]': [terminos.publico],
  ...extra,
});

const wizard = (p) => p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')));
const alPaso3 = async (p) => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 3; });
  await esperar(250);
};
const buscar = async (p, texto) => {
  await p.type('input[name="org_nombre"]', texto);
  await p.waitForFunction(() => document.querySelectorAll('.org-sugerencia').length > 0, { timeout: 6000 });
  return p.$$eval('.org-sugerencia', (n) => n.map((b) => b.innerText.replace(/\s+/g, ' ').trim()));
};
const elegir = (p, nombre) => p.evaluate((n) => {
  [...document.querySelectorAll('.org-sugerencia')].find((b) => b.innerText.includes(n))?.click();
}, nombre);
const seVe = (p, selector) => p.evaluate((s) => {
  const n = document.querySelector(s);
  return !! n && n.getBoundingClientRect().height > 0 && getComputedStyle(n).display !== 'none';
}, selector);

try {
  /* ═══════════════════ 1 · APAGADO ═══════════════════ */

  t('1 · Interruptor apagado: lo de siempre');
  interruptor('0');

  const p1 = await pestana();
  await alPaso3(p1);
  const sug1 = await buscar(p1, `Junto Varias ${SELLO}`);
  di('La ofrece como «Ya tiene cuenta»', sug1.some((s) => s.includes(ORG) && /ya tiene cuenta/i.test(s)), sug1.join(' | '));
  await elegir(p1, ORG);
  await esperar(300);
  di('Elegirla manda a iniciar sesión', await seVe(p1, '[data-org-tomada]'));
  di('…y no se marca para sumarse', await p1.$eval('input[name="org_id"]', (n) => n.value === ''));

  const robo = await enviar(p1, '/publicar-actividad', actividad({
    org_nombre: ORG, org_id: D.org, org_tipo: 'Organización sin fines de lucro',
    email: `varias.robo.${SELLO}@ejemplo.cl`, password: CLAVE, password_confirmation: CLAVE,
  }));
  di('Un org_id metido a mano se rechaza', ! robo.url.includes('/listo') && robo.texto.includes('ya tiene una cuenta'), robo.url);
  di('…y no se crea la cuenta', ultima(tinker(`echo App\\Models\\User::where('email','varias.robo.${SELLO}@ejemplo.cl')->count();`)) === '0');

  /* ═══════════════════ 2 · ENCENDIDO, wizard ═══════════════════ */

  t('2 · Encendido: el wizard sin sesión se suma');
  interruptor('1');

  const p2 = await pestana();
  await alPaso3(p2);
  const sug2 = await buscar(p2, `Junto Varias ${SELLO}`);
  di('La ofrece como «Puedes sumarte»', sug2.some((s) => s.includes(ORG) && /puedes sumarte/i.test(s)), sug2.join(' | '));
  await elegir(p2, ORG);
  await esperar(300);
  di('Dice que se va a sumar y que se avisa a la principal', await seVe(p2, '[data-org-sumandose]')
    && (await p2.$eval('[data-org-sumandose]', (n) => n.innerText)).includes('avisaremos a su cuenta principal'));
  di('No manda a iniciar sesión', ! await seVe(p2, '[data-org-tomada]'));
  di('El id viaja', await p2.$eval('input[name="org_id"]', (n) => n.value) === String(D.org));
  di('No pide el logo', await p2.evaluate(() => {
    const caja = document.querySelector('input[name="org_logo"]')?.closest('[x-show]');
    return ! caja || caja.getBoundingClientRect().height === 0;
  }));
  // Con «Otra» o «Institución educativa» del paso 2 tampoco pide sus campos.
  await p2.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).tipo = 'Institución educativa'; });
  await esperar(200);
  di('No pide la unidad educativa aunque el tipo la pida', ! await seVe(p2, '[data-campo="org_unidad_educativa"]'));
  await p2.evaluate(() => { const w = Alpine.$data(document.querySelector('[x-data^="wizard"]')); w.tipo = 'Organización sin fines de lucro'; w.paso = 4; });
  await esperar(300);
  di('Paso 4: los enlaces de la organización no se enseñan', await p2.evaluate(() => {
    const campo = document.querySelector('input[name="enlace_web"]')?.closest('label');
    return !! campo && campo.getBoundingClientRect().height === 0;
  }));
  di('…y dice quién los cambia', await seVe(p2, '[data-enlaces-de-la-principal]'));

  const antes = json(`echo json_encode(App\\Models\\Organization::find(${D.org})->only(['nombre','tipo','logo_path','enlace_web','enlace_red_social','correo_contacto','user_id']));`);
  const envio = await enviar(p2, '/publicar-actividad', actividad({
    org_nombre: ORG, org_id: D.org,
    // Lo que se intente cambiar de la ficha al sumarse, se ignora.
    org_tipo: 'Otra', org_tipo_otro: 'Pirata', enlace_web: PIRATA, enlace_red_social: PIRATA,
    email: SUMADA, password: CLAVE, password_confirmation: CLAVE,
  }));
  di('El envío se acepta', envio.url.includes('/listo'), `${envio.estado} → ${envio.url}`);

  const tras = json(
    `$o = App\\Models\\Organization::find(${D.org}); $u = App\\Models\\User::where('email','${SUMADA}')->first();`
    + ` $a = App\\Models\\Activity::where('user_id', $u?->id)->latest('id')->first();`
    + ` echo json_encode(['ficha' => $o->only(['nombre','tipo','logo_path','enlace_web','enlace_red_social','correo_contacto','user_id']),`
    + ` 'usuario' => $u?->id, 'orgUsuario' => $u?->organization_id, 'iguales' => App\\Models\\Organization::where('nombre','${ORG}')->count(),`
    + ` 'actividad' => $a?->id, 'actOrg' => $a?->organization_id, 'estado' => $a?->estado, 'motivo' => $a?->statusLogs()->first()?->comentario,`
    + ` 'aviso' => App\\Models\\EmailLog::where('plantilla','cuenta_sumada')->where('related_type', App\\Models\\User::class)->where('related_id', $u?->id)->pluck('to')]);`
  );
  D.sumada = tras.usuario;
  D.actSumada = tras.actividad;
  di('**La cuenta nueva es de esa organización**', tras.orgUsuario === D.org, String(tras.orgUsuario));
  di('**No se creó un duplicado**', tras.iguales === 1, `${tras.iguales} con ese nombre`);
  di('**La ficha no cambió (ni tipo, ni enlaces, ni logo, ni principal)**', JSON.stringify(tras.ficha) === JSON.stringify(antes),
    JSON.stringify(tras.ficha));
  di('La actividad es de la organización y de la cuenta nueva', tras.actOrg === D.org && !! tras.actividad);
  di('**La actividad va a revisión (primera de una cuenta sumada)**', tras.estado === 'revision', `${tras.estado} · ${tras.motivo}`);
  di('**La principal recibe el aviso de cuenta sumada**', tras.aviso.length === 1 && tras.aviso[0] === PRINCIPAL, tras.aviso.join(', '));

  /* ═══════════════════ 3 · La sumada, con sesión ═══════════════════ */

  t('3 · La cuenta sumada ve y toca sólo lo suyo');
  // p2 quedó con la sesión de la cuenta nueva: el wizard la deja dentro.
  const mias = await pedir(p2, '/mi-cuenta/actividades');
  di('Mis actividades: la suya sí', mias.texto.includes(`Actividad sumada ${SELLO}`));
  di('Mis actividades: la de la principal no', ! mias.texto.includes(`Actividad de la principal ${SELLO}`));
  di('Editar la de la principal: 403', (await pedir(p2, `/mi-cuenta/actividades/${D.actPrincipal}/editar`)).estado === 403);
  di('Ver los inscritos de la principal: 403', (await pedir(p2, `/mi-cuenta/actividades/${D.actPrincipal}/participantes`)).estado === 403);
  const suya = await pedir(p2, `/mi-cuenta/actividades/${D.actSumada}/editar`);
  di('Editar la suya: sí', suya.estado === 200);
  di('…sin los campos de enlaces de la organización', suya.texto.includes('data-enlaces-de-la-principal') && ! suya.texto.includes('name="enlace_web"'));
  const perfil = await pedir(p2, '/mi-cuenta/perfil');
  di('El perfil no le ofrece cambiar el logo', perfil.texto.includes('data-ficha-de-otra-cuenta') && ! perfil.texto.includes('name="logo"'));
  const logo = await p2.evaluate(async (base) => {
    const doc = await (await fetch(base + '/mi-cuenta/perfil', { credentials: 'same-origin' })).text();
    const f = new FormData();
    f.append('_token', (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1]);
    f.append('_method', 'PUT');
    const r = await fetch(base + '/mi-cuenta/perfil/logo', { method: 'POST', body: f, credentials: 'same-origin' });
    return r.status;
  }, B);
  di('…y un PUT del logo a mano da 403', logo === 403, String(logo));

  await p2.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p2.waitForFunction(() => window.Alpine !== undefined);
  const w3 = await p2.evaluate(() => {
    const w = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    return { p2: w.saltaPaso2(), p3: w.saltaPaso3(), ajena: w.fichaAjena() };
  });
  di('El wizard con sesión se salta los pasos 2 y 3', w3.p2 && w3.p3, JSON.stringify(w3));
  di('…y esconde los enlaces de la organización', w3.ajena);

  const segunda = await enviar(p2, '/publicar-actividad', actividad({
    titulo: `Segunda sumada ${SELLO}`, org_nombre: ORG, org_tipo: 'Otra', enlace_web: PIRATA,
  }));
  di('Publica una segunda con sesión', segunda.url.includes('/listo'), segunda.url);
  di('…sin tocar los enlaces de la ficha', ultima(tinker(`echo App\\Models\\Organization::find(${D.org})->enlace_web;`)) === antes.enlace_web);

  /* ═══════════════════ 4 · ENCENDIDO, registro ═══════════════════ */

  t('4 · El registro también se suma');
  const p4 = await pestana();
  await p4.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });
  await p4.waitForFunction(() => window.Alpine !== undefined);
  const sug4 = await buscar(p4, `Junto Varias ${SELLO}`);
  di('La ofrece como «Puedes sumarte»', sug4.some((s) => s.includes(ORG) && /puedes sumarte/i.test(s)));
  await elegir(p4, ORG);
  await esperar(300);
  di('Dice que se va a sumar', await seVe(p4, '[data-org-sumandose]'));
  di('No pide el tipo', ! await seVe(p4, 'select[name="org_tipo"]'));
  const reg = await enviar(p4, '/mi-cuenta/registro', {
    org_nombre: ORG, org_id: D.org, name: 'Persona Registro', email: REGISTRADA, password: CLAVE, password_confirmation: CLAVE,
  });
  di('El registro se acepta y entra a su cuenta', reg.url.includes('/mi-cuenta/actividades'), `${reg.estado} → ${reg.url}`);
  const r4 = json(
    `$u = App\\Models\\User::where('email','${REGISTRADA}')->first();`
    + ` echo json_encode(['org' => $u?->organization_id, 'principal' => App\\Models\\Organization::find(${D.org})->user_id,`
    + ` 'aviso' => App\\Models\\EmailLog::where('plantilla','cuenta_sumada')->where('related_id', $u?->id)->pluck('to')]);`
  );
  D.registrada = ultima(tinker(`echo App\\Models\\User::where('email','${REGISTRADA}')->value('id');`));
  di('Queda en la organización', r4.org === D.org);
  di('La principal sigue siendo la misma', r4.principal === D.principal);
  di('La principal recibe el aviso', r4.aviso.length === 1 && r4.aviso[0] === PRINCIPAL, r4.aviso.join(', '));

  /* ═══════════════════ 5 · La principal ═══════════════════ */

  t('5 · La principal ve lo suyo y edita la ficha');
  const p5 = await pestana();
  await p5.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  const e5 = await entrar(p5, '/mi-cuenta/login', PRINCIPAL, CLAVE);
  di('Entra', e5.url.includes('/mi-cuenta'), e5.url);
  const suyas = await pedir(p5, '/mi-cuenta/actividades');
  di('Ve la suya', suyas.texto.includes(`Actividad de la principal ${SELLO}`));
  di('No ve las de la sumada', ! suyas.texto.includes(`Actividad sumada ${SELLO}`));
  di('Editar la de la sumada: 403', (await pedir(p5, `/mi-cuenta/actividades/${D.actSumada}/editar`)).estado === 403);
  const propia = await pedir(p5, `/mi-cuenta/actividades/${D.actPrincipal}/editar`);
  di('En la suya sí tiene los enlaces de la organización', propia.texto.includes('name="enlace_web"'));
  di('El perfil le deja cambiar el logo', (await pedir(p5, '/mi-cuenta/perfil')).texto.includes('name="logo"'));

  /* ═══════════════════ 6 · Panel ═══════════════════ */

  t('6 · Panel → Organizaciones: cuentas, principal, sacar');
  const p6 = await pestana();
  await p6.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await entrar(p6, '/admin/login', ADMIN, CLAVE_ADMIN);
  const ficha = await pedir(p6, `/admin/organizaciones/${D.org}/editar`);
  di('La ficha lista las otras cuentas', ficha.texto.includes('data-cuentas-ficha') && ficha.texto.includes(SUMADA) && ficha.texto.includes(REGISTRADA));
  di('La principal sale como principal', /Cuenta principal[\s\S]{0,300}varias\.principal/.test(ficha.texto));
  di('No ofrece eliminarla', ficha.texto.includes('data-no-eliminar-con-cuenta'));
  const listado = await pedir(p6, `/admin/organizaciones?q=${encodeURIComponent(`Varias ${SELLO}`)}`);
  di('El listado dice cuántas cuentas más', listado.texto.includes('+ 2 cuentas más'));

  // Desde la ficha, como en el panel: el aviso vuelve a ella con `back()`.
  await p6.goto(`${B}/admin/organizaciones/${D.org}/editar`, { waitUntil: 'networkidle2' });
  await enviar(p6, `/admin/organizaciones/${D.org}/principal/${D.sumada}`, {});
  di('«Hacer principal» cambia la principal', ultima(tinker(`echo App\\Models\\Organization::find(${D.org})->user_id;`)) === String(D.sumada));
  await enviar(p6, `/admin/organizaciones/${D.org}/principal/${D.principal}`, {});
  di('…y se puede devolver', ultima(tinker(`echo App\\Models\\Organization::find(${D.org})->user_id;`)) === String(D.principal));

  const sacarPrincipal = await enviar(p6, `/admin/organizaciones/${D.org}/cuentas/${D.principal}`, { _method: 'DELETE' });
  di('A la principal no se la puede sacar', sacarPrincipal.texto.includes('No se puede sacar a la cuenta principal')
    && ultima(tinker(`echo App\\Models\\User::find(${D.principal})->organization_id;`)) === String(D.org));

  await enviar(p6, `/admin/organizaciones/${D.org}/cuentas/${D.registrada}`, { _method: 'DELETE' });
  di('«Sacar» deja la cuenta sin organización', ultima(tinker(`echo App\\Models\\User::find(${D.registrada})->organization_id ?? 'NULO';`)) === 'NULO');
  di('…sin borrarla', ultima(tinker(`echo App\\Models\\User::find(${D.registrada}) ? 'ESTA' : 'NO';`)) === 'ESTA');

  const eliminar = await enviar(p6, `/admin/organizaciones/${D.org}`, { _method: 'DELETE' });
  di('Eliminar una organización con cuentas se rechaza', ultima(tinker(`echo App\\Models\\Organization::find(${D.org}) ? 'ESTA' : 'NO';`)) === 'ESTA',
    eliminar.url);

  const exp = await p6.evaluate(async (base) => (await fetch(base + '/admin/actividades/exportar/descargar?q=' + encodeURIComponent('sumada'), { credentials: 'same-origin' })).status, B);
  di('La exportación de actividades sigue respondiendo', exp === 200, String(exp));

  /* ═══════════════════ 7 · Volver a APAGAR ═══════════════════ */

  t('7 · Apagar no saca a nadie; sólo deja de admitir nuevas');
  interruptor('0');

  const p7 = await pestana();
  await p7.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await entrar(p7, '/mi-cuenta/login', SUMADA, CLAVE);
  di('La sumada sigue viendo lo suyo', (await pedir(p7, '/mi-cuenta/actividades')).texto.includes(`Actividad sumada ${SELLO}`));
  const tercera = await enviar(p7, '/publicar-actividad', actividad({ titulo: `Tercera sumada ${SELLO}`, org_nombre: ORG }));
  di('…y sigue publicando', tercera.url.includes('/listo'), tercera.url + ' ' + fallos(tercera.texto));

  const p7b = await pestana();
  await p7b.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  const tardia = await enviar(p7b, '/publicar-actividad', actividad({
    org_nombre: ORG, org_id: D.org, org_tipo: 'Organización sin fines de lucro',
    email: TARDIA, password: CLAVE, password_confirmation: CLAVE,
  }));
  di('Alguien nuevo ya no se puede sumar', ! tardia.url.includes('/listo')
    && ultima(tinker(`echo App\\Models\\User::where('email','${TARDIA}')->count();`)) === '0', tardia.url);

  di('Sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  await nav.close();
  console.log('');
  console.log('  ' + ultima(limpiar()));
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
