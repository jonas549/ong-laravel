// Los dos puntos de la reunión del 2026-09-01 que había que proponer antes:
// el enlace de calendario en los correos y la aprobación automática.
//
//   node pruebas/calendario-y-aprobacion.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', '--default-character-set=utf8mb4', 'ong_laravel', '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();

const artisan = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php],
    { encoding: 'utf8', cwd: 'C:/laragon/www/ong-laravel' }).trim();

/*
 * Los ajustes se guardan en caché (`Setting::todos()` usa rememberForever, y el
 * modelo la olvida al guardarse). Un UPDATE a pelo se salta esa invalidación,
 * así que hay que olvidarla a mano o el cambio no se ve.
 */
const ajuste = (clave, valor) => {
    sql(`UPDATE settings SET valor='${valor}' WHERE clave='${clave}'`);
    artisan("Illuminate\\Support\\Facades\\Cache::forget(App\\Models\\Setting::CACHE_KEY);");
};

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1440, height: 1000 });

/*
 * El .ics se pide con fetch y no con `page.goto`.
 *
 * La respuesta lleva `Content-Disposition: attachment`, que es justo lo que
 * tiene que llevar: Chrome la trata como una descarga y aborta la navegación
 * con ERR_ABORTED. Con fetch se leen el estado, las cabeceras y el cuerpo sin
 * pedirle al navegador que la pinte.
 */
const pedir = (url) => p.evaluate(async (u) => {
    const r = await fetch(u);
    return {
        estado: r.status,
        tipo: r.headers.get('content-type') ?? '',
        disposicion: r.headers.get('content-disposition') ?? '',
        texto: await r.text(),
    };
}, url);

const entrar = async (url, correo, clave) => {
    await p.goto(url, { waitUntil: 'networkidle2' });
    await p.evaluate(() => {
        document.querySelector('input[name="email"]').value = '';
        document.querySelector('input[name="password"]').value = '';
    });
    await p.type('input[name="email"]', correo);
    await p.type('input[name="password"]', clave);
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

    /*
     * Si no entró, no tiene sentido seguir: todo lo que venga después fallara
     * por el mismo motivo y con mensajes que no dicen cuál es. `clave-admin.mjs`
     * le cambia la contrasena al organizador, asi que basta con haberlo corrido
     * antes para llegar aqui sin poder entrar.
     */
    if (p.url().includes('/login')) {
        const motivo = await p.evaluate(() => document.querySelector('.field-error')?.textContent?.trim() ?? '');
        console.log('');
        console.log(`  No se pudo entrar como ${correo}: ${motivo}`);
        console.log('  Prueba con: php artisan db:seed --class=UserSeeder');
        await nav.close();
        process.exit(1);
    }
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

/* ══════════════════════════════════════════════════════════════════ */
t('El .ics que se sirve por su ruta');

const slug = sql("SELECT slug FROM activities WHERE estado='publicada' AND sin_fecha_definida=0 AND fecha_inicio IS NOT NULL ORDER BY id LIMIT 1");
di('hay una actividad publicada con fecha', !! slug, slug);

await p.goto(`${B}/actividades/${slug}`, { waitUntil: 'networkidle2' });

const resp = await pedir(`${B}/actividades/${slug}/calendario.ics`);
const ics = resp.texto;

di('la ruta responde 200', resp.estado === 200, String(resp.estado));
di('se sirve como calendario', resp.tipo.includes('text/calendar'), resp.tipo);
di('se descarga como archivo .ics', resp.disposicion.includes('.ics'), resp.disposicion);

di('es un calendario bien formado',
    ics.startsWith('BEGIN:VCALENDAR') && ics.trimEnd().endsWith('END:VCALENDAR'));
di('trae un solo evento', (ics.match(/BEGIN:VEVENT/g) ?? []).length === 1);
di('lleva identificador estable', /UID:actividad-\d+@/.test(ics));
di('lleva el título de la actividad', /SUMMARY:.+/.test(ics));
di('y el enlace a la ficha', ics.includes('/actividades/'));

// El RFC manda CRLF y 75 octetos por línea; hay lectores que se atragantan.
di('las líneas van con CRLF', ics.includes('\r\n') && ! /[^\r]\n/.test(ics));
di('ninguna línea pasa de 75 octetos',
    ics.split('\r\n').every((l) => Buffer.byteLength(l, 'utf8') <= 75),
    'la más larga: ' + Math.max(...ics.split('\r\n').map((l) => Buffer.byteLength(l, 'utf8'))));

// Y las tildes sobreviven al plegado, que es donde se rompen.
di('las tildes no se parten al plegar', ! ics.includes('\uFFFD'));

/* ══════════════════════════════════════════════════════════════════ */
t('Una actividad sin fecha no ofrece calendario');

const permanente = sql("SELECT slug FROM activities WHERE estado='publicada' ORDER BY id LIMIT 1");
sql(`UPDATE activities SET sin_fecha_definida=1 WHERE slug='${permanente}'`);

const resp2 = await pedir(`${B}/actividades/${permanente}/calendario.ics`);
di('devuelve 404 en vez de un calendario vacío', resp2.estado === 404, String(resp2.estado));

sql(`UPDATE activities SET sin_fecha_definida=0 WHERE slug='${permanente}'`);

/* ══════════════════════════════════════════════════════════════════ */
t('Una actividad sin publicar tampoco');

const borrador = sql("SELECT slug FROM activities WHERE estado='borrador' LIMIT 1");
if (borrador) {
    const resp3 = await pedir(`${B}/actividades/${borrador}/calendario.ics`);
    di('404, igual que su ficha', resp3.estado === 404, String(resp3.estado));
} else {
    di('404, igual que su ficha', true, '(no hay borradores para probarlo)');
}

/* ══════════════════════════════════════════════════════════════════ */
t('El correo lleva los dos enlaces');

await entrar(`${B}/admin/login`, 'admin@ong-laravel.test', 'admin1234');

const idPlantilla = sql("SELECT id FROM email_templates WHERE clave='inscripcion_confirmada'");
await p.goto(`${B}/admin/plantillas/${idPlantilla}`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);

const pantalla = await p.evaluate(() => document.body.innerText);
di('el catálogo ofrece el marcador de calendario', /bloque_calendario/.test(pantalla));

/*
 * La plantilla se sembró antes de que existiera el marcador, así que el aviso
 * de «hay marcadores nuevos» tiene que salir. Es lo que pidió Jonas: avisar,
 * no meterlo por la fuerza en un texto que escribió alguien.
 */
di('avisa de que hay un marcador nuevo', /marcador nuevo|marcadores nuevos/i.test(pantalla));
di('y NO lo ha metido solo en el cuerpo',
    ! sql(`SELECT cuerpo_html FROM email_templates WHERE id=${idPlantilla}`).includes('bloque_calendario'));

// La vista previa lo tiene que pintar como HTML, no como texto suelto.
/* Igual que lo hace el botón «Vista previa» de la propia pantalla. */
const verPrevia = (base, id) => p.evaluate(async (base, id) => {
    const cuerpo = new FormData();
    cuerpo.append('asunto', document.querySelector('[name=asunto]').value);
    cuerpo.append('cuerpo_html', document.querySelector('[name=cuerpo_html]').value);
    cuerpo.append('_token', document.querySelector('meta[name=csrf-token]')?.content ?? '');

    const r = await fetch(`${base}/admin/plantillas/${id}/previa`, { method: 'POST', body: cuerpo });

    return r.ok ? (await r.json()).html : `(HTTP ${r.status})`;
}, base, id);

const previa = await verPrevia(B, idPlantilla);

di('la vista previa responde', ! previa.startsWith('(HTTP'), previa.slice(0, 20));

/* Ahora con el marcador puesto, para ver que se sustituye por HTML de verdad. */
sql(`UPDATE email_templates SET cuerpo_html = CONCAT(cuerpo_html, '{{ bloque_calendario }}') WHERE id=${idPlantilla}`);

await p.goto(`${B}/admin/plantillas/${idPlantilla}`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);

const previa2 = await verPrevia(B, idPlantilla);

di('el bloque se pinta como HTML, no como texto', /<a [^>]*>Google Calendar<\/a>/.test(previa2),
    previa2.includes('&lt;a') ? 'salió escapado' : previa2.slice(0, 20));
di('y ya no avisa de que sea nuevo',
    ! /marcador nuevo|marcadores nuevos/i.test(await p.evaluate(() => document.body.innerText)));

sql(`UPDATE email_templates SET cuerpo_html = REPLACE(cuerpo_html, '{{ bloque_calendario }}', '') WHERE id=${idPlantilla}`);

/* ══════════════════════════════════════════════════════════════════ */
t('Aprobación automática: la primera se revisa, la segunda no');

const idOrg = sql("SELECT o.id FROM organizations o JOIN users u ON u.id=o.user_id WHERE u.email='organizador@ong-laravel.test'");
const guardado = {
    publicadas: sql(`SELECT GROUP_CONCAT(id) FROM activities WHERE organization_id=${idOrg} AND published_at IS NOT NULL`),
    ajustes: sql(`SELECT GROUP_CONCAT(id) FROM activities WHERE organization_id=${idOrg} AND estado='ajustes'`),
};

/** Qué decide el servicio ahora mismo, preguntándoselo a él. */
const decide = () => execFileSync('php', ['artisan', 'tinker', '--execute',
    `echo app(\\App\\Services\\AprobacionAutomatica::class)->estadoAlEnviar(\\App\\Models\\Organization::find(${idOrg}))[0];`],
    { encoding: 'utf8', cwd: 'C:/laragon/www/ong-laravel' }).trim();

try {
    // Sin nada publicado y sin ajustes abiertos: es su primera vez.
    sql(`UPDATE activities SET published_at=NULL, estado='borrador' WHERE organization_id=${idOrg}`);
    di('sin nada publicado, va a revisión', decide() === 'revision', decide());

    // Con una publicada: pasa directa.
    sql(`UPDATE activities SET published_at=NOW(), estado='publicada' WHERE organization_id=${idOrg} LIMIT 1`);
    di('con una publicada, se publica sola', decide() === 'publicada', decide());

    // Cancelada: sigue contando, porque la ONG llegó a aprobarla.
    sql(`UPDATE activities SET estado='cancelada' WHERE organization_id=${idOrg} AND published_at IS NOT NULL LIMIT 1`);
    di('una cancelada sigue contando como «ya publicó»', decide() === 'publicada', decide());
    sql(`UPDATE activities SET estado='publicada' WHERE organization_id=${idOrg} AND published_at IS NOT NULL LIMIT 1`);

    // Un «necesita ajustes» abierto lo pausa.
    const otra = sql(`SELECT id FROM activities WHERE organization_id=${idOrg} AND published_at IS NULL LIMIT 1`);
    if (otra) {
        sql(`UPDATE activities SET estado='ajustes' WHERE id=${otra}`);
        di('un «necesita ajustes» abierto lo pausa', decide() === 'revision', decide());
        sql(`UPDATE activities SET estado='borrador' WHERE id=${otra}`);
        di('y al resolverse se reanuda solo', decide() === 'publicada', decide());
    }

    // El interruptor por organización.
    sql(`UPDATE organizations SET requiere_revision=1 WHERE id=${idOrg}`);
    di('el interruptor de la organización manda', decide() === 'revision', decide());
    sql(`UPDATE organizations SET requiere_revision=0 WHERE id=${idOrg}`);

    // Y el general.
    ajuste('aprobacion_automatica', '0');
    di('el interruptor general lo apaga todo', decide() === 'revision', decide());
    ajuste('aprobacion_automatica', '1');
    di('y al encenderlo vuelve', decide() === 'publicada', decide());

    /* ── Ahora de verdad, por la pantalla ── */
    t('Reenviar desde «Mi cuenta»');

    await salir();
    await entrar(`${B}/mi-cuenta/login`, 'organizador@ong-laravel.test', 'organizador1234');

    const borradorId = sql(`SELECT id FROM activities WHERE organization_id=${idOrg} AND estado='borrador' LIMIT 1`);

    if (borradorId) {
        await p.goto(`${B}/mi-cuenta/actividades/${borradorId}/editar`, { waitUntil: 'networkidle2' });

        const hayBoton = await p.evaluate(() => !! [...document.querySelectorAll('button[type=submit]')]
            .find((b) => b.textContent.includes('Enviar a revisión')));
        di('el borrador ofrece «Enviar a revisión»', hayBoton, hayBoton ? '' : p.url());

        await p.evaluate(() => [...document.querySelectorAll('button[type=submit]')]
            .find((b) => b.textContent.includes('Enviar a revisión')).click());
        await p.waitForNavigation({ waitUntil: 'networkidle2' });
        await esperar(300);

        di('un borrador de una organización conocida se publica solo',
            sql(`SELECT estado FROM activities WHERE id=${borradorId}`) === 'publicada',
            sql(`SELECT estado FROM activities WHERE id=${borradorId}`));
        di('y queda marcada como publicada sin revisar',
            sql(`SELECT publicada_automaticamente FROM activities WHERE id=${borradorId}`) === '1');
        di('con el motivo escrito en el historial',
            sql(`SELECT comentario FROM activity_status_logs WHERE activity_id=${borradorId} ORDER BY id DESC LIMIT 1`)
                .includes('automáticamente'));

        // Y una que vuelve de ajustes NO se auto-aprueba, por muchas que tenga.
        sql(`UPDATE activities SET estado='ajustes', publicada_automaticamente=0 WHERE id=${borradorId}`);
        await p.goto(`${B}/mi-cuenta/actividades/${borradorId}/editar`, { waitUntil: 'networkidle2' });
        await p.evaluate(() => [...document.querySelectorAll('button[type=submit]')]
            .find((b) => b.textContent.includes('Enviar a revisión')).click());
        await p.waitForNavigation({ waitUntil: 'networkidle2' });
        await esperar(300);

        di('la que vuelve de ajustes SIEMPRE pasa por revisión',
            sql(`SELECT estado FROM activities WHERE id=${borradorId}`) === 'revision',
            sql(`SELECT estado FROM activities WHERE id=${borradorId}`));
    } else {
        di('hay un borrador con el que probarlo', false);
    }

    /* ── La pantalla del paso 5 dice la verdad ── */
    t('La pantalla de «enviado» sigue el estado real');

    const publicadaId = sql(`SELECT id FROM activities WHERE organization_id=${idOrg} AND estado='publicada' LIMIT 1`);
    const publicadaSlug = sql(`SELECT slug FROM activities WHERE id=${publicadaId}`);

    await p.goto(`${B}/publicar-actividad/${publicadaSlug}/listo`, { waitUntil: 'networkidle2' });
    const textoPublicada = await p.evaluate(() => document.body.innerText);

    di('con la actividad publicada NO promete una revisión',
        ! /Revisaremos la información/i.test(textoPublicada));
    di('y dice que ya está en el calendario', /ya está publicada en el calendario/i.test(textoPublicada));

    sql(`UPDATE activities SET estado='revision' WHERE id=${publicadaId}`);
    await p.goto(`${B}/publicar-actividad/${publicadaSlug}/listo`, { waitUntil: 'networkidle2' });
    const textoRevision = await p.evaluate(() => document.body.innerText);

    di('en revisión, sí lo dice', /Revisaremos la información/i.test(textoRevision));
    di('y la etiqueta sigue el estado', /Estamos revisando tu actividad/i.test(textoRevision));

    sql(`UPDATE activities SET estado='publicada' WHERE id=${publicadaId}`);

    /* ── El panel puede repasar lo que se publicó solo ── */
    t('El panel enseña lo publicado sin revisar');

    sql(`UPDATE activities SET publicada_automaticamente=1 WHERE id=${publicadaId}`);

    await salir();
    await entrar(`${B}/admin/login`, 'admin@ong-laravel.test', 'admin1234');
    await p.goto(`${B}/admin/actividades`, { waitUntil: 'networkidle2' });

    const conPestania = await p.evaluate(() => document.body.innerText);
    di('hay una pestaña para encontrarlas', /Publicadas solas/i.test(conPestania));
    di('y la fila lleva su marca', /Sin revisar/i.test(conPestania));

    await p.goto(`${B}/admin/actividades?auto=1`, { waitUntil: 'networkidle2' });
    const filas = await p.$$eval('table.tabla tbody tr', (n) => n.length);
    const cuantas = Number(sql('SELECT COUNT(*) FROM activities WHERE publicada_automaticamente=1'));
    di('el filtro enseña exactamente ésas', filas === cuantas, `${filas} filas, ${cuantas} en la base`);

    /*
     * La ficha de la organización, con su interruptor.
     *
     * Esta comprobación existe porque la primera versión daba un 500 ahí y no
     * lo vio nadie: ninguna prueba abría esa pantalla, y el fallo era una
     * confusión entre dos componentes que se llaman parecido —`panel.casilla`
     * es la casilla de una fila de tabla, no la de un formulario—.
     */
    await p.goto(`${B}/admin/organizaciones/${idOrg}/editar`, { waitUntil: 'networkidle2' });

    di('la ficha de la organización carga', ! /Internal Server Error|ErrorException/i.test(
        await p.evaluate(() => document.body.innerText)));
    di('y tiene el interruptor de revisión',
        await p.$$eval('[name="requiere_revision"]', (n) => n.length) > 0);

    // Y se guarda de verdad, que es lo que importa de una casilla.
    await p.evaluate(() => {
        document.querySelector('input[type=checkbox][name="requiere_revision"]').checked = true;
        document.querySelector('form[action*="organizaciones"] button[type=submit]').click();
    });
    await p.waitForNavigation({ waitUntil: 'networkidle2' });

    di('marcarlo se guarda', sql(`SELECT requiere_revision FROM organizations WHERE id=${idOrg}`) === '1');
    di('y con eso vuelve a revisión', decide() === 'revision', decide());

    await p.evaluate(() => {
        document.querySelector('input[type=checkbox][name="requiere_revision"]').checked = false;
        document.querySelector('form[action*="organizaciones"] button[type=submit]').click();
    });
    await p.waitForNavigation({ waitUntil: 'networkidle2' });

    di('y desmarcarlo también', sql(`SELECT requiere_revision FROM organizations WHERE id=${idOrg}`) === '0');
} finally {
    sql(`UPDATE activities SET published_at=NULL, estado='borrador', publicada_automaticamente=0 WHERE organization_id=${idOrg}`);
    if (guardado.publicadas) {
        sql(`UPDATE activities SET published_at=NOW(), estado='publicada' WHERE id IN (${guardado.publicadas})`);
    }
    if (guardado.ajustes) {
        sql(`UPDATE activities SET estado='ajustes' WHERE id IN (${guardado.ajustes})`);
    }
    sql(`UPDATE organizations SET requiere_revision=0 WHERE id=${idOrg}`);
    ajuste('aprobacion_automatica', '1');
    console.log('  (actividades y ajustes de la organización de pruebas devueltos a su sitio)');
}

/* ══════════════════════════════════════════════════════════════════ */
t('Sin errores en la consola');

di('ningún error de JavaScript', errores.length === 0, errores.slice(0, 2).join(' | '));

console.log('');
console.log(`${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal === 0 ? 0 : 1);
