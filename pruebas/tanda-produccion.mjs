// Lo que se puede comprobar en PRODUCCIÓN sin escribir nada.
//
// Existe porque varias suites de esta tanda publican actividades, suben fotos,
// reclaman organizaciones o mandan correos a personas de verdad. En producción
// eso deja rastro y, en el caso del aviso de vuelta de ajustes, escribe a las
// tres cuentas de administración, dos de las cuales son personas.
//
// Aquí sólo se cargan pantallas y se consulta.
//
//   DPS_URL=https://ong.sandboxdelta.com node pruebas/tanda-produccion.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

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

const salir = async () => {
  if (! p.url().startsWith(B)) await p.goto(`${B}/`, { waitUntil: 'domcontentloaded' });

  await p.evaluate(async (base) => {
    for (const ruta of ['/mi-cuenta/logout', '/admin/logout']) {
      const doc = await (await fetch(base + '/', { credentials: 'same-origin' })).text();
      const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
      if (! token) continue;
      await fetch(base + ruta, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + encodeURIComponent(token),
      });
    }
  }, B);
};

const entrar = async (puerta, correo, clave) => {
  await salir();
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', correo);
  await p.type('input[name="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};

const alPaso = async (n) => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate((paso) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = paso; }, n);
  await esperar(350);
};

await salir();

/* ═══════════════ P9 — el buscador de organizaciones ══════════════ */

t('P9 — el buscador de organizaciones');

await alPaso(3);
di('El nombre es un buscador',
  await p.$eval('input[name="org_nombre"]', (n) => n.getAttribute('role') === 'combobox'));

await p.type('input[name="org_nombre"]', 'fund');
await p.waitForFunction(() => document.querySelectorAll('.org-sugerencia').length > 0, { timeout: 8000 })
  .catch(() => null);

const sugerencias = await p.$$eval('.org-sugerencia', (n) => n.map((b) => b.innerText.replace(/\s+/g, ' ').trim()));
di('Sugiere organizaciones de la base', sugerencias.length > 0, sugerencias.slice(0, 2).join(' | '));
di('Y distingue las que ya tienen cuenta', sugerencias.some((s) => /ya tiene cuenta/i.test(s)));

/* ═══════════════ P14 — el aviso de acceso ════════════════════════ */

t('P14 — «¿Ya tienes cuenta?»');

const aviso = await p.evaluate(() => {
  const c = document.querySelector('.acceso-aviso');

  return c ? { texto: c.innerText.replace(/\s+/g, ' ').trim(), alto: c.getBoundingClientRect().height } : null;
});
di('El aviso está arriba y se ve', !! aviso && aviso.alto > 0, aviso?.texto);

await p.evaluate(() => document.querySelector('.acceso-aviso button')?.click());
await esperar(400);
di('El diálogo se abre',
  await p.evaluate(() => (document.querySelector('.acceso-caja')?.getBoundingClientRect().height ?? 0) > 0));
di('Con las dos salidas', await p.evaluate(() => {
  const enlaces = [...document.querySelectorAll('.acceso-caja a')].map((a) => a.getAttribute('href') ?? '');

  return enlaces.some((h) => h.includes('recuperar')) && enlaces.some((h) => h.includes('/mi-cuenta/login'));
}));

/* ═══════════════ P13 — el selector de hora ═══════════════════════ */

t('P13 — horas en punto, sin minutos');

await alPaso(4);
di('Ya no hay selector nativo de hora',
  await p.evaluate(() => document.querySelectorAll('input[type="time"]').length) === 0);

await p.evaluate(() => document.querySelectorAll('.campo-selector-boton')[1]?.click());
await esperar(400);
/*
 * Las opciones del desplegable ABIERTO, no todas las de la página: hay un
 * campo de hora de inicio y otro de término, y cada uno monta su lista. Contar
 * `.hora-opcion` a secas da 48 y parece que sobran horas.
 */
const horas = await p.evaluate(() => {
  const lista = [...document.querySelectorAll('.hora-lista')]
    .find((l) => l.getBoundingClientRect().height > 0);

  return lista ? [...lista.querySelectorAll('.hora-opcion')].map((o) => o.innerText.replace(/\s+/g, ' ').trim()) : [];
});
di('El desplegable ofrece las 24 en punto', horas.length === 24, `${horas[0]} … ${horas[23]}`);
di('Todas en punto y con AM/PM', horas.every((h) => /^\d{2}:00 \d+ (AM|PM)$/.test(h)));
di('Y el campo sigue vacío: no propone la hora actual',
  await p.$eval('input[name="hora_inicio"]', (n) => n.value) === '');

/* ═══════════════ P16 — las direcciones ═══════════════════════════ */

t('P16 — sugerencias de dirección y el punto');

const geo = await p.evaluate(async (u) => {
  const r = await fetch(u);

  return { estado: r.status, datos: await r.json() };
}, `${B}/direcciones/buscar?q=avenida+providencia`);

di('El geocodificador responde desde el servidor', geo.estado === 200);

const uno = geo.datos?.direcciones?.[0];
di('Con sugerencias y su punto', !! uno && typeof uno.latitud === 'number',
  uno ? `${uno.etiqueta} [${uno.latitud}, ${uno.longitud}]` : '(ninguna)');
di('Dentro de Chile', !! uno && uno.latitud < -17 && uno.latitud > -56);
di('El campo de dirección es un buscador',
  await p.$eval('input[name="direccion"]', (n) => n.getAttribute('role') === 'combobox'));

/* ═══════════════ P15 — dónde se pregunta ═════════════════════════ */

t('P15 — la pregunta de los voluntarios');

await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).tipo = 'Empresa o institución privada'; });
await esperar(350);
di('Está en el paso de la actividad y se ve',
  await p.$eval('input[name="org_num_voluntarios"]', (n) => n.getBoundingClientRect().height > 0));
di('Junto a los participantes estimados', await p.evaluate(() => {
  const vol = document.querySelector('input[name="org_num_voluntarios"]');
  const est = document.querySelector('input[name="participantes_estimados"]');

  return !! vol && !! est && Math.abs(vol.getBoundingClientRect().top - est.getBoundingClientRect().top) < 400;
}));

/* ═══════════════ P19 — el peso de las imágenes ═══════════════════ */

t('P19 — el campo de imagen reduce antes de subir');

di('La portada lleva el campo nuevo',
  await p.evaluate(() => !! document.querySelector('[data-campo="imagen"][x-data^="campoImagen"]')));

/* ═══════════════ P12 — la marquesina ═════════════════════════════ */

t('P12 — la marquesina no repite');

await p.goto(`${B}/`, { waitUntil: 'networkidle2' });
const chips = await p.$$eval('.marquee-track .logo-chip', (n) => n.map((c) => ({
  nombre: c.querySelector('span:last-child')?.textContent.trim(),
  copia: c.getAttribute('aria-hidden') === 'true',
})));

const pasada = chips.filter((c) => ! c.copia).map((c) => c.nombre);
const repetidos = pasada.filter((n, i) => pasada.indexOf(n) !== i);

di('La marquesina se pinta', chips.length > 0, `${pasada.length} organizaciones`);
di('**Ningún nombre repetido dentro de una pasada**', repetidos.length === 0,
  repetidos.join(' · ') || 'ninguno');
di('Y son organizaciones, no las once pastillas de antes', pasada.length > 0 && pasada.length !== 11,
  pasada.slice(0, 3).join(' · '));

/* ═══════════════ P17, P20 y P6, en el panel ══════════════════════ */

t('P6, P17 y P20 — el panel');

await entrar('/admin/login', 'admin@ong-laravel.test', 'admin1234');
await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });

di('P20: el ajuste de cuándo invitar a evaluar',
  (await p.$('[name="evaluacion_invitacion_cuando"]')) !== null);
di('P6: el tope de fotografías', (await p.$('[name="evaluacion_max_fotos"]')) !== null);

await p.goto(`${B}/admin/plantillas`, { waitUntil: 'networkidle2' });
di('P20: la plantilla del correo está en el catálogo',
  (await texto()).includes('Invitación a evaluar la actividad'));

await entrar('/mi-cuenta/login', 'organizador@ong-laravel.test', 'organizador1234');
await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });

const idActividad = await p.evaluate(() => {
  const a = [...document.querySelectorAll('a')].find((x) => /participantes/.test(x.getAttribute('href') ?? ''));

  return a ? a.getAttribute('href').match(/actividades\/(\d+)/)?.[1] : null;
});

if (idActividad) {
  await p.goto(`${B}/mi-cuenta/actividades/${idActividad}/participantes`, { waitUntil: 'networkidle2' });
  const cabeceras = await p.$$eval('table.plist thead th', (n) => n.map((c) => c.textContent.trim()));
  di('P17: ya no hay columna «Estado»', ! cabeceras.some((c) => /estado/i.test(c)), cabeceras.join(' · '));
} else {
  console.log('     (ninguna actividad con participantes a la vista: P17 no se comprueba aquí)');
}

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
