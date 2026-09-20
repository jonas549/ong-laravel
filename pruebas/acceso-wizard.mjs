// P14 — iniciar sesión a mitad del wizard, sin perder lo escrito.
//
// Lo importante de este punto no es el botón: es que quien lleva medio
// formulario relleno pueda identificarse y **siga teniéndolo relleno**. Eso es
// lo que se comprueba aquí, campo por campo.
//
//   node pruebas/acceso-wizard.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

const CUENTA = 'organizador@ong-laravel.test';
const CLAVE = 'organizador1234';

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
const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
/*
 * Sólo los valores que hacen falta, y no el objeto entero: el estado de Alpine
 * es un proxy con métodos y referencias circulares, y devolverlo tal cual
 * llega a Node medio vacío. La primera versión de esta prueba fallaba en seis
 * sitios por eso, sin que hubiera nada roto.
 */
const estado = () => p.evaluate(() => {
  const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));

  return {
    conSesion: d.conSesion,
    accesoAbierto: d.accesoAbierto,
    accesoError: d.accesoError,
    correoCuenta: d.correoCuenta,
    formato: d.formato,
    sinFecha: d.sinFecha,
    temas: [...d.sel.temas],
  };
});

// El bloqueo por intentos cuenta sobre `access_logs`, así que las pruebas de
// contraseña mala dejan rastro. Se limpia el de esta cuenta al empezar.
const soltarElFreno = () => tinker(
  `App\\Models\\AccessLog::where('email','${CUENTA}')->delete(); cache()->flush(); echo 'libre';`
);

const abrirWizard = async () => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await esperar(250);
};

/* Cierra cualquier sesión abierta, para empezar como un visitante. */
const salir = async () => {
  await p.goto(`${B}/`, { waitUntil: 'domcontentloaded' });
  await p.evaluate(async (base) => {
    const doc = await (await fetch(base + '/', { credentials: 'same-origin' })).text();
    const token = (doc.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1];
    if (token) {
      await fetch(base + '/mi-cuenta/logout', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + encodeURIComponent(token),
      });
    }
  }, B);
};

soltarElFreno();
await salir();

/* ═══════════════ El aviso de arriba ══════════════════════════════ */

t('P14 — el aviso está arriba y sólo sin sesión');

await abrirWizard();

const aviso = await p.evaluate(() => {
  const caja = document.querySelector('.acceso-aviso');

  return caja ? { texto: caja.innerText.replace(/\s+/g, ' ').trim(), alto: caja.getBoundingClientRect().height, y: caja.getBoundingClientRect().top } : null;
});

di('El aviso existe', aviso !== null);
di('Y dice lo que pidió el cliente',
  !! aviso && /¿Ya tienes cuenta\?/.test(aviso.texto) && /Inicia sesión para crear tu actividad/.test(aviso.texto),
  aviso?.texto);
di('**Se ve de verdad**', !! aviso && aviso.alto > 0, aviso ? `alto ${Math.round(aviso.alto)} px` : '');
di('Y está arriba, antes del formulario', !! aviso && aviso.y < 400, aviso ? `a ${Math.round(aviso.y)} px` : '');

/* ═══════════════ Lo escrito no se pierde ═════════════════════════ */

t('Se rellena medio formulario ANTES de entrar');

await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 4; });
await esperar(300);

const TITULO = 'Actividad escrita antes de entrar';
const DESCRIPCION = 'Este texto tiene que seguir aquí después de iniciar sesión.';

await p.type('input[name="titulo"]', TITULO);
await p.type('textarea[name="descripcion"]', DESCRIPCION);
await p.evaluate(() => {
  const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
  d.sinFecha = true;
  d.formato = 'Online';
});
await esperar(200);

// Y un par de chips, que son estado de Alpine y no campos del DOM.
const temaId = await p.evaluate(() => {
  const chip = document.querySelector('#paso4 [x-on\\:click^="alternar(\'temas\'"]')
    ?? [...document.querySelectorAll('button')].find((b) => b.getAttribute('x-on:click')?.startsWith("alternar('temas'"));
  chip?.click();

  return Alpine.$data(document.querySelector('[x-data^="wizard"]')).sel.temas.length;
});
di('Hay un tema elegido antes de entrar', temaId > 0, `${temaId} temas`);

t('Se entra desde el diálogo, sin recargar');

await p.evaluate(() => {
  [...document.querySelectorAll('.acceso-aviso button')][0]?.click();
});
await esperar(300);

di('El diálogo se abre', await p.evaluate(() => (document.querySelector('.acceso-caja')?.getBoundingClientRect().height ?? 0) > 0));
di('Ofrece recuperar la contraseña', await p.evaluate(() => !! [...document.querySelectorAll('.acceso-caja a')]
  .find((a) => /olvidaste/i.test(a.textContent))));
di('Y la puerta de siempre, por si acaso', await p.evaluate(() => !! [...document.querySelectorAll('.acceso-caja a')]
  .find((a) => a.getAttribute('href')?.includes('/mi-cuenta/login'))));

// Primero, una contraseña mala: tiene que decirlo y no romper nada.
await p.type('[data-acceso-correo]', CUENTA);
await p.type('.acceso-caja input[type="password"]', 'estanoes');
await p.evaluate(() => [...document.querySelectorAll('.acceso-caja button')].find((b) => /Entrar/.test(b.textContent))?.click());
await p.waitForFunction(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).accesoError !== '', { timeout: 6000 });

di('Una contraseña mala lo dice', /No encontramos una cuenta/.test((await estado()).accesoError),
  (await estado()).accesoError);
di('Y no abre sesión', (await estado()).conSesion === false);
di('Lo escrito sigue intacto tras el fallo',
  await p.$eval('input[name="titulo"]', (n) => n.value) === TITULO);

// Y ahora la buena.
await p.evaluate(() => {
  const caja = document.querySelector('.acceso-caja');
  caja.querySelector('input[type="password"]').value = '';
});
await p.type('.acceso-caja input[type="password"]', CLAVE);
await p.evaluate(() => [...document.querySelectorAll('.acceso-caja button')].find((b) => /Entrar/.test(b.textContent))?.click());
await p.waitForFunction(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).conSesion === true, { timeout: 8000 });
await esperar(400);

const tras = await estado();
di('**La sesión queda abierta**', tras.conSesion === true);
di('El diálogo se cierra solo', tras.accesoAbierto === false);
di('Y el aviso de «¿ya tienes cuenta?» desaparece',
  await p.evaluate(() => (document.querySelector('.acceso-aviso')?.getBoundingClientRect().height ?? 0) === 0));

t('**Y nada de lo escrito se ha perdido**');

di('El título sigue', await p.$eval('input[name="titulo"]', (n) => n.value) === TITULO,
  await p.$eval('input[name="titulo"]', (n) => n.value));
di('La descripción sigue', await p.$eval('textarea[name="descripcion"]', (n) => n.value) === DESCRIPCION);
di('El formato elegido sigue', tras.formato === 'Online', tras.formato);
di('La casilla de fecha permanente sigue', tras.sinFecha === true);
di('Y los temas elegidos siguen', tras.temas.length === temaId, `${tras.temas.length} temas`);

t('El paso 3 pasa a ser el de una cuenta que ya existe');

await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 3; });
await esperar(300);

const paso3 = await texto();
di('Dice a qué cuenta se sumará', paso3.includes('Esta actividad se sumará a tu cuenta'));
di('Con su correo', paso3.includes(CUENTA));
di('Ya no se pide crear acceso', ! paso3.includes('Crea tu acceso'));

di('El nombre de su organización viene relleno',
  (await p.$eval('input[name="org_nombre"]', (n) => n.value)).length > 0,
  await p.$eval('input[name="org_nombre"]', (n) => n.value));

/*
 * Y los campos de correo y contraseña no pueden viajar: la regla del servidor
 * es `prohibited` con sesión abierta, así que si se enviaran el formulario
 * rebotaría entero.
 */
di('**Correo y contraseña ya no se envían**', await p.evaluate(() => {
  const campos = ['email', 'password', 'password_confirmation']
    .map((n) => document.querySelector(`[name="${n}"]`))
    .filter(Boolean);

  return campos.length > 0 && campos.every((c) => c.disabled);
}));

/* ═══════════════ Y que la actividad se publique de verdad ════════ */

t('Se envía el formulario con la sesión abierta a mitad');

/*
 * Éste es el remate. Con sesión abierta, las reglas del servidor marcan correo
 * y contraseña como `prohibited`: si esos campos viajaran, el formulario
 * rebotaría entero y quien entró a mitad perdería todo justo al enviar, que es
 * lo contrario de lo que pide el punto.
 */
await p.evaluate(() => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = 4; });
await esperar(300);

/*
 * Lo que falta para que el formulario esté completo, rellenado como lo haría
 * una persona: pulsando los chips y escribiendo en los campos. La primera
 * versión tocaba el estado de Alpine directamente y la guía de errores seguía
 * pidiendo la dirección, porque lo que ella mira es el DOM.
 */
await p.evaluate(() => {
  const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
  const primero = (grupo) => {
    const b = [...document.querySelectorAll('button')]
      .find((x) => x.getAttribute('x-on:click')?.startsWith(`alternar('${grupo}'`));
    if (b && d.sel[grupo].length === 0) b.click();
  };

  primero('temas');
  primero('caracteristicas');
  primero('publicos');
});
await esperar(250);

/*
 * Fecha y lugar: se marca «disponible de forma permanente», que es la casilla
 * que releva a la fecha, la región, la comuna y la dirección de golpe
 * (`data-obligatorio-salvo`). Se pulsa la casilla de verdad y no se toca el
 * estado de Alpine: lo que la guía mira es el DOM.
 */
await p.evaluate(() => {
  const casilla = document.querySelector('input[name="sin_fecha_definida"]');
  if (casilla && ! casilla.checked) casilla.click();
});
await esperar(300);

const faltan = await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]'))
  .camposQueFaltan().map((e) => e.campo));
di('El formulario queda completo antes de enviar', faltan.length === 0, faltan.join(' · '));

const cuantasActividades = () => Number(ultimaLinea(tinker("echo App\\Models\\Activity::count();")));

const antes = cuantasActividades();
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => null),
  p.evaluate(() => {
    [...document.querySelectorAll('button[type="submit"]')]
      .find((b) => /Publicar|Enviar/i.test(b.textContent))?.click();
  }),
]);
await esperar(600);

const despues = cuantasActividades();
di('**La actividad se publica sin rebotar**', despues === antes + 1 && /\/listo/.test(p.url()),
  `${antes} → ${despues} · ${p.url().replace(B, '')}`);

const deQuien = ultimaLinea(tinker(
  `$a = App\\Models\\Activity::latest('id')->first();`
  + ` echo $a->organization?->user?->email ?? 'sin cuenta';`
));
di('Y queda en la cuenta con la que se entró a mitad', deQuien === CUENTA, deQuien);

// Se deshace: es una actividad de prueba en el listado del organizador.
tinker(
  `$a = App\\Models\\Activity::where('titulo','${TITULO}')->first();`
  + ` if ($a) { $a->registrations()->forceDelete(); $a->forceDelete(); }`
  + ` echo 'limpio';`
);

di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

soltarElFreno();

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
