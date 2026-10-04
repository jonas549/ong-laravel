// Cerrar sesión, pulsar «atrás» y entrar con otra cuenta. En Chrome.
//
// EL BUG (04/10), con la secuencia exacta del cliente:
//   1. Con sesión de administrador, cerrar sesión.
//   2. «Atrás» → volvía el dashboard, como si la sesión siguiera viva.
//   3. «Cerrar sesión» desde ahí → 419.
//   4. Refrescar → al home.
//   5. Entrar como organizador → 403 «No puedes ver esta página».
//   6. «Volver al inicio» → ahí sí, su panel.
//
// Las causas: las pantallas con sesión no eran `no-store` y el navegador las
// devolvía de su memoria; desde esa copia el token ya no valía (419); y al
// refrescarla sin sesión quedaba `/admin` como destino pendiente, que el login
// de organizador seguía al pie de la letra (403).
//
//   node pruebas/cerrar-sesion.mjs
import puppeteer from 'puppeteer-core';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1280, height: 900 });
const estados = [];
p.on('response', (r) => r.request().isNavigationRequest() && estados.push(r.status()));

const ruta = () => new URL(p.url()).pathname;
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

const entrarAdmin = async () => {
  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', ADMIN);
  await p.type('input[type="password"]', CLAVE_ADMIN);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
};

const entrarOrganizador = async () => {
  await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p.type('[name="email"]', ORG);
  await p.type('[name="password"]', CLAVE_ORG);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};

/** Pulsa el «Cerrar sesión» que haya en pantalla, como haría una persona. */
const cerrarSesion = async () => {
  estados.length = 0;
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    p.evaluate(() => [...document.querySelectorAll('form')].find((f) => /logout/.test(f.action))?.submit()),
  ]);
};

t('La secuencia del cliente, con sesión de administrador');

await entrarAdmin();
di('1 · Entra al dashboard', ruta() === '/admin', ruta());

const cabeceras = await p.evaluate(async () => (await fetch('/admin')).headers.get('cache-control'));
di('   Y el panel se sirve con no-store', /no-store/.test(cabeceras ?? ''), cabeceras);

await cerrarSesion();
di('1 · Cerrar sesión lleva al login del panel', ruta() === '/admin/login', ruta());

estados.length = 0;
await p.goBack({ waitUntil: 'networkidle2' });
await esperar(800);
const tras = await texto();
const pareceDashboard = await p.evaluate(() => document.body.hasAttribute('data-con-sesion'));
di('2 · «Atrás» NO vuelve a pintar el dashboard con sesión', !pareceDashboard, `${ruta()} · ${tras.slice(0, 60)}`);
di('   Lo que sale es el login, servido de nuevo por el servidor', ruta() === '/admin/login', ruta());

// 3 · Si aun así quedara un «Cerrar sesión» a mano, no puede dar 419. Se
// fuerza: el formulario de una página vieja, con un token que ya no vale.
await p.goto(`${B}/`, { waitUntil: 'networkidle2' });
estados.length = 0;
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.evaluate((b) => {
    const f = document.createElement('form');
    f.method = 'POST'; f.action = `${b}/admin/logout`;
    f.innerHTML = '<input type="hidden" name="_token" value="token-de-una-sesion-que-ya-no-existe">';
    document.body.appendChild(f); f.submit();
  }, B),
]);
di('3 · «Cerrar sesión» con un token caducado no da 419', !estados.includes(419), `estados: ${estados.join(',')} → ${ruta()}`);
di('   Y dice que la sesión ya está cerrada', /Tu sesión ya está cerrada/.test(await texto()));

// 4 · Refrescar una pantalla del panel sin sesión: deja /admin pendiente.
await p.goto(`${B}/admin`, { waitUntil: 'networkidle2' });
di('4 · /admin sin sesión manda al login del panel', ruta() === '/admin/login', ruta());

await entrarOrganizador();
di('5 · Entrar como organizador lleva a SU panel, no a un 403', ruta() === '/mi-cuenta/actividades', ruta());
di('   Sin «No puedes ver esta página»', !/No puedes ver esta página|No tienes acceso/.test(await texto()));

t('Una cuenta en la sección del otro rol va a su panel');

await p.goto(`${B}/admin/organizaciones`, { waitUntil: 'networkidle2' });
di('Organizador en /admin/organizaciones → a su cuenta', ruta() === '/mi-cuenta/actividades', ruta());
di('   Con una línea que dice por qué', /Esa página es del panel de administración/.test(await texto()));

const postAjeno = await p.evaluate(async () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  const r = await fetch('/admin/organizaciones', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'text/html' }, redirect: 'manual' });
  return r.status;
});
di('Pero un ENVÍO a una ruta del panel sigue siendo 403', postAjeno === 403, `${postAjeno}`);

const mia = await p.evaluate(async () => (await fetch('/mi-cuenta/actividades')).headers.get('cache-control'));
di('Mi-cuenta también se sirve con no-store', /no-store/.test(mia ?? ''), mia);

await cerrarSesion();
await entrarAdmin();
await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
di('Administrador en /mi-cuenta/actividades → al panel', ruta() === '/admin', ruta());
di('   Con una línea que dice por qué', /Esa página es de las cuentas de organización/.test(await texto()));

t('El destino pendiente sólo vale en su puerta');

await cerrarSesion();
await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
di('/mi-cuenta sin sesión manda al login de organizador', ruta() === '/mi-cuenta/login', ruta());
await entrarAdmin();
di('Entrar al panel después no lleva a mi-cuenta', ruta() === '/admin', ruta());

await cerrarSesion();
await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
await p.type('[name="email"]', ORG);
await p.type('[name="password"]', CLAVE_ORG);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
di('Y un destino de su lado se sigue respetando', ruta() === '/mi-cuenta/actividades', ruta());

t('Sin sesión, las páginas públicas siguen pudiendo guardarse');
await cerrarSesion();
const publica = await p.evaluate(async () => (await fetch('/')).headers.get('cache-control'));
di('El home sin sesión no lleva no-store', !/no-store/.test(publica ?? ''), publica);

console.log('');
console.log(`${ok} bien · ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
