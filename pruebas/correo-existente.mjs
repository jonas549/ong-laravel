// Punto 2 del 23/09 — «cuenta ya registrada» sale tarde y borra lo escrito.
//
// Antes, que el correo del paso 3 ya tuviera cuenta se sabía sólo al enviar el
// formulario entero, y el «Inicia sesión» del aviso llevaba a la página de
// acceso: lo escrito se perdía. Ahora se dice al salir del campo y el aviso
// abre el acceso del propio wizard, que no recarga.
//
// Se comprueba: que sale a tiempo, que se va con otro correo, que entrar desde
// él conserva la actividad escrita, que el rebote del servidor (sin la
// consulta) ya no manda fuera, y que la consulta tiene freno propio.
//
//   node pruebas/correo-existente.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { CLAVE_ORG, ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
let seccion = '';
const t = (x) => { seccion = x.slice(0, 3); console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

// Los frenos cuentan en la caché y el bloqueo en `access_logs`: se empieza limpio.
const soltarElFreno = () => tinker(`App\\Models\\AccessLog::where('email','${ORG}')->delete(); cache()->flush(); echo 'libre';`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(`[${seccion}] ${String(e).split('\n')[0]} @ ${p.url()}`));

const W = '[x-data^="wizard"]';
const estado = () => p.evaluate((W) => {
    const d = Alpine.$data(document.querySelector(W));

    return { conSesion: d.conSesion, accesoAbierto: d.accesoAbierto, accesoCorreo: d.accesoCorreo, correoExiste: d.correoExiste, paso: d.paso };
}, W);
const irA = async (n) => { await p.evaluate((W, n) => { Alpine.$data(document.querySelector(W)).paso = n; }, W, n); await esperar(300); };

// Lo que dice la verdad es el alto (ver README: `offsetParent` miente).
const avisoVisible = () => p.evaluate(() => (document.querySelector('[data-correo-existe]')?.getBoundingClientRect().height ?? 0) > 0);

// Ctrl+A y no triple clic: en un `type="email"` el triple clic se para en el
// punto y el correo nuevo acaba pegado al viejo.
const escribirCorreo = async (correo) => {
    await p.click('input[name="email"]');
    await p.keyboard.down('Control'); await p.keyboard.press('KeyA'); await p.keyboard.up('Control');
    await p.keyboard.press('Backspace');
    await p.type('input[name="email"]', correo);
    await p.keyboard.press('Tab');
};

let consultas = 0;
p.on('request', (r) => { if (r.url().endsWith('/publicar-actividad/correo')) consultas++; });

const salir = async () => {
    await p.goto(`${B}/`, { waitUntil: 'domcontentloaded' });
    await p.evaluate(async (base) => {
        const doc = await (await fetch(base + '/', { credentials: 'same-origin' })).text();
        const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
        if (token) {
            await fetch(base + '/mi-cuenta/logout', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(token),
            });
        }
    }, B);
};

soltarElFreno();
await salir();

/* ══════════════════════════════════════════════════════════════════ */
t('1 · El aviso sale al salir del campo, sin enviar nada');

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await irA(3);

di('de entrada no hay aviso', ! await avisoVisible());

const urlAntes = p.url();
await escribirCorreo(ORG);
await p.waitForFunction((W) => Alpine.$data(document.querySelector(W)).correoExiste === true, { timeout: 4000 }, W).catch(() => {});

di('**con un correo que ya tiene cuenta, sale**', await avisoVisible());
di('sin salir de la página', p.url() === urlAntes, p.url());
di('dice el mensaje de siempre', await p.evaluate(() => document.querySelector('[data-campo="email"]').innerText.includes('Este usuario ya existe')));
di('el campo se marca en rojo', await p.$eval('input[name="email"]', (i) => i.classList.contains('is-invalid')));
di('«Inicia sesión» es un botón, no un enlace a otra pantalla', await p.evaluate(() => {
    const aviso = document.querySelector('[data-correo-existe]');
    return !! [...aviso.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Inicia sesión')
        && ! aviso.querySelector('a[href*="/mi-cuenta/login"]');
}));
di('recuperar la contraseña abre otra pestaña', await p.evaluate(() => document.querySelector('[data-correo-existe] a[href*="recuperar-contrasena"]')?.target === '_blank'));

const antes = consultas;
await p.click('input[name="email"]');
await p.keyboard.press('Tab');
await esperar(900);
di('el mismo correo no se vuelve a consultar', consultas === antes, `${consultas - antes} consultas más`);

t('2 · Con otro correo, el aviso se va');

await escribirCorreo(`nadie-${Date.now()}@ejemplo.cl`);
await esperar(1200);
di('un correo libre no lo enseña', ! await avisoVisible());
di('ni marca el campo', ! await p.$eval('input[name="email"]', (i) => i.classList.contains('is-invalid')));

await escribirCorreo(ORG.toUpperCase());
await p.waitForFunction((W) => Alpine.$data(document.querySelector(W)).correoExiste === true, { timeout: 4000 }, W).catch(() => {});
di('en mayúsculas también lo reconoce (como el envío)', await avisoVisible());

await p.click('input[name="email"]');
await p.keyboard.type('x');
await esperar(100);
di('al volver a escribir se quita en el acto', ! await avisoVisible());

await escribirCorreo('a-medias@');
await esperar(1200);
di('a medio escribir, ni aviso', ! await avisoVisible());

/* ══════════════════════════════════════════════════════════════════ */
t('3 · Entrar desde el aviso conserva lo escrito');

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await irA(3);
await p.type('input[name="org_nombre"]', 'Una organización cualquiera');
await irA(4);

const TITULO = 'Actividad escrita antes de ver el aviso';
const DESCRIPCION = 'Esto tiene que seguir aquí después de entrar desde el aviso.';
await p.type('input[name="titulo"]', TITULO);
await p.type('textarea[name="descripcion"]', DESCRIPCION);
await p.evaluate((W) => {
    document.querySelector('[data-campo="temas"] button.chip').click();
    Alpine.$data(document.querySelector(W)).formato = 'Online';
}, W);

// Vuelve al paso 3 y escribe su correo, que ya tiene cuenta.
await irA(3);
await escribirCorreo(ORG);
await p.waitForFunction((W) => Alpine.$data(document.querySelector(W)).correoExiste === true, { timeout: 4000 }, W).catch(() => {});
di('sale el aviso', await avisoVisible());

await p.evaluate(() => [...document.querySelectorAll('[data-correo-existe] button')].find((b) => b.textContent.trim() === 'Inicia sesión').click());
await esperar(300);

const abierto = await estado();
di('abre el acceso del wizard', abierto.accesoAbierto === true);
di('con su correo ya puesto', abierto.accesoCorreo === ORG, abierto.accesoCorreo);

await p.type('.acceso-caja input[type="password"]', CLAVE_ORG);
await p.keyboard.press('Enter');
await p.waitForFunction((W) => Alpine.$data(document.querySelector(W)).conSesion === true, { timeout: 8000 }, W).catch(() => {});
await esperar(400);

const tras = await estado();
di('**entra sin recargar**', tras.conSesion === true && p.url().endsWith('/publicar-actividad'), p.url());
di('el aviso desaparece', ! await avisoVisible() && tras.correoExiste === false);
di('**el título sigue**', await p.$eval('input[name="titulo"]', (n) => n.value) === TITULO);
di('**la descripción sigue**', await p.$eval('textarea[name="descripcion"]', (n) => n.value) === DESCRIPCION);
di('el formato sigue', await p.evaluate((W) => Alpine.$data(document.querySelector(W)).formato, W) === 'Online');
di('y el tema elegido', await p.evaluate((W) => Alpine.$data(document.querySelector(W)).sel.temas.length, W) === 1);

/* ══════════════════════════════════════════════════════════════════ */
t('4 · Si la consulta no llega, el rebote del envío tampoco manda fuera');

await salir();
soltarElFreno();

await p.setRequestInterception(true);
const cortar = (r) => (r.url().endsWith('/publicar-actividad/correo') ? r.abort() : r.continue());
p.on('request', cortar);

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await irA(3);
await escribirCorreo(ORG);
await esperar(1000);
di('sin respuesta, no se inventa el aviso', ! await avisoVisible());

// `submit()` del formulario: se salta la guía de errores del navegador, que es
// lo que haría un navegador sin JavaScript. El servidor valida igual.
await Promise.all([
    p.waitForNavigation({ timeout: 20000 }),
    p.$eval('input[name="email"]', (i) => i.form.submit()),
]);
await p.waitForFunction(() => window.Alpine !== undefined);
await irA(3);

di('tras el rebote, el aviso sale ya puesto', await avisoVisible());
di('el mensaje aparece una sola vez junto al campo', await p.evaluate(() => {
    const campo = document.querySelector('[data-campo="email"]');
    return [...campo.querySelectorAll('.field-error')].filter((e) => e.getBoundingClientRect().height > 0 && e.textContent.includes('Este usuario ya existe')).length === 1;
}));
di('y tampoco lleva a la página de acceso', await p.evaluate(() => ! document.querySelector('[data-campo="email"] a[href*="/mi-cuenta/login"]')));

p.off('request', cortar);
await p.setRequestInterception(false);

/* ══════════════════════════════════════════════════════════════════ */
t('5 · La consulta tiene freno, y es sólo suyo');

soltarElFreno();
const codigos = await p.evaluate(async () => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const pedir = (ruta, cuerpo) => fetch(ruta, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify(cuerpo),
    }).then((r) => r.status);

    const correo = [];
    for (let i = 0; i < 22; i++) correo.push(await pedir('/publicar-actividad/correo', { email: `x${i}@ejemplo.cl` }));

    // Con el de la consulta agotado, entrar tiene que seguir respondiendo.
    const entrar = await pedir('/publicar-actividad/entrar', { email: 'nadie@ejemplo.cl', password: 'x' });

    return { correo, entrar };
});

di('corta a partir de la 21', codigos.correo.slice(0, 20).every((c) => c === 200) && codigos.correo[20] === 429, codigos.correo.join(' '));
di('**y no gasta el freno de entrar**', codigos.entrar === 422, String(codigos.entrar));

tinker(`App\\Models\\AccessLog::where('email','nadie@ejemplo.cl')->delete(); echo 'ok';`);
soltarElFreno();

/* ══════════════════════════════════════════════════════════════════ */
t('6 · Sin errores de JavaScript');

di('ninguno', errores.length === 0, errores.join(' | '));

await salir();
await nav.close();

console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
