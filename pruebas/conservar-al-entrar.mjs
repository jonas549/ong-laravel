// Punto 2 de la tanda del 30/09: entrar desde el aviso «este correo ya tiene
// cuenta» conserva lo escrito — campo por campo, no sólo el título.
//
// `correo-existente.mjs` ya comprueba que el acceso del wizard no recarga y
// que el título, la descripción, el formato y un tema siguen ahí. Esto rellena
// el wizard ENTERO, archivos incluidos, y compara lo que el formulario
// enviaría antes y después de entrar: el `FormData` del <form>, que es la
// verdad de lo que viaja, no lo que se ve.
//
// Lo de la organización (paso 3) NO se conserva, y a propósito: al entrar, el
// formulario se rellena con la ficha de la cuenta («datos precargados», que el
// cliente dio por bueno). Se comprueba aparte que lo que queda es la ficha.
//
// Y al final se envía de verdad y se lee la actividad de la base: lo que vio
// el formulario tiene que ser lo que llegó.
//
//   node pruebas/conservar-al-entrar.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const W = '[x-data^="wizard"]';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

const SELLO = Date.now();
const CORREO = `conservar.${SELLO}@ejemplo.cl`;
const CLAVE = 'conservar-2026';
const ORG = `Fundación Ya Registrada ${SELLO}`;
const TITULO = `Actividad que no se pierde ${SELLO}`;

tinker(
  `$u = new App\\Models\\User(['name' => 'Conservar', 'email' => '${CORREO}', 'password' => bcrypt('${CLAVE}')]);`
  + ` $u->forceFill(['role' => App\\Models\\User::ROL_ORGANIZER, 'is_active' => true, 'email_verified_at' => now()])->save();`
  + ` App\\Models\\Organization::create(['user_id' => $u->id, 'nombre' => '${ORG}', 'slug' => 'conservar-${SELLO}',`
  + ` 'tipo' => 'Organización sin fines de lucro', 'logo_path' => 'storage/organizaciones/prueba.png', 'activo' => true]); echo 'OK';`
);

const limpiar = () => tinker(
  `$u = App\\Models\\User::where('email','${CORREO}')->first(); if ($u) { $o = $u->organization;`
  + ` foreach ($o?->activities()->withTrashed()->get() ?? [] as $a) { $a->terms()->detach(); $a->collaborators()->delete(); $a->statusLogs()->delete(); $a->forceDelete(); }`
  + ` $o?->forceDelete(); App\\Models\\AccessLog::where('user_id',$u->id)->delete(); $u->forceDelete(); } echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const alp = (f, ...a) => p.evaluate((W, src, ...a) => {
  const d = Alpine.$data(document.querySelector(W));
  return new Function('d', '...a', src)(d, ...a);
}, W, f, ...a);

const irA = async (n) => { await alp('d.paso = a[0]', n); await esperar(250); };
const valores = (sel) => p.$$eval(sel + ' option', (o) => o.map((x) => x.value).filter(Boolean));

/** Lo que el formulario enviaría ahora mismo, con los archivos por su nombre. */
const loQueViaja = () => p.evaluate((W) => {
  const form = document.querySelector(W).querySelector('form') ?? document.querySelector(W).closest('form');
  const fd = new FormData(form);
  const salida = {};
  for (const [k, v] of fd.entries()) {
    const valor = v instanceof File ? (v.size ? `[archivo ${v.name} · ${v.size} B]` : '') : v;
    (salida[k] ??= []).push(valor);
  }
  return Object.fromEntries(Object.entries(salida).map(([k, v]) => [k, v.join(' | ')]));
}, W);

try {
  t('1 · Rellenar el wizard entero sin sesión');

  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(300);

  await alp("d.tipo = 'Organización sin fines de lucro'");
  await irA(3);
  await p.type('input[name="org_nombre"]', 'Nombre escrito antes de entrar');
  const logo = await p.$('input[name="org_logo"]');
  await logo.uploadFile('public/img/logo-fundacion-trascender.png');
  await p.waitForFunction(() => { const d = Alpine.$data(document.querySelector('[data-campo="org_logo"]')); return d.tiene && ! d.reduciendo; }, { timeout: 8000 }).catch(() => null);

  await irA(4);
  await alp("d.formato = 'Presencial'; d.insc = true; d.acc = true; d.colab = true; d.colabs = ['Junta de Vecinos Uno', 'Club Deportivo Dos']; d.mismoCorreo = false");
  await esperar(250);
  await p.type('input[name="titulo"]', TITULO);
  await p.type('textarea[name="descripcion"]', 'Descripción completa que tiene que sobrevivir al inicio de sesión.');
  await p.type('input[name="fecha_inicio"]', '04122026');
  await p.select('select[name="hora_inicio"]', '10:00');
  await p.select('select[name="hora_termino"]', '13:00');
  const region = (await valores('select[name="region_id"]'))[2];
  await p.select('select[name="region_id"]', region);
  await p.waitForFunction(() => [...document.querySelectorAll('select[name="commune_id"] option')].some((o) => o.value));
  await p.select('select[name="commune_id"]', (await valores('select[name="commune_id"]'))[1]);
  await p.type('input[name="direccion"]', 'Avenida Siempreviva 742');
  await p.type('input[name="participantes_estimados"]', '120');
  await p.type('input[name="cupos_totales"]', '80');
  await p.type('textarea[name="accesibilidad_detalle"], input[name="accesibilidad_detalle"]', 'Rampa en la entrada y baño accesible.');
  // «¿Cuántos trabajadores participan como voluntarios?» sólo sale para
  // empresas, y ésta es una sin fines de lucro: no se escribe.
  await p.type('input[name="correo_contacto"]', 'contacto.actividad@ejemplo.cl');
  await p.type('input[name="enlace_web"]', 'misitio.cl/actividad');
  await p.type('input[name="enlace_red_social"]', 'instagram.com/miorganizacion');
  // Los enlaces se completan con `https://` al salir del campo (Q3). Se sale
  // aquí para que el «antes» ya lleve la forma final.
  await p.evaluate(() => document.activeElement?.blur());
  await esperar(150);
  const portada = await p.$('input[name="imagen"]');
  await portada.uploadFile('public/img/logo-fundacion-trascender.png');
  await esperar(1200);
  await p.evaluate(() => {
    for (const [g, n] of [['temas', 2], ['caracteristicas', 3], ['publicos', 2]]) {
      [...document.querySelectorAll(`[data-campo="${g}"] button.chip`)].slice(0, n).forEach((b) => b.click());
    }
  });
  await esperar(300);

  const antes = await loQueViaja();
  di('Hay un buen puñado de campos rellenos', Object.values(antes).filter(Boolean).length >= 25, `${Object.values(antes).filter(Boolean).length} con valor`);

  t('2 · El correo ya tiene cuenta: aviso y entrar desde él');

  await irA(3);
  await p.type('input[name="email"]', CORREO);
  await p.evaluate(() => document.querySelector('input[name="email"]').blur());
  await p.waitForFunction((W) => Alpine.$data(document.querySelector(W)).correoExiste === true, { timeout: 8000 }, W).catch(() => {});
  di('Sale el aviso', await alp('return d.correoExiste') === true);

  await p.evaluate(() => [...document.querySelectorAll('[data-correo-existe] button')].find((b) => b.textContent.trim() === 'Inicia sesión').click());
  await esperar(300);
  await p.type('.acceso-caja input[type="password"]', CLAVE);
  await p.keyboard.press('Enter');
  await p.waitForFunction((W) => Alpine.$data(document.querySelector(W)).conSesion === true, { timeout: 10000 }, W).catch(() => {});
  await esperar(500);
  di('Entra sin recargar la página', await alp('return d.conSesion') === true && p.url().endsWith('/publicar-actividad'), p.url());

  t('3 · Campo por campo');

  const despues = await loQueViaja();
  // Lo de la cuenta y la organización cambia a propósito; el resto, no.
  const deLaCuenta = (k) => /^(_token|email|password|password_confirmation|org_nombre|org_id|org_logo|org_tipo|org_tipo_otro|org_unidad_educativa)$/.test(k);
  const claves = [...new Set([...Object.keys(antes), ...Object.keys(despues)])].filter((k) => ! deLaCuenta(k)).sort();
  for (const k of claves) {
    di(`${k}`, (antes[k] ?? '') === (despues[k] ?? ''), (antes[k] ?? '') === (despues[k] ?? '')
      ? String(antes[k] ?? '').slice(0, 50)
      : `antes «${String(antes[k] ?? '').slice(0, 40)}» → después «${String(despues[k] ?? '').slice(0, 40)}»`);
  }

  t('4 · Lo de la organización pasa a ser lo de la cuenta');

  di('El nombre es el de su ficha', despues.org_nombre === ORG, despues.org_nombre);
  di('Ya no viaja correo ni contraseña', ! despues.email && ! despues.password);

  t('5 · Se envía, y lo guardado es lo escrito');

  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => null),
    p.click('button[type="submit"]'),
  ]);
  di('Llega a la pantalla final', /\/listo/.test(p.url()), p.url().replace(B, ''));

  const g = JSON.parse(tinker(
    `$a = App\\Models\\Activity::where('titulo','${TITULO}')->with(['terms','collaborators','organization'])->first();`
    + ` echo json_encode($a ? ['org' => $a->organization->nombre, 'fecha' => $a->fecha_inicio?->format('Y-m-d'), 'hi' => substr($a->hora_inicio,0,5), 'ht' => substr($a->hora_termino,0,5),`
    + ` 'region' => $a->region_id, 'dir' => $a->direccion, 'part' => $a->participantes_estimados, 'cupos' => $a->cupos_totales, 'insc' => $a->inscripcion_habilitada,`
    + ` 'acc' => $a->accesibilidad_detalle, 'correo' => $a->correo_contacto, 'terms' => $a->terms->count(), 'colabs' => $a->collaborators->pluck('nombre'),`
    + ` 'portada' => (bool) $a->imagen_portada, 'web' => $a->organization->enlace_web, 'vol' => $a->organization->num_voluntarios] : null);`
  ));
  di('La actividad existe y es de su organización', g?.org === ORG, g?.org);
  di('Fecha y horas', g?.fecha === '2026-12-04' && g?.hi === '10:00' && g?.ht === '13:00', `${g?.fecha} ${g?.hi}-${g?.ht}`);
  di('Región y dirección', String(g?.region) === region && g?.dir === 'Avenida Siempreviva 742', `${g?.region} · ${g?.dir}`);
  di('Participantes, inscripción y cupos', g?.part === 120 && g?.insc && g?.cupos === 80, `${g?.part} · ${g?.insc} · ${g?.cupos}`);
  di('Accesibilidad', g?.acc === 'Rampa en la entrada y baño accesible.');
  di('Correo de contacto', g?.correo === 'contacto.actividad@ejemplo.cl', g?.correo);
  di('Temas, características y públicos (2 + 3 + 2)', g?.terms === 7, String(g?.terms));
  di('Colaboradores', JSON.stringify(g?.colabs) === JSON.stringify(['Junta de Vecinos Uno', 'Club Deportivo Dos']), JSON.stringify(g?.colabs));
  di('Imagen de portada', g?.portada === true);
  di('Enlace web, en la organización', g?.web === 'https://misitio.cl/actividad', g?.web);

  t('Consola');
  di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 3).join(' | '));
} catch (err) {
  di('La prueba terminó sin excepciones', false, String(err).slice(0, 200));
} finally {
  await nav.close();
  console.log('');
  console.log(limpiar());
  console.log(`\n${ok} OK · ${mal} MAL`);
  process.exit(mal ? 1 : 0);
}
