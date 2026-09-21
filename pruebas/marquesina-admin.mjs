// La marquesina del home, elegida desde el panel (2026-09-21).
//
// Páginas → Marquesina de organizaciones. Lo que se comprueba es lo que pidió
// Jonas, punto por punto, mirando SIEMPRE el home y no sólo la pantalla del
// panel:
//
//   1. Añadir (buscando), quitar y ordenar, y que el home salga en ese orden.
//   2. El interruptor «mostrar todas»: encendido salen todas; apagado, la
//      lista manual, y vuelve tal como estaba.
//   3. Por defecto apagado, con la lista que salía antes.
//   4. La velocidad sigue ajustada al número de pastillas en los dos modos.
//
//   node pruebas/marquesina-admin.mjs
//
// Escribe (guarda la lista y el interruptor) y al terminar los deja como
// estaban. No correr contra producción.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();

// Ver `marquesina.mjs`: la consola de Windows devuelve las tildes rotas.
const igualable = (texto) => (texto ?? '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/[^a-zA-Z0-9 ]/g, '')
  .replace(/\s+/g, ' ')
  .trim()
  .toLowerCase();

/* ── El estado de partida, para dejarlo igual al terminar ── */
const listaInicial = ultima(tinker(
  "echo App\\Support\\Marquesina::elegidas()->pluck('id')->implode(',');"
)).split(',').filter(Boolean).map(Number);
const autoInicial = ultima(tinker("echo App\\Support\\Marquesina::automatica() ? 'SI' : 'NO';")) === 'SI';

const restaurar = () => tinker(
  `App\\Support\\Marquesina::guardar([${listaInicial.join(',')}]);`
  + ` App\\Models\\Setting::set('marquesina_automatica', ${autoInicial ? 'true' : 'false'}); echo 'RESTAURADO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
p.on('dialog', (d) => d.accept());

/**
 * Lo que pinta el home: la primera pasada, en orden, y la velocidad.
 *
 * En una pestaña que se abre y se cierra cada vez. Con una segunda pestaña
 * abierta todo el rato, la del panel queda en segundo plano, Chrome le
 * congela los `requestAnimationFrame` y los clics de puppeteer se quedan
 * esperando para siempre.
 */
const leerHome = async () => {
  const home = await nav.newPage();
  await home.setViewport({ width: 1440, height: 1000 });
  await home.goto(`${B}/`, { waitUntil: 'networkidle2' });

  const leido = await home.evaluate(() => {
    const carril = document.querySelector('.marquee-track');
    const nombres = [...document.querySelectorAll('.marquee-track .logo-chip')]
      .filter((c) => c.getAttribute('aria-hidden') !== 'true')
      .map((c) => c.querySelector('span:last-child')?.textContent.trim());

    return {
      nombres,
      pxs: carril ? Math.round((carril.scrollWidth / 2) / parseFloat(getComputedStyle(carril).animationDuration)) : 0,
    };
  });

  await home.close();
  await p.bringToFront();

  return leido;
};

const panel = async () => {
  await p.goto(`${B}/admin/paginas/marquesina`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(200);
};

const listaPanel = () => p.$$eval('.fila-marquesina', (n) => n.map((li) => li.querySelector('span[x-text="o.nombre"]').textContent.trim()));
const interruptor = () => p.$eval('input[name="automatica"]', (n) => n.checked);

const guardar = async () => {
  await p.evaluate(() => { window.__antes = true; });
  await p.click('button[type="submit"].btn-primary');
  // Vuelve a la misma URL: se espera a que sea otra página, no otra dirección.
  await p.waitForFunction(() => window.__antes === undefined && document.readyState === 'complete' && window.Alpine !== undefined, { timeout: 30000 });
  await esperar(150);
};

const anadir = async (escribir) => {
  await p.$eval('#buscar-org', (n) => { n.value = ''; });
  await p.type('#buscar-org', escribir);
  await p.waitForFunction(() => document.querySelectorAll('.resultado-marquesina').length > 0, { timeout: 8000 }).catch(() => null);
  await esperar(150);
  await p.evaluate(() => document.querySelector('.resultado-marquesina:not([disabled])')?.click());
  await esperar(150);
};

try {
  /* ═══════════════ Entrar, y que sólo entre un administrador ═══════════════ */

  t('Dónde está, y quién entra');

  const sinSesion = await p.goto(`${B}/admin/paginas/marquesina`, { waitUntil: 'networkidle2' });
  di('Sin sesión manda al login', p.url().includes('/admin/login'), `${sinSesion.status()} → ${p.url().replace(B, '')}`);

  await p.type('input[name="email"]', ADMIN);
  await p.type('input[name="password"]', CLAVE_ADMIN);
  await p.click('form button[type="submit"]');
  await p.waitForFunction(() => ! location.pathname.includes('/login') && document.readyState === 'complete', { timeout: 30000 });

  await p.goto(`${B}/admin/paginas/home/participantes`, { waitUntil: 'networkidle2' });
  di('La sección de la marquesina enlaza a la pantalla', await p.evaluate(() => !! [...document.querySelectorAll('a')]
    .find((a) => a.textContent.includes('Elegir las organizaciones') && a.getAttribute('href').endsWith('/admin/paginas/marquesina'))));

  await panel();
  di('Está en el menú: Páginas → Marquesina de organizaciones', await p.evaluate(() => !! [...document.querySelectorAll('aside a, nav a')]
    .find((a) => a.textContent.trim() === 'Marquesina de organizaciones')));

  /* ═══════════════ 3. Por defecto ═══════════════ */

  t('Por defecto: apagado, y el home sale como antes');

  await restaurar();
  tinker("App\\Models\\Setting::set('marquesina_automatica', false); echo 'x';");
  await panel();

  di('El interruptor arranca apagado', ! (await interruptor()));

  const inicial = await listaPanel();
  const hoy = await leerHome();
  di('**El home enseña exactamente la lista, en su orden**',
    JSON.stringify(hoy.nombres.map(igualable)) === JSON.stringify(inicial.map(igualable)),
    `${hoy.nombres.join(' · ')}`);

  /* ═══════════════ 1. Añadir, ordenar, quitar ═══════════════ */

  t('Añadir buscando, ordenar y quitar');

  await anadir('Aldeas Infantiles');
  await anadir('Javier Arrieta');
  await anadir('Tregua');

  let lista = await listaPanel();
  di('Buscar y añadir las pone al final de la lista', lista.slice(-3).map(igualable).join('|')
    === ['ALDEAS INFANTILES SOS', 'FUNDACIÓN JAVIER ARRIETA', 'FUNDACIÓN TREGUA'].map(igualable).join('|'), lista.join(' · '));

  // Una que ya está no se añade dos veces: el botón sale desactivado.
  await p.$eval('#buscar-org', (n) => { n.value = ''; });
  await p.type('#buscar-org', 'Aldeas Infantiles');
  await p.waitForFunction(() => document.querySelectorAll('.resultado-marquesina').length > 0, { timeout: 8000 }).catch(() => null);
  await esperar(150);
  di('Una que ya está no se puede añadir otra vez', await p.evaluate(() =>
    [...document.querySelectorAll('.resultado-marquesina')].find((b) => /ALDEAS/.test(b.textContent))?.disabled === true));
  di('Y lo dice', (await p.evaluate(() => document.body.innerText)).includes('Ya está en la lista'));

  // ↑: Tregua sube un puesto, por encima de Javier Arrieta.
  const filas = await p.$$('.fila-marquesina');
  await (await filas[filas.length - 1].$('button[aria-label="Subir"]')).click();
  await esperar(150);
  lista = await listaPanel();
  di('La flecha ↑ sube un puesto', igualable(lista[lista.length - 2]) === igualable('FUNDACIÓN TREGUA'), lista.slice(-3).join(' · '));

  // Arrastrar: Aldeas, del antepenúltimo puesto al primero.
  await p.setDragInterception(true);
  const origen = (await p.$$('.fila-marquesina'))[lista.length - 3];
  const destino = (await p.$$('.fila-marquesina'))[0];
  await origen.dragAndDrop(destino);
  await p.setDragInterception(false);
  await esperar(250);
  lista = await listaPanel();
  di('Arrastrar cambia el orden', igualable(lista[0]) === igualable('ALDEAS INFANTILES SOS'), lista.join(' · '));

  di('Avisa de que hay cambios sin guardar', (await p.evaluate(() => document.body.innerText)).includes('Hay cambios sin guardar'));

  const antesDeGuardar = await leerHome();
  di('Nada llega al home hasta guardar', JSON.stringify(antesDeGuardar.nombres) === JSON.stringify(hoy.nombres));

  await guardar();
  di('Guardar confirma', (await p.evaluate(() => document.body.innerText)).includes('La marquesina muestra la lista elegida'));

  const guardada = await listaPanel();
  di('La lista se conserva al recargar, en su orden', JSON.stringify(guardada) === JSON.stringify(lista), guardada.join(' · '));

  let enHome = await leerHome();
  di('**El home sale con esa lista y en ese orden**',
    JSON.stringify(enHome.nombres.map(igualable)) === JSON.stringify(guardada.map(igualable)), enHome.nombres.join(' · '));

  // Quitar la primera.
  await (await (await p.$$('.fila-marquesina'))[0].$('button[aria-label^="Quitar"]')).click();
  await esperar(150);
  await guardar();
  const sinAldeas = await listaPanel();
  enHome = await leerHome();
  di('Quitar la saca de la lista', ! sinAldeas.map(igualable).includes(igualable('ALDEAS INFANTILES SOS')));
  di('**Y del home**', ! enHome.nombres.map(igualable).includes(igualable('ALDEAS INFANTILES SOS')) && enHome.nombres.length === sinAldeas.length,
    enHome.nombres.join(' · '));

  /* ═══════════════ 2. El interruptor ═══════════════ */

  t('El interruptor «mostrar todas»');

  const todas = Number(ultima(tinker('echo App\\Support\\Marquesina::todas()->count();')));

  await p.click('input[name="automatica"]');
  await esperar(200);
  di('Encenderlo explica que la lista se guarda', (await p.evaluate(() => document.body.innerText)).includes('al apagarlo vuelve a salir'));
  await guardar();

  di('Queda encendido al recargar', await interruptor());
  di('Y la lista manual sigue ahí, intacta', JSON.stringify(await listaPanel()) === JSON.stringify(sinAldeas));

  const auto = await leerHome();
  di('**Encendido, el home enseña todas**', auto.nombres.length === todas, `${auto.nombres.length} de ${todas}`);
  const repetidas = auto.nombres.filter((n, i) => auto.nombres.indexOf(n) !== i);
  di('Sin repetir ninguna', repetidas.length === 0, repetidas.join(' · '));
  di('**A una velocidad que se lee**', auto.pxs > 20 && auto.pxs < 90, `${auto.pxs} px/s`);

  await p.click('input[name="automatica"]');
  await guardar();
  const vuelta = await leerHome();
  di('Apagado otra vez, queda apagado', ! (await interruptor()));
  di('**Y el home vuelve a la lista tal como estaba**',
    JSON.stringify(vuelta.nombres.map(igualable)) === JSON.stringify(sinAldeas.map(igualable)), vuelta.nombres.join(' · '));
  // Con pocas, manda el mínimo de 34 s por vuelta que ya había: van despacio,
  // como siempre. Lo que no puede pasar es que vayan más rápido que con todas.
  di('La velocidad también va con la lista', vuelta.pxs > 10 && vuelta.pxs < 90, `${vuelta.pxs} px/s`);

  /* ═══════════════ Lo que no debe pasar ═══════════════ */

  t('Lo que no se deja');

  const trampa = await p.evaluate(async (base) => {
    const doc = await (await fetch(base + '/admin/paginas/marquesina', { credentials: 'same-origin' })).text();
    const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
    const f = new URLSearchParams();
    f.append('_token', token);
    f.append('_method', 'PUT');
    f.append('organizaciones[]', '999999');

    const r = await fetch(base + '/admin/paginas/marquesina', { method: 'POST', body: f, credentials: 'same-origin' });

    return (await r.text()).includes('organización seleccionada no es válida') || r.url.includes('/admin/paginas/marquesina');
  }, B);
  const tras = await listaPanel();
  di('Un id que no existe no se guarda', trampa && JSON.stringify(tras) === JSON.stringify(sinAldeas));

  // Vaciar la lista con el interruptor apagado: el home no enseña la tira.
  tinker('App\\Support\\Marquesina::guardar([]); echo 1;');
  const vacia = await leerHome();
  di('Con la lista vacía y apagado, no sale la marquesina', vacia.nombres.length === 0);

  di('Sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  di('La prueba deja la lista y el interruptor como estaban', ultima(restaurar()) === 'RESTAURADO');
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
