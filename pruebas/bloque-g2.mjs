// Bloque G, segunda mitad: lo que no es crear-editar-borrar.
//
// Filtros, orden por columna, paginacion, acciones masivas, reordenar
// arrastrando y exportar. Todo en Chrome de verdad, porque son justo las cosas
// que por HTTP salen bien y en pantalla no.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { readFileSync, existsSync, rmSync, mkdirSync, readdirSync } from 'node:fs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = 'http://127.0.0.1:8123';
// Las capturas van al temporal del sistema salvo que se diga otra cosa:
// versionado, no puede apuntar al scratchpad de una sesión concreta.
const S = process.env.DPS_SALIDA ?? tmpdir();
const BAJADAS = `${S}/bajadas`;
const MYSQL = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(58)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

if (existsSync(BAJADAS)) rmSync(BAJADAS, { recursive: true, force: true });
mkdirSync(BAJADAS, { recursive: true });

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'], protocolTimeout: 30000 });
const errores = [];
const nativos = [];
const p = await nav.newPage();
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
p.on('pageerror', (e) => errores.push(String(e)));
// Un `confirm()` del navegador aqui es un fallo, no una casualidad: el proyecto
// pide dialogo propio. Se anota y se acepta para no dejar la pagina colgada.
p.on('dialog', async (d) => { nativos.push(d.message()); await d.accept(); });
await p.setViewport({ width: 1440, height: 1000 });

const cliente = await p.createCDPSession();
await cliente.send('Page.setDownloadBehavior', { behavior: 'allow', downloadPath: BAJADAS });

await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle0' });
await p.type('input[name=email]', 'admin@ong-laravel.test');
await p.type('input[name=password]', 'admin1234');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.click('button[type=submit]')]);

const filas = () => p.evaluate(() => [...document.querySelectorAll('tbody tr')].filter((f) => f.querySelector('td')).length);

/* ═════════════════ semilla: seis testimonios de prueba ═════════════════ */

sql("DELETE FROM testimonials WHERE autor LIKE 'ZZT%'");
for (let i = 1; i <= 6; i++) {
  sql(`INSERT INTO testimonials (autor, cargo, texto, orden, activo, created_at, updated_at) VALUES ('ZZT ${i}','cargo ${i}','texto ${i}',${i},1,UTC_TIMESTAMP(),UTC_TIMESTAMP())`);
}
const ids = sql("SELECT id FROM testimonials WHERE autor LIKE 'ZZT%' ORDER BY orden").split('\n').map((x) => x.trim());

/* ═════════════════════════ filtros y buscador ═════════════════════════ */

t('Filtros, orden y paginacion');

await p.goto(`${B}/admin/contenido/testimonios`, { waitUntil: 'networkidle0' });
const todas = await filas();
di('el listado sin filtros trae todo', todas >= 6, `${todas} filas`);

// El buscador tiene retardo y se envia solo.
await p.evaluate(() => { const e = document.querySelector('.panel-filtros [name=q]'); e.focus(); e.value = ''; });
await p.keyboard.type('ZZT');
await p.waitForNavigation({ waitUntil: 'networkidle0' });
di('el buscador filtra solo, sin pulsar nada', (await filas()) === 6, `${await filas()} filas`);
di('y lo escrito sigue en la caja', (await p.evaluate(() => document.querySelector('.panel-filtros [name=q]').value)) === 'ZZT');

// Apago dos para probar el selector de estado.
sql(`UPDATE testimonials SET activo=0 WHERE id IN (${ids[0]},${ids[1]})`);
await p.goto(`${B}/admin/contenido/testimonios?q=ZZT&estado=no`, { waitUntil: 'networkidle0' });
di('el filtro de estado deja solo los apagados', (await filas()) === 2, `${await filas()} filas`);
sql("UPDATE testimonials SET activo=1 WHERE autor LIKE 'ZZT%'");

// Ordenar por columna.
await p.goto(`${B}/admin/contenido/testimonios?q=ZZT`, { waitUntil: 'networkidle0' });
const hayColumna = await p.evaluate(() => !!document.querySelector('th a.col-orden'));
di('las columnas ordenables son enlaces', hayColumna);

if (hayColumna) {
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.evaluate(() => document.querySelector('th a.col-orden').click())]);
  di('ordenar conserva el filtro', p.url().includes('q=ZZT') && p.url().includes('orden='), p.url().split('?')[1] ?? '');
  const primeroAsc = await p.evaluate(() => document.querySelector('tbody tr td:nth-child(2)')?.textContent.trim());

  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.evaluate(() => document.querySelector('th a.col-orden').click())]);
  const primeroDesc = await p.evaluate(() => document.querySelector('tbody tr td:nth-child(2)')?.textContent.trim());
  di('volver a pulsar le da la vuelta', primeroAsc !== primeroDesc, `${primeroAsc} -> ${primeroDesc}`);
}

// Un orden inventado por la URL no ordena por el y no revienta.
await p.goto(`${B}/admin/contenido/testimonios?q=ZZT&orden=(select+1)&dir=asc`, { waitUntil: 'networkidle0' });
di('un orden inventado no rompe el listado', (await filas()) === 6, `${await filas()} filas`);

// Paginacion.
await p.goto(`${B}/admin/contenido/testimonios?q=ZZT&filas=15`, { waitUntil: 'networkidle0' });
di('se puede elegir cuantas filas por pagina', await p.evaluate(() => !!document.querySelector('.panel-filtros [name=filas]')));

/* ══════════════════════ acciones masivas ══════════════════════ */

t('Acciones masivas');

await p.goto(`${B}/admin/contenido/testimonios?q=ZZT`, { waitUntil: 'networkidle0' });
di('la barra de acciones esta escondida sin seleccion', await p.evaluate(() => {
  const barra = document.querySelector('.tabla-acciones');
  return !barra || barra.offsetParent === null;
}));

const marcar = async (cuantas) => {
  await p.evaluate((n) => {
    [...document.querySelectorAll('tbody input[name="ids[]"]')].slice(0, n).forEach((c) => {
      c.checked = true;
      c.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }, cuantas);
  await esperar(150);
};

await marcar(2);
di('al marcar dos aparece la barra', await p.evaluate(() => document.querySelector('.tabla-acciones')?.offsetParent !== null));
const cuenta = await p.evaluate(() => document.querySelector('.tabla-acciones-cuenta')?.textContent.trim() ?? '');
di('y dice cuantas hay', cuenta.includes('2 de'), cuenta);

const masiva = async (texto) => {
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle0' }),
    p.evaluate((x) => [...document.querySelectorAll('.tabla-acciones button')].find((b) => b.textContent.trim() === x)?.click(), texto),
  ]);
};

await masiva('Esconder');
const apagados = Number(sql("SELECT COUNT(*) FROM testimonials WHERE autor LIKE 'ZZT%' AND activo=0"));
di('«Esconder» apaga las dos marcadas', apagados === 2, `${apagados} apagados`);
const aviso = await p.evaluate(() => document.querySelector('.panel-flash')?.textContent.replace(/\s+/g, ' ').trim() ?? '');
di('y lo cuenta en el aviso', aviso.includes('2'), aviso.slice(0, 60));

await p.goto(`${B}/admin/contenido/testimonios?q=ZZT`, { waitUntil: 'networkidle0' });
await marcar(2);
await masiva('Mostrar en el sitio');
di('«Mostrar en el sitio» las vuelve a encender', sql("SELECT COUNT(*) FROM testimonials WHERE autor LIKE 'ZZT%' AND activo=0") === '0');

// La destructiva pregunta, y con el dialogo propio.
await p.goto(`${B}/admin/contenido/testimonios?q=ZZT`, { waitUntil: 'networkidle0' });
await marcar(3);
await p.evaluate(() => [...document.querySelectorAll('.tabla-acciones button')].find((b) => b.textContent.trim() === 'Eliminar')?.click());
await esperar(500);
di('la accion masiva destructiva pregunta antes', await p.evaluate(() => document.querySelector('.dialogo')?.offsetParent !== null));
di('y con el dialogo propio, no el del navegador', nativos.length === 0, nativos.join(' | '));
const textoDialogo = await p.evaluate(() => document.querySelector('.dialogo-texto')?.textContent ?? '');
di('el dialogo dice cuantas son', textoDialogo.includes('3 de'), textoDialogo.slice(0, 70));
di('el foco arranca en «Cancelar»', await p.evaluate(() => document.activeElement?.textContent.trim() === 'Cancelar'));

await p.evaluate(() => [...document.querySelectorAll('.dialogo button')].find((b) => b.textContent.trim() === 'Cancelar')?.click());
await esperar(300);
di('cancelar no borra nada', sql("SELECT COUNT(*) FROM testimonials WHERE autor LIKE 'ZZT%' AND deleted_at IS NOT NULL") === '0');
di('y el dialogo se cierra', await p.evaluate(() => {
  const d = document.querySelector('.dialogo-fondo');
  return !d || d.offsetParent === null;
}));

await marcar(3);
await p.evaluate(() => [...document.querySelectorAll('.tabla-acciones button')].find((b) => b.textContent.trim() === 'Eliminar')?.click());
await esperar(500);
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle0' }),
  p.evaluate(() => document.querySelector('.dialogo button[type=submit]')?.click()),
]);
const borrados = Number(sql("SELECT COUNT(*) FROM testimonials WHERE autor LIKE 'ZZT%' AND deleted_at IS NOT NULL"));
di('aceptar elimina las marcadas, en blando', borrados === 3, `${borrados} eliminados`);

await p.goto(`${B}/admin/contenido/testimonios?q=ZZT&papelera=eliminados`, { waitUntil: 'networkidle0' });
await marcar(3);
await masiva('Restaurar');
di('y la papelera las devuelve todas de una vez', sql("SELECT COUNT(*) FROM testimonials WHERE autor LIKE 'ZZT%' AND deleted_at IS NOT NULL") === '0');

/* ══════════════════════ reordenar arrastrando ══════════════════════ */

t('Reordenar arrastrando');

for (let i = 0; i < ids.length; i++) sql(`UPDATE testimonials SET orden=${100 + i} WHERE id=${ids[i]}`);

await p.goto(`${B}/admin/contenido/testimonios`, { waitUntil: 'networkidle0' });
di('sin filtros, las filas se pueden arrastrar', await p.evaluate(() => !!document.querySelector('tbody tr[draggable=true]')));

await p.goto(`${B}/admin/contenido/testimonios?q=ZZT`, { waitUntil: 'networkidle0' });
di('con filtros no, y lo explica', await p.evaluate(() => ! document.querySelector('tbody tr[draggable=true]')
    && document.body.textContent.includes('quita los filtros')));

await p.goto(`${B}/admin/contenido/testimonios`, { waitUntil: 'networkidle0' });
const antes = await p.evaluate(() => [...document.querySelectorAll('tbody tr[data-fila]')].map((f) => f.dataset.fila));

/*
 * El arrastre HTML5 no se puede hacer con el raton desde CDP, asi que se
 * disparan los mismos eventos que dispara el navegador, con su DataTransfer.
 */
const arrastrar = async (desde, hasta) => {
  await p.evaluate((a, b) => {
    const cuerpo = document.querySelector('tbody');
    const origen = cuerpo.querySelector(`[data-fila="${a}"]`);
    const destino = cuerpo.querySelector(`[data-fila="${b}"]`);
    const dt = new DataTransfer();
    const caja = destino.getBoundingClientRect();

    origen.dispatchEvent(new DragEvent('dragstart', { bubbles: true, dataTransfer: dt }));
    destino.dispatchEvent(new DragEvent('dragover', { bubbles: true, cancelable: true, dataTransfer: dt, clientY: caja.top + 2 }));
    destino.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: dt }));
  }, desde, hasta);
  await esperar(800);
};

const ultima = antes[antes.length - 1];
await arrastrar(ultima, antes[0]);

const enPantalla = await p.evaluate(() => [...document.querySelectorAll('tbody tr[data-fila]')].map((f) => f.dataset.fila));
di('la fila se mueve en pantalla', enPantalla[0] === ultima, enPantalla.slice(0, 3).join(','));

await p.goto(`${B}/admin/contenido/testimonios`, { waitUntil: 'networkidle0' });
const trasRecargar = await p.evaluate(() => [...document.querySelectorAll('tbody tr[data-fila]')].map((f) => f.dataset.fila));
di('y el orden nuevo aguanta la recarga', trasRecargar[0] === ultima, trasRecargar.slice(0, 3).join(','));

const enHome = await nav.newPage();
await enHome.goto(B, { waitUntil: 'networkidle0' });
const vocesHome = await enHome.evaluate(() => document.body.textContent.replace(/\s+/g, ' '));
di('el home sigue en pie tras reordenar', vocesHome.length > 1000, `${vocesHome.length} caracteres`);
await enHome.close();

// Un id ajeno colado en la lista de orden no debe reventar nada.
const respuesta = await p.evaluate(async (url, token) => {
  const cuerpo = new FormData();
  cuerpo.append('orden[]', '999999');
  const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, body: cuerpo });
  return r.status;
}, `${B}/admin/contenido/testimonios/orden`, await p.evaluate(() => document.querySelector('meta[name=csrf-token]').content));
di('un id que no existe no revienta el reordenar', respuesta < 500, `respondio ${respuesta}`);

/* ══════════════════════ exportar ══════════════════════ */

t('Exportar');

await p.goto(`${B}/admin/contenido/testimonios?q=ZZT`, { waitUntil: 'networkidle0' });

for (const formato of ['csv', 'xlsx']) {
  di(`el listado ofrece bajar ${formato}`, await p.evaluate((f) => [...document.querySelectorAll('a')].some((a) => a.href.includes(`formato=${f}`)), formato));

  /*
   * Se pide desde la propia pagina, con su sesion. Bajarlo a disco desde
   * headless depende de una orden de CDP que cambia entre versiones, y lo que
   * importa comprobar es lo que manda el servidor: que llega como fichero
   * adjunto, que no viene vacio y que trae lo que estaba filtrado.
   */
  const r = await p.evaluate(async (url) => {
    const res = await fetch(url, { credentials: 'same-origin' });
    const texto = await res.text();

    return {
      estado: res.status,
      adjunto: res.headers.get('content-disposition') ?? '',
      largo: texto.length,
      texto: texto.slice(0, 4000),
    };
  }, `${B}/admin/contenido/testimonios/exportar?formato=${formato}&q=ZZT`);

  di(`exportar a ${formato} responde un fichero adjunto`, r.estado === 200 && r.adjunto.includes('attachment') && r.largo > 100,
     `${r.estado} - ${r.adjunto.slice(0, 46)} - ${r.largo} bytes`);

  if (formato === 'csv') {
    const veces = (r.texto.match(/ZZT/g) ?? []).length;
    di('el csv trae solo lo filtrado', veces === 6, `${veces} veces ZZT`);
    di('y usa punto y coma, que es lo que abre Excel en es-CL', r.texto.split(String.fromCharCode(10))[0].includes(';'));
  }
}

/* ══════════════════════ el aviso flash ══════════════════════ */

t('Avisos');

await p.goto(`${B}/admin/contenido/testimonios?q=ZZT`, { waitUntil: 'networkidle0' });
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle0' }),
  p.evaluate(() => [...document.querySelectorAll('tbody button')].find((b) => b.textContent.trim() === 'Esconder')?.click()),
]);
di('una accion de fila deja su aviso', await p.evaluate(() => document.querySelector('.panel-flash')?.offsetParent !== null));
di('y el aviso se puede cerrar', await p.evaluate(async () => {
  document.querySelector('.panel-flash-cerrar')?.click();
  await new Promise((r) => setTimeout(r, 500));
  const f = document.querySelector('.panel-flash');
  return !f || f.offsetParent === null;
}));

await p.screenshot({ path: `${S}/g-masivas.png`, fullPage: true });

sql("DELETE FROM testimonials WHERE autor LIKE 'ZZT%'");

console.log('');
console.log(`dialogos nativos: ${nativos.length ? nativos.join(' | ') : 'ninguno'}`);
console.log(`errores de consola: ${errores.length ? errores.slice(0, 4).join(' | ') : 'ninguno'}`);
if (errores.length) mal++;

console.log('');
console.log('='.repeat(62));
console.log(`  ${ok} bien, ${mal} mal`);
console.log('='.repeat(62));

await nav.close();
process.exit(mal === 0 ? 0 : 1);
