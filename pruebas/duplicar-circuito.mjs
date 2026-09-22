// A1 de la sexta tanda — el circuito entero de duplicar una sección, como lo
// haría la ONG y siempre desde la pantalla: duplicar, cambiarle titular, texto
// e imagen, publicar, moverla, esconderla, volver a mostrarla y borrarla.
//
// Después de cada paso se mira el home PÚBLICO, porque lo que importa es lo que
// ve el visitante, y el listado del panel, que es la pantalla que dio el 500.
//
// Y la independencia en los dos sentidos: `duplicar-seccion.mjs` comprueba que
// editar la copia no toca la original; aquí además que editar la original no
// toca la copia, que es la mitad que nadie había mirado.
//
//   node pruebas/duplicar-circuito.mjs
//
// Contra producción NO: publica en el home de verdad.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

// Lo que había en la original, para dejarla igual al terminar.
const respaldo = tinker(`echo json_encode(App\\Models\\HomeSection::where('clave','que-es')->value('contenido'));`)
  .split('\n').pop();
const limpiar = () => tinker(`App\\Models\\HomeSection::where('clave','like','%--%')->delete(); echo 'ok';`);
limpiar();

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1400, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', ADMIN);
await p.type('input[name="password"]', CLAVE_ADMIN);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

/** El listado del panel: responde 200 y cuántas filas pinta. */
const listado = async () => {
  const r = await p.goto(`${B}/admin/paginas/home`, { waitUntil: 'networkidle2' });
  return { estado: r.status(), filas: await p.evaluate(() => document.querySelectorAll('.fila-seccion').length) };
};

/** Lo que enseña el home público de las dos «¿Qué es…?». */
const publico = async () => {
  const r = await p.goto(`${B}/?t=${Date.now()}`, { waitUntil: 'networkidle2' });
  return {
    estado: r.status(),
    ...(await p.evaluate(() => {
      const leer = (id) => {
        const s = document.getElementById(id);
        if (! s) return null;
        return {
          titulo: s.querySelector('h2')?.innerText.replace(/\s+/g, ' ').trim(),
          texto: s.innerText.replace(/\s+/g, ' '),
          imagen: s.querySelector('img')?.getAttribute('src') ?? '',
        };
      };
      const ids = [...document.querySelectorAll('main section, body > section, section')].map((s) => s.id).filter(Boolean);
      return { original: leer('que-es'), copia: leer('que-es-2'), ids };
    })),
  };
};

/** Escribe en los campos del editor abierto y pulsa Publicar. */
const publicar = async (campos) => {
  await p.evaluate((campos) => {
    for (const [nombre, valor] of Object.entries(campos)) {
      const oculto = document.querySelector(`[name="${nombre}"]`);
      const editable = oculto?.closest('[x-data]')?.querySelector('[contenteditable]');
      if (editable && oculto.type === 'hidden' && nombre === 'cuerpo') {
        editable.innerHTML = valor;
        editable.dispatchEvent(new Event('input', { bubbles: true }));
      } else if (oculto?.type === 'hidden') {
        // La imagen: lo mismo que deja el selector de la biblioteca al elegir.
        const raiz = oculto.closest('.campo-medio');
        const datos = raiz && window.Alpine ? window.Alpine.$data(raiz) : null;
        if (datos && 'ruta' in datos) datos.ruta = valor;
        oculto.value = valor;
      } else {
        oculto.value = valor;
        oculto.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }
  }, campos);
  await new Promise((r) => setTimeout(r, 300));
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => [...document.querySelectorAll('button[type="submit"]')].find((b) => b.textContent.trim() === 'Publicar').click()),
  ]);
  return p.evaluate(() => document.body.innerText.includes('Publicado'));
};


/* ═══════════════ 1. Duplicar ═════════════════════════════════════ */

t('1. Duplicar «¿Qué es el Patrimonio Social?»');

let l = await listado();
di('El listado carga', l.estado === 200 && l.filas === 13, `HTTP ${l.estado}, ${l.filas} filas`);
const antes = await publico();

await listado();
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.evaluate(() => document.querySelector('.fila-seccion[data-clave="que-es"] form[action*="duplicar"] button').click()),
]);
di('Lleva a editar la copia', p.url().endsWith('/admin/paginas/home/que-es--2'), p.url().replace(B, ''));

l = await listado();
di('**El listado sigue cargando con la copia dentro**', l.estado === 200 && l.filas === 14, `HTTP ${l.estado}, ${l.filas} filas`);

let h = await publico();
di('El home responde', h.estado === 200);
di('La copia sale en el home, igual que la original', !! h.copia && h.copia.titulo === antes.original.titulo, h.copia?.titulo);
di('Y justo detrás de la original', h.ids.indexOf('que-es-2') === h.ids.indexOf('que-es') + 1, h.ids.join(','));

/* ═══════════════ 2. Cambiarle titular, texto e imagen ════════════ */

t('2. Cambiarle titular, texto e imagen a la copia');

await p.goto(`${B}/admin/paginas/home/que-es--2`, { waitUntil: 'networkidle2' });
di('Publica', await publicar({
  titulo_antes: 'Titular de la copia:',
  cuerpo: '<p>Texto que sólo tiene la copia.</p>',
  imagen: 'img/construyamos-crop.png',
}));

h = await publico();
di('**La copia enseña el titular nuevo**', h.copia?.titulo.startsWith('Titular de la copia:'), h.copia?.titulo);
di('**El texto nuevo**', h.copia?.texto.includes('Texto que sólo tiene la copia.'));
di('**Y la imagen nueva**', h.copia?.imagen.endsWith('img/construyamos-crop.png'), h.copia?.imagen.replace(B, ''));
di('La original, con su titular', h.original?.titulo === antes.original.titulo, h.original?.titulo);
di('Con su texto', ! h.original?.texto.includes('Texto que sólo tiene la copia.'));
di('Y con su imagen', h.original?.imagen === antes.original.imagen, h.original?.imagen.replace(B, ''));

/* ═══════════════ 3. Y al revés ═══════════════════════════════════ */

t('3. Editar la ORIGINAL no toca la copia');

await p.goto(`${B}/admin/paginas/home/que-es`, { waitUntil: 'networkidle2' });
di('Publica', await publicar({ titulo_antes: 'Titular de la original:' }));

h = await publico();
di('La original cambia', h.original?.titulo.startsWith('Titular de la original:'), h.original?.titulo);
di('**Y la copia conserva el suyo**', h.copia?.titulo.startsWith('Titular de la copia:'), h.copia?.titulo);
di('**Y su texto y su imagen**', h.copia?.texto.includes('Texto que sólo tiene la copia.') && h.copia?.imagen.endsWith('construyamos-crop.png'));

/* ═══════════════ 4. Reordenar ════════════════════════════════════ */

t('4. Moverla al final, arrastrando');

await listado();
// El arrastre de verdad: dragstart en la copia y drop sobre la última fila.
const movida = await p.evaluate(async () => {
  const filas = [...document.querySelectorAll('.fila-seccion')];
  const copia = filas.find((f) => f.dataset.clave === 'que-es--2');
  const ultima = filas[filas.length - 1];
  const dt = new DataTransfer();
  const lanzar = (el, tipo, y = 0) => el.dispatchEvent(new DragEvent(tipo, { bubbles: true, cancelable: true, dataTransfer: dt, clientY: y }));
  lanzar(copia, 'dragstart');
  // Por la mitad de abajo de la última fila: ahí el editor la suelta detrás.
  lanzar(ultima, 'dragover', ultima.getBoundingClientRect().bottom - 2);
  lanzar(ultima, 'drop');
  lanzar(copia, 'dragend');
  await new Promise((r) => setTimeout(r, 1500));
  return [...document.querySelectorAll('.fila-seccion')].map((f) => f.dataset.clave).pop();
});
di('En el panel queda la última', movida === 'que-es--2', movida);

l = await listado();
const ultimaTrasRecargar = await p.evaluate(() => [...document.querySelectorAll('.fila-seccion')].map((f) => f.dataset.clave).pop());
di('**Y sigue ahí al recargar: el orden se guardó**', l.estado === 200 && ultimaTrasRecargar === 'que-es--2', ultimaTrasRecargar);

h = await publico();
const conId = h.ids;
di('En el home ya no va detrás de la original', conId.indexOf('que-es-2') !== conId.indexOf('que-es') + 1, conId.join(','));
const trasLaCopia = await p.evaluate(() => {
  // La franja con fondo-03 es del pie (partials/public/footer), no del home.
  const todas = [...document.querySelectorAll('section')]
    .filter((s) => ! s.closest('footer') && ! (s.getAttribute('style') ?? '').includes('fondo-03'));
  const i = todas.findIndex((s) => s.id === 'que-es-2');
  return todas.slice(i + 1).map((s) => s.id || s.querySelector('h2,h3')?.innerText.slice(0, 30) || s.className);
});
di('**Va la última de las secciones del home**', trasLaCopia.length === 0, trasLaCopia.join(' | '));

/* ═══════════════ 5. Esconder y mostrar ═══════════════════════════ */

t('5. Esconderla y volver a mostrarla');

await listado();
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.evaluate(() => document.querySelector('.fila-seccion[data-clave="que-es--2"] form[action*="estado"] button').click()),
]);
l = await listado();
di('El listado carga y la marca «No se ve»', l.estado === 200 && await p.evaluate(() =>
  /No se ve/.test(document.querySelector('.fila-seccion[data-clave="que-es--2"]').innerText)));
h = await publico();
di('**Desaparece del home**', h.copia === null);
di('La original sigue', !! h.original);

await listado();
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.evaluate(() => document.querySelector('.fila-seccion[data-clave="que-es--2"] form[action*="estado"] button').click()),
]);
h = await publico();
di('**Y vuelve al mostrarla, con lo que tenía**', h.copia?.titulo.startsWith('Titular de la copia:'), h.copia?.titulo);

/* ═══════════════ 6. Borrar ═══════════════════════════════════════ */

t('6. Borrarla desde el panel');

await listado();
await p.evaluate(() => [...document.querySelector('.fila-seccion[data-clave="que-es--2"]').querySelectorAll('button')]
  .find((b) => b.textContent.trim() === 'Eliminar').click());
await new Promise((r) => setTimeout(r, 400));
di('Pide confirmación', await p.evaluate(() => /Eliminar esta copia/.test(document.body.innerText)));
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.evaluate(() => document.querySelector('.dialogo-botones button[type="submit"]').click()),
]);
l = await listado();
di('**El listado carga, con una fila menos**', l.estado === 200 && l.filas === 13, `HTTP ${l.estado}, ${l.filas} filas`);
h = await publico();
di('**Y el home ya no la tiene**', h.copia === null && !! h.original);
di('La original conserva lo último que se le publicó', h.original?.titulo.startsWith('Titular de la original:'));

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

/* Se deja la original como estaba. */
tinker(`App\\Models\\HomeSection::where('clave','que-es')->update(['contenido' => ${respaldo === 'null' ? 'null' : `'${respaldo.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`}]); echo 'ok';`);
limpiar();

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
