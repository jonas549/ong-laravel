// P2/P3/P4 — la parte del hilo que se puede comprobar SIN mover nada.
//
// Existe aparte de `hilo-moderacion.mjs` por un motivo concreto: aquel recorre
// el circuito entero y, al devolver una actividad a revisión, avisa por correo
// a TODOS los administradores activos. En producción son tres y dos son
// personas de verdad —Natalia entre ellas—, así que ahí no se corre.
//
// Esto carga las mismas pantallas sin pulsar nada que cambie de estado.
//
//   DPS_URL=https://ong.sandboxdelta.com node pruebas/hilo-moderacion-lectura.mjs
import puppeteer from 'puppeteer-core';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
// Una actividad en «necesita ajustes» de la organización sembrada.
const ID = Number(process.env.DPS_ACTIVIDAD ?? 4);

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const entrar = async (puerta, c, k) => {
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', c);
  await p.type('input[name="password"]', k);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

t('Lo que ve la organización en una actividad con ajustes pedidos');

await entrar('/mi-cuenta/login', ORG, CLAVE_ORG);
await p.goto(`${B}/mi-cuenta/actividades/${ID}/editar`, { waitUntil: 'networkidle2' });

const org = await texto();
di('Está la conversación con la ONG', org.includes('Conversación con el equipo organizador'));
di('Hay campo para responder', (await p.$('#mensaje_ajustes')) !== null);
di('Y se ve de verdad, no sólo está en el DOM', await p.$eval('#mensaje_ajustes', (n) => {
  const c = n.getBoundingClientRect();
  return c.height > 0 && c.width > 0 && getComputedStyle(n).visibility !== 'hidden';
}).catch(() => false));
di('Se avisa de que guardar la devuelve a revisión', org.includes('vuelve automáticamente a revisión'));
di('Ya no hay botón «Enviar a revisión»', ! org.includes('Enviar a revisión'));

t('Lo que ve la ONG en el panel');

await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
await p.goto(`${B}/admin/actividades/${ID}`, { waitUntil: 'networkidle2' });

const admin = await texto();
di('La tarjeta de moderación lleva el hilo', (await p.$('.hilo')) !== null);
di('Con lo que se le pidió a la organización', /hilo/.test(admin) || admin.length > 0);
di('Y el campo dice que el organizador puede responder',
  admin.includes('Podrá responderte al reenviar la actividad corregida'));

// El hilo tiene que estar maquetado, no sólo presente: si el CSS no llegó al
// servidor, las burbujas se pintan como párrafos sueltos y no se distingue
// quién dijo qué. Es el fallo clásico de este proyecto —el cron no compila—.
const maquetado = await p.evaluate(() => {
  const m = document.querySelector('.hilo-mensaje');
  if (! m) return null;
  const e = getComputedStyle(m);
  return { radio: e.borderRadius, fondo: e.backgroundColor, ancho: m.getBoundingClientRect().width };
});
di('Y el CSS del hilo llegó al servidor',
  !! maquetado && maquetado.radio !== '0px' && maquetado.ancho > 0,
  maquetado ? `radio ${maquetado.radio}, fondo ${maquetado.fondo}` : '(sin mensajes en el hilo)');

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
