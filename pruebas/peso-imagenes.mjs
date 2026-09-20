// P19 — el peso de las imágenes se resuelve AL ELEGIR, no al enviar.
//
// La decisión entre las dos que pedían el ticket y el Word: se reduce
// automáticamente en el navegador, y el aviso con bloqueo queda para cuando
// eso no baste. Aquí se comprueban las dos caras.
//
// Antes de correrlo hay que fabricar las imágenes:
//   node pruebas/peso-imagenes.mjs --preparar
//
//   node pruebas/peso-imagenes.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { writeFileSync, existsSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

const PORTADA = join(tmpdir(), 'dps-portada-grande.jpg');
const LOGO = join(tmpdir(), 'dps-logo-grande.png');
const NO_IMAGEN = join(tmpdir(), 'dps-no-imagen.txt');

/*
 * Las imágenes se fabrican con GD desde PHP y no se versionan: son megas de
 * ruido que no aportan nada al repositorio, y el ruido es justo lo que hace
 * falta —una imagen plana se comprime a nada y no probaría el límite—.
 */
const preparar = () => {
  execFileSync(PHP, ['-r', `
    $im = imagecreatetruecolor(3000, 2000);
    for ($y = 0; $y < 2000; $y++) {
      for ($x = 0; $x < 3000; $x += 3) {
        imagefilledrectangle($im, $x, $y, $x + 3, $y, imagecolorallocate($im, ($x * 7 + $y * 13) % 256, ($x * 3) % 256, ($y * 5) % 256));
      }
    }
    imagejpeg($im, '${PORTADA.replace(/\\/g, '/')}', 98);

    $lg = imagecreatetruecolor(1400, 1400);
    for ($y = 0; $y < 1400; $y++) {
      for ($x = 0; $x < 1400; $x++) {
        imagesetpixel($lg, $x, $y, imagecolorallocate($lg, ($x * 7 + $y * 13) % 256, ($x * 3) % 256, ($y * 5) % 256));
      }
    }
    imagepng($lg, '${LOGO.replace(/\\/g, '/')}', 0);
  `], { encoding: 'utf8' });

  writeFileSync(NO_IMAGEN, 'esto no es una imagen');
};

if (process.argv.includes('--preparar') || ! existsSync(PORTADA) || ! existsSync(LOGO)) {
  preparar();
  console.log(`  portada: ${Math.round(statSync(PORTADA).size / 1024)} KB · logo: ${Math.round(statSync(LOGO).size / 1024)} KB`);

  if (process.argv.includes('--preparar')) process.exit(0);
}

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const alPaso = async (n) => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate((paso) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = paso; }, n);
  await esperar(300);
};

/** Lo que de verdad va a viajar: el archivo que quedó dentro del input. */
const loQueViaja = (nombre) => p.evaluate((n) => {
  const f = document.querySelector(`input[name="${n}"]`)?.files?.[0];

  return f ? { nombre: f.name, tipo: f.type, bytes: f.size } : null;
}, nombre);

/** Y sus dimensiones reales, decodificándolo. */
const medidas = (nombre) => p.evaluate(async (n) => {
  const f = document.querySelector(`input[name="${n}"]`)?.files?.[0];

  if (! f) return null;

  const bmp = await createImageBitmap(f);
  const r = { ancho: bmp.width, alto: bmp.height };
  bmp.close?.();

  return r;
}, nombre);

/* ═══════════════ La portada: 2 MB ════════════════════════════════ */

t('P19 — la imagen de portada se reduce sola (máx. 2 MB)');

await alPaso(4);

const original = statSync(PORTADA).size;
di('La imagen de prueba pasa del límite', original > 2048 * 1024, `${Math.round(original / 1024)} KB`);

await (await p.$('input[name="imagen"]')).uploadFile(PORTADA);
await esperar(2500);

const portada = await loQueViaja('imagen');
di('**Lo que va a subirse ya cabe**', portada && portada.bytes <= 2048 * 1024,
  portada ? `${Math.round(portada.bytes / 1024)} KB` : '(nada)');
di('Y pesa bastante menos que el original', portada && portada.bytes < original * 0.8,
  portada ? `${Math.round(original / 1024)} KB → ${Math.round(portada.bytes / 1024)} KB` : '');

const dims = await medidas('imagen');
di('Reducida a 1600 px de lado largo', dims && Math.max(dims.ancho, dims.alto) === 1600,
  dims ? `${dims.ancho}×${dims.alto}` : '');
di('Conserva su tipo', portada?.tipo === 'image/jpeg', portada?.tipo);

const pantalla = await p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
const pesoDicho = await p.evaluate(() => {
  const caja = document.querySelector('[data-campo="imagen"]');

  return [...caja.querySelectorAll('.helper')]
    .filter((h) => h.getBoundingClientRect().height > 0)
    .map((h) => h.innerText.replace(/\s+/g, ' ').trim())
    .find((t) => /\d+([,.]\d+)? (KB|MB)$/.test(t)) ?? '';
});
di('Se le dice cuánto pesa lo que eligió', pesoDicho !== '' && ! /máx/.test(pesoDicho), pesoDicho);
di('Y no se le enseña ningún error', await p.evaluate(() => {
  const caja = document.querySelector('[data-campo="imagen"]');

  return ! caja?.dataset.errorPropio;
}));

t('Y se ve la vista previa de lo elegido');

di('La miniatura enseña lo que se subió', await p.evaluate(() => {
  const img = document.querySelector('[data-campo="imagen"] img');

  return !! img && img.src.startsWith('blob:') && img.getBoundingClientRect().height > 10;
}));

/* ═══════════════ El logo: 500 KB ═════════════════════════════════ */

t('P19 — el logo de la organización (máx. 500 KB)');

await alPaso(3);

const originalLogo = statSync(LOGO).size;
di('El logo de prueba pasa del límite', originalLogo > 500 * 1024, `${Math.round(originalLogo / 1024)} KB`);

await (await p.$('input[name="org_logo"]')).uploadFile(LOGO);
await esperar(2500);

const logo = await loQueViaja('org_logo');
di('Se reduce a lo que cabe', logo && logo.bytes <= 500 * 1024,
  logo ? `${Math.round(originalLogo / 1024)} KB → ${Math.round(logo.bytes / 1024)} KB` : '(nada)');

const dimsLogo = await medidas('org_logo');
di('Y a 800 px de lado largo', dimsLogo && Math.max(dimsLogo.ancho, dimsLogo.alto) === 800,
  dimsLogo ? `${dimsLogo.ancho}×${dimsLogo.alto}` : '');

/* ═══════════════ Lo que no es una imagen ═════════════════════════ */

t('Lo que no es una imagen se rechaza al elegirlo');

await (await p.$('input[name="org_logo"]')).uploadFile(NO_IMAGEN);
await esperar(600);

const avisoTipo = await p.evaluate(() => {
  const caja = document.querySelector('[data-campo="org_logo"]');
  const aviso = caja?.querySelector('.field-error');

  return { texto: aviso?.textContent.trim() ?? '', visible: (aviso?.getBoundingClientRect().height ?? 0) > 0 };
});

di('Se avisa del tipo', /JPG, PNG o WebP/.test(avisoTipo.texto), avisoTipo.texto);
di('Y el aviso se ve', avisoTipo.visible);
di('El campo queda vacío, no con el archivo malo',
  (await loQueViaja('org_logo')) === null);

/* ═══════════════ El bloqueo del envío ════════════════════════════ */

t('Si aun reducida no cabe, se corta el envío');

await alPaso(4);

/*
 * El caso es raro de provocar con un archivo de verdad —hay que dar con una
 * imagen que no baje del límite ni a 1600 px— así que se marca la caja a mano,
 * que es exactamente lo que hace el componente cuando no lo consigue. Lo que
 * se comprueba aquí es la consecuencia: que el envío no sale.
 */
await p.evaluate(() => {
  document.querySelector('[data-campo="imagen"]').dataset.errorPropio =
    'La imagen de portada pesa 4,2 MB y el máximo son 2,0 MB. Prueba con una más pequeña.';
});

const cortado = await p.evaluate(() => {
  const form = document.querySelector('form');
  let seEnvio = false;
  const espia = (e) => { seEnvio = ! e.defaultPrevented; };

  form.addEventListener('submit', espia);
  form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
  form.removeEventListener('submit', espia);

  return seEnvio;
});

di('**El envío no sale**', cortado === false);

/*
 * El resumen que SE VE, no el primero del DOM: hay uno por paso y los de los
 * pasos que no se están mirando están ahí, ocultos. Buscar el primero daba un
 * falso fallo.
 */
const resumen = await p.evaluate(() => {
  const caja = [...document.querySelectorAll('[data-resumen-errores]')]
    .find((c) => c.getBoundingClientRect().height > 0);

  return { visible: !! caja, texto: caja?.innerText.replace(/\s+/g, ' ') ?? '' };
});

di('El resumen de arriba lo enseña', resumen.visible);
di('Con el motivo, no sólo el nombre del campo', /pesa .* y el máximo son/.test(resumen.texto),
  resumen.texto.slice(0, 120));

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
