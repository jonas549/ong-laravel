// El botón de enviar cuando algo corta el envío. En Chrome.
//
// EL BUG (2026-09-10): la guía de errores del bloque K aborta el envío con
// `preventDefault()` desde el propio formulario, pero el estado de carga del
// bloque H —que se engancha al documento— seguía marcando el botón como
// ocupado. `.esta-cargando` lleva `pointer-events:none` y nadie lo suelta, así
// que el botón quedaba con el loader puesto y **sin poder pulsarse nunca más**:
// el formulario no se podía reenviar ni después de corregir el campo que
// faltaba. Un intento fallido dejaba la pantalla muerta.
//
// Dónde podía pasar, y dónde no:
//
//   - **El wizard y el editor de mi-cuenta**, sí. Son los dos formularios que
//     a propósito NO llevan `required` nativo (bloque K: en un formulario por
//     pasos, un control inválido dentro de un paso oculto hace que Chrome corte
//     el envío sin decir nada). Sin `required`, `checkValidity()` los da por
//     buenos y el guardián que ya existía no los cubría.
//   - **La inscripción y la encuesta**, no. Ahí cada caja obligatoria tiene un
//     control con `required` nativo, así que Chrome corta antes y el evento
//     `submit` ni siquiera se dispara. Se comprueban igual, por dos motivos:
//     el invariante que hoy las salva puede romperlo un grupo de chips añadido
//     mañana, y el envío sintético de más abajo ejercita el camino aunque el
//     navegador no llegue a él.
//   - **El panel al CANCELAR una acción masiva**, sí. `confirmarAccion`
//     devuelve false y la vista hace `preventDefault()`: mismo patrón.
//
// Por qué en Chrome y no por HTTP: por HTTP no hay JavaScript, así que el envío
// ni llega a cortarse y el fallo no existe. Lo que hay que mirar es una clase,
// un estilo calculado y si un segundo clic llega a disparar el envío.
//
// Y por qué cuenta eventos `submit` en vez de mirar sólo la clase: la clase
// dice cómo se VE el botón; el contador dice si se puede volver a PULSAR, que
// es lo que reportó el cliente. Un botón con `pointer-events:none` se deja
// clicar por el ratón sin que el clic le llegue, y sin ningún error.
//
//   node pruebas/boton-envio.mjs
import puppeteer from 'puppeteer-core';
import { ADMIN, CLAVE_ADMIN, CLAVE_ORG, ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
await p.setViewport({ width: 1440, height: 900 });

/* ─────────────────────────────────────────────────────────── helpers ── */

/**
 * Cuenta los envíos que llegan a dispararse. En captura, para contarlos aunque
 * alguien pare la propagación. Hay que reinstalarlo tras cada navegación.
 */
const contarEnvios = () => p.evaluate(() => {
    window.__envios = 0;
    document.addEventListener('submit', () => { window.__envios++; }, true);
});
const envios = () => p.evaluate(() => window.__envios);

const abrir = async (ruta) => {
    await p.goto(`${B}${ruta}`, { waitUntil: 'networkidle2' });
    await p.waitForFunction(() => window.Alpine !== undefined);
    await contarEnvios();
};

const entrarComo = async (puerta, correo, clave) => {
    await p.goto(`${B}${puerta}`, { waitUntil: 'networkidle2' });
    await p.type('input[name="email"]', correo);
    await p.type('input[name="password"]', clave);
    await Promise.all([
        p.waitForNavigation({ waitUntil: 'networkidle2' }),
        p.click('button[type="submit"]'),
    ]);
};

/**
 * El estado del botón tal como lo ve quien lo mira y quien lo pulsa.
 *
 * `pointer-events` es lo que decide si se puede volver a pulsar: el bloque H
 * eligió eso y no `disabled` a propósito, porque un botón deshabilitado no
 * manda su `name`/`value` y las acciones masivas los necesitan.
 */
const estado = (sel) => p.evaluate((s) => {
    const b = [...document.querySelectorAll(s)].find((e) => e.offsetParent !== null);
    if (! b) return null;

    return {
        cargando: b.classList.contains('esta-cargando'),
        ocupado: b.dataset.ocupado === '1',
        aria: b.getAttribute('aria-busy'),
        sinClics: getComputedStyle(b).pointerEvents === 'none',
        texto: b.textContent.trim(),
    };
}, sel);

/** El botón quedó como estaba: ni loader, ni marca, ni bloqueo al ratón. */
const botonIntacto = async (donde, sel) => {
    const e = await estado(sel);

    di(`${donde}: el botón NO se queda cargando`, e !== null && ! e.cargando);
    di(`${donde}: sin marca de ocupado`, e !== null && ! e.ocupado);
    di(`${donde}: sin aria-busy`, e !== null && e.aria === null, String(e?.aria));
    di(`${donde}: admite clics (pointer-events)`, e !== null && ! e.sinClics);

    return e;
};

/**
 * Y la de verdad: intentarlo otra vez y ver si el envío llega a dispararse.
 *
 * `disparar` es cómo se intenta. Por defecto un clic de ratón, que es lo que
 * hace una persona; los formularios con `required` nativo necesitan el envío
 * sintético, porque ahí el navegador corta antes de que el clic llegue a nada.
 */
const sePuedeReenviar = async (donde, sel, disparar = null) => {
    const antes = await envios();

    await (disparar ? disparar() : p.click(sel).catch(() => {}));
    await esperar(400);

    di(`${donde}: se puede volver a enviar`, await envios() > antes, `${antes} → ${await envios()}`);
};

/**
 * Corta la navegación sin tocar el estado de carga.
 *
 * Se registra DESPUÉS de que app.js registrara el suyo: en la misma fase y el
 * mismo objetivo manda el orden de registro, así que el del bloque H corre
 * primero —y marca el botón, que es lo correcto— y éste cancela el viaje
 * después. Sirve para comprobar que un envío bueno sale sin crear registros.
 */
const frenarNavegacion = () => p.evaluate(() => {
    document.addEventListener('submit', (e) => e.preventDefault());
});

/**
 * Un envío sintético, para los formularios que el `required` nativo no deja
 * llegar hasta aquí. `dispatchEvent` se salta la validación del navegador y
 * corre los manejadores igual, así que ejercita justo el camino del fallo sin
 * navegar a ninguna parte.
 */
const envioSintetico = (formSel) => p.evaluate((f) => {
    const form = document.querySelector(f);
    const boton = form.querySelector('button[type=submit]');

    form.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: boton }));
}, formSel);

/** Cada caja obligatoria tiene un control con `required`: es lo que las salva. */
const obligatoriosConRequiredNativo = (formSel) => p.evaluate((f) => {
    const cajas = [...document.querySelectorAll(`${f} [data-campo][data-obligatorio]`)];

    return {
        total: cajas.length,
        sinNativo: cajas
            .filter((c) => ! [...c.querySelectorAll('input,select,textarea')].some((x) => x.required))
            .map((c) => c.dataset.campo),
    };
}, formSel);

/* ══════════════════════════════════════════════════════════════════ */
t('1 · El wizard sin sesión — el caso del reporte');

await abrir('/publicar-actividad');

const paso = async (n) => {
    await p.evaluate((n) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = n; }, n);
    await p.waitForFunction((n) => document.querySelector(`[data-paso="${n}"]`)?.offsetParent !== null, {}, n);
};
const valores = (sel) => p.$$eval(sel + ' option', (o) => o.map((x) => x.value).filter(Boolean));
const faltan = () => p.evaluate(
    () => Alpine.$data(document.querySelector('[x-data^="wizard"]')).camposQueFaltan().map((e) => e.campo));

/** Rellena el wizard entero MENOS el público, que es el campo del reporte. */
const rellenarWizardSalvoPublico = async ({ conSesion }) => {
    await paso(3);
    if (! conSesion) {
        await p.type('input[name="org_nombre"]', 'Fundación de Prueba');
        await p.type('input[name="email"]', `boton${Date.now()}@ong-laravel.test`);
        await p.type('input[name="password"]', 'clave-larga-1234');
        await p.type('input[name="password_confirmation"]', 'clave-larga-1234');

        // El logo es obligatorio en escritorio salvo en «Otra»: sin subirlo, lo
        // que falta no sería sólo el público, que es el caso que se prueba.
        const campoLogo = await p.$('input[name="org_logo"]');
        if (campoLogo) {
            await campoLogo.uploadFile('public/img/logo-fundacion-trascender.png');
            await p.waitForFunction(() => {
                const d = Alpine.$data(document.querySelector('[data-campo="org_logo"]'));
                return d.tiene && ! d.reduciendo;
            }, { timeout: 8000 }).catch(() => null);
        }
    }

    await paso(4);
    await p.type('input[name="titulo"]', 'Actividad de prueba del botón');
    await p.type('textarea[name="descripcion"]', 'Descripción cualquiera para la prueba.');
    await p.type('input[name="fecha_inicio"]', '04122026');
    await p.select('select[name="region_id"]', (await valores('select[name="region_id"]'))[0]);
    await p.waitForFunction(() => [...document.querySelectorAll('select[name="commune_id"] option')].some((o) => o.value));
    await p.select('select[name="commune_id"]', (await valores('select[name="commune_id"]'))[0]);
    await p.type('input[name="direccion"]', 'Calle Falsa 123');
    await p.evaluate(() => {
        document.querySelector('[data-campo="temas"] button.chip').click();
        document.querySelector('[data-campo="caracteristicas"] button.chip').click();
    });
};

await rellenarWizardSalvoPublico({ conSesion: false });

di('sólo falta el público beneficiado', (await faltan()).join(',') === 'publicos', (await faltan()).join(','));

// El wizard no lleva `required` nativo, y por eso el guardián viejo no bastaba.
di('el wizard no usa `required` nativo (por diseño)',
    await p.$$eval('form input[required], form select[required], form textarea[required]', (n) => n.length) === 0);

// Bajar del todo y pulsar, como hace cualquiera.
await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await esperar(250);

const urlAntes = p.url();
await p.click('button[type="submit"]');
await esperar(600);

di('el envío NO salió al servidor', p.url() === urlAntes);
di('y salió el aviso', await p.$$eval('[data-resumen-errores]', (n) => n.some((e) => e.offsetParent !== null)));
di('el envío llegó a dispararse', await envios() >= 1, `${await envios()} envíos`);

await botonIntacto('wizard/invitado', 'button[type="submit"]');
await sePuedeReenviar('wizard/invitado', 'button[type="submit"]');

/* ══════════════════════════════════════════════════════════════════ */
t('1b · Corregir el campo y volver a enviar — el camino del reporte');

await p.evaluate(() => document.querySelector('[data-campo="publicos"] button.chip').click());
await esperar(250);
di('ya no falta nada', (await faltan()).length === 0);

const e1b = await estado('button[type="submit"]');
di('el botón sigue utilizable tras corregir', e1b !== null && ! e1b.cargando && ! e1b.sinClics);

// Con el formulario ya completo, este clic SÍ enviaría de verdad y crearía
// cuenta, organización y actividad. Se frena el viaje justo después de que el
// bloque H haya hecho lo suyo, para probar el clic sin ensuciar la base.
await frenarNavegacion();

const antes1b = await envios();
await p.click('button[type="submit"]').catch(() => {});
await esperar(400);
di('el clic dispara el envío', await envios() > antes1b, `${antes1b} → ${await envios()}`);

// Y de paso queda demostrada la otra mitad: en el camino bueno el botón SÍ se
// marca ocupado, que es lo que evita el doble clic.
const trasEnviar = await estado('button[type="submit"]');
di('y ahora sí queda marcado como ocupado', trasEnviar !== null && trasEnviar.cargando);

/* ══════════════════════════════════════════════════════════════════ */
t('2 · El wizard CON sesión de organizador');

await entrarComo('/mi-cuenta/login', ORG, CLAVE_ORG);
di('entró como organizador', p.url().includes('/mi-cuenta'), p.url());

await abrir('/publicar-actividad');
di('el wizard se abre con la sesión puesta', await p.$$eval('[x-data^="wizard"]', (n) => n.length) === 1);
/*
 * Con la ficha de la organización completa, el paso 3 ya no se pinta: lo que se
 * comprueba es que no haya nada que pedirle de la cuenta, esté el paso fuera o
 * dentro. Antes esto miraba que el campo de contraseña no existiera; ahora el
 * bloque entero puede no existir, que es más de lo mismo.
 */
const pideClave = await p.evaluate(() => {
    const campo = document.querySelector('input[name="password"]');
    return !! campo && campo.getBoundingClientRect().height > 0;
});
di('y no se le pide contraseña', pideClave === false);

await rellenarWizardSalvoPublico({ conSesion: true });
await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await esperar(250);
await p.click('button[type="submit"]');
await esperar(600);

di('wizard/con sesión: el envío se cortó', await envios() >= 1, `${await envios()} envíos`);
await botonIntacto('wizard/con sesión', 'button[type="submit"]');
await sePuedeReenviar('wizard/con sesión', 'button[type="submit"]');

/* ══════════════════════════════════════════════════════════════════ */
t('3 · El editor de actividades de /mi-cuenta');

await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
const editar = await p.$$eval('a[href*="/editar"]', (n) => n.map((a) => a.getAttribute('href'))
    .filter((h) => /\/mi-cuenta\/actividades\/\d+\/editar/.test(h)));
di('hay alguna actividad que editar', editar.length > 0, `${editar.length}`);

await abrir(new URL(editar[0], B).pathname);

// El título es obligatorio; vaciarlo es lo que corta el envío.
const tituloOriginal = await p.$eval('input[name="titulo"]', (e) => e.value);
const escribirTitulo = (v) => p.evaluate((v) => {
    const c = document.querySelector('input[name="titulo"]');
    c.value = v;
    c.dispatchEvent(new Event('input', { bubbles: true }));
}, v);

await escribirTitulo('');
await esperar(200);

const botonActualizar = 'form[method="POST"] button[type="submit"].btn-primary';
await p.evaluate((s) => document.querySelector(s).scrollIntoView({ block: 'center' }), botonActualizar);
await p.click(botonActualizar);
await esperar(600);

di('mi-cuenta: el envío se cortó', await envios() >= 1, `${await envios()} envíos`);
di('mi-cuenta: sigue en el formulario', p.url().includes('/editar'));
await botonIntacto('mi-cuenta', botonActualizar);
await sePuedeReenviar('mi-cuenta', botonActualizar);

// Se devuelve el título para no dejar la actividad a medias.
await escribirTitulo(tituloOriginal);

/* ══════════════════════════════════════════════════════════════════ */
t('4 · El formulario de inscripción de la ficha pública');

await abrir('/actividades/jornada-de-reforestacion-urbana');

const formInscripcion = 'form[action*="/inscribirse"]';
const botonInscripcion = `${formInscripcion} button[type="submit"]`;
di('la ficha trae su formulario de inscripción', await p.$$eval(botonInscripcion, (n) => n.length) === 1);

// El invariante que hoy lo salva. Si mañana alguien le añade un grupo de chips,
// esta comprobación es la que avisa de que ya no está protegido por el nativo.
const invInscripcion = await obligatoriosConRequiredNativo(formInscripcion);
di('cada obligatorio tiene `required` nativo',
    invInscripcion.total > 0 && invInscripcion.sinNativo.length === 0,
    `${invInscripcion.total} cajas; sin nativo: ${invInscripcion.sinNativo.join(', ') || '—'}`);

// Un clic de verdad: el navegador corta antes y el submit ni se dispara.
await p.evaluate((s) => document.querySelector(s).scrollIntoView({ block: 'center' }), botonInscripcion);
await p.click(botonInscripcion);
await esperar(500);
di('inscripción: Chrome corta antes de disparar el envío', await envios() === 0, `${await envios()} envíos`);
await botonIntacto('inscripción/nativo', botonInscripcion);

// Y el camino del fallo, ejercitado a mano por si mañana deja de haber nativo.
await envioSintetico(formInscripcion);
await esperar(400);
di('inscripción: el envío sintético lo corta la guía', await envios() >= 1, `${await envios()} envíos`);
await botonIntacto('inscripción/sintético', botonInscripcion);
await sePuedeReenviar('inscripción/sintético', botonInscripcion, () => envioSintetico(formInscripcion));

/* ══════════════════════════════════════════════════════════════════ */
t('5 · La encuesta de evaluación del QR');

// Contra una actividad sembrada de siempre y no contra el escenario de
// `datos-evaluacion.php`: éste no puede convivir con el del calendario, y una
// prueba que dependa de él obliga a tenerlo puesto. Ver pruebas/README.md.
await abrir('/evaluar/jornada-de-reforestacion-urbana');
const formEvaluacion = 'form.evaluacion-form';
di('la encuesta está abierta', await p.$$eval('.evaluacion-enviar', (n) => n.length) === 1);

const invEvaluacion = await obligatoriosConRequiredNativo(formEvaluacion);
di('cada obligatorio tiene `required` nativo',
    invEvaluacion.total > 0 && invEvaluacion.sinNativo.length === 0,
    `${invEvaluacion.total} cajas; sin nativo: ${invEvaluacion.sinNativo.join(', ') || '—'}`);

await p.evaluate(() => document.querySelector('.evaluacion-enviar').scrollIntoView({ block: 'center' }));
await p.click('.evaluacion-enviar');
await esperar(500);
di('evaluación: Chrome corta antes de disparar el envío', await envios() === 0, `${await envios()} envíos`);
await botonIntacto('evaluación/nativo', '.evaluacion-enviar');

await envioSintetico(formEvaluacion);
await esperar(400);
di('evaluación: el envío sintético lo corta la guía', await envios() >= 1, `${await envios()} envíos`);
await botonIntacto('evaluación/sintético', '.evaluacion-enviar');
await sePuedeReenviar('evaluación/sintético', '.evaluacion-enviar', () => envioSintetico(formEvaluacion));

/* ══════════════════════════════════════════════════════════════════ */
t('6 · Y el camino bueno SIGUE marcando el botón ocupado');

/*
 * La otra mitad: el arreglo no puede cargarse el estado de carga del bloque H,
 * que está ahí porque un doble clic en «Publicar» ya provocó un 500.
 *
 * Se comprueba sin navegar, con un truco deliberado: se registra un listener
 * de `submit` en el documento DESPUÉS de que app.js registrara el suyo. En la
 * misma fase y el mismo objetivo manda el orden de registro, así que el del
 * estado de carga corre primero —y marca— y el nuestro cancela la navegación
 * después. Eso deja el botón marcado y la página quieta para poder mirarlo.
 */
await abrir('/publicar-actividad');
await rellenarWizardSalvoPublico({ conSesion: true });
await p.evaluate(() => document.querySelector('[data-campo="publicos"] button.chip').click());
await esperar(250);
di('el wizard está completo', (await faltan()).length === 0);

await frenarNavegacion();
await p.evaluate(() => document.querySelector('button[type="submit"]').scrollIntoView({ block: 'center' }));
await p.click('button[type="submit"]');
await esperar(400);

const bueno = await estado('button[type="submit"]');
di('con el formulario completo, el botón SÍ se marca ocupado', bueno !== null && bueno.cargando);
di('y lo dice con aria-busy', bueno !== null && bueno.aria === 'true');

/* ══════════════════════════════════════════════════════════════════ */
t('7 · El panel: cancelar el diálogo de una acción masiva');

await entrarComo('/admin/login', ADMIN, CLAVE_ADMIN);
di('entró como admin', p.url().includes('/admin'), p.url());

await abrir('/admin/contenido/noticias');

const masiva = 'button[data-confirmar]';
di('el listado tiene acciones masivas', await p.$$eval(masiva, (n) => n.length) > 0);

await p.evaluate(() => {
    const casilla = document.querySelector('.panel-tabla input[type="checkbox"][name="ids[]"]');
    if (casilla) casilla.click();
});
await esperar(200);
await p.evaluate((s) => document.querySelector(s).scrollIntoView({ block: 'center' }), masiva);
await p.click(masiva);
await esperar(500);

di('se abrió el diálogo de confirmación',
    await p.evaluate(() => Alpine.store('confirmacion').abierto) === true);

// Aquí está el otro caso del mismo fallo: se cancela y el botón se quedaba muerto.
await p.evaluate(() => Alpine.store('confirmacion').cerrar());
await esperar(400);

await botonIntacto('panel/cancelar', masiva);
await sePuedeReenviar('panel/cancelar', masiva);
await p.evaluate(() => Alpine.store('confirmacion').cerrar());

/* ══════════════════════════════════════════════════════════════════ */
t('Errores de consola');

const relevantes = errores.filter((e) => ! /favicon|net::ERR_/.test(e));
di('sin errores de JavaScript', relevantes.length === 0, relevantes.slice(0, 3).join(' | '));

console.log('');
console.log(`  ${ok} OK · ${mal} MAL`);
console.log('');

await nav.close();
process.exit(mal === 0 ? 0 : 1);
