// El home tras la tanda del 2026-09-04: la tercera tarjeta y los tres tamaños
// de logo.
//
//   - «Quiero ser voluntario» encendida y administrable de punta a punta,
//     enlace incluido;
//   - los auspiciadores grandes, los participantes medianos y los
//     colaboradores pequeños, y que eso se cambie desde el CRUD sin tocar
//     código, que es lo que pidió el cliente;
//   - la imagen nueva de «¿Qué es el Patrimonio Social?».
//
// Necesita Chrome: lo que hay que comprobar de los tamaños son píxeles, y del
// enlace de la tarjeta, que un cambio hecho en el panel llegue al sitio.
//
//   php artisan serve --host=127.0.0.1 --port=8123
//   node pruebas/home-tarjetas-y-logos.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = 'http://127.0.0.1:8123';
const S = process.env.DPS_SALIDA ?? tmpdir();
const MYSQL = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(56)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => console.log(`\n=== ${x} ===`);
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const ID = sql("SELECT id FROM participation_cards WHERE titulo='Quiero ser voluntario'");
const guardado = { href: sql(`SELECT href FROM participation_cards WHERE id=${ID}`), activo: sql(`SELECT activo FROM participation_cards WHERE id=${ID}`) };
const devolver = () => sql(`UPDATE participation_cards SET href='${guardado.href}', activo=${guardado.activo} WHERE id=${ID}`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
p.on('pageerror', (e) => errores.push(String(e)));

const ir = async (url) => { await p.goto(url, { waitUntil: 'networkidle2' }); await esperar(260); };

/** Todo lo diferido carga: si no, medir un logo devuelve cero. */
const cargarTodo = async () => {
  await p.evaluate(() => document.querySelectorAll('img[loading=lazy]').forEach((i) => { i.loading = 'eager'; i.src = i.src; }));
  await p.evaluate(() => Promise.all([...document.images].map((i) => (i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; })))));
  await esperar(300);
};

try {
    await p.setViewport({ width: 1440, height: 1000 });

    t('La tercera tarjeta del home');
    await ir(`${B}/`);
    const tarjetas = await p.$$eval('.part-card', (n) => n.map((c) => ({
      titulo: c.querySelector('h3')?.textContent.trim(),
      href: c.getAttribute('href'),
      cta: c.querySelector('.pc-ctatext')?.textContent.trim(),
      icono: !!c.querySelector('.pc-icon'),
      arte: !!c.querySelector('.pc-art'),
      alto: Math.round(c.getBoundingClientRect().height),
      x: Math.round(c.getBoundingClientRect().x),
    })));
    di('salen las tres', tarjetas.length === 3, JSON.stringify(tarjetas.map((x) => x.titulo)));
    di('«Quiero ser voluntario» va la primera', tarjetas[0]?.titulo === 'Quiero ser voluntario');
    di('con su ilustración y su arte de fondo', tarjetas[0]?.icono && tarjetas[0]?.arte);
    di('su enlace ya no es el ancla muerta del fuente', tarjetas[0]?.href !== '#voluntario', tarjetas[0]?.href);
    di('y ese destino existe', await p.evaluate(async (h) => (await fetch(h)).ok, tarjetas[0].href), tarjetas[0]?.href);
    di('las tres miden lo mismo de alto', new Set(tarjetas.map((x) => x.alto)).size === 1, `${tarjetas.map((x) => x.alto).join('/')} px`);
    di('van en una sola fila', new Set(tarjetas.map((x) => x.x)).size === 3);

    t('Y se administra entera desde el panel');
    await ir(`${B}/admin/login`);
    await p.type('input[name=email]', 'admin@ong-laravel.test');
    await p.type('input[name=password]', 'admin1234');
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type=submit]')]);
    di('se entra al panel', p.url().includes('/admin'), p.url());

    await ir(`${B}/admin/contenido/tarjetas`);
    const fila = await p.$$eval('tbody tr', (n) => n.find((f) => f.innerText.includes('Quiero ser voluntario'))?.innerText.replace(/\s+/g, ' ').trim());
    // El listado no pinta un «Sí»: la acción que ofrece es la contraria al estado.
    di('sale en el listado y como visible', fila?.includes('Esconder') && !fila.includes('Mostrar'), fila?.slice(0, 60));

    await ir(`${B}/admin/contenido/tarjetas/${ID}/editar`);
    const campos = await p.$$eval('form [name]', (n) => n.map((e) => e.getAttribute('name')));
    di('el formulario trae el campo Enlace', campos.includes('href'), '');
    di('y título, botón, color y visible', ['titulo', 'cta', 'color', 'activo'].every((c) => campos.includes(c)));

    // OJO: en esta pantalla hay varios <form> y el primero es el buscador.
    // Enviar `document.querySelector('form')` no guarda nada.
    await p.$eval('[name=href]', (e) => { e.value = ''; });
    await p.type('[name=href]', 'https://voluntariadoschile.cl');
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.$eval('[name=titulo]', (e) => e.form.requestSubmit())]);
    di('guarda y vuelve al listado', p.url().includes('/admin/contenido/tarjetas'));
    di('la base trae el enlace nuevo', sql(`SELECT href FROM participation_cards WHERE id=${ID}`) === 'https://voluntariadoschile.cl');
    await ir(`${B}/`);
    di('y el home ya lleva ahí', (await p.$eval('.part-card', (c) => c.getAttribute('href'))) === 'https://voluntariadoschile.cl');

    await ir(`${B}/admin/contenido/tarjetas`);
    const alternar = () => Promise.all([
      p.waitForNavigation({ waitUntil: 'networkidle2' }),
      p.$$eval('tbody tr', (n) => n.find((f) => f.innerText.includes('Quiero ser voluntario')).querySelector('button, a[href*=estado]').click()),
    ]);
    await alternar();
    di('se apaga desde el listado', sql(`SELECT activo FROM participation_cards WHERE id=${ID}`) === '0');
    await ir(`${B}/`);
    di('y desaparece del home', (await p.$$('.part-card')).length === 2);
    await ir(`${B}/admin/contenido/tarjetas`);
    await alternar();
    di('y vuelve a encenderse', sql(`SELECT activo FROM participation_cards WHERE id=${ID}`) === '1');

    t('Los tres tamaños de logo');
    await ir(`${B}/`);
    await cargarTodo();
    const filas = await p.$$eval('.logos-fila', (n) => n.map((f) => ({
      clase: f.className.replace('logos-fila ', ''),
      hueco: getComputedStyle(f).gap,
      chips: [...f.querySelectorAll('.logo-chip')].map((c) => {
        const i = c.querySelector('img');
        return {
          clase: c.className.replace('logo-chip ', ''),
          caja: Math.round(c.getBoundingClientRect().height),
          logo: i ? Math.round(i.getBoundingClientRect().height) : null,
          alt: i?.alt,
        };
      }),
    })));
    di('hay tres filas de logos', filas.length === 3, JSON.stringify(filas.map((f) => f.clase)));
    di('auspician va grande', filas[0].clase === 'logos-fila--grande' && filas[0].chips.every((c) => c.caja === 124), `${filas[0].chips.map((c) => c.caja)}`);
    di('participan va mediano', filas[1].clase === 'logos-fila--mediano' && filas[1].chips.every((c) => c.caja === 100), `${filas[1].chips.map((c) => c.caja)}`);
    di('colaboran va pequeño', filas[2].clase === 'logos-fila--chico' && filas[2].chips.every((c) => c.caja === 76), `${filas[2].chips.map((c) => c.caja)}`);
    di('los tres tamaños son distintos y en orden', filas[0].chips[0].caja > filas[1].chips[0].caja && filas[1].chips[0].caja > filas[2].chips[0].caja);
    di('Reale y Anglo crecieron respecto a pequeño', filas[1].chips.every((c) => c.logo > filas[2].chips[0].logo), `${filas[1].chips.map((c) => c.logo)} vs ${filas[2].chips.map((c) => c.logo)}`);
    di('el hueco de la fila acompaña al tamaño', parseFloat(filas[0].hueco) > parseFloat(filas[2].hueco), `${filas[0].hueco} / ${filas[2].hueco}`);

    t('Y el tamaño se cambia desde el CRUD, sin tocar código');
    const PID = sql("SELECT id FROM partners WHERE nombre='Reale Seguros'");
    await ir(`${B}/admin/contenido/partners/${PID}/editar`);
    const sel = await p.$eval('[name=tamano]', (e) => ({ opciones: [...e.options].map((o) => o.value), elegido: e.value })).catch(() => null);
    di('hay un select de tamaño en el formulario', sel !== null, JSON.stringify(sel));
    di('con los tres tamaños', ['grande', 'mediano', 'chico'].every((x) => sel?.opciones.includes(x)), JSON.stringify(sel?.opciones));
    di('y Reale sale en mediano', sel?.elegido === 'mediano', sel?.elegido);

    await p.select('[name=tamano]', 'grande');
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.$eval('[name=nombre]', (e) => e.form.requestSubmit())]);
    await ir(`${B}/`);
    await cargarTodo();
    const tras = await p.$$eval('.logos-fila', (n) => n.map((f) => ({ clase: f.className, altos: [...f.querySelectorAll('.logo-chip')].map((c) => Math.round(c.getBoundingClientRect().height)) })));
    di('el cambio se ve en el home al momento', tras[1].altos.includes(124), JSON.stringify(tras[1].altos));
    di('y un grupo puede mezclar tamaños', new Set(tras[1].altos).size === 2, JSON.stringify(tras[1].altos));
    sql(`UPDATE partners SET tamano='mediano' WHERE id=${PID}`);

    t('La imagen de «¿Qué es el Patrimonio Social?»');
    await ir(`${B}/`);
    await cargarTodo();
    const img = await p.$eval('#que-es img', (e) => ({
      archivo: e.currentSrc.split('/').pop(),
      natural: `${e.naturalWidth}x${e.naturalHeight}`,
      alt: e.alt,
      ajuste: getComputedStyle(e).objectFit,
      proporcionDelMarco: (r => (r.width / r.height).toFixed(2))(e.parentElement.getBoundingClientRect()),
    }));
    di('es la nueva', img.archivo === 'manos-patrimonio-social.jpg', img.archivo);
    di('llega recortada a 16:11, la del marco', img.natural === '1376x946' && img.proporcionDelMarco === '1.45', `${img.natural} · marco ${img.proporcionDelMarco}`);
    di('conserva su texto alternativo', img.alt.length > 10, img.alt);
    di('y no se deforma', img.ajuste === 'cover');

    t('Nada se desborda');
    for (const ancho of [1440, 1020, 760, 390]) {
      await p.setViewport({ width: ancho, height: 1000 });
      await ir(`${B}/`);
      const d = await p.evaluate(() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth }));
      di(`sin desborde horizontal a ${ancho}`, d.doc <= d.win, JSON.stringify(d));
    }
    await p.screenshot({ path: `${S}/home-390.png`, fullPage: true });
} finally {
    devolver();
}

console.log(`\n${ok} OK · ${mal} mal · consola: ${errores.length ? errores.join(' | ') : 'limpia'}`);
await nav.close();
process.exit(mal ? 1 : 0);
