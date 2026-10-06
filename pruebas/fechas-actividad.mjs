// Tanda del 05/10, puntos 4 y 5 — fechas pasadas y actividades de varios días.
//
// Punto 4:
//   - Al CREAR se acepta una fecha pasada del año en curso, con el aviso de
//     «ya pasó» que no corta el envío; la de un año anterior se rechaza.
//   - Al EDITAR, si la actividad ya pasó, las fechas salen bloqueadas y se
//     dice por qué; ni un envío hecho a mano las cambia. Lo demás se edita.
//   - Una actividad que ya pasó no acepta inscritos: sin formulario y con el
//     POST directo rechazado. Sin romper lo de alrededor: el interruptor sigue
//     como estaba, las inscripciones ya hechas siguen visibles y la encuesta
//     de después y su QR siguen funcionando.
// Punto 5:
//   - «La actividad dura varios días» en el wizard y en el editor, iguales:
//     sin marcar no hay campo de término; marcada sí, y obligatorio, y no
//     anterior al inicio. En el editor arranca marcada si ya tenía término.
//   - El rango se lee en la ficha, las tarjetas y mi-cuenta.
//
// Monta su propia organización y sus actividades, y las borra al terminar.
//
//   node pruebas/fechas-actividad.mjs
//
// Contra producción NO: crea cuentas, actividades e inscripciones.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();
const json = (php) => JSON.parse(ultima(tinker(php)));

const SELLO = Date.now();
const CORREO = `fechas.${SELLO}@ejemplo.cl`;
const CLAVE = 'fechas-actividad-2026';

// Fechas de Chile, calculadas por el servidor para no depender del reloj de aquí.
const F = json(`$z = App\\Support\\Fecha::zona(); $h = now($z);`
  + ` echo json_encode(['hoy' => $h->toDateString(), 'menos10' => $h->copy()->subDays(10)->toDateString(), 'menos1' => $h->copy()->subDay()->toDateString(),`
  + ` 'mas1' => $h->copy()->addDay()->toDateString(), 'mas20' => $h->copy()->addDays(20)->toDateString(), 'mas22' => $h->copy()->addDays(22)->toDateString(),`
  + ` 'anioPasado' => $h->copy()->subYear()->endOfYear()->toDateString(), 'inicioAnio' => $h->copy()->startOfYear()->toDateString()]);`);
const dmy = (iso) => iso.split('-').reverse().join(' / ');

const ids = json(
  `$u = new App\\Models\\User(['name' => 'Persona Fechas', 'email' => '${CORREO}', 'password' => bcrypt('${CLAVE}')]);`
  + ` $u->forceFill(['role' => App\\Models\\User::ROL_ORGANIZER, 'is_active' => true, 'email_verified_at' => now()])->save();`
  + ` $o = App\\Models\\Organization::create(['user_id' => $u->id, 'nombre' => 'Fundación Fechas ${SELLO}', 'slug' => 'fechas-'.$u->id, 'activo' => true,`
  + ` 'tipo' => 'Organización sin fines de lucro', 'logo_path' => 'storage/organizaciones/prueba.png']);`
  + ` $base = App\\Models\\Activity::where('estado','publicada')->firstOrFail();`
  + ` $crear = function ($clave, $ini, $fin = null) use ($base, $o) { $a = $base->replicate(); $a->forceFill(['organization_id' => $o->id,`
  + ` 'titulo' => 'Fechas '.$clave.' ${SELLO}', 'slug' => 'fechas-'.$clave.'-${SELLO}', 'estado' => 'publicada', 'published_at' => now(),`
  + ` 'fecha_inicio' => $ini, 'fecha_termino' => $fin, 'sin_fecha_definida' => false, 'inscripcion_habilitada' => true, 'cupos_disponibles' => null, 'cupos_totales' => null])->save(); $a->terms()->sync($base->terms->pluck('id')); return $a->id; };`
  + ` $ids = ['pasada' => $crear('pasada', '${F.menos10}'), 'hoy' => $crear('hoy', '${F.hoy}'), 'encurso' => $crear('encurso', '${F.menos1}', '${F.mas1}'),`
  + ` 'futura' => $crear('futura', '${F.mas20}'), 'varios' => $crear('varios', '${F.mas20}', '${F.mas22}')];`
  + ` App\\Models\\Registration::create(['activity_id' => $ids['pasada'], 'nombre' => 'Ya inscrita', 'correo' => 'ya.${SELLO}@ejemplo.cl', 'estado' => 'confirmado']);`
  + ` $r = App\\Models\\Region::whereHas('communes')->first(); $ids['region'] = $r->id; $ids['comuna'] = $r->communes()->first()->id; echo json_encode($ids);`
);

const actividad = (id) => json(`echo json_encode(App\\Models\\Activity::find(${id})?->only(['fecha_inicio','fecha_termino','sin_fecha_definida','inscripcion_habilitada','descripcion','slug','estado']));`);
const inscritos = (id) => +ultima(tinker(`echo App\\Models\\Registration::where('activity_id', ${id})->count();`));
const ficha = (id) => B + ultima(tinker(`echo route('activities.show', App\\Models\\Activity::find(${id}), false);`));

const limpiar = () => tinker(
  `$o = App\\Models\\Organization::where('nombre', 'Fundación Fechas ${SELLO}')->first();`
  + ` if ($o) { foreach (App\\Models\\Activity::withTrashed()->where('organization_id', $o->id)->get() as $a) { $a->registrations()->forceDelete(); $a->statusLogs()->delete(); $a->terms()->detach(); $a->forceDelete(); } }`
  + ` $u = App\\Models\\User::where('email','${CORREO}')->first(); if ($u) { $u->organization?->forceDelete(); $u->delete(); }`
  + ` App\\Models\\AccessLog::where('email','${CORREO}')->delete(); echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

// El wizard, hasta el paso de la actividad y con todo relleno menos las fechas.
const abrirWizard = async (titulo) => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => b.innerText.includes('No, solo quiero difundir')).click());
  await esperar(300);
  await p.type('input[name="titulo"]', titulo);
  await p.type('textarea[name="descripcion"]', 'Sembrada por pruebas/fechas-actividad.mjs.');
  await p.evaluate((r, c) => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    for (const grupo of ['temas', 'caracteristicas', 'publicos']) {
      const b = [...document.querySelectorAll('button')].find((x) => x.getAttribute('x-on:click')?.startsWith(`alternar('${grupo}'`));
      if (b && d.sel[grupo].length === 0) b.click();
    }
    d.regionId = String(r);
    setTimeout(() => { d.communeId = String(c); }, 50);
  }, ids.region, ids.comuna);
  await esperar(300);
  await p.type('input[name="direccion"]', 'Calle de prueba 123');
  // Cerrar las sugerencias sin `Escape`, que en el wizard vuelve atrás.
  await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).dirAbiertas = false; });
};
const escribirFecha = async (sel, iso) => {
  await p.$eval(sel, (i) => { i.value = ''; i.focus(); });
  await p.type(sel, iso.split('-').reverse().join(''));
  await p.$eval(sel, (i) => i.blur());
  await esperar(150);
};
const enviarWizard = async () => {
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => null),
    p.evaluate(() => [...document.querySelectorAll('button[type="submit"]')].find((b) => /Publicar|Enviar/i.test(b.textContent)).click()),
  ]);
};
const visible = (sel) => p.evaluate((s) => { const e = document.querySelector(s); return !! e && !! e.offsetParent && getComputedStyle(e).display !== 'none'; }, sel);
const nueva = (titulo) => json(`echo json_encode(App\\Models\\Activity::where('titulo','${titulo}')->first()?->only(['id','fecha_inicio','fecha_termino','estado']));`);

try {
  await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', CORREO);
  await p.type('input[type="password"]', CLAVE);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  t('4a · Wizard: fecha pasada del año en curso');

  const T1 = `Pasada por wizard ${SELLO}`;
  await abrirWizard(T1);
  di('sin fecha no hay aviso', ! (await visible('.aviso-fecha-pasada')));
  await escribirFecha('input[name="fecha_inicio"]', F.menos10);
  di('**con una fecha pasada sale el aviso**', await visible('.aviso-fecha-pasada'));
  di('con el texto pedido', (await p.$eval('.aviso-fecha-pasada', (e) => e.innerText)).includes('Elegiste una fecha que ya pasó. Corrígela en caso de que te hayas equivocado'));
  await escribirFecha('input[name="fecha_inicio"]', F.mas20);
  di('con una futura se va', ! (await visible('.aviso-fecha-pasada')));
  await escribirFecha('input[name="fecha_inicio"]', F.hoy);
  di('con la de hoy no sale (hoy no ha pasado)', ! (await visible('.aviso-fecha-pasada')));
  await escribirFecha('input[name="fecha_inicio"]', F.menos10);
  await enviarWizard();
  const a1 = nueva(T1);
  di('**y el envío NO se corta: la actividad se crea**', !! a1 && /\/listo/.test(p.url()), p.url().replace(B, ''));
  di('con la fecha pasada guardada', a1?.fecha_inicio?.startsWith(F.menos10), a1?.fecha_inicio);

  t('4b · Wizard: fecha de un año anterior');

  const T2 = `Año anterior ${SELLO}`;
  await abrirWizard(T2);
  await escribirFecha('input[name="fecha_inicio"]', F.anioPasado);
  await enviarWizard();
  di('**se rechaza**', ! nueva(T2));
  const texto2 = await p.evaluate(() => document.body.innerText);
  di('diciendo por qué', texto2.includes('La fecha no puede ser de un año anterior.'));
  di('el 1 de enero del año en curso sí vale', ultima(tinker(
    `$v = Illuminate\\Support\\Facades\\Validator::make(['fecha_inicio' => '${F.inicioAnio}'], ['fecha_inicio' => (new App\\Http\\Requests\\PublishActivityRequest)->rules()['fecha_inicio']]); echo $v->fails() ? 'falla' : 'pasa';`)) === 'pasa');

  t('5a · Wizard: «La actividad dura varios días»');

  const T3 = `Varios días por wizard ${SELLO}`;
  await abrirWizard(T3);
  di('la casilla está', await visible('.casilla-varios-dias'));
  di('**sin marcar, no hay campo de término**', ! (await visible('input[name="fecha_termino"]')));
  di('y va deshabilitado (no viaja)', await p.$eval('input[name="fecha_termino"]', (i) => i.disabled));
  await p.click('.casilla-varios-dias input');
  await esperar(200);
  di('**al marcarla aparece**', await visible('input[name="fecha_termino"]'));
  await escribirFecha('input[name="fecha_inicio"]', F.mas20);
  await enviarWizard();
  di('marcada y sin término: no se crea', ! nueva(T3));
  // Lo frena antes el navegador, con el resumen de arriba (bloque K).
  di('y el resumen pide la fecha de término', await p.evaluate(() => [...document.querySelectorAll('[data-resumen-errores]')].some((r) => r.offsetParent && r.innerText.includes('Fecha de término'))));
  di('al volver, la casilla sigue marcada y el campo visible', await visible('input[name="fecha_termino"]'));

  await escribirFecha('input[name="fecha_inicio"]', F.mas22);
  await escribirFecha('input[name="fecha_termino"]', F.mas20);
  await enviarWizard();
  di('término anterior al inicio: no se crea', ! nueva(T3));
  di('y lo dice', (await p.evaluate(() => document.body.innerText)).includes('La fecha de término no puede ser anterior a la de inicio.'));

  await escribirFecha('input[name="fecha_inicio"]', F.mas20);
  await escribirFecha('input[name="fecha_termino"]', F.mas22);
  await enviarWizard();
  const a3 = nueva(T3);
  di('**con las dos fechas bien, se crea con su término**', a3?.fecha_termino?.startsWith(F.mas22), JSON.stringify(a3));

  const T4 = `Un día por wizard ${SELLO}`;
  await abrirWizard(T4);
  await escribirFecha('input[name="fecha_inicio"]', F.mas20);
  // Marcar, escribir el término y desmarcar: no se guarda nada de término.
  await p.click('.casilla-varios-dias input');
  await escribirFecha('input[name="fecha_termino"]', F.mas22);
  await p.click('.casilla-varios-dias input');
  await esperar(200);
  await enviarWizard();
  const a4 = nueva(T4);
  di('marcada y desmarcada: se crea sin término', !! a4 && a4.fecha_termino === null, JSON.stringify(a4));

  /*
   * Fallo reproducido en producción tras la tanda: «La hora de término debe
   * ser posterior a la de inicio» saltaba también con varios días. Del 20 al
   * 22, empezar a las 18:00 y terminar a las 10:00 es perfectamente posible.
   */
  t('5a bis · Las horas en una actividad de varios días');
  const horas = async (ini, fin) => {
    await p.select('select[name="hora_inicio"]', ini);
    await p.select('select[name="hora_termino"]', fin);
  };
  const T5 = `Varios días hora temprana ${SELLO}`;
  await abrirWizard(T5);
  await escribirFecha('input[name="fecha_inicio"]', F.mas20);
  await p.click('.casilla-varios-dias input');
  await escribirFecha('input[name="fecha_termino"]', F.mas22);
  await horas('18:00', '10:00');
  await enviarWizard();
  di('**varios días: término a las 10:00 tras empezar a las 18:00, se crea**', !! nueva(T5),
    (await p.evaluate(() => document.body.innerText)).includes('posterior a la de inicio') ? 'rebotó por la hora' : '');

  const T6 = `Un día hora temprana ${SELLO}`;
  await abrirWizard(T6);
  await escribirFecha('input[name="fecha_inicio"]', F.mas20);
  await horas('18:00', '10:00');
  await enviarWizard();
  di('un solo día: la misma hora se sigue rechazando', ! nueva(T6));
  di('con su mensaje', (await p.evaluate(() => document.body.innerText)).includes('La hora de término debe ser posterior a la de inicio.'));

  const T7 = `Varios días mismo día ${SELLO}`;
  await abrirWizard(T7);
  await escribirFecha('input[name="fecha_inicio"]', F.mas20);
  await p.click('.casilla-varios-dias input');
  await escribirFecha('input[name="fecha_termino"]', F.mas20);
  await horas('18:00', '10:00');
  await enviarWizard();
  di('«varios días» pero el mismo día de término: se sigue rechazando', ! nueva(T7));

  t('5b · El rango se lee en la ficha, las tarjetas y mi-cuenta');

  const fv = await (await fetch(ficha(ids.varios))).text();
  const rango = ultima(tinker(`echo App\\Models\\Activity::find(${ids.varios})->fecha_larga;`));
  di('el texto largo es un rango', / al /.test(rango), rango);
  di('la ficha lo enseña', fv.includes(rango));
  const corta = ultima(tinker(`echo App\\Models\\Activity::find(${ids.varios})->fecha_corta;`));
  di('la tarjeta, en corto', / al /.test(corta), corta);
  const unDia = ultima(tinker(`echo App\\Models\\Activity::find(${ids.futura})->fecha_larga;`));
  di('una de un solo día sigue como antes', ! / al /.test(unDia) && / de \d{4}$/.test(unDia), unDia);
  await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
  const lista = ultima(tinker(`echo App\\Models\\Activity::find(${ids.varios})->fecha_lista;`));
  di('mi-cuenta enseña el rango', (await p.evaluate(() => document.body.innerText)).includes(lista), lista);

  t('5c · Editor: la misma casilla');

  await p.goto(`${B}/mi-cuenta/actividades/${ids.varios}/editar`, { waitUntil: 'networkidle2' });
  di('con término guardado, la casilla arranca marcada', await p.$eval('.casilla-varios-dias input', (i) => i.checked));
  di('y el campo visible con su fecha', (await visible('input[name="fecha_termino"]')) && (await p.$eval('input[name="fecha_termino"]', (i) => i.value)) === dmy(F.mas22));
  await p.click('.casilla-varios-dias input');
  await esperar(200);
  di('al desmarcarla el campo se va', ! (await visible('input[name="fecha_termino"]')));
  await Promise.all([p.waitForNavigation({ timeout: 20000 }), p.click(`form[action$="/${ids.varios}"] button[type="submit"]`)]);
  di('**y al guardar se queda de un día**', actividad(ids.varios).fecha_termino === null);

  await p.goto(`${B}/mi-cuenta/actividades/${ids.futura}/editar`, { waitUntil: 'networkidle2' });
  di('sin término, arranca desmarcada y sin campo', ! (await p.$eval('.casilla-varios-dias input', (i) => i.checked)) && ! (await visible('input[name="fecha_termino"]')));
  await p.click('.casilla-varios-dias input');
  await escribirFecha('input[name="fecha_termino"]', F.mas22);
  await Promise.all([p.waitForNavigation({ timeout: 20000 }), p.click(`form[action$="/${ids.futura}"] button[type="submit"]`)]);
  di('marcarla y poner término lo guarda', actividad(ids.futura).fecha_termino?.startsWith(F.mas22));

  // El mismo fallo de las horas, en el editor: ahora es de varios días.
  await p.goto(`${B}/mi-cuenta/actividades/${ids.futura}/editar`, { waitUntil: 'networkidle2' });
  await p.select('select[name="hora_inicio"]', '18:00');
  await p.select('select[name="hora_termino"]', '10:00');
  await Promise.all([p.waitForNavigation({ timeout: 20000 }), p.click(`form[action$="/${ids.futura}"] button[type="submit"]`)]);
  const horasGuardadas = ultima(tinker(`$a = App\\Models\\Activity::find(${ids.futura}); echo substr($a->hora_inicio, 0, 5).'-'.substr($a->hora_termino, 0, 5);`));
  di('**editor, varios días: término antes que el inicio se guarda**', horasGuardadas === '18:00-10:00', horasGuardadas);

  t('5d · Los dos formularios se ven igual');

  const pieza = async () => p.evaluate(() => ({
    casilla: document.querySelector('.casilla-varios-dias')?.innerText.trim(),
    termino: document.querySelector('[data-campo="fecha_termino"]')?.childNodes[0]?.textContent.trim(),
  }));
  await p.goto(`${B}/mi-cuenta/actividades/${ids.futura}/editar`, { waitUntil: 'networkidle2' });
  const enEditor = await pieza();
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  const enWizard = await pieza();
  di('el mismo texto de casilla y de campo', JSON.stringify(enEditor) === JSON.stringify(enWizard) && enWizard.casilla === 'La actividad dura varios días', JSON.stringify(enWizard));

  t('4c · Editor: una actividad que ya pasó');

  const antes = actividad(ids.pasada);
  await p.goto(`${B}/mi-cuenta/actividades/${ids.pasada}/editar`, { waitUntil: 'networkidle2' });
  di('**la fecha sale bloqueada**', await p.$eval('input[name="fecha_inicio"]', (i) => i.disabled));
  di('también la casilla de varios días y la de «permanente»',
    await p.evaluate(() => document.querySelector('.casilla-varios-dias input').disabled && document.querySelector('input[name="sin_fecha_definida"]').disabled));
  di('**y se dice por qué**', (await visible('.fechas-bloqueadas')) && (await p.$eval('.fechas-bloqueadas', (e) => e.innerText)).includes('ya se realizó'));
  di('el resto se puede editar (título, descripción)', ! (await p.$eval('input[name="titulo"]', (i) => i.disabled)) && ! (await p.$eval('textarea[name="descripcion"]', (i) => i.disabled)));
  await p.$eval('textarea[name="descripcion"]', (el) => { el.value = 'Descripción cambiada después del evento.'; el.dispatchEvent(new Event('input', { bubbles: true })); });
  await Promise.all([p.waitForNavigation({ timeout: 20000 }), p.click(`form[action$="/${ids.pasada}"] button[type="submit"]`)]);
  const despues = actividad(ids.pasada);
  di('**guardar funciona**', despues.descripcion === 'Descripción cambiada después del evento.', p.url().replace(B, ''));
  di('y las fechas no se movieron', despues.fecha_inicio === antes.fecha_inicio && despues.fecha_termino === antes.fecha_termino);

  // Un envío hecho a mano con otra fecha tampoco la cambia.
  await p.goto(`${B}/mi-cuenta/actividades/${ids.pasada}/editar`, { waitUntil: 'networkidle2' });
  await p.evaluate((nueva) => {
    const f = document.querySelector('form[action$="/editar"], form[method="POST"][action*="/mi-cuenta/actividades/"]');
    document.querySelectorAll('input[name="fecha_inicio"], input[name="fecha_termino"], input[name="varios_dias"]').forEach((i) => { i.disabled = false; });
    document.querySelector('input[name="fecha_inicio"]').value = nueva;
  }, dmy(F.mas20));
  await Promise.all([p.waitForNavigation({ timeout: 20000 }), p.click(`form[action$="/${ids.pasada}"] button[type="submit"]`)]);
  di('**ni forzando el formulario se cambia la fecha**', actividad(ids.pasada).fecha_inicio === antes.fecha_inicio, actividad(ids.pasada).fecha_inicio);

  await p.goto(`${B}/mi-cuenta/actividades/${ids.encurso}/editar`, { waitUntil: 'networkidle2' });
  di('una de varios días que sigue en curso NO se bloquea', ! (await p.$eval('input[name="fecha_inicio"]', (i) => i.disabled)) && ! (await visible('.fechas-bloqueadas')));

  t('4d · Inscripción cerrada cuando la fecha pasa');

  const anon = await nav.createBrowserContext();
  const q = await anon.newPage();
  await q.goto(ficha(ids.pasada), { waitUntil: 'networkidle2' });
  const texto = await q.evaluate(() => document.body.innerText);
  di('**la ficha de una pasada no tiene formulario**', ! (await q.$('input[name="correo"]')));
  di('y dice que ya se realizó (no «¡Te esperamos!»)', texto.includes('Esta actividad ya se realizó.') && ! texto.includes('Te esperamos'));

  // El POST directo, con un token válido de una ficha que sí lo tiene.
  await q.goto(ficha(ids.hoy), { waitUntil: 'networkidle2' });
  di('la de hoy sí tiene formulario', !! (await q.$('input[name="correo"]')));
  const slugPasada = actividad(ids.pasada).slug;
  const antesPost = inscritos(ids.pasada);
  const r = await q.evaluate(async (url) => {
    const fd = new FormData();
    fd.append('_token', document.querySelector('input[name="_token"]').value);
    fd.append('nombre', 'Llega tarde'); fd.append('correo', 'tarde@ejemplo.cl'); fd.append('es_mayor_edad', '1');
    const res = await fetch(url, { method: 'POST', body: fd, redirect: 'follow' });
    return { status: res.status, texto: await res.text() };
  }, `${B}/actividades/${slugPasada}/inscribirse`);
  di('**el POST directo se rechaza**', inscritos(ids.pasada) === antesPost, `${antesPost} → ${inscritos(ids.pasada)}`);
  di('con el motivo', r.texto.includes('Esta actividad ya se realizó: no recibe inscripciones.'));

  await q.goto(ficha(ids.encurso), { waitUntil: 'networkidle2' });
  di('una de varios días que sigue en curso admite inscritos', !! (await q.$('input[name="correo"]')));

  di('**el interruptor sigue como estaba** (no se apaga solo)', actividad(ids.pasada).inscripcion_habilitada === true);

  t('4e · Lo de alrededor sigue funcionando');

  await p.goto(`${B}/mi-cuenta/actividades/${ids.pasada}/participantes`, { waitUntil: 'networkidle2' });
  di('las inscripciones ya hechas siguen visibles', (await p.evaluate(() => document.body.innerText)).includes(`ya.${SELLO}@ejemplo.cl`), p.url().replace(B, ''));
  const exp = await p.evaluate(async (u) => (await fetch(u)).status, `${B}/mi-cuenta/actividades/${ids.pasada}/participantes/exportar`);
  di('y exportables', exp === 200, `${exp}`);

  await q.goto(`${B}/evaluar/${slugPasada}`, { waitUntil: 'networkidle2' });
  di('**la encuesta de después sigue abierta**', !! (await q.$('form[action*="/evaluar/"]')), q.url().replace(B, ''));
  const qr = await p.evaluate(async (u) => { const res = await fetch(u); return `${res.status} ${res.headers.get('content-type')}`; }, `${B}/mi-cuenta/actividades/${ids.pasada}/qr.png`);
  di('**y su QR se descarga**', qr.startsWith('200 image/png'), qr);
  await anon.close();

  t('Consola');
  di('sin errores de JavaScript', errores.length === 0, errores.join(' | '));
} finally {
  await nav.close();
  console.log('  ' + ultima(limpiar()));
}

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
