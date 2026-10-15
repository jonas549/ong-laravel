// /voluntariados con vista de lista y de calendario y la barra de filtros de
// actividades (15/10), en Chrome.
//
//   1 · Lista: la barra siempre visible, con las 16 regiones y los 3 formatos,
//       sin comuna ni tema; el conmutador con «Lista» activo.
//   2 · Calendario del mes: un voluntariado de un día en su casilla, uno de
//       varios días en todos los suyos (marcados como «sigue»), «+2 más» con
//       cinco el mismo día, sin hora, y los de convocatoria permanente en la
//       franja de arriba. Ni el terminado ni el cerrado.
//   3 · Mes siguiente: el que cruza de mes sale en sus días de allí.
//   4 · Filtros en las dos vistas: región, formato («En persona» cuenta como
//       Presencial) y búsqueda; filtrar en el calendario no cambia de vista
//       ni de mes; el conmutador conserva los filtros.
//   5 · /actividades sigue con su calendario (el parcial es compartido).
//   6 · A 390 px nada se sale de la pantalla.
//
// Siembra con `datos-voluntariados.php` y lo limpia al terminar.
//   node pruebas/voluntariados-calendario.mjs     (desde la raíz del repo; no contra producción)
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const CAPTURAS = process.env.DPS_CAPTURAS ?? null;

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(72)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').filter((l) => l.trim()).pop()?.trim();
const igual = (a, b) => JSON.stringify([...a].sort()) === JSON.stringify([...b].sort());

tinker("require base_path('pruebas/datos-voluntariados.php');");

const [HOY, MES_QUE_VIENE] = tinker("echo now(App\\Support\\Fecha::zona())->toDateString().' '.now(App\\Support\\Fecha::zona())->addMonthNoOverflow()->format('Y-m');").split(' ');
const dia = (n) => { const f = new Date(HOY + 'T12:00:00Z'); f.setUTCDate(f.getUTCDate() + n); return f; };
const mesDe = (f) => f.toISOString().slice(0, 7);
const MES = HOY.slice(0, 7);
const regionId = (nombre) => tinker(`echo App\\Models\\Region::where('nombre', '${nombre}')->value('id');`);
const VALPO = regionId('Valparaíso');
const BIOBIO = regionId('Biobío');

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const ir = async (ruta) => (await p.goto(B + ruta, { waitUntil: 'networkidle0' })).status();
const titulos = () => p.$$eval('.act-card h3', (hs) => hs.map((h) => h.textContent.trim()));
const casilla = (n) => p.evaluate((n) => {
  const c = [...document.querySelectorAll('.cal-dia:not(.cal-dia--fuera)')].find((d) => d.querySelector('.cal-dia-numero')?.textContent.trim() === String(n));
  if (!c) return null;
  return {
    visibles: [...c.querySelectorAll(':scope > .cal-act')].map((a) => ({ t: a.querySelector('.cal-act-nombre').textContent.trim(), sigue: a.classList.contains('cal-act--sigue'), hora: !!a.querySelector('.cal-act-hora'), tema: a.querySelector('.cal-act-tema')?.textContent.trim() ?? null, href: a.getAttribute('href') })),
    mas: c.querySelector('.cal-mas summary')?.textContent.trim() ?? null,
    plegadas: [...c.querySelectorAll('.cal-mas .cal-act-nombre')].map((a) => a.textContent.trim()),
  };
}, n);
const nombresEn = async (n) => { const c = await casilla(n); return c ? [...c.visibles.map((v) => v.t), ...c.plegadas] : []; };
const franja = () => p.$$eval('.cal-grupo', (gs) => gs.map((g) => ({ titulo: g.querySelector('.cal-grupo-titulo').textContent.trim(), items: [...g.querySelectorAll('.cal-suelta-nombre')].map((x) => x.textContent.trim()) })));
const captura = async (nombre) => { if (CAPTURAS) await p.screenshot({ path: `${CAPTURAS}/${nombre}.png`, fullPage: true }); };

const PRUEBA = (x) => `${x} (prueba)`;

try {
  t('1 · Lista y barra de filtros');
  di('/voluntariados responde 200', (await ir('/voluntariados')) === 200);
  const barra = await p.evaluate(() => {
    const f = document.querySelector('[data-filtros-voluntariados]');
    return f && {
      regiones: f.querySelectorAll('#f-region option').length,
      formatos: [...f.querySelectorAll('#f-formato option')].map((o) => o.value).filter(Boolean),
      comuna: !!f.querySelector('[name=comuna]'),
      tema: !!f.querySelector('[name=tema]'),
      buscar: !!f.querySelector('[name=q]'),
    };
  });
  di('la barra está', !!barra);
  di('región: «Todas» + las 16', barra?.regiones === 17, `(${barra?.regiones})`);
  di('formato: los tres de actividades', igual(barra?.formatos ?? [], ['Presencial', 'Online', 'Híbrido']), JSON.stringify(barra?.formatos));
  di('sin comuna y sin tema', barra && !barra.comuna && !barra.tema);
  di('con buscador', barra?.buscar);
  const lista = await titulos();
  di('10 tarjetas: 9 de prueba + la real', lista.length === 10, `(${lista.length})`);
  di('ni la terminada ni la cerrada', !lista.includes(PRUEBA('Ya terminó')) && !lista.includes(PRUEBA('Postulación cerrada')));
  di('la cabecera cuenta 10', (await p.$eval('h1 + p', (e) => e.textContent)).includes('10 voluntariados disponibles'));
  const conm = await p.$$eval('.vista-conmutador a', (as) => as.map((a) => ({ txt: a.textContent.trim(), activo: a.classList.contains('vista-btn--activo') })));
  di('conmutador Lista (activo) / Calendario', conm.length === 2 && conm[0].txt === 'Lista' && conm[0].activo && conm[1].txt === 'Calendario' && !conm[1].activo);
  di('el enlace a todo Voluntariados Chile sigue', !!(await p.$('[data-todos-vch]')));
  await captura('vol-lista');

  t('2 · Calendario de este mes');
  di('?vista=calendario responde 200', (await ir('/voluntariados?vista=calendario')) === 200);
  di('conmutador con «Calendario» activo', await p.$eval('.vista-conmutador .vista-btn--activo', (a) => a.textContent.trim() === 'Calendario'));
  const d2 = dia(2), d3 = dia(3), d6 = dia(6), d5 = dia(5);
  if (mesDe(d2) === MES) {
    const c = await casilla(d2.getUTCDate());
    // Cuatro de un día más el que está en curso: cinco.
    di('5 el mismo día: 3 a la vista y «+2 más»', c?.visibles.length === 3 && c?.mas === '+2 más', JSON.stringify(c?.mas));
    di('esos 5 son los de ese día', igual(await nombresEn(d2.getUTCDate()), ['Huerta comunitaria', 'Tutorías en línea', 'Limpieza de playa', 'Centro de acopio', 'Acompañamiento en curso'].map(PRUEBA)));
    di('ninguna casilla lleva hora (la API no la trae)', c?.visibles.every((v) => !v.hora));
    di('la categoría va como etiqueta, tal cual', c?.visibles.some((v) => v.tema === 'Acción comunitaria y emergencias'));
    di('enlazan a la ficha del voluntariado', c?.visibles.every((v) => v.href.includes('/voluntariados/')));
  } else console.log('  (dentro de dos días ya es otro mes: se salta la casilla de 4)');
  if (mesDe(d6) === MES) {
    const dias = [3, 4, 5, 6].map((n) => dia(n).getUTCDate());
    const enCada = await Promise.all(dias.map(async (n) => (await casilla(n))?.visibles.concat([]).find((v) => v.t === PRUEBA('Ropero solidario'))));
    di('el de 4 días sale en sus 4 días', enCada.every(Boolean));
    di('el primero sin marca, los siguientes «sigue»', enCada[0] && !enCada[0].sigue && enCada.slice(1).every((v) => v?.sigue));
  }
  if (mesDe(d5) === MES) {
    // Empezó hace 10 días y sigue hasta dentro de 5: sale también hoy.
    di('el que empezó antes y sigue sale hoy', (await nombresEn(dia(0).getUTCDate())).includes(PRUEBA('Acompañamiento en curso')));
  }
  const fr = await franja();
  di('franja «Convocatoria permanente» con la real y la de prueba', fr.length === 1 && fr[0].titulo === 'Convocatoria permanente' && fr[0].items.includes(PRUEBA('Mentorías permanentes')) && fr[0].items.length === 2, JSON.stringify(fr));
  const todo = await p.$$eval('.cal-act-nombre, .cal-suelta-nombre', (xs) => xs.map((x) => x.textContent.trim()));
  di('ni la terminada ni la cerrada en el calendario', !todo.includes(PRUEBA('Ya terminó')) && !todo.includes(PRUEBA('Postulación cerrada')));
  await captura('vol-calendario');

  t('3 · Mes siguiente');
  di('?mes= del mes que viene responde 200', (await ir(`/voluntariados?vista=calendario&mes=${MES_QUE_VIENE}`)) === 200);
  const c1 = await casilla(1), c2 = await casilla(2), c10 = await casilla(10);
  const camp = (c) => c?.visibles.find((v) => v.t === PRUEBA('Campamento de invierno'));
  di('el que cruza de mes sale el 1 y el 2, marcado «sigue»', camp(c1)?.sigue && camp(c2)?.sigue);
  di('la feria, el día 10', (await nombresEn(10)).includes(PRUEBA('Feria de reciclaje')), JSON.stringify(c10?.visibles.map((v) => v.t)));
  di('la franja sale también en otro mes', (await franja()).length === 1);
  di('la flecha «anterior» conserva vista', (await p.$eval('.cal-nav a.cal-paso', (a) => a.getAttribute('href'))).includes('vista=calendario'));

  t('4 · Filtros');
  await ir(`/voluntariados?region=${VALPO}`);
  di('región Valparaíso (escrita «Región de Valparaíso»): ropero y feria', igual(await titulos(), ['Ropero solidario', 'Feria de reciclaje'].map(PRUEBA)), JSON.stringify(await titulos()));
  await ir(`/voluntariados?region=${BIOBIO}`);
  di('región Biobío: tutorías (en dos regiones) y limpieza', igual(await titulos(), ['Tutorías en línea', 'Limpieza de playa'].map(PRUEBA)), JSON.stringify(await titulos()));
  await ir('/voluntariados?formato=Presencial');
  di('formato Presencial incluye el «En persona»', igual(await titulos(), ['Huerta comunitaria', 'Ropero solidario', 'Centro de acopio', 'Campamento de invierno', 'Acompañamiento en curso'].map(PRUEBA)), JSON.stringify(await titulos()));
  await ir('/voluntariados?formato=Online');
  di('formato Online', igual(await titulos(), ['Tutorías en línea', 'Mentorías permanentes'].map(PRUEBA)), JSON.stringify(await titulos()));
  await ir('/voluntariados?q=ropero');
  di('búsqueda por título', igual(await titulos(), [PRUEBA('Ropero solidario')]));
  await ir('/voluntariados?q=Corporaci%C3%B3n%20Ropero');
  di('búsqueda por organización', igual(await titulos(), [PRUEBA('Ropero solidario')]));
  await ir(`/voluntariados?region=${BIOBIO}&formato=Presencial`);
  di('sin resultados: aviso y «Ver todos»', (await titulos()).length === 0 && (await p.$eval('section', (s) => s.textContent)).includes('No encontramos voluntariados con esos filtros'));

  await ir(`/voluntariados?vista=calendario&mes=${MES}&formato=Online`);
  if (mesDe(d2) === MES) di('calendario con Online: el día de 4 queda con tutorías', igual(await nombresEn(d2.getUTCDate()), [PRUEBA('Tutorías en línea')]));
  di('calendario con Online: la franja, sólo mentorías', JSON.stringify((await franja())[0]?.items) === JSON.stringify([PRUEBA('Mentorías permanentes')]));
  await p.select('#f-formato', 'Híbrido');
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.click('[data-filtros-voluntariados] button[type=submit]')]);
  const u = new URL(p.url());
  di('filtrar en el calendario no cambia de vista ni de mes', u.searchParams.get('vista') === 'calendario' && u.searchParams.get('mes') === MES && u.searchParams.get('formato') === 'Híbrido', u.search);
  const aLista = await p.$eval('.vista-conmutador a', (a) => a.getAttribute('href'));
  di('«Lista» conserva el filtro y suelta el mes', aLista.includes('formato=') && !aLista.includes('mes=') && !aLista.includes('vista='), aLista);

  t('5 · /actividades no cambió');
  di('/actividades?vista=calendario responde 200 con su rejilla', (await ir('/actividades?vista=calendario')) === 200 && !!(await p.$('.cal-dias')));
  di('sus casillas enlazan a actividades', await p.$$eval('.cal-act', (as) => as.every((a) => a.getAttribute('href').includes('/activity/'))));
  di('/actividades lista responde 200', (await ir('/actividades')) === 200);

  t('6 · Teléfono (390 px)');
  await p.setViewport({ width: 390, height: 844 });
  for (const ruta of ['/voluntariados', '/voluntariados?vista=calendario']) {
    await ir(ruta);
    const ancho = await p.evaluate(() => document.documentElement.scrollWidth);
    di(`${ruta}: sin desborde horizontal`, ancho <= 390, `(${ancho})`);
  }
  await captura('vol-calendario-390');
} finally {
  await nav.close();
  tinker("$limpiar = true; require base_path('pruebas/datos-voluntariados.php');");
}

console.log(`\n${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
