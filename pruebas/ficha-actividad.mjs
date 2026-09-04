// La ficha pública de una actividad: quién organiza, sus enlaces y compartir.
//
// Los tres puntos que trajo Jonas de la reunión del 2026-09-01:
//   - el organizador bajo el título, con logo y con peso (antes iba apagado
//     en la ficha lateral);
//   - su sitio web y su red social, que se capturaban y no se pintaban;
//   - botones de compartir, y que al compartir salga la imagen correcta.
//
// Necesita Chrome, y por dos motivos que no son de comodidad: el portapapeles
// no existe sin navegador, y lo que hay que comprobar del bloque del
// organizador es dónde cae y cuánto pesa, no que el HTML contenga el nombre.
//
//   php artisan serve --host=127.0.0.1 --port=8123
//   node pruebas/ficha-actividad.mjs
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

const SLUG = sql("SELECT slug FROM activities WHERE estado='publicada' AND deleted_at IS NULL ORDER BY id LIMIT 1");
const OTRA = sql("SELECT slug FROM activities WHERE estado='publicada' AND deleted_at IS NULL ORDER BY id LIMIT 1 OFFSET 1");
const ORG = sql(`SELECT organization_id FROM activities WHERE slug='${SLUG}'`);

// Lo que había antes de tocar nada, para devolverlo al terminar.
const guardado = {
  logo: sql(`SELECT IFNULL(logo_path,'') FROM organizations WHERE id=${ORG}`),
  web: sql(`SELECT IFNULL(enlace_web,'') FROM organizations WHERE id=${ORG}`),
  red: sql(`SELECT IFNULL(enlace_red_social,'') FROM organizations WHERE id=${ORG}`),
};
const nulo = (v) => (v === '' ? 'NULL' : `'${v}'`);
const devolver = () => sql(
  `UPDATE organizations SET logo_path=${nulo(guardado.logo)}, enlace_web=${nulo(guardado.web)}, enlace_red_social=${nulo(guardado.red)} WHERE id=${ORG};`
  + ` UPDATE activities SET enlace_web=NULL, enlace_red_social=NULL WHERE slug IN ('${SLUG}','${OTRA}');`,
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
await nav.defaultBrowserContext().overridePermissions(B, ['clipboard-read', 'clipboard-write']);
const p = await nav.newPage();
const errores = [];
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1440, height: 1100 });

const ir = async (slug) => { await p.goto(`${B}/actividades/${slug}`, { waitUntil: 'networkidle2' }); await esperar(260); };
const enlaces = () => p.$$eval('.org-enlaces a', (n) => n.map((a) => ({ texto: a.textContent.trim(), href: a.href, rel: a.rel, destino: a.target }))).catch(() => []);

try {
    t('Quién organiza, bajo el título y con peso propio');
    sql(`UPDATE organizations SET logo_path=NULL, enlace_web='https://juntoalbarrio.cl', enlace_red_social='https://instagram.com/juntoalbarrio' WHERE id=${ORG}`);
    await ir(SLUG);
    const firma = await p.$eval('.org-firma', (e) => {
      const logo = e.querySelector('.org-logo');
      const nom = e.querySelector('.org-nombre');
      const h1 = document.querySelector('h1');
      return {
        tipo: logo.tagName === 'IMG' ? 'imagen' : 'iniciales',
        texto: logo.textContent.trim(),
        caja: `${Math.round(logo.getBoundingClientRect().width)}x${Math.round(logo.getBoundingClientRect().height)}`,
        nombre: nom.textContent.trim(),
        peso: getComputedStyle(nom).fontWeight,
        tam: parseFloat(getComputedStyle(nom).fontSize),
        bajoElTitulo: e.getBoundingClientRect().top >= h1.getBoundingClientRect().bottom - 2,
        sobreLaDescripcion: e.getBoundingClientRect().bottom <= [...document.querySelectorAll('p')].find((x) => x.textContent.length > 40).getBoundingClientRect().top + 2,
      };
    });
    di('el bloque cae justo debajo del título', firma.bajoElTitulo && firma.sobreLaDescripcion, JSON.stringify({ b: firma.bajoElTitulo, s: firma.sobreLaDescripcion }));
    di('el nombre va en negrita y grande', +firma.peso >= 700 && firma.tam >= 18, `${firma.peso} / ${firma.tam}px`);
    di('ya no está en la ficha lateral', !(await p.$$eval('aside .helper', (n) => n.map((e) => e.textContent.trim()))).includes('Organiza'));

    t('Y cuando la organización no tiene logo');
    di('salen sus iniciales, no un hueco', firma.tipo === 'iniciales' && /^[A-ZÁÉÍÓÚÑ]{1,2}$/.test(firma.texto), firma.texto);
    di('en una caja del mismo tamaño que el logo', firma.caja === '54x54', firma.caja);

    sql(`UPDATE organizations SET logo_path='img/logo-cos-color.png' WHERE id=${ORG}`);
    await ir(SLUG);
    const conLogo = await p.$eval('.org-logo', (e) => ({
      tag: e.tagName,
      caja: `${Math.round(e.getBoundingClientRect().width)}x${Math.round(e.getBoundingClientRect().height)}`,
      ajuste: getComputedStyle(e).objectFit,
      cargada: e.naturalWidth > 0,
    }));
    di('con logo se pinta el logo', conLogo.tag === 'IMG' && conLogo.cargada, JSON.stringify(conLogo));
    di('en la misma caja, sin deformarse', conLogo.caja === '54x54' && conLogo.ajuste === 'contain', JSON.stringify(conLogo));

    t('Sitio web y redes del organizador');
    let e = await enlaces();
    di('salen los dos de la organización', e.length === 2, JSON.stringify(e.map((x) => x.texto)));
    di('la red se rotula por su dominio', e[1]?.texto === 'Instagram', e[1]?.texto);
    di('abren fuera, con noopener nofollow ugc', e.every((x) => x.destino === '_blank' && ['noopener', 'nofollow', 'ugc'].every((r) => x.rel.includes(r))), e[0]?.rel);

    // Los de la actividad mandan sobre los de la organización: son los que el
    // organizador edita desde /mi-cuenta.
    sql(`UPDATE activities SET enlace_web='https://reforestemos.cl', enlace_red_social='https://www.linkedin.com/company/x' WHERE slug='${SLUG}'`);
    await ir(SLUG);
    e = await enlaces();
    di('los de la actividad ganan a los de la organización', e[0]?.href === 'https://reforestemos.cl/', e[0]?.href);
    di('y su red también se reconoce', e[1]?.texto === 'LinkedIn', e[1]?.texto);

    sql(`UPDATE organizations SET enlace_web=NULL, enlace_red_social=NULL WHERE id=${ORG}`);
    await ir(OTRA);
    di('sin ninguno de los dos, no se pinta la fila', (await enlaces()).length === 0 && (await p.$('.org-enlaces')) === null);
    di('pero el organizador sigue ahí', (await p.$eval('.org-nombre', (x) => x.textContent.trim())).length > 0);

    t('Open Graph: al compartir sale lo de ESTA actividad');
    await ir(SLUG);
    const og = await p.evaluate(() => Object.fromEntries(
      [...document.querySelectorAll('meta[property^="og:"], meta[name^="twitter:"]')]
        .map((m) => [m.getAttribute('property') ?? m.getAttribute('name'), m.content]),
    ));
    const portada = sql(`SELECT IFNULL(imagen_portada,'') FROM activities WHERE slug='${SLUG}'`);
    di('og:title es el de la actividad', og['og:title']?.length > 0 && !og['og:title'].startsWith('Día del'), og['og:title']);
    di('og:description sale de su descripción', (og['og:description'] ?? '').length > 20);
    di('og:url apunta a la ficha', (og['og:url'] ?? '').endsWith(SLUG), og['og:url']);
    di('og:image es su portada y no la genérica', portada === '' || (og['og:image'] ?? '').includes(portada), og['og:image']);
    di('og:image es absoluta', (og['og:image'] ?? '').startsWith('http'));
    di('twitter:card pide tarjeta grande', og['twitter:card'] === 'summary_large_image', og['twitter:card']);

    t('Los botones de compartir');
    const botones = await p.$$eval('.compartir-btn', (n) => n.map((b) => ({
      tag: b.tagName, texto: b.textContent.trim(), href: b.getAttribute('href'),
      alto: Math.round(b.getBoundingClientRect().height), rel: b.getAttribute('rel'), destino: b.getAttribute('target'),
    })));
    di('son tres', botones.length === 3, JSON.stringify(botones.map((b) => b.texto)));
    const wa = botones.find((b) => b.texto === 'WhatsApp');
    const fb = botones.find((b) => b.texto === 'Facebook');
    const cp = botones.find((b) => b.texto === 'Copiar enlace');
    di('WhatsApp lleva el título y el enlace', wa?.href?.startsWith('https://wa.me/?text=') && decodeURIComponent(wa.href).includes(SLUG));
    di('Facebook lleva el sharer con la url', fb?.href?.includes('facebook.com/sharer/sharer.php?u=') && decodeURIComponent(fb.href).includes(SLUG));
    di('los dos abren fuera y con noopener', [wa, fb].every((b) => b.destino === '_blank' && /noopener/.test(b.rel ?? '')));
    di('los dos funcionan sin JavaScript', [wa, fb].every((b) => b.tag === 'A' && b.href.startsWith('https://')));
    di('copiar es un <button>', cp?.tag === 'BUTTON');
    di('los tres miden lo mismo', new Set(botones.map((b) => b.alto)).size === 1, `${botones.map((b) => b.alto).join('/')} px`);
    di('el min-height cuenta el borde (border-box)', cp?.alto === 40, `${cp?.alto} px`);
    di('se nombra a Instagram y qué hacer con él', (await p.$eval('.compartir-nota', (x) => x.textContent)).includes('Instagram'));

    t('Copiar el enlace de verdad');
    await p.$$eval('.compartir-btn', (n) => n.find((b) => b.tagName === 'BUTTON').click());
    await esperar(400);
    di('el portapapeles trae la url canónica', (await p.evaluate(() => navigator.clipboard.readText())) === `${B}/actividades/${SLUG}`);
    const tras = await p.$eval('.compartir-botones button.compartir-btn', (b) => ({ txt: b.textContent.trim(), cls: b.className }));
    di('el botón avisa de que copió', tras.txt === 'Enlace copiado' && tras.cls.includes('--hecho'), tras.txt);
    await esperar(2600);
    di('y vuelve solo a su texto', (await p.$eval('.compartir-botones button.compartir-btn', (b) => b.textContent.trim())) === 'Copiar enlace');
    di('el aviso vacío no deja hueco', (await p.$eval('.compartir-aviso', (x) => Math.round(x.getBoundingClientRect().height))) === 0);

    t('Nada se desborda');
    for (const ancho of [1440, 1020, 760, 390]) {
      await p.setViewport({ width: ancho, height: 1100 });
      await ir(SLUG);
      const d = await p.evaluate(() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth }));
      di(`sin desborde horizontal a ${ancho}`, d.doc <= d.win, JSON.stringify(d));
    }
    await p.screenshot({ path: `${S}/ficha-actividad-390.png`, fullPage: true });

    t('Un nombre de organización imposible no rompe la ficha');
    sql(`UPDATE organizations SET nombre='Fundacionsupercalifragilisticoespialidosaydemasnombrelarguisimo' WHERE id=${ORG}`);
    await p.setViewport({ width: 390, height: 900 });
    await ir(SLUG);
    const d = await p.evaluate(() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth }));
    di('una palabra de 60 letras no lo desborda', d.doc <= d.win, JSON.stringify(d));
    di('porque el nombre lleva `dato-editable`', (await p.$eval('.org-nombre', (x) => getComputedStyle(x).overflowWrap)) === 'anywhere');
    sql(`UPDATE organizations SET nombre='Fundación Junto al Barrio' WHERE id=${ORG}`);
} finally {
    devolver();
}

console.log(`\n${ok} OK · ${mal} mal · consola: ${errores.length ? errores.join(' | ') : 'limpia'}`);
await nav.close();
process.exit(mal ? 1 : 0);
