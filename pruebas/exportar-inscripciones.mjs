// «Exportar inscripciones» del admin — tanda del 05/10, punto 7a.
//
// SÓLO los participantes: nombre, correo, mayor de edad, actividad,
// organización, fecha de inscripción y fecha de la actividad, con el ID
// primero y «Baja» al final. Las columnas de la actividad que se añadieron el
// 23/09 se fueron a Actividades → Exportar (`exportar-actividades.mjs`).
// Y «Respondió la evaluación», cruzando por correo y actividad.
//
// Se descarga el Excel como administrador, se lee con el mismo OpenSpout que
// lo escribe (`leer-xlsx.php`) y cada celda se compara con la base. Para la
// columna de evaluación se siembra una respuesta —con el correo en otras
// mayúsculas— y se borra al terminar.
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

// Una actividad con al menos dos inscripciones: una evaluará y la otra no.
const actId = sql(`select activity_id from registrations r join activities a on a.id = r.activity_id
    where a.deleted_at is null group by activity_id having count(*) >= 2 order by activity_id limit 1`);
const [regSi, correoSi] = sql(`select id, correo from registrations where activity_id = ${actId} order by id limit 1`).split('\t');
const regNo = sql(`select id from registrations where activity_id = ${actId} and lower(correo) <> lower('${correoSi}')
    and lower(correo) not in (select lower(correo) from activity_evaluations where activity_id = ${actId}) order by id limit 1`);
const EVAL = `eval-export-${Date.now()}`;
sql(`insert into activity_evaluations (activity_id, nombre, correo, experiencia, significado, motivacion, ip_hash, created_at, updated_at)
    values (${actId}, '${EVAL}', upper('${correoSi}'), 5, 'Prueba de exportación', 5, repeat('0', 64), now(), now())`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();

try {
    await p.goto(`${B}/admin/login`);
    await p.type('[name="email"]', ADMIN);
    await p.type('[name="password"]', CLAVE_ADMIN);
    await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

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
    const fila = (id) => datos.find((f) => String(f[0]) === String(id));

    t('1 · Las columnas');

    const esperadas = ['ID', 'Nombre', 'Correo', 'Mayor de edad', 'Actividad', 'Organización',
        'Fecha de inscripción', 'Fecha de la actividad', 'Respondió la evaluación', 'Baja'];
    di('**exactamente las pedidas**, con el ID primero', JSON.stringify(cab) === JSON.stringify(esperadas), cab.join(' | '));
    di('sin las de la actividad (se fueron a su exportación)', ! ['Descripción', 'Temas', 'Dirección', 'Formato', 'Cupos totales'].some((c) => cab.includes(c)));
    di('todas las filas tienen las mismas celdas que la cabecera', datos.every((f) => f.length === cab.length));
    di('una fila por inscripción de la actividad', datos.length === +sql(`select count(*) from registrations where activity_id = ${actId}`), `${datos.length}`);

    t('2 · Los datos, contra la base');

    const r = JSON.parse(sql(`select json_object('nombre', r.nombre, 'correo', r.correo, 'mayor', if(r.es_mayor_edad, 'Sí', 'No'),
        'act', a.titulo, 'org', o.nombre,
        'fecha', if(a.sin_fecha_definida, 'Por definir', concat(date_format(a.fecha_inicio, '%d-%m-%Y'),
            if(a.fecha_termino is not null and a.fecha_termino <> a.fecha_inicio, concat(' al ', date_format(a.fecha_termino, '%d-%m-%Y')), ''))))
        from registrations r join activities a on a.id = r.activity_id join organizations o on o.id = a.organization_id where r.id = ${regSi}`));
    const f = fila(regSi);
    const v = (c) => String(f?.[col(c)] ?? '');
    di('la inscripción está en el archivo', !! f, `inscripción ${regSi}`);
    di('nombre y correo', v('Nombre') === r.nombre && v('Correo') === r.correo);
    di('mayor de edad', v('Mayor de edad') === r.mayor, v('Mayor de edad'));
    di('actividad y organización', v('Actividad') === r.act && v('Organización') === r.org, `${v('Actividad')} · ${v('Organización')}`);
    di('fecha de la actividad', v('Fecha de la actividad') === r.fecha, `${v('Fecha de la actividad')} / ${r.fecha}`);
    di('fecha de inscripción, con hora', /\d{2}:\d{2}/.test(v('Fecha de inscripción')), v('Fecha de inscripción'));

    t('3 · Respondió la evaluación');

    di('**«Sí» para quien la respondió** (aunque escribiera el correo en mayúsculas)', v('Respondió la evaluación') === 'Sí', v('Respondió la evaluación'));
    di('«No» para quien no', !! regNo && String(fila(regNo)?.[col('Respondió la evaluación')] ?? '') === 'No', `inscripción ${regNo || '—'}`);
} finally {
    sql(`delete from activity_evaluations where nombre = '${EVAL}'`);
    await nav.close();
}

console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
