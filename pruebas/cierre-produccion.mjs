// C1, C3 y C4 de la cuarta tanda, comprobados en producción SIN mover nada.
//
// Existe aparte de las suites locales por el mismo motivo que
// `hilo-moderacion-lectura.mjs`: allí hay tres administradores y dos son
// personas de verdad. Aquí no se envía ningún formulario que cambie de
// estado. Se carga, se mira y se cierra.
//
// Lo único que escribe es la sesión: hay que entrar para ver el panel.
//
//   DPS_URL=https://ong.sandboxdelta.com node pruebas/cierre-produccion.mjs
import puppeteer from 'puppeteer-core';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
const entrar = async (puerta, c, k) => {
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', c);
  await p.type('input[name="password"]', k);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};
const salir = async () => {
  await p.evaluate(async () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    for (const puerta of ['/mi-cuenta/logout', '/admin/logout']) {
      await fetch(puerta, {
        method: 'POST',
        headers: { 'content-type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: token ?? '' }).toString(),
        credentials: 'same-origin',
      }).catch(() => {});
    }
  });
};

/* ═════════════ C1 — el buscador, en las DOS pantallas ═══════════════ */

t('C1 — crear cuenta de organizador tiene el buscador de organizaciones');

await p.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await esperar(300);

di('El campo del nombre es el buscador, no un texto suelto',
  (await p.$('[x-data^="registroOrganizador"] input[name="org_nombre"]')) !== null);
di('Con su lista de sugerencias esperando', (await p.$('.org-sugerencias')) !== null);
di('Y el campo oculto donde viaja la organización elegida',
  (await p.$('input[type="hidden"][name="org_id"]')) !== null);

// Escribir NO envía nada: es un GET al listado, el mismo que hace el wizard.
await p.type('input[name="org_nombre"]', 'fu', { delay: 60 });
await esperar(1200);

const sugerencias = await p.$$eval('.org-sugerencias li, .org-sugerencias button',
  (n) => n.map((x) => x.innerText.replace(/\s+/g, ' ').trim()).filter(Boolean));
di('Escribir dos letras ofrece organizaciones del listado', sugerencias.length > 0,
  `${sugerencias.length}: ${sugerencias.slice(0, 2).join(' · ')}`);

t('C1 — y el paso 3 del wizard sigue teniendo el suyo');

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await esperar(300);
di('El wizard monta el mismo buscador',
  (await p.$('input[name="org_nombre"]')) !== null && (await p.$('.org-sugerencias')) !== null);

/* ═════════════ C4 — el paso 3, sólo si hace falta ═══════════════════ */

t('C4 — el paso 3 con la cuenta de organizador');

await entrar('/mi-cuenta/login', ORG, CLAVE_ORG);
di('La sesión queda abierta', ! p.url().includes('/login'), p.url());

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await esperar(400);

const estado = await p.evaluate(() => {
  const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));

  return { salta: d.saltaPaso3(), cambiado: d.tipoCambiado(), tipo: d.tipo, ficha: d.tipoDeLaFicha };
});
di('El componente responde a saltaPaso3()', typeof estado.salta === 'boolean', String(estado.salta));
di('Y el tipo de partida es el de su ficha, sin cambiar', estado.cambiado === false,
  `${estado.tipo} / ${estado.ficha}`);

const barra = await p.$$eval('.steplink', (n) => n
  .filter((b) => b.offsetParent !== null)
  .map((b) => b.innerText.replace(/\s+/g, ' ').trim()));

di('La barra coincide con lo que dice el componente',
  estado.salta ? barra.length === 4 : barra.length === 5, barra.join(' | '));
di('«Tu organización» está si y sólo si el paso 3 se pinta',
  barra.some((x) => x.includes('Tu organización')) === ! estado.salta);

/*
 * Lo que de verdad pide C4: los números de la barra van seguidos. Con el paso
 * 3 fuera son 1,2,3,4 y no 1,2,4,5 — el hueco sería decir que hay un paso que
 * no se está viendo.
 */
const numeros = barra.map((x) => x[0]).join('');
di('**Sin hueco: los números van seguidos**', numeros === (estado.salta ? '1234' : '12345'), numeros);

const CAMPOS = ['org_nombre', 'org_tipo_otro', 'org_unidad_educativa', 'org_logo'];
const pedidos = await p.evaluate((campos) => campos.filter((c) => {
  const e = document.querySelector(`[data-campo="${c}"]`);

  return e && e.getBoundingClientRect().height > 0;
}), CAMPOS);

di('Con el paso 3 saltado no se pide ningún dato de la organización',
  ! estado.salta || pedidos.length === 0, pedidos.join(', ') || '(ninguno)');

const oculto = await p.$eval('input[type="hidden"][name="org_nombre"]', (e) => e.value).catch(() => null);
di('Y lo que no se pregunta viaja igual, en campo oculto',
  pedidos.includes('org_nombre') ? oculto === null : (oculto ?? '') !== '', String(oculto));

/* ═════════════ C3 — la columna «Estado», fuera ══════════════════════ */

t('C3 — las inscripciones del panel');

await salir();
await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
di('La sesión de administración queda abierta', ! p.url().includes('/login'), p.url());

await p.goto(`${B}/admin/inscripciones`, { waitUntil: 'networkidle2' });

const cabeceras = await p.$$eval('table thead th', (n) => n.map((x) => x.innerText.replace(/\s+/g, ' ').trim()));
di('La tabla ya no tiene columna «Estado»', ! cabeceras.some((c) => /^Estado/i.test(c)), cabeceras.join(' | '));
di('Pero sigue habiendo tabla', cabeceras.length > 0, `${cabeceras.length} columnas`);

const panel = await texto();
di('No queda ningún «Confirmado» suelto en la tabla', ! /\bConfirmad[oa]\b/.test(panel));

const bajas = await p.$$eval('.plist-baja', (n) => n.length).catch(() => 0);
const marcas = await p.$$eval('.plist-baja-marca', (n) => n.map((x) => x.innerText.trim())).catch(() => []);
di('Las bajas se marcan en la propia fila', bajas === marcas.length,
  `${bajas} filas apagadas · ${marcas.length} marcas`);

/*
 * El filtro no se quita: se queda uno que sí separa. Lo que no puede seguir
 * ofreciendo son los estados del esquema —«pendiente» devolvía todo y
 * «confirmado» nada, porque el doble opt-in no se construyó—. Lo único que
 * distingue de verdad a una inscripción de otra es si se dio de baja.
 */
const opciones = (sel) => p.$$eval(sel, (n) => n.map((o) => o.innerText.trim()));
const sinEstados = (lista) => ! lista.some((o) => /pendiente|^confirmad/i.test(o));

const filtro = await opciones('[name="estado"] option');
di('El filtro ya no ofrece los estados del esquema', sinEstados(filtro), filtro.join(' | '));
di('Y sí separa las bajas, que es lo que se mira',
  filtro.some((o) => /canceladas/i.test(o)), filtro.join(' | '));

await p.goto(`${B}/admin/inscripciones/exportar`, { waitUntil: 'networkidle2' });
const filtroExp = await opciones('[name="estado"] option');
di('Lo mismo en la pantalla de exportar', sinEstados(filtroExp), filtroExp.join(' | '));

await p.goto(`${B}/admin`, { waitUntil: 'networkidle2' });
const dash = await p.$$eval('table thead th', (n) => n.map((x) => x.innerText.trim()));
di('Ni en «últimas inscripciones» del dashboard', ! dash.some((c) => /^Estado$/i.test(c)), dash.join(' | '));

/* ═════════════ P12 — la marquesina, que es lo que pregunta C5 ═══════ */

t('C5 — la marquesina de organizaciones (P12)');

await salir();
await p.goto(`${B}/`, { waitUntil: 'networkidle2' });

const chips = await p.$$eval('.marquee-track .logo-chip', (n) => n.map((c) => ({
  nombre: c.querySelector('span:last-child')?.textContent.trim(),
  copia: c.getAttribute('aria-hidden') === 'true',
})));

const pasada = chips.filter((c) => ! c.copia).map((c) => c.nombre);
const repetidos = pasada.filter((n, i) => pasada.indexOf(n) !== i);

di('La marquesina se pinta', pasada.length > 0, `${pasada.length} organizaciones`);
di('**Ningún nombre repetido dentro de una pasada**', repetidos.length === 0,
  repetidos.join(' · ') || 'ninguno');
di('La segunda pasada existe y está oculta a los lectores de pantalla',
  chips.filter((c) => c.copia).length === pasada.length, `${chips.length} pastillas en total`);
di('Y no son las once pastillas escritas a mano de antes', pasada.length !== 11,
  pasada.join(' · '));

t('Errores de JavaScript');
di('Ninguno', errores.length === 0, errores.join(' | '));

console.log('');
console.log(`RESULTADO: ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
