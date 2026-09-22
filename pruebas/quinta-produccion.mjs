// Q2, Q3 y Q4 comprobados en producción.
//
// Es la gemela de `quinta-tanda.mjs` sin `tinker`: allí la base no se toca
// desde aquí. Lo único que escribe es el enlace del kit en Configuración —y
// lo devuelve a como estaba al terminar—, porque sin enlace el botón de Q4 no
// se pinta y no habría nada que mirar. Ese guardado sirve además para probar
// Q3 sobre el propio ajuste: se escribe sin `https://` a propósito.
//
//   DPS_URL=https://el-sitio-en-produccion DPS_ADMIN=... DPS_CLAVE_ADMIN=... \
//   DPS_ORG=... DPS_CLAVE_ORG=... node pruebas/quinta-produccion.mjs
import puppeteer from 'puppeteer-core';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

// Sin protocolo a propósito: es la mitad de Q3 que se prueba aquí.
const KIT_SIN_PROTOCOLO = 'drive.google.com/drive/folders/kit-dps';
const KIT_ESPERADO = 'https://'+KIT_SIN_PROTOCOLO;

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
const entrar = async (puerta, c, k) => {
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', c);
  await p.type('input[name="password"]', k);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

  return ! p.url().includes('/login');
};
const salir = () => p.evaluate(async () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  for (const puerta of ['/admin/logout', '/mi-cuenta/logout']) {
    await fetch(puerta, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _token: token ?? '' }).toString(),
      credentials: 'same-origin',
    }).catch(() => {});
  }
});

/** Guarda el enlace del kit desde el formulario de Configuración. */
const guardarKit = async (valor) => {
  await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(300);

  await p.evaluate((v) => {
    const c = document.querySelector('[name="kit_difusion_url"]');
    c.value = v;
    c.dispatchEvent(new Event('input', { bubbles: true }));
    c.dispatchEvent(new Event('blur', { bubbles: true }));
  }, valor);
  await esperar(300);

  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('[name="kit_difusion_url"]').closest('form').submit()),
  ]);

  await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });

  return p.$eval('[name="kit_difusion_url"]', (e) => e.value);
};

let kitOriginal = null;

try {
  /* ═════════════ Q2 — el logo se lee en la ficha ═══════════════════ */

  t('Q2 — el logo de la organización en las fichas publicadas');

  await p.goto(`${B}/actividades`, { waitUntil: 'networkidle2' });
  const fichas = await p.$$eval('a[href*="/activity/"]', (n) => [...new Set(n.map((a) => a.getAttribute('href')))]);
  di('Hay fichas publicadas que mirar', fichas.length > 0, `${fichas.length}`);

  const medidas = [];

  for (const ruta of fichas) {
    await p.goto(new URL(ruta, B).toString(), { waitUntil: 'networkidle2' });

    const m = await p.evaluate(() => {
      const im = document.querySelector('.org-firma img.org-logo');
      if (! im) return null;

      const r = im.getBoundingClientRect();

      return {
        org: document.querySelector('.org-firma .org-nombre')?.textContent.trim(),
        w: Math.round(r.width), h: Math.round(r.height),
        nw: im.naturalWidth, nh: im.naturalHeight,
        carga: im.naturalWidth > 0,
      };
    });

    if (m) medidas.push({ ...m, ruta });
  }

  di('Y fichas con logo, no sólo con iniciales', medidas.length > 0, `${medidas.length} de ${fichas.length}`);

  for (const m of medidas) {
    console.log(`  · ${m.ruta.replace(B, '')} — ${m.org}: pintado ${m.w}×${m.h}, original ${m.nw}×${m.nh}`);

    /*
     * Lo que se comprueba es que el ancho SIGA A LA FORMA del logo: el alto de
     * la caja es fijo y el ancho sale de ahi, acotado entre el cuadrado de 54
     * y los 170 donde empezaría a comerse el nombre. Un logo cuadrado da 54 y
     * uno apaisado, más; lo que no puede pasar es que todos den 54, que era el
     * fallo.
     */
    const contenido = 54 - 10;
    const esperado = Math.min(170, Math.max(54, Math.round(contenido * (m.nw / m.nh)) + 10));

    di(`   la imagen carga`, m.carga === true);
    di(`   el alto es el de la firma`, m.h === 54, `${m.h}px`);
    di(`   **y el ancho sale de su proporción**`,
      Math.abs(m.w - esperado) <= 2, `${m.w}px, esperado ~${esperado}px`);
    di(`   entre el cuadrado y el tope`, m.w >= 54 && m.w <= 170, `${m.w}px`);
  }

  /* ═════════════ Q3 — enlaces sin protocolo ════════════════════════ */

  t('Q3 — el formulario de actividad');

  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(300);

  const campo = await p.$eval('input[name="enlace_web"]', (e) => ({ tipo: e.type, modo: e.inputMode, auto: e.hasAttribute('data-autoprotocolo') }));
  di('El campo no es type="url"', campo.tipo === 'text', `type="${campo.tipo}"`);
  di('Pide el teclado de direcciones', campo.modo === 'url');
  di('Y lleva el completado automático', campo.auto === true);

  await p.evaluate(() => {
    const c = document.querySelector('input[name="enlace_web"]');
    c.value = 'www.tusitio.cl';
    c.dispatchEvent(new Event('input', { bubbles: true }));
    c.dispatchEvent(new Event('blur', { bubbles: true }));
  });
  await esperar(250);

  di('Escribir sin protocolo lo completa al salir del campo',
    (await p.$eval('input[name="enlace_web"]', (e) => e.value)) === 'https://www.tusitio.cl',
    await p.$eval('input[name="enlace_web"]', (e) => e.value));
  di('Y el navegador no lo corta', await p.$eval('input[name="enlace_web"]', (e) => e.checkValidity()));

  t('Q3 — la ficha de organización del panel');

  di('Entra la cuenta de administración', await entrar('/admin/login', ADMIN, CLAVE_ADMIN), p.url());

  await p.goto(`${B}/admin/organizaciones`, { waitUntil: 'networkidle2' });
  const editar = await p.$$eval('a', (n) => n.map((a) => a.getAttribute('href')).find((h) => h && /organizaciones\/\d+\/editar/.test(h)));

  await p.goto(new URL(editar, B).toString(), { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(300);

  const campoPanel = await p.$eval('input[name="enlace_web"]', (e) => ({ tipo: e.type, auto: e.hasAttribute('data-autoprotocolo') }));
  di('Tampoco es type="url" en el panel', campoPanel.tipo === 'text', `type="${campoPanel.tipo}"`);
  di('Y lleva el completado automático', campoPanel.auto === true);

  await p.evaluate(() => {
    const c = document.querySelector('input[name="enlace_web"]');
    c.value = 'instagram.com/tuorganizacion';
    c.dispatchEvent(new Event('input', { bubbles: true }));
    c.dispatchEvent(new Event('blur', { bubbles: true }));
  });
  await esperar(300);

  // No se envía: sólo se mira que no aparezca el aviso rojo de antes.
  const aviso = await p.evaluate(() => {
    const caja = document.querySelector('input[name="enlace_web"]').closest('.campo');

    return [...caja.querySelectorAll('.field-error')]
      .filter((e) => e.getBoundingClientRect().height > 0)
      .map((e) => e.innerText.trim()).join(' | ');
  });
  di('**Sin protocolo ya no pinta error**', aviso === '', aviso || '(ninguno)');

  /* ═════════════ Q3 + Q4 — el enlace del kit ═══════════════════════ */

  t('Q3 — el enlace del kit se guarda con el protocolo puesto');

  await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
  kitOriginal = await p.$eval('[name="kit_difusion_url"]', (e) => e.value);
  console.log(`  (valor original del ajuste: ${kitOriginal === '' ? '(vacío)' : kitOriginal})`);

  const guardado = await guardarKit(KIT_SIN_PROTOCOLO);
  di('Se escribe sin `https://` y se guarda con él', guardado === KIT_ESPERADO, guardado);

  t('Q4 — el botón del kit en la barra de mi-cuenta');

  await salir();

  /*
   * En produccion puede no haber ninguna cuenta de organizador disponible: las
   * dos sembradas quedaron desactivadas al sacar sus claves del repositorio
   * (C6). `/mi-cuenta` exige `role:organizer`, asi que un administrador no
   * sirve para mirar esta barra. Si no se puede entrar se dice y se sigue, que
   * es mejor que un monton de fallos que no son del codigo.
   */
  const conOrganizador = await entrar('/mi-cuenta/login', ORG, CLAVE_ORG);
  di('Entra la cuenta de organizador', conOrganizador, p.url());

if (! conOrganizador) {
  console.log('');
  console.log('  *** Q4 NO SE PUEDE COMPROBAR AQUI: no hay sesion de organizador. ***');
  console.log('      Hace falta una cuenta con rol de organizador activa en el servidor.');
} else {
  const idActividad = await p.$$eval('a', (n) => {
    const h = n.map((a) => a.getAttribute('href')).find((x) => x && /\/mi-cuenta\/actividades\/\d+\/editar/.test(x));

    return h ? h.match(/actividades\/(\d+)\//)[1] : null;
  });

  const PANTALLAS = [
    ['Mis actividades', '/mi-cuenta/actividades'],
    ['Evaluaciones', '/mi-cuenta/evaluaciones'],
    ['Mi perfil', '/mi-cuenta/perfil'],
  ];

  if (idActividad) {
    PANTALLAS.push(
      ['Editar actividad', `/mi-cuenta/actividades/${idActividad}/editar`],
      ['Participantes', `/mi-cuenta/actividades/${idActividad}/participantes`],
      ['Cambios guardados', `/mi-cuenta/actividades/${idActividad}/guardado`],
    );
  }

  for (const [nombre, ruta] of PANTALLAS) {
    await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });

    const enlace = await p.evaluate((url) => {
      const a = [...document.querySelectorAll('a')]
        .find((x) => x.innerText.trim() === 'Kit de difusión');

      return a ? { visible: a.getBoundingClientRect().height > 0, href: a.getAttribute('href'), destino: a.getAttribute('target') } : null;
    }, KIT_ESPERADO);

    di(`${nombre}: el kit está en la barra`, enlace?.visible === true, ruta);
    di(`${nombre}: apunta al enlace de Configuración`, enlace?.href === KIT_ESPERADO, enlace?.href ?? '(no está)');
    di(`${nombre}: y con el resto de la barra`, (await texto()).includes('Cerrar sesión'));
  }

  t('Q4 — con el enlace vacío no se pinta');

  await salir();
  await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
  const vaciado = await guardarKit('');
  di('El ajuste se puede dejar vacío', vaciado === '', `«${vaciado}»`);

  await salir();
  await entrar('/mi-cuenta/login', ORG, CLAVE_ORG);
  await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });

  di('**Sin enlace no hay botón**', ! (await texto()).includes('Kit de difusión'));
  di('Pero la barra sigue ahí', (await texto()).includes('Cerrar sesión'));
}
} finally {
  /* El ajuste vuelve a como estaba, pase lo que pase por el camino. */
  if (kitOriginal !== null) {
    await salir();
    await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
    const devuelto = await guardarKit(kitOriginal);
    console.log('');
    console.log(`  (ajuste devuelto a: ${devuelto === '' ? '(vacío)' : devuelto})`);
  }

  await nav.close();
}

t('Errores de JavaScript');
di('Ninguno', errores.length === 0, errores.join(' | '));

console.log('');
console.log(`RESULTADO: ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
