// C4 — el paso 3 «Tu organización» se salta cuando no hay nada que preguntar.
//
// Lo que se comprueba:
//
//   · Con la ficha completa —incluido el logo— el paso 3 no se pinta: el
//     «Continuar» del 2 lleva al 4 y el «Volver» del 4 devuelve al 2.
//   · La barra enseña cuatro pasos y RENUMERA: «Tu actividad» sale como el 3.
//     Sin renumerar quedaría 1, 2, 4, 5, que es decir que falta un paso.
//   · Si falta algo, el paso 3 se pinta pero sólo con eso: con el logo fuera,
//     ni el buscador de organizaciones ni nada más.
//   · Y lo que no puede pasar: cambiar el tipo de organización en el paso 2
//     deja el 3 saltado aunque el tipo nuevo exija un campo que la ficha no
//     tiene. Sería un campo obligatorio que no está en pantalla.
//
//   node pruebas/paso3-salto.mjs
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

const SELLO = Date.now();
const CORREO = `paso3.${SELLO}@ejemplo.cl`;
const CLAVE = 'paso3-salto-2026';
const ORG = `Fundación Ficha Completa ${SELLO}`;

/* El escenario: una cuenta con la ficha entera, logo incluido. */
tinker(
  `$u = App\\Models\\User::create(['name' => 'Ficha Completa', 'email' => '${CORREO}',`
  + ` 'password' => bcrypt('${CLAVE}'), 'rol' => 'organizador', 'activo' => true,`
  + ` 'email_verified_at' => now()]);`
  + ` App\\Models\\Organization::create(['user_id' => $u->id, 'nombre' => '${ORG}',`
  + ` 'tipo' => 'Organización sin fines de lucro', 'activo' => true,`
  + ` 'logo_path' => 'storage/organizaciones/prueba.png']);`
  + ` echo 'ESCENARIO';`
);

const limpiar = () => tinker(
  `$u = App\\Models\\User::withTrashed()->where('email','${CORREO}')->first();`
  + ` if ($u) { $u->organization?->forceDelete(); $u->forceDelete(); }`
  + ` App\\Models\\AccessLog::where('email','${CORREO}')->delete(); cache()->flush();`
  + ` echo 'LIMPIO';`
);

/** Deja o quita el logo de la ficha, que es lo que decide si el paso 3 se pinta. */
const logo = (valor) => tinker(
  `App\\Models\\Organization::where('nombre','${ORG}')->update(['logo_path' => ${valor}]);`
  + ` echo 'LOGO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

/*
 * Del estado de Alpine se sacan valores sueltos y no el objeto: es un proxy
 * con métodos y referencias circulares, y devolverlo entero llega a Node medio
 * vacío.
 */
const estado = () => p.evaluate(() => {
  const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));

  return { paso: d.paso, tipo: d.tipo, salta: d.saltaPaso3(), cambiado: d.tipoCambiado() };
});

/** Lo que se VE en la barra: el número de dentro del círculo y la etiqueta. */
const barra = () => p.evaluate(() => [...document.querySelectorAll('.steplink')]
  .filter((b) => b.offsetParent !== null)
  .map((b) => b.innerText.replace(/\s+/g, ' ').trim()));

/*
 * Los campos de la organización que se le están pidiendo de verdad.
 *
 * Se mide la altura y no el `display`: el campo del logo es un `<input>` con
 * `display:none` de siempre —se pulsa la etiqueta—, así que preguntarle a él
 * daría oculto incluso cuando sí se está pidiendo. Se mira el bloque.
 */
const CAMPOS = ['org_nombre', 'org_tipo_otro', 'org_unidad_educativa', 'org_logo'];

const pideEnPaso3 = () => p.evaluate((campos) => campos
  .filter((c) => {
    const e = document.querySelector(`[data-campo="${c}"]`);

    return e && e.getBoundingClientRect().height > 0;
  }), CAMPOS);

const abrirWizard = async () => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(300);
};

/** Pulsa un botón por su texto, dentro del paso que se esté viendo. */
const pulsar = async (texto) => {
  await p.evaluate((txt) => {
    const b = [...document.querySelectorAll('button')]
      .filter((x) => x.offsetParent !== null)
      .find((x) => x.innerText.trim().startsWith(txt));
    b?.click();
  }, texto);
  await esperar(350);
};

try {
  t('Entrar con la cuenta de ficha completa');

  await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', CORREO);
  await p.type('input[type="password"]', CLAVE);
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate(() => document.querySelector('form[method="POST"]').submit()),
  ]);
  di('La sesión queda abierta', ! p.url().includes('/login'), p.url());

  t('Ficha completa: el paso 3 no existe');

  await abrirWizard();
  const b1 = await barra();
  di('La barra enseña cuatro pasos', b1.length === 4, b1.join(' | '));
  di('«Tu organización» no está en la barra', ! b1.some((x) => x.includes('Tu organización')));
  di('«Tu actividad» se enseña como el 3', b1.some((x) => x.startsWith('3') && x.includes('Tu actividad')), b1[2]);
  di('«Enviado» se enseña como el 4', b1.some((x) => x.startsWith('4') && x.includes('Enviado')), b1[3]);
  di('Sin hueco: los números van 1, 2, 3, 4', b1.map((x) => x[0]).join('') === '1234', b1.map((x) => x[0]).join(''));

  const e0 = await estado();
  di('El componente sabe que salta el 3', e0.salta === true);

  t('La navegación lo esquiva en los dos sentidos');

  await pulsar('No, solo quiero difundir');
  di('Del 1 se va al 2', (await estado()).paso === 2);

  await pulsar('Continuar');
  di('El «Continuar» del 2 lleva al 4, no al 3', (await estado()).paso === 4, `paso ${(await estado()).paso}`);

  /*
   * Hacia atrás no hay botón: el paso 4 no lleva «Volver» y el círculo del 3
   * está escondido, así que al 3 sólo se puede llegar pidiéndolo por su
   * nombre. Se pide, que es la única forma de comprobar que también se
   * esquiva en ese sentido y no deja a nadie encerrado.
   */
  await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).irA(3));
  await esperar(300);
  di('Pedir el paso 3 desde el 4 devuelve al 2', (await estado()).paso === 2, `paso ${(await estado()).paso}`);

  await p.evaluate(() => {
    const b = [...document.querySelectorAll('.steplink')].filter((x) => x.offsetParent !== null);
    b.find((x) => x.innerText.includes('Tu actividad'))?.click();
  });
  await esperar(300);
  di('La barra lleva a «Tu actividad» y es el paso 4 de dentro', (await estado()).paso === 4);

  t('Cambiar el tipo en el paso 2 devuelve el paso 3');

  await p.evaluate(() => {
    const b = [...document.querySelectorAll('.steplink')].filter((x) => x.offsetParent !== null);
    b.find((x) => x.innerText.includes('Tipo de org.'))?.click();
  });
  await esperar(300);
  await p.evaluate(() => {
    [...document.querySelectorAll('button')]
      .filter((x) => x.offsetParent !== null)
      .find((x) => x.innerText.trim() === 'Otra')?.click();
  });
  await esperar(300);

  const e1 = await estado();
  di('El componente ve el tipo cambiado', e1.cambiado === true, e1.tipo);
  di('Y deja de saltar el paso 3', e1.salta === false);

  const b2 = await barra();
  di('La barra vuelve a enseñar cinco pasos', b2.length === 5, b2.join(' | '));
  di('«Tu organización» vuelve como el 3', b2.some((x) => x.startsWith('3') && x.includes('Tu organización')));
  di('«Tu actividad» vuelve a ser el 4', b2.some((x) => x.startsWith('4') && x.includes('Tu actividad')));

  await pulsar('Continuar');
  di('El «Continuar» del 2 ya lleva al 3', (await estado()).paso === 3);

  const c1 = await pideEnPaso3();
  di('Y pide el campo que el tipo nuevo exige', c1.includes('org_tipo_otro'), c1.join(', ') || '(nada)');
  di('Sin volver a pedir el nombre', ! c1.includes('org_nombre'), c1.join(', ') || '(nada)');
  di('Ni el logo, que ya lo tiene', ! c1.includes('org_logo'), c1.join(', ') || '(nada)');

  t('Falta el logo: el paso 3 se pinta, pero sólo con el logo');

  logo('null');
  await abrirWizard();

  const b3 = await barra();
  di('La barra vuelve a los cinco pasos', b3.length === 5, b3.join(' | '));
  di('«Tu organización» está de vuelta', b3.some((x) => x.includes('Tu organización')));
  di('El componente no salta el 3', (await estado()).salta === false);

  await pulsar('No, solo quiero difundir');
  await pulsar('Continuar');
  di('El «Continuar» del 2 lleva al 3', (await estado()).paso === 3);

  const c2 = await pideEnPaso3();
  di('Se le pide el logo', c2.includes('org_logo'), c2.join(', ') || '(nada)');
  di('Y no se le vuelve a pedir el nombre', ! c2.includes('org_nombre'), c2.join(', ') || '(nada)');

  const oculto = await p.$eval('input[type="hidden"][name="org_nombre"]', (e) => e.value).catch(() => null);
  di('Pero el nombre viaja igual, en un campo oculto', oculto === ORG, String(oculto));

  t('Errores de JavaScript');
  di('Ninguno', errores.length === 0, errores.join(' | '));
} finally {
  await nav.close();
  limpiar();
}

console.log('');
console.log(`RESULTADO: ${ok} bien, ${mal} mal`);
process.exit(mal === 0 ? 0 : 1);
