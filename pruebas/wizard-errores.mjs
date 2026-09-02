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

// La marca es una pastilla, no una barra: dentro de un flex en columna se
// estiraba a todo el ancho y dejaba de leerse como una etiqueta. Hay que
// medirla con el paso 4 en pantalla; oculta, todo mide cero.
await irAPaso(4);
const anchoMarcas = await p.$$eval('.marca-obligatoria', (n) => n.map((e) => Math.round(e.getBoundingClientRect().width)));
di('y la marca es una pastilla, no una barra',
    anchoMarcas.length === 3 && anchoMarcas.every((w) => w > 0 && w < 130),
    anchoMarcas.join('px, ') + 'px');
await irAPaso(1);

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
di('hay botón de calendario', await p.$$eval('[data-campo="fecha_inicio"] .campo-selector-boton', (n) => n.length) === 1);

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
    const cal = document.querySelector('[data-campo="fecha_inicio"] .campo-selector-nativo');
    cal.value = '2027-03-09';
    cal.dispatchEvent(new Event('change', { bubbles: true }));
});
di('el calendario escribe en el campo de texto',
    await p.$eval('input[name="fecha_inicio"]', (e) => e.value) === '09 / 03 / 2027');

/* ══════════════════════════════════════════════════════════════════ */
t('Los campos de hora, con el mismo trato que la fecha');

const hora = async (campo, texto) => {
    await p.evaluate((c) => { document.querySelector(`input[name="${c}"]`).value = ''; }, campo);
    await p.focus(`input[name="${campo}"]`);
    await p.type(`input[name="${campo}"]`, texto);
    await p.evaluate((c) => {
        const el = document.querySelector(`input[name="${c}"]`);
        el.dispatchEvent(new Event('blur', { bubbles: true }));
    }, campo);
    await new Promise((r) => setTimeout(r, 150));
    return p.$eval(`input[name="${campo}"]`, (e) => e.value);
};

di('sigue siendo texto, no type=time',
    await p.$eval('input[name="hora_inicio"]', (e) => e.type) === 'text');
di('el hueco enseña el formato',
    await p.$eval('input[name="hora_inicio"]', (e) => e.placeholder) === 'HH:MM');
di('hay botón de reloj',
    await p.$$eval('[data-campo="hora_inicio"] .campo-selector-boton', (n) => n.length) === 1);
di('y ayuda con un ejemplo',
    await p.$eval('[data-campo="hora_inicio"]', (e) => /Ej\. 09:00/.test(e.innerText)));

di('los dos puntos se ponen solos al teclear', await hora('hora_inicio', '0930') === '09:30');
di('«9» son las nueve en punto', await hora('hora_inicio', '9') === '09:00');
di('«930» también se entiende', await hora('hora_inicio', '930') === '09:30');
di('y lo escrito con punto', await hora('hora_inicio', '9.30') === '09:30');
di('una hora imposible se deja como está, y la explica el servidor',
    await hora('hora_inicio', '99') === '99', await hora('hora_inicio', '99'));

// Pegar: el mismo camino que en la fecha, por el evento que dispara el
// navegador al pegar.
const pegarHora = async (texto) => {
    await p.evaluate((v) => {
        const campo = document.querySelector('input[name="hora_termino"]');
        campo.value = v;
        campo.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertFromPaste' }));
    }, texto);
    await new Promise((r) => setTimeout(r, 150));
    return p.$eval('input[name="hora_termino"]', (e) => e.value);
};

di('pegado con dos puntos, se respeta', await pegarHora('13:45') === '13:45');
di('pegado sin separador, se ordena solo', await pegarHora('1345') === '13:45');
di('pegado con segundos, se recorta', await pegarHora('13:45:00') === '13:45');

// Y el reloj escribe en el campo de texto, no lo sustituye.
await p.evaluate(() => {
    const r = document.querySelector('[data-campo="hora_termino"] .campo-selector-nativo');
    r.value = '18:15';
    r.dispatchEvent(new Event('change', { bubbles: true }));
});
await new Promise((r) => setTimeout(r, 150));
di('el reloj escribe en el campo de texto',
    await p.$eval('input[name="hora_termino"]', (e) => e.value) === '18:15');

await p.evaluate(() => {
    document.querySelector('input[name="hora_inicio"]').value = '';
    document.querySelector('input[name="hora_termino"]').value = '';
});

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
t('La dirección ahora es obligatoria de verdad');

/*
 * Decidido por Jonas el 2026-09-02: el asterisco del HTML fuente manda, y la
 * regla pasa a `required_without:sin_fecha_definida` como las de región y
 * comuna. Lo que se comprueba aquí es que las dos puntas dicen lo mismo: la
 * revisión previa la exige, y el servidor también.
 */
await abrirWizard();
await irAPaso(4);

const exigeDireccion = await p.evaluate(() => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    return d.camposQueFaltan(4).some((e) => e.campo === 'direccion');
});
di('la revisión previa la exige', exigeDireccion);

// Y con «disponible de forma permanente» deja de exigirla, igual que la fecha.
await p.evaluate(() => document.querySelector('input[name="sin_fecha_definida"]').click());
await new Promise((r) => setTimeout(r, 300));
const sinFechaSinDireccion = await p.evaluate(() => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    return d.camposQueFaltan(4).some((e) => e.campo === 'direccion');
});
di('pero deja de exigirla si es permanente', sinFechaSinDireccion === false);

const relevados = await p.evaluate(() => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    return d.camposQueFaltan(4).map((e) => e.campo);
});
di('y lo mismo con región y comuna, que son `required_without`',
    ! relevados.includes('region_id') && ! relevados.includes('commune_id'),
    relevados.join(', '));

await p.evaluate(() => document.querySelector('input[name="sin_fecha_definida"]').click());
await new Promise((r) => setTimeout(r, 300));
const devuelta = await p.evaluate(() => {
    const d = Alpine.$data(document.querySelector('[x-data^="wizard"]'));
    return d.camposQueFaltan(4).map((e) => e.campo);
});
di('y al desmarcarla vuelven las tres',
    ['region_id', 'commune_id', 'direccion'].every((c) => devuelta.includes(c)),
    devuelta.join(', '));

/* ══════════════════════════════════════════════════════════════════ */
t('El editor de actividades de «Mi cuenta»');

await p.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle2' });
if (p.url().includes('/login')) {
    await p.type('input[name="email"]', 'organizador@ong-laravel.test');
    await p.type('input[name="password"]', 'organizador1234');
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
}

await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
const editar = await p.evaluate(() => document.querySelector('a[href*="/editar"]')?.href ?? null);

if (! editar) {
    di('el organizador tiene alguna actividad que editar', false);
} else {
    await p.goto(editar, { waitUntil: 'networkidle2' });
    await p.waitForFunction(() => window.Alpine !== undefined);

    const raiz = '[x-data^="editorActividad"]';
    const faltanAqui = () => p.evaluate((r) =>
        Alpine.$data(document.querySelector(r)).camposQueFaltan().map((e) => e.campo), raiz);

    di('la pantalla carga', await p.$(raiz) !== null);
    di('no hay resumen visible sin errores', await textoResumen() === null);

    const cajasAqui = await p.$$eval('[data-campo][data-obligatorio]', (n) => n.map((e) => e.dataset.campo));
    di('los campos obligatorios están marcados',
        ['titulo', 'descripcion', 'fecha_inicio', 'direccion', 'commune_id', 'temas', 'publicos']
            .every((c) => cajasAqui.includes(c)),
        cajasAqui.join(', '));

    /*
     * «Características» lleva asterisco en el HTML fuente y su regla dice
     * `nullable`, y hay actividades sembradas sin ninguna. Hasta que se decida,
     * la revisión previa sigue a la REGLA, igual que se hizo con «Dirección»
     * antes de que Jonas la decidiera.
     */
    di('«características» sigue a la regla y no al asterisco',
        ! cajasAqui.includes('caracteristicas'));
    di('y «accesibilidad», que es opcional, tampoco', ! cajasAqui.includes('accesos'));

    di('los dos grupos obligatorios lo dicen con la palabra',
        await p.$$eval('[data-campo] .marca-obligatoria',
            (n) => n.map((e) => e.closest('[data-campo]').dataset.campo).join(',')) === 'temas,publicos');

    // Aquí el encabezado va dentro de `.lbl`, que es flex en columna: la
    // marca salía estirada a todo el ancho y en su propio renglón.
    const marcasMc = await p.$$eval('.marca-obligatoria', (n) => n.map((e) => {
        const r = e.getBoundingClientRect();
        const previo = e.parentElement.getBoundingClientRect();
        return { ancho: Math.round(r.width), mismaLinea: r.top - previo.top < r.height };
    }));
    di('la marca es una pastilla y va en la misma línea',
        marcasMc.every((m) => m.ancho > 0 && m.ancho < 130 && m.mismaLinea),
        JSON.stringify(marcasMc));

    di('el título ya no lleva el `required` del navegador',
        await p.$eval('input[name="titulo"]', (e) => ! e.required));

    // Las dos fechas y las dos horas, con su selector y su máscara.
    const conSelector = (campos) => p.$$eval(
        campos.map((c) => `[data-campo="${c}"] .campo-selector-boton`).join(', '),
        (n) => n.length);

    di('las dos fechas tienen calendario',
        await conSelector(['fecha_inicio', 'fecha_termino']) === 2);
    di('y las dos horas tienen reloj',
        await conSelector(['hora_inicio', 'hora_termino']) === 2);
    di('los cuatro siguen siendo de texto, para poder pegar',
        await p.$$eval('input[name="fecha_inicio"], input[name="fecha_termino"], input[name="hora_inicio"], input[name="hora_termino"]',
            (n) => n.length === 4 && n.every((e) => e.type === 'text')));

    // Seleccionar todo y reescribir encima, que es lo que se hace para cambiar
    // una fecha que ya estaba puesta.
    await p.focus('input[name="fecha_inicio"]');
    await p.keyboard.down('Control');
    await p.keyboard.press('KeyA');
    await p.keyboard.up('Control');
    await p.type('input[name="fecha_inicio"]', '09032027');
    di('se puede reescribir encima de la fecha que había',
        await p.$eval('input[name="fecha_inicio"]', (e) => e.value) === '09 / 03 / 2027',
        await p.$eval('input[name="fecha_inicio"]', (e) => e.value));

    /* ── Vaciar lo obligatorio y guardar ── */
    await p.evaluate(() => {
        document.querySelector('input[name="titulo"]').value = '';
        document.querySelector('input[name="direccion"]').value = '';
    });
    await p.evaluate((r) => {
        // Desmarcar todos los públicos, que es el caso del reporte.
        const d = Alpine.$data(document.querySelector(r));
        d.sel.publicos = [];
    }, raiz);
    await new Promise((r) => setTimeout(r, 300));

    const pendientesAqui = await faltanAqui();
    di('detecta los tres que faltan',
        ['titulo', 'direccion', 'publicos'].every((c) => pendientesAqui.includes(c)),
        pendientesAqui.join(', '));

    await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await new Promise((r) => setTimeout(r, 300));

    const urlEdit = p.url();
    await p.evaluate(() => [...document.querySelectorAll('button[type=submit]')]
        .find((b) => b.textContent.includes('Actualizar')).click());
    await new Promise((r) => setTimeout(r, 800));

    di('no se guarda', p.url() === urlEdit);

    const resEdit = await textoResumen();
    di('aparece el resumen', resEdit !== null, JSON.stringify(resEdit?.slice(0, 90)));
    di('dice cuántos faltan', /Faltan 3 campos/.test(resEdit ?? ''));
    di('nombra los tres', /Nombre de la actividad/.test(resEdit ?? '')
        && /Dirección/.test(resEdit ?? '') && /Público beneficiado/.test(resEdit ?? ''));
    di('el resumen quedó EN PANTALLA', await enPantalla('[data-resumen-errores]') === true);

    const marcadasAqui = await p.$$eval('.campo-fallido', (n) => n.map((e) => e.dataset.campo));
    di('marca las tres cajas', marcadasAqui.length === 3, marcadasAqui.join(', '));

    // El renglón salta, y al rellenar se limpia.
    await p.evaluate(() => document.querySelector('[data-resumen-errores] .resumen-errores-salto').click());
    await new Promise((r) => setTimeout(r, 700));
    di('el renglón salta a su campo', await enPantalla('[data-campo="titulo"]') === true);

    await p.type('input[name="titulo"]', 'Título recuperado');
    await new Promise((r) => setTimeout(r, 300));
    di('al rellenarlo sale del resumen', /Faltan 2 campos/.test(await textoResumen() ?? ''));

    /* ── Y el camino del servidor: la dirección vacía ahora la rechaza él ── */
    await p.evaluate(() => { document.querySelector('input[name="direccion"]').value = ''; });
    await p.evaluate((r) => {
        const f = document.querySelector('form[action*="/mi-cuenta/actividades/"]');
        f.setAttribute('x-on:submit', '');
        f.submit();
    }, raiz);
    await p.waitForNavigation({ waitUntil: 'networkidle2' });
    await p.waitForFunction(() => window.Alpine !== undefined);
    await new Promise((r) => setTimeout(r, 800));

    const servEdit = await textoResumen();
    di('el servidor rechaza la dirección vacía', /Dirección/.test(servEdit ?? ''),
        JSON.stringify(servEdit?.slice(0, 90)));
    di('y lo dice con el motivo', /permanente|obligatori/i.test(servEdit ?? ''));
    di('con el resumen EN PANTALLA', await enPantalla('[data-resumen-errores]') === true);
}

/* ══════════════════════════════════════════════════════════════════ */
t('Sin errores en la consola');

di('ningún error de JavaScript', errores.length === 0, errores.slice(0, 3).join(' | '));

console.log('');
console.log(`${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal === 0 ? 0 : 1);
