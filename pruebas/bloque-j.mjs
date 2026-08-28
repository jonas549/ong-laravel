// Bloque J — biblioteca de medios y selector, en Chrome de verdad.
//
// Subir un archivo, elegirlo desde un formulario y ver que la ruta viaja hasta
// la base son cosas que por HTTP no se ven: el selector es un diálogo que
// carga por fetch y escribe en un campo oculto.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { writeFileSync, mkdirSync, rmSync, existsSync } from 'node:fs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const S = process.env.DPS_SALIDA ?? tmpdir();
const MYSQL = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', '--default-character-set=utf8mb4', 'ong_laravel', '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(58)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

/* Un PNG de verdad, generado al vuelo: 4x4 rojo. */
const PNG = Buffer.from(
    '89504e470d0a1a0a0000000d494844520000000400000004080200000026930900' +
    '0000164944415408d763fccfc0f01f8a19181818fe8301000fd8027fc3a3b7f800' +
    '00000049454e44ae426082', 'hex');

const TEMPORAL = `${S}/dps-medios-prueba`;
if (existsSync(TEMPORAL)) rmSync(TEMPORAL, { recursive: true, force: true });
mkdirSync(TEMPORAL, { recursive: true });

const ARCHIVO = `${TEMPORAL}/Foto de Prueba ÁÉÍ.png`;
writeFileSync(ARCHIVO, PNG);

const ARCHIVO2 = `${TEMPORAL}/Segunda imagen.png`;
writeFileSync(ARCHIVO2, PNG);

const REEMPLAZO = `${TEMPORAL}/reemplazo.png`;
writeFileSync(REEMPLAZO, PNG);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
await p.setViewport({ width: 1440, height: 1000 });

/* ── Entrar ── */
await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', 'admin@ong-laravel.test');
await p.type('input[name="password"]', 'admin1234');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

t('La biblioteca carga y enseña lo que ya había');

await p.goto(`${B}/admin/medios`, { waitUntil: 'networkidle2' });

const indexadas = Number(sql("SELECT COUNT(*) FROM media WHERE origen='codigo'"));
const enPantalla = await p.$$eval('.ficha-medio', (n) => n.length);

di('la pantalla de medios carga', p.url().endsWith('/admin/medios'));
di('indexó las imágenes del diseño', indexadas === 75, `${indexadas} filas`);
di('la cuadrícula pinta la primera página', enPantalla > 0 && enPantalla <= 24, `${enPantalla} fichas`);

const conImagen = await p.$$eval('.ficha-medio-imagen img', (i) => i.filter((x) => x.naturalWidth > 0).length);
di('las miniaturas cargan de verdad', conImagen > 0, `${conImagen} con imagen`);

/* Filtros */
await p.goto(`${B}/admin/medios?tipo=svg`, { waitUntil: 'networkidle2' });
const svg = await p.$$eval('.ficha-medio', (n) => n.length);
di('el filtro por formato funciona', svg === 3, `${svg} SVG`);

await p.goto(`${B}/admin/medios?q=scotiabank`, { waitUntil: 'networkidle2' });
const busq = await p.$$eval('.ficha-medio', (n) => n.length);
di('el buscador filtra', busq >= 1 && busq < 75, `${busq} resultados`);

t('Subir un archivo');

const antesSubidos = Number(sql("SELECT COUNT(*) FROM media WHERE origen='subido'"));

await p.goto(`${B}/admin/medios`, { waitUntil: 'networkidle2' });
await p.evaluate(() => document.querySelector('[x-on\\:click="$dispatch(\'abrir-subida\')"]')?.click());
await esperar(300);

const entrada = await p.$('input[type=file][multiple]');
di('el panel de subida se abre', !!entrada);

if (entrada) {
    await entrada.uploadFile(ARCHIVO, ARCHIVO2);
    await esperar(400);

    const enCola = await p.$$eval('li', (ls) => ls.filter((l) => /Foto de Prueba/.test(l.textContent)).length);
    di('el archivo entra en la cola', enCola > 0);

    // El botón lleva sus dos estados dentro («Subir» y «Subiendo…»), así que
    // su textContent no es 'Subir' a secas. Se busca por el principio.
    await p.evaluate(() => {
        [...document.querySelectorAll('button')]
            .find((b) => b.textContent.replace(/\s+/g, ' ').trim().startsWith('Subir Subiendo'))
            ?.click();
    });
    await p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => {});
    await esperar(600);
}

const despuesSubidos = Number(sql("SELECT COUNT(*) FROM media WHERE origen='subido'"));
di('sube los dos archivos de una tanda', despuesSubidos === antesSubidos + 2, `${antesSubidos} -> ${despuesSubidos}`);

const fila = sql("SELECT ruta, nombre, ancho, alto, mime, peso FROM media WHERE origen='subido' ORDER BY id DESC LIMIT 1").split('\t');
di('la ruta va a storage, no a public/img', fila[0]?.startsWith('storage/medios/'), fila[0]);
di('el nombre se normaliza: sin espacios ni acentos', /^[a-z0-9-]+\.png$/.test(fila[0].split('/').pop()), fila[0].split('/').pop());
// Los dos de la tanda, no sólo el último: el nombre original es lo que se
// enseña en la biblioteca, y es lo primero que se pierde al normalizar.
// `.map(trim)`: en Windows la salida de mysql deja un retorno de carro al
// final de cada línea, y sin quitarlo ninguna comparación cuadra nunca.
const nombres = sql("SELECT nombre FROM media WHERE origen='subido' ORDER BY id")
    .split(String.fromCharCode(10)).map((x) => x.trim());
di('conserva los nombres originales para enseñarlos',
    nombres.includes('Foto de Prueba ÁÉÍ.png') && nombres.includes('Segunda imagen.png'),
    nombres.join(' · '));
di('lee las medidas', fila[2] === '4' && fila[3] === '4', `${fila[2]}x${fila[3]}`);
di('detecta el tipo real', fila[4] === 'image/png', fila[4]);

const url = `${B}/${fila[0]}`;
const servido = await p.evaluate(async (u) => (await fetch(u)).status, url);
di('el archivo se sirve por su URL', servido === 200, `HTTP ${servido} ${fila[0]}`);

t('El selector, desde un formulario real');

await p.goto(`${B}/admin/contenido/noticias/nuevo`, { waitUntil: 'networkidle2' });

di('el formulario trae el selector', await p.$('.campo-medio') !== null);
di('ya no hay campo de texto para la ruta', await p.$('input[type=text][name="imagen"]') === null);

await p.evaluate(() => {
    [...document.querySelectorAll('.campo-medio button')].find((b) => /Elegir imagen/.test(b.textContent))?.click();
});
await esperar(900);

const dialogo = await p.$('[role=dialog]');
di('el diálogo de la biblioteca se abre', !!dialogo);

const fichas = await p.$$eval('[role=dialog] .ficha-medio', (n) => n.length);
di('el diálogo carga la biblioteca por fetch', fichas > 0, `${fichas} fichas`);

// Buscar dentro del diálogo y elegir.
await p.evaluate(() => {
    const b = document.querySelector('[role=dialog] input[type=search]');
    b.value = 'scotiabank';
    b.dispatchEvent(new Event('input', { bubbles: true }));
});
await esperar(1000);

const filtradas = await p.$$eval('[role=dialog] .ficha-medio', (n) => n.length);
di('el buscador del diálogo filtra', filtradas >= 1 && filtradas < fichas, `${filtradas} de ${fichas}`);

await p.evaluate(() => document.querySelector('[role=dialog] .ficha-medio')?.click());
await esperar(400);

const elegido = await p.evaluate(() => ({
    oculto: document.querySelector('input[type=hidden][name="imagen"]')?.value,
    cerrado: !document.querySelector('[role=dialog]')?.offsetParent,
    vista: !!document.querySelector('.campo-medio-vista img'),
}));

di('elegir escribe la ruta en el campo oculto', /^img\//.test(elegido.oculto || ''), String(elegido.oculto));
di('el diálogo se cierra al elegir', elegido.cerrado);
di('se ve la vista previa', elegido.vista);

// Guardar y comprobar que la ruta llegó a la base.
const titulo = 'Noticia de prueba J ' + Date.now();
await p.evaluate((tit) => {
    const f = document.querySelector('[name="titulo"]').form;
    const set = (n, v) => { const e = f.querySelector(`[name="${n}"]`); if (e) { e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); } };
    set('titulo', tit);
    set('extracto', 'Extracto de prueba.');
    set('published_at', '2026-08-28T10:00');
}, titulo);

// El primer <form> de la página es el buscador del panel: hay que coger el
// que de verdad tiene el campo del título.
await p.evaluate(() => document.querySelector('[name="titulo"]').form.submit());
await p.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});

const guardada = sql(`SELECT imagen FROM posts WHERE titulo=${JSON.stringify(titulo).replace(/"/g, "'")}`);
di('la ruta elegida llega a la base', guardada === elegido.oculto, `«${guardada}»`);

t('Dónde se usa, y el freno al borrar');

// La que acabamos de subir no la usa nadie.
const idSubida = sql("SELECT id FROM media WHERE origen='subido' ORDER BY id DESC LIMIT 1");
await p.goto(`${B}/admin/medios/${idSubida}`, { waitUntil: 'networkidle2' });

const sinUso = await p.evaluate(() => document.body.textContent.includes('En ningún sitio'));
di('una imagen sin usar lo dice', sinUso);
di('una imagen subida se puede reemplazar', await p.$('input[type=file][name="archivo"]') !== null);

// La que sí se usa.
const idUsada = sql("SELECT id FROM media WHERE ruta='img/logo-scotiabank-red.svg'");
await p.goto(`${B}/admin/medios/${idUsada}`, { waitUntil: 'networkidle2' });

const usada = await p.evaluate(() => ({
    texto: document.body.textContent,
    hayReemplazo: !!document.querySelector('input[type=file][name="archivo"]'),
    hayBorrar: [...document.querySelectorAll('button')].some((b) => /Borrar el archivo/.test(b.textContent)),
}));

di('dice dónde se usa', /Testimonio|Partner/.test(usada.texto));
di('una imagen del diseño no se reemplaza', !usada.hayReemplazo);
di('una imagen del diseño no se borra', !usada.hayBorrar);

// El servidor lo impide aunque se lance a mano.
const intento = await p.evaluate(async (base, id) => {
    const t = document.querySelector('meta[name=csrf-token]')?.content;
    const r = await fetch(`${base}/admin/medios/${id}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': t, 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_method=DELETE',
        redirect: 'manual',
    });
    return r.status;
}, B, idUsada);
await esperar(300);
const sigue = Number(sql(`SELECT COUNT(*) FROM media WHERE id=${idUsada} AND deleted_at IS NULL`));
di('un DELETE a mano no borra la del diseño', sigue === 1, `respondió ${intento}`);

t('Editar, reemplazar, carpetas y borrar');

const idNueva = sql("SELECT id FROM media WHERE origen='subido' ORDER BY id DESC LIMIT 1");

/* — Editar título, alt y carpeta — */
await p.goto(`${B}/admin/medios/${idNueva}`, { waitUntil: 'networkidle2' });
await p.evaluate(() => {
    const f = document.querySelector('[name="titulo"]').form;
    const set = (n, v) => { const e = f.querySelector(`[name="${n}"]`); e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); };
    set('titulo', 'Cartel de la jornada');
    set('alt', 'Voluntarias plantando un árbol en la plaza');
    set('carpeta', 'edicion 2026');
    f.submit();
});
await p.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});

const editado = sql(`SELECT titulo, alt, carpeta FROM media WHERE id=${idNueva}`).split('	');
di('guarda el título', editado[0] === 'Cartel de la jornada', editado[0]);
di('guarda el texto alternativo', editado[1] === 'Voluntarias plantando un árbol en la plaza', editado[1]);
di('guarda la carpeta', editado[2] === 'edicion 2026', editado[2]);

/* — La carpeta filtra — */
await p.goto(`${B}/admin/medios?carpeta=edicion+2026`, { waitUntil: 'networkidle2' });
const enCarpeta = await p.$$eval('.ficha-medio', (n) => n.length);
di('el filtro por carpeta funciona', enCarpeta === 1, `${enCarpeta} archivo`);

/* — El filtro por fecha — */
const hoy = new Date().toISOString().slice(0, 10);
const subidasHoy = Number(sql(`SELECT COUNT(*) FROM media WHERE DATE(created_at) >= '${hoy}'`));
await p.goto(`${B}/admin/medios?desde=${hoy}`, { waitUntil: 'networkidle2' });
const deHoy = await p.$$eval('.ficha-medio', (n) => n.length);
di('el filtro por fecha deja sólo lo de hoy', deHoy === subidasHoy, `${deHoy}, y en la base hay ${subidasHoy}`);
di('y las del diseño llevan la fecha de su archivo, no la de indexado',
    Number(sql("SELECT COUNT(*) FROM media WHERE origen='codigo' AND DATE(created_at) >= '" + hoy + "'")) === 0);

await p.goto(`${B}/admin/medios?hasta=2020-01-01`, { waitUntil: 'networkidle2' });
const viejas = await p.$$eval('.ficha-medio', (n) => n.length);
di('el filtro por fecha hacia atrás también', viejas === 0, `${viejas}`);

/* — Reemplazar conservando la URL — */
const rutaAntes = sql(`SELECT ruta FROM media WHERE id=${idNueva}`);
await p.goto(`${B}/admin/medios/${idNueva}`, { waitUntil: 'networkidle2' });
const campoReemplazo = await p.$('input[type=file][name="archivo"]');
await campoReemplazo.uploadFile(REEMPLAZO);
await p.evaluate(() => document.querySelector('[name="archivo"]').form.submit());
await p.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});

const rutaDespues = sql(`SELECT ruta FROM media WHERE id=${idNueva}`);
di('reemplazar NO cambia la URL', rutaDespues === rutaAntes, rutaDespues);
const sirve = await p.evaluate(async (u) => (await fetch(u)).status, `${B}/${rutaDespues}`);
di('y el archivo nuevo se sirve por ella', sirve === 200, `HTTP ${sirve}`);

/* — Subir desde el propio selector — */
await p.goto(`${B}/admin/contenido/partners/nuevo`, { waitUntil: 'networkidle2' });
await p.evaluate(() => [...document.querySelectorAll('.campo-medio button')].find((b) => /Elegir imagen/.test(b.textContent))?.click());
await esperar(800);

const antesDeSubirAqui = Number(sql("SELECT COUNT(*) FROM media WHERE origen='subido'"));
const entradaDialogo = await p.$('[role=dialog] input[type=file]');
di('el diálogo ofrece subir en el momento', !!entradaDialogo);

if (entradaDialogo) {
    await entradaDialogo.uploadFile(ARCHIVO2);
    await esperar(2500);

    const trasSubir = await p.evaluate(() => ({
        oculto: document.querySelector('input[type=hidden][name="logo_path"]')?.value,
        cerrado: !document.querySelector('[role=dialog]')?.offsetParent,
    }));

    const ahora = Number(sql("SELECT COUNT(*) FROM media WHERE origen='subido'"));
    di('subir desde el selector crea la fila', ahora === antesDeSubirAqui + 1, `${antesDeSubirAqui} -> ${ahora}`);
    di('y lo deja elegido sin recargar', /^storage\/medios\//.test(trasSubir.oculto || ''), String(trasSubir.oculto));
    di('y cierra el diálogo', trasSubir.cerrado);
}

/* — El logo de la organización — */
const idOrg = sql('SELECT id FROM organizations ORDER BY id LIMIT 1');
await p.goto(`${B}/admin/organizaciones/${idOrg}/editar`, { waitUntil: 'networkidle2' });
di('la ficha de organización usa el selector', await p.$('.campo-medio') !== null);
di('y ya no pide la ruta a mano', await p.$('input[type=text][name="logo_path"]') === null);

/* — Borrar: en uso se frena, sin usar se borra — */
const idEnUso = sql("SELECT id FROM media WHERE ruta='img/manos.png'");
const enUsoAntes = Number(sql(`SELECT COUNT(*) FROM media WHERE id=${idEnUso} AND deleted_at IS NULL`));
di('la imagen de prueba en uso existe', enUsoAntes === 1);

// Sin usar: se borra de verdad, archivo incluido.
const rutaBorrar = sql(`SELECT ruta FROM media WHERE id=${idNueva}`);
await p.goto(`${B}/admin/medios/${idNueva}`, { waitUntil: 'networkidle2' });
await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => /Borrar el archivo/.test(b.textContent))?.click());
await esperar(400);
await p.evaluate(() => [...document.querySelectorAll('button')].find((b) => /Sí, borrar/.test(b.textContent))?.click());
await p.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
await esperar(400);

const borrada = Number(sql(`SELECT COUNT(*) FROM media WHERE id=${idNueva} AND deleted_at IS NULL`));
di('una imagen sin usar se borra', borrada === 0);

// En el disco, no por HTTP: un archivo que falta bajo el enlace de `storage`
// responde 403 y no 404, y esa petición contaría como error de consola.
const enDisco = existsSync(`C:/laragon/www/ong-laravel/public/${rutaBorrar}`);
di('y su archivo desaparece del disco', !enDisco, rutaBorrar);

t('Errores de JavaScript');
di('sin errores en consola', errores.length === 0, errores.slice(0, 3).join(' | '));

await p.screenshot({ path: `${S}/j-biblioteca.png`, fullPage: false });

/* ── Limpieza ── */
sql(`DELETE FROM posts WHERE titulo=${JSON.stringify(titulo).replace(/"/g, "'")}`);

/*
 * TODOS los archivos subidos, no sólo el último: cada pasada de esta prueba
 * sube varios, y el que se borra por la interfaz es uno. Sin esto, repetirla
 * va dejando huérfanos en `storage/medios` que ya no tienen fila.
 */
const subidas = sql("SELECT ruta FROM media WHERE origen='subido'")
    .split(String.fromCharCode(10)).map((x) => x.trim()).filter(Boolean);

for (const r of subidas) {
    try { rmSync(`C:/laragon/www/ong-laravel/public/${r}`, { force: true }); } catch {}
}

sql("DELETE FROM media WHERE origen='subido'");
rmSync(TEMPORAL, { recursive: true, force: true });
console.log('\n  (noticia, archivo y fila de prueba borrados)');

console.log(`\n${'='.repeat(62)}\n  ${ok} bien, ${mal} mal\n${'='.repeat(62)}`);
await nav.close();
process.exit(mal ? 1 : 0);
