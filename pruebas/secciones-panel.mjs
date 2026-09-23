// Punto 7 del 23/09 — la barra del panel del organizador, agrupada bajo
// «Secciones» y más visible.
//
// Se recorren las pantallas de «Mi cuenta»: en todas tiene que estar el
// bloque con su título y las secciones (Mis actividades, Inscritos,
// Evaluaciones y el kit si está configurado), con la de la pantalla marcada.
// Y «Inscritos», que antes no existía como sección, tiene que llevar a algo:
// sus actividades con inscripción y cuántos inscritos tiene cada una.
//
// No escribe nada.
//
//   node pruebas/secciones-panel.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '--default-character-set=utf8mb4', '-e', q]).toString().trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1440, height: 900 });

await p.goto(`${B}/mi-cuenta/login`);
await p.type('[name="email"]', ORG);
await p.type('[name="password"]', CLAVE_ORG);
await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

const kit = sql(`select ifnull(valor, '') from settings where clave = 'kit_difusion_url'`);
const orgId = sql(`select o.id from organizations o join users u on u.id = o.user_id where u.email = '${ORG}'`);
const unaActividad = sql(`select id from activities where organization_id = ${orgId} and inscripcion_habilitada = 1 order by updated_at desc limit 1`);

const barra = () => p.evaluate(() => {
    const n = document.querySelector('nav.secciones');
    if (! n) return null;
    return {
        titulo: n.querySelector('.secciones-titulo')?.textContent.trim(),
        enlaces: [...n.querySelectorAll('a.seccion')].map((a) => a.textContent.trim()),
        activa: [...n.querySelectorAll('a.seccion--activa')].map((a) => a.textContent.trim()),
        alto: n.getBoundingClientRect().height,
        fondo: getComputedStyle(n).backgroundColor,
    };
});

t('1 · En todas las pantallas de «Mi cuenta»');

const pantallas = [
    ['Mis actividades', '/mi-cuenta/actividades', 'Mis actividades'],
    ['Editor', `/mi-cuenta/actividades/${unaActividad}/editar`, 'Mis actividades'],
    ['Inscritos', '/mi-cuenta/inscritos', 'Inscritos'],
    ['Participantes de una actividad', `/mi-cuenta/actividades/${unaActividad}/participantes`, 'Inscritos'],
    ['Evaluaciones', '/mi-cuenta/evaluaciones', 'Evaluaciones'],
    ['Perfil', '/mi-cuenta/perfil', null],
];

const esperadas = ['Mis actividades', 'Inscritos', 'Evaluaciones', ...(kit ? ['Kit de difusión'] : [])];

for (const [nombre, ruta, activa] of pantallas) {
    await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
    const b = await barra();
    di(`${nombre}: bloque «Secciones» visible`, b?.titulo === 'Secciones' && b.alto > 0);
    di(`${nombre}: con las secciones`, JSON.stringify(b?.enlaces) === JSON.stringify(esperadas), b?.enlaces.join(' · '));
    di(`${nombre}: marcada ${activa ?? 'ninguna'}`, JSON.stringify(b?.activa) === JSON.stringify(activa ? [activa] : []), b?.activa.join(' · '));
}

di('se distingue del fondo (caja blanca)', (await barra())?.fondo === 'rgb(255, 255, 255)');
di('perfil y cerrar sesión siguen a mano', await p.evaluate(() =>
    !! [...document.querySelectorAll('a.crumb')].find((a) => a.textContent.trim() === 'Mi perfil')
    && !! [...document.querySelectorAll('button.crumb')].find((b) => b.textContent.trim() === 'Cerrar sesión')));

if (kit) {
    di('el kit se abre en otra pestaña', await p.evaluate(() =>
        [...document.querySelectorAll('a.seccion')].find((a) => a.textContent.trim() === 'Kit de difusión')?.target === '_blank'));
}

t('2 · «Inscritos» lleva a algo');

await p.goto(`${B}/mi-cuenta/inscritos`, { waitUntil: 'networkidle2' });
const filas = await p.$$eval('[data-inscritos-actividad]', (n) => n.map((a) => ({
    href: a.getAttribute('href'),
    n: Number(a.querySelector('div[style*="font-size:24px"]').textContent.trim()),
})));

const enBase = sql(`select a.id, (select count(*) from registrations r where r.activity_id = a.id and r.estado != 'cancelado') from activities a
    where a.organization_id = ${orgId} and a.deleted_at is null
    and (a.inscripcion_habilitada = 1 or exists (select 1 from registrations r where r.activity_id = a.id))`)
    .split('\n').filter(Boolean).map((l) => l.split('\t'));

di('una fila por actividad con inscripción', filas.length === enBase.length, `${filas.length} / ${enBase.length}`);
di('cada una con sus inscritos, como en la base', enBase.every(([id, n]) =>
    filas.some((f) => f.href.includes(`/actividades/${id}/participantes`) && f.n === Number(n))));

await Promise.all([p.waitForNavigation(), p.click('[data-inscritos-actividad]')]);
di('y al pulsarla abre su lista de participantes', /\/actividades\/\d+\/participantes$/.test(new URL(p.url()).pathname), p.url());

t('3 · Sólo sus actividades');

const ajena = sql(`select id from activities where organization_id != ${orgId} and inscripcion_habilitada = 1 limit 1`);
await p.goto(`${B}/mi-cuenta/inscritos`, { waitUntil: 'networkidle2' });
di('no aparece ninguna de otra organización', ! await p.evaluate((id) =>
    !! document.querySelector(`[data-inscritos-actividad][href*="/actividades/${id}/"]`), ajena));

t('4 · En el teléfono');

await p.setViewport({ width: 390, height: 844 });
for (const ruta of ['/mi-cuenta/actividades', '/mi-cuenta/inscritos']) {
    await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
    const m = await p.evaluate(() => {
        const n = document.querySelector('nav.secciones').getBoundingClientRect();
        return { cabe: n.left >= 0 && n.right <= window.innerWidth, sinScroll: document.documentElement.scrollWidth <= window.innerWidth };
    });
    di(`${ruta}: la barra cabe y la página no desborda`, m.cabe && m.sinScroll);
}

t('5 · Sin errores de JavaScript');
di('ninguno', errores.length === 0, errores.join(' | '));

await nav.close();
console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
