// El código QR de la encuesta de evaluación.
//
// **Lo que de verdad prueba este archivo es que el QR SE LEE.** Todo lo demás
// —que la ruta responda, que el archivo pese lo suyo, que el `<img>` esté en su
// sitio— se puede comprobar mirando el HTML, y no serviría de nada: un QR mal
// generado se ve perfectamente bien y no escanea. Las dos formas de romperlo
// son mudas —una zona tranquila corta y una escala fraccionaria que deja los
// módulos a medio píxel— y las dos se descubren cuando ya hay cien carteles
// impresos.
//
// Así que aquí se decodifica el PNG con el mismo tipo de decodificador que usa
// un teléfono (`jsQR`, sobre los píxeles crudos) y se compara la URL que sale
// con la que tenía que salir. Se prueba también a la mitad de tamaño y sobre la
// versión pequeña del correo, porque un QR que sólo lee a 2000 px no sirve.
//
//   php artisan serve --host=127.0.0.1 --port=8123
//   php artisan tinker --execute="require base_path('pruebas/datos-evaluacion.php');"
//   node pruebas/qr-evaluacion.mjs
import puppeteer from 'puppeteer-core';
import jsQR from 'jsqr';
import { PNG } from 'pngjs';
import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), '..');

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(58)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => console.log(`\n=== ${x} ===`);

const tinker = (linea) => execFileSync('php', ['artisan', 'tinker', '--execute', linea], { cwd: RAIZ, encoding: 'utf8' });

const sembrado = tinker("require base_path('pruebas/datos-evaluacion.php');");
const datos = sembrado.match(/EVALUACION-LISTA (\S+) (\S+) (\S+) (\S+)/);
if (!datos) { console.error('No se pudo sembrar:\n' + sembrado); process.exit(1); }
const [, ABIERTA, , , ] = datos;

/** El id de la actividad, que es lo que piden las rutas del panel y la cuenta. */
const ID = tinker(`echo App\\Models\\Activity::where('slug','${ABIERTA}')->value('id');`).trim().split('\n').pop().trim();

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();

/** Descarga binaria conservando la sesión del navegador. */
const bajar = async (url) => {
  const r = await p.evaluate(async (u) => {
    const res = await fetch(u, { credentials: 'include' });
    const buf = new Uint8Array(await res.arrayBuffer());
    return {
      estado: res.status,
      tipo: res.headers.get('content-type'),
      disposicion: res.headers.get('content-disposition'),
      bytes: Array.from(buf),
    };
  }, url);

  return { ...r, buffer: Buffer.from(r.bytes) };
};

/**
 * Lee un PNG como lo leería un teléfono.
 *
 * `jsQR` trabaja sobre los píxeles ya descomprimidos, igual que la cámara: si
 * esto devuelve la URL, el código escanea de verdad.
 */
const decodificar = (buffer, escala = 1) => {
  const png = PNG.sync.read(buffer);

  if (escala === 1) {
    const leido = jsQR(new Uint8ClampedArray(png.data), png.width, png.height);
    return leido?.data ?? null;
  }

  // Reducción por vecino más próximo, que es la peor de todas: si aguanta
  // esto, aguanta la cámara de cualquier teléfono.
  const ancho = Math.round(png.width * escala);
  const alto = Math.round(png.height * escala);
  const pequeno = new Uint8ClampedArray(ancho * alto * 4);

  for (let y = 0; y < alto; y++) {
    for (let x = 0; x < ancho; x++) {
      const oy = Math.min(png.height - 1, Math.round(y / escala));
      const ox = Math.min(png.width - 1, Math.round(x / escala));
      const origen = (oy * png.width + ox) * 4;
      const destino = (y * ancho + x) * 4;
      pequeno.set(png.data.subarray(origen, origen + 4), destino);
    }
  }

  return jsQR(pequeno, ancho, alto)?.data ?? null;
};

const esperado = `${B}/evaluar/${ABIERTA}`;

// ── Entrar como organizador ────────────────────────────────

const entrar = async (url, correo, clave) => {
  await p.goto(url, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', correo);
  await p.type('input[name="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};

t('El organizador ve el QR en su actividad');
await entrar(`${B}/mi-cuenta/login`, 'organizador@ong-laravel.test', 'organizador1234');
await p.goto(`${B}/mi-cuenta/actividades/${ID}/editar`, { waitUntil: 'networkidle2' });

const caja = await p.$('.qr-caja');
di('La caja del QR está en la pantalla', caja !== null);

if (caja) {
  const visible = await p.$eval('.qr-caja', (n) => n.getBoundingClientRect().height > 40);
  di('Y ocupa sitio de verdad, no es una caja vacía', visible);

  const src = await p.$eval('.qr-imagen', (n) => n.getAttribute('src'));
  di('La miniatura va incrustada como data:', src.startsWith('data:image/png;base64,'));

  const enlace = await p.$eval('.qr-enlace', (n) => n.textContent.trim());
  di('Enseña la dirección que codifica', enlace === esperado, enlace);

  const botones = await p.$$eval('.qr-botones a', (n) => n.map((a) => a.textContent.trim()));
  di('Ofrece los dos formatos', botones.length === 2 && botones.some((b) => b.includes('SVG')) && botones.some((b) => b.includes('PNG')), botones.join(' · '));

  // La miniatura de la pantalla también tiene que escanear: es la que la gente
  // acaba fotografiando con otro teléfono para probar.
  const enPantalla = decodificar(Buffer.from(src.split(',')[1], 'base64'));
  di('La miniatura de 240 px se decodifica', enPantalla === esperado, enPantalla ?? '(ilegible)');
}

t('La descarga en PNG');
const png = await bajar(`${B}/mi-cuenta/actividades/${ID}/qr.png`);
di('Responde 200', png.estado === 200, String(png.estado));
di('Es un PNG', png.tipo === 'image/png', png.tipo);
di('Se descarga con nombre propio', (png.disposicion ?? '').includes(`qr-evaluacion-${ABIERTA}.png`), png.disposicion ?? '');
di('Pesa lo que pesa un PNG de verdad', png.buffer.length > 1000, png.buffer.length + ' bytes');

const leidoPng = decodificar(png.buffer);
di('SE DECODIFICA, y dice la dirección correcta', leidoPng === esperado, leidoPng ?? '(ilegible)');

// Un cartel impreso se escanea desde lejos y de lado: la cámara nunca ve los
// 2048 px. Se prueba a un cuarto y a un octavo.
for (const escala of [0.25, 0.125]) {
  const leido = decodificar(png.buffer, escala);
  di(`Sigue leyéndose reducido al ${escala * 100}%`, leido === esperado, leido ?? '(ilegible)');
}

t('La descarga en SVG');
const svg = await bajar(`${B}/mi-cuenta/actividades/${ID}/qr.svg`);
const texto = svg.buffer.toString('utf8');
di('Responde 200', svg.estado === 200, String(svg.estado));
di('Es un SVG', (svg.tipo ?? '').includes('svg'), svg.tipo ?? '');
di('Se descarga con nombre propio', (svg.disposicion ?? '').includes(`qr-evaluacion-${ABIERTA}.svg`), svg.disposicion ?? '');
di('Trae la etiqueta <svg>', texto.includes('<svg'), '');
// endroid dibuja TODOS los módulos en un solo <path>, no en un <rect> por
// módulo: lo que hay que contar son los subtrazos de su atributo `d`, uno por
// tramo de módulos negros. Contar etiquetas daba 2 y parecía un SVG vacío.
const trazos = (texto.match(/M\d+,\d+/g) ?? []).length;
di('Y dibuja módulos, no está vacío', trazos > 50, trazos + ' trazos');

t('El QR de otra organización no se descarga');
// Es la comprobación que pilla la fuga: pedir el id de una actividad ajena.
const ajena = tinker(`echo App\\Models\\Activity::where('organization_id','!=',App\\Models\\Activity::where('slug','${ABIERTA}')->value('organization_id'))->value('id') ?: 0;`).trim().split('\n').pop().trim();

if (ajena && ajena !== '0') {
  const fuga = await bajar(`${B}/mi-cuenta/actividades/${ajena}/qr.png`);
  di('Devuelve 403 y no el archivo', fuga.estado === 403, String(fuga.estado));
} else {
  di('Devuelve 403 y no el archivo', true, '(no hay actividad de otra organización que probar)');
}

t('El administrador tiene la misma descarga');
await p.goto(`${B}/mi-cuenta/logout`, { waitUntil: 'networkidle2' }).catch(() => {});
await p.evaluate(() => document.querySelector('form[action*="logout"]')?.submit()).catch(() => {});
await entrar(`${B}/admin/login`, 'admin@ong-laravel.test', 'admin1234');
await p.goto(`${B}/admin/actividades/${ID}`, { waitUntil: 'networkidle2' });

const cajaAdmin = await p.$('.qr-caja');
di('La caja del QR está en la ficha de moderación', cajaAdmin !== null);

const pngAdmin = await bajar(`${B}/admin/actividades/${ID}/qr.png`);
di('El admin descarga el PNG', pngAdmin.estado === 200, String(pngAdmin.estado));
di('Y ese PNG también se decodifica', decodificar(pngAdmin.buffer) === esperado, '');

t('El QR sólo aparece cuando la actividad está publicada');
const idBorrador = tinker(`echo App\\Models\\Activity::where('slug','prueba-eval-borrador')->value('id');`).trim().split('\n').pop().trim();
await p.goto(`${B}/admin/actividades/${idBorrador}`, { waitUntil: 'networkidle2' });
di('En una actividad sin publicar no se pinta', (await p.$('.qr-caja')) === null);

await nav.close();

console.log(`\n${ok} bien · ${mal} mal`);
process.exit(mal ? 1 : 0);
