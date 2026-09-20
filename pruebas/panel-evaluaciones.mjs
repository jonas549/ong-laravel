// Las evaluaciones en el panel: respuestas, promedios, fotos y exportación.
//
// Necesita Chrome porque tres de las comprobaciones son de las que sólo se ven
// con pantalla: que el texto largo de una respuesta no estire la tabla, que la
// cuadrícula de fotos separe de verdad las autorizadas de las que no, y que la
// exportación descargue un archivo y no una página de error con extensión .xlsx
// (que ya pasó una vez en este proyecto).
//
//   php artisan serve --host=127.0.0.1 --port=8123
//   node pruebas/panel-evaluaciones.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), '..');

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(58)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => console.log(`\n=== ${x} ===`);
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const tinker = (linea) => execFileSync('php', ['artisan', 'tinker', '--execute', linea], { cwd: RAIZ, encoding: 'utf8' });
const ultima = (salida) => salida.trim().split('\n').pop().trim();

const sembrado = tinker("require base_path('pruebas/datos-evaluacion.php');");
const datos = sembrado.match(/EVALUACION-LISTA (\S+) (\S+) (\S+) (\S+) ids=(\S+) exp=(\S+) mot=(\S+)/);
if (!datos) { console.error('No se pudo sembrar:\n' + sembrado); process.exit(1); }
const [, ABIERTA, , , , IDS, EXP, MOT] = datos;

/** El id de la actividad con respuestas. Lo publica el propio escenario. */
const IDA = IDS.split(',')[0];

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
p.on('pageerror', (e) => errores.push(String(e)));

await p.setViewport({ width: 1440, height: 1100 });

const ir = async (url) => { await p.goto(url, { waitUntil: 'networkidle2' }); await esperar(220); };

// ── Entrar en el panel ──────────────────────────────────────

await ir(`${B}/admin/login`);
await p.type('input[name="email"]', 'admin@ong-laravel.test');
await p.type('input[name="password"]', 'admin1234');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

t('El nodo del menú');
const menu = await p.$$eval('.panel-menu a, aside a', (n) => n.map((a) => a.textContent.trim()));
di('«Evaluaciones» está en el árbol del panel', menu.some((m) => m.includes('Evaluaciones')) || menu.some((m) => m.includes('Respuestas')), '');

t('El listado de respuestas');
await ir(`${B}/admin/evaluaciones`);
di('La pantalla carga', (await p.$('.panel-tabla, table')) !== null);

const filas = await p.$$eval('tbody tr', (n) => n.length);
di('Enseña las respuestas sembradas', filas >= 5, filas + ' filas');

const columnas = await p.$$eval('thead th', (n) => n.map((c) => c.textContent.trim()).filter(Boolean));
di('Con la columna del texto libre', columnas.some((c) => c.includes('Patrimonio Social')), columnas.join(' · '));

t('Los promedios de las dos escalas');
/*
 * Filtrado por la actividad sembrada, y no sobre el total del sitio.
 *
 * Los promedios del escenario están elegidos para dar 3,00 y 4,00 exactos, pero
 * la pantalla sin filtrar mezcla las respuestas de TODAS las actividades: basta
 * una evaluación suelta de otra prueba para que el número deje de cuadrar y la
 * comprobación falle sin que haya nada roto. Que el filtro entre en la cuenta
 * se comprueba abajo, en su propia sección.
 */
await ir(`${B}/admin/evaluaciones?actividad=${IDA}`);

const tarjetas = await p.$$eval('.eval-resumen-tarjeta', (n) => n.map((c) => ({
  pregunta: c.querySelector('.eval-resumen-pregunta').textContent.trim(),
  nota: c.querySelector('.eval-resumen-nota strong')?.textContent.trim() ?? null,
  barras: c.querySelectorAll('.eval-reparto-fila').length,
})));

di('Hay una tarjeta por escala', tarjetas.length === 2, String(tarjetas.length));
di('El promedio de experiencia es el sembrado',
  tarjetas[0]?.nota === EXP.replace('.', ','), `${tarjetas[0]?.nota} (esperado ${EXP.replace('.', ',')})`);
di('El de motivación también',
  tarjetas[1]?.nota === MOT.replace('.', ','), `${tarjetas[1]?.nota} (esperado ${MOT.replace('.', ',')})`);
di('Y además del promedio se ve el reparto del 1 al 5',
  tarjetas.every((c) => c.barras === 5), tarjetas.map((c) => c.barras).join(' / '));

t('El texto largo no rompe la tabla');
const anchoTabla = await p.evaluate(() => {
  const t = document.querySelector('table');
  return { tabla: Math.round(t.getBoundingClientRect().width), ventana: window.innerWidth };
});
di('La tabla cabe en la pantalla', anchoTabla.tabla <= anchoTabla.ventana, `${anchoTabla.tabla} de ${anchoTabla.ventana}`);
di('No hay desborde horizontal en la página',
  !(await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)));

// Una palabra imposible: tiene que partirse, no estirar la columna.
const idLarga = ultima(tinker(
  "$e = App\\Models\\ActivityEvaluation::first();"
  + " $e->update(['significado' => str_repeat('Supercalifragilisticoespialidoso', 9)]);"
  + " echo $e->id;"
));
await ir(`${B}/admin/evaluaciones`);
di('Una palabra de 280 letras tampoco la estira',
  !(await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)));
tinker(`App\\Models\\ActivityEvaluation::where('id',${idLarga})->update(['significado' => 'Respuesta de prueba restaurada.']);`);

t('Los filtros');
await ir(`${B}/admin/evaluaciones?actividad=${IDA}&q=Riquelme`);
// La columna «Persona» es la CUARTA desde que el ID es la primera de todas las
// tablas del panel (P1). Iba por posición y se corrió un puesto.
const buscadas = await p.$$eval('tbody tr td:nth-child(4)', (n) => n.map((c) => c.textContent.trim()));
di('Buscar por nombre acota el listado', buscadas.length === 1 && buscadas[0].includes('Riquelme'), buscadas.join(' · '));

await ir(`${B}/admin/evaluaciones?actividad=${IDA}&q=Elena`);
const promedioFiltrado = await p.$eval('.eval-resumen-tarjeta .eval-resumen-nota strong', (n) => n.textContent.trim());
di('**Y los promedios se recalculan con el filtro**, no sobre el total',
  promedioFiltrado === '5,00', promedioFiltrado + ' (Elena puso un 5)');

await ir(`${B}/admin/evaluaciones?desde=2099-01-01`);
const vacio = await p.$$eval('tbody tr', (n) => n.length);
di('Un rango de fechas sin nada deja el listado vacío', vacio <= 1, String(vacio));
di('Y lo dice en vez de enseñar una tabla en blanco',
  (await p.$eval('tbody', (n) => n.textContent)).toLowerCase().includes('coincide'));

t('La exportación');
const descarga = await p.evaluate(async (url) => {
  const res = await fetch(url, { credentials: 'include' });
  const buf = new Uint8Array(await res.arrayBuffer());
  return {
    estado: res.status,
    tipo: res.headers.get('content-type'),
    // Un XLSX es un zip: empieza por PK.
    firma: String.fromCharCode(buf[0], buf[1]),
    bytes: buf.length,
  };
}, `${B}/admin/evaluaciones/exportar`);

di('El XLSX responde 200', descarga.estado === 200, String(descarga.estado));
di('Es un archivo de Excel de verdad, no una página de error',
  descarga.firma === 'PK', `firma «${descarga.firma}», ${descarga.bytes} bytes`);
di('Con el tipo correcto', (descarga.tipo ?? '').includes('spreadsheetml'), descarga.tipo ?? '');

const csv = await p.evaluate(async (url) => {
  const res = await fetch(url, { credentials: 'include' });
  return { estado: res.status, texto: (await res.text()).slice(0, 400) };
}, `${B}/admin/evaluaciones/exportar?formato=csv`);

di('El CSV también', csv.estado === 200, String(csv.estado));
di('Separado por punto y coma, que es lo que abre el Excel en español',
  csv.texto.includes(';'), csv.texto.split('\n')[0]?.slice(0, 90));
di('Y dice «Sí» con tilde', !/;Si;|;Si$/m.test(csv.texto), '');

t('Las fotografías, separadas por autorización');
// Dos respuestas con foto: una autorizada y otra no.
tinker(
  "$d = 'evaluaciones/pruebas'; Illuminate\\Support\\Facades\\Storage::disk('local')->put($d.'/si.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));"
  + " Illuminate\\Support\\Facades\\Storage::disk('local')->put($d.'/no.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));"
  + " $t = App\\Models\\ActivityEvaluation::orderBy('id')->take(2)->get();"
  + " $t[0]->fotos()->delete(); $t[0]->fotos()->create(['ruta' => $d.'/si.png']); $t[0]->update(['foto_autorizada' => true]);"
  + " $t[1]->fotos()->delete(); $t[1]->fotos()->create(['ruta' => $d.'/no.png']); $t[1]->update(['foto_autorizada' => false]);"
  + " echo 'LISTO';"
);

await ir(`${B}/admin/evaluaciones/fotos`);
const pestanas = await p.$$eval('.eval-pestana', (n) => n.map((a) => ({
  texto: a.textContent.replace(/\s+/g, ' ').trim(),
  activa: a.classList.contains('activa'),
})));
di('Hay dos pestañas', pestanas.length === 2, pestanas.map((x) => x.texto).join(' | '));
di('Se abre en las autorizadas', pestanas[0]?.activa === true);

const avisoOk = await p.$('.alert-ok');
di('Dice que ésas sí se pueden usar', avisoOk !== null);
di('Y enseña una foto', (await p.$$eval('.eval-foto', (n) => n.length)) === 1, '');
di('Con el botón de pasarla a la biblioteca', (await p.$('form[action*="biblioteca"] button')) !== null);

await ir(`${B}/admin/evaluaciones/fotos?estado=sin-autorizar`);
const avisoMal = await p.$('.alert-error');
di('En la otra pestaña avisa de que NO se pueden publicar', avisoMal !== null);
di('El aviso está arriba del todo, antes de ninguna foto',
  await p.evaluate(() => {
    const aviso = document.querySelector('.alert-error');
    const foto = document.querySelector('.eval-foto');
    return !!aviso && (!foto || aviso.getBoundingClientRect().top < foto.getBoundingClientRect().top);
  }));
di('Y no ofrece pasarla a la biblioteca', (await p.$('form[action*="biblioteca"]')) === null);

t('La foto se sirve desde el disco privado, con sesión');
// La foto ya no es una columna de la evaluación: es una fila de su tabla, y
// la ruta que la sirve va por el id de la FOTO.
const conFoto = ultima(tinker("echo App\\Models\\EvaluationPhoto::orderBy('id')->value('id');"));

const conSesion = await p.evaluate(async (url) => {
  const res = await fetch(url, { credentials: 'include' });
  return { estado: res.status, tipo: res.headers.get('content-type') };
}, `${B}/admin/evaluaciones/fotos/${conFoto}`);
di('Con sesión de admin, la foto se ve', conSesion.estado === 200 && (conSesion.tipo ?? '').includes('image'), `${conSesion.estado} ${conSesion.tipo}`);

// Y sin sesión, no. Es la comprobación que justifica el disco privado.
const anonima = await nav.createBrowserContext();
const pAnon = await anonima.newPage();
const sinSesion = await pAnon.goto(`${B}/admin/evaluaciones/fotos/${conFoto}`, { waitUntil: 'networkidle2' });
di('**Sin sesión NO se puede ver**', sinSesion.status() !== 200 || pAnon.url().includes('login'),
  `${sinSesion.status()} → ${pAnon.url().replace(B, '')}`);
await anonima.close();

t('Pasar una foto autorizada a la biblioteca');
const mediosAntes = Number(ultima(tinker('echo App\\Models\\Media::count();')));
await ir(`${B}/admin/evaluaciones/fotos`);
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.click('form[action*="biblioteca"] button'),
]);
di('Avisa de que ya está en la biblioteca', (await p.$('.alert-ok, [class*=flash]')) !== null);
di('Y hay un medio más', Number(ultima(tinker('echo App\\Models\\Media::count();'))) === mediosAntes + 1);

t('Borrar una respuesta');
const antesBorrar = Number(ultima(tinker('echo App\\Models\\ActivityEvaluation::count();')));
await ir(`${B}/admin/evaluaciones`);
await p.click('tbody tr:last-child button[class*="danger"]');
await esperar(400);
di('Pide confirmación antes de borrar', (await p.$eval('body', (b) => b.textContent)).includes('¿Eliminar esta evaluación?'));

await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.evaluate(() => [...document.querySelectorAll('button, a')].find((b) => b.textContent.trim() === 'Sí, eliminar')?.click()),
]);
di('Y al confirmar se va de verdad',
  Number(ultima(tinker('echo App\\Models\\ActivityEvaluation::count();'))) === antesBorrar - 1);

t('Sin errores de JavaScript');
di('La consola quedó limpia', errores.length === 0, errores.slice(0, 2).join(' | '));

await nav.close();

// Deja el escenario limpio para la siguiente pasada.
tinker("Illuminate\\Support\\Facades\\Storage::disk('local')->deleteDirectory('evaluaciones/pruebas');");

console.log(`\n${ok} bien · ${mal} mal`);
process.exit(mal ? 1 : 0);
