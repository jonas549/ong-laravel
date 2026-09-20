// C6 — qué cuentas hay en producción. SÓLO MIRA: no crea, no cambia, no borra.
//
//   DPS_URL=https://ong.sandboxdelta.com node pruebas/usuarios-produccion.mjs
import puppeteer from 'puppeteer-core';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1200 });

await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', ADMIN);
await p.type('input[name="password"]', CLAVE_ADMIN);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
console.log('entrado en:', p.url());

await p.goto(`${B}/admin/usuarios?por_pagina=100`, { waitUntil: 'networkidle2' });

const filas = await p.$$eval('table tbody tr', (n) => n.map((f) => [...f.querySelectorAll('td')]
  .map((c) => c.innerText.replace(/\s+/g, ' ').trim()).join(' · ')));

console.log('');
console.log(`${filas.length} cuentas:`);
filas.forEach((f) => console.log('  ' + f));

await nav.close();
