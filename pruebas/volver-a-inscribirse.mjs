// Punto 6 del 08/10 — volver a inscribirse después de darse de baja.
//
// Antes, quien se daba de baja y volvía a inscribirse con el mismo correo se
// quedaba fuera: la base no admite dos filas con el mismo correo en la misma
// actividad, y el alta reventaba (500). Ahora se reactiva la fila cancelada.
//
// Lo que se comprueba, de punta a punta en Chrome:
//   - se puede volver a inscribir y llega a «Soy parte»;
//   - sigue habiendo UNA sola fila (ni duplicado en el panel ni en el Excel);
//   - los cupos cuadran: baja devuelve uno, volver a inscribirse lo vuelve a
//     gastar;
//   - vuelve como recién hecha: pendiente, fecha de hoy, token nuevo, sin
//     recordatorio dado por enviado; el enlace de cancelar viejo ya no sirve;
//   - inscribirse otra vez estando activa sigue diciendo «ya está inscrito».
//
//   node pruebas/volver-a-inscribirse.mjs
//
// Contra producción NO: crea una organización, una actividad e inscripciones.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').pop();

const SELLO = Date.now();
const CORREO = `vuelve.${SELLO}@ejemplo.cl`;

const ids = JSON.parse(tinker(
  `$o = App\\Models\\Organization::create(['user_id' => null, 'nombre' => 'Org Vuelve ${SELLO}', 'slug' => 'vuelve-${SELLO}', 'tipo' => 'Organización sin fines de lucro', 'activo' => true]);`
  + ` $a = new App\\Models\\Activity; $a->forceFill(['organization_id' => $o->id, 'titulo' => 'Actividad vuelve ${SELLO}', 'descripcion' => 'Prueba.',`
  + ` 'slug' => 'actividad-vuelve-${SELLO}', 'estado' => 'publicada', 'published_at' => now(), 'formato' => 'Presencial',`
  + ` 'inscripcion_habilitada' => true, 'cupos_totales' => 20, 'cupos_disponibles' => 20, 'fecha_inicio' => now()->addDays(20)->toDateString()])->save();`
  + ` echo json_encode(['org' => $o->id, 'act' => $a->id, 'slug' => $a->slug]);`
));

const fila = () => JSON.parse(tinker(
  `$r = App\\Models\\Registration::where('activity_id', ${ids.act})->where('correo', '${CORREO}')->get();`
  + ` $f = $r->first(); echo json_encode(['n' => $r->count(), 'id' => $f?->id, 'estado' => $f?->estado, 'token' => $f?->token, 'nombre' => $f?->nombre,`
  + ` 'creada' => $f?->created_at?->timestamp, 'recordatorio' => $f?->recordatorio_encolado_at, 'activas' => App\\Models\\Registration::where('activity_id', ${ids.act})->activas()->count(),`
  + ` 'cupos' => App\\Models\\Activity::find(${ids.act})->cupos_disponibles]);`
));

const limpiar = () => tinker(
  `App\\Models\\Registration::where('activity_id', ${ids.act})->delete(); App\\Models\\Activity::whereKey(${ids.act})->forceDelete();`
  + ` App\\Models\\Organization::whereKey(${ids.org})->forceDelete(); echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const inscribirse = async (nombre) => {
  await p.goto(`${B}/actividades/${ids.slug}`, { waitUntil: 'networkidle2' });
  await p.type('#r-nombre', nombre);
  await p.type('#r-correo', CORREO);
  const [resp] = await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.click('form[action*="inscribirse"] button[type=submit]'),
  ]);

  return resp?.status();
};

try {
  t('1 · Inscribirse y darse de baja');

  await inscribirse('Primera Vez');
  let f = fila();
  di('queda inscrita', f.n === 1 && f.estado !== 'cancelado', f.estado);
  di('gasta un cupo', f.cupos === 19, String(f.cupos));
  const primera = f;

  // Que el recordatorio ya se hubiera encolado: al volver no puede darse por enviado.
  tinker(`App\\Models\\Registration::whereKey(${f.id})->update(['recordatorio_encolado_at' => now(), 'created_at' => now()->subDays(3)]); echo 'ok';`);

  await p.goto(`${B}/inscripcion/${f.token}/cancelar`, { waitUntil: 'networkidle2' });
  f = fila();
  di('la baja la cancela', f.estado === 'cancelado');
  di('y devuelve el cupo', f.cupos === 20, String(f.cupos));

  t('2 · Volver a inscribirse');

  const estado = await inscribirse('Segunda Vez');
  di('**se puede volver a inscribir** (sin error)', estado === 200 && p.url().endsWith('/soy-parte'), `${estado} ${p.url().replace(B, '')}`);
  f = fila();
  di('**sigue habiendo UNA sola fila**', f.n === 1, String(f.n));
  di('es la misma fila, reactivada', f.id === primera.id);
  di('vuelve como pendiente', f.estado === 'pendiente', f.estado);
  di('con los datos nuevos', f.nombre === 'Segunda Vez', f.nombre);
  di('con la fecha de inscripción de hoy', f.creada > primera.creada - 60 && f.creada >= Math.floor(Date.now() / 1000) - 600);
  di('sin el recordatorio dado por enviado', f.recordatorio === null);
  di('token nuevo', f.token && f.token !== primera.token);
  di('gasta otra vez un cupo', f.cupos === 19, String(f.cupos));
  di('las activas de la actividad: 1', f.activas === 1, String(f.activas));

  const viejo = await p.goto(`${B}/inscripcion/${primera.token}/cancelar`, { waitUntil: 'networkidle2' });
  di('el enlace de cancelar viejo ya no sirve (404)', viejo.status() === 404, String(viejo.status()));
  di('y no la ha cancelado', fila().estado === 'pendiente');

  t('3 · Inscrita, no se duplica');

  await inscribirse('Tercera Vez');
  const aviso = await p.evaluate(() => document.body.innerText.includes('Ese correo ya está inscrito en esta actividad.'));
  di('dice que ya está inscrita', aviso);
  f = fila();
  di('y sigue habiendo una fila, con sus datos', f.n === 1 && f.nombre === 'Segunda Vez' && f.cupos === 19);

  di('Sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  await nav.close();
  console.log(`\n  (${limpiar()})`);
}

console.log(`\n  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
