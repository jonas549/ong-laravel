// Qué pasa cuando un admin pide ajustes y el organizador corrige y reenvía.
//
// Es la pregunta que Natalia hizo dos veces en la reunión del 2026-09-01: ¿en
// qué estado queda? ¿Vuelve directo a «esperando revisión» o hay un paso
// intermedio? Se responde ejecutándolo, no leyendo el código: el estado lo
// tocan tres sitios distintos —el moderador, el guardado del organizador y el
// botón de reenviar— y lo que importa es cuál de ellos lo mueve de verdad.
//
//   node pruebas/moderacion-ajustes.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', '--default-character-set=utf8mb4', 'ong_laravel', '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(60)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });

const entrar = async (url, correo, clave) => {
    await p.goto(url, { waitUntil: 'networkidle2' });
    await p.type('input[name="email"]', correo);
    await p.type('input[name="password"]', clave);
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
};

const salir = async () => {
    const salio = await p.evaluate(() => {
        const f = document.querySelector('form[action*="logout"]');
        if (! f) return false;
        f.submit();
        return true;
    });
    if (salio) await p.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
};

/* La actividad del organizador de pruebas, y su estado original. */
const id = sql(`SELECT a.id FROM activities a
    JOIN organizations o ON o.id = a.organization_id
    JOIN users u ON u.id = o.user_id
    WHERE u.email = 'organizador@ong-laravel.test' AND a.deleted_at IS NULL
    ORDER BY a.id LIMIT 1`);

if (! id) {
    console.log('No hay ninguna actividad del organizador de pruebas. Corre `php artisan dps:instalar`.');
    await nav.close();
    process.exit(1);
}

const estado = () => sql(`SELECT estado FROM activities WHERE id=${id}`);
const nota = () => sql(`SELECT COALESCE(observaciones_revision,'') FROM activities WHERE id=${id}`);
const original = { estado: estado(), publicada: sql(`SELECT COALESCE(published_at,'') FROM activities WHERE id=${id}`) };

try {
    /* ══════════════════════════════════════════════════════════════ */
    t('El admin pide ajustes');

    sql(`UPDATE activities SET estado='revision' WHERE id=${id}`);

    await entrar(`${B}/admin/login`, 'admin@ong-laravel.test', 'admin1234');
    await p.goto(`${B}/admin/actividades/${id}`, { waitUntil: 'networkidle2' });

    const hayFormulario = await p.evaluate(() => !! document.querySelector('form[action*="/ajustes"]'));
    di('la ficha ofrece «pedir ajustes»', hayFormulario);

    await p.evaluate(() => {
        const f = document.querySelector('form[action*="/ajustes"]');
        const campo = f.querySelector('[name="comentario"]');
        campo.value = 'Falta detallar el punto de encuentro.';
        f.submit();
    });
    await p.waitForNavigation({ waitUntil: 'networkidle2' });

    di('queda en «ajustes»', estado() === 'ajustes', estado());
    di('y guarda la observación del admin', nota() === 'Falta detallar el punto de encuentro.', nota());

    await salir();

    /* ══════════════════════════════════════════════════════════════ */
    t('El organizador corrige y guarda');

    await entrar(`${B}/mi-cuenta/login`, 'organizador@ong-laravel.test', 'organizador1234');
    await p.goto(`${B}/mi-cuenta/actividades/${id}/editar`, { waitUntil: 'networkidle2' });
    await p.waitForFunction(() => window.Alpine !== undefined);

    const pantalla = await p.evaluate(() => document.body.innerText);
    di('la pantalla le dice que necesita ajustes', /Necesitamos algunos ajustes/i.test(pantalla));
    di('y le enseña la observación del admin', /punto de encuentro/i.test(pantalla));

    // Guardar sin más, que es lo que hace cualquiera después de corregir.
    await p.evaluate(() => {
        document.querySelector('input[name="titulo"]').value += ' ';
        [...document.querySelectorAll('button[type=submit]')]
            .find((b) => b.textContent.includes('Actualizar')).click();
    });
    await p.waitForNavigation({ waitUntil: 'networkidle2' });

    di('se guarda', p.url().includes('/mi-cuenta/actividades'), p.url());

    /*
     * ESTA es la respuesta a la pregunta: guardar NO mueve el estado. La
     * actividad sigue en «Necesitamos algunos ajustes» hasta que la persona
     * pulse el botón de reenviar.
     */
    di('guardar NO la devuelve a revisión: sigue en «ajustes»', estado() === 'ajustes', estado());

    /* ══════════════════════════════════════════════════════════════ */
    t('El organizador la reenvía');

    await p.goto(`${B}/mi-cuenta/actividades/${id}/editar`, { waitUntil: 'networkidle2' });

    const hayBoton = await p.evaluate(() => !! [...document.querySelectorAll('button[type=submit]')]
        .find((b) => b.textContent.includes('Enviar a revisión')));
    di('hay un botón «Enviar a revisión»', hayBoton);

    await p.evaluate(() => [...document.querySelectorAll('button[type=submit]')]
        .find((b) => b.textContent.includes('Enviar a revisión')).click());
    await p.waitForNavigation({ waitUntil: 'networkidle2' });
    await esperar(300);

    di('vuelve DIRECTA a «revisión», sin estado intermedio', estado() === 'revision', estado());

    const tras = await p.evaluate(() => document.body.innerText);
    di('y la pantalla lo dice', /Estamos revisando tu actividad|revisión/i.test(tras));

    /* Y queda anotado quién movió qué, para el historial. */
    const saltos = sql(`SELECT CONCAT(de_estado,'->',a_estado) FROM activity_status_logs
        WHERE activity_id=${id} ORDER BY id DESC LIMIT 2`)
        .split('\n').map((l) => l.trim()).reverse();
    di('el historial guarda los dos saltos',
        saltos.join(' , ') === 'revision->ajustes , ajustes->revision', saltos.join(' , '));

    di('los estados posibles siguen siendo cinco, sin uno intermedio nuevo',
        Number(sql("SELECT COUNT(DISTINCT a_estado) FROM activity_status_logs")) <= 5);
} finally {
    sql(`UPDATE activities SET estado='${original.estado}', observaciones_revision=NULL WHERE id=${id}`);
    sql(`UPDATE activities SET titulo=TRIM(titulo) WHERE id=${id}`);
    console.log(`  (actividad ${id} devuelta a '${original.estado}')`);
}

console.log('');
console.log(`${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal === 0 ? 0 : 1);
