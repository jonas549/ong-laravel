// La vista de calendario de /actividades.
//
// Necesita Chrome: casi todo lo que hay que comprobar aquí es geometría —que
// las siete columnas empiecen en lunes, que la casilla pliegue lo que no cabe,
// que en 390 px la rejilla se convierta en lista— y por HTTP eso no se ve.
//
// **Una trampa de este archivo, anotada para no volver a caer.** El contenido
// de un `<details>` cerrado sigue en el DOM, y desde Chrome 152 conserva además
// su caja de layout: `offsetParent` devuelve algo y `getBoundingClientRect()`
// da una altura de verdad aunque no se pinte nada. Preguntar por esas dos cosas
// da un falso «se ve». Lo que sí dice la verdad es **el alto de la casilla que
// lo contiene**, que es lo que se mide abajo.
//
//   php artisan serve --host=127.0.0.1 --port=8123
//   node pruebas/calendario-actividades.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = 'http://127.0.0.1:8123';
const S = process.env.DPS_SALIDA ?? tmpdir();
const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), '..');

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(56)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => console.log(`\n=== ${x} ===`);
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const tinker = (linea) => execFileSync('php', ['artisan', 'tinker', '--execute', linea], { cwd: RAIZ, encoding: 'utf8' });

// El escenario, y el mes al que pertenece: las fechas cuelgan de hoy.
const sembrado = tinker("require base_path('pruebas/datos-calendario.php');");
const MES = sembrado.match(/CALENDARIO-LISTO (\d{4}-\d{2})/)?.[1];
if (!MES) { console.error('No se pudo sembrar el escenario:\n' + sembrado); process.exit(1); }
const [ANIO, NUM] = MES.split('-').map(Number);
const otroMes = (n) => { const d = new Date(Date.UTC(ANIO, NUM - 1 + n, 1)); return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`; };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
p.on('pageerror', (e) => errores.push(String(e)));

const ir = async (url) => { await p.goto(url, { waitUntil: 'networkidle2' }); await esperar(220); };
const cal = (extra = '') => `${B}/actividades?vista=calendario${extra}`;

/** Lo que se ve en cada casilla. Sólo lo pintado: ver la nota de arriba. */
const casillas = () => p.$$eval('.cal-dia', (n) => n.map((d) => ({
  dia: d.querySelector('.cal-dia-numero')?.textContent.trim(),
  fuera: d.className.includes('--fuera'),
  hoy: d.className.includes('--hoy'),
  vacio: d.className.includes('--vacio'),
  visible: d.offsetParent !== null,
  alto: Math.round(d.getBoundingClientRect().height),
  actos: [...d.querySelectorAll('.cal-act')]
    .filter((a) => !a.closest('.cal-mas') || a.closest('.cal-mas').open)
    .map((a) => ({
      nombre: a.querySelector('.cal-act-nombre')?.textContent.trim(),
      tema: a.querySelector('.cal-act-tema')?.textContent.trim() ?? null,
      hora: a.querySelector('.cal-act-hora')?.textContent.trim().replace(/\s+/g, '') ?? null,
      sigue: a.className.includes('--sigue'),
    })),
})));

await p.setViewport({ width: 1440, height: 1200 });

t('El conmutador lista ↔ calendario');
await ir(`${B}/actividades`);
const botones = await p.$$eval('.vista-btn', (n) => n.map((b) => ({ txt: b.textContent.trim(), activo: b.className.includes('--activo'), href: b.getAttribute('href') })));
di('hay dos botones de vista', botones.length === 2, JSON.stringify(botones.map((b) => b.txt)));
di('en /actividades el activo es Lista', botones[0].activo && !botones[1].activo);
di('la lista de tarjetas sigue saliendo', (await p.$$('.act-card')).length > 0);

await ir(`${B}/actividades?region=13&tema=1`);
const conFiltro = await p.$$eval('.vista-btn', (n) => n.map((b) => b.getAttribute('href')));
di('pasar a calendario conserva los filtros', ['region=13', 'tema=1', 'vista=calendario'].every((x) => conFiltro[1].includes(x)), conFiltro[1]);
await ir(cal('&region=13&tema=1'));
di('y volver a la lista los conserva y suelta el mes', conFiltro[0].includes('region=13') && !(await p.$eval('.vista-btn', (b) => b.getAttribute('href'))).includes('mes='));

t('La rejilla del mes');
await ir(cal(`&mes=${MES}`));
di('el activo pasa a Calendario', (await p.$$eval('.vista-btn', (n) => n[1].className)).includes('--activo'));
const cab = await p.$$eval('.cal-cabecera span', (n) => n.map((s) => s.textContent.trim().toLowerCase()));
di('las columnas van de lunes a domingo', cab[0].startsWith('lun') && cab[6].startsWith('dom'), JSON.stringify(cab));

const cs = await casillas();
di('la rejilla trae semanas completas', cs.length % 7 === 0, `${cs.length} casillas`);
di('hoy está marcado, y una sola vez', cs.filter((c) => c.hoy).length === 1);
di('el mes se rotula en castellano', /^[A-ZÁÉÍÓÚ][a-zá-ú]+ de \d{4}$/.test(await p.$eval('.cal-mes', (e) => e.textContent.trim())), await p.$eval('.cal-mes', (e) => e.textContent.trim()));

t('Categoría, nombre y hora — lo que pidió el cliente');
const dia17 = cs.find((c) => c.dia === '17' && !c.fuera);
di('el nombre está', dia17.actos[0].nombre?.length > 0, dia17.actos[0].nombre);
di('la categoría está', dia17.actos[0].tema?.length > 0, dia17.actos[0].tema);
di('la hora está, con su rango cuando lo hay', dia17.actos.some((a) => a.hora === '09:00–12:00'), JSON.stringify(dia17.actos.map((a) => a.hora)));
di('ordenadas por hora, y las sin hora al final', dia17.actos[0].hora?.startsWith('09:00') && dia17.actos[1].hora?.startsWith('10:30'));

t('Una casilla llena pliega lo que no cabe');
di('el día 17 pinta tres', dia17.actos.length === 3, `${dia17.actos.length} a la vista`);
di('y ofrece «+2 más»', (await p.$$eval('.cal-mas > summary', (n) => n.map((s) => s.textContent.trim()))).includes('+2 más'));
// El alto de la casilla es lo único que no miente con un <details> cerrado.
const altoCerrado = (await casillas()).find((c) => c.dia === '17' && !c.fuera).alto;
await p.$eval('.cal-mas > summary', (s) => s.click());
await esperar(250);
const abierto = (await casillas()).find((c) => c.dia === '17' && !c.fuera);
di('al abrir, la casilla crece', abierto.alto > altoCerrado, `${altoCerrado} → ${abierto.alto} px`);
di('y salen las cinco', abierto.actos.length === 5, `${abierto.actos.length}`);
di('ninguna se queda sin poder verse', abierto.actos.every((a) => a.nombre?.length > 0));

t('Varios días: la actividad se pinta en todas sus casillas');
await ir(cal(`&mes=${MES}`));
const feria = (await casillas()).filter((c) => !c.fuera && c.actos.some((a) => a.nombre === 'Feria patrimonial de barrio'));
di('sale en sus tres días de este mes', feria.length === 3, JSON.stringify(feria.map((c) => c.dia)));
di('son días consecutivos', feria.map((c) => +c.dia).every((d, i, a) => i === 0 || d === a[i - 1] + 1), JSON.stringify(feria.map((c) => c.dia)));
di('el primero NO va marcado como arrastre', feria[0].actos.find((a) => a.nombre === 'Feria patrimonial de barrio').sigue === false);
di('los siguientes SÍ', feria.slice(1).every((c) => c.actos.find((a) => a.nombre === 'Feria patrimonial de barrio').sigue));
const finde = (await casillas()).filter((c) => !c.fuera && c.actos.some((a) => a.nombre === 'Mingako de pintura mural'));
di('el fin de semana ocupa sus dos días', finde.length === 2, JSON.stringify(finde.map((c) => c.dia)));

t('Y el mes siguiente recoge su cola');
await ir(cal(`&mes=${otroMes(1)}`));
const cola = (await casillas()).filter((c) => !c.fuera && c.actos.some((a) => a.nombre === 'Feria patrimonial de barrio'));
di('la feria sigue el 1 y el 2', JSON.stringify(cola.map((c) => c.dia)) === '["1","2"]', JSON.stringify(cola.map((c) => c.dia)));
di('los dos como arrastre', cola.every((c) => c.actos.find((a) => a.nombre === 'Feria patrimonial de barrio').sigue));

t('Navegación de meses');
const pasos = await p.$$eval('.cal-paso', (n) => n.map((a) => a.getAttribute('href')));
di('la flecha atrás vuelve al mes de hoy', pasos[0]?.includes(`mes=${MES}`), pasos[0]);
di('la de adelante avanza uno', pasos[1]?.includes(`mes=${otroMes(2)}`), pasos[1]);
di('sale «Ir a este mes» fuera del mes actual', (await p.$('.cal-hoy')) !== null);
await ir(cal());
di('y no sale estando en él', (await p.$('.cal-hoy')) === null);
di('las flechas van con nofollow', (await p.$$eval('.cal-paso[href]', (n) => n.every((a) => (a.rel || '').includes('nofollow')))));

t('Los filtros son los mismos que los de la lista');
await ir(cal(`&mes=${MES}&tema=1`));
const temasVistos = [...new Set((await casillas()).flatMap((c) => c.actos).map((a) => a.tema))];
di('el calendario obedece el filtro de tema', temasVistos.length === 1, JSON.stringify(temasVistos));
const ocultos = await p.$$eval('form input[type=hidden]', (n) => n.map((i) => `${i.name}=${i.value}`));
di('filtrar no saca del calendario ni del mes', ocultos.includes('vista=calendario') && ocultos.includes(`mes=${MES}`), JSON.stringify(ocultos));
di('«Limpiar» tampoco saca del calendario', (await p.$eval('a.btn-ghost', (a) => a.getAttribute('href'))).includes('vista=calendario'));

t('La franja de las que no caben en ninguna casilla');
await ir(cal(`&mes=${MES}`));
const grupos = await p.$$eval('.cal-grupo', (n) => n.map((g) => ({
  titulo: g.querySelector('.cal-grupo-titulo').textContent.trim(),
  cuenta: g.querySelector('.cal-grupo-cuenta').textContent.trim(),
  abierto: g.hasAttribute('open'),
  items: g.querySelectorAll('.cal-suelta').length,
})));
di('hay dos grupos, y separados', grupos.length === 2, JSON.stringify(grupos.map((g) => g.titulo)));
di('«Disponibles todo el año», con su cuenta', grupos[0].titulo === 'Disponibles todo el año' && grupos[0].cuenta === '2' && grupos[0].items === 2, JSON.stringify(grupos[0]));
di('«Con fecha por definir», con la suya', grupos[1].titulo === 'Con fecha por definir' && grupos[1].cuenta === '1' && grupos[1].items === 1, JSON.stringify(grupos[1]));
di('salen desplegados', grupos.every((g) => g.abierto));
di('y se pliegan', await p.$eval('.cal-grupo summary', (s) => { s.click(); return !s.parentElement.hasAttribute('open'); }));
di('sin una línea de JavaScript', await p.$$eval('.cal-grupo', (n) => n.every((g) => !g.hasAttribute('x-data'))));
di('ninguna suelta se cuela en la rejilla', !(await casillas()).flatMap((c) => c.actos).some((a) => a.nombre === 'Biblioteca abierta del barrio'));
await ir(cal('&mes=2027-03'));
di('la franja no depende del mes: sigue en uno vacío', (await p.$$('.cal-grupo')).length === 2);

t('Un mes vacío, y uno imposible');
di('dice que no hay nada y ofrece el siguiente', (await p.$eval('.card p', (e) => e.textContent.trim())).startsWith('No hay actividades con fecha en marzo'));
for (const malo of ['9999-99', 'pepito', '1204-07', '2026-13', '2026-00', '']) {
  await ir(cal(`&mes=${encodeURIComponent(malo)}`));
  const titulo = await p.$eval('.cal-mes', (e) => e.textContent.trim());
  const hoy = await p.$('.cal-hoy');
  di(`«${malo || '(vacío)'}» cae en el mes de hoy`, hoy === null, titulo);
}

t('Nada se desborda, y la rejilla se pliega a lista');
for (const ancho of [1440, 1180, 1020, 760, 560, 390]) {
  await p.setViewport({ width: ancho, height: 1200 });
  await ir(cal(`&mes=${MES}`));
  const d = await p.evaluate(() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth }));
  di(`sin desborde horizontal a ${ancho}`, d.doc <= d.win, JSON.stringify(d));
}

await p.setViewport({ width: 390, height: 900 });
await ir(cal(`&mes=${MES}`));
const movil = await casillas();
di('las casillas vacías desaparecen', movil.filter((c) => c.vacio).every((c) => !c.visible));
di('las que tienen algo se quedan', movil.filter((c) => !c.vacio).every((c) => c.visible), `${movil.filter((c) => !c.vacio).length} días con algo`);
di('los rótulos de columna se esconden', !(await p.$eval('.cal-cabecera', (e) => e.offsetParent !== null)));
const largo = await p.$$eval('.cal-dia:not(.cal-dia--vacio) .cal-dia-largo', (n) => n.filter((e) => e.offsetParent !== null).map((e) => e.textContent.trim()));
di('cada día se rotula entero', largo.length > 0 && /\d+ de \w+/.test(largo[0]), largo[0]);
di('y el número suelto se esconde', !(await p.$eval('.cal-dia:not(.cal-dia--vacio) .cal-dia-numero', (e) => e.offsetParent !== null)));
di('el conmutador se reparte el ancho', (await p.$$eval('.vista-btn', (n) => n.map((b) => Math.round(b.getBoundingClientRect().width)))).every((w, _, a) => Math.abs(w - a[0]) < 2));
await p.screenshot({ path: `${S}/calendario-390.png`, fullPage: true });

t('La ficha sigue llegándose desde una casilla');
await p.setViewport({ width: 1440, height: 1200 });
await ir(cal(`&mes=${MES}`));
const destino = await p.$eval('.cal-act', (a) => a.getAttribute('href'));
await ir(`${B}${new URL(destino, B).pathname}`);
di('la casilla lleva a su ficha', (await p.$('h1')) !== null && p.url().includes('/actividades/'), p.url());

console.log(`\n${ok} OK · ${mal} mal · consola: ${errores.length ? errores.join(' | ') : 'limpia'}`);
console.log(`Capturas en ${S}`);

tinker("$limpiar = true; require base_path('pruebas/datos-calendario.php');");
await nav.close();
process.exit(mal ? 1 : 0);
