// La portada se sube en JPEG, y el envío desde el teléfono termina. En Chrome.
//
// EL BUG (04/10): desde un teléfono con mala cobertura, «Enviar actividad» se
// quedaba girando para siempre y no se creaba nada. La portada era una
// ilustración en PNG: el navegador la reducía a 1600 px pero la dejaba en PNG,
// ~2 MB. Esa subida a 150 kbit/s no terminaba nunca y LiteSpeed cortaba la
// conexión a los ~3 minutos, sin que la petición llegara a Laravel —por eso el
// log no tenía nada—. Ahora la portada sale siempre en JPEG (~200 KB).
//
// Se comprueba con la subida limitada a 150 kbit/s, que es lo que la rompía.
// CREA una cuenta, una organización y una actividad: sólo en local.
//
//   node pruebas/portada-movil.mjs
import puppeteer from 'puppeteer-core';
import { PNG } from 'pngjs';
import { writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

if (!/127\.0\.0\.1|localhost|\.test/.test(B)) {
  console.log('Esta prueba crea datos: no se corre contra producción.');
  process.exit(1);
}

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

/*
 * Una «ilustración» de 2400x1800: bloques de color con bordes y algo de grano,
 * que en PNG pesa varios MB y en JPEG unos cientos de KB, como la de la prueba
 * real. Lleva una franja transparente para comprobar que no sale negra.
 */
const DIBUJO = join(tmpdir(), 'dps-portada-ilustracion.png');
{
  const png = new PNG({ width: 2400, height: 1800 });
  for (let y = 0; y < png.height; y++) {
    for (let x = 0; x < png.width; x++) {
      const i = (png.width * y + x) << 2;
      const bloque = ((x >> 6) * 7 + (y >> 6) * 13) % 5;
      const grano = (x * 31 + y * 17) % 23;
      png.data[i] = [229, 40, 250, 90, 200][bloque] - grano;
      png.data[i + 1] = [114, 120, 220, 200, 60][bloque] - grano;
      png.data[i + 2] = [0, 200, 240, 90, 220][bloque] - grano;
      png.data[i + 3] = y < 120 ? 0 : 255;
    }
  }
  writeFileSync(DIBUJO, PNG.sync.write(png));
}

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 390, height: 844, deviceScaleFactor: 3, isMobile: true, hasTouch: true });

const paso = async (n) => {
  await p.evaluate((n) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = n; }, n);
  await esperar(250);
};
const valores = (sel) => p.$$eval(`${sel} option`, (os) => os.map((o) => o.value).filter(Boolean));

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);

const marca = Date.now();
await paso(3);
await p.type('input[name="org_nombre"]', `Organización Portada ${marca}`);
await p.type('input[name="email"]', `portada${marca}@ong-laravel.test`);
await p.type('input[name="password"]', 'clave-larga-1234');
await p.type('input[name="password_confirmation"]', 'clave-larga-1234');

await paso(4);
await p.type('input[name="titulo"]', `Portada desde el teléfono ${marca}`);
await p.type('textarea[name="descripcion"]', 'Descripción para la prueba de la portada.');
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

t('La portada PNG se pasa a JPEG al elegirla');

const entrada = await p.$('input[name="imagen"]');
await entrada.uploadFile(DIBUJO);
await p.waitForFunction(() => {
  const n = document.querySelector('input[name="imagen"]');
  return n.files[0]?.type === 'image/jpeg' || ! Alpine.$data(n).reduciendo && n.files[0];
}, { timeout: 20000 });
await esperar(300);

const enCampo = await p.$eval('input[name="imagen"]', (n) => ({ nombre: n.files[0].name, tipo: n.files[0].type, kb: n.files[0].size / 1024 }));
const original = (await import('node:fs')).statSync(DIBUJO).size / 1024;

di('Lo que se va a subir es un JPEG', enCampo.tipo === 'image/jpeg', enCampo.tipo);
di('Y lleva extensión .jpg', enCampo.nombre.endsWith('.jpg'), enCampo.nombre);
di('Pesa una fracción del PNG', enCampo.kb < original / 4, `${Math.round(original)} KB → ${Math.round(enCampo.kb)} KB`);
di('Por debajo de 600 KB', enCampo.kb < 600, `${Math.round(enCampo.kb)} KB`);

// Lo transparente, en blanco y no en negro: se lee el píxel de la franja.
const esquina = await p.evaluate(async () => {
  const b = await createImageBitmap(document.querySelector('input[name="imagen"]').files[0]);
  const c = document.createElement('canvas');
  c.width = 4; c.height = 4;
  c.getContext('2d').drawImage(b, 0, 0, 4, 4, 0, 0, 4, 4);
  return [...c.getContext('2d').getImageData(1, 1, 1, 1).data].slice(0, 3);
});
di('La parte transparente sale blanca, no negra', esquina.every((v) => v > 240), esquina.join(','));

t('Con la subida a 150 kbit/s, el envío termina');

const cdp = await p.createCDPSession();
await cdp.send('Network.enable');
await cdp.send('Network.emulateNetworkConditions', {
  offline: false, latency: 150, downloadThroughput: 1500 * 128, uploadThroughput: 150 * 128,
});

const t0 = Date.now();
await Promise.all([
  p.waitForNavigation({ timeout: 90000 }).catch(() => {}),
  (await p.$('button[type="submit"].btn-primary')).tap(),
]);
const segundos = (Date.now() - t0) / 1000;

di('Llega a la pantalla de «enviada»', /\/listo$/.test(p.url()), p.url().replace(B, ''));
di('En menos de 40 s (con PNG no terminaba nunca)', segundos < 40, `${segundos.toFixed(1)} s`);

t('Sin errores de JavaScript');
di('La consola quedó limpia', errores.length === 0, errores.slice(0, 2).join(' · '));

console.log('');
console.log(`${ok} bien · ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
