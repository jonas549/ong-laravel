// Las tres comodidades de campo de la tanda del 11/09. En Chrome.
//
//   28 — «Usar el mismo correo de la cuenta» tiene que RELLENAR el campo de
//        contacto. La casilla existía y no hacía nada: marcarla no se notaba y
//        el campo seguía vacío.
//   29 — visor de contraseña (mostrar/ocultar) en los campos de clave.
//   31 — «https://» automático en sitio web y red social. Sin él, type="url"
//        da por inválido «www.mi.cl», y un enlace guardado sin protocolo se
//        resuelve como RUTA RELATIVA: acaba apuntando al propio sitio.
//
// Por qué en Chrome y no por HTTP: las tres son JavaScript. Por HTTP no se
// ejecuta ninguna y las tres pasarían sin existir.
//
//   node pruebas/campos-formulario.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
await p.setViewport({ width: 1440, height: 900 });

const abrir = async (ruta) => {
  await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
};

const valor = (sel) => p.$eval(sel, (n) => n.value);

/* Los pasos del wizard se ocultan con Alpine, así que hay que ir al paso donde
 * vive cada campo antes de poder pulsarlo. Es lo que hacen `wizard-errores` y
 * `boton-envio`. */
const paso = async (n) => {
  await p.evaluate((n) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = n; }, n);
  await esperar(250);
};
const limpiar = (sel) => p.$eval(sel, (n) => { n.value = ''; n.dispatchEvent(new Event('input', { bubbles: true })); });

/* ═════════════════════════════ 28 — el correo que se copia ══════════ */

t('Punto 28 — «Usar el mismo correo de la cuenta»');

await abrir('/publicar-actividad');

await paso(4);
di('La casilla arranca marcada', await p.$eval('input[name="usar_correo_cuenta"]', (n) => n.checked));

await paso(3);
await p.click('input[name="email"]');
await p.type('input[name="email"]', 'organizacion@ejemplo.cl');
await esperar(250);
await paso(4);

di('Con la casilla marcada, el de contacto se rellena solo',
  (await valor('input[name="correo_contacto"]')) === 'organizacion@ejemplo.cl',
  await valor('input[name="correo_contacto"]'));

di('Y queda de sólo lectura, para que no digan cosas distintas',
  await p.$eval('input[name="correo_contacto"]', (n) => n.readOnly));

// readonly y no disabled: un deshabilitado no se envía, y el servidor se
// quedaría sin correo de contacto justo cuando el usuario ha dicho que quiere
// el de su cuenta.
di('Pero NO deshabilitado, o no viajaría en el envío',
  await p.$eval('input[name="correo_contacto"]', (n) => ! n.disabled));

await paso(3);
await limpiar('input[name="email"]');
await p.type('input[name="email"]', 'otra@ejemplo.cl');
await esperar(250);
await paso(4);
di('Cambiar el de la cuenta arrastra al de contacto',
  (await valor('input[name="correo_contacto"]')) === 'otra@ejemplo.cl',
  await valor('input[name="correo_contacto"]'));

await p.click('input[name="usar_correo_cuenta"]');
await esperar(250);
di('Al desmarcar se puede volver a escribir',
  await p.$eval('input[name="correo_contacto"]', (n) => ! n.readOnly));

await limpiar('input[name="correo_contacto"]');
await p.type('input[name="correo_contacto"]', 'publico@ejemplo.cl');
await esperar(200);
await p.$eval('input[name="email"]', (n) => { n.value = 'tercero@ejemplo.cl'; n.dispatchEvent(new Event('input', { bubbles: true })); });
await esperar(250);
di('Y desmarcada, el de la cuenta ya no lo pisa',
  (await valor('input[name="correo_contacto"]')) === 'publico@ejemplo.cl',
  await valor('input[name="correo_contacto"]'));

await p.click('input[name="usar_correo_cuenta"]');
await esperar(250);
di('Volver a marcarla copia otra vez el de la cuenta',
  (await valor('input[name="correo_contacto"]')) === 'tercero@ejemplo.cl',
  await valor('input[name="correo_contacto"]'));

/* ═════════════════════════════ 31 — el https:// ═════════════════════ */

t('Punto 31 — el «https://» que nadie tiene que escribir');

const probarProtocolo = async (sel, escrito, esperado, titulo) => {
  await p.$eval(sel, (n) => { n.value = ''; });
  await p.click(sel);
  if (escrito) await p.type(sel, escrito);
  // Se completa AL SALIR del campo, no mientras se escribe: hacerlo en cada
  // pulsación mueve el cursor y pelea con quien está pegando una dirección.
  await p.$eval(sel, (n) => n.blur());
  await esperar(150);
  const v = await valor(sel);
  di(titulo, v === esperado, `«${escrito}» → «${v}»`);
};

await probarProtocolo('input[name="enlace_web"]', 'www.misitio.cl', 'https://www.misitio.cl',
  'Al sitio web le pone el protocolo');
await probarProtocolo('input[name="enlace_red_social"]', 'instagram.com/mi-org', 'https://instagram.com/mi-org',
  'Y a la red social también');
await probarProtocolo('input[name="enlace_web"]', 'https://ya.tiene.cl', 'https://ya.tiene.cl',
  'Lo que ya trae protocolo no se toca');
await probarProtocolo('input[name="enlace_web"]', 'http://inseguro.cl', 'http://inseguro.cl',
  'Y un http:// explícito se respeta, no se fuerza a https');
await probarProtocolo('input[name="enlace_web"]', '', '',
  'Un campo vacío se queda vacío');
await probarProtocolo('input[name="enlace_web"]', 'todavia-escribiendo', 'todavia-escribiendo',
  'Lo que aún no parece un dominio se deja en paz');

// Lo que de verdad importa: que el campo quede VÁLIDO para el navegador.
await p.$eval('input[name="enlace_web"]', (n) => { n.value = ''; });
await p.click('input[name="enlace_web"]');
await p.type('input[name="enlace_web"]', 'www.misitio.cl');
await p.$eval('input[name="enlace_web"]', (n) => n.blur());
await esperar(150);
di('Y con el protocolo puesto, el navegador ya lo da por válido',
  await p.$eval('input[name="enlace_web"]', (n) => n.checkValidity()));

/* ═════════════════════════════ 29 — el visor de contraseña ══════════ */

t('Punto 29 — ver lo que se escribió en la contraseña');

await paso(3);
const clave = 'input[name="password"]';
di('El campo nace oculto', (await p.$eval(clave, (n) => n.type)) === 'password');
di('Y tiene su botón al lado', (await p.$('.campo-visor')) !== null);

di('El botón NO es de envío, o pulsarlo mandaría el formulario',
  await p.$eval('.campo-visor', (n) => n.type === 'button'));

await p.click(clave);
await p.type(clave, 'Secreta123');
await p.click('.campo-visor');
await esperar(150);
di('Al pulsarlo se ve la contraseña', (await p.$eval(clave, (n) => n.type)) === 'text');
di('Sin perder lo escrito', (await valor(clave)) === 'Secreta123');
di('Y lo anuncia para el lector de pantalla',
  await p.$eval('.campo-visor', (n) => n.getAttribute('aria-pressed') === 'true'));
di('El foco vuelve al campo, no se queda en el botón',
  await p.evaluate((s) => document.activeElement === document.querySelector(s), clave));

await p.click('.campo-visor');
await esperar(150);
di('Y al pulsarlo otra vez se vuelve a ocultar', (await p.$eval(clave, (n) => n.type)) === 'password');

// El campo no puede desbordar su columna: aquí no hay reset de box-sizing y el
// relleno de 46 px del botón se sumaría por fuera si .fld no lo acotara.
const medidas = await p.$eval(clave, (n) => {
  const caja = n.closest('.campo-con-visor');
  return { campo: n.getBoundingClientRect().width, caja: caja.getBoundingClientRect().width };
});
di('El botón no ensancha el campo fuera de su caja',
  Math.abs(medidas.campo - medidas.caja) < 1.5, `${medidas.campo} vs ${medidas.caja}`);

/* ═════════════════════ 5 — online no pide dirección física ══════════ */

t('Punto 5 — una actividad ONLINE no tiene dirección que escribir');

const faltan = () => p.evaluate(
  () => Alpine.$data(document.querySelector('[x-data^="wizard"]')).camposQueFaltan().map((e) => e.campo));

await abrir('/publicar-actividad');
await paso(4);

// Presencial es el formato por defecto: ahí la dirección SÍ se pide.
await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).formato = 'Presencial'; });
await esperar(250);
di('Presencial sigue pidiendo la dirección', (await faltan()).includes('direccion'),
  (await faltan()).join(', '));

await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).formato = 'Online'; });
await esperar(250);
di('Online ya NO la pide', ! (await faltan()).includes('direccion'), (await faltan()).join(', '));

// Híbrido tiene parte presencial: se sigue pidiendo, y esto lo fija.
await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).formato = 'Híbrido'; });
await esperar(250);
di('Híbrido la vuelve a pedir, que tiene parte presencial',
  (await faltan()).includes('direccion'), (await faltan()).join(', '));

// Y el otro relevo, el que ya existía, sigue funcionando.
await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).sinFecha = true; });
await esperar(250);
di('«Disponible de forma permanente» la sigue relevando igual',
  ! (await faltan()).includes('direccion'), (await faltan()).join(', '));

t('Sin errores de JavaScript');
di('La consola quedó limpia', errores.length === 0, errores.slice(0, 3).join(' · '));

console.log('');
console.log(`${ok} bien · ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
