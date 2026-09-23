// Cupos e inscripción previa. En Chrome, publicando de verdad.
//
// EL BUG: con «¿Requiere inscripción previa? → No», el campo de cupos se
// escondía con `x-show` y seguía viajando con su 80 de ejemplo. La actividad
// se guardaba con 80 cupos y la ficha pública enseñaba «Cupos disponibles 80»
// al lado de «No es necesario inscripción previa». Lo que la persona hubiera
// escrito en «Cantidad de participantes estimados» se guardaba en otra
// columna que no sale en ninguna pantalla.
//
// Se comprueba lo que viaja en el POST, lo que queda en la base y lo que
// enseña la ficha, por los dos sitios donde se escriben los cupos: el wizard
// y el editor de mi-cuenta.
//
//   node pruebas/cupos.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '-e', q]).toString().trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1440, height: 900 });

// Lo que lleva de verdad cada POST, por nombre de campo.
let enviado = {};
p.on('request', (r) => {
    if (r.method() !== 'POST') return;
    enviado = {};
    for (const [, n, v] of (r.postData() ?? '').matchAll(/name="([^"]+)"\r\n\r\n([^\r]*)/g)) enviado[n] = v;
});

await p.goto(`${B}/mi-cuenta/login`);
await p.type('[name="email"]', ORG);
await p.type('[name="password"]', CLAVE_ORG);
await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

const W = '[x-data^="wizard"]';
const valores = (sel) => p.$$eval(sel + ' option', (o) => o.map((x) => x.value).filter(Boolean));
const fila = (titulo) => {
    const [id, insc, total, disp, estimados] = sql(
        `select id, inscripcion_habilitada, ifnull(cupos_totales,'null'), ifnull(cupos_disponibles,'null'), ifnull(participantes_estimados,'null') from activities where titulo='${titulo}'`,
    ).split('\t');
    return { id, insc, total, disp, estimados };
};
const reemplazar = async (sel, texto) => {
    await p.click(sel);
    await p.keyboard.down('Control'); await p.keyboard.press('KeyA'); await p.keyboard.up('Control');
    await p.keyboard.type(texto);
};
const pulsarNo = (sel) => p.evaluate((s) => {
    [...document.querySelectorAll('button.chip')].find((b) => b.getAttribute('x-on:click') === s).click();
}, sel);
const cuposEnFicha = async (id) => {
    await p.goto(`${B}/activity/${id}/x`, { waitUntil: 'networkidle2' });
    return p.evaluate(() => {
        const r = [...document.querySelectorAll('.helper')].find((e) => e.textContent.trim() === 'Cupos disponibles');
        return r ? r.nextElementSibling.textContent.trim() : null;
    });
};

async function publicar(titulo, preparar) {
    await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
    await p.evaluate((W) => { Alpine.$data(document.querySelector(W)).paso = 4; }, W);
    await esperar(300);
    await p.type('input[name="titulo"]', titulo);
    await p.type('textarea[name="descripcion"]', 'Descripción de la prueba de cupos.');
    await p.type('input[name="fecha_inicio"]', '04122026');
    await p.select('select[name="region_id"]', (await valores('select[name="region_id"]'))[0]);
    await p.waitForFunction(() => [...document.querySelectorAll('select[name="commune_id"] option')].some((o) => o.value));
    await p.select('select[name="commune_id"]', (await valores('select[name="commune_id"]'))[0]);
    await p.type('input[name="direccion"]', 'Calle Falsa 123');
    await p.evaluate(() => {
        for (const c of ['temas', 'caracteristicas', 'publicos']) document.querySelector(`[data-campo="${c}"] button.chip`).click();
    });
    await preparar();
    await Promise.all([p.waitForNavigation({ timeout: 20000 }), p.click('button[type="submit"]')]);
}

const sello = Date.now();

/* ══════════════════════════════════════════════════════════════════ */
t('1 · Wizard, con inscripción y 100 cupos');

const conInsc = `Prueba cupos con inscripción ${sello}`;
await publicar(conInsc, () => reemplazar('input[name="cupos_totales"]', '100'));
const a = fila(conInsc);

di('el POST lleva 100', enviado.cupos_totales === '100', enviado.cupos_totales);
di('se guarda 100 de total', a.total === '100', a.total);
di('y 100 disponibles', a.disp === '100', a.disp);
di('la ficha enseña 100', await cuposEnFicha(a.id) === '100');

/* ══════════════════════════════════════════════════════════════════ */
t('1b · Wizard, con inscripción y el campo sin tocar');

// Antes venía relleno con 80: quien no lo tocaba publicaba con 80 cupos.
const sinTocar = `Prueba cupos sin tocar ${sello}`;
let campo = null;
await publicar(sinTocar, async () => {
    campo = await p.$eval('input[name="cupos_totales"]', (i) => ({ valor: i.value, marcador: i.placeholder }));
});
const c = fila(sinTocar);

di('el campo sale vacío', campo.valor === '', JSON.stringify(campo.valor));
di('con «Ej. 80» de marcador', campo.marcador === 'Ej. 80', campo.marcador);
di('se guarda con inscripción', c.insc === '1', c.insc);
di('sin límite de cupos (antes: 80)', c.total === 'null' && c.disp === 'null', `${c.total}/${c.disp}`);

/* ══════════════════════════════════════════════════════════════════ */
t('2 · Wizard, SIN inscripción y 100 participantes estimados — el caso del reporte');

const sinInsc = `Prueba cupos sin inscripción ${sello}`;
await publicar(sinInsc, async () => {
    await p.type('input[name="participantes_estimados"]', '100');
    await pulsarNo('insc = false');
});
const b = fila(sinInsc);

di('el POST NO lleva cupos', ! ('cupos_totales' in enviado), String(enviado.cupos_totales));
di('se guarda sin inscripción', b.insc === '0');
di('sin total de cupos (antes: 80)', b.total === 'null', b.total);
di('sin cupos disponibles (antes: 80)', b.disp === 'null', b.disp);
di('los estimados se guardan donde van', b.estimados === '100', b.estimados);
di('la ficha NO enseña cupos', await cuposEnFicha(b.id) === null);

/* ══════════════════════════════════════════════════════════════════ */
t('3 · Editor de mi-cuenta: apagar y encender la inscripción');

await p.goto(`${B}/mi-cuenta/actividades/${a.id}/editar`, { waitUntil: 'networkidle2' });
await pulsarNo('insc = false');
await Promise.all([p.waitForNavigation(), p.click('form[action$="/' + a.id + '"] button[type="submit"]')]);
const a2 = fila(conInsc);

di('al apagarla, el POST no lleva cupos', ! ('cupos_disponibles' in enviado));
di('se queda sin cupos', a2.total === 'null' && a2.disp === 'null', `${a2.total}/${a2.disp}`);
di('la ficha ya no los enseña', await cuposEnFicha(a.id) === null);

await p.goto(`${B}/mi-cuenta/actividades/${a.id}/editar`, { waitUntil: 'networkidle2' });
await p.evaluate(() => {
    [...document.querySelectorAll('button.chip')].find((b) => b.getAttribute('x-on:click') === 'insc = true').click();
});
await esperar(200);
await reemplazar('input[name="cupos_disponibles"]', '30');
await Promise.all([p.waitForNavigation(), p.click('form[action$="/' + a.id + '"] button[type="submit"]')]);
const a3 = fila(conInsc);

di('al encenderla con 30, se guardan 30 disponibles', a3.disp === '30', a3.disp);
di('y el total parte de ahí', a3.total === '30', a3.total);
di('la ficha enseña 30', await cuposEnFicha(a.id) === '30');

/* ══════════════════════════════════════════════════════════════════ */
t('4 · Sin errores de JavaScript');
di('ninguno', errores.length === 0, errores.join(' | '));

sql(`delete from activities where titulo in ('${conInsc}', '${sinInsc}')`);
await nav.close();

console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
