// El mensaje al compartir, editable desde Configuración → General.
//
// Se guarda desde el panel y se mira lo que propone el botón de WhatsApp de
// una ficha: con marcadores, sin `{enlace}` (se añade al final) y vacío (el de
// siempre). Deja el ajuste como estaba.
//
//   node pruebas/mensaje-compartir.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '--default-character-set=utf8mb4', '-e', q]).toString().trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };

const POR_DEFECTO = 'Súmate a esta actividad de celebración del Día del Patrimonio Social: ';
const antes = sql(`select ifnull(valor, '') from settings where clave = 'compartir_mensaje'`);
const [id, titulo] = sql(`select id, titulo from activities where estado = 'publicada' and published_at is not null and deleted_at is null order by id limit 1`).split('\t');

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const ficha = await nav.newPage();
await p.bringToFront();

const guardar = async (texto) => {
    await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
    await p.$eval('[name="compartir_mensaje"]', (i, v) => { i.value = v; }, texto);
    await Promise.all([p.waitForNavigation(), p.$eval('[name="compartir_mensaje"]', (i) => i.form.submit())]);
};
const mensaje = async () => {
    await ficha.goto(`${B}/activity/${id}/x`, { waitUntil: 'networkidle2' });
    const href = await ficha.evaluate(() => [...document.querySelectorAll('a')].find((a) => a.href.startsWith('https://wa.me/'))?.href ?? '');
    await p.bringToFront();
    return new URL(href || 'https://x').searchParams.get('text') ?? '';
};

try {
    await p.goto(`${B}/admin/login`);
    await p.type('[name="email"]', ADMIN);
    await p.type('[name="password"]', CLAVE_ADMIN);
    await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

    console.log('');
    await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });
    const campo = await p.evaluate(() => {
        const i = document.querySelector('[name="compartir_mensaje"]');
        return i ? { alto: i.getBoundingClientRect().height, ayuda: i.closest('div').innerText } : null;
    });
    di('el campo está en Configuración → General', (campo?.alto ?? 0) > 0);
    di('explica {nombre} y {enlace}', /\{nombre\}/.test(campo?.ayuda) && /\{enlace\}/.test(campo?.ayuda));

    await guardar('');
    const vacio = await mensaje();
    di('vacío, sale el de siempre', vacio.startsWith(POR_DEFECTO) && vacio.includes(titulo) && vacio.includes(`/activity/${id}/`), vacio);

    await guardar('Ven a {nombre} → {enlace} ¡Te esperamos!');
    const propio = await mensaje();
    di('con marcadores, los cambia por los de la actividad', propio.startsWith(`Ven a ${titulo} → `) && propio.endsWith('¡Te esperamos!') && propio.includes(`/activity/${id}/`), propio);
    di('y no quedan marcadores sin cambiar', ! /\{(nombre|enlace)\}/.test(propio));

    await guardar('Mira esta actividad: {nombre}');
    const sinEnlace = await mensaje();
    di('sin {enlace}, el enlace se añade al final', sinEnlace.startsWith(`Mira esta actividad: ${titulo} `) && /\/activity\/\d+\//.test(sinEnlace), sinEnlace);
} finally {
    sql(`update settings set valor = '${antes.replace(/'/g, "''")}' where clave = 'compartir_mensaje'`);
    execFileSync(process.env.DPS_PHP ?? 'php', ['artisan', 'tinker', '--execute', "Illuminate\\Support\\Facades\\Cache::forget(App\\Models\\Setting::CACHE_KEY);"]);
    await nav.close();
}

di('queda como estaba', sql(`select ifnull(valor, '') from settings where clave = 'compartir_mensaje'`) === antes);
console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
