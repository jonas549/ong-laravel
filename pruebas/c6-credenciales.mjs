// C6 — las credenciales de producción.
//
// Lo que hace, por este orden:
//
//   1. Crea la cuenta de administración pedida.
//   2. Entra con ella, para no depender de la vieja en los pasos siguientes.
//   3. Le pone contraseña nueva a las dos cuentas sembradas, cuyas claves
//      están escritas en cuarenta archivos de este repositorio.
//   4. Comprueba que la nueva entra.
//   5. Y las DESACTIVA. No las borra: borrar se llevaría por delante lo que
//      cuelga de ellas, y nadie ha dado el visto bueno a eso.
//
// **Las contraseñas llegan por el entorno y no se escriben aquí.** Escribirlas
// en un archivo del repositorio es exactamente el problema que esto viene a
// cerrar.
//
//   DPS_URL=https://ong.sandboxdelta.com \
//   DPS_CLAVE_ADMIN=... DPS_CLAVE_NUEVA_ADMIN=... \
//   DPS_CLAVE_NUEVA_ORG=... DPS_CORREO_NUEVO=... DPS_CLAVE_NUEVA=... \
//   node pruebas/c6-credenciales.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

const ADMIN = 'admin@ong-laravel.test';
const ORG = 'organizador@ong-laravel.test';
const CLAVE_ADMIN = process.env.DPS_CLAVE_ADMIN ?? '';

const NUEVO = process.env.DPS_CORREO_NUEVO ?? '';
const NUEVO_NOMBRE = process.env.DPS_NOMBRE_NUEVO ?? 'Jonas — Delta Digital';
const CLAVE_NUEVA = process.env.DPS_CLAVE_NUEVA ?? '';
const CLAVE_NUEVA_ADMIN = process.env.DPS_CLAVE_NUEVA_ADMIN ?? '';
const CLAVE_NUEVA_ORG = process.env.DPS_CLAVE_NUEVA_ORG ?? '';

for (const [k, v] of Object.entries({ DPS_CLAVE_ADMIN: CLAVE_ADMIN, DPS_CORREO_NUEVO: NUEVO, DPS_CLAVE_NUEVA: CLAVE_NUEVA, DPS_CLAVE_NUEVA_ADMIN: CLAVE_NUEVA_ADMIN, DPS_CLAVE_NUEVA_ORG: CLAVE_NUEVA_ORG })) {
  if (! v) { console.error(`Falta ${k} en el entorno.`); process.exit(2); }
}

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(60)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1200 });

const texto = () => p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));

const entrar = async (puerta, correo, clave) => {
  await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
  await p.type('input[name="email"]', correo);
  await p.type('input[name="password"]', clave);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

  return ! p.url().includes('/login');
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

/** La fila del listado de usuarios que lleva ese correo. */
const fila = async (correo) => {
  await p.goto(`${B}/admin/usuarios?por_pagina=100`, { waitUntil: 'networkidle2' });

  return p.evaluate((c) => {
    const f = [...document.querySelectorAll('table tbody tr')].find((x) => x.innerText.includes(c));
    if (! f) return null;

    const editar = [...f.querySelectorAll('a')].find((a) => a.innerText.trim() === 'Editar');

    return {
      texto: f.innerText.replace(/\s+/g, ' ').trim(),
      activo: /\bActivo\b/.test(f.innerText),
      editar: editar?.getAttribute('href') ?? null,
    };
  }, correo);
};

try {
  t('Entrar con la cuenta de siempre');
  di('La contraseña vieja del administrador todavía entra', await entrar('/admin/login', ADMIN, CLAVE_ADMIN), p.url());

  t(`Crear ${NUEVO} como administración`);

  const yaEsta = await fila(NUEVO);

  if (yaEsta) {
    di('Ya existía, no se crea otra vez', true, yaEsta.texto);
  } else {
    await p.goto(`${B}/admin/usuarios`, { waitUntil: 'networkidle2' });
    await p.type('#u-name', NUEVO_NOMBRE);
    await p.type('#u-email', NUEVO);
    await p.type('#u-password', CLAVE_NUEVA);
    await p.select('#u-role', 'admin');
    await Promise.all([
      p.waitForNavigation({ waitUntil: 'networkidle2' }),
      p.evaluate(() => document.querySelector('#u-password').closest('form').submit()),
    ]);
    di('El panel dice que se creó', (await texto()).includes('Usuario creado'), '');
  }

  const nueva = await fila(NUEVO);
  di('Está en el listado, con rol de administración',
    nueva !== null && /Administración/.test(nueva.texto), nueva?.texto ?? '(no está)');

  t('Entrar con la cuenta nueva');

  await salir();
  di('La cuenta nueva entra al panel', await entrar('/admin/login', NUEVO, CLAVE_NUEVA), p.url());

  /*
   * A partir de aquí se trabaja con la cuenta nueva a propósito: los dos
   * pasos siguientes le cambian la contraseña a la vieja y la desactivan, y
   * hacerlo desde ella misma es quedarse sin la rama en la que se está
   * sentado.
   */
  t('Contraseña nueva para las dos cuentas sembradas');

  const cambiar = async (correo, clave) => {
    const f = await fila(correo);
    if (! f?.editar) return false;

    await p.goto(f.editar, { waitUntil: 'networkidle2' });
    await p.type('input[name="password"]', clave);
    await p.type('input[name="password_confirmation"]', clave);
    await Promise.all([
      p.waitForNavigation({ waitUntil: 'networkidle2' }),
      p.evaluate(() => document.querySelector('input[name="password_confirmation"]').closest('form').submit()),
    ]);

    /*
     * El aviso lleva el NOMBRE en medio —«Contraseña de Fulano
     * actualizada.»—, así que se busca por los dos extremos y no por la
     * frase entera.
     */
    const aviso = await texto();

    return /Contraseña de .+ actualizada/.test(aviso);
  };

  di(`Cambiada la de ${ADMIN}`, await cambiar(ADMIN, CLAVE_NUEVA_ADMIN), '');
  di(`Cambiada la de ${ORG}`, await cambiar(ORG, CLAVE_NUEVA_ORG), '');

  t('Y la nueva es la que entra');

  await salir();
  di('El administrador sembrado entra con la nueva', await entrar('/admin/login', ADMIN, CLAVE_NUEVA_ADMIN), p.url());
  await salir();
  di('El organizador sembrado entra con la nueva', await entrar('/mi-cuenta/login', ORG, CLAVE_NUEVA_ORG), p.url());

  t('Desactivar las dos, sin borrarlas');

  await salir();
  di('De vuelta con la cuenta nueva', await entrar('/admin/login', NUEVO, CLAVE_NUEVA), p.url());

  // Se hace desde el listado: ahí el botón es un submit de su propia fila.
  const desactivar = async (correo) => {
    const f = await fila(correo);
    if (! f) return false;
    if (! f.activo) return true;

    await Promise.all([
      p.waitForNavigation({ waitUntil: 'networkidle2' }),
      p.evaluate((c) => {
        const fila = [...document.querySelectorAll('table tbody tr')].find((x) => x.innerText.includes(c));
        [...fila.querySelectorAll('button')].find((b) => b.innerText.trim() === 'Desactivar')?.click();
      }, correo),
    ]);

    return (await fila(correo))?.activo === false;
  };

  di(`${ADMIN} queda desactivada`, await desactivar(ADMIN), '');
  di(`${ORG} queda desactivada`, await desactivar(ORG), '');

  // De una en una: las dos comparten la misma pestaña y `Promise.all`
  // dispara dos navegaciones a la vez, que se abortan entre sí.
  const quedan = [await fila(ADMIN), await fila(ORG)];
  di('**Pero siguen existiendo: no se ha borrado ninguna**',
    quedan.every((f) => f !== null), quedan.map((f) => f?.texto).join(' // '));
} finally {
  await nav.close();
}

console.log('');
console.log(`RESULTADO: ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
