// Q2, Q3 y Q4 de la quinta tanda.
//
//   Q2 — el logo de la organización se ve de verdad en la ficha: no basta con
//        que el `<img>` esté ahí, tiene que medir algo que se lea.
//   Q3 — un enlace sin `https://` no rebota, en las tres pantallas donde se
//        puede escribir uno, y se guarda con el protocolo puesto.
//   Q4 — el botón del kit de difusión sale en la barra de todas las pantallas
//        de mi-cuenta, y no sale si el enlace está vacío.
//
//   node pruebas/quinta-tanda.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => (s.split('\n').filter((l) => l.trim()).pop() ?? '').trim();

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
const entrar = async (puerta, c, k) => {
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', c);
  await p.type('input[name="password"]', k);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};
const salir = () => p.evaluate(async () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  for (const puerta of ['/admin/logout', '/mi-cuenta/logout']) {
    await fetch(puerta, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _token: token ?? '' }).toString(),
      credentials: 'same-origin',
    }).catch(() => {});
  }
});

/* ═══════════════ Q2 — el logo en la ficha ════════════════════════════ */

t('Q2 — el logo de la organización se lee en la ficha');

/*
 * Se comprueba con un logo APAISADO, que es lo que había roto: metido en un
 * cuadrado de 54 px, uno de 668×100 quedaba en 54×8 y no se leía ni una letra.
 * Lo que se mide es el ancho pintado.
 *
 * El logo se siembra aquí y se quita al final, pero **sólo si no hay ninguno
 * ya**: en una base local recién sembrada ninguna organización tiene, y sin
 * logo esta parte no probaría nada. En producción las hay de verdad y no se
 * toca nada.
 */
const yaHabiaLogo = ultima(tinker(
  "echo App\\Models\\Organization::whereNotNull('logo_path')"
  + "->whereHas('activities', fn($q) => $q->where('estado','publicada'))->value('id') ?: '';"
));

const SEMBRADO = 'storage/organizaciones/prueba-apaisado.png';
let organizacionSembrada = null;

if (! yaHabiaLogo) {
  organizacionSembrada = ultima(tinker(
    "$o = App\\Models\\Organization::whereHas('activities', fn($q) => $q->where('estado','publicada'))->first();"
    + " $dir = public_path('storage/organizaciones'); @mkdir($dir, 0777, true);"
    + " $im = imagecreatetruecolor(668, 100);"
    + " imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));"
    + " imagestring($im, 5, 20, 40, 'LOGO APAISADO', imagecolorallocate($im, 0, 0, 0));"
    + " imagepng($im, $dir.'/prueba-apaisado.png'); imagedestroy($im);"
    + " $o->update(['logo_path' => '" + SEMBRADO + "']); echo $o->id;"
  ));
}

const ficha = ultima(tinker(
  "$o = App\\Models\\Organization::whereNotNull('logo_path')"
  + "->whereHas('activities', fn($q) => $q->where('estado','publicada'))->first();"
  + " echo $o ? $o->activities()->where('estado','publicada')->value('id') : '';"
));

if (! ficha) {
  console.log('  (sin organización con logo y actividad publicada: se salta Q2)');
} else {
  await p.goto(`${B}/activity/${ficha}`, { waitUntil: 'networkidle2' });

  const logo = await p.evaluate(() => {
    const im = document.querySelector('.org-firma img.org-logo');
    if (! im) return null;

    const r = im.getBoundingClientRect();

    return {
      caja: { w: Math.round(r.width), h: Math.round(r.height) },
      natural: { w: im.naturalWidth, h: im.naturalHeight },
      carga: im.naturalWidth > 0,
      src: im.getAttribute('src'),
    };
  });

  di('La ficha pinta un logo, no las iniciales', logo !== null, logo?.src ?? '(iniciales)');
  di('Y la imagen carga', logo?.carga === true, `${logo?.natural.w}×${logo?.natural.h}`);

  /*
   * El alto es fijo y el ancho manda. Un logo apaisado tiene que salir más
   * ancho que alto; el cuadrado se queda en 54×54, que también vale.
   */
  const apaisado = logo && logo.natural.w / logo.natural.h > 1.6;
  di('**El ancho se adapta a la forma del logo**',
    ! apaisado || (logo.caja.w > logo.caja.h + 8),
    `pintado ${logo?.caja.w}×${logo?.caja.h} · original ${logo?.natural.w}×${logo?.natural.h}`);
  di('Sin pasarse del ancho que se come el nombre', (logo?.caja.w ?? 0) <= 170, `${logo?.caja.w}px`);
}

/* ═══════════════ Q3 — enlaces sin protocolo ══════════════════════════ */

t('Q3 — el wizard acepta un enlace sin https://');

await salir();
await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await esperar(300);

const tipoWizard = await p.$eval('input[name="enlace_web"]', (e) => e.type);
di('El campo no es type="url", que corta el envío desde el navegador',
  tipoWizard === 'text', `type="${tipoWizard}"`);
di('Pero sigue pidiendo el teclado de direcciones',
  (await p.$eval('input[name="enlace_web"]', (e) => e.inputMode)) === 'url');

await p.evaluate(() => {
  const c = document.querySelector('input[name="enlace_web"]');
  c.value = 'www.tusitio.cl';
  c.dispatchEvent(new Event('input', { bubbles: true }));
  c.dispatchEvent(new Event('blur', { bubbles: true }));
});
await esperar(200);
di('Al salir del campo se completa solo',
  (await p.$eval('input[name="enlace_web"]', (e) => e.value)) === 'https://www.tusitio.cl',
  await p.$eval('input[name="enlace_web"]', (e) => e.value));

di('Y el navegador lo da por válido',
  await p.$eval('input[name="enlace_web"]', (e) => e.checkValidity()));

t('Q3 — y el servidor también, aunque llegue sin protocolo');

/*
 * La comprobación que de verdad importa: el navegador puede no haber
 * completado nada —si se envía con Enter, el `blur` no siempre llega— así que
 * lo que se prueba es un POST con el valor crudo.
 */
const reglas = ultima(tinker(
  "$v = Validator::make(['enlace_web' => 'instagram.com/loquesea'], ['enlace_web' => App\\Support\\Enlace::reglas()]);"
  + " echo $v->fails() ? 'RECHAZA' : 'ACEPTA';"
));
di('`url:http,https` sobre el valor ya completado lo acepta',
  ultima(tinker(
    "$v = Validator::make(['e' => App\\Support\\Enlace::normalizar('instagram.com/loquesea')],"
    + " ['e' => App\\Support\\Enlace::reglas()]); echo $v->fails() ? 'RECHAZA' : 'ACEPTA';"
  )) === 'ACEPTA');

di('Y sobre el crudo, sin completar, rebotaría — por eso se completa antes',
  reglas === 'RECHAZA', reglas);

di('`instagram.com/loquesea` se completa con https://',
  ultima(tinker("echo App\\Support\\Enlace::normalizar('instagram.com/loquesea');")) === 'https://instagram.com/loquesea');
di('Lo que ya trae protocolo no se toca',
  ultima(tinker("echo App\\Support\\Enlace::normalizar('http://viejo.cl');")) === 'http://viejo.cl');
di('**Y un `javascript:` no pasa: acabaría en un href público**',
  ultima(tinker(
    "$v = Validator::make(['e' => App\\Support\\Enlace::normalizar('javascript:alert(1)')],"
    + " ['e' => App\\Support\\Enlace::reglas()]); echo $v->fails() ? 'RECHAZA' : 'ACEPTA';"
  )) === 'RECHAZA');

t('Q3 — la ficha de organización del panel');

await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
const orgId = ultima(tinker("echo App\\Models\\Organization::value('id');"));
await p.goto(`${B}/admin/organizaciones/${orgId}/editar`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await esperar(300);

const tipoPanel = await p.$eval('input[name="enlace_web"]', (e) => e.type);
di('Tampoco es type="url" en el panel', tipoPanel === 'text', `type="${tipoPanel}"`);
di('Y lleva el completado automático',
  await p.$eval('input[name="enlace_web"]', (e) => e.hasAttribute('data-autoprotocolo')));

await p.evaluate(() => {
  const c = document.querySelector('input[name="enlace_web"]');
  c.value = 'instagram.com/tuorganizacion';
  c.dispatchEvent(new Event('input', { bubbles: true }));
  c.dispatchEvent(new Event('blur', { bubbles: true }));
});
await esperar(300);

const avisoPanel = await p.evaluate(() => {
  const campo = document.querySelector('input[name="enlace_web"]').closest('.campo');

  return [...campo.querySelectorAll('.field-error')]
    .filter((e) => e.getBoundingClientRect().height > 0)
    .map((e) => e.innerText.trim())
    .join(' | ');
});
di('**Escribirlo sin protocolo no pinta ningún error**', avisoPanel === '', avisoPanel || '(ninguno)');

t('Q3 — el enlace del kit en Configuración');

di('Es un ajuste de enlace y se valida como tal',
  ultima(tinker(
    "$v = Validator::make(['kit_difusion_url' => App\\Support\\Enlace::normalizar('drive.google.com/drive/folders/abc')],"
    + " ['kit_difusion_url' => App\\Support\\Enlace::reglas()]); echo $v->fails() ? 'RECHAZA' : 'ACEPTA';"
  )) === 'ACEPTA');

/* ═══════════════ Q4 — el kit en la barra de mi-cuenta ════════════════ */

t('Q4 — el botón del kit, en todas las pantallas de mi-cuenta');

const KIT = 'https://drive.google.com/drive/folders/kit-de-prueba';
const kitOriginal = ultima(tinker("echo App\\Models\\Setting::get('kit_difusion_url') ?: '';"));

tinker(`App\\Models\\Setting::set('kit_difusion_url', '${KIT}'); echo 'PUESTO';`);

await salir();
await entrar('/mi-cuenta/login', ORG, CLAVE_ORG);

const idActividad = ultima(tinker(
  "$u = App\\Models\\User::where('email','" + ORG + "')->first();"
  + " echo $u?->organization?->activities()->value('id') ?: '';"
));

const PANTALLAS = [
  ['Mis actividades', '/mi-cuenta/actividades'],
  ['Evaluaciones', '/mi-cuenta/evaluaciones'],
  ['Mi perfil', '/mi-cuenta/perfil'],
  ['Editar actividad', `/mi-cuenta/actividades/${idActividad}/editar`],
  ['Participantes', `/mi-cuenta/actividades/${idActividad}/participantes`],
  ['Cambios guardados', `/mi-cuenta/actividades/${idActividad}/guardado`],
];

const kitEnLaBarra = () => p.evaluate((url) => {
  const a = [...document.querySelectorAll('a')]
    .find((x) => x.getAttribute('href') === url && x.innerText.trim() === 'Kit de difusión');

  return a ? { visible: a.getBoundingClientRect().height > 0, destino: a.getAttribute('target') } : null;
}, KIT);

for (const [nombre, ruta] of PANTALLAS) {
  await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });

  const enlace = await kitEnLaBarra();
  const barra = await p.evaluate(() => document.body.innerText.includes('Cerrar sesión'));

  di(`${nombre}: el kit está en la barra`, enlace?.visible === true, p.url().replace(B, ''));
  di(`${nombre}: con el resto de la barra`, barra === true);
}

di('Y abre en otra pestaña', (await kitEnLaBarra())?.destino === '_blank');

t('Q4 — con el enlace vacío no se pinta');

tinker("App\\Models\\Setting::set('kit_difusion_url', ''); echo 'VACIO';");
await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });

di('**Sin enlace, no hay botón**', ! (await texto()).includes('Kit de difusión'));
di('Pero la barra sigue ahí', (await texto()).includes('Cerrar sesión'));

tinker(`App\\Models\\Setting::set('kit_difusion_url', '${kitOriginal}'); echo 'DEVUELTO';`);

if (organizacionSembrada) {
  tinker(
    "App\\Models\\Organization::where('id', " + organizacionSembrada + ")->update(['logo_path' => null]);"
    + " @unlink(public_path('" + SEMBRADO + "')); echo 'LIMPIO';"
  );
}

t('Errores de JavaScript');
di('Ninguno', errores.length === 0, errores.join(' | '));

console.log('');
console.log(`RESULTADO: ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
