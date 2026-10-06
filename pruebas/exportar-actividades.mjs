// Actividades → Exportar — tanda del 05/10, punto 7b.
//
// TODAS las actividades, en cualquier estado y sin seguir los filtros de la
// pantalla: el cliente veía cortarse la lista en 33 de 39. Se descarga por el
// botón del listado, se lee con OpenSpout (`leer-xlsx.php`) y se compara:
//   - la lista de IDs, entera, contra la base (no sólo cuántas);
//   - la cabecera, con el ID primero;
//   - una actividad completa, celda por celda, con un colaborador añadido para
//     que «Colaboración» tenga algo que comprobar (se quita al terminar).
//
//   node pruebas/exportar-actividades.mjs      (desde la raíz del repo)
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { writeFileSync, unlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '--default-character-set=utf8mb4', '-e', q]).toString().trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

// La actividad con más términos, para que temas y compañía tengan contenido.
const actId = sql(`select a.id from activities a where a.deleted_at is null
    order by (select count(*) from activity_taxonomy_term t where t.activity_id = a.id) desc, a.id limit 1`);
const COLAB = `Colaborador de prueba ${Date.now()}`;
sql(`insert into activity_collaborators (activity_id, nombre, tipo, orden, created_at, updated_at) values (${actId}, '${COLAB}', 'Institución educativa', 99, now(), now())`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 900 });

try {
    await p.goto(`${B}/admin/login`);
    await p.type('[name="email"]', ADMIN);
    await p.type('[name="password"]', CLAVE_ADMIN);
    await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

    t('0 · El botón');

    // Con un filtro puesto, para ver que la descarga NO lo sigue.
    await p.goto(`${B}/admin/actividades?estado=publicada`, { waitUntil: 'networkidle2' });
    // El del contenido, no el «Exportar» del menú lateral (que es el de inscripciones).
    const href = await p.evaluate(() => [...document.querySelectorAll('a')].find((a) => a.textContent.trim() === 'Exportar' && a.href.includes('/admin/actividades/exportar'))?.getAttribute('href'));
    di('«Exportar» está en el listado de actividades', !! href, href ?? '');
    di('y lleva la marca de descarga, para soltarse al terminar', await p.evaluate(() => !! [...document.querySelectorAll('a[data-descarga]')].find((a) => a.textContent.trim() === 'Exportar' && a.href.includes('/admin/actividades/exportar'))));

    const base64 = await p.evaluate(async (url) => {
        const r = await fetch(url, { credentials: 'same-origin' });
        const bytes = new Uint8Array(await r.arrayBuffer());
        let s = '';
        for (const b of bytes) s += String.fromCharCode(b);
        return btoa(s);
    }, href);

    const archivo = join(tmpdir(), `actividades-${Date.now()}.xlsx`);
    writeFileSync(archivo, Buffer.from(base64, 'base64'));
    const filas = JSON.parse(execFileSync(PHP, ['pruebas/leer-xlsx.php', archivo], { encoding: 'utf8' }));
    unlinkSync(archivo);

    const [cab, ...datos] = filas;
    const col = (nombre) => cab.indexOf(nombre);

    t('1 · TODAS las actividades');

    // `\r?\n`: en Windows la salida de mysql trae `\r` al final de cada línea.
    const enBase = sql('select id from activities where deleted_at is null order by id').split(/\r?\n/).map((x) => x.trim()).filter(Boolean);
    const enArchivo = datos.map((f) => String(f[0])).sort((a, b) => a - b);
    const sobran = enArchivo.filter((x) => ! enBase.includes(x));
    const faltanIds = enBase.filter((x) => ! enArchivo.includes(x));
    di('**el mismo número de filas que actividades hay**', enArchivo.length === enBase.length, `${enArchivo.length} de ${enBase.length}`);
    di('**y exactamente los mismos IDs**', JSON.stringify(enArchivo) === JSON.stringify(enBase), `sobran: ${sobran.join(',') || '—'} · faltan: ${faltanIds.join(',') || '—'}`);
    const estados = sql('select count(distinct estado) from activities where deleted_at is null');
    di('en todos los estados, no sólo las publicadas', new Set(datos.map((f) => f[col('Estado')])).size === +estados, [...new Set(datos.map((f) => f[col('Estado')]))].join(', '));
    di('las de la papelera no salen', ! datos.some((f) => sql(`select count(*) from activities where id = ${+f[0]} and deleted_at is not null`) !== '0'));

    t('2 · Las columnas');

    const pedidas = ['ID', 'Actividad', 'Organización', 'Estado', 'Correo que registró la actividad', 'Fecha de registro',
        'Fecha de la actividad', 'Fecha de término', 'Hora de inicio', 'Hora de término', 'Dirección', 'Comuna', 'Región',
        'Formato', 'Cupos totales', 'Requiere inscripción previa', 'Descripción', 'Temas', 'Características', 'Dirigido a',
        'Colaboración', 'Red social', 'Sitio web'];
    di('el ID es la primera', cab[0] === 'ID', cab[0]);
    const faltan = pedidas.filter((c) => ! cab.includes(c));
    di('están todas las pedidas', faltan.length === 0, faltan.join(', ') || `${cab.length} columnas`);
    di('todas las filas tienen las mismas celdas que la cabecera', datos.every((f) => f.length === cab.length));

    t('3 · Una actividad, contra la base');

    const fila = datos.find((f) => String(f[0]) === actId);
    const v = (c) => String(fila?.[col(c)] ?? '');
    const a = JSON.parse(sql(`select json_object(
        'titulo', a.titulo, 'org', o.nombre, 'correo', ifnull(u.email, ''),
        'fecha', if(a.sin_fecha_definida, 'Por definir', ifnull(date_format(a.fecha_inicio, '%d-%m-%Y'), '')),
        'termino', if(a.sin_fecha_definida, '', ifnull(date_format(a.fecha_termino, '%d-%m-%Y'), '')),
        'hi', ifnull(left(a.hora_inicio, 5), ''), 'ht', ifnull(left(a.hora_termino, 5), ''),
        'dir', ifnull(a.direccion, ''), 'comuna', ifnull(c.nombre, ''), 'region', ifnull(r.nombre, ''), 'formato', ifnull(a.formato, ''),
        'insc', if(a.inscripcion_habilitada, 'Sí', 'No'), 'cupos', ifnull(a.cupos_totales, ''),
        'desc', ifnull(a.descripcion, ''), 'web', ifnull(o.enlace_web, ''), 'red', ifnull(o.enlace_red_social, ''))
        from activities a join organizations o on o.id = a.organization_id left join users u on u.id = o.user_id
        left join communes c on c.id = a.commune_id left join regions r on r.id = a.region_id where a.id = ${actId}`));
    const nombres = (grupo) => sql(`select group_concat(t.nombre order by t.nombre separator '|') from activity_taxonomy_term x
        join taxonomy_terms t on t.id = x.taxonomy_term_id where x.activity_id = ${actId} and t.grupo = '${grupo}'`).replace(/^NULL$/, '');
    const mismos = (celda, grupo) => String(celda ?? '').split(', ').filter(Boolean).sort().join('|') === nombres(grupo).split('|').filter(Boolean).sort().join('|');

    di('la actividad está en el archivo', !! fila, `actividad ${actId}`);
    di('nombre y organización', v('Actividad') === a.titulo && v('Organización') === a.org);
    di('correo que la registró (la cuenta de la organización)', v('Correo que registró la actividad') === a.correo, v('Correo que registró la actividad'));
    di('fecha de registro con hora', /\d{2}:\d{2}/.test(v('Fecha de registro')), v('Fecha de registro'));
    di('fechas de la actividad y de término', v('Fecha de la actividad') === a.fecha && v('Fecha de término') === a.termino, `${v('Fecha de la actividad')} / ${v('Fecha de término')}`);
    di('horas', v('Hora de inicio') === a.hi && v('Hora de término') === a.ht);
    di('dirección, comuna y región', v('Dirección') === a.dir && v('Comuna') === a.comuna && v('Región') === a.region, `${v('Comuna')}, ${v('Región')}`);
    di('formato, cupos e inscripción', v('Formato') === a.formato && v('Cupos totales') === String(a.cupos) && v('Requiere inscripción previa') === a.insc);
    di('descripción completa', v('Descripción') === a.desc, `${v('Descripción').length} caracteres`);
    di('temas y características', mismos(v('Temas'), 'tema') && mismos(v('Características'), 'caracteristica'));
    di('dirigido a', v('Dirigido a').includes(nombres('publico').split('|')[0] ?? ''), v('Dirigido a'));
    di('colaboración, con su tipo', v('Colaboración').includes(`${COLAB} (Institución educativa)`), v('Colaboración'));
    di('red social y sitio web de la organización', v('Sitio web') === a.web && v('Red social') === a.red);
} finally {
    sql(`delete from activity_collaborators where nombre = '${COLAB}'`);
    await nav.close();
}

console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
