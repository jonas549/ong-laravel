// Sexta tanda, bloque B del wizard: qué pasos se salta quien ya tiene ficha y
// cómo se cambia de cuenta sin salir.
//
//   B1 · con sesión y el tipo en la ficha, el paso 2 no se pinta y la barra
//        renumera sin hueco.
//   B2 · una ficha completa SIN tipo —la de quien reclamó una organización del
//        listado desde /mi-cuenta/registro— pintaba el paso 3 casi vacío, con
//        «Tu cuenta» y nada más. Ahora pasa por el 2, que es donde se pregunta
//        el tipo, y se salta el 3.
//   B3 · con el paso 3 a la vista y la sesión abierta, cerrar sesión y entrar
//        con otra cuenta sin salir del wizard y sin perder lo escrito.
//   B5 · a «Otra» no se le exige logo para saltarse el 3.
//   B6 · en el teléfono el logo no se exige a nadie.
//
//   node pruebas/sexta-wizard.mjs
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
const CLAVE = 'sexta-wizard-2026';
const A = { correo: `sexta.a.${SELLO}@ejemplo.cl`, org: `Fundación Sexta A ${SELLO}` };
const Bc = { correo: `sexta.b.${SELLO}@ejemplo.cl`, org: `Fundación Sexta B ${SELLO}` };

const crear = (c, extra) => tinker(
  `$u = new App\\Models\\User(['name' => 'Sexta', 'email' => '${c.correo}', 'password' => bcrypt('${CLAVE}')]);`
  + ` $u->forceFill(['role' => App\\Models\\User::ROL_ORGANIZER, 'is_active' => true, 'email_verified_at' => now()])->save();`
  + ` App\\Models\\Organization::create(['user_id' => $u->id, 'nombre' => '${c.org}', 'slug' => 'sexta-'.$u->id, 'activo' => true, ${extra}]);`
  + ` echo 'OK';`
);
crear(A, `'tipo' => 'Organización sin fines de lucro', 'logo_path' => 'storage/organizaciones/prueba.png'`);
crear(Bc, `'tipo' => 'Organización sin fines de lucro', 'logo_path' => 'storage/organizaciones/prueba.png'`);

/** Pone la ficha de A en el estado de cada escenario. */
const fichaA = (campos) => tinker(
  `App\\Models\\Organization::where('nombre','${A.org}')->update([${campos}]); echo 'OK';`
);

const limpiar = () => tinker(
  `foreach (['${A.correo}','${Bc.correo}'] as $c) { $u = App\\Models\\User::where('email',$c)->first();`
  + ` if ($u) { $u->organization?->forceDelete(); $u->delete(); } App\\Models\\AccessLog::where('email',$c)->delete(); }`
  + ` echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const estado = () => p.evaluate(() => {
  const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
  return { paso: d.paso, salta2: d.saltaPaso2(), salta3: d.saltaPaso3(), sesion: d.conSesion, correo: d.correoCuenta };
});

const barra = () => p.evaluate(() => [...document.querySelectorAll('.steplink')]
  .filter((b) => b.getBoundingClientRect().width > 0)
  .map((b) => b.innerText.replace(/\s+/g, ' ').trim()));

const CAMPOS = ['org_nombre', 'org_tipo_otro', 'org_unidad_educativa', 'org_logo'];
const pideEnPaso3 = () => p.evaluate((campos) => campos.filter((c) => {
  const e = document.querySelector(`[data-campo="${c}"]`);
  return e && e.getBoundingClientRect().height > 0;
}), CAMPOS);

const abrirWizard = async () => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(300);
};

const pulsar = async (texto) => {
  const hecho = await p.evaluate((txt) => {
    const b = [...document.querySelectorAll('button')]
      .filter((x) => x.getBoundingClientRect().width > 0)
      .find((x) => x.innerText.trim().startsWith(txt));
    b?.click();
    return !! b;
  }, texto);
  await esperar(350);
  return hecho;
};

const entrarComo = async (c) => {
  await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', c.correo);
  await p.type('input[type="password"]', CLAVE);
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('form[method="POST"]').submit()),
  ]);
};

try {
  await entrarComo(A);
  di('La sesión de A queda abierta', ! p.url().includes('/login'), p.url().replace(B, ''));

  /* ═══════════════ B1 ═══════════════════════════════════════════ */

  t('B1 — con tipo y ficha completa, fuera el 2 y el 3');

  await abrirWizard();
  let b = await barra();
  di('La barra enseña tres pasos', b.length === 3, b.join(' | '));
  di('Ni «Tipo de org.» ni «Tu organización»', ! b.some((x) => /Tipo de org|Tu organización/.test(x)));
  di('Renumera sin hueco: 1 ¿Voluntariado?, 2 Tu actividad, 3 Enviado',
    b[0]?.startsWith('1') && b[1]?.startsWith('2') && b[1]?.includes('Tu actividad') && b[2]?.startsWith('3'), b.map((x) => x.slice(0, 16)).join(' | '));

  await pulsar('No, solo quiero difundir');
  let e = await estado();
  di('«Solo difundir» lleva directo a «Tu actividad»', e.paso === 4, `paso ${e.paso}`);

  // Volver por la barra a «¿Voluntariado?» y pulsar el paso que ya no está.
  await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).irA(2));
  await esperar(200);
  e = await estado();
  di('Pedir el 2 desde el 4 no deja en un paso saltado', e.paso === 1, `paso ${e.paso}`);

  /* ═══════════════ B2 ═══════════════════════════════════════════ */

  t('B2 — ficha completa sin tipo (reclamada desde el registro)');

  fichaA(`'tipo' => null`);
  await abrirWizard();
  b = await barra();
  di('Se enseña el 2, que es donde se pregunta el tipo', b.some((x) => x.includes('Tipo de org.')), b.join(' | '));
  di('**Y NO el 3**', ! b.some((x) => x.includes('Tu organización')));

  await pulsar('No, solo quiero difundir');
  e = await estado();
  di('«Solo difundir» lleva al 2', e.paso === 2, `paso ${e.paso}`);

  await pulsar('Organización sin fines de lucro');
  await pulsar('Continuar');
  e = await estado();
  di('**Con el tipo elegido, «Continuar» salta al 4**', e.paso === 4, `paso ${e.paso}`);

  // Y si elige un tipo que pide algo que la ficha no tiene, el 3 vuelve.
  await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).irA(2));
  await esperar(200);
  await pulsar('Otra');
  await pulsar('Continuar');
  e = await estado();
  di('Con «Otra», el 3 vuelve para pedir la descripción', e.paso === 3, `paso ${e.paso}`);
  const pide = await pideEnPaso3();
  di('Y pide sólo eso', pide.length === 1 && pide[0] === 'org_tipo_otro', pide.join(', '));

  // Lo que se enviaría: el tipo del paso 2, aunque el paso esté visto y pasado.
  di('El tipo elegido viaja en el formulario', await p.evaluate(() =>
    document.querySelector('input[name="org_tipo"]').value === 'Otra'));

  /* ═══════════════ B5 ═══════════════════════════════════════════ */

  t('B5 — «Otra» sin logo no pasa por el 3');

  fichaA(`'tipo' => 'Otra', 'tipo_otro' => 'Colectivo vecinal', 'logo_path' => null`);
  await abrirWizard();
  b = await barra();
  e = await estado();
  di('Salta el 2 y el 3', e.salta2 && e.salta3, b.join(' | '));

  /* ═══════════════ B6 ═══════════════════════════════════════════ */

  t('B6 — sin logo: en escritorio se pide, en el teléfono no');

  fichaA(`'tipo' => 'Organización sin fines de lucro', 'tipo_otro' => null, 'logo_path' => null`);
  await abrirWizard();
  e = await estado();
  di('Escritorio: el 3 se pinta', e.salta3 === false);
  await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).irA(3));
  await esperar(200);
  const pideEscritorio = await pideEnPaso3();
  di('Y pide sólo el logo', pideEscritorio.join(',') === 'org_logo', pideEscritorio.join(', '));
  di('Diciendo que es opcional y que se sube después en «Mi perfil»', await p.evaluate(() =>
    /opcional.*Mi perfil/s.test(document.querySelector('[data-campo="org_logo"]').innerText)));

  await p.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  await abrirWizard();
  e = await estado();
  b = await barra();
  di('**Teléfono: salta el 2 y el 3**', e.salta2 && e.salta3, b.join(' | '));
  await pulsar('No, solo quiero difundir');
  e = await estado();
  di('Y «Solo difundir» lleva a «Tu actividad»', e.paso === 4, `paso ${e.paso}`);
  await p.setViewport({ width: 1440, height: 1000 });

  t('B6 — y el logo se sube después desde «Mi perfil»');

  await p.goto(`${B}/mi-cuenta/perfil`, { waitUntil: 'networkidle2' });
  di('«Mi perfil» tiene el bloque del logo', await p.evaluate(() =>
    !! document.querySelector('#logo-organizacion input[type="file"][name="logo"]')));
  const archivo = await p.$('#logo-organizacion input[type="file"]');
  await archivo.uploadFile('public/img/logo-fundacion-trascender.png');
  await p.waitForFunction(() => {
    const d = Alpine.$data(document.querySelector('#logo-organizacion [x-data]'));
    return d.tiene && ! d.reduciendo;
  }, { timeout: 8000 });
  di('Enseña la vista previa antes de guardar', await p.evaluate(() =>
    !! document.querySelector('#logo-organizacion img[src^="blob:"], #logo-organizacion img[src^="data:"]')));
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('#logo-organizacion button[type="submit"]').click()),
  ]);
  di('Lo guarda', await p.evaluate(() => document.body.innerText.includes('Logo guardado')));
  const guardado = tinker(`echo App\\Models\\Organization::where('nombre','${A.org}')->value('logo_path');`).split('\n').pop();
  di('**En su ficha**', /^storage\/organizaciones\/.+\.(png|jpe?g|webp)$/.test(guardado), guardado);
  di('Y se sirve', (await p.goto(`${B}/${guardado}`)).status() === 200);

  await abrirWizard();
  e = await estado();
  di('**Con el logo puesto, el wizard ya no pasa por el 3**', e.salta3 === true);

  // Se borra el archivo de la prueba y la ficha vuelve a quedarse sin logo,
  // que es lo que necesita B3 para que el paso 3 se pinte.
  tinker(`Illuminate\\Support\\Facades\\Storage::disk('public')->delete(str_replace('storage/', '', '${guardado}'));`
    + ` App\\Models\\Organization::where('nombre','${A.org}')->update(['logo_path' => null]); echo 'OK';`);

  /* ═══════════════ B3 ═══════════════════════════════════════════ */

  t('B3 — cerrar sesión y entrar con otra cuenta sin salir');

  await abrirWizard();
  // Escribe en «Tu actividad» y vuelve al 3, que es donde está el botón.
  await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).irA(4));
  await esperar(200);
  await p.type('[name="titulo"]', `Actividad escrita antes de cambiar ${SELLO}`);
  await p.type('[name="descripcion"]', 'Descripción que no se puede perder.');
  await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).irA(3));
  await esperar(200);

  di('En el 3, junto a «Tu cuenta», está la opción', await p.evaluate(() =>
    [...document.querySelectorAll('button')].some((x) => x.getBoundingClientRect().width > 0
      && x.innerText.includes('Cerrar sesión y entrar con otra cuenta'))));

  await pulsar('Cerrar sesión y entrar con otra cuenta');
  await esperar(600);
  e = await estado();
  di('Cierra la sesión sin recargar', e.sesion === false && e.paso === 3, `paso ${e.paso}`);
  di('Y abre el acceso', await p.evaluate(() => document.querySelector('.acceso-caja').getBoundingClientRect().height > 0));
  const cookieSinSesion = await p.evaluate(async () => (await fetch('/mi-cuenta/actividades', { redirect: 'manual' })).type);
  di('El servidor ya no la tiene abierta', cookieSinSesion === 'opaqueredirect');

  await p.type('[data-acceso-correo]', Bc.correo);
  await p.type('.acceso-caja input[type="password"]', CLAVE);
  await pulsar('Entrar');
  await esperar(900);
  e = await estado();
  di('**Entra con la otra cuenta**', e.sesion === true && e.correo === Bc.correo, e.correo);
  di('Con su organización en el formulario', await p.evaluate((org) =>
    document.querySelector('[name="org_nombre"]').value === org, Bc.org));
  di('**Y lo escrito en «Tu actividad» sigue ahí**', await p.evaluate((s) =>
    document.querySelector('[name="titulo"]').value.includes(s)
    && document.querySelector('[name="descripcion"]').value === 'Descripción que no se puede perder.', String(SELLO)));
  di('Su ficha está completa: pasa al 4', e.paso === 4, `paso ${e.paso}`);

  // El token nuevo está puesto: un POST con el del formulario no da 419.
  const conToken = await p.evaluate(async () => {
    const token = document.querySelector('form input[name="_token"]').value;
    const r = await fetch('/publicar-actividad/entrar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body: `_token=${encodeURIComponent(token)}`,
    });
    return r.status;
  });
  di('El formulario lleva el token de la sesión nueva (no 419)', conToken === 422, `HTTP ${conToken}`);

  t('B3 — y si al final no entra con ninguna');

  await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).irA(3));
  await esperar(200);
  // Con la ficha de B completa el 3 se salta; se fuerza a mirar el botón.
  await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).irAlPaso(3));
  await esperar(200);
  await pulsar('Cerrar sesión y entrar con otra cuenta');
  await esperar(600);
  await pulsar('Cancelar');
  e = await estado();
  di('Queda sin sesión, en el 3', e.sesion === false && e.paso === 3);
  const sinCuenta = await pideEnPaso3();
  di('Y el 3 pide la organización entera, vacía', sinCuenta.includes('org_nombre') && await p.evaluate(() =>
    document.querySelector('[name="org_nombre"]').value === ''), sinCuenta.join(', '));
  di('Con «Crea tu acceso»', await p.evaluate(() => [...document.querySelectorAll('.seclabel')]
    .some((x) => x.textContent.includes('Crea tu acceso') && x.getBoundingClientRect().height > 0)));
  b = await barra();
  di('La barra vuelve a los cinco pasos', b.length === 5, b.map((x) => x.slice(0, 3)).join(''));

  di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));
} finally {
  limpiar();
  await nav.close();
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
