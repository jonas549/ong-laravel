// Puntos 3, 8 y 9 del 08/10 — el home.
//
//   3 · El bloque de actividades sólo enseña las que no han pasado.
//   8 · Destacadas: fuera las que ya pasaron, AUNQUE estén destacadas a mano
//       (la fecha manda). Lo que el home elige solo deja fuera también las
//       cerradas; una cerrada destacada a mano sí sale (eso no cambia).
//   9 · El botón de «¿Qué es el Patrimonio Social?» lleva al sitio del DPS,
//       en otra pestaña.
//
// Monta cinco actividades con un sello, apaga las destacadas que hubiera,
// prueba los dos modos de la sección y lo deja todo como estaba. Lee el HTML
// que pinta el servidor: el carrusel no depende de JavaScript para elegir.
//
//   node pruebas/home-actividades-proximas.mjs
//
// Contra producción NO: crea actividades y toca la sección del home.
import { execFileSync } from 'node:child_process';

const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();

const SELLO = Date.now();
const slug = (k) => `home-${k}-${SELLO}`;

/*
 * Las cinco, con fechas relativas a hoy en Chile:
 *   pasada-dest   — pasó hace 10 días, destacada a mano      → nunca
 *   cerrada-dest  — dentro de 5 días, cerrada, destacada     → sí en «destacadas»
 *   varios-dest   — empezó hace 3 días y termina en 3, dest. → sí (no ha pasado)
 *   proxima       — dentro de 7 días, abierta, sin destacar  → sí en el automático
 *   pasada        — pasó ayer, abierta, sin destacar         → nunca
 */
const ACTIVIDADES = {
  'pasada-dest': { ini: -10, fin: null, destacada: true, cerrada: false },
  'cerrada-dest': { ini: 5, fin: null, destacada: true, cerrada: true },
  'varios-dest': { ini: -3, fin: 3, destacada: true, cerrada: false },
  proxima: { ini: 7, fin: null, destacada: false, cerrada: false },
  pasada: { ini: -1, fin: null, destacada: false, cerrada: false },
};

const fila = ultima(tinker(`$s = App\\Models\\HomeSection::where('clave','actividades')->first(); echo $s ? json_encode(['c' => $s->contenido]) : 'null';`));
const destacadasAntes = ultima(tinker(`echo implode(',', App\\Models\\Activity::where('destacada', true)->pluck('id')->all()) ?: '-';`));

const ponerSeccion = (seleccion) => tinker(
  `$s = App\\Models\\HomeSection::firstOrCreate(['clave' => 'actividades'], ['orden' => 50, 'activo' => true]);`
  + ` $s->contenido = array_merge($s->contenido ?? [], ['seleccion' => '${seleccion}', 'cuantas' => 24]); $s->save(); echo 'OK';`);

const carrusel = async () => {
  const html = await (await fetch(`${B}/`)).text();
  const i = html.indexOf('data-carousel="act"');
  const trozo = i < 0 ? '' : html.slice(i, html.indexOf('</section>', i));

  return Object.fromEntries(Object.keys(ACTIVIDADES).map((k) => [k, trozo.includes(`/${slug(k)}"`)]));
};

try {
  for (const [k, a] of Object.entries(ACTIVIDADES)) {
    const salida = tinker(
      `$z = App\\Support\\Fecha::zona(); $o = App\\Models\\Activity::where('estado','publicada')->firstOrFail();`
      + ` $a = $o->replicate(); $a->forceFill(['titulo' => 'Home ${k} ${SELLO}', 'slug' => '${slug(k)}', 'estado' => 'publicada', 'published_at' => now(),`
      + ` 'sin_fecha_definida' => false, 'orden' => 0, 'fecha_inicio' => now($z)->addDays(${a.ini})->toDateString(),`
      + ` 'fecha_termino' => ${a.fin === null ? 'null' : `now($z)->addDays(${a.fin})->toDateString()`},`
      + ` 'destacada' => ${a.destacada}, 'cerrada' => ${a.cerrada}])->save(); echo 'OK';`);
    di(`escenario: ${k}`, ultima(salida) === "OK", ultima(salida));
  }
  tinker(`App\\Models\\Activity::where('destacada', true)->where('slug', 'not like', 'home-%-${SELLO}')->update(['destacada' => false]); echo 'OK';`);

  t('Modo «destacadas», con destacadas a mano');

  ponerSeccion('destacadas');
  let c = await carrusel();
  di('**una destacada a mano que ya pasó NO sale**', ! c['pasada-dest']);
  di('una destacada de varios días que sigue en curso sí sale', c['varios-dest']);
  di('una cerrada destacada a mano sí sale (el panel manda)', c['cerrada-dest']);
  di('las no destacadas no salen mientras haya destacadas', ! c.proxima && ! c.pasada);

  t('Modo «destacadas», sin ninguna vigente: cae a las próximas');

  tinker(`App\\Models\\Activity::whereIn('slug', ['${slug('cerrada-dest')}', '${slug('varios-dest')}'])->update(['destacada' => false]); echo 'OK';`);
  c = await carrusel();
  di('**con sólo una destacada pasada, cae a las próximas**', c.proxima, JSON.stringify(c));
  di('sin la pasada destacada', ! c['pasada-dest']);
  di('sin la que pasó ayer', ! c.pasada);
  di('sin la cerrada (no destacada)', ! c['cerrada-dest']);
  di('con la de varios días en curso', c['varios-dest']);

  t('Modo «próximas»');

  ponerSeccion('proximas');
  c = await carrusel();
  di('salen las próximas abiertas', c.proxima && c['varios-dest']);
  di('**ninguna de las que ya pasaron**', ! c.pasada && ! c['pasada-dest']);
  di('ni las cerradas', ! c['cerrada-dest']);

  t('El listado de /actividades no cambia');

  const listado = await (await fetch(`${B}/actividades?q=${encodeURIComponent(`Home pasada ${SELLO}`)}`)).text();
  di('una pasada sigue en el listado público', listado.includes(`/${slug("pasada")}"`));

  t('Punto 9 · «¿Qué es el Patrimonio Social?»');

  const html = await (await fetch(`${B}/`)).text();
  const i = html.indexOf('id="que-es"');
  const queEs = i < 0 ? '' : html.slice(i, html.indexOf('</section>', i));
  const boton = queEs.match(/<a href="([^"]+)"([^>]*)class="btn btn-primary/);
  di('el botón lleva a diadelpatrimoniosocial.cl/que-es/', boton?.[1] === 'https://diadelpatrimoniosocial.cl/que-es/', boton?.[1] ?? '(sin botón)');
  di('en otra pestaña, con rel="noopener"', /target="_blank"/.test(boton?.[2] ?? '') && /rel="noopener"/.test(boton?.[2] ?? ''));
} finally {
  tinker(`App\\Models\\Activity::where('slug', 'like', 'home-%-${SELLO}')->forceDelete(); echo 'OK';`);
  if (destacadasAntes !== '-') tinker(`App\\Models\\Activity::whereIn('id', [${destacadasAntes}])->update(['destacada' => true]); echo 'OK';`);
  if (fila === 'null') {
    tinker(`App\\Models\\HomeSection::where('clave','actividades')->delete(); echo 'OK';`);
  } else {
    const contenido = JSON.parse(fila).c;
    tinker(`App\\Models\\HomeSection::where('clave','actividades')->update(['contenido' => ${contenido === null ? 'null' : `'${JSON.stringify(contenido).replace(/'/g, "\\'")}'`}]); echo 'OK';`);
  }
  console.log('\n  (actividades de prueba borradas; destacadas y sección como estaban)');
}

console.log(`\n  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
