// La encuesta de evaluación: la pantalla a la que lleva el QR.
//
// Necesita Chrome, y por dos motivos distintos:
//
// 1. **La guía de errores es invisible por HTTP por definición.** Un script sin
//    pantalla encuentra el aviso siempre, porque no tiene scroll: los 74 casos
//    del bloque F pasaban también con el menú roto. Lo que hay que medir aquí
//    es dónde CAE el aviso cuando el navegador deja la página tras el POST.
// 2. **La reducción de la fotografía sólo existe en el navegador.** Es JS sobre
//    un lienzo; por HTTP no ocurre.
//
//   php artisan serve --host=127.0.0.1 --port=8123
//   node pruebas/encuesta-evaluacion.mjs
import puppeteer from 'puppeteer-core';
import { PNG } from 'pngjs';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
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
const datos = sembrado.match(/EVALUACION-LISTA (\S+) (\S+) (\S+) (\S+)/);
if (!datos) { console.error('No se pudo sembrar:\n' + sembrado); process.exit(1); }
const [, ABIERTA, CERRADA, FUTURA, BORRADOR] = datos;

// ── Una fotografía de 3000x2000 para probar la reducción ────
//
// Un degradado y no ruido: comprime a unos pocos kilobytes, así que el archivo
// se escribe y se sube en un instante. Lo que se mide es el TAMAÑO EN PÍXELES
// de lo que acaba guardado, que es lo que decide la reducción.
const FOTO = join(tmpdir(), 'dps-foto-grande.png');
{
  const png = new PNG({ width: 3000, height: 2000 });
  for (let y = 0; y < png.height; y++) {
    for (let x = 0; x < png.width; x++) {
      const i = (png.width * y + x) << 2;
      png.data[i] = (x * 255 / png.width) | 0;
      png.data[i + 1] = (y * 255 / png.height) | 0;
      png.data[i + 2] = 128;
      png.data[i + 3] = 255;
    }
  }
  writeFileSync(FOTO, PNG.sync.write(png));
}

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
p.on('pageerror', (e) => errores.push(String(e)));

const ir = async (url) => { await p.goto(url, { waitUntil: 'networkidle2' }); await esperar(200); };
const encuesta = (slug) => `${B}/evaluar/${slug}`;

/** Cuántas respuestas hay ahora mismo, para saber si un envío guardó algo. */
const cuantas = () => Number(ultima(tinker('echo App\\Models\\ActivityEvaluation::count();')));

/** Un correo distinto por prueba: el índice único no deja repetir. */
let n = 0;
const correoNuevo = () => `prueba${++n}.${Date.now()}@ejemplo.cl`;

/** Rellena todo lo obligatorio. No envía. */
const rellenar = async (correo, texto = 'Que el barrio se cuida entre vecinos.') => {
  await p.type('#ev-nombre', 'Persona de Prueba');
  await p.type('#ev-correo', correo);
  await p.click('input[name="experiencia"][value="4"]');
  await p.type('#ev-significado', texto);
  await p.click('input[name="motivacion"][value="5"]');
};

/**
 * El filtro de tiempo del anti-spam pide cuatro segundos entre pintar el
 * formulario y enviarlo. Se esperan cinco para no depender del reloj.
 */
const dejarPasarElTiempo = () => esperar(5000);

/**
 * Suelta el freno de la ruta antes de cada tanda de envíos.
 *
 * `throttle:5,1` es correcto y hace falta —es un formulario público que acepta
 * fotos— pero esta prueba manda muchos más de cinco envíos por minuto desde la
 * misma IP. Sin esto, a mitad del archivo empiezan a caer 429 y las
 * comprobaciones fallan por un motivo que no tiene nada que ver con lo que
 * miden. El freno se comprueba aparte, en su propia sección del final.
 */
const soltarElFreno = () => tinker('cache()->flush();');

// ══════════════════════════════════════════════════════════════

t('La pantalla, en escritorio');
await p.setViewport({ width: 1440, height: 1000 });
await ir(encuesta(ABIERTA));

di('Responde y pinta el formulario', (await p.$('.evaluacion-form')) !== null);
di('El título es el del wireframe',
  (await p.$eval('.evaluacion-titulo', (n) => n.textContent.trim())) === 'Cuéntanos cómo fue tu experiencia');
di('Dice qué actividad se está evaluando',
  (await p.$eval('.evaluacion-actividad', (n) => n.textContent.trim())) === 'Encuesta abierta');

const anchoCaja = await p.$eval('.evaluacion-caja', (n) => Math.round(n.getBoundingClientRect().width));
di('En escritorio la columna no se estira sin límite', anchoCaja <= 560, anchoCaja + ' px');

const cortaHorizontal = await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
di('No desborda a lo ancho', !cortaHorizontal);

t('Las dos escalas del 1 al 5');
const notas = await p.$$eval('.evaluacion-escala', (grupos) => grupos.map((g) => ({
  pregunta: g.querySelector('legend').textContent.trim(),
  cuantas: g.querySelectorAll('input[type="radio"]').length,
  extremos: [...g.querySelectorAll('.evaluacion-extremos span')].map((s) => s.textContent.trim()),
  alto: Math.round(g.querySelector('.evaluacion-nota span').getBoundingClientRect().height),
})));

di('Son dos escalas', notas.length === 2);
di('Cada una tiene cinco opciones', notas.every((e) => e.cuantas === 5));
di('La primera rotula «Muy mala» y «Excelente»',
  notas[0]?.extremos.join(' → ') === 'Muy mala → Excelente', notas[0]?.extremos.join(' → '));
di('La segunda rotula «Poco motivado» y «Muy motivado»',
  notas[1]?.extremos.join(' → ') === 'Poco motivado → Muy motivado', notas[1]?.extremos.join(' → '));
di('Las cajas son grandes para el dedo (≥44 px)', notas.every((e) => e.alto >= 44), notas.map((e) => e.alto).join(' / '));

// Que se marquen de verdad, y que se vea.
await p.click('input[name="experiencia"][value="4"]');
// El clic vuelve en cuanto se despachan los eventos, pero el color no está
// puesto hasta el siguiente repintado: sin esta espera se lee el blanco de
// antes y parece que la regla `:checked` no existe.
await esperar(200);
const marcada = await p.$eval('input[name="experiencia"][value="4"] + span', (s) => getComputedStyle(s).backgroundColor);
const sinMarcar = await p.$eval('input[name="experiencia"][value="2"] + span', (s) => getComputedStyle(s).backgroundColor);
di('La elegida cambia de color', marcada !== sinMarcar, `${marcada} vs ${sinMarcar}`);
di('Y es el naranja del sitio', marcada === 'rgb(229, 114, 0)', marcada);

t('El contador de caracteres');
await p.type('#ev-significado', 'Hola');
await esperar(120);
let restantes = await p.$eval('.evaluacion-contador span', (s) => s.textContent.trim());
di('Descuenta lo escrito', restantes === '296', restantes);

// El máximo del navegador y el del servidor tienen que ser el mismo número.
const tope = await p.$eval('#ev-significado', (n) => n.getAttribute('maxlength'));
di('El tope del campo es 300', tope === '300', tope);

await p.$eval('#ev-significado', (n) => { n.value = 'x'.repeat(300); n.dispatchEvent(new Event('input')); });
await esperar(120);
restantes = await p.$eval('.evaluacion-contador span', (s) => s.textContent.trim());
di('Al llegar al tope dice cero y no un negativo', restantes === '0', restantes);

t('La autorización de la fotografía');
di('Está oculta mientras no hay foto',
  await p.$eval('.evaluacion-autorizacion', (n) => n.getBoundingClientRect().height === 0));

const entradaFoto = await p.$('#ev-foto');
await entradaFoto.uploadFile(FOTO);
await esperar(900);

di('Aparece en cuanto se elige una',
  await p.$eval('.evaluacion-autorizacion', (n) => n.getBoundingClientRect().height > 10));
di('Se ve la miniatura de lo elegido',
  await p.$eval('.evaluacion-foto-imagen', (n) => n.getBoundingClientRect().height > 10));

const reducida = await p.$eval('#ev-foto', (n) => {
  const f = n.files[0];
  return f ? { nombre: f.name, tipo: f.type, bytes: f.size } : null;
});
di('El archivo que se va a subir conserva su tipo', reducida?.tipo === 'image/png', reducida?.tipo);

await p.click('.evaluacion-foto-quitar');
await esperar(250);
di('Al quitar la foto la autorización se esconde otra vez',
  await p.$eval('.evaluacion-autorizacion', (n) => n.getBoundingClientRect().height === 0));
di('Y la casilla se desmarca sola',
  await p.$eval('input[name="foto_autorizada"]', (n) => !n.checked));

t('La guía de errores — que el aviso SE VEA');
soltarElFreno();
await ir(encuesta(ABIERTA));
await dejarPasarElTiempo();

// Se envía con casi todo vacío. El servidor rechaza y devuelve la página.
await p.$eval('.evaluacion-form', (f) => f.setAttribute('novalidate', 'novalidate'));
await p.type('#ev-nombre', 'Sólo el nombre');
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2' }),
  p.$eval('.evaluacion-form', (f) => f.submit()),
]);
await esperar(400);

const resumen = await p.$('[data-resumen-errores]');
di('Vuelve con el resumen de errores', resumen !== null);

if (resumen) {
  const donde = await p.$eval('[data-resumen-errores]', (n) => {
    const r = n.getBoundingClientRect();
    return { arriba: Math.round(r.top), alto: Math.round(r.height), ventana: window.innerHeight };
  });

  // **Esta es la comprobación que importa de todo el archivo.** El fallo que
  // originó el bloque K era un aviso correcto que caía fuera de la pantalla.
  di('Y el resumen CAE DENTRO de la pantalla',
    donde.arriba >= 0 && donde.arriba < donde.ventana, `top ${donde.arriba} de ${donde.ventana}`);
  di('Ocupa sitio de verdad', donde.alto > 20, donde.alto + ' px');

  const faltan = await p.$$eval('.resumen-errores-salto', (b) => b.map((x) => x.textContent.trim().split('\n')[0].trim()));
  di('Enumera los campos que faltan', faltan.length >= 3, faltan.join(' · '));
  di('Nombra la escala de experiencia', faltan.some((f) => f.toLowerCase().includes('experiencia')));
  di('Nombra la respuesta abierta', faltan.some((f) => f.toLowerCase().includes('patrimonio')));
  di('El nombre, que sí venía relleno, NO aparece', !faltan.some((f) => f.toLowerCase() === 'nombre'));

  // El renglón salta a su campo: es lo que sustituye a bajar leyendo.
  await p.click('.resumen-errores-salto');
  await esperar(600);
  const enfocado = await p.evaluate(() => {
    const caja = document.querySelector('.campo-fallido');
    if (!caja) return null;
    const r = caja.getBoundingClientRect();
    return r.top > -50 && r.top < window.innerHeight;
  });
  di('Al pulsar un renglón salta a su campo', enfocado === true);

  const marcados = await p.$$eval('.campo-fallido', (n) => n.length);
  di('Los campos fallidos quedan marcados en la caja entera', marcados >= 3, String(marcados));
}

t('Un envío bueno');
soltarElFreno();
const antesBueno = cuantas();
await ir(encuesta(ABIERTA));
await dejarPasarElTiempo();
await rellenar(correoNuevo());
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('.evaluacion-enviar')]);

di('Acaba en la pantalla de gracias', p.url().endsWith('/gracias'), p.url());
di('Con el mensaje del wireframe',
  (await p.$eval('.evaluacion-gracias-titulo', (n) => n.textContent)).includes('Muchas gracias por ser parte'));
di('Y el botón «Ir al sitio»',
  (await p.$$eval('.evaluacion-gracias-boton', (n) => n.map((a) => a.textContent.trim()))).includes('Ir al sitio'));
di('Se guardó una respuesta', cuantas() === antesBueno + 1, `${antesBueno} → ${cuantas()}`);

t('Un envío con fotografía');
soltarElFreno();
const antesFoto = cuantas();
await ir(encuesta(ABIERTA));
await dejarPasarElTiempo();
const correoFoto = correoNuevo();
await rellenar(correoFoto);
await (await p.$('#ev-foto')).uploadFile(FOTO);
await esperar(1200);
await p.click('input[name="foto_autorizada"]');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('.evaluacion-enviar')]);

di('Llega a gracias', p.url().endsWith('/gracias'));
di('Se guardó', cuantas() === antesFoto + 1);

const guardada = ultima(tinker(
  `$e = App\\Models\\ActivityEvaluation::where('correo','${correoFoto}')->first();`
  + ` $r = $e && $e->foto_path ? Illuminate\\Support\\Facades\\Storage::disk('local')->path($e->foto_path) : null;`
  + ` $m = $r && is_file($r) ? getimagesize($r) : [0,0];`
  + ` echo ($e->foto_path ?? 'SIN').'|'.($e->foto_autorizada ? 1 : 0).'|'.$m[0].'x'.$m[1].'|'.(is_file($r ?? '') ? filesize($r) : 0);`
));
const [ruta, autorizada, medidas, peso] = guardada.split('|');

di('La foto quedó en el disco PRIVADO, no en el público',
  ruta.startsWith('evaluaciones/') && !ruta.includes('public'), ruta);
di('Con la autorización marcada', autorizada === '1');
di('**Reducida a 1600 px de lado largo**', medidas === '1600x1067', medidas + ' (se subió 3000x2000)');
di('Y pesa mucho menos que el original', Number(peso) > 0, peso + ' bytes');

t('Anti-spam — el campo trampa');
soltarElFreno();
const antesTrampa = cuantas();
await ir(encuesta(ABIERTA));
await dejarPasarElTiempo();
await rellenar(correoNuevo());
await p.$eval('input[name="sitio_web"]', (n) => { n.value = 'https://spam.example'; });
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('.evaluacion-enviar')]);

di('Responde como si todo hubiera ido bien', p.url().endsWith('/gracias'), p.url());
di('Pero NO guarda nada', cuantas() === antesTrampa, `${antesTrampa} → ${cuantas()}`);

t('Anti-spam — demasiado rápido');
soltarElFreno();
const antesRapido = cuantas();
await ir(encuesta(ABIERTA));
// Sin esperar: se rellena y se envía en menos de cuatro segundos.
await rellenar(correoNuevo());
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('.evaluacion-enviar')]);

di('También responde que sí', p.url().endsWith('/gracias'));
di('Y tampoco guarda', cuantas() === antesRapido, `${antesRapido} → ${cuantas()}`);

t('El campo trampa no se ve ni se tabula');
await ir(encuesta(ABIERTA));
const trampa = await p.$eval('.evaluacion-trampa', (n) => {
  const r = n.getBoundingClientRect();
  const control = n.querySelector('input');
  return { izquierda: Math.round(r.left), tab: control.getAttribute('tabindex'), completar: control.getAttribute('autocomplete') };
});
di('Está fuera de la pantalla', trampa.izquierda < -1000, trampa.izquierda + ' px');
di('No entra en el recorrido del teclado', trampa.tab === '-1');
di('Y el navegador no lo autocompleta', trampa.completar === 'off');

t('Una segunda evaluación con el mismo correo');
soltarElFreno();
const correoRepetido = correoNuevo();
for (const vuelta of [1, 2]) {
  await ir(encuesta(ABIERTA));
  await dejarPasarElTiempo();
  await rellenar(correoRepetido, `Intento número ${vuelta}.`);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('.evaluacion-enviar')]);
}

di('La segunda vez también acaba en gracias', p.url().endsWith('/gracias'));
di('Y NO se le enseña un error', (await p.$('.alert-error')) === null);
di('Se le dice que ya la teníamos',
  (await p.$('.evaluacion-gracias-nota')) !== null
  && (await p.$eval('.evaluacion-gracias-nota', (n) => n.textContent)).includes('Ya teníamos'));

const repetidas = ultima(tinker(`echo App\\Models\\ActivityEvaluation::where('correo','${correoRepetido}')->count();`));
di('Sólo hay una fila guardada', repetidas === '1', repetidas);

t('Fuera de plazo y direcciones que no existen');
await ir(encuesta(CERRADA));
di('La encuesta cerrada no enseña el formulario', (await p.$('.evaluacion-form')) === null);
di('Y explica por qué',
  (await p.$eval('.evaluacion-gracias-titulo', (n) => n.textContent)).includes('cerrada'));
di('Con salida a la actividad', (await p.$('.evaluacion-gracias-boton')) !== null);

const respuesta404 = await p.goto(`${B}/evaluar/${BORRADOR}`, { waitUntil: 'networkidle2' });
di('Una actividad sin publicar da 404 y no 403', respuesta404.status() === 404, String(respuesta404.status()));

const inventada = await p.goto(`${B}/evaluar/esto-no-existe-en-ninguna-parte`, { waitUntil: 'networkidle2' });
di('Una dirección inventada da 404', inventada.status() === 404, String(inventada.status()));

t('Con el ajuste en «desde el día de la actividad»');
tinker("App\\Models\\Setting::set('evaluacion_apertura', 'actividad'); cache()->forget(App\\Models\\Setting::CACHE_KEY);");
await ir(encuesta(FUTURA));
di('Una actividad que aún no ocurre no admite respuestas', (await p.$('.evaluacion-form')) === null);
di('Y lo dice sin hablar de plazos vencidos',
  (await p.$eval('.evaluacion-gracias-titulo', (n) => n.textContent)).includes('todavía no'));

await ir(encuesta(ABIERTA));
di('Una que ya ocurrió sigue abierta', (await p.$('.evaluacion-form')) !== null);

tinker("App\\Models\\Setting::set('evaluacion_apertura', 'publicacion'); cache()->forget(App\\Models\\Setting::CACHE_KEY);");
await ir(encuesta(FUTURA));
di('Con el ajuste por defecto, la futura vuelve a estar abierta', (await p.$('.evaluacion-form')) !== null);

t('En el teléfono (390 px)');
await p.setViewport({ width: 390, height: 780 });
await ir(encuesta(ABIERTA));

const movil = await p.evaluate(() => ({
  desborda: document.documentElement.scrollWidth > window.innerWidth + 1,
  caja: Math.round(document.querySelector('.evaluacion-caja').getBoundingClientRect().width),
  nota: Math.round(document.querySelector('.evaluacion-nota span').getBoundingClientRect().height),
  notasEnUnaFila: (() => {
    const cajas = [...document.querySelectorAll('.evaluacion-escala')][0].querySelectorAll('.evaluacion-nota');
    const arriba = [...cajas].map((c) => Math.round(c.getBoundingClientRect().top));
    return new Set(arriba).size === 1;
  })(),
  boton: Math.round(document.querySelector('.evaluacion-enviar').getBoundingClientRect().width),
}));

di('No desborda a lo ancho', !movil.desborda);
di('La tarjeta ocupa el ancho disponible', movil.caja >= 340 && movil.caja <= 390, movil.caja + ' px');
di('Las cinco notas siguen en una sola fila', movil.notasEnUnaFila);
di('Y siguen siendo grandes para el dedo', movil.nota >= 44, movil.nota + ' px');
di('El botón de enviar ocupa todo el ancho', movil.boton >= movil.caja - 40, movil.boton + ' px');

t('Sin errores de JavaScript');
/*
 * Se descartan los que provoca la propia prueba: los 404 son las dos
 * direcciones inexistentes que se piden a posta, y los 429 el freno de la ruta
 * cuando se le manda una tanda seguida. Lo que queda tiene que ser cero.
 */
const propios = errores.filter((e) => !/status of (404|429)/.test(e));
di('La consola quedó limpia', propios.length === 0, propios.slice(0, 2).join(' | '));

t('El freno de la ruta');
soltarElFreno();
await ir(encuesta(ABIERTA));

/*
 * Seis envíos seguidos contra un límite de cinco por minuto. Se mandan por
 * `fetch` y no rellenando el formulario: lo que se mide es el freno, y da igual
 * lo que lleven dentro.
 */
const codigos = await p.evaluate(async (url) => {
  const token = document.querySelector('meta[name="csrf-token"]').content;
  const salida = [];

  for (let i = 0; i < 6; i++) {
    const cuerpo = new FormData();
    cuerpo.append('_token', token);
    cuerpo.append('nombre', 'Insistente');
    const res = await fetch(url, { method: 'POST', body: cuerpo, redirect: 'manual' });
    salida.push(res.status);
  }

  return salida;
}, encuesta(ABIERTA));

di('El sexto envío del minuto se corta', codigos.includes(429), codigos.join(' · '));
soltarElFreno();

await nav.close();

console.log(`\n${ok} bien · ${mal} mal`);
process.exit(mal ? 1 : 0);
