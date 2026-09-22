// Sexta tanda, B7 y B8: los títulos de campo se distinguen de su ayuda, y en
// el teléfono el calendario de la fecha se ve y se usa.
//
//   node pruebas/sexta-formulario.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const alPaso4 = async () => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 4; });
  await esperar(300);
};

/* ═══════════════ B7 ═══════════════════════════════════════════ */

t('B7 — el título del campo se distingue de su ayuda');

await p.setViewport({ width: 1440, height: 1000 });
await alPaso4();

const estilos = await p.evaluate(() => ['correo_contacto', 'enlace_red_social', 'enlace_web'].map((nombre) => {
  const etiqueta = document.querySelector(`[name="${nombre}"]`).closest('.lbl');
  const ayuda = etiqueta.querySelector('.helper');
  const e = getComputedStyle(etiqueta);
  const a = getComputedStyle(ayuda);
  return {
    nombre,
    titulo: parseFloat(e.fontSize), pesoTitulo: Number(e.fontWeight), colorTitulo: e.color,
    ayuda: parseFloat(a.fontSize), pesoAyuda: Number(a.fontWeight), colorAyuda: a.color,
  };
}));

for (const s of estilos) {
  di(`${s.nombre}: título más grande que la ayuda`, s.titulo >= s.ayuda + 2, `${s.titulo} px / ${s.ayuda} px`);
  di(`${s.nombre}: título en negrita y la ayuda no`, s.pesoTitulo >= 700 && s.pesoAyuda <= 400, `${s.pesoTitulo} / ${s.pesoAyuda}`);
  di(`${s.nombre}: y de otro color`, s.colorTitulo !== s.colorAyuda, `${s.colorTitulo} / ${s.colorAyuda}`);
}

// Los títulos de los grupos de chips van igual que los de los campos.
const grupos = await p.evaluate(() => {
  const campo = parseFloat(getComputedStyle(document.querySelector('[name="correo_contacto"]').closest('.lbl')).fontSize);
  const temas = document.querySelector('[data-campo="temas"]');
  const titulo = temas ? [...temas.querySelectorAll('div')].find((d) => /tema/i.test(d.textContent)) : null;
  return { campo, grupo: titulo ? parseFloat(getComputedStyle(titulo).fontSize) : null };
});
di('Los títulos de los grupos de chips, al mismo tamaño', grupos.grupo === grupos.campo, `${grupos.grupo} / ${grupos.campo}`);

/* ═══════════════ B8 ═══════════════════════════════════════════ */

t('B8 — el calendario de la fecha, en el teléfono');

di('En escritorio no se pinta el botón grande', await p.evaluate(() =>
  document.querySelector('[data-campo="fecha_inicio"] .fecha-calendario-movil').getBoundingClientRect().height === 0));

await p.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
await alPaso4();

await p.evaluate(() => document.querySelector('[data-campo="fecha_inicio"] .fecha-calendario-movil')
  .scrollIntoView({ block: 'center', behavior: 'instant' }));
await esperar(300);
const boton = await p.evaluate(() => {
  const b = document.querySelector('[data-campo="fecha_inicio"] .fecha-calendario-movil');
  const nativo = b.querySelector('input[type="date"]');
  const cb = b.getBoundingClientRect();
  const campo = document.querySelector('input[name="fecha_inicio"]').getBoundingClientRect().width;
  const cn = nativo.getBoundingClientRect();
  // Lo que queda debajo del dedo en el centro del botón.
  const bajoElDedo = document.elementFromPoint(cb.x + cb.width / 2, cb.y + cb.height / 2);
  return {
    alto: cb.height, ancho: cb.width, campo, texto: b.innerText.trim(),
    cubre: Math.abs(cn.width - cb.width) < 2 && Math.abs(cn.height - cb.height) < 2,
    tocaElNativo: bajoElDedo === nativo,
  };
});
di('**En el teléfono se ve un botón «Elegir en el calendario»**', boton.alto >= 44 && boton.texto === 'Elegir en el calendario', `${Math.round(boton.ancho)}×${Math.round(boton.alto)} px`);
di('A todo el ancho del campo', Math.abs(boton.ancho - boton.campo) < 2, `${Math.round(boton.ancho)} / ${Math.round(boton.campo)} px`);
di('**Tocarlo es tocar un campo de fecha nativo**', boton.tocaElNativo && boton.cubre);

// Lo que el sistema haría al elegir un día: el campo nativo cambia.
await p.evaluate(() => {
  const n = document.querySelector('[data-campo="fecha_inicio"] .fecha-calendario-movil input');
  n.dispatchEvent(new Event('focus'));
  n.value = '2026-12-04';
  n.dispatchEvent(new Event('change', { bubbles: true }));
});
await esperar(200);
di('**Lo elegido se escribe en el campo de texto**',
  await p.$eval('input[name="fecha_inicio"]', (e) => e.value) === '04 / 12 / 2026',
  await p.$eval('input[name="fecha_inicio"]', (e) => e.value));

di('Y el calendario se abre por la fecha que ya hay escrita', await p.evaluate(() => {
  const n = document.querySelector('[data-campo="fecha_inicio"] .fecha-calendario-movil input');
  n.value = '';
  n.dispatchEvent(new Event('focus'));
  return n.value === '2026-12-04';
}));

// Pegar sigue funcionando: el campo de texto no se ha tocado.
await p.evaluate(() => {
  const c = document.querySelector('input[name="fecha_inicio"]');
  c.value = '2027-03-09';
  c.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertFromPaste' }));
});
await esperar(150);
di('Se puede seguir pegando una fecha', await p.$eval('input[name="fecha_inicio"]', (e) => e.value) === '09 / 03 / 2027',
  await p.$eval('input[name="fecha_inicio"]', (e) => e.value));

await p.evaluate(() => document.querySelector('input[name="sin_fecha_definida"]').click());
await esperar(200);
di('Con «Disponible de forma permanente» se desactiva', await p.evaluate(() =>
  document.querySelector('[data-campo="fecha_inicio"] .fecha-calendario-movil input').disabled));

di('Sin desborde horizontal a 390 px', await p.evaluate(() => document.documentElement.scrollWidth <= 390),
  String(await p.evaluate(() => document.documentElement.scrollWidth)));

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
