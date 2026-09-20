// Q2 — el logo de la organización en la ficha de actividad.
//
// Antes de tocar nada hay que saber si el problema es de datos o de vista, y
// eso se puede ver sin entrar a la base: la marquesina del home pinta el logo
// de cada organización que ha publicado, o sus iniciales si no lo tiene. Si
// ninguna tiene logo, la ficha está haciendo lo correcto al enseñar iniciales
// y no hay nada que arreglar en el código.
//
// Sólo mira: no envía ningún formulario.
//
//   DPS_URL=https://ong.sandboxdelta.com node pruebas/logo-organizacion.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });

console.log('=== Organizaciones en la marquesina del home ===');
console.log('');

await p.goto(`${B}/`, { waitUntil: 'networkidle2' });

const orgs = await p.$$eval('.marquee-track .logo-chip', (n) => n
  .filter((c) => c.getAttribute('aria-hidden') !== 'true')
  .map((c) => ({
    nombre: c.querySelector('span:last-child')?.textContent.trim(),
    logo: c.querySelector('img')?.getAttribute('src') ?? null,
  })));

orgs.forEach((o) => console.log(`  ${(o.nombre ?? '').padEnd(34)} ${o.logo ?? '(iniciales, sin logo)'}`));
console.log('');
console.log(`  ${orgs.filter((o) => o.logo).length} de ${orgs.length} tienen logo cargado.`);

console.log('');
console.log('=== Las fichas de actividad publicadas ===');
console.log('');

await p.goto(`${B}/actividades`, { waitUntil: 'networkidle2' });

// La ficha publica vive en /activity/{id}/{slug}; /actividades/{slug} es una
// redireccion antigua que sigue ahi por los enlaces que ya se repartieron.
const fichas = await p.$$eval('a[href*="/activity/"]', (n) => [...new Set(n
  .map((a) => a.getAttribute('href'))
  .filter(Boolean))]);

for (const ruta of fichas.slice(0, 12)) {
  await p.goto(new URL(ruta, B).toString(), { waitUntil: 'networkidle2' });

  const firma = await p.evaluate(() => {
    const img = document.querySelector('.org-firma img.org-logo');
    const ini = document.querySelector('.org-firma .org-logo--iniciales');
    const caja = (img ?? ini)?.getBoundingClientRect();

    return {
      nombre: document.querySelector('.org-firma .org-nombre')?.textContent.trim(),
      src: img?.getAttribute('src') ?? null,
      // `naturalWidth` a 0 con src puesto = la imagen no cargó.
      cargada: img ? img.naturalWidth > 0 : null,
      iniciales: ini?.textContent.trim() ?? null,
      alto: caja ? Math.round(caja.height) : 0,
    };
  });

  const que = firma.src
    ? `LOGO ${firma.src} ${firma.cargada ? '(carga)' : '*** NO CARGA ***'}`
    : `iniciales «${firma.iniciales}»`;

  console.log(`  ${p.url().replace(B, '')}`);
  console.log(`     organiza: ${firma.nombre} → ${que} · alto ${firma.alto}px`);
}

await nav.close();
