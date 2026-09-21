// El listado histórico del cliente, importado (2026-09-21).
//
// Tres cosas que la importación tiene que dejar funcionando, y que no se ven
// mirando la tabla:
//
//   1. El buscador —del registro y del paso 3 del wizard— las ofrece como
//      libres, para que cada una se ponga su contraseña.
//   2. Reclamar una que llegó SIN TIPO se queda con el que se eligió en el
//      paso 2. Antes el tipo de la reclamada mandaba siempre, y con el listado
//      del cliente eso la dejaba sin tipo para siempre.
//   3. La marquesina las enseña con su logo, sin repetir, y a una velocidad
//      que se pueda leer: con doscientas pastillas, los 34 s de antes las
//      hacían pasar como un borrón.
//
// Necesita la importación hecha en local:
//
//   php artisan dps:importar-organizaciones database/importacion/organizaciones/organizaciones-importar.csv \
//     --logos=database/importacion/organizaciones/logos
//   node pruebas/importacion-organizaciones.mjs
//
// El punto 2 escribe (crea una cuenta y una actividad): no correr contra
// producción. Deja la base como estaba.
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
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();

// Ver `marquesina.mjs`: la consola de Windows devuelve las tildes rotas.
const igualable = (texto) => (texto ?? '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/[^a-zA-Z0-9 ]/g, '')
  .replace(/\s+/g, ' ')
  .trim()
  .toLowerCase();

const importadas = Number(ultima(tinker(
  "echo App\\Models\\Organization::whereNotNull('anios_participacion')->whereNull('user_id')->count();"
)));

if (importadas < 100) {
  console.log(`Sólo hay ${importadas} organizaciones importadas: corre antes la importación (ver cabecera).`);
  process.exit(1);
}

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

const salir = async () => {
  if (! p.url().startsWith(B)) await p.goto(`${B}/`, { waitUntil: 'domcontentloaded' });

  await p.evaluate(async (base) => {
    for (const ruta of ['/mi-cuenta/logout', '/admin/logout']) {
      const doc = await (await fetch(base + '/', { credentials: 'same-origin' })).text();
      const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
      if (! token) continue;
      await fetch(base + ruta, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + encodeURIComponent(token),
      });
    }
  }, B);
};

/* ═══════════════ 1. El buscador las ofrece ═══════════════ */

const PANTALLAS = [
  {
    nombre: 'Crear cuenta de organizador',
    abrir: async () => {
      await salir();
      await p.goto(`${B}/mi-cuenta/registro`, { waitUntil: 'networkidle2' });
      await p.waitForFunction(() => window.Alpine !== undefined);
      await esperar(250);
    },
  },
  {
    nombre: 'Wizard (paso 3)',
    abrir: async () => {
      await salir();
      await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
      await p.waitForFunction(() => window.Alpine !== undefined);
      await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 3; });
      await esperar(300);
    },
  },
];

// Una con tilde y otra con logo convertido de PDF: las dos formas de fallar.
const BUSCADAS = [
  { escribir: 'Aldeas Infantiles', nombre: 'ALDEAS INFANTILES SOS' },
  { escribir: 'Javier Arrieta', nombre: 'FUNDACIÓN JAVIER ARRIETA' },
];

for (const pantalla of PANTALLAS) {
  t(`El buscador — ${pantalla.nombre}`);

  for (const b of BUSCADAS) {
    await pantalla.abrir();
    await p.type('input[name="org_nombre"]', b.escribir);
    const salieron = await p.waitForFunction(() => document.querySelectorAll('.org-sugerencia').length > 0, { timeout: 8000 })
      .then(() => true).catch(() => false);
    const sugerencias = salieron
      ? await p.$$eval('.org-sugerencia', (n) => n.map((x) => x.innerText.replace(/\s+/g, ' ').trim()))
      : [];
    const suya = sugerencias.find((s) => igualable(s).includes(igualable(b.nombre)));

    di(`«${b.escribir}» ofrece ${b.nombre}`, !! suya, suya ?? sugerencias.join(' · '));
    di('  y como libre, para reclamarla', !! suya && /en el listado/i.test(suya));
  }
}

/* ═══════════════ 2. Reclamar una sin tipo ═══════════════ */

t('Reclamar desde el wizard una organización del listado sin tipo');

const SELLO = Date.now();
const SIN_TIPO = `Fundación Importada Sin Tipo ${SELLO}`;
const correo = `imp${SELLO}@ejemplo.cl`;

const idSinTipo = ultima(tinker(
  `echo App\\Models\\Organization::create(['user_id' => null, 'nombre' => '${SIN_TIPO}', 'tipo' => null,`
  + ` 'anios_participacion' => '2024, 2025', 'activo' => true, 'verificada' => false])->id;`
));

const limpiar = () => tinker(
  `$o = App\\Models\\Organization::withTrashed()->find(${idSinTipo});`
  + ` if ($o) { $o->activities()->withTrashed()->get()->each->forceDelete(); $o->forceDelete(); }`
  + ` App\\Models\\User::where('email','${correo}')->forceDelete(); echo 'LIMPIO';`
);

try {
  await salir();
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });

  const envio = await p.evaluate(async (d) => {
    const doc = await (await fetch(d.base + '/publicar-actividad', { credentials: 'same-origin' })).text();
    const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];

    const f = new FormData();
    f.append('_token', token);
    f.append('org_nombre', d.nombre);
    f.append('org_id', d.orgId);
    // Lo que eligió en el paso 2: la organización no traía ninguno.
    f.append('org_tipo', 'Organización sin fines de lucro');
    f.append('email', d.correo);
    f.append('password', 'ClaveLarga123');
    f.append('password_confirmation', 'ClaveLarga123');
    f.append('titulo', 'Actividad de organización importada');
    f.append('descripcion', 'Prueba de reclamar una organización del listado del cliente.');
    f.append('formato', 'Online');
    f.append('sin_fecha_definida', '1');
    f.append('temas[]', d.tema);
    f.append('caracteristicas[]', d.carac);
    f.append('publicos[]', d.publico);

    const r = await fetch(d.base + '/publicar-actividad', { method: 'POST', body: f, credentials: 'same-origin' });

    return { estado: r.status, url: r.url };
  }, {
    base: B,
    nombre: SIN_TIPO,
    orgId: idSinTipo,
    correo,
    tema: ultima(tinker("echo App\\Models\\TaxonomyTerm::where('grupo','tema')->value('id');")),
    carac: ultima(tinker("echo App\\Models\\TaxonomyTerm::where('grupo','caracteristica')->value('id');")),
    publico: ultima(tinker("echo App\\Models\\TaxonomyTerm::where('grupo','publico')->value('id');")),
  });

  di('El envío se acepta', envio.estado === 200 && envio.url.includes('/listo'), `${envio.estado} → ${envio.url.replace(B, '')}`);

  const [dueno, tipo, cuantas] = ultima(tinker(
    `$o = App\\Models\\Organization::find(${idSinTipo}); $u = App\\Models\\User::where('email','${correo}')->first();`
    + ` echo ($o->user_id === ($u->id ?? 0) ? 'SUYA' : 'NO').'|'.($o->tipo ?? 'NULO')`
    + `.'|'.App\\Models\\Organization::where('nombre','${SIN_TIPO}')->count();`
  )).split('|');

  di('La organización queda a nombre de la cuenta nueva', dueno === 'SUYA');
  di('**Y se queda con el tipo que se eligió en el paso 2**', igualable(tipo) === igualable('Organización sin fines de lucro'), tipo);
  di('Sin duplicarla', cuantas === '1', `${cuantas} con ese nombre`);
} finally {
  di('La prueba deja la base como estaba', ultima(limpiar()) === 'LIMPIO');
}

/* ═══════════════ 3. La marquesina ═══════════════ */

t('La marquesina: las importadas, con su logo y sin repetir');

await salir();
await p.goto(`${B}/`, { waitUntil: 'networkidle2' });

const chips = await p.$$eval('.marquee-track .logo-chip', (n) => n.map((c) => ({
  nombre: c.querySelector('span:last-child')?.textContent.trim(),
  logo: c.querySelector('img')?.getAttribute('src') ?? null,
  duplicado: c.getAttribute('aria-hidden') === 'true',
})));
const pasada = chips.filter((c) => ! c.duplicado);
const nombres = pasada.map((c) => igualable(c.nombre));

const [esperadas, conLogo] = ultima(tinker(
  "$q = App\\Models\\Organization::where('activo',true)->where(fn($q) => $q"
  + "->whereHas('activities', fn($a) => $a->where('estado','publicada'))->orWhereNotNull('anios_participacion'));"
  + " $l = $q->get(['nombre','logo_path'])->unique(fn($o) => mb_strtolower(trim($o->nombre)));"
  + " echo $l->count().'|'.$l->whereNotNull('logo_path')->count();"
)).split('|').map(Number);

di('Salen todas: las que publicaron y las del listado', pasada.length === esperadas, `${pasada.length} en pantalla · ${esperadas} en la base`);
di('Y entre ellas las importadas', nombres.includes(igualable('ALDEAS INFANTILES SOS')) && nombres.includes(igualable('ZURICH')));

const repetidos = nombres.filter((n, i) => nombres.indexOf(n) !== i);
di('**Ningún nombre se repite dentro de una pasada**', repetidos.length === 0, repetidos.join(' · '));
di('Las que tienen logo lo enseñan', pasada.filter((c) => c.logo).length === conLogo, `${pasada.filter((c) => c.logo).length} de ${conLogo}`);

const logos = [...new Set(pasada.map((c) => c.logo).filter(Boolean))];
di('Ningún logo se repite en dos organizaciones', logos.length === pasada.filter((c) => c.logo).length,
  `${logos.length} archivos distintos`);

/*
 * Que carguen de verdad y se dejen decodificar: una ruta mal guardada o un
 * archivo que no es imagen pinta un <img> roto que ninguna consulta ve. Se
 * piden fuera del carril porque ahí van con `loading="lazy"` y sólo se
 * cargan al pasar por delante.
 */
const rotos = await p.evaluate(async (srcs) => {
  const malos = [];

  await Promise.all(srcs.map((src) => new Promise((listo) => {
    const i = new Image();
    i.onload = () => { if (! i.naturalWidth) malos.push(src); listo(); };
    i.onerror = () => { malos.push(src); listo(); };
    i.src = src;
  })));

  return malos;
}, logos);
di('**Todos los logos cargan y son imagen**', rotos.length === 0, rotos.slice(0, 5).join(' · '));

// Van con `loading="lazy"`: hay que traer la marquesina a la pantalla.
await p.evaluate(() => document.querySelector('.marquee').scrollIntoView({ block: 'center' }));
await p.waitForFunction(() => [...document.querySelectorAll('.marquee-track .logo-chip img')]
  .some((i) => i.complete && i.naturalWidth), { timeout: 10000 }).catch(() => null);
await esperar(500);

const alto = await p.$$eval('.marquee-track .logo-chip img', (n) => n.filter((i) => i.complete && i.naturalWidth)
  .map((i) => Math.round(i.getBoundingClientRect().height)));
di('Y se pintan a 30 px de alto', alto.length > 0 && alto.every((h) => h === 30), `${alto.length} ya cargados`);

/*
 * La velocidad: cuántos píxeles por segundo recorre el carril. Con las once
 * pastillas de antes eran unos 65; lo que no puede pasar es que doscientas
 * vayan a más de 1.000 porque la duración siguió siendo 34 s.
 */
const velocidad = await p.evaluate(() => {
  const carril = document.querySelector('.marquee-track');
  const segundos = parseFloat(getComputedStyle(carril).animationDuration);

  return { segundos, pxs: Math.round((carril.scrollWidth / 2) / segundos) };
});
di('**Se mueve a una velocidad que se puede leer**', velocidad.pxs > 20 && velocidad.pxs < 90,
  `${velocidad.pxs} px/s · ${velocidad.segundos} s por vuelta`);

di('Sin errores de JavaScript', errores.length === 0, errores.join(' | '));

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
