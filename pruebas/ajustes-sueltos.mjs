// Tanda del 05/10, punto 8 — los ajustes sueltos.
//
//   8a  «¿No es la que buscas? Haz clic aquí» en el buscador de organizaciones
//       (el clic de verdad lo cubren organizaciones-wizard y registro-organizacion).
//   8b  El buscador de Admin → Actividades encuentra por organización.
//   8c  La imagen de difusión: la descripción son SIEMPRE los primeros 120
//       caracteres, con titular corto o largo. Se mira lo que se dibujó.
//   8d  Admin → Revisar actividad tiene «Ver actividad», también sin publicar.
//   8e  Mis actividades: «QR evaluación» en las publicadas, que descarga el PNG.
//
// Toca el título y la descripción de una actividad sembrada y los deja como
// estaban.
//
//   node pruebas/ajustes-sueltos.mjs      (desde la raíz del repo)
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '--default-character-set=utf8mb4', '-e', q]).toString().trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const orgId = sql(`select o.id from organizations o join users u on u.id = o.user_id where u.email = '${ORG}'`);
const orgNombre = sql(`select nombre from organizations where id = ${orgId}`);
const publicada = sql(`select id from activities where organization_id = ${orgId} and estado = 'publicada' and deleted_at is null order by id limit 1`);
const sinPublicar = sql(`select id from activities where organization_id = ${orgId} and estado <> 'publicada' and deleted_at is null order by id limit 1`);
const [tituloAntes, descAntes] = sql(`select to_base64(titulo), to_base64(descripcion) from activities where id = ${publicada}`).split('\t');

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];

const entrar = async (ctx, puerta, correo, clave) => {
  const p = await ctx.newPage();
  p.on('pageerror', (e) => errores.push(String(e)));
  await p.setViewport({ width: 1440, height: 900 });
  await p.goto(`${B}/${puerta}/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', correo);
  await p.type('input[type="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
  return p;
};

try {
  t('8a · El buscador de organizaciones');
  const anon = await nav.createBrowserContext();
  const q0 = await anon.newPage();
  await q0.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });
  const html = await q0.content();
  di('dice «¿No es la que buscas? Haz clic aquí»', html.includes('¿No es la que buscas? Haz clic aquí'));
  di('y ya no «No es ésta»', ! html.includes('No es ésta'));
  await anon.close();

  const adminCtx = await nav.createBrowserContext();
  const a = await entrar(adminCtx, 'admin', ADMIN, CLAVE_ADMIN);

  t('8b · Admin → Actividades busca por organización');
  await a.goto(`${B}/admin/actividades?q=${encodeURIComponent(orgNombre)}`, { waitUntil: 'networkidle2' });
  const ids = await a.evaluate(() => [...document.querySelectorAll('table.tabla tbody tr')].map((tr) => tr.querySelector('td')?.innerText.trim()).filter(Boolean));
  const deLaOrg = sql(`select count(*) from activities where organization_id = ${orgId} and deleted_at is null`);
  di('**por el nombre de la organización salen sus actividades**', ids.length > 0 && ids.includes(publicada), `${ids.length} fila(s), ${deLaOrg} de la organización`);
  const ajenas = ids.filter((id) => sql(`select organization_id from activities where id = ${+id}`) !== orgId && ! sql(`select titulo from activities where id = ${+id}`).includes(orgNombre));
  di('y sólo las suyas', ajenas.length === 0, ajenas.join(',') || '');
  const palabra = Buffer.from(tituloAntes, 'base64').toString('utf8').split(' ')[0];
  await a.goto(`${B}/admin/actividades?q=${encodeURIComponent(palabra)}`, { waitUntil: 'networkidle2' });
  di('por el nombre de la actividad sigue funcionando', (await a.evaluate(() => document.querySelectorAll('table.tabla tbody tr').length)) > 0, palabra);
  // Hay dos `q` en la pantalla: el del listado y el buscador general del panel.
  di('el buscador lo dice en su texto', await a.evaluate(() => [...document.querySelectorAll('input[name="q"]')].some((i) => i.placeholder === 'Buscar por actividad u organización…')));

  t('8d · Admin → Revisar actividad: «Ver actividad»');
  for (const [id, cual] of [[publicada, 'publicada'], [sinPublicar, 'sin publicar']]) {
    await a.goto(`${B}/admin/actividades/${id}`, { waitUntil: 'networkidle2' });
    const href = await a.$eval('[data-ver-actividad]', (el) => el.href).catch(() => null);
    di(`${cual}: el botón está y lleva a la ficha pública`, !! href && /\/activity\/\d+\//.test(href), href ?? '');
    const estado = href ? await a.evaluate(async (u) => (await fetch(u)).status, href) : 0;
    di(`${cual}: la ficha abre para el panel`, estado === 200, `${estado}`);
  }
  await adminCtx.close();

  const orgCtx = await nav.createBrowserContext();
  const o = await entrar(orgCtx, 'mi-cuenta', ORG, CLAVE_ORG);

  t('8e · Mis actividades: «QR evaluación»');
  await o.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
  const qrs = await o.evaluate(() => [...document.querySelectorAll('[data-qr-evaluacion]')].map((a) => ({ href: a.getAttribute('href'), texto: a.innerText.trim(), descarga: a.hasAttribute('data-descarga'), icono: !! a.querySelector('svg') })));
  const publicadas = sql(`select count(*) from activities where organization_id = ${orgId} and estado = 'publicada' and deleted_at is null`);
  di('**uno por cada actividad publicada**, y sólo en ésas', qrs.length === +publicadas, `${qrs.length} de ${publicadas}`);
  di('rotulado «QR evaluación», con icono', qrs.length > 0 && qrs.every((q) => q.texto === 'QR evaluación' && q.icono));
  di('marcado como descarga, para soltarse al terminar', qrs.every((q) => q.descarga));
  const propio = qrs.find((q) => q.href.includes(`/actividades/${publicada}/qr.png`));
  const res = propio ? await o.evaluate(async (u) => { const r = await fetch(u); return `${r.status} ${r.headers.get('content-type')}`; }, propio.href) : '';
  di('**descarga el PNG del QR de esa actividad**', res.startsWith('200 image/png'), res);

  t('8c · Imagen de difusión: siempre 120 caracteres de descripción');
  const DESC = 'Una jornada abierta para conocer el oficio de la cestería, con talleres para niñas y niños, música en vivo, '
    + 'feria de productores locales y una conversación final con las artesanas del barrio sobre cómo se transmite el oficio.';
  const esperada = Array.from(DESC).slice(0, 120).join('').trimEnd() + '…';
  const dibujar = async (titulo) => {
    sql(`update activities set titulo = '${titulo}', descripcion = '${DESC}' where id = ${publicada}`);
    await o.goto(`${B}/mi-cuenta/actividades/${publicada}/difusion`, { waitUntil: 'networkidle2' });
    await o.waitForFunction(() => ['lista', 'error'].includes(Alpine.$data(document.querySelector('.difusion'))?.estado), { timeout: 20000 });
    const textos = await o.evaluate(() => JSON.parse(JSON.stringify(Alpine.$data(document.querySelector('.difusion')).dibujo?.textos ?? [])));
    const desc = textos.filter((x) => x.y > 668 && x.y < 846 && x.color === '#33363a' && x.fuente.includes('Inter'));
    const tit = textos.filter((x) => x.y > 668 && x.y < 846 && x.color === '#e67824');
    return { desc: desc.map((x) => x.t).join(' '), lineasTitulo: tit.length, bajo: Math.max(...desc.map((x) => x.y)) };
  };
  const corto = await dibujar('Cestería');
  const largo = await dibujar('Jornada de cestería tradicional con talleres familiares y feria de productores');
  di('con titular corto, una línea de titular', corto.lineasTitulo === 1, `${corto.lineasTitulo}`);
  di('con titular largo, dos', largo.lineasTitulo === 2, `${largo.lineasTitulo}`);
  di('**la descripción son los primeros 120 caracteres**', corto.desc === esperada, `«${corto.desc}»`);
  di('**y no cambia con el largo del titular**', largo.desc === corto.desc, `«${largo.desc}»`);
  di('sin salirse del hueco del texto', largo.bajo < 846 && corto.bajo < 846, `${corto.bajo} / ${largo.bajo}`);
  await orgCtx.close();

  t('Consola');
  di('sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  sql(`update activities set titulo = from_base64('${tituloAntes}'), descripcion = from_base64('${descAntes}') where id = ${publicada}`);
  await nav.close();
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
