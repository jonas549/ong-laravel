// Sexta tanda, las correcciones del 22/09:
//
//   B5 · el logo es obligatorio en escritorio en todos los tipos menos «Otra»,
//        y opcional en el teléfono. En el wizard y en /mi-cuenta/registro.
//   C5 · una tarjeta de «¿cómo participar?» sin enlace no se pinta como
//        enlace, y la migración repara las que estaban vacías o en «#».
//   Paso 1 · «Redirigiendo en 5 segundos…» redirige de verdad.
//
//   node pruebas/sexta-correcciones.mjs
//
// Contra producción NO: toca ajustes y tarjetas del home.
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
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();

const TIPOS = [
  'Organización sin fines de lucro',
  'Empresa o institución privada',
  'Institución educativa',
  'Municipalidad u organismo público',
  'Otra',
];

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const ancho = async (w) => p.setViewport({ width: w, height: 900, isMobile: w < 800, hasTouch: w < 800 });

/** Cómo se le pide el logo al tipo elegido, en la pantalla que sea. */
const comoPideElLogo = () => p.evaluate(() => {
  const caja = document.querySelector('[data-campo="org_logo"]');
  if (! caja || caja.getBoundingClientRect().height === 0) return { visible: false };
  const titulo = caja.querySelector('div');
  return {
    visible: true,
    obligatorio: caja.hasAttribute('data-obligatorio'),
    asterisco: /\*/.test(titulo.innerText),
    diceOpcional: /opcional/i.test(caja.innerText),
  };
});

/* ═══════════════ B5 — el wizard ═══════════════════════════════ */

t('B5 — el wizard, en escritorio');

await ancho(1440);
await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 3; });
await esperar(300);

for (const tipo of TIPOS) {
  await p.evaluate((x) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).tipo = x; }, tipo);
  await esperar(180);
  const l = await comoPideElLogo();
  const debe = tipo !== 'Otra';
  di(`${tipo}: ${debe ? 'obligatorio' : 'opcional'}`,
    l.visible && l.obligatorio === debe && l.asterisco === debe, `asterisco ${l.asterisco}, data-obligatorio ${l.obligatorio}`);
  di(`${tipo}: la ayuda dice «opcional» sólo si lo es`, l.diceOpcional === ! debe);
}

// Y la guía de errores lo exige: «Continuar» no deja pasar sin logo.
await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).tipo = 'Empresa o institución privada'; });
await esperar(200);
const faltaLogo = await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]'))
  .camposQueFaltan().map((e) => e.campo));
di('**La guía de errores lo pide como obligatorio**', faltaLogo.includes('org_logo'), faltaLogo.join(', '));

await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).tipo = 'Otra'; });
await esperar(200);
const conOtra = await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]'))
  .camposQueFaltan().map((e) => e.campo));
di('Y con «Otra» no', ! conOtra.includes('org_logo'), conOtra.join(', '));

t('B5 — el wizard, en el teléfono');

await ancho(390);
await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 3; });
await esperar(300);

for (const tipo of TIPOS) {
  await p.evaluate((x) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).tipo = x; }, tipo);
  await esperar(180);
  const l = await comoPideElLogo();
  di(`${tipo}: opcional en el teléfono`, l.visible && ! l.obligatorio && ! l.asterisco && l.diceOpcional,
    `asterisco ${l.asterisco}, data-obligatorio ${l.obligatorio}, dice opcional ${l.diceOpcional}`);
}

/* ═══════════════ B5 — crear cuenta ════════════════════════════ */

t('B5 — /mi-cuenta/registro');

await ancho(1440);
await p.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await esperar(300);

di('**Ahora tiene campo de logo**', (await comoPideElLogo()).visible);

for (const tipo of TIPOS) {
  await p.select('select[name="org_tipo"]', tipo);
  await esperar(180);
  const l = await comoPideElLogo();
  const debe = tipo !== 'Otra';
  di(`${tipo}: ${debe ? 'obligatorio' : 'opcional'}`, l.obligatorio === debe && l.asterisco === debe,
    `asterisco ${l.asterisco}, data-obligatorio ${l.obligatorio}`);
}

// Enviar sin logo con un tipo que lo exige: se corta y se dice.
await p.select('select[name="org_tipo"]', 'Organización sin fines de lucro');
await p.type('input[name="org_nombre"]', `Fundación Sin Logo ${Date.now()}`);
await p.type('input[name="name"]', 'Persona');
await p.type('input[name="email"]', `sinlogo.${Date.now()}@ejemplo.cl`);
await p.type('input[name="password"]', 'sin-logo-2026');
await p.type('input[name="password_confirmation"]', 'sin-logo-2026');
await p.evaluate(() => document.querySelector('form').querySelector('button[type="submit"]').click());
await esperar(600);
di('**Sin logo no se envía, y lo dice**', p.url().includes('/registro') && await p.evaluate(() =>
  /Sube el logo de tu organización/.test(document.body.innerText)), p.url().replace(B, ''));

// Y con logo, el servidor lo guarda: el campo es nuevo en esta pantalla.
const SELLO = Date.now();
const correoRegistro = `conlogo.${SELLO}@ejemplo.cl`;
const orgRegistro = `Fundación Con Logo ${SELLO}`;
await p.evaluate(() => { document.querySelector('input[name="org_nombre"]').value = ''; document.querySelector('input[name="email"]').value = ''; });
await p.type('input[name="org_nombre"]', orgRegistro);
await p.type('input[name="email"]', correoRegistro);
await (await p.$('input[name="org_logo"]')).uploadFile('public/img/logo-fundacion-trascender.png');
await p.waitForFunction(() => {
  const d = Alpine.$data(document.querySelector('[data-campo="org_logo"]'));
  return d.tiene && ! d.reduciendo;
}, { timeout: 8000 });
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => null),
  p.evaluate(() => document.querySelector('form').querySelector('button[type="submit"]').click()),
]);
di('Con logo, la cuenta se crea', ! p.url().includes('/registro'), p.url().replace(B, ''));
const guardado = ultima(tinker(`echo App\\Models\\Organization::where('nombre','${orgRegistro}')->value('logo_path');`));
di('**Y el logo queda en su ficha**', /^storage\/organizaciones\/.+\.(png|jpe?g|webp)$/.test(guardado), guardado);
di('Servido', (await p.goto(`${B}/${guardado}`)).status() === 200);

tinker(
  `Illuminate\\Support\\Facades\\Storage::disk('public')->delete(str_replace('storage/', '', '${guardado}'));`
  + ` $u = App\\Models\\User::where('email','${correoRegistro}')->first();`
  + ` if ($u) { $u->organization?->forceDelete(); $u->delete(); }`
  + ` App\\Models\\AccessLog::where('email','${correoRegistro}')->delete(); cache()->flush(); echo 'LIMPIO';`
);

await ancho(390);
await p.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await esperar(300);
await p.select('select[name="org_tipo"]', 'Organización sin fines de lucro');
await esperar(200);
const enMovil = await comoPideElLogo();
di('En el teléfono, opcional también aquí', ! enMovil.obligatorio && ! enMovil.asterisco && enMovil.diceOpcional);

/* ═══════════════ C5 — enlaces muertos ═════════════════════════ */

t('C5 — una tarjeta sin enlace no se pinta como enlace');

await ancho(1440);
const antes = ultima(tinker(`echo App\\Models\\ParticipationCard::where('titulo','Quiero ir a un panorama solidario')->value('href');`));
di('La migración dejó las tres con destino', ultima(tinker(
  `echo App\\Models\\ParticipationCard::whereIn('href', ['', '#'])->orWhereNull('href')->count();`)) === '0');

tinker(`App\\Models\\ParticipationCard::where('titulo','Quiero ir a un panorama solidario')->update(['href' => '#']); cache()->flush(); echo 'OK';`);
await p.goto(`${B}/?t=${Date.now()}`, { waitUntil: 'networkidle2' });
const muerta = await p.evaluate(() => {
  const c = [...document.querySelectorAll('.part-card')].find((x) => x.innerText.includes('panorama'));
  return { etiqueta: c?.tagName, href: c?.getAttribute('href'), sigueSaliendo: !! c };
});
di('Se sigue viendo la tarjeta', muerta.sigueSaliendo);
di('**Pero no es un enlace**', muerta.etiqueta === 'DIV' && muerta.href === null, `${muerta.etiqueta} href=${muerta.href}`);

tinker(`App\\Models\\ParticipationCard::where('titulo','Quiero ir a un panorama solidario')->update(['href' => '${antes}']); cache()->flush(); echo 'OK';`);
await p.goto(`${B}/?t=${Date.now()}`, { waitUntil: 'networkidle2' });
di('Con enlace, vuelve a serlo', await p.evaluate(() =>
  [...document.querySelectorAll('.part-card')].find((x) => x.innerText.includes('panorama'))?.tagName === 'A'));

di('La de voluntariado, a Voluntariados Chile y en otra pestaña', await p.evaluate(() => {
  const a = [...document.querySelectorAll('a.part-card')].find((x) => x.innerText.includes('Quiero ser voluntario'));
  return a?.href === 'https://voluntariadoschile.cl/oportunidades' && a.target === '_blank';
}));

di('El botón del kit ya no lleva al ancla muerta «#kit»', await p.evaluate(() =>
  ! [...document.querySelectorAll('a')].some((a) => a.getAttribute('href') === '#kit')));

/* ═══════════════ Paso 1 — la redirección ══════════════════════ */

t('Paso 1 — «Redirigiendo en 5 segundos…» redirige de verdad');

/*
 * Para seguir la redirección sin salir del sitio, el destino se apunta a una
 * página nuestra: lo que se comprueba es que el navegador se va de verdad al
 * enlace configurado, no cuál es ese enlace. El de producción se comprueba
 * arriba, en el ajuste.
 */
const DESTINO = `${B}/actividades?desvio=1`;
const urlAntes = ultima(tinker(`echo App\\Models\\Setting::get('voluntariado_url');`));
tinker(`App\\Models\\Setting::where('clave','voluntariado_url')->update(['valor' => '${DESTINO}']); cache()->flush(); echo 'OK';`);

const abrirAviso = async () => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(300);
  await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => b.innerText.includes('Sí, necesito voluntarios')).click());
  await esperar(400);
};

await abrirAviso();
const aviso = await p.evaluate(() => {
  const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
  return { abierto: d.redirigir, url: d.urlVoluntariado, texto: document.body.innerText.match(/Redirigiendo en \d+ segundos?…/)?.[0] };
});
di('El aviso se abre y anuncia la cuenta atrás', aviso.abierto && !! aviso.texto, aviso.texto);
di('Con el destino de Configuración', aviso.url === DESTINO, aviso.url);

await esperar(1200);
const bajando = await p.evaluate(() => document.body.innerText.match(/Redirigiendo en (\d+)/)?.[1]);
di('La cuenta atrás corre', Number(bajando) < 5, `${bajando} s`);

await p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 12000 }).catch(() => null);
di('**Al llegar a cero, se va al destino**', p.url() === DESTINO, p.url().replace(B, ''));

// El botón no espera.
await abrirAviso();
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 8000 }).catch(() => null),
  p.evaluate(() => [...document.querySelectorAll('button')].filter((b) => b.getBoundingClientRect().width > 0)
    .find((b) => /Ir a Voluntariados/.test(b.innerText)).click()),
]);
di('El botón lleva al mismo sitio sin esperar', p.url() === DESTINO, p.url().replace(B, ''));

// Y «Volver» cancela la cuenta atrás: seis segundos después sigue en el wizard.
await abrirAviso();
await p.evaluate(() => [...document.querySelectorAll('button')].filter((b) => b.getBoundingClientRect().width > 0)
  .find((b) => b.innerText.trim() === 'Volver').click());
await esperar(6500);
const trasVolver = await p.evaluate(() => ({ url: location.href, paso: Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso }));
di('**«Volver» cancela la cuenta atrás**', trasVolver.url.includes('/publicar-actividad'), trasVolver.url.replace(B, ''));
di('Y sigue al paso 2', trasVolver.paso === 2, `paso ${trasVolver.paso}`);

// Sin enlace configurado, el aviso no promete ninguna redirección.
tinker(`App\\Models\\Setting::where('clave','voluntariado_url')->update(['valor' => '']); cache()->flush(); echo 'OK';`);
await abrirAviso();
await esperar(1500);
di('Sin enlace, no anuncia redirección', await p.evaluate(() => ! /Redirigiendo en/.test(document.body.innerText)));
di('Y no ofrece el botón de ir', await p.evaluate(() =>
  ! [...document.querySelectorAll('button')].some((b) => b.getBoundingClientRect().width > 0 && /Ir a Voluntariados/.test(b.innerText))));
di('Ahí sigue en el wizard', p.url().includes('/publicar-actividad'), p.url().replace(B, ''));

tinker(`App\\Models\\Setting::where('clave','voluntariado_url')->update(['valor' => '${urlAntes}']); cache()->flush(); echo 'OK';`);

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
