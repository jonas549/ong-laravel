// El aviso de errores del wizard y del formulario de inscripción, en Chrome.
//
// Por qué en Chrome y no por HTTP: lo que se arregla aquí es que el usuario NO
// VEÍA el error. Un script por HTTP lee el HTML que vuelve y encuentra el
// `<span class="field-error">` perfectamente, porque no tiene pantalla ni
// scroll; los 74 casos por HTTP del bloque F pasaban también con el menú roto.
// Aquí hay que medir dónde queda la caja respecto de la ventana, si el envío
// llegó a salir, y qué pasa al escribir en un campo. Eso son píxeles y eventos.
//
//   node pruebas/wizard-errores.mjs
import puppeteer from 'puppeteer-core';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(60)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
await p.setViewport({ width: 1440, height: 900 });

/** ¿Está esta caja dentro de la ventana ahora mismo? Es LA pregunta del bug. */
const enPantalla = (sel) => p.evaluate((s) => {
    const el = [...document.querySelectorAll(s)].find((e) => e.offsetParent !== null);
    if (! el) return null;
    const r = el.getBoundingClientRect();
    return r.top >= -4 && r.top < innerHeight && r.height > 0;
}, sel);

const textoResumen = () => p.evaluate(() => {
    const el = [...document.querySelectorAll('[data-resumen-errores]')].find((e) => e.offsetParent !== null);
    return el ? el.innerText.replace(/\s+/g, ' ').trim() : null;
});

const paso = () => p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso);
const irAPaso = async (n) => {
    await p.evaluate((n) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = n; }, n);
    // x-show se aplica en el ciclo de Alpine: escribir antes de que el paso
    // este en pantalla pierde lo tecleado sin dar ningun error.
    await p.waitForFunction((n) => document.querySelector(`[data-paso="${n}"]`)?.offsetParent !== null, {}, n);
};

/** Lo que la guia dice que falta, tal cual, para no adivinar. */
const faltan = () => p.evaluate(
    () => Alpine.$data(document.querySelector('[x-data^="wizard"]')).camposQueFaltan().map((e) => e.campo));

const abrirWizard = async () => {
    await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
    await p.waitForFunction(() => window.Alpine !== undefined);
};

/* ══════════════════════════════════════════════════════════════════ */
t('El wizard carga y la guía se monta');

await abrirWizard();

di('la pantalla carga', p.url().endsWith('/publicar-actividad'));
di('arranca en el paso 1', await paso() === 1);
di('no hay resumen visible sin errores', await textoResumen() === null);

const cajas = await p.$$eval('[data-campo]', (n) => n.length);
const obligatorios = await p.$$eval('[data-campo][data-obligatorio]', (n) => n.map((e) => e.dataset.campo));
di('todas las cajas de campo están marcadas', cajas >= 16, `${cajas} cajas`);
di('los tres grupos de chips son obligatorios',
    ['temas', 'publicos', 'caracteristicas'].every((c) => obligatorios.includes(c)),
    obligatorios.join(', '));

// Y lo dicen con la palabra, no sólo con el asterisco: es lo que fallaba.
const conMarca = await p.$$eval('[data-campo] .marca-obligatoria',
    (n) => n.map((e) => e.closest('[data-campo]').dataset.campo));
di('y lo dicen con la palabra «Obligatorio»',
    ['temas', 'publicos', 'caracteristicas'].every((c) => conMarca.includes(c)),
    conMarca.join(', '));

/* ══════════════════════════════════════════════════════════════════ */
t('Enviar el paso 4 sin el público beneficiado — el caso del reporte');

await irAPaso(3);
await p.type('input[name="org_nombre"]', 'Fundación de Prueba');
await p.type('input[name="email"]', `prueba${Date.now()}@ong-laravel.test`);
await p.type('input[name="password"]', 'clave-larga-1234');
await p.type('input[name="password_confirmation"]', 'clave-larga-1234');

await irAPaso(4);
await p.type('input[name="titulo"]', 'Jornada comunitaria de prueba');
await p.type('textarea[name="descripcion"]', 'Una descripción cualquiera para la prueba.');
await p.type('input[name="fecha_inicio"]', '04122026');
// Las comunas las pinta un x-for, asi que el primer hijo del select es el
// <template>: hay que leer los valores, no contar posiciones.
const valores = (sel) => p.$$eval(sel + ' option', (o) => o.map((x) => x.value).filter(Boolean));
await p.select('select[name="region_id"]', (await valores('select[name="region_id"]'))[0]);
await p.waitForFunction(() => [...document.querySelectorAll('select[name="commune_id"] option')].some((o) => o.value));
await p.select('select[name="commune_id"]', (await valores('select[name="commune_id"]'))[0]);
await p.type('input[name="direccion"]', 'Calle Falsa 123');

// Temas y características sí; público NO, que es lo que le pasó al cliente.
await p.evaluate(() => {
    const grupo = (nombre) => document.querySelector(`[data-campo="${nombre}"]`);
    grupo('temas').querySelector('button.chip').click();
    grupo('caracteristicas').querySelector('button.chip').click();
});

const pendientes = await faltan();
di('el formulario quedo completo salvo el publico',
    pendientes.length === 1 && pendientes[0] === 'publicos', pendientes.join(', '));

// Bajar del todo, como hace cualquiera antes de darle a enviar.
await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await new Promise((r) => setTimeout(r, 300));

const urlAntes = p.url();
await p.click('button[type="submit"]');
await new Promise((r) => setTimeout(r, 700));

di('el envío NO salió', p.url() === urlAntes);

const resumen = await textoResumen();
di('aparece el resumen', resumen !== null);
di('dice cuántos faltan', /Falta 1 campo/.test(resumen ?? ''), JSON.stringify(resumen));
di('nombra el campo en castellano', /Público beneficiado/.test(resumen ?? ''));
di('el resumen quedó EN PANTALLA', await enPantalla('[data-resumen-errores]') === true);

const marcadas = await p.$$eval('.campo-fallido', (n) => n.map((e) => e.dataset.campo));
di('la caja del grupo de chips queda marcada', marcadas.includes('publicos'), marcadas.join(', '));
di('no marca nada que esté completo', marcadas.length === 1);

/* ══════════════════════════════════════════════════════════════════ */
t('El renglón del resumen salta a su campo');

await p.evaluate(() => document.querySelector('[data-resumen-errores] .resumen-errores-salto').click());
await new Promise((r) => setTimeout(r, 700));

di('el campo queda en pantalla', await enPantalla('[data-campo="publicos"]') === true);
di('y el resumen dejo de tapar la pagina entera',
    await p.$$eval('[data-resumen-errores]', (n) => n.every((e) => e.getBoundingClientRect().height < 400)));
di('el foco cayó en el primer chip', await p.evaluate(
    () => document.activeElement?.closest('[data-campo]')?.dataset.campo) === 'publicos');

/* ══════════════════════════════════════════════════════════════════ */
t('Al rellenarlo, la marca se va sola');

const marcaAntes = await p.$eval('[data-campo="publicos"] .marca-obligatoria', (e) => e.textContent.trim());
await p.evaluate(() => document.querySelector('[data-campo="publicos"] button.chip').click());
await new Promise((r) => setTimeout(r, 300));

const marcaDespues = await p.$eval('[data-campo="publicos"] .marca-obligatoria', (e) => e.textContent.trim());
di('la marca decía «Obligatorio»', marcaAntes === 'Obligatorio', marcaAntes);
di('y pasa a «Listo» al elegir', /Listo/.test(marcaDespues), marcaDespues);
di('la caja deja de estar marcada', await p.$$eval('.campo-fallido', (n) => n.length) === 0);
di('el resumen desaparece', await textoResumen() === null);

/* ══════════════════════════════════════════════════════════════════ */
t('El campo de fecha dice en qué orden van los números');

const fecha = await p.$eval('input[name="fecha_inicio"]', (e) => ({
    tipo: e.type, hueco: e.placeholder, valor: e.value,
}));
di('sigue siendo texto, no type=date', fecha.tipo === 'text');
di('el hueco enseña el formato', fecha.hueco === 'dd / mm / aaaa', fecha.hueco);
di('la máscara puso las barras al teclear', fecha.valor === '04 / 12 / 2026', fecha.valor);
di('hay ayuda escrita con un ejemplo',
    await p.$eval('[data-campo="fecha_inicio"]', (e) => /Ej\. 04 \/ 12 \/ 2026/.test(e.innerText)));
di('y la ayuda cabe en una linea de su columna',
    await p.$eval('[data-campo="fecha_inicio"] .helper', (e) => e.getBoundingClientRect().height < 24),
    await p.$eval('[data-campo="fecha_inicio"] .helper', (e) => Math.round(e.getBoundingClientRect().height) + 'px'));
di('hay botón de calendario', await p.$$eval('[data-campo="fecha_inicio"] .campo-fecha-boton', (n) => n.length) === 1);

/*
 * Pegar.
 *
 * Lo que se rompia con type=date era que el campo NO ADMITE pegar; que siga
 * siendo de texto es la prueba de que eso no ha vuelto, y ya esta comprobado
 * dos lineas mas arriba. Lo que queda por ver es lo nuevo: que lo pegado se
 * ordene solo aunque venga en otro formato.
 *
 * El Ctrl+V de verdad con el portapapeles del sistema no se puede hacer aqui:
 * Chrome sin cabeza deniega writeText por no tener el foco de la ventana. Se
 * dispara el mismo evento que dispara el navegador al pegar —input con
 * inputType insertFromPaste—, que es justo por donde entra el manejador.
 */
const pegar = async (texto) => {
    await p.evaluate((v) => {
        const campo = document.querySelector('input[name="fecha_inicio"]');
        campo.value = v;
        campo.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertFromPaste' }));
    }, texto);
    await new Promise((r) => setTimeout(r, 200));
    return p.$eval('input[name="fecha_inicio"]', (e) => e.value);
};

di('pegado en ISO, se ordena solo', await pegar('2026-12-04') === '04 / 12 / 2026');
di('pegado con guiones, se ordena solo', await pegar('4-12-2026') === '04 / 12 / 2026');
di('pegado ya en formato chileno, se respeta', await pegar('04/12/2026') === '04 / 12 / 2026');
di('tecleado a mano en ISO, tambien se arregla al salir',
    await p.evaluate(() => {
        const c = Alpine.$data(document.querySelector('[data-campo="fecha_inicio"]'));
        c.entrada.value = '20 / 26 / 1204';
        c.normalizar();
        return c.entrada.value;
    }) === '04 / 12 / 2026');

// Y el calendario escribe en el campo de texto, no lo sustituye.
await p.evaluate(() => {
    const cal = document.querySelector('[data-campo="fecha_inicio"] .campo-fecha-nativo');
    cal.value = '2027-03-09';
    cal.dispatchEvent(new Event('change', { bubbles: true }));
});
di('el calendario escribe en el campo de texto',
    await p.$eval('input[name="fecha_inicio"]', (e) => e.value) === '09 / 03 / 2027');

/* ══════════════════════════════════════════════════════════════════ */
t('«Disponible de forma permanente» no pide la fecha');

await p.evaluate(() => document.querySelector('input[name="sin_fecha_definida"]').click());
await new Promise((r) => setTimeout(r, 250));
await p.evaluate(() => { document.querySelector('input[name="fecha_inicio"]').value = ''; });

const faltanSinFecha = await p.evaluate(
    () => Alpine.$data(document.querySelector('[x-data^="wizard"]')).camposQueFaltan().map((e) => e.campo));
di('con la fecha deshabilitada no la exige', ! faltanSinFecha.includes('fecha_inicio'), faltanSinFecha.join(', '));

await p.evaluate(() => document.querySelector('input[name="sin_fecha_definida"]').click());
await new Promise((r) => setTimeout(r, 250));

/* ══════════════════════════════════════════════════════════════════ */
t('«¿Cuál?» sólo se exige si el público incluye «Otros»');

const pideCual = await p.evaluate(
    () => Alpine.$data(document.querySelector('[x-data^="wizard"]')).camposQueFaltan().map((e) => e.campo));
di('oculto, no se exige', ! pideCual.includes('publico_otro'), pideCual.join(', '));

const hayOtros = await p.evaluate(() => {
    const b = [...document.querySelectorAll('[data-campo="publicos"] button.chip')]
        .find((x) => x.textContent.trim() === 'Otros');
    if (b) b.click();
    return !! b;
});

if (hayOtros) {
    await new Promise((r) => setTimeout(r, 300));
    const pideAhora = await p.evaluate(
        () => Alpine.$data(document.querySelector('[x-data^="wizard"]')).camposQueFaltan().map((e) => e.campo));
    di('al marcar «Otros», sí se exige', pideAhora.includes('publico_otro'), pideAhora.join(', '));
    await p.evaluate(() => {
        [...document.querySelectorAll('[data-campo="publicos"] button.chip')]
            .find((x) => x.textContent.trim() === 'Otros').click();
    });
} else {
    di('al marcar «Otros», sí se exige', true, '(no hay término «Otros» en la base)');
}

/* ══════════════════════════════════════════════════════════════════ */
t('«Continuar →» del paso 3 no deja pasar con el nombre vacío');

await abrirWizard();
await irAPaso(3);
await p.evaluate(() => { document.querySelector('input[name="org_nombre"]').value = ''; });
// Acotado al paso 3: hay un «Continuar» en el 2 y otro en el 3, y sin esto
// se pulsaba el del 2, que no valida nada.
const continuar = () => p.evaluate(() => [...document.querySelectorAll('[data-paso="3"] button')]
    .find((b) => b.textContent.includes('Continuar')).click());

await continuar();
await new Promise((r) => setTimeout(r, 500));

di('se queda en el paso 3', await paso() === 3);
di('y dice qué falta', /Nombre de la organización/.test(await textoResumen() ?? ''));

await p.type('input[name="org_nombre"]', 'Fundación de Prueba');
await p.type('input[name="email"]', `prueba${Date.now()}@ong-laravel.test`);
await p.type('input[name="password"]', 'clave-larga-1234');
await p.type('input[name="password_confirmation"]', 'clave-larga-1234');
await continuar();
await new Promise((r) => setTimeout(r, 500));
di('con los datos puestos sí pasa al 4', await paso() === 4);

/* ══════════════════════════════════════════════════════════════════ */
t('Lo que rebota del SERVIDOR también se ve');

// Un POST a mano, saltándose la revisión previa: es el camino que sigue
// existiendo cuando falla algo que sólo el servidor sabe (un correo repetido,
// un formato) y el que se recorre sin JavaScript.
await abrirWizard();
await p.evaluate(() => {
    const f = document.querySelector('form[action*="publicar"]');
    f.setAttribute('x-on:submit', '');
    f.submit();
});
await p.waitForNavigation({ waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await new Promise((r) => setTimeout(r, 800));

const delServidor = await textoResumen();
di('el POST rechazado vuelve con resumen', delServidor !== null);
di('cuenta los campos que faltan', /Faltan \d+ campos/.test(delServidor ?? ''), JSON.stringify(delServidor?.slice(0, 90)));
di('el resumen quedó EN PANTALLA', await enPantalla('[data-resumen-errores]') === true);
di('vuelve al paso del primer error', [2, 3].includes(await paso()), `paso ${await paso()}`);
di('nombra los campos, no las claves', /Nombre de la organización/.test(delServidor ?? ''));
di('trae también el motivo de cada uno', /obligatorio|Elige|Marca|Indica/i.test(delServidor ?? ''));

/* ══════════════════════════════════════════════════════════════════ */
t('El formulario de inscripción público');

const slug = await p.evaluate(async (base) => {
    const html = await (await fetch(`${base}/actividades`)).text();
    return html.match(/\/actividades\/([a-z0-9-]+)"/)?.[1] ?? null;
}, B);

if (! slug) {
    di('hay una actividad publicada para probar', false, 'ninguna publicada');
} else {
    await p.goto(`${B}/actividades/${slug}`, { waitUntil: 'networkidle2' });
    await p.waitForFunction(() => window.Alpine !== undefined);

    const hayForm = await p.$('form[action*="inscribirse"]');
    if (! hayForm) {
        di('la actividad recibe inscripciones', false, 'no las recibe');
    } else {
        di('los campos están marcados',
            await p.$$eval('form[action*="inscribirse"] [data-campo]', (n) => n.length) === 3);
        di('el teléfono se dice que es opcional',
            await p.$eval('[data-campo="telefono"]', (e) => /opcional/i.test(e.innerText)));

        /*
         * Primero, vacío y sin la validación del navegador: lo tiene que
         * frenar la guía, igual que en el wizard.
         */
        await p.evaluate(() => { document.querySelector('form[action*="inscribirse"]').noValidate = true; });
        const urlInsc = p.url();
        await p.evaluate(() => document.querySelector('form[action*="inscribirse"] button[type=submit]').click());
        await new Promise((r) => setTimeout(r, 600));

        di('vacío, no se envía', p.url() === urlInsc);
        di('y dice los dos que faltan', /Faltan 2 campos/.test(await textoResumen() ?? ''));
        di('el resumen queda EN PANTALLA', await enPantalla('[data-resumen-errores]') === true);

        /*
         * Y ahora el camino del SERVIDOR, que es el que no tenía aviso
         * ninguno: algo que la guía da por bueno —el campo tiene texto— y el
         * servidor rechaza. Un nombre de 300 caracteres contra `max:255`.
         * Con un correo mal escrito no vale: `a@b` lo dan por bueno los dos.
         */
        await p.type('#r-nombre', 'N'.repeat(300));
        await p.type('#r-correo', `prueba${Date.now()}@ong-laravel.test`);
        await Promise.all([
            p.waitForNavigation({ waitUntil: 'networkidle2' }),
            p.evaluate(() => document.querySelector('form[action*="inscribirse"] button[type=submit]').click()),
        ]);
        await p.waitForFunction(() => window.Alpine !== undefined);
        await new Promise((r) => setTimeout(r, 800));

        const insc = await textoResumen();
        di('lo que rechaza el servidor vuelve con resumen', insc !== null, JSON.stringify(insc?.slice(0, 80)));
        di('nombra el campo', /Nombre/.test(insc ?? ''));
        di('y trae el motivo', /255|caracteres/.test(insc ?? ''));
        di('y queda EN PANTALLA', await enPantalla('[data-resumen-errores]') === true);
    }
}

/* ══════════════════════════════════════════════════════════════════ */
t('Con la sesión abierta no pide el correo ni la contraseña');

/*
 * El caso que ya mordió una vez: con sesión abierta el paso 3 no pinta el
 * bloque «crea tu acceso», porque la actividad va a la cuenta que ya existe. Si
 * la revisión previa exigiera esos dos campos, frenaría el envío pidiendo algo
 * que no está en pantalla — que es el mismo fallo que se viene a arreglar, en
 * su peor versión.
 */
await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', 'organizador@ong-laravel.test');
await p.type('input[name="password"]', 'organizador1234');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);

await abrirWizard();

di('no se pinta el campo de correo', await p.$('input[name="email"]') === null);
di('ni el de contraseña', await p.$('input[name="password"]') === null);

await irAPaso(3);
const faltanConSesion = await p.evaluate(
    () => Alpine.$data(document.querySelector('[x-data^="wizard"]')).camposQueFaltan(3).map((e) => e.campo));
di('y no se exigen', faltanConSesion.length === 0, faltanConSesion.join(', '));

await continuar();
await new Promise((r) => setTimeout(r, 500));
di('«Continuar» deja pasar al 4', await paso() === 4);

/* ══════════════════════════════════════════════════════════════════ */
t('Sin errores en la consola');

di('ningún error de JavaScript', errores.length === 0, errores.slice(0, 3).join(' | '));

console.log('');
console.log(`${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal === 0 ? 0 : 1);
