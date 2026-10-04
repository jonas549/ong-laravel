// La pantalla de prueba de la API de Voluntariados Chile. En Chrome.
//
// Sin API Key todavía, lo que se puede probar de verdad es:
//   · que la pantalla llama al endpoint real y enseña su 401 tal cual, con su
//     code y su message, el código HTTP y lo que tardó;
//   · que con una clave falsa y parámetros fuera de rango sigue saliendo lo
//     que diga la API, sin que la pantalla lo frene antes;
//   · que el revisor del contrato da por bueno el ejemplo del PDF y detecta
//     un campo que falta, uno vacío, uno no documentado y un tipo cambiado;
//   · que la API Key no aparece en ningún sitio: ni en la respuesta, ni en la
//     URL que se enseña, ni en el log de Laravel.
//
// Llama al endpoint real de Voluntariados Chile: sólo lectura, sin clave.
//
//   node pruebas/probador-vch.mjs
import puppeteer from 'puppeteer-core';
import { readFileSync, existsSync } from 'node:fs';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const CLAVE_FALSA = `clave-falsa-de-prueba-${Date.now()}`;
const LOG = 'storage/logs/laravel.log';
const tamLogAntes = existsSync(LOG) ? readFileSync(LOG).length : 0;

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1280, height: 900 });

const respuestas = [];
p.on('response', async (r) => {
  if (r.url().includes('/probador/')) respuestas.push(await r.text().catch(() => ''));
});

await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
await p.type('input[type="email"]', ADMIN);
await p.type('input[type="password"]', CLAVE_ADMIN);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

const r = await p.goto(`${B}/admin/voluntariados-chile/probador`, { waitUntil: 'networkidle2' });
t('La pantalla');
di('Existe para un administrador', r.status() === 200, `${r.status()}`);
di('Está en el menú del panel', await p.evaluate(() => [...document.querySelectorAll('a')].some((a) => /Probar API Voluntariados/.test(a.textContent))));
di('El campo de la clave es de contraseña y no tiene name',
  await p.$eval('[data-probador-clave]', (n) => n.type === 'password' && ! n.name && n.autocomplete === 'off'));

const consultar = async () => {
  await p.click('[data-probador-consultar]');
  await p.waitForSelector('[data-probador-http]', { timeout: 40000 });
  await p.waitForFunction(() => ! document.querySelector('[data-probador-consultar]').disabled, { timeout: 40000 });
  await esperar(200);
};
const texto = (sel) => p.$eval(sel, (n) => n.textContent.trim()).catch(() => '');

t('Sin API Key: el 401 de la API, tal cual');
await consultar();
di('Código HTTP 401', (await texto('[data-probador-http]')) === '401', await texto('[data-probador-http]'));
di('Enseña cuánto tardó', /^\d+ ms$/.test(await texto('[data-probador-ms]')), await texto('[data-probador-ms]'));
di('El code de la API: unauthorized', (await texto('[data-probador-error-code]')) === 'unauthorized', await texto('[data-probador-error-code]'));
di('Y su message, sin traducir ni esconder', /API key/i.test(await texto('[data-probador-error-message]')), await texto('[data-probador-error-message]'));
di('La respuesta cruda se ve formateada', /"error": \{\n/.test(await texto('[data-probador-crudo]')));
di('El revisor reconoce el error documentado', /formato documentado: HTTP 401/.test(await texto('[data-probador-informe]')));

// Para la reunión (04/10): el resultado se lee de un vistazo, proyectado.
di('Veredicto en una frase: «falta la credencial»',
  (await texto('[data-probador-titular]')) === 'La API rechaza la petición: falta la credencial', await texto('[data-probador-titular]'));
di('El 401 grande, arriba de todo',
  await p.$eval('[data-probador-veredicto-codigo]', (n) => n.textContent.trim() === '401' && parseFloat(getComputedStyle(n).fontSize) >= 72));
di('El mensaje literal de Voluntariados Chile', /«Missing or invalid API key\.»/.test(await texto('[data-probador-literal]')), await texto('[data-probador-literal]'));
di('La hora de la consulta, en hora de Chile', /^\d{1,2} de [a-z]+ de \d{4}, a las \d{2}:\d{2}:\d{2}$/.test(await texto('[data-probador-hora]')), await texto('[data-probador-hora]'));
di('El veredicto va antes que el detalle técnico', await p.evaluate(() =>
  document.querySelector('[data-probador-veredicto]').compareDocumentPosition(document.querySelector('[data-probador-crudo]')) & Node.DOCUMENT_POSITION_FOLLOWING));

t('Con una clave falsa y parámetros fuera de rango');
await p.type('[data-probador-clave]', CLAVE_FALSA);
await p.type('[data-probador-desde]', 'esto-no-es-una-fecha');
await p.type('[data-probador-tamano]', '500');
await consultar();
const http2 = await texto('[data-probador-http]');
di('La pantalla no frena nada: contesta la API (401 o 400)', ['401', '400'].includes(http2), http2);
di('La URL que se enseña lleva los parámetros', /updated_since=esto-no-es-una-fecha/.test(await p.evaluate(() => document.body.innerText)));

t('La API Key no aparece en ningún sitio');
di('No vuelve en ninguna respuesta de la pantalla', respuestas.every((x) => ! x.includes(CLAVE_FALSA)), `${respuestas.length} respuestas`);
di('No está en el texto de la página fuera de su campo', ! (await p.evaluate(() => document.body.innerText)).includes(CLAVE_FALSA));
const logNuevo = existsSync(LOG) ? readFileSync(LOG).subarray(tamLogAntes).toString() : '';
di('No está en el log de Laravel', ! logNuevo.includes(CLAVE_FALSA));
await p.reload({ waitUntil: 'networkidle2' });
di('Al recargar, el campo vuelve vacío', (await p.$eval('[data-probador-clave]', (n) => n.value)) === '');

t('El revisor, con el ejemplo del PDF');
await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => /JSON pegado/.test(b.textContent)).click());
await p.click('[data-probador-ejemplo]');
await p.click('[data-probador-revisar]');
await p.waitForSelector('[data-probador-informe]', { timeout: 10000 });
await esperar(300);
const inf = await p.evaluate(() => document.querySelector('[data-probador-informe]').innerText);
di('Dos oportunidades, sin campos que falten', /2\s*oportunidades/.test(await p.evaluate(() => document.body.innerText)) && ! /falta:/.test(inf));
di('Problemas contra la documentación: 0', (await texto('[data-probador-problemas]')) === '0', await texto('[data-probador-problemas]'));
di('Avisa del registro de prueba en producción', /registro de prueba/.test(inf));
di('Avisa de minimum_age: no se exige y trae detalle «25»', /minimum_age: dice que no se exige y trae detalle «25»/.test(inf));
di('Avisa del rango que ya terminó y sigue abierta', /terminó el 2026-09-29/.test(inf));
di('Cuenta los vacíos permitidos (cover_image_url, volunteers_needed…)', /cover_image_url/.test(inf) && /volunteers_needed/.test(inf));
di('Lista los formatos reales', /En persona/.test(inf) && /Híbrido/.test(inf));
di('Y compara las regiones con las nuestras', /Metropolitana — parecida a la nuestra «Metropolitana de Santiago»/.test(inf), (inf.match(/Metropolitana[^\n]*/) ?? [''])[0]);
di('Y las comunas', /Arica — igual a la nuestra/.test(inf));

t('El revisor, con un JSON alterado a propósito');
const alterado = await p.evaluate(() => {
  const j = JSON.parse(document.getElementById('ejemplo-pdf').textContent);
  delete j.data[0].title;                        // falta
  j.data[0].description = '   ';                 // vacío no permitido
  j.data[0].volunteers_needed = '30';            // tipo cambiado
  j.data[0].format = 'Virtual';                  // valor nuevo
  j.data[0].nuevo_campo = 'algo';                // no documentado
  j.data[1].organization.slug = 'coaniquem';     // no documentado, anidado
  j.data[1].schedule.type = 'one_day';           // fuera de los valores
  return JSON.stringify(j);
});
await p.$eval('[data-probador-pegado]', (n, v) => { n.value = v; n.dispatchEvent(new Event('input')); }, alterado);
await p.click('[data-probador-revisar]');
await esperar(800);
const inf2 = await p.evaluate(() => document.querySelector('[data-probador-informe]').innerText);
di('title: falta', /title\s+falta: 1/.test(inf2));
di('description: vacío', /description\s+vacío: 1/.test(inf2));
di('volunteers_needed: tipo distinto', /volunteers_needed\s+tipo distinto: 1/.test(inf2));
di('schedule.type: valor no documentado', /schedule\.type\s+valor no documentado: 1/.test(inf2));
di('nuevo_campo: no documentado', /nuevo_campo/.test(inf2));
di('organization.slug: no documentado, aunque esté anidado', /organization\.slug/.test(inf2));
di('El formato «Virtual» sale como NUEVO', /Virtual — NUEVO/.test(inf2));
di('El total de problemas ya no es 0', Number(await texto('[data-probador-problemas]')) >= 4, await texto('[data-probador-problemas]'));

/*
 * Cuando la API no contesta desde el servidor (04/10, en producción: el botón
 * «parecía no hacer nada»). Hace falta un segundo `artisan serve` con la API
 * apuntando a una IP sin salida:
 *
 *   VCH_API_URL=http://10.255.255.1/nada php artisan serve --port=8124
 *   DPS_URL_SIN_SALIDA=http://127.0.0.1:8124 node pruebas/probador-vch.mjs
 *
 * La sesión vale en los dos puertos: las cookies son del host, no del puerto.
 */
const SIN_SALIDA = process.env.DPS_URL_SIN_SALIDA;
if (SIN_SALIDA) {
  t('La API no contesta desde el servidor');
  await p.goto(`${SIN_SALIDA}/admin/voluntariados-chile/probador`, { waitUntil: 'networkidle2' });
  const t0 = Date.now();
  await p.click('[data-probador-consultar]');
  await esperar(2200);
  const espera = await p.evaluate(() => {
    const n = document.querySelector('[data-probador-cargando]');
    return n && getComputedStyle(n).display !== 'none' ? n.innerText.replace(/\s+/g, ' ') : null;
  });
  di('Mientras espera, se ve un aviso grande con los segundos', /Consultando a Voluntariados Chile/.test(espera ?? '') && /[12] s/.test(espera ?? ''), espera ?? '(nada)');
  await p.waitForSelector('[data-probador-titular]', { timeout: 40000 });
  di('Y termina diciendo qué pasó', /No se pudo contactar a Voluntariados Chile/.test(await texto('[data-probador-titular]')), await texto('[data-probador-titular]'));
  di('Con el motivo técnico', /Detalle:/.test(await p.evaluate(() => document.querySelector('[data-probador-veredicto]').innerText)));
  di('En menos de 25 s, no medio minuto', (Date.now() - t0) / 1000 < 25, `${((Date.now() - t0) / 1000).toFixed(1)} s`);
  di('El aviso de espera desaparece', await p.$eval('[data-probador-cargando]', (n) => getComputedStyle(n).display === 'none'));
} else {
  console.log('\n  (sin DPS_URL_SIN_SALIDA: no se prueba la API que no contesta)');
}

t('Sin errores de JavaScript');
di('La consola quedó limpia', errores.length === 0, errores.slice(0, 2).join(' · '));

console.log('');
console.log(`${ok} bien · ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
