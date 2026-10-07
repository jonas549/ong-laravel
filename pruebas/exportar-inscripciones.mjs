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
import * as clicDescarga from './clic-descarga.mjs';

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
sql(`insert into activity_evaluations (activity_id, nombre, correo, experiencia, significado, motivacion, como_se_entero, ip_hash, created_at, updated_at)
    values (${actId}, '${EVAL}', upper('${correoSi}'), 4, 'Prueba de exportación', 5, 'redes', repeat('0', 64), now(), now())`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();

try {
    await p.goto(`${B}/admin/login`);
    await p.type('[name="email"]', ADMIN);
    await p.type('[name="password"]', CLAVE_ADMIN);
    await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

    // Descarga un Excel y lo devuelve leído, fila a fila.
    const bajar = async (url) => {
        const base64 = await p.evaluate(async (u) => {
            const r = await fetch(u, { credentials: 'same-origin' });
            const bytes = new Uint8Array(await r.arrayBuffer());
            let s = '';
            for (const b of bytes) s += String.fromCharCode(b);
            return btoa(s);
        }, url);
        const archivo = join(tmpdir(), `inscripciones-${Date.now()}.xlsx`);
        writeFileSync(archivo, Buffer.from(base64, 'base64'));
        const leidas = JSON.parse(execFileSync(PHP, ['pruebas/leer-xlsx.php', archivo], { encoding: 'utf8' }));
        unlinkSync(archivo);
        return leidas;
    };
    const urlDelClic = () => clicDescarga.urlDelClic(p);
    const avisoCuentaVieja = () => clicDescarga.avisoCuentaVieja(p);

    const filas = await bajar(`${B}/admin/inscripciones/exportar/descargar?actividad=${actId}`);

    const [cab, ...datos] = filas;
    const col = (nombre) => cab.indexOf(nombre);
    const fila = (id) => datos.find((f) => String(f[0]) === String(id));

    t('0 · El filtro «Sin las canceladas»');

    // Mandaba `estado=activas` y la consulta lo buscaba tal cual: daba cero.
    await p.goto(`${B}/admin/inscripciones/exportar?estado=activas`, { waitUntil: 'networkidle2' });
    const activas = await p.evaluate(() => +document.querySelector('.card strong')?.textContent.trim());
    di('**cuenta las inscripciones sin cancelar**, no cero', activas === +sql("select count(*) from registrations where estado <> 'cancelado'"), `${activas}`);
    await p.goto(`${B}/admin/inscripciones/exportar?estado=cancelado`, { waitUntil: 'networkidle2' });
    di('y «Sólo las canceladas» sigue igual', await p.evaluate(() => +document.querySelector('.card strong')?.textContent.trim()) === +sql("select count(*) from registrations where estado = 'cancelado'"));

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

    t('4 · Marcar y descargar, sin pasar por «Ver cuántas son»');

    // El camino natural (07/10): se toca el formulario y se pulsa «Descargar en
    // Excel» directamente. Antes el enlace se armaba al cargar la página y no
    // se enteraba de nada de lo tocado después.
    await p.goto(`${B}/admin/inscripciones/exportar`, { waitUntil: 'networkidle2' });
    di('la casilla está y sale desmarcada', await p.$eval('input[name="respuestas"]', (i) => ! i.checked));
    const elegida = await p.select('select[name="actividad"]', String(actId));
    di('se elige la actividad en el formulario', elegida[0] === String(actId), `actividad ${actId}`);
    await p.click('input[name="respuestas"]');
    di('al cambiar un filtro, el número de arriba avisa que ya no vale', await avisoCuentaVieja());
    const hrefCon = await urlDelClic();
    di('**el clic lleva la casilla**', /[?&]respuestas=1(&|$)/.test(hrefCon ?? ''), hrefCon ?? '(sin clic)');
    di('**y el filtro recién elegido**', !! hrefCon && new URL(hrefCon).searchParams.get('actividad') === String(actId));
    const b64 = await p.evaluate(async (url) => {
        const r = await fetch(url, { credentials: 'same-origin' });
        const bytes = new Uint8Array(await r.arrayBuffer());
        let s = '';
        for (const b of bytes) s += String.fromCharCode(b);
        return btoa(s);
    }, hrefCon);
    const archivo2 = join(tmpdir(), `inscripciones-${Date.now()}.xlsx`);
    writeFileSync(archivo2, Buffer.from(b64, 'base64'));
    const [cab2, ...datos2] = JSON.parse(execFileSync(PHP, ['pruebas/leer-xlsx.php', archivo2], { encoding: 'utf8' }));
    unlinkSync(archivo2);
    const preguntas = ['¿Cómo evaluarías tu experiencia en esta actividad?', '¿Qué tan dispuesto(a) estarías',
        'Después de participar, ¿qué significa para ti el Patrimonio Social?', '¿Cómo te enteraste de esta actividad?'];
    di('**las mismas columnas de antes, en su orden**', JSON.stringify(cab2.slice(0, esperadas.length)) === JSON.stringify(esperadas));
    di('**y detrás, una por pregunta, con su texto**', cab2.length === esperadas.length + 4 && preguntas.every((q, i) => String(cab2[esperadas.length + i]).startsWith(q)), cab2.slice(esperadas.length).join(' | '));
    const f2 = datos2.find((r) => String(r[0]) === String(regSi));
    const resp = f2?.slice(esperadas.length).map(String);
    di('**las respuestas de quien evaluó**', JSON.stringify(resp) === JSON.stringify(['4', '5', 'Prueba de exportación', 'Redes sociales']), JSON.stringify(resp));
    di('«Respondió la evaluación» sigue igual', String(f2?.[cab2.indexOf('Respondió la evaluación')]) === 'Sí');
    const g2 = datos2.find((r) => String(r[0]) === String(regNo));
    di('vacías para quien no evaluó', !! g2 && g2.slice(esperadas.length).every((x) => String(x ?? '') === ''));
    di('**sólo las inscripciones de la actividad elegida**', datos2.length === +sql(`select count(*) from registrations where activity_id = ${actId}`), `${datos2.length} filas`);

    t('5 · Desmarcar y descargar');

    // Al revés: la página llega con la casilla marcada en la URL, se desmarca
    // y se descarga. Manda lo que se ve, no lo que traía la URL.
    await p.goto(`${B}/admin/inscripciones/exportar?actividad=${actId}&respuestas=1`, { waitUntil: 'networkidle2' });
    di('llega marcada', await p.$eval('input[name="respuestas"]', (i) => i.checked));
    await p.click('input[name="respuestas"]');
    di('la casilla sola no invalida el número (no cambia cuántas salen)', ! await avisoCuentaVieja());
    const hrefSin = await urlDelClic();
    di('**desmarcada, el clic ya no la lleva**', !! hrefSin && ! /[?&]respuestas=/.test(hrefSin), hrefSin ?? '(sin clic)');
    const [cab3] = hrefSin ? await bajar(hrefSin) : [];
    di('**y el Excel sale como siempre**', JSON.stringify(cab3) === JSON.stringify(esperadas), cab3?.join(' | '));

    t('6 · «Mostrar» cambiado sin contar');

    await p.goto(`${B}/admin/inscripciones/exportar`, { waitUntil: 'networkidle2' });
    await p.select('select[name="estado"]', 'cancelado');
    const hrefCanc = await urlDelClic();
    di('**el clic lleva «Sólo las canceladas»**', !! hrefCanc && new URL(hrefCanc).searchParams.get('estado') === 'cancelado', hrefCanc ?? '(sin clic)');
    const [, ...canceladas] = hrefCanc ? await bajar(hrefCanc) : [[]];
    di('y el Excel trae sólo las canceladas', canceladas.length === +sql("select count(*) from registrations where estado = 'cancelado'"), `${canceladas.length} filas`);
} finally {
    sql(`delete from activity_evaluations where nombre = '${EVAL}'`);
    await nav.close();
}

console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
