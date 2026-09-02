// Los dos accesos: que quien se equivoca de puerta llegue a la buena.
//
// El sitio tiene `/admin/login` y `/mi-cuenta/login`, y cada uno rechaza a la
// cuenta del otro. El rechazo era correcto y estaba explicado, pero se quedaba
// en texto: decía la dirección y no llevaba. El 2026-09-01 dejó a una persona
// del cliente parada delante del mensaje.
//
// Se comprueba en Chrome porque lo que hay que ver es si el botón existe, a
// dónde va, y si al llegar el campo viene relleno y el foco está donde tiene
// que estar. Por HTTP se ve el HTML, no dónde acaba una persona.
//
//   node pruebas/login-puertas.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', '--default-character-set=utf8mb4', 'ong_laravel', '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();

const ADMIN = { correo: 'admin@ong-laravel.test', clave: 'admin1234' };
const ORG = { correo: 'organizador@ong-laravel.test', clave: 'organizador1234' };

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(58)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1280, height: 900 });

/** El aviso de puerta equivocada que haya en pantalla, si lo hay. */
const aviso = () => p.evaluate(() => {
    const el = document.querySelector('.puerta-equivocada');
    if (! el) return null;
    const a = el.querySelector('a.btn');
    return { texto: el.innerText.replace(/\s+/g, ' ').trim(), url: a?.href ?? null, boton: a?.textContent.trim() ?? null };
});

const entrar = async (url, correo, clave) => {
    await p.goto(url, { waitUntil: 'networkidle2' });
    await p.evaluate(() => {
        document.querySelector('input[name="email"]').value = '';
        document.querySelector('input[name="password"]').value = '';
    });
    await p.type('input[name="email"]', correo);
    await p.type('input[name="password"]', clave);
    await Promise.all([
        p.waitForNavigation({ waitUntil: 'networkidle2' }),
        p.click('button[type="submit"]'),
    ]);
};

const enfocado = () => p.evaluate(() => document.activeElement?.getAttribute('name') ?? null);
const valorCorreo = () => p.evaluate(() => document.querySelector('input[name="email"]')?.value ?? null);

/* ══════════════════════════════════════════════════════════════════ */
t('Organizador que entra por la puerta de administración');

await entrar(`${B}/admin/login`, ORG.correo, ORG.clave);

di('no le deja entrar', p.url().includes('/admin/login'));

const av1 = await aviso();
di('sale el aviso con botón', av1 !== null && av1.url !== null, JSON.stringify(av1?.boton));
di('el botón lleva al acceso de organizaciones', (av1?.url ?? '').includes('/mi-cuenta/login'), av1?.url);
di('el aviso explica de qué tipo es la cuenta', /organización/i.test(av1?.texto ?? ''));
di('el mensaje del campo ya no lleva una URL a copiar a mano',
    ! /https?:\/\//.test(await p.$eval('.field-error', (e) => e.textContent).catch(() => '')));

/* ── El botón, que es lo que faltaba ── */
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('.puerta-equivocada a.btn')]);

di('el botón lleva de verdad al otro acceso', p.url().includes('/mi-cuenta/login'));
di('y el correo llega puesto', await valorCorreo() === ORG.correo, await valorCorreo());
di('el foco cae en la contraseña, que es lo que falta', await enfocado() === 'password');
di('el correo NO viaja en la URL', ! p.url().includes('@') && ! /correo|email/.test(p.url()), p.url());

/* ── Y desde ahí se entra ── */
await p.type('input[name="password"]', ORG.clave);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
di('con la contraseña entra a su cuenta', p.url().includes('/mi-cuenta'), p.url());

/* ── El correo se gasta al usarse ── */
const salir = async () => {
    // El logout es POST: hay que enviar su formulario, no visitar la ruta.
    const salio = await p.evaluate(() => {
        const f = document.querySelector('form[action*="logout"]');
        if (! f) return false;
        f.submit();
        return true;
    });
    if (salio) await p.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
    return salio;
};

di('hay por dónde cerrar sesión', await salir());

await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
di('al volver más tarde el campo ya no viene relleno', (await valorCorreo()) === '', JSON.stringify(await valorCorreo()));

/* ══════════════════════════════════════════════════════════════════ */
t('Y al revés: administrador por la puerta de organizaciones');

await entrar(`${B}/mi-cuenta/login`, ADMIN.correo, ADMIN.clave);

di('no le deja entrar', p.url().includes('/mi-cuenta/login'));

const av2 = await aviso();
di('sale el aviso con botón', av2 !== null && av2.url !== null, JSON.stringify(av2?.boton));
di('el botón lleva al panel', (av2?.url ?? '').includes('/admin/login'), av2?.url);

await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('.puerta-equivocada a.btn')]);
di('el botón lleva de verdad al panel', p.url().includes('/admin/login'));
di('y el correo llega puesto', await valorCorreo() === ADMIN.correo, await valorCorreo());
di('el foco cae en la contraseña', await enfocado() === 'password');

await p.type('input[name="password"]', ADMIN.clave);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
di('con la contraseña entra al panel', p.url().includes('/admin') && ! p.url().includes('/login'), p.url());

await salir();

/* ══════════════════════════════════════════════════════════════════ */
t('La pista no se regala: sólo con la contraseña correcta');

/*
 * Esto es lo que hay que no romper. Si el aviso saliera con la contraseña
 * equivocada, probar correos en `/admin/login` diría cuáles son de
 * administración, que es justo lo que la separación de accesos evita.
 */
await entrar(`${B}/admin/login`, ORG.correo, 'contrasena-que-no-es');
di('con la contraseña equivocada NO sale el aviso', await aviso() === null);

await entrar(`${B}/admin/login`, 'no-existe-nadie@ong-laravel.test', 'loquesea');
di('con un correo que no existe tampoco', await aviso() === null);

await entrar(`${B}/mi-cuenta/login`, ADMIN.correo, 'contrasena-que-no-es');
di('y lo mismo en el otro acceso', await aviso() === null);

/* ══════════════════════════════════════════════════════════════════ */
t('Equivocarse de puerta no deja a nadie fuera');

/*
 * Lo enseñó una captura de estas mismas pruebas: el aviso salía con
 * «Demasiados intentos fallidos» encima. Un `rol` contaba para el bloqueo, y el
 * bloqueo salta ANTES de comprobar nada, así que a la sexta vez la persona que
 * se equivoca de acceso dejaba de ver el botón que la lleva a su sitio — que es
 * exactamente a quien esto viene a ayudar.
 *
 * No quita protección: un `rol` sólo se registra cuando la contraseña YA era
 * correcta. Quien la sabe no está adivinando nada, y entraría por la puerta
 * buena.
 */
sql(`DELETE FROM access_logs WHERE email='${ORG.correo}'`);

for (let i = 0; i < 7; i++) {
    await entrar(`${B}/admin/login`, ORG.correo, ORG.clave);
}

const trasSiete = await aviso();
di('a la séptima vez sigue saliendo el botón', trasSiete !== null && trasSiete.url !== null);
di('y no dice «demasiados intentos»',
    ! /demasiados intentos/i.test(await p.evaluate(() => document.body.innerText)));
di('pero los intentos quedan registrados',
    Number(sql(`SELECT COUNT(*) FROM access_logs WHERE email='${ORG.correo}' AND resultado='rol'`)) >= 7);

// Y la contraseña equivocada sí sigue bloqueando, que es para lo que está.
sql(`DELETE FROM access_logs WHERE email='${ORG.correo}'`);

for (let i = 0; i < 6; i++) {
    await entrar(`${B}/mi-cuenta/login`, ORG.correo, 'contrasena-que-no-es');
}

di('la contraseña equivocada SÍ sigue bloqueando',
    /demasiados intentos/i.test(await p.evaluate(() => document.body.innerText)));

sql(`DELETE FROM access_logs WHERE email='${ORG.correo}'`);

/* ══════════════════════════════════════════════════════════════════ */
t('Cambiar el rol con la sesión abierta');

/*
 * La pregunta de Jonas: si a un organizador le suben a administrador desde el
 * panel, ¿entra ya, o tiene que cerrar sesión?
 *
 * Se prueba de verdad: se entra como organizador, se le cambia el rol en la
 * base sin tocar la sesión, y se mira si el panel le deja pasar. Al final se
 * deja como estaba pase lo que pase.
 */
const rolOriginal = sql(`SELECT role FROM users WHERE email='${ORG.correo}'`);

try {
    await entrar(`${B}/mi-cuenta/login`, ORG.correo, ORG.clave);
    di('entra como organizador', p.url().includes('/mi-cuenta'));

    await p.goto(`${B}/admin`, { waitUntil: 'networkidle2' });
    const antes = await p.evaluate(() => document.body.innerText.slice(0, 120));
    di('con su rol, el panel le rechaza', /no tienes acceso/i.test(antes) || p.url().includes('/admin/login'),
        p.url());

    // Le suben a administrador, sin tocarle la sesión.
    sql(`UPDATE users SET role='admin' WHERE email='${ORG.correo}'`);

    await p.goto(`${B}/admin`, { waitUntil: 'networkidle2' });
    const entraYa = ! p.url().includes('/login')
        && ! /no tienes acceso/i.test(await p.evaluate(() => document.body.innerText.slice(0, 200)));
    di('tras el cambio de rol entra SIN cerrar sesión', entraYa, p.url());

    // Y al revés: se le devuelve el rol de organizador estando dentro del panel.
    sql(`UPDATE users SET role='organizer' WHERE email='${ORG.correo}'`);
    await p.goto(`${B}/admin`, { waitUntil: 'networkidle2' });
    const textoTras = await p.evaluate(() => document.body.innerText.slice(0, 400));
    di('y al bajarle el rol, el panel deja de dejarle pasar',
        /no tienes acceso/i.test(textoTras) || p.url().includes('/login'));
    di('el rechazo es un 403 con su explicación, no una pantalla en blanco',
        /no tienes acceso a esta sección/i.test(textoTras), textoTras.replace(/\s+/g, ' ').slice(0, 80));
    di('y ofrece por dónde salir',
        await p.evaluate(() => [...document.querySelectorAll('a.btn')].length >= 1));
} finally {
    sql(`UPDATE users SET role='${rolOriginal}' WHERE email='${ORG.correo}'`);
    console.log(`  (rol de ${ORG.correo} devuelto a '${rolOriginal}')`);
}

/* ══════════════════════════════════════════════════════════════════ */
t('Sin errores en la consola');

di('ningún error de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

console.log('');
console.log(`${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal === 0 ? 0 : 1);
