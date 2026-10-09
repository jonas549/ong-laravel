// Tanda del 09/10 — lo que se ve, en Chrome.
//
//   1. El aviso rojo de un campo se va en cuanto el campo queda bien (y
//      vuelve si se estropea): Usuarios, la ficha de organización, el panel.
//   5. Crear la cuenta de una organización sin cuenta desde su ficha.
//   6. Filtrar organizaciones por fecha de creación.
//   7. Preguntas frecuentes: la barra sobre el menú, el pie y la página.
//   8. La ficha pública: botón de correo (sin la dirección a la vista) y la
//      accesibilidad.
//   9. El editor de «Mi cuenta» pinta los MISMOS campos que el wizard, y
//      guardar no pierde la accesibilidad, las etiquetas viejas ni el tipo
//      de colaborador.
//  10. El título «Ediciones» del home.
//
// Monta su escenario con un sello irrepetible y lo deshace al final.
//
//   node pruebas/tanda-09-10.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(72)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();
const json = (php) => JSON.parse(ultima(tinker(php)));

const SELLO = Date.now();
const CLAVE = 'ClaveLarga123';
const ORGANIZADOR = `tanda.org.${SELLO}@ejemplo.cl`;
const ORG = `Fundación Tanda Nueve ${SELLO}`;
const LIBRE = `Libre Tanda Nueve ${SELLO}`;
const CUENTA_LIBRE = `tanda.libre.${SELLO}@ejemplo.cl`;
const DETALLE = 'Rampa de acceso y baño accesible.';
const CORREO_FICHA = `contacto.ficha.${SELLO}@ejemplo.cl`;

/* ─────────────────────────────── escenario ── */

const D = json(
  `$u = App\\Models\\User::create(['name' => 'Organizador Tanda', 'email' => '${ORGANIZADOR}', 'password' => '${CLAVE}', 'role' => 'organizer', 'is_active' => true]);`
  + ` $u->forceFill(['email_verified_at' => now()])->save();`
  + ` $o = App\\Models\\Organization::create(['nombre' => '${ORG}', 'tipo' => 'Organización sin fines de lucro', 'activo' => true]);`
  + ` $o->enlazarCuenta($u);`
  + ` $c = App\\Models\\Commune::where('activo', true)->first();`
  + ` $a = App\\Models\\Activity::whereNotNull('published_at')->first()->replicate();`
  + ` $a->forceFill(['organization_id' => $o->id, 'user_id' => $u->id, 'titulo' => 'Actividad tanda ${SELLO}', 'slug' => 'tanda-nueve-${SELLO}',`
  + ` 'estado' => 'publicada', 'published_at' => now(), 'destacada' => false, 'cerrada' => false, 'sin_fecha_definida' => false,`
  + ` 'fecha_inicio' => now()->addDays(20)->toDateString(), 'fecha_termino' => null, 'formato' => 'Presencial', 'direccion' => 'Calle Falsa 123',`
  + ` 'region_id' => $c->region_id, 'commune_id' => $c->id, 'tiene_accesibilidad' => true, 'accesibilidad_detalle' => '${DETALLE}',`
  + ` 'correo_contacto' => '${CORREO_FICHA}', 'info_previa' => null, 'publico_otro' => null])->save();`
  + ` $ids = collect(['tema', 'caracteristica', 'publico', 'acceso'])->map(fn ($g) => App\\Models\\TaxonomyTerm::where('grupo', $g)->where('activo', true)->value('id'))->filter();`
  + ` $a->terms()->sync($ids);`
  + ` $a->collaborators()->create(['nombre' => 'Colaboradora Uno', 'tipo' => 'Institución educativa', 'orden' => 0]);`
  + ` $l = App\\Models\\Organization::create(['nombre' => '${LIBRE}', 'tipo' => 'Organización sin fines de lucro', 'activo' => true]);`
  + ` echo json_encode(['org' => $o->id, 'act' => $a->id, 'url' => route('activities.show', $a, false), 'libre' => $l->id,`
  + ` 'acceso' => App\\Models\\TaxonomyTerm::where('grupo', 'acceso')->where('activo', true)->value('id'), 'comuna' => $c->id, 'region' => $c->region_id]);`
);

const estado = (campos) => json(
  `$a = App\\Models\\Activity::with(['terms', 'collaborators'])->find(${D.act});`
  + ` echo json_encode(['acc' => (bool) $a->tiene_accesibilidad, 'detalle' => $a->accesibilidad_detalle, 'titulo' => $a->titulo,`
  + ` 'acceso' => $a->terms->where('grupo', 'acceso')->pluck('id')->values(), 'tipo' => $a->collaborators->first()?->tipo,`
  + ` 'colabs' => $a->collaborators->pluck('nombre'), 'comuna' => $a->commune_id, 'correo' => $a->correo_contacto,`
  + ` 'carac' => $a->terms->where('grupo', 'caracteristica')->count()]);`
);

const limpiar = () => tinker(
  `foreach (App\\Models\\Organization::withTrashed()->whereIn('nombre', ['${ORG}', '${LIBRE}'])->get() as $o) {`
  + ` foreach ($o->activities()->withTrashed()->get() as $a) { $a->registrations()->delete(); $a->collaborators()->delete(); $a->statusLogs()->delete(); $a->forceDelete(); }`
  + ` App\\Models\\User::where('organization_id', $o->id)->update(['organization_id' => null]); $o->forceDelete(); }`
  + ` foreach (App\\Models\\User::withTrashed()->where('email', 'like', 'tanda.%.${SELLO}@ejemplo.cl')->get() as $u) {`
  + ` App\\Models\\AccessLog::where('user_id', $u->id)->orWhere('actor_id', $u->id)->delete(); $u->forceDelete(); }`
  + ` echo 'LIMPIO';`
);

/* ─────────────────────────────── utilidades ── */

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];

const pestana = async () => {
  const ctx = await nav.createBrowserContext();
  const p = await ctx.newPage();
  await p.setViewport({ width: 1440, height: 1000 });
  p.on('pageerror', (e) => errores.push(String(e)));
  return p;
};

const entrar = async (p, ruta, email, clave) => {
  await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', email);
  await p.type('input[type="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
};

/** ¿Se ve el aviso del servidor que va con este campo? */
const avisoVisible = (p, selector) => p.$eval(selector, (campo) => {
  for (let caja = campo.parentElement, n = 0; caja && n < 3; caja = caja.parentElement, n++) {
    const aviso = [...caja.querySelectorAll('.field-error')].find((e) => ! e.hasAttribute('x-show'));
    if (aviso) return aviso.offsetParent !== null;
  }
  return null;
});

/** Reemplaza el valor de un campo como lo haría quien escribe. */
const escribir = async (p, selector, valor) => {
  // Ctrl+A y no triple clic: el triple clic no siempre selecciona todo.
  await p.focus(selector);
  await p.keyboard.down('Control');
  await p.keyboard.press('a');
  await p.keyboard.up('Control');
  await p.keyboard.press('Backspace');
  if (valor) await p.type(selector, valor);
  await esperar(80);
};

try {
  const admin = await pestana();
  await entrar(admin, '/admin/login', ADMIN, CLAVE_ADMIN);

  t('1 · El aviso rojo se va cuando el campo queda bien');

  await admin.goto(`${B}/admin/usuarios`, { waitUntil: 'networkidle2' });
  await admin.type('#u-name', 'Persona Corta');
  await admin.type('#u-email', ADMIN);
  await admin.type('#u-password', 'corta');
  await Promise.all([admin.waitForNavigation({ waitUntil: 'networkidle2' }),
    admin.evaluate(() => document.querySelector('form[data-crear-usuario]').submit())]);

  di('Usuarios: rebota con el aviso de la contraseña', await avisoVisible(admin, '#u-password') === true);
  di('…y el del correo repetido', await avisoVisible(admin, '#u-email') === true);
  await escribir(admin, '#u-password', 'cort');
  di('con 4 letras el aviso sigue', await avisoVisible(admin, '#u-password') === true);
  await escribir(admin, '#u-password', 'unaclavelarga');
  di('con una válida, se va', await avisoVisible(admin, '#u-password') === false);
  di('…y el campo deja de estar en rojo', ! await admin.$eval('#u-password', (c) => c.classList.contains('is-invalid')));
  await escribir(admin, '#u-password', 'otra');
  di('si se vuelve a estropear, vuelve', await avisoVisible(admin, '#u-password') === true);
  const correoRechazado = await admin.$eval('#u-email', (c) => c.value);
  await escribir(admin, '#u-email', `otro.${SELLO}@ejemplo.cl`);
  di('el del correo repetido se va al cambiar el correo', await avisoVisible(admin, '#u-email') === false);
  await escribir(admin, '#u-email', correoRechazado);
  di('…y vuelve si se escribe el mismo que se rechazó', await avisoVisible(admin, '#u-email') === true);

  // Un campo del panel (`x-panel.campo`): Configuración → General.
  await admin.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
  const conCampo = await admin.$('#c-sitio_nombre') ?? await admin.$('input[name="recordatorio_dias"]');
  di('Configuración → General avisa de la dirección de ejemplo', !! await admin.$('[data-correo-de-ejemplo]')
    || ! (await admin.content()).includes('ong-laravel.test'));
  di('(la pantalla carga)', !! conCampo || (await admin.title()).length > 0);

  t('5 · Crear la cuenta desde «Editar organización»');

  await admin.goto(`${B}/admin/organizaciones/${D.libre}/editar`, { waitUntil: 'networkidle2' });
  di('sin cuenta, la ficha ofrece crearla', !! await admin.$('form[data-crear-cuenta-organizacion]'));
  di('…y el lateral enlaza al formulario', !! await admin.$('a[href="#crear-cuenta"]'));
  await admin.type('#c-name', 'Persona Libre');
  await admin.type('#c-email', CUENTA_LIBRE);
  await admin.type('#c-password', 'corta');
  await Promise.all([admin.waitForNavigation({ waitUntil: 'networkidle2' }),
    admin.evaluate(() => document.querySelector('form[data-crear-cuenta-organizacion]').submit())]);
  di('contraseña corta: rebota con su aviso', await avisoVisible(admin, '#c-password') === true);
  di('…conservando el nombre y el correo', await admin.$eval('#c-email', (c) => c.value) === CUENTA_LIBRE);
  await escribir(admin, '#c-password', 'unaclavelarga');
  di('al corregirla, el aviso se va', await avisoVisible(admin, '#c-password') === false);
  await Promise.all([admin.waitForNavigation({ waitUntil: 'networkidle2' }),
    admin.evaluate(() => document.querySelector('form[data-crear-cuenta-organizacion]').submit())]);
  const tras = await admin.evaluate(() => document.body.innerText);
  di('se crea y lo dice', tras.includes(`Cuenta creada: ${CUENTA_LIBRE}`), tras.match(/Cuenta creada[^\n]*/)?.[0] ?? '');
  di('la ficha ya la muestra como principal', (await admin.$eval('[data-cuenta-ficha]', (e) => e.innerText)).includes(CUENTA_LIBRE));
  di('y el formulario desaparece', ! await admin.$('form[data-crear-cuenta-organizacion]'));
  const libre = await pestana();
  await entrar(libre, '/mi-cuenta/login', CUENTA_LIBRE, 'unaclavelarga');
  di('esa persona entra con su contraseña', libre.url().includes('/mi-cuenta'), libre.url().replace(B, ''));

  t('6 · Filtrar organizaciones por fecha de creación');

  const hoy = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  await admin.goto(`${B}/admin/organizaciones?desde=${hoy}&hasta=${hoy}&q=${encodeURIComponent('Tanda Nueve ' + SELLO)}`, { waitUntil: 'networkidle2' });
  let texto = await admin.evaluate(() => document.body.innerText);
  di('desde/hasta hoy: salen las dos de la prueba', texto.includes(ORG) && texto.includes(LIBRE));
  di('los campos guardan las fechas elegidas', await admin.$eval('[data-filtro-desde]', (c) => c.value) === hoy);
  await admin.goto(`${B}/admin/organizaciones?hasta=2020-01-01&q=${encodeURIComponent('Tanda Nueve ' + SELLO)}`, { waitUntil: 'networkidle2' });
  texto = await admin.evaluate(() => document.body.innerText);
  di('hasta 2020: ninguna', ! texto.includes(ORG) && texto.includes('Ninguna organización coincide'));
  const basura = await admin.goto(`${B}/admin/organizaciones?desde=basura`, { waitUntil: 'networkidle2' });
  di('una fecha mal escrita no rompe nada', basura.status() === 200 && !! await admin.$('[data-filtro-desde]'), String(basura.status()));

  t('7 · Preguntas frecuentes');

  const pub = await pestana();
  await pub.goto(`${B}/`, { waitUntil: 'networkidle2' });
  const barra = await pub.$eval('[data-barra-fina]', (b) => ({ texto: b.innerText.trim(), alto: b.getBoundingClientRect().height, href: b.querySelector('a').getAttribute('href') }));
  di('la barra sobre el menú dice «Preguntas frecuentes»', barra.texto === 'Preguntas frecuentes', `${Math.round(barra.alto)} px`);
  di('…y es delgada', barra.alto > 0 && barra.alto < 40);
  di('…y está encima del menú', await pub.evaluate(() => document.querySelector('[data-barra-fina]').getBoundingClientRect().bottom <= document.querySelector('header').getBoundingClientRect().top + 1));
  di('el pie la enlaza bajo la política de privacidad', await pub.evaluate(() => {
    const faq = document.querySelector('footer [data-pie-faq]');
    const priv = [...document.querySelectorAll('footer a')].find((a) => a.textContent.includes('Política de privacidad'));
    return !! faq && !! priv && faq.getBoundingClientRect().top > priv.getBoundingClientRect().top;
  }));
  await Promise.all([pub.waitForNavigation({ waitUntil: 'networkidle2' }), pub.click('[data-barra-fina] a')]);
  di('la página carga', pub.url().endsWith('/preguntas-frecuentes'));
  const faq = await pub.evaluate(() => ({
    titulo: document.querySelector('h1')?.textContent.trim(),
    preguntas: document.querySelectorAll('.faq details').length,
    secciones: [...document.querySelectorAll('.faq h2')].map((h) => h.textContent.trim()),
    enlaces: [...document.querySelectorAll('.faq a')].map((a) => a.href),
  }));
  di('con su título', faq.titulo === 'Preguntas frecuentes');
  di('las 32 preguntas del documento', faq.preguntas === 32, String(faq.preguntas));
  di('en sus 4 secciones', faq.secciones.length === 4, faq.secciones.join(' / '));
  di('con los enlaces del documento', faq.enlaces.some((h) => h.includes('comunidad-org.cl')) && faq.enlaces.some((h) => h.includes('drive.google.com')));
  const altoCerrada = await pub.$eval('.faq details', (d) => d.getBoundingClientRect().height);
  await pub.click('.faq details summary');
  await esperar(150);
  const altoAbierta = await pub.$eval('.faq details', (d) => d.getBoundingClientRect().height);
  di('una pregunta se abre al pulsarla', altoAbierta > altoCerrada + 20, `${Math.round(altoCerrada)} → ${Math.round(altoAbierta)} px`);
  await pub.setViewport({ width: 390, height: 844 });
  await pub.reload({ waitUntil: 'networkidle2' });
  di('en el teléfono no hay scroll lateral', await pub.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
  await pub.setViewport({ width: 1440, height: 1000 });
  await pub.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  di('el pie compacto también la enlaza', !! await pub.$('footer [data-pie-faq]'));
  await admin.goto(`${B}/admin/paginas/preguntas-frecuentes`, { waitUntil: 'networkidle2' });
  di('el panel la edita como la de privacidad', (await admin.$eval('textarea[name="contenido"]', (c) => c.value)).includes('<details>'));

  t('8 · La ficha pública de la actividad');

  await pub.goto(`${B}${D.url}`, { waitUntil: 'networkidle2' });
  const ficha = await pub.evaluate((correo) => ({
    boton: document.querySelector('[data-escribir-organizador]')?.getAttribute('href') ?? '',
    textoBoton: document.querySelector('[data-escribir-organizador]')?.innerText.trim(),
    aLaVista: document.body.innerText.includes(correo),
    acc: document.querySelector('[data-ficha-accesibilidad]')?.innerText ?? '',
  }), CORREO_FICHA);
  di('hay un botón para escribir a quien organiza', ficha.boton.startsWith(`mailto:${CORREO_FICHA}`), ficha.textoBoton);
  di('…y la dirección no está a la vista', ! ficha.aLaVista);
  di('la accesibilidad sale con su texto', ficha.acc.includes(DETALLE), ficha.acc.replace(/\s+/g, ' ').slice(0, 80));

  t('9 · El editor usa el formulario de publicar');

  const org = await pestana();
  await entrar(org, '/mi-cuenta/login', ORGANIZADOR, CLAVE);
  await org.goto(`${B}/mi-cuenta/actividades/${D.act}/editar`, { waitUntil: 'networkidle2' });
  const editor = await org.evaluate(() => ({
    // textContent y no innerText: los rótulos de sección van en mayúsculas por CSS.
    texto: document.body.textContent.replace(/\s+/g, ' '),
    nombres: [...new Set([...document.querySelectorAll('form[action*="/actividades/"] [name]')].map((c) => c.name))].sort(),
    region: document.querySelector('select[name="region_id"]')?.value,
    comuna: document.querySelector('select[name="commune_id"]')?.value,
    detalle: document.querySelector('textarea[name="accesibilidad_detalle"]')?.value,
    colabs: [...document.querySelectorAll('input[name="colaboradores[]"]')].map((c) => c.value),
  }));
  di('ya no pregunta la accesibilidad con etiquetas', ! editor.texto.includes('¿La actividad es accesible para personas con discapacidad?'));
  di('pregunta «¿Tu actividad cuenta con alguna adecuación…?»', editor.texto.includes('¿Tu actividad cuenta con alguna adecuación de accesibilidad?'));
  di('…con lo que se contestó al publicar', editor.detalle === DETALLE);
  di('ya no dice «Temas de la actividad (hasta 3)»', ! editor.texto.includes('(hasta 3)'));
  di('los rótulos son los de publicar', ['Información básica', 'Descripción de la actividad', '¿Requiere inscripción previa?', 'Imagen de portada', 'Usar el mismo correo de la cuenta']
    .every((r) => editor.texto.includes(r)));
  di('región y comuna, con las de la actividad', editor.region === String(D.region) && editor.comuna === String(D.comuna), `${editor.region} / ${editor.comuna}`);
  di('los colaboradores, como etiquetas con su nombre', editor.colabs.join() === 'Colaboradora Uno');

  const wiz = await pestana();
  await wiz.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  const nombresWizard = await wiz.evaluate(() => [...new Set([...document.querySelectorAll('[data-paso="4"] [name]')].map((c) => c.name))].sort());
  // Los `algo[]` los pinta la selección (chips, etiquetas): en el wizard
  // vacío no existen todavía. Se comparan los demás.
  const sinSeleccion = (n) => ! n.endsWith('[]');
  const soloEditor = editor.nombres.filter(sinSeleccion).filter((n) => ! nombresWizard.includes(n));
  const soloWizard = nombresWizard.filter(sinSeleccion).filter((n) => ! editor.nombres.includes(n));
  di('los campos son los mismos que en el paso 4 del wizard', soloWizard.join() === 'cupos_totales'
    && soloEditor.filter((n) => ! ['_token', '_method', 'cupos_disponibles', 'mensaje_ajustes'].includes(n)).length === 0,
    `sólo editor: ${soloEditor.join(', ')} · sólo wizard: ${soloWizard.join(', ')}`);

  await escribir(org, 'input[name="titulo"]', `Actividad tanda editada ${SELLO}`);
  await Promise.all([org.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }),
    org.click(`form[action$="/${D.act}"] button[type="submit"]`)]);
  let e = estado();
  di('se guarda', e.titulo === `Actividad tanda editada ${SELLO}`, org.url().replace(B, ''));
  di('guardar no apaga la accesibilidad (el fallo de antes)', e.acc === true && e.detalle === DETALLE);
  di('las etiquetas de accesibilidad viejas se conservan', ! D.acceso || e.acceso.includes(D.acceso));
  di('el tipo del colaborador se conserva', e.tipo === 'Institución educativa', String(e.tipo));
  di('la comuna sigue igual', e.comuna === D.comuna);
  di('el correo público sigue igual', e.correo === CORREO_FICHA);

  await org.goto(`${B}/mi-cuenta/actividades/${D.act}/editar`, { waitUntil: 'networkidle2' });
  await org.evaluate(() => {
    const no = [...document.querySelectorAll('button.chip')].filter((b) => b.textContent.trim() === 'No' && b.getAttribute('x-on:click')?.startsWith('acc'));
    no[0]?.click();
  });
  await esperar(150);
  await Promise.all([org.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }),
    org.click(`form[action$="/${D.act}"] button[type="submit"]`)]);
  e = estado();
  di('contestar «No» en el editor sí la apaga, y borra el detalle', e.acc === false && e.detalle === null);

  // Características obligatorias, como al publicar.
  await org.goto(`${B}/mi-cuenta/actividades/${D.act}/editar`, { waitUntil: 'networkidle2' });
  await org.evaluate(() => {
    const d = Alpine.$data(document.querySelector('[x-data^="editorActividad"]'));
    d.sel.caracteristicas.splice(0);
  });
  await esperar(150);
  await org.click(`form[action$="/${D.act}"] button[type="submit"]`);
  await esperar(600);
  const resumen = await org.evaluate(() => document.querySelector('[data-resumen-errores]')?.innerText ?? '');
  di('sin características no deja guardar, y lo dice', resumen.includes('característica') || resumen.includes('Características'), resumen.replace(/\s+/g, ' ').slice(0, 90));

  t('10 · El título «Ediciones» del home');

  await pub.goto(`${B}/`, { waitUntil: 'networkidle2' });
  const ediciones = await pub.evaluate(() => {
    const h = document.querySelector('[data-titulo-ediciones]');
    const tarjeta = h?.nextElementSibling?.querySelector('article');
    const cifra = document.querySelector('.count');
    return { texto: h?.textContent.trim(), antesDeTarjetas: !! tarjeta, despuesDeCifras: !! cifra && cifra.getBoundingClientRect().top < h.getBoundingClientRect().top };
  });
  di('dice «Ediciones»', ediciones.texto === 'Ediciones');
  di('entre los contadores y las tarjetas', ediciones.antesDeTarjetas && ediciones.despuesDeCifras);
  await admin.goto(`${B}/admin/paginas/home/cifras`, { waitUntil: 'networkidle2' });
  di('se edita en el panel, con los demás rótulos de la sección', (await admin.evaluate(() => document.body.innerText)).includes('Título sobre las ediciones'));

  t('Consola');
  di('sin errores de JavaScript', errores.length === 0, errores.join(' | ').slice(0, 300));
} finally {
  await nav.close();
  console.log('  ' + ultima(limpiar()));
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
