// Tanda del 15/10, en Chrome: lo que ve cada uno.
//
//   1 · El organizador cancela desde Mis actividades: el diálogo promete
//       borradores, el aviso lo dice, y la actividad está en «Borradores» (no
//       en «Canceladas»), sin botón de cancelar; la ficha pública da 404; las
//       inscripciones canceladas, los cupos devueltos y el formulario
//       encendido.
//   2 · Desde el editor, «Enviar a revisión» la manda a revisión aunque la
//       aprobación automática la publicaría.
//   3 · Una cancelada por la ONG se edita y se guarda: la pantalla ya no
//       promete que «se verá en el sitio».
//   4 · «Disponible de forma permanente»: el texto largo en la ficha, Mis
//       actividades y la ficha del panel; «Todo el año» en la tarjeta del
//       listado y en el listado del panel; y la imagen de difusión lo pinta
//       sin cortarlo.
//
// Monta su propia cuenta, organización y actividades con un sello y lo
// deshace al final. Captura la imagen de difusión en DPS_CAPTURAS si se da.
//
//   node pruebas/cancelar-y-permanente.mjs     (desde la raíz del repo; no contra producción)
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const CAPTURAS = process.env.DPS_CAPTURAS ?? null;

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(74)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();
const json = (php) => JSON.parse(ultima(tinker(php)));

const SELLO = Date.now();
const CLAVE = 'ClaveLarga123';
const CORREO = `cancelar.${SELLO}@ejemplo.cl`;
const ORG = `Fundación Cancelar ${SELLO}`;
const T = `T15 ${SELLO}`;
const LARGO = 'Actividad disponible de forma permanente';

// La aprobación automática encendida con umbral 1 mientras dura la prueba.
const ajustesAntes = json(`echo json_encode(['a' => App\\Models\\Setting::get('aprobacion_automatica'), 'd' => App\\Models\\Setting::get('aprobacion_automatica_desde')]);`);
tinker(`App\\Models\\Setting::set('aprobacion_automatica', '1'); App\\Models\\Setting::set('aprobacion_automatica_desde', '1'); Illuminate\\Support\\Facades\\Cache::forget(App\\Models\\Setting::CACHE_KEY);`);

const D = json(
  `$z = App\\Support\\Fecha::zona(); $hoy = now($z)->startOfDay();`
  + ` $u = App\\Models\\User::create(['name' => 'Cancela Quince', 'email' => '${CORREO}', 'password' => '${CLAVE}', 'role' => 'organizer', 'is_active' => true]);`
  + ` $o = App\\Models\\Organization::create(['nombre' => '${ORG}', 'tipo' => 'Organización sin fines de lucro', 'activo' => true, 'correo_contacto' => 'contacto.quince@ejemplo.cl', 'descripcion' => 'Descripción de prueba']);`
  + ` $o->enlazarCuenta($u);`
  + ` $c = App\\Models\\Commune::where('activo', true)->first(); $base = App\\Models\\Activity::whereNotNull('published_at')->has('terms')->with('terms')->first();`
  + ` $nueva = function ($nombre, $extra) use ($base, $o, $u, $c, $hoy) { $a = $base->replicate(); $a->forceFill(array_merge(['organization_id' => $o->id, 'user_id' => $u->id,`
  + ` 'titulo' => '${T} '.$nombre, 'slug' => 't15-'.$nombre.'-${SELLO}', 'estado' => 'publicada', 'published_at' => now()->subDays(2), 'destacada' => false, 'cerrada' => false,`
  + ` 'sin_fecha_definida' => false, 'fecha_inicio' => $hoy->copy()->addDays(12)->toDateString(), 'fecha_termino' => null, 'region_id' => $c->region_id, 'commune_id' => $c->id,`
  + ` 'formato' => 'Presencial', 'inscripcion_habilitada' => true, 'cupos_totales' => 10, 'cupos_disponibles' => 8], $extra))->save();`
  + ` $a->terms()->sync($base->terms->pluck('id')); return $a; };`
  + ` $a = ['viva' => $nueva('viva', []), 'panel' => $nueva('panel', ['estado' => 'cancelada', 'inscripcion_habilitada' => false]),`
  + ` 'perm' => $nueva('perm', ['fecha_inicio' => null, 'sin_fecha_definida' => true])];`
  + ` foreach ([1, 2] as $i) { App\\Models\\Registration::create(['activity_id' => $a['viva']->id, 'nombre' => 'Inscrita '.$i, 'correo' => 'insc'.$i.'.${SELLO}@ejemplo.cl', 'es_mayor_edad' => true, 'estado' => 'pendiente', 'token' => Illuminate\\Support\\Str::random(40)]); }`
  + ` echo json_encode(['ids' => collect($a)->map->id, 'slugs' => collect($a)->map->slug]);`
);

const limpiar = () => tinker(
  `foreach (App\\Models\\Organization::withTrashed()->where('nombre', '${ORG}')->get() as $o) {`
  + ` foreach ($o->activities()->withTrashed()->get() as $a) { $a->registrations()->delete(); $a->collaborators()->delete(); $a->statusLogs()->delete(); $a->terms()->detach(); $a->forceDelete(); }`
  + ` App\\Models\\User::where('organization_id', $o->id)->update(['organization_id' => null]); $o->forceDelete(); }`
  + ` foreach (App\\Models\\User::withTrashed()->where('email', '${CORREO}')->get() as $u) {`
  + ` App\\Models\\AccessLog::where('user_id', $u->id)->orWhere('actor_id', $u->id)->delete(); $u->forceDelete(); }`
  + ` App\\Models\\Setting::set('aprobacion_automatica', ${JSON.stringify(ajustesAntes.a === null ? '1' : (ajustesAntes.a ? '1' : '0'))});`
  + ` App\\Models\\Setting::set('aprobacion_automatica_desde', ${JSON.stringify(String(ajustesAntes.d ?? 1))});`
  + ` Illuminate\\Support\\Facades\\Cache::forget(App\\Models\\Setting::CACHE_KEY); echo 'LIMPIO';`
);

const fila = (id) => json(`$a = App\\Models\\Activity::find(${id}); $l = $a->statusLogs()->reorder('id', 'desc')->first();`
  + ` echo json_encode(['estado' => $a->estado, 'habilitada' => (bool) $a->inscripcion_habilitada, 'cupos' => $a->cupos_disponibles,`
  + ` 'vivas' => $a->registrations()->where('estado', '!=', 'cancelado')->count(), 'de' => $l?->de_estado, 'a' => $l?->a_estado, 'comentario' => $l?->comentario]);`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const pagina = async () => {
  const p = await (await nav.createBrowserContext()).newPage();
  await p.setViewport({ width: 1440, height: 1000 });
  p.on('pageerror', (e) => errores.push(String(e)));
  return p;
};
const entrar = async (p, ruta, email, clave) => {
  await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', email);
  await p.type('input[type="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);
};
const texto = (p) => p.evaluate(() => document.body.innerText);
const ID = D.ids;

try {
  const org = await pagina();
  await entrar(org, '/mi-cuenta/login', CORREO, CLAVE);

  t('1 · El organizador cancela');
  await org.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
  // Js::from escapa las barras de la URL (dos veces): se quitan todas las
  // barras invertidas y se busca por el id y «cancelar».
  const botonDe = (id) => [...document.querySelectorAll('button.sqbtn-danger')]
    .find((b) => (b.getAttribute('x-on:click') ?? '').replace(/\\/g, '').includes(`/actividades/${id}/cancelar`));
  const hayBoton = () => org.evaluate(`(${botonDe})(${ID.viva}) !== undefined`);
  di('la publicada tiene botón de cancelar', await hayBoton());
  await org.evaluate(`(${botonDe})(${ID.viva}).click()`);
  await org.waitForFunction(() => [...document.querySelectorAll('[role="dialog"]')].some((d) => d.offsetParent !== null));
  const dialogo = await org.evaluate(() => [...document.querySelectorAll('[role="dialog"]')].find((d) => d.offsetParent !== null).innerText);
  di('el diálogo promete los borradores', dialogo.includes('Tu actividad quedará guardada en tus borradores.'));
  di('y avisa de las 2 inscritas', /Hay 2 personas inscritas/.test(dialogo));
  await Promise.all([org.waitForNavigation({ waitUntil: 'networkidle2' }),
    org.evaluate(() => [...document.querySelectorAll('[role="dialog"]')].find((d) => d.offsetParent !== null).querySelector('form button[type=submit]').click())]);
  const aviso = await texto(org);
  di('el aviso dice que quedó en borradores y a cuántas avisó', aviso.includes('La actividad fue cancelada y quedó en tus borradores. Avisamos por correo a las 2 personas inscritas.'));

  let f = fila(ID.viva);
  di('estado: borrador', f.estado === 'borrador', f.estado);
  di('historial: publicada → borrador, «Cancelada por el organizador.»', f.de === 'publicada' && f.a === 'borrador' && f.comentario === 'Cancelada por el organizador.');
  di('inscripciones canceladas y cupos devueltos (8 → 10)', f.vivas === 0 && f.cupos === 10, `${f.vivas} vivas, ${f.cupos} cupos`);
  di('el formulario de inscripción sigue encendido', f.habilitada === true);

  await org.goto(`${B}/mi-cuenta/actividades?filtro=Borradores`, { waitUntil: 'networkidle2' });
  di('sale en «Borradores»', (await texto(org)).includes(`${T} viva`));
  di('ya sin botón de cancelar', !(await hayBoton()));
  await org.goto(`${B}/mi-cuenta/actividades?filtro=Canceladas`, { waitUntil: 'networkidle2' });
  di('no sale en «Canceladas»', !(await texto(org)).includes(`${T} viva`));

  const anon = await pagina();
  const st = (await anon.goto(`${B}/activity/${ID.viva}/${D.slugs.viva}`, { waitUntil: 'networkidle2' })).status();
  di('la ficha pública da 404', st === 404, String(st));

  t('2 · Enviar a revisión desde el editor');
  const control = json(`$a = App\\Models\\Activity::find(${ID.perm})->replicate(); $a->estado = 'borrador'; $a->published_at = null;`
    + ` echo json_encode(app(App\\Services\\AprobacionAutomatica::class)->estadoAlEnviar($a));`);
  di('(control) otra suya saldría publicada sola', control[0] === 'publicada', control[1]);
  await org.goto(`${B}/mi-cuenta/actividades/${ID.viva}/editar`, { waitUntil: 'networkidle2' });
  di('el editor no tiene «Cancelar actividad»', !(await org.evaluate(() => [...document.querySelectorAll('button')].some((b) => b.textContent.trim() === 'Cancelar actividad' && b.offsetParent !== null))));
  di('tiene «Enviar a revisión»', !!(await org.$('button[name="enviar_revision"]')));
  await Promise.all([org.waitForNavigation({ waitUntil: 'networkidle2' }), org.click('button[name="enviar_revision"]')]);
  f = fila(ID.viva);
  di('queda en revisión, no publicada', f.estado === 'revision', f.estado);
  di('con «A revisión: vuelve de una cancelación.»', f.comentario === 'A revisión: vuelve de una cancelación.', String(f.comentario));
  di('el aviso dice que se envió a revisión', (await texto(org)).includes('Enviamos tu actividad a revisión.'));

  t('3 · Guardar una cancelada por la ONG');
  await org.goto(`${B}/mi-cuenta/actividades/${ID.panel}/editar`, { waitUntil: 'networkidle2' });
  await Promise.all([org.waitForNavigation({ waitUntil: 'networkidle2' }),
    org.evaluate(() => [...document.querySelectorAll('button[type=submit]')].find((b) => b.textContent.trim() === 'Actualizar actividad').click())]);
  const guardado = await texto(org);
  di('llega a la pantalla de guardado', org.url().includes('/guardado'), org.url());
  di('dice que sigue cancelada', guardado.includes('sigue cancelada y no aparece en el sitio'));
  di('no promete que se verá ni que está publicada', !guardado.includes('Se verá en el sitio') && !guardado.includes('ya están publicados'));
  di('sigue cancelada', fila(ID.panel).estado === 'cancelada');

  t('4 · «Disponible de forma permanente»');
  await anon.goto(`${B}/activity/${ID.perm}/${D.slugs.perm}`, { waitUntil: 'networkidle2' });
  const cuando = await anon.evaluate(() => [...document.querySelectorAll('.helper')].find((h) => h.textContent.trim() === 'Cuándo')?.nextElementSibling?.textContent.trim());
  di('ficha pública, «Cuándo»', cuando === LARGO, cuando);
  di('la ficha no dice «por definir» en ninguna parte', !/por definir/i.test(await texto(anon)));
  await anon.goto(`${B}/actividades?q=${encodeURIComponent(`${T} perm`)}`, { waitUntil: 'networkidle2' });
  const tarjeta = await anon.evaluate(() => document.querySelector('.act-card')?.innerText ?? '');
  di('tarjeta del listado: «Todo el año»', tarjeta.includes('Todo el año') && !tarjeta.includes(LARGO), tarjeta.replace(/\s+/g, ' ').slice(0, 80));
  await org.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
  // La fila: el bloque más pequeño que tiene el título y la fecha.
  const miFila = await org.evaluate((t, largo) => [...document.querySelectorAll('div')]
    .filter((d) => d.innerText.includes(t) && d.innerText.includes(' · '))
    .sort((a, b) => a.innerText.length - b.innerText.length)[0]?.innerText ?? '', `${T} perm`, LARGO);
  di('Mis actividades', miFila.includes(LARGO), miFila.replace(/\s+/g, ' ').slice(0, 120));

  const admin = await pagina();
  await entrar(admin, '/admin/login', ADMIN, CLAVE_ADMIN);
  await admin.goto(`${B}/admin/actividades/${ID.perm}`, { waitUntil: 'networkidle2' });
  di('ficha del panel', (await texto(admin)).includes(LARGO));
  await admin.goto(`${B}/admin/actividades?q=${encodeURIComponent(`${T} perm`)}`, { waitUntil: 'networkidle2' });
  const filaPanel = await admin.evaluate((t) => [...document.querySelectorAll('tr')].find((r) => r.innerText.includes(t))?.innerText ?? '', `${T} perm`);
  di('listado del panel: «Todo el año»', filaPanel.includes('Todo el año'), filaPanel.replace(/\s+/g, ' ').slice(0, 100));

  await org.goto(`${B}/mi-cuenta/actividades/${ID.perm}/difusion`, { waitUntil: 'networkidle2' });
  const lista = await org.waitForFunction(() => { const i = document.querySelector('[data-difusion-imagen]'); return i && i.offsetParent !== null && i.naturalWidth > 0; }, { timeout: 20000 })
    .then(() => true, () => false);
  di('imagen de difusión: se dibuja', lista);
  const datos = await org.evaluate(() => document.querySelector('.difusion')?.getAttribute('x-data') ?? '');
  di('imagen de difusión: lleva el texto largo', datos.includes(LARGO));
  // Que el texto quepa sin cortarse sólo se ve mirando la imagen.
  if (CAPTURAS && lista) {
    const png = await org.evaluate(() => document.querySelector('[data-difusion-imagen]').src);
    const { writeFileSync } = await import('node:fs');
    const b64 = await org.evaluate(async (u) => { const r = await fetch(u); const b = await r.blob(); return await new Promise((ok) => { const f = new FileReader(); f.onload = () => ok(f.result.split(',')[1]); f.readAsDataURL(b); }); }, png);
    writeFileSync(`${CAPTURAS}/difusion-permanente.png`, Buffer.from(b64, 'base64'));
  }

  di('sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));
} finally {
  await nav.close();
  console.log(ultima(limpiar()));
}

console.log(`\n${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
