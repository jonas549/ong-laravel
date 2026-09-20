// P13, P15 y P17 — el selector de hora, dónde se pregunta por los voluntarios,
// y la columna «Estado» de los inscritos.
//
//   node pruebas/hora-y-campos.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

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

/* ═════════════════ P13 — el selector de hora ═════════════════════ */

t('P13 — sólo horas en punto, y sin proponer la hora actual');

await alPaso(4);

di('**Ya no hay ningún selector nativo de hora**',
  await p.evaluate(() => document.querySelectorAll('input[type="time"]').length) === 0);

await p.evaluate(() => document.querySelectorAll('.campo-selector-boton')[1]?.click());
await esperar(300);

const lista = await p.evaluate(() => {
  const caja = document.querySelector('.hora-lista');
  const opciones = [...document.querySelectorAll('.hora-lista')][0];

  return {
    visible: !! caja && caja.getBoundingClientRect().height > 0,
    opciones: opciones ? [...opciones.querySelectorAll('.hora-opcion')].map((o) => o.innerText.replace(/\s+/g, ' ').trim()) : [],
  };
});

di('Se abre el desplegable', lista.visible);
di('Con las 24 horas del día', lista.opciones.length === 24, `${lista.opciones.length} opciones`);
di('**Todas en punto, ninguna con minutos sueltos**',
  lista.opciones.every((o) => /^\d{2}:00\b/.test(o)),
  lista.opciones.slice(0, 3).join(' · '));
di('Y cada una con su AM/PM', lista.opciones.every((o) => /\d+ (AM|PM)$/.test(o)),
  `${lista.opciones[0]} … ${lista.opciones[23]}`);
di('La lista empieza en 00:00 y acaba en 23:00',
  lista.opciones[0].startsWith('00:00') && lista.opciones[23].startsWith('23:00'));

// «Nada de proponer la hora actual»: el campo tiene que seguir vacío.
di('**No propone ninguna hora: el campo sigue vacío**',
  await p.$eval('input[name="hora_inicio"]', (n) => n.value) === '');

await p.evaluate(() => [...document.querySelectorAll('.hora-opcion')].find((o) => o.innerText.includes('17:00'))?.click());
await esperar(250);
di('Elegir una la escribe en el campo',
  await p.$eval('input[name="hora_inicio"]', (n) => n.value) === '17:00',
  await p.$eval('input[name="hora_inicio"]', (n) => n.value));
di('Y cierra la lista',
  await p.evaluate(() => (document.querySelector('.hora-lista')?.getBoundingClientRect().height ?? 0) === 0));

// El campo sigue admitiendo lo que se escriba: es un atajo, no una jaula.
await p.evaluate(() => {
  const c = document.querySelector('input[name="hora_inicio"]');
  c.value = '';
  c.focus();
});
await p.type('input[name="hora_inicio"]', '930');
await p.evaluate(() => document.querySelector('input[name="hora_inicio"]').blur());
await esperar(200);
di('Escribir una hora con minutos sigue valiendo',
  await p.$eval('input[name="hora_inicio"]', (n) => n.value) === '09:30',
  await p.$eval('input[name="hora_inicio"]', (n) => n.value));

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
await p.type('input[name="email"]', 'organizador@ong-laravel.test');
await p.type('input[name="password"]', 'organizador1234');
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
