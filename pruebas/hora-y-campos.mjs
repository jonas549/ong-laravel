// B4 (antes P13), P15 y P17 — el selector de hora, dónde se pregunta por los
// voluntarios, y la columna «Estado» de los inscritos.
//
//   node pruebas/hora-y-campos.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { CLAVE_ORG, ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

const alPaso = async (n) => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate((paso) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = paso; }, n);
  await esperar(300);
};

/* ═════════════════ B4 — el selector de hora ═════════════════════ */

/*
 * B4 de la sexta tanda (sustituye a P13): un <select> nativo con las 24 horas
 * en punto en AM/PM. El desplegable propio de antes se recortaba dentro de la
 * tarjeta y el cliente sólo veía de 12 AM a 6 AM.
 *
 * «Que al abrirse quede en las 9:00 AM» sin elegirla: un <select> abre por la
 * opción elegida, así que la vacía va justo antes de las 9:00 AM. Lo que se
 * comprueba es eso —que la vacía es la elegida y está ahí—, porque la lista
 * desplegada la pinta el sistema y no se puede capturar.
 */
const revisarHoras = async (quien) => {
  for (const campo of ['hora_inicio', 'hora_termino']) {
    const s = await p.$eval(`select[name="${campo}"]`, (el) => ({
      opciones: [...el.options].map((o) => ({ v: o.value, t: o.textContent.trim() })),
      valor: el.value,
      alto: el.getBoundingClientRect().height,
    }));
    const horas = s.opciones.filter((o) => o.v !== '');
    const vacia = s.opciones.findIndex((o) => o.v === '');

    di(`${quien} · ${campo}: un select con las 24 horas`, horas.length === 24, `${horas[0]?.t} … ${horas[23]?.t}`);
    di(`${quien} · ${campo}: en punto y en AM/PM`,
      horas.every((o) => /^\d{2}:00$/.test(o.v) && /^\d{1,2}:00 (AM|PM)$/.test(o.t)),
      horas.slice(8, 10).map((o) => o.t).join(' · '));
    di(`${quien} · ${campo}: 12 AM, 12 PM y 11 PM bien escritas`,
      horas[0].t === '12:00 AM' && horas[12].t === '12:00 PM' && horas[23].t === '11:00 PM');
    di(`${quien} · ${campo}: **vacía y justo encima de las 9:00 AM**`,
      s.valor === '' && s.opciones[vacia + 1]?.t === '9:00 AM', `${s.opciones[vacia - 1]?.t} · [${s.opciones[vacia]?.t}] · ${s.opciones[vacia + 1]?.t}`);
    di(`${quien} · ${campo}: se puede bajar hasta la última`, s.opciones.at(-1).t === '11:00 PM');
    di(`${quien} · ${campo}: se ve (no tiene alto cero)`, s.alto > 30, `${Math.round(s.alto)} px`);
  }

  await p.select('select[name="hora_inicio"]', '17:00');
  di(`${quien} · elegir una deja «HH:MM»`, await p.$eval('select[name="hora_inicio"]', (n) => n.value) === '17:00');
  await p.select('select[name="hora_inicio"]', '');
  di(`${quien} · y la vacía la quita`, await p.$eval('select[name="hora_inicio"]', (n) => n.value) === '');
};

t('B4 — wizard, escritorio');
await alPaso(4);
di('**Ya no hay ningún selector nativo de hora**',
  await p.evaluate(() => document.querySelectorAll('input[type="time"]').length) === 0);
await revisarHoras('escritorio');

t('B4 — wizard, teléfono');
await p.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
await alPaso(4);
await revisarHoras('teléfono');
await p.setViewport({ width: 1440, height: 1000 });

/* ═════════════════ P15 — dónde se pregunta ═══════════════════════ */

t('P15 — la pregunta de los voluntarios, en el paso de la actividad');

await alPaso(3);
di('**Ya no está en el paso de la organización**',
  await p.evaluate(() => {
    const campo = document.querySelector('input[name="org_num_voluntarios"]');
    if (! campo) return true;
    // Puede estar en el DOM del paso 4, que no se ve estando en el 3.
    return campo.closest('[data-paso]')?.getAttribute('data-paso') !== '3';
  }));

await alPaso(4);
// Sólo sale en el flujo de empresa.
await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).tipo = 'Empresa o institución privada'; });
await esperar(300);

const campoVol = await p.$('input[name="org_num_voluntarios"]');
di('Está en el paso de la actividad', campoVol !== null);
di('Y se ve, con el flujo de empresa', campoVol !== null && await p.$eval(
  'input[name="org_num_voluntarios"]', (n) => n.getBoundingClientRect().height > 0));
di('Junto a los participantes estimados', await p.evaluate(() => {
  const vol = document.querySelector('input[name="org_num_voluntarios"]');
  const est = document.querySelector('input[name="participantes_estimados"]');
  if (! vol || ! est) return false;
  // En el mismo bloque «Público de la actividad».
  return vol.closest('div[style*="padding:30px"]') === est.closest('div[style*="padding:30px"]');
}));

await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).tipo = 'Organización sin fines de lucro'; });
await esperar(250);
di('Y sigue sin salir si no es empresa', await p.$eval(
  'input[name="org_num_voluntarios"]', (n) => n.getBoundingClientRect().height === 0));

/* ═════════════════ P17 — la columna «Estado» ═════════════════════ */

t('P17 — fuera la columna «Estado» de los inscritos del organizador');

const actividad = ultima(tinker(
  "$a = App\\Models\\Activity::where('estado','publicada')"
  + "->whereHas('registrations')->first(); echo $a ? $a->id : 'NO';"
));

await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', ORG);
await p.type('input[name="password"]', CLAVE_ORG);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

if (actividad === 'NO') {
  di('Hay una actividad con inscritos para probar', false, 'ninguna');
} else {
  await p.goto(`${B}/mi-cuenta/actividades/${actividad}/participantes`, { waitUntil: 'networkidle2' });

  const cabeceras = await p.$$eval('table.plist thead th', (n) => n.map((c) => c.textContent.trim()));
  di('La tabla carga', cabeceras.length > 0, cabeceras.join(' · '));
  di('**Ya no hay columna «Estado»**', ! cabeceras.some((c) => /estado/i.test(c)));
  di('Y quedan las cuatro que importan', cabeceras.length === 4, `${cabeceras.length} columnas`);

  di('Ninguna celda pinta ya un guion suelto',
    ! (await p.$$eval('table.plist tbody td', (n) => n.map((c) => c.textContent.trim()))).includes('—'));

  /*
   * Y una baja se sigue distinguiendo, por otro camino. Se fabrica una para
   * la prueba y se deshace al salir: dejarlo al azar de los datos sembrados
   * haría que este ramal —que es la mitad del punto— se quedara sin
   * comprobar justo el día que no hubiera ninguna.
   */
  const laBaja = ultima(tinker(
    `$r = App\\Models\\Registration::where('activity_id',${actividad})->where('estado','!=','cancelado')->first();`
    + ` if ($r) { $r->update(['estado' => 'cancelado']); }`
    + ` echo $r ? $r->id : 'NO';`
  ));

  if (laBaja !== 'NO') {
    await p.reload({ waitUntil: 'networkidle2' });

    di('Quien se dio de baja lleva su etiqueta junto al nombre',
      (await p.$$eval('.plist-baja-marca', (n) => n.length)) > 0);
    di('Y su fila va atenuada',
      await p.$eval('tr.plist-baja', (n) => Number(getComputedStyle(n).opacity) < 1));
    di('La etiqueta no sale tachada junto con el nombre',
      await p.$eval('.plist-baja-marca', (n) => getComputedStyle(n).textDecorationLine === 'none'),
      await p.$eval('.plist-baja-marca', (n) => getComputedStyle(n).textDecorationLine));

    tinker(`App\\Models\\Registration::where('id',${laBaja})->update(['estado' => 'confirmado']); echo 'restaurada';`);
  } else {
    console.log('     (no hay inscripciones que dar de baja: ese ramal no se comprueba)');
  }

  const filtro = await p.$$eval('select[name="estado"] option', (n) => n.map((o) => o.textContent.trim()));
  di('El filtro no ofrece estados que no existen',
    ! filtro.some((o) => /pendiente|confirmad/i.test(o)), filtro.join(' · '));
}

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
