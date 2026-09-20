// P16 — sugerencias de dirección con Photon, y el punto en el mapa.
//
// Lo que pidió el punto, y lo que se comprueba:
//
//   · que hay sugerencias de verdad mientras se escribe;
//   · que elegir una guarda LATITUD y LONGITUD;
//   · que el enlace del mapa de la ficha usa ese punto y no la cadena;
//   · y que **el campo sigue aceptando texto libre**: la sugerencia ayuda, no
//     bloquea el envío. Eso último es la condición que puso el encargo.
//
//   node pruebas/direcciones-photon.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultimaLinea = (texto) => texto.split('\n').filter((x) => x.trim()).pop().trim();

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const alPaso = async (n) => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate((paso) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = paso; }, n);
  await esperar(300);
};
const dato = (clave) => p.evaluate((c) => Alpine.$data(document.querySelector('[x-data^="wizard"]'))[c], clave);

/* ═══════════════ El servicio ═════════════════════════════════════ */

t('P16 — el geocodificador responde');

const respuesta = await p.evaluate(async (u) => {
  const r = await fetch(u);

  return { estado: r.status, datos: await r.json() };
}, `${B}/direcciones/buscar?q=avenida+providencia`).catch(() => null)
  ?? await (async () => {
    await p.goto(`${B}/`, { waitUntil: 'domcontentloaded' });

    return p.evaluate(async (u) => {
      const r = await fetch(u);

      return { estado: r.status, datos: await r.json() };
    }, `${B}/direcciones/buscar?q=avenida+providencia`);
  })();

di('El punto de entrada responde', respuesta.estado === 200);

const primera = respuesta.datos?.direcciones?.[0];
di('Con sugerencias de verdad', !! primera, primera?.etiqueta ?? '(ninguna)');
di('Y cada una trae su punto',
  !! primera && typeof primera.latitud === 'number' && typeof primera.longitud === 'number',
  primera ? `${primera.latitud}, ${primera.longitud}` : '');
di('**Dentro de Chile, no en otro país**',
  !! primera && primera.latitud < -17 && primera.latitud > -56 && primera.longitud < -66 && primera.longitud > -76,
  primera ? `lat ${primera.latitud}` : '');

// El orden de Photon es [longitud, latitud]; invertirlo deja el punto en el
// océano Índico y no lo nota nadie hasta que abre el mapa.
di('Latitud y longitud no están cambiadas',
  !! primera && Math.abs(primera.latitud) < Math.abs(primera.longitud),
  primera ? `|${primera.latitud}| < |${primera.longitud}|` : '');

const cortas = await p.evaluate(async (u) => (await (await fetch(u)).json()).direcciones,
  `${B}/direcciones/buscar?q=av`);
di('Con menos de tres letras no se consulta nada', Array.isArray(cortas) && cortas.length === 0);

/* ═══════════════ En el formulario ════════════════════════════════ */

t('En el wizard: escribir, elegir, y que quede el punto');

await alPaso(4);

await p.type('input[name="direccion"]', 'Avenida Providencia');
await p.waitForFunction(() => document.querySelectorAll('.org-sugerencia').length > 0, { timeout: 9000 })
  .catch(() => null);

const sugeridas = await p.$$eval('.org-sugerencia', (n) => n.map((b) => b.innerText.replace(/\s+/g, ' ').trim()));
di('Salen sugerencias mientras se escribe', sugeridas.length > 0, sugeridas[0] ?? '(ninguna)');

di('Antes de elegir no hay punto', (await dato('latitud')) === '');

await p.evaluate(() => document.querySelector('.org-sugerencia')?.click());
await esperar(350);

const lat = await dato('latitud');
const lon = await dato('longitud');

di('**Al elegir una queda guardado el punto**', typeof lat === 'number' && typeof lon === 'number', `${lat}, ${lon}`);
di('Y viaja en el formulario',
  await p.$eval('input[name="latitud"]', (n) => n.value) !== ''
  && await p.$eval('input[name="longitud"]', (n) => n.value) !== '',
  await p.$eval('input[name="latitud"]', (n) => n.value));
di('Se le dice que la ubicación quedó fijada',
  /Ubicación exacta guardada/.test(await p.evaluate(() => document.body.innerText)));

/*
 * Y si después cambia la dirección a mano, el punto se olvida. Dejarlo sería
 * peor que no tenerlo: el mapa llevaría a un sitio que no es el que dice el
 * texto.
 */
await p.type('input[name="direccion"]', ' esquina Pedro de Valdivia');
await esperar(300);
di('**Editar la dirección a mano olvida el punto**', (await dato('latitud')) === '',
  String(await dato('latitud')));

/* ═══════════════ No bloquea ══════════════════════════════════════ */

t('El campo sigue aceptando texto libre: la sugerencia no obliga');

await alPaso(4);
await p.type('input[name="direccion"]', 'Metro Salvador, salida norte');
await esperar(900);

di('Se puede escribir una dirección que no es una calle',
  await p.$eval('input[name="direccion"]', (n) => n.value) === 'Metro Salvador, salida norte');

const faltaDireccion = await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]'))
  .camposQueFaltan(4).some((e) => e.campo === 'direccion'));
di('**Y la revisión previa la da por buena, sin punto**', faltaDireccion === false);

/* ═══════════════ El enlace del mapa ══════════════════════════════ */

t('El enlace del mapa usa el punto, no la cadena');

/*
 * Se guarda la dirección que tenía para devolvérsela al terminar: esta prueba
 * escribe sobre una actividad sembrada, y dejarla con «Metro Salvador» haría
 * fallar en falso a cualquier suite que mire ese dato después.
 */
const original = ultimaLinea(tinker(
  `$a = App\\Models\\Activity::published()->where('formato','!=','Online')->first();`
  + ` echo $a->id.'|'.($a->direccion ?? '');`
));

const conPunto = ultimaLinea(tinker(
  `$a = App\\Models\\Activity::published()->where('formato','!=','Online')->first();`
  + ` $a->forceFill(['direccion' => 'Metro Salvador, salida norte', 'latitud' => -33.4265, 'longitud' => -70.6250])->save();`
  + ` echo $a->id.'|'.$a->slug.'|'.$a->mapa_url;`
));
const [id, slug, url] = conPunto.split('|');

di('Con punto, el enlace lleva a las coordenadas', /query=-33\.4265/.test(url), url);
di('Y no a la cadena de texto', ! /Metro/.test(url));

await p.goto(`${B}/activity/${id}/${slug}`, { waitUntil: 'networkidle2' });
const enlace = await p.evaluate(() => {
  const a = [...document.querySelectorAll('a')].find((x) => /Ver en el mapa/.test(x.textContent));

  return a ? { href: a.getAttribute('href'), texto: a.textContent.trim(), alto: a.getBoundingClientRect().height } : null;
});

di('La ficha pública lo enseña', !! enlace && enlace.alto > 0, enlace?.texto);
di('Apuntando al punto', !! enlace && /query=-33\.4265/.test(enlace.href));
di('Y sale a un sitio de fuera con cuidado',
  await p.evaluate(() => {
    const a = [...document.querySelectorAll('a')].find((x) => /Ver en el mapa/.test(x.textContent));

    return (a?.getAttribute('rel') ?? '').includes('noopener') && a?.getAttribute('target') === '_blank';
  }));

// Y sin punto, se cae a la búsqueda por texto y lo dice.
const sinPunto = ultimaLinea(tinker(
  `$a = App\\Models\\Activity::find(${id});`
  + ` $a->forceFill(['latitud' => null, 'longitud' => null])->save();`
  + ` echo $a->fresh()->mapa_url;`
));
di('Sin punto se cae a la búsqueda por texto', /Metro\+Salvador|Metro%20Salvador/.test(sinPunto), sinPunto.slice(0, 90));

await p.goto(`${B}/activity/${id}/${slug}`, { waitUntil: 'networkidle2' });
di('Y la ficha avisa de que es aproximada',
  /búsqueda aproximada/.test(await p.evaluate(() => document.body.innerText)));

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

// Y se deja la actividad como estaba.
const [idOriginal, dirOriginal] = original.split('|');
tinker(
  `App\\Models\\Activity::where('id',${idOriginal})`
  + `->update(['direccion' => '${dirOriginal.replace(/'/g, '')}', 'latitud' => null, 'longitud' => null]);`
  + ` echo 'restaurada';`
);

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
