// P2, P3 y P4 — el circuito de moderación como hilo de ida y vuelta.
//
// Recorre el circuito entero por la interfaz, con las dos cuentas: la ONG pide
// ajustes, la organización corrige y responde, y la ONG se entera. Lo que se
// comprueba no es que existan los campos sino que la información llega al otro
// lado, que era justo lo que se rompía.
//
//   php artisan tinker --execute="require base_path('pruebas/datos-hilo.php');"
//   node pruebas/hilo-moderacion.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const ID = 5;

const PIDE = 'Falta la dirección exacta y la fecha no cuadra con la edición.';
const RESPONDE = 'Listo: corregí la fecha y agregué la dirección exacta del punto de encuentro.';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1400, height: 1000 });

const entrar = async (puerta, correo, clave) => {
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', correo);
  await p.type('input[name="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

/* ═════════════════ La ONG pide ajustes ═══════════════════════════ */

t('La ONG pide ajustes (punto de partida)');

await entrar('/admin/login', 'admin@ong-laravel.test', 'admin1234');
await p.goto(`${B}/admin/actividades/${ID}`, { waitUntil: 'networkidle2' });

di('El hilo arranca vacío', (await texto()).includes('Aún no se ha escrito nada sobre esta actividad'));

await p.type('#comentario', PIDE);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('form[action$="/ajustes"] button[type="submit"]')]);

di('Tras pedirlos, el mensaje está en el hilo del panel', (await texto()).includes(PIDE));

/* ═════════════════ P2 y P3 — la organización corrige ═════════════ */

t('P3 — la organización ve el hilo y puede responder');

await entrar('/mi-cuenta/login', 'organizador@ong-laravel.test', 'organizador1234');
await p.goto(`${B}/mi-cuenta/actividades/${ID}/editar`, { waitUntil: 'networkidle2' });

const edicion = await texto();
di('La organización lee lo que le pidió la ONG', edicion.includes(PIDE));
di('Y lo lee dentro de la conversación', edicion.includes('Conversación con el equipo organizador'));
di('Hay un campo para responder', (await p.$('#mensaje_ajustes')) !== null);
di('Se avisa de que guardar la devuelve a revisión',
  edicion.includes('vuelve automáticamente a revisión'));

// El aviso tiene que verse de verdad, no sólo estar en el DOM: este proyecto
// ya tuvo un fallo entero por dar por bueno un aviso fuera de pantalla.
di('Y el campo de respuesta se ve', await p.$eval('#mensaje_ajustes', (n) => {
  const c = n.getBoundingClientRect();
  return c.height > 0 && c.width > 0 && getComputedStyle(n).visibility !== 'hidden';
}));

t('P2 — guardar la devuelve a revisión, sin botón aparte');

di('Ya no hay botón «Enviar a revisión»', ! edicion.includes('Enviar a revisión'));

await p.type('#mensaje_ajustes', RESPONDE);
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.click('form[action$="/actividades/' + ID + '"] button[type="submit"].btn-primary'),
]);

const guardado = await texto();
di('La pantalla de guardado dice que las correcciones salieron', guardado.includes('Enviamos tus correcciones'));

await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
const listado = await texto();
di('En su listado ya no dice «Necesitamos algunos ajustes»',
  ! listado.includes('Necesitamos algunos ajustes'));
di('Y dice que se está revisando', listado.includes('Estamos revisando'));

await p.goto(`${B}/mi-cuenta/actividades/${ID}/editar`, { waitUntil: 'networkidle2' });
const trasGuardar = await texto();
di('Su propio mensaje queda en el hilo', trasGuardar.includes(RESPONDE));
di('Y el de la ONG sigue estando', trasGuardar.includes(PIDE));
di('Ya no se le pide responder otra vez', (await p.$('#mensaje_ajustes')) === null);

/* ═════════════════ P4 — la ONG se entera ═════════════════════════ */

t('P4 — la ONG se entera sin tener que ir a buscarlo');

await entrar('/admin/login', 'admin@ong-laravel.test', 'admin1234');

const escritorio = await texto();
di('El escritorio avisa de que volvió corregida',
  /volvi[oó] corregida y espera|volvieron corregidas y esperan/i.test(escritorio));

await p.goto(`${B}/admin/actividades`, { waitUntil: 'networkidle2' });
const listadoAdmin = await texto();
di('Hay pestaña «Volvieron corregidas»', listadoAdmin.includes('Volvieron corregidas'));
// La insignia va en versalitas por CSS, así que el texto llega en mayúsculas.
di('Y la fila lleva la insignia', /volvi[oó] corregida/i.test(listadoAdmin));

await p.goto(`${B}/admin/actividades?vueltas=1`, { waitUntil: 'networkidle2' });
di('El filtro la encuentra', (await texto()).includes('Ruta patrimonial'));

await p.goto(`${B}/admin/actividades/${ID}`, { waitUntil: 'networkidle2' });
const ficha = await texto();
di('La ficha dice que volvió corregida', ficha.includes('Volvió corregida'));
di('Y el hilo enseña lo que respondió la organización', ficha.includes(RESPONDE));
di('Junto a lo que había pedido la ONG', ficha.includes(PIDE));

// El orden importa: es una conversación, se lee de arriba abajo.
di('En orden de conversación', ficha.indexOf(PIDE) < ficha.indexOf(RESPONDE));

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
