// Punto 4 del 23/09 — «Ver mi actividad» en la pantalla de «¡Gracias por sumar…!».
//
// Cuando la actividad sale publicada en el acto (organizador ya verificado),
// la pantalla final lleva un botón a su ficha pública. En revisión no: la
// ficha todavía no se enseña al público.
//
// Se lee de la base qué actividad de la organización sembrada está en cada
// estado, así que no escribe nada.
//
//   node pruebas/ver-mi-actividad.mjs
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

const deLaOrg = (estado) => sql(
    `select a.slug, a.titulo from activities a join organizations o on o.id = a.organization_id join users u on u.id = o.user_id
     where u.email = '${ORG}' and a.estado = '${estado}' order by a.id desc limit 1`,
).split('\t');

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1440, height: 900 });

await p.goto(`${B}/mi-cuenta/login`);
await p.type('[name="email"]', ORG);
await p.type('[name="password"]', CLAVE_ORG);
await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

const boton = () => p.evaluate(() => {
    const a = document.querySelector('[data-ver-actividad]');
    return a ? { texto: a.textContent.trim(), href: a.getAttribute('href'), alto: a.getBoundingClientRect().height } : null;
});

t('1 · Publicada en el acto: lleva el botón');

const [slugPub, tituloPub] = deLaOrg('publicada');
await p.goto(`${B}/publicar-actividad/${slugPub}/listo`, { waitUntil: 'networkidle2' });
const b = await boton();

di('el botón está', b !== null);
di('dice «Ver mi actividad»', b?.texto === 'Ver mi actividad', b?.texto);
di('y se ve', (b?.alto ?? 0) > 0, `${Math.round(b?.alto ?? 0)} px`);
di('apunta a la ficha pública', /\/activity\/\d+\//.test(b?.href ?? ''), b?.href);

await Promise.all([p.waitForNavigation(), p.click('[data-ver-actividad]')]);
const h1 = await p.evaluate(() => document.querySelector('h1')?.textContent.trim());
di('y la ficha abre con su título', h1 === tituloPub, h1);

t('2 · En revisión: sin botón');

const [slugRev] = deLaOrg('revision');
await p.goto(`${B}/publicar-actividad/${slugRev}/listo`, { waitUntil: 'networkidle2' });
di('no hay botón', await boton() === null);

t('3 · En el teléfono');

await p.setViewport({ width: 390, height: 844 });
await p.goto(`${B}/publicar-actividad/${slugPub}/listo`, { waitUntil: 'networkidle2' });
const movil = await p.evaluate(() => {
    const a = document.querySelector('[data-ver-actividad]').getBoundingClientRect();
    return { dentro: a.left >= 0 && a.right <= window.innerWidth, scroll: document.documentElement.scrollWidth <= window.innerWidth };
});
di('el botón cabe', movil.dentro);
di('sin desbordar la página', movil.scroll);

t('4 · Sin errores de JavaScript');
di('ninguno', errores.length === 0, errores.join(' | '));

await nav.close();
console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
