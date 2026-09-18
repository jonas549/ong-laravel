// Puntos 4 y 19 de la tanda del 11/09. En Chrome.
//
//   19 — el wizard ya no deja crear una organizacion con un nombre que ya
//        existe, y lo dice con un mensaje que explica que hacer.
//   4  — cuando el formulario rebota, el archivo que la persona ya habia
//        subido NO se pierde. Es la causa real del ticket «el logo no aparece
//        en la ficha»: no es que no se pintara, es que nunca llegaba a
//        guardarse, y de forma completamente muda.
//
// Los dos se prueban juntos porque el segundo necesita al primero: hace falta
// un rechazo DEL SERVIDOR para que el formulario rebote, y la guia de errores
// del bloque K corta antes cualquier fallo que el navegador pueda ver.
//
// SEGURO CONTRA PRODUCCION: el camino que se ejercita por defecto es el del
// RECHAZO, que no crea ni una fila. La segunda parte —reenviar y comprobar que
// la organizacion nace con su logo— solo corre con DPS_CREA_DATOS=1.
//
//   node pruebas/archivo-retenido.mjs
//   DPS_URL=https://ong.sandboxdelta.com DPS_ORG_EXISTENTE=deltadigital.cl node pruebas/archivo-retenido.mjs
//   DPS_CREA_DATOS=1 node pruebas/archivo-retenido.mjs      (solo en local)
import puppeteer from 'puppeteer-core';
import { PNG } from 'pngjs';
import { writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const ORG_EXISTENTE = process.env.DPS_ORG_EXISTENTE ?? 'Fundación Junto al Barrio';
const CREA_DATOS = process.env.DPS_CREA_DATOS === '1';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

// Un logo pequeno y valido: 120x120, unos pocos kB, muy por debajo de los 500.
const LOGO = join(tmpdir(), 'dps-logo-retenido.png');
{
  const png = new PNG({ width: 120, height: 120 });
  for (let y = 0; y < png.height; y++) {
    for (let x = 0; x < png.width; x++) {
      const i = (png.width * y + x) << 2;
      png.data[i] = 229; png.data[i + 1] = 114; png.data[i + 2] = 0; png.data[i + 3] = 255;
    }
  }
  writeFileSync(LOGO, PNG.sync.write(png));
}

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
await p.setViewport({ width: 1440, height: 1000 });

const paso = async (n) => {
  await p.evaluate((n) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = n; }, n);
  await esperar(250);
};
const valores = (sel) => p.$$eval(`${sel} option`, (os) => os.map((o) => o.value).filter(Boolean));
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

const rellenar = async (nombreOrg, correo) => {
  await paso(3);
  await p.type('input[name="org_nombre"]', nombreOrg);
  await p.type('input[name="email"]', correo);
  await p.type('input[name="password"]', 'clave-larga-1234');
  await p.type('input[name="password_confirmation"]', 'clave-larga-1234');

  await paso(4);
  await p.type('input[name="titulo"]', 'Actividad de prueba de retencion');
  await p.type('textarea[name="descripcion"]', 'Descripcion cualquiera para la prueba.');
  await p.type('input[name="fecha_inicio"]', '04122026');
  await p.select('select[name="region_id"]', (await valores('select[name="region_id"]'))[0]);
  await p.waitForFunction(() => [...document.querySelectorAll('select[name="commune_id"] option')].some((o) => o.value));
  await p.select('select[name="commune_id"]', (await valores('select[name="commune_id"]'))[0]);
  await p.type('input[name="direccion"]', 'Calle Falsa 123');
  await p.evaluate(() => {
    document.querySelector('[data-campo="temas"] button.chip')?.click();
    document.querySelector('[data-campo="caracteristicas"] button.chip')?.click();
    document.querySelector('[data-campo="publicos"] button.chip')?.click();
  });
};

const enviar = async () => {
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    p.evaluate(() => document.querySelector('form[action*="publicar-actividad"], form#wizard, form')?.requestSubmit()),
  ]);
  await esperar(400);
};

/* ══════════════ 19 — el nombre repetido se rechaza ══════════════ */

t('Punto 19 — no se puede registrar una organización con un nombre que ya existe');

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);

const correoNuevo = `retencion${Date.now()}@ong-laravel.test`;
await rellenar(ORG_EXISTENTE, correoNuevo);

// El logo, que es lo que tiene que sobrevivir al rebote.
await paso(3);
await (await p.$('input[name="org_logo"]')).uploadFile(LOGO);
await esperar(300);

await enviar();

const tras = await texto();
di('El servidor rechaza el nombre repetido',
  /Ya hay una organización registrada con ese nombre/i.test(tras),
  (tras.match(/Ya hay una organización[^.]*\./) ?? ['(no sale el mensaje)'])[0].slice(0, 90));
di('Y el mensaje dice qué hacer, no sólo que está mal',
  /inicia sesión/i.test(tras) && /nombre que la diferencie/i.test(tras));

/* ══════════════ 4 — el archivo sobrevive al rebote ══════════════ */

t('Punto 4 — el archivo subido sobrevive al rebote del formulario');

await p.waitForFunction(() => window.Alpine !== undefined);
await paso(3);

const aviso = await p.$eval('.archivo-retenido', (n) => ({
  texto: n.innerText.replace(/\s+/g, ' ').trim(),
  alto: n.getBoundingClientRect().height,
})).catch(() => null);

di('Se avisa de que el archivo sigue guardado', aviso !== null, aviso?.texto?.slice(0, 80) ?? '(no hay aviso)');
di('Y dice el NOMBRE del archivo, para poder reconocerlo',
  (aviso?.texto ?? '').includes('dps-logo-retenido.png'), aviso?.texto?.slice(0, 90));
di('Y se ve de verdad, no está a cero de alto', (aviso?.alto ?? 0) > 10, `${aviso?.alto} px`);

// El selector sigue vacío: es lo que NINGÚN navegador deja rellenar, y la razón
// de que hiciera falta todo esto.
di('El selector de archivo sigue vacío, como manda el navegador',
  await p.$eval('input[name="org_logo"]', (n) => n.files.length === 0));

if (! CREA_DATOS) {
  t('La segunda parte no corre');
  console.log('  Reenviar crearía una organización y una actividad de verdad.');
  console.log('  Para ejercitarla en local: DPS_CREA_DATOS=1 node pruebas/archivo-retenido.mjs');
} else {
  t('Punto 4 — y al reenviar SIN volver a elegirlo, la organización nace con su logo');

  await p.$eval('input[name="org_nombre"]', (n) => { n.value = ''; });
  const nombreNuevo = `Fundación Retención ${Date.now()}`;
  await p.type('input[name="org_nombre"]', nombreNuevo);

  // Las contrasenas NO se repueblan tras un rebote —ningun navegador las
  // devuelve, y `old()` tampoco debe—, asi que hay que reescribirlas, igual
  // que haria una persona. Es justo el contraste con el archivo: ese SI
  // sobrevive ahora.
  await p.type('input[name="password"]', 'clave-larga-1234');
  await p.type('input[name="password_confirmation"]', 'clave-larga-1234');

  await enviar();

  const errores2 = await p.$$eval('.field-error, .resumen-errores, .alert-error',
    (ns) => ns.map((n) => n.innerText.replace(/\s+/g, ' ').trim()).filter(Boolean).slice(0, 6));
  if (errores2.length) console.log('  errores del servidor:', JSON.stringify(errores2));

  const final = await texto();
  di('El envío sale bien esta vez', /Recibimos tu actividad|tu actividad fue enviada/i.test(final),
    final.slice(0, 80));

  // Lo que de verdad importa: que la organización tenga logo, sin haber vuelto
  // a elegir el archivo.
  // Y el aviso desaparece: lo retenido se limpia al publicar bien, que es lo
  // que evita que los archivos se queden colgando de la sesion para siempre.
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await paso(3);
  di('Publicado bien, ya no queda nada retenido',
    (await p.$('.archivo-retenido')) === null);

  console.log('');
  console.log(`  Nombre creado: ${nombreNuevo}`);
  console.log('  Comprueba el logo con:');
  console.log('    php artisan tinker --execute="\\$o=App\\\\Models\\\\Organization::orderByDesc(\'id\')->first(); echo \\$o->nombre.\' -> \'.var_export(\\$o->logo_path,true);"');
}

t('Sin errores de JavaScript');
di('La consola quedó limpia', errores.length === 0, errores.slice(0, 2).join(' · '));

console.log('');
console.log(`${ok} bien · ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
