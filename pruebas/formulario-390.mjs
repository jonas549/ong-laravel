// El formulario de publicar actividad, entero, a 390 px. En Chrome.
//
// Las tres reglas del encargo del 04/10, aplicadas a CADA elemento visible de
// cada tarjeta, en cada paso y en varios estados (vacío, con errores, relleno):
//
//   1. Nada toca el borde de la tarjeta ni se sale: cada pieza queda a 12 px
//      o más del borde. Incluye el contorno rosa de un campo con error, que
//      no ocupa sitio en el flujo y por eso se salía sin que nada lo frenara.
//   2. Ningún texto queda cortado: ni un elemento recortado por `overflow`,
//      ni el texto de ayuda de un campo, ni un placeholder más largo que su
//      caja.
//   3. Nada se monta sobre nada: dos piezas que no son una dentro de la otra
//      no pueden compartir píxeles.
//
// No es una regla de diseño inventada: es lo que pidió el cliente, que lo vio
// en su teléfono. Y no se fía de las capturas: mide. Las capturas las deja en
// la carpeta de `CAPTURAS` para mirarlas a ojo, que es la otra mitad.
//
//   node pruebas/formulario-390.mjs
//   CAPTURAS=C:/ruta node pruebas/formulario-390.mjs
import puppeteer from 'puppeteer-core';
import { PNG } from 'pngjs';
import { writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const CAPTURAS = process.env.CAPTURAS ?? null;
const ANCHO = Number(process.env.ANCHO ?? 390);

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

// Una imagen cualquiera para la portada y el logo, con nombre largo a propósito:
// el nombre del archivo es uno de los textos que se cortaban.
const IMAGEN = join(tmpdir(), 'foto-de-la-actividad-con-un-nombre-muy-largo-1000739531.png');
{
  const png = new PNG({ width: 400, height: 300 });
  for (let i = 0; i < png.data.length; i += 4) { png.data[i] = 229; png.data[i + 1] = 114; png.data[i + 2] = 0; png.data[i + 3] = 255; }
  writeFileSync(IMAGEN, PNG.sync.write(png));
}

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: ANCHO, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });

const datos = (fn, ...a) => p.evaluate(fn, ...a);
const wiz = (cambios) => datos((c) => Object.assign(Alpine.$data(document.querySelector('[x-data^="wizard"]')), c), cambios);

/**
 * Mide la tarjeta (o tarjetas) del paso visible y devuelve los problemas.
 * Todo en el navegador: es donde están las cajas de verdad.
 */
const auditar = () => datos((margen) => {
  const visible = (el) => {
    for (let n = el; n && n !== document.body; n = n.parentElement) {
      const cs = getComputedStyle(n);
      if (cs.display === 'none' || cs.visibility === 'hidden' || Number(cs.opacity) === 0) return false;
    }
    const r = el.getBoundingClientRect();
    return r.width > 0.5 && r.height > 0.5;
  };

  // En el wizard, el paso visible; en el editor de mi-cuenta, la página.
  const paso = [...document.querySelectorAll('[data-paso]')].find(visible) ?? document.querySelector('main');
  if (!paso) return { problemas: ['no hay paso visible'], piezas: 0 };

  // Las tarjetas: cajas blancas con esquinas redondeadas dentro del paso.
  const tarjetas = [...paso.querySelectorAll('*')].filter((n) => {
    const cs = getComputedStyle(n);
    return visible(n) && cs.backgroundColor === 'rgb(255, 255, 255)'
      && parseFloat(cs.borderTopLeftRadius) >= 14 && n.getBoundingClientRect().width > 250;
  }).filter((n, _, todas) => !todas.some((o) => o !== n && o.contains(n)));

  // Las piezas: lo que se ve y se lee, no los contenedores.
  const esPieza = (n) => {
    if (!visible(n)) return false;
    if (n.matches('input:not([type=hidden]), select, textarea, button, img, svg, .chip, .btn')) return true;
    if (n.closest('svg, button, .btn, .chip, select')) return false;
    return [...n.childNodes].some((c) => c.nodeType === 3 && c.textContent.trim());
  };

  const nombre = (n) => {
    const txt = (n.innerText || n.placeholder || n.getAttribute('aria-label') || n.value || n.tagName).replace(/\s+/g, ' ').trim();
    return `${n.tagName.toLowerCase()}${n.className && typeof n.className === 'string' ? '.' + n.className.trim().split(/\s+/)[0] : ''} «${txt.slice(0, 40)}»`;
  };

  const problemas = [];
  let piezas = 0;

  for (const tarjeta of tarjetas) {
    const T = tarjeta.getBoundingClientRect();
    const lista = [...tarjeta.querySelectorAll('*')].filter(esPieza);
    piezas += lista.length;

    // Lo que se dibuja de cada pieza, contando su contorno y quitando lo que
    // recorta un contenedor suyo (una miniatura dentro de su marco) — sin
    // pasar de la tarjeta, que es justo contra la que se mide el borde.
    const caja = (n) => {
      const r = n.getBoundingClientRect();
      const cs = getComputedStyle(n);
      const o = cs.outlineStyle !== 'none' ? parseFloat(cs.outlineWidth) + parseFloat(cs.outlineOffset || 0) : 0;
      const c = { l: r.left - o, r: r.right + o, t: r.top - o, b: r.bottom + o };
      for (let a = n.parentElement; a && a !== tarjeta; a = a.parentElement) {
        if (getComputedStyle(a).overflow === 'visible') continue;
        const ar = a.getBoundingClientRect();
        c.l = Math.max(c.l, ar.left); c.r = Math.min(c.r, ar.right); c.t = Math.max(c.t, ar.top); c.b = Math.min(c.b, ar.bottom);
      }
      return c;
    };

    // Un texto en línea que ocupa dos renglones tiene un rectángulo que
    // abarca los dos enteros: se mira renglón a renglón.
    const renglones = (n) => (getComputedStyle(n).display === 'inline' ? [...n.getClientRects()] : [n.getBoundingClientRect()]);

    /*
     * Lo que se monta A PROPÓSITO no cuenta: el ojo de la contraseña y el
     * botón del calendario van dentro de su campo, y una lista de
     * sugerencias es un desplegable que flota sobre lo de debajo mientras se
     * elige. De ese último sí se mira que no se salga de la tarjeta.
     */
    const flotante = (n) => n.closest('.campo-visor, .campo-selector-boton, [role=listbox]');

    // 1 · bordes. Las cajas con error se miden aparte, con su contorno.
    for (const n of [...lista, ...tarjeta.querySelectorAll('.campo-fallido')]) {
      if (!visible(n)) continue;
      const c = caja(n);
      if (c.l < T.left + margen - 0.5 || c.r > T.right - margen + 0.5) {
        problemas.push(`borde: ${nombre(n)} va de ${Math.round(c.l - T.left)} a ${Math.round(T.right - c.r)} px de los bordes`);
      }
    }

    // 2 · texto cortado.
    for (const n of lista) {
      const cs = getComputedStyle(n);
      const corta = cs.overflowX !== 'visible' || cs.textOverflow === 'ellipsis' || cs.whiteSpace === 'nowrap';
      if (!n.matches('input, textarea, select') && corta && n.scrollWidth > n.clientWidth + 1) {
        problemas.push(`cortado: ${nombre(n)} (${n.scrollWidth} px en ${n.clientWidth})`);
      }
      // El texto que asoma fuera de su propia caja, aunque nadie lo recorte.
      if (!n.matches('input, textarea, select, img, svg')) {
        const rango = document.createRange();
        rango.selectNodeContents(n);
        const rr = rango.getBoundingClientRect();
        const r = n.getBoundingClientRect();
        if (rr.width && (rr.right > r.right + 1 || rr.left < r.left - 1) && cs.overflowX !== 'visible') {
          problemas.push(`cortado: ${nombre(n)} (el texto sale de su caja)`);
        }
      }
      // El placeholder: se mide con la fuente del campo.
      if (n.matches('input[placeholder], textarea[placeholder]') && !n.value && n.tagName === 'INPUT') {
        const lienzo = document.createElement('canvas').getContext('2d');
        lienzo.font = `${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
        const ancho = lienzo.measureText(n.placeholder).width;
        const util = n.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
        if (ancho > util + 1) problemas.push(`cortado: el placeholder de ${nombre(n)} (${Math.round(ancho)} px en ${Math.round(util)})`);
      }
      // El nombre de un select largo.
      if (n.matches('select')) {
        const lienzo = document.createElement('canvas').getContext('2d');
        lienzo.font = `${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
        const txt = n.options[n.selectedIndex]?.text ?? '';
        const util = n.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
        if (lienzo.measureText(txt).width > util + 1) problemas.push(`cortado: la opción elegida de ${nombre(n)} «${txt.slice(0, 30)}»`);
      }
    }

    // 3 · solapes, entre piezas que no se contienen.
    for (let i = 0; i < lista.length; i++) {
      for (let j = i + 1; j < lista.length; j++) {
        const a = lista[i], b = lista[j];
        if (a.contains(b) || b.contains(a) || flotante(a) || flotante(b)) continue;
        const A = caja(a), Bc = caja(b);
        let ancho = Math.min(A.r, Bc.r) - Math.max(A.l, Bc.l);
        let alto = Math.min(A.b, Bc.b) - Math.max(A.t, Bc.t);
        if (ancho > 1 && alto > 1 && (renglones(a).length > 1 || renglones(b).length > 1)) {
          // Renglón contra renglón.
          const pisan = renglones(a).some((ra) => renglones(b).some((rb) =>
            Math.min(ra.right, rb.right) - Math.max(ra.left, rb.left) > 1 && Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top) > 1));
          if (!pisan) ancho = 0;
        }
        if (ancho > 1 && alto > 1) problemas.push(`solape: ${nombre(a)} con ${nombre(b)} (${Math.round(ancho)}×${Math.round(alto)} px)`);
      }
    }
    // Y el contorno de una caja con error contra lo que tenga alrededor.
    for (const f of tarjeta.querySelectorAll('.campo-fallido')) {
      if (!visible(f)) continue;
      const F = caja(f);
      for (const n of lista) {
        if (f.contains(n) || n.contains(f) || flotante(n)) continue;
        const r = n.getBoundingClientRect();
        if (Math.min(F.r, r.right) - Math.max(F.l, r.left) > 1 && Math.min(F.b, r.bottom) - Math.max(F.t, r.top) > 1) {
          problemas.push(`solape: el recuadro de error de «${f.dataset.etiqueta ?? f.dataset.campo}» con ${nombre(n)}`);
        }
      }
    }
  }

  const desborde = document.documentElement.scrollWidth - innerWidth;
  if (desborde > 0) problemas.push(`la página se desplaza ${desborde} px en horizontal`);

  return { problemas: [...new Set(problemas)], piezas, tarjetas: tarjetas.length };
}, 12);

const revisar = async (estado) => {
  await esperar(450);
  const { problemas, piezas, tarjetas } = await auditar();
  di(`${estado}: ${piezas} piezas en ${tarjetas} tarjeta(s), sin problemas`, problemas.length === 0, problemas.length ? `${problemas.length}` : '');
  problemas.slice(0, 25).forEach((x) => console.log(`        · ${x}`));

  // La barra de pasos se desplaza en horizontal: el paso en curso tiene que
  // quedar a la vista, no fuera por la derecha.
  await esperar(500);
  if (!(await p.$('[x-data^="wizard"]'))) return;
  const enBarra = await datos(() => {
    const paso = Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso;
    const b = document.querySelector(`[data-paso-barra="${paso}"]`);
    const barra = b?.parentElement;
    if (!b) return { ve: false, paso };
    const r = b.getBoundingClientRect(), R = barra.getBoundingClientRect();
    return { ve: r.left >= R.left - 1 && r.right <= R.right + 1, paso };
  });
  di(`${estado}: el paso ${enBarra.paso} se ve en la barra de arriba`, enBarra.ve);
  if (CAPTURAS) await p.screenshot({ path: `${CAPTURAS}/${estado.replace(/[^\wáéíóúñ]+/gi, '-').toLowerCase()}.png`, fullPage: true });
};

await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);

t(`El wizard a ${ANCHO} px`);

await wiz({ paso: 1 }); await revisar('Paso 1');
await wiz({ paso: 2 }); await revisar('Paso 2');
await wiz({ paso: 3 }); await revisar('Paso 3 vacío');

await datos(() => [...document.querySelectorAll('button')].find((b) => /Continuar/.test(b.textContent) && b.offsetParent)?.click());
await revisar('Paso 3 con errores');

await wiz({ tipo: 'Otra' });
await revisar('Paso 3 con tipo Otra');
await wiz({ tipo: 'Institución educativa' });
await revisar('Paso 3 con institución educativa');
await wiz({ tipo: 'Organización sin fines de lucro' });

await (await p.$('input[name="org_logo"]')).uploadFile(IMAGEN);
await p.type('input[name="org_nombre"]', 'Fundación');
await esperar(900);
await revisar('Paso 3 con logo y buscador abierto');

await wiz({ paso: 4 });
await revisar('Paso 4 vacío');

await datos(() => document.querySelector('form').requestSubmit());
await wiz({ paso: 4 });
await revisar('Paso 4 con errores');

await wiz({ colab: true, insc: true, acc: true, colabs: ['Fundación de prueba con un nombre bastante largo', 'Junta de vecinos'] });
await (await p.$('input[name="imagen"]')).uploadFile(IMAGEN);
await esperar(1200);
await revisar('Paso 4 relleno con portada');

await wiz({ sinFecha: true });
await revisar('Paso 4 sin fecha definida');

/* ══════════════ El editor de mi-cuenta: los mismos campos ══════════════ */

t(`El editor de «Mis actividades» a ${ANCHO} px`);

await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
await p.type('[name="email"]', ORG);
await p.type('[name="password"]', CLAVE_ORG);
await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

const editar = await datos(() => [...document.querySelectorAll('a[href*="/editar"]')].map((a) => a.href).find((h) => /actividades\/\d+\/editar/.test(h)));
if (editar) {
  await p.goto(editar, { waitUntil: 'networkidle2' });
  await revisar('Editor de actividad');
  await (await p.$('input[name="imagen"]'))?.uploadFile(IMAGEN);
  await esperar(1200);
  await revisar('Editor con portada nueva');
} else {
  console.log('  (el organizador sembrado no tiene actividades que editar)');
}

// Punto 4 del 04/10: la insignia «Tu actividad ya es parte del Día del
// Patrimonio Social» se salía de la tarjeta (heredaba un `nowrap` de las
// tablas del panel).
await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
await revisar('Mis actividades');

t('Sin errores de JavaScript');
di('La consola quedó limpia', errores.length === 0, errores.slice(0, 2).join(' · '));

console.log('');
console.log(`${ok} bien · ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
