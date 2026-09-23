// Punto 6 del 23/09 — «Exportar inscripciones» trae los datos de la actividad.
//
// Se descarga el Excel como administrador, se lee con el mismo OpenSpout que
// lo escribe (`leer-xlsx.php`) y cada celda de la actividad se compara con la
// base. Para que la columna «Colaboración» tenga algo que comprobar, se le
// añade un colaborador a la actividad y se quita al terminar.
//
//   node pruebas/exportar-inscripciones.mjs      (desde la raíz del repo)
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

// Una inscripción cuya actividad tenga términos de los tres grupos.
const [regId, actId] = sql(`select r.id, a.id from registrations r join activities a on a.id = r.activity_id
    where a.deleted_at is null order by (select count(*) from activity_taxonomy_term t where t.activity_id = a.id) desc, r.id desc limit 1`).split('\t');

const COLAB = `Colaborador de prueba ${Date.now()}`;
sql(`insert into activity_collaborators (activity_id, nombre, tipo, orden, created_at, updated_at) values (${actId}, '${COLAB}', 'Institución educativa', 99, now(), now())`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();

try {
    await p.goto(`${B}/admin/login`);
    await p.type('[name="email"]', ADMIN);
    await p.type('[name="password"]', CLAVE_ADMIN);
    await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

    // Filtrado por la actividad: menos filas y la nuestra dentro.
    const base64 = await p.evaluate(async (url) => {
        const r = await fetch(url, { credentials: 'same-origin' });
        const bytes = new Uint8Array(await r.arrayBuffer());
        let s = '';
        for (const b of bytes) s += String.fromCharCode(b);
        return btoa(s);
    }, `${B}/admin/inscripciones/exportar/descargar?actividad=${actId}`);

    const archivo = join(tmpdir(), `inscripciones-${Date.now()}.xlsx`);
    writeFileSync(archivo, Buffer.from(base64, 'base64'));
    const filas = JSON.parse(execFileSync(PHP, ['pruebas/leer-xlsx.php', archivo], { encoding: 'utf8' }));
    unlinkSync(archivo);

    const [cab, ...datos] = filas;
    const col = (nombre) => cab.indexOf(nombre);
    const fila = datos.find((f) => String(f[0]) === regId);

    t('1 · Las columnas');

    di('el ID sigue siendo la primera', cab[0] === 'ID', cab[0]);
    di('siguen las del participante', ['Nombre', 'Correo', 'Actividad', 'Organización', 'Fecha de inscripción', 'Baja'].every((c) => cab.includes(c)));
    const pedidas = ['Fecha de inicio', 'Hora de inicio', 'Hora de término', 'Dirección', 'Formato', 'Cupos totales',
        'Requiere inscripción previa', 'Descripción', 'Temas', 'Características', 'Dirigido a', 'Colaboración', 'Sitio web', 'Red social'];
    const faltan = pedidas.filter((c) => ! cab.includes(c));
    di('y todas las de la actividad que pide el ticket', faltan.length === 0, faltan.join(', ') || `${cab.length} columnas`);
    di('todas las filas tienen las mismas celdas que la cabecera', datos.every((f) => f.length === cab.length));

    t('2 · Los datos, contra la base');

    di('la inscripción está en el archivo', !! fila, `inscripción ${regId}`);

    const a = JSON.parse(sql(`select json_object(
        'fecha', if(a.sin_fecha_definida, 'Por definir', date_format(a.fecha_inicio, '%d-%m-%Y')),
        'hi', ifnull(left(a.hora_inicio, 5), ''), 'ht', ifnull(left(a.hora_termino, 5), ''),
        'dir', ifnull(a.direccion, ''), 'formato', ifnull(a.formato, ''),
        'insc', if(a.inscripcion_habilitada, 'Sí', 'No'), 'cupos', ifnull(a.cupos_totales, ''),
        'desc', ifnull(a.descripcion, ''), 'web', ifnull(o.enlace_web, ''), 'red', ifnull(o.enlace_red_social, ''))
        from activities a join organizations o on o.id = a.organization_id where a.id = ${actId}`));
    const nombres = (grupo) => sql(`select group_concat(t.nombre order by t.nombre separator '|') from activity_taxonomy_term x
        join taxonomy_terms t on t.id = x.taxonomy_term_id where x.activity_id = ${actId} and t.grupo = '${grupo}'`).replace(/^NULL$/, '');
    const mismos = (celda, grupo) => String(celda ?? '').split(', ').filter(Boolean).sort().join('|') === nombres(grupo).split('|').filter(Boolean).sort().join('|');

    const v = (c) => String(fila?.[col(c)] ?? '');
    di('fecha de inicio', v('Fecha de inicio') === a.fecha, `${v('Fecha de inicio')} / ${a.fecha}`);
    di('horas de inicio y término', v('Hora de inicio') === a.hi && v('Hora de término') === a.ht, `${v('Hora de inicio')}-${v('Hora de término')}`);
    di('dirección', v('Dirección') === a.dir, v('Dirección'));
    di('formato', v('Formato') === a.formato, v('Formato'));
    di('requiere inscripción previa', v('Requiere inscripción previa') === a.insc, v('Requiere inscripción previa'));
    di('cupos', v('Cupos totales') === String(a.cupos), `${v('Cupos totales')} / ${a.cupos}`);
    di('descripción completa', v('Descripción') === a.desc, `${v('Descripción').length} caracteres`);
    di('temas', mismos(v('Temas'), 'tema'), v('Temas'));
    di('características', mismos(v('Características'), 'caracteristica'), v('Características'));
    di('dirigido a', v('Dirigido a').includes(nombres('publico').split('|')[0] ?? ''), v('Dirigido a'));
    di('colaboración, con su tipo', v('Colaboración').includes(`${COLAB} (Institución educativa)`), v('Colaboración'));
    di('sitio web y red social de la organización', v('Sitio web') === a.web && v('Red social') === a.red, `${v('Sitio web')} · ${v('Red social')}`);
} finally {
    sql(`delete from activity_collaborators where nombre = '${COLAB}'`);
    await nav.close();
}

console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
