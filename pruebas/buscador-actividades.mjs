// Punto 5 del 23/09 — buscador de texto en /actividades.
//
// Un campo libre con lupa que filtra por nombre de actividad o por nombre de
// organización. El resto de filtros y el calendario siguen como estaban, y el
// texto viaja con ellos.
//
// Los recuentos se comparan con la base, no con un número fijo: así la prueba
// vale con los datos que haya. No escribe nada.
//
//   node pruebas/buscador-actividades.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '--default-character-set=utf8mb4', '-e', q]).toString().trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

// Lo que debería salir, contado en la base con la misma regla.
const PUBLICADAS = `from activities a join organizations o on o.id = a.organization_id
    where a.estado = 'publicada' and a.published_at is not null and a.deleted_at is null`;
const cuantas = (texto) => Number(sql(`select count(*) ${PUBLICADAS} and (a.titulo like '%${texto}%' or o.nombre like '%${texto}%')`));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1440, height: 900 });

// El recuento de la cabecera: «N actividades publicadas».
const total = () => p.evaluate(() => Number((document.querySelector('h1 + p')?.textContent.match(/\d+/) ?? ['-1'])[0]));
const titulos = () => p.$$eval('a[href*="/activity/"] h3, a[href*="/activity/"] .card-titulo', (n) => n.map((x) => x.textContent.trim()));
const buscar = async (texto) => {
    await p.goto(`${B}/actividades`, { waitUntil: 'networkidle2' });
    await p.type('#f-q', texto);
    await Promise.all([p.waitForNavigation(), p.keyboard.press('Enter')]);
};

// Un título y una organización que existan de verdad.
const [titulo, org] = sql(`select a.titulo, o.nombre ${PUBLICADAS} order by a.id desc limit 1`).split('\t');
const trozo = titulo.split(' ').slice(0, 2).join(' ');

t('1 · El campo');

await p.goto(`${B}/actividades`, { waitUntil: 'networkidle2' });
const campo = await p.evaluate(() => {
    const i = document.querySelector('#f-q');
    const lupa = i?.parentElement.querySelector('svg');
    return i ? { tipo: i.type, marcador: i.placeholder, lupa: !! lupa && lupa.getBoundingClientRect().width > 0, alto: i.getBoundingClientRect().height } : null;
});
di('está, y se ve', (campo?.alto ?? 0) > 0);
di('con lupa', campo?.lupa === true);
di('dice qué busca', /actividad/i.test(campo?.marcador) && /organización/i.test(campo?.marcador), campo?.marcador);

t('2 · Por nombre de actividad');

await buscar(trozo);
di(`«${trozo}»: tantas como en la base`, await total() === cuantas(trozo), `${await total()} / ${cuantas(trozo)}`);
di('el campo conserva lo buscado', await p.$eval('#f-q', (i) => i.value) === trozo);
di('la URL lleva q', new URL(p.url()).searchParams.get('q') === trozo);

t('3 · Por nombre de organización');

await buscar(org);
di(`«${org}»: tantas como en la base`, await total() === cuantas(org), `${await total()} / ${cuantas(org)}`);
di('y hay alguna', await total() > 0);

// Sin tildes ni mayúsculas, como escribe la gente.
const plano = org.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
await buscar(plano);
di(`sin tildes ni mayúsculas («${plano}») encuentra lo mismo`, await total() === cuantas(org), String(await total()));

t('4 · Lo que no debe encontrar');

await buscar('zzqxw-no-existe');
di('un texto sin coincidencias da cero', await total() === 0);
di('y lo dice', (await p.evaluate(() => document.body.innerText)).includes('No encontramos actividades'));

await buscar('%');
di('«%» no es un comodín', await total() === Number(sql(`select count(*) ${PUBLICADAS} and (a.titulo like '%\\%%' or o.nombre like '%\\%%')`)), String(await total()));

t('5 · Con los otros filtros y el calendario');

const regionId = sql(`select a.region_id ${PUBLICADAS} and o.nombre = '${org.replace(/'/g, "''")}' and a.region_id is not null limit 1`);
const conRegion = Number(sql(`select count(*) ${PUBLICADAS} and a.region_id = ${regionId} and (a.titulo like '%${org}%' or o.nombre like '%${org}%')`));
await p.goto(`${B}/actividades?q=${encodeURIComponent(org)}&region=${regionId}`, { waitUntil: 'networkidle2' });
di('se combina con la región', await total() === conRegion, `${await total()} / ${conRegion}`);

const aCalendario = await p.$eval('.vista-conmutador a:last-child', (a) => a.href);
di('pasar a calendario conserva la búsqueda', new URL(aCalendario).searchParams.get('q') === org);

await p.goto(aCalendario, { waitUntil: 'networkidle2' });
di('en el calendario el campo sigue relleno', await p.$eval('#f-q', (i) => i.value) === org);
const flecha = await p.evaluate(() => [...document.querySelectorAll('a')].map((a) => a.href).find((h) => h.includes('mes=')));
di('y cambiar de mes también', !! flecha && new URL(flecha).searchParams.get('q') === org, flecha);

await p.goto(`${B}/actividades?q=${encodeURIComponent(org)}`, { waitUntil: 'networkidle2' });
const limpiar = await p.evaluate(() => [...document.querySelectorAll('a')].find((a) => a.textContent.trim() === 'Limpiar')?.href);
di('«Limpiar» la suelta', !! limpiar && ! new URL(limpiar).searchParams.has('q'), limpiar);

t('6 · En el teléfono');

await p.setViewport({ width: 390, height: 844 });
await p.goto(`${B}/actividades`, { waitUntil: 'networkidle2' });
const movil = await p.evaluate(() => {
    const i = document.querySelector('#f-q').getBoundingClientRect();
    return { cabe: i.left >= 0 && i.right <= window.innerWidth, sinScroll: document.documentElement.scrollWidth <= window.innerWidth };
});
di('el campo cabe', movil.cabe);
di('sin desbordar la página', movil.sinScroll);

t('7 · Sin errores de JavaScript');
di('ninguno', errores.length === 0, errores.join(' | '));

await nav.close();
console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
