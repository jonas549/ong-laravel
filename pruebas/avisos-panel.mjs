// Punto 9 del 23/09 — el correo que recibe los avisos se cambia desde el panel.
//
// Configuración → General tiene que enseñar el ajuste, guardar un correo
// nuevo, rechazar lo que no es un correo, y dejarlo como estaba al terminar.
// A quién llega de verdad el aviso lo comprueba `avisos-destinatario.php`.
//
//   node pruebas/avisos-panel.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '--default-character-set=utf8mb4', '-e', q]).toString().trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };

const valor = () => sql(`select ifnull(valor, '') from settings where clave = 'avisos_email'`);
const antes = valor();

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 900 });

const guardar = async (correo) => {
    await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
    await p.$eval('[name="avisos_email"]', (i, v) => { i.value = v; }, correo);
    await Promise.all([p.waitForNavigation(), p.$eval('[name="avisos_email"]', (i) => i.form.submit())]);
};

try {
    await p.goto(`${B}/admin/login`);
    await p.type('[name="email"]', ADMIN);
    await p.type('[name="password"]', CLAVE_ADMIN);
    await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

    await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
    const campo = await p.evaluate(() => {
        const i = document.querySelector('[name="avisos_email"]');
        return i ? { valor: i.value, etiqueta: i.closest('div, label')?.innerText ?? '', alto: i.getBoundingClientRect().height } : null;
    });
    console.log('');
    di('el ajuste está en Configuración → General', (campo?.alto ?? 0) > 0);
    di('dice para qué es', /avisos de actividades/i.test(campo?.etiqueta ?? ''), (campo?.etiqueta ?? '').split('\n')[0]);
    di('con el buzón del equipo', campo?.valor === antes, campo?.valor);

    await guardar('equipo-prueba@ejemplo.cl');
    di('guarda un correo nuevo', valor() === 'equipo-prueba@ejemplo.cl', valor());

    await guardar('esto no es un correo');
    di('rechaza lo que no es un correo', valor() === 'equipo-prueba@ejemplo.cl', valor());
    di('y lo dice junto al campo', await p.evaluate(() => !! document.querySelector('[name="avisos_email"]')?.closest('div')?.querySelector('.field-error')));
} finally {
    sql(`update settings set valor = '${antes.replace(/'/g, "''")}' where clave = 'avisos_email'`);
    execFileSync(process.env.DPS_PHP ?? 'php', ['artisan', 'tinker', '--execute', "Illuminate\\Support\\Facades\\Cache::forget(App\\Models\\Setting::CACHE_KEY);"]);
    await nav.close();
}

di('queda como estaba', valor() === antes, valor());
console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
