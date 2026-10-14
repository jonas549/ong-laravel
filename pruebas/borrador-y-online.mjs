// «Guardar borrador» del wizard y el lugar de las actividades online, en Chrome.
//
//   A. El lugar según el formato: en el wizard y en el editor de «Mi cuenta»,
//      en los tres formatos. Online esconde y deshabilita región, comuna y
//      dirección; los asteriscos siguen al formato y a «permanente».
//   B. Las reglas del servidor, en los dos formularios, para que digan lo
//      mismo que la pantalla.
//   C. Una actividad online, de punta a punta: se guarda sin lugar, y la
//      tarjeta, la ficha, el filtro por región, el calendario, el .ics y
//      «otras actividades» se portan bien.
//   D. «Guardar borrador»: sin el paso 3 lo dice y lleva al campo; con el
//      paso 3 crea cuenta, organización y borrador; la pantalla dice qué pasó
//      y dónde está; desde Borradores se completa y se envía, entonces sí con
//      todas las reglas.
//
// Monta lo suyo con un sello irrepetible y lo deshace al final.
//
//   node pruebas/borrador-y-online.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(72)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();
const json = (php) => JSON.parse(ultima(tinker(php)));

const SELLO = Date.now();
const CLAVE = 'ClaveLarga123';
const CORREO_ONLINE = `online.${SELLO}@ejemplo.cl`;
const CORREO_BORRADOR = `borrador.${SELLO}@ejemplo.cl`;
const ORG_ONLINE = `Fundación Online ${SELLO}`;
const ORG_BORRADOR = `Fundación Borrador ${SELLO}`;
const TITULO_ONLINE = `Taller online ${SELLO}`;
const TITULO_BORRADOR = `Borrador ${SELLO}`;
const TITULO_BORRADOR_2 = `Segundo borrador ${SELLO}`;

const limpiar = () => tinker(
  `foreach (App\\Models\\Organization::withTrashed()->whereIn('nombre', ['${ORG_ONLINE}', '${ORG_BORRADOR}'])->get() as $o) {`
  + ` foreach ($o->activities()->withTrashed()->get() as $a) { $a->registrations()->delete(); $a->collaborators()->delete(); $a->statusLogs()->delete(); $a->terms()->detach(); $a->forceDelete(); }`
  + ` App\\Models\\User::where('organization_id', $o->id)->update(['organization_id' => null]); $o->forceDelete(); }`
  + ` foreach (App\\Models\\User::withTrashed()->whereIn('email', ['${CORREO_ONLINE}', '${CORREO_BORRADOR}'])->get() as $u) {`
  + ` App\\Models\\AccessLog::where('user_id', $u->id)->orWhere('actor_id', $u->id)->delete(); $u->forceDelete(); }`
  + ` echo 'LIMPIO';`
);

const actividad = (titulo) => json(
  `$a = App\\Models\\Activity::with('terms')->where('titulo', '${titulo}')->first();`
  + ` echo json_encode($a ? ['id' => $a->id, 'slug' => $a->slug, 'estado' => $a->estado, 'formato' => $a->formato, 'region' => $a->region_id,`
  + ` 'comuna' => $a->commune_id, 'direccion' => $a->direccion, 'lat' => $a->latitud, 'descripcion' => $a->descripcion,`
  + ` 'temas' => $a->terms->where('grupo', 'tema')->count(), 'logs' => $a->statusLogs()->count(),`
  + ` 'autor' => $a->autor?->email, 'org' => $a->organization?->nombre] : null);`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const errores = [];
const p = await nav.newPage();
p.on('pageerror', (e) => errores.push(String(e)));
p.on('console', (m) => m.type() === 'error' && ! m.text().includes('Failed to load resource') && errores.push(m.text()));
await p.setViewport({ width: 1440, height: 900 });

/* ── ayudas ── */

const RAIZ = '[x-data^="wizard"], [x-data^="editorActividad"]';

const abrirWizard = async () => {
    await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
    await p.waitForFunction(() => window.Alpine !== undefined);
};

const irAPaso = async (n) => {
    await p.evaluate((n) => { Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso = n; }, n);
    await p.waitForFunction((n) => document.querySelector(`[data-paso="${n}"]`)?.offsetParent !== null, {}, n);
};

const paso = () => p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).paso);

const elegirFormato = async (f) => {
    await p.evaluate((f) => [...document.querySelectorAll('button.chip')].find((b) => b.textContent.trim() === f).click(), f);
    await esperar(150);
};

/** Cómo está el lugar ahora mismo, tal como lo ve la persona. */
const lugar = () => p.evaluate((raiz) => {
    const ve = (el) => !! el && el.offsetParent !== null;
    const caja = (c) => document.querySelector(`[data-campo="${c}"]`);
    const asterisco = (c) => {
        const s = caja(c)?.querySelector(':scope > span[x-show]');
        return !! s && getComputedStyle(s).display !== 'none';
    };
    return {
        bloque: ve(document.querySelector('[data-bloque-lugar]')),
        aviso: ve(document.querySelector('[data-aviso-online]')),
        desactivados: ['region_id', 'commune_id', 'direccion', 'latitud', 'longitud']
            .every((n) => document.querySelector(`[name="${n}"]`)?.disabled),
        activos: ['region_id', 'commune_id', 'direccion'].every((n) => ! document.querySelector(`[name="${n}"]`)?.disabled),
        asteriscos: ['region_id', 'commune_id', 'direccion'].map(asterisco),
        faltan: Alpine.$data(document.querySelector(raiz)).camposQueFaltan().map((e) => e.campo),
    };
}, RAIZ);

const textoResumen = () => p.evaluate(() => {
    const el = [...document.querySelectorAll('[data-resumen-errores]')].find((e) => e.offsetParent !== null);
    return el ? el.innerText.replace(/\s+/g, ' ').trim() : null;
});

const valores = (sel) => p.$$eval(sel + ' option', (o) => o.map((x) => x.value).filter(Boolean));

const elegirLugar = async () => {
    await p.select('select[name="region_id"]', (await valores('select[name="region_id"]'))[0]);
    await p.waitForFunction(() => [...document.querySelectorAll('select[name="commune_id"] option')].some((o) => o.value));
    await p.select('select[name="commune_id"]', (await valores('select[name="commune_id"]'))[0]);
    await p.evaluate(() => { const d = document.querySelector('input[name="direccion"]'); d.value = ''; });
    await p.type('input[name="direccion"]', 'Calle Falsa 123');
};

const marcarChips = () => p.evaluate(() => {
    for (const g of ['temas', 'caracteristicas', 'publicos']) {
        const caja = document.querySelector(`[data-campo="${g}"]`);
        if (! caja.querySelector('input[type=hidden]')) caja.querySelector('button.chip').click();
    }
});

/** El paso 3 entero, como alguien sin cuenta. */
const rellenarPaso3 = async (org, correo) => {
    await irAPaso(3);
    await p.type('input[name="org_nombre"]', org);
    await p.type('input[name="email"]', correo);
    await p.type('input[name="password"]', CLAVE);
    await p.type('input[name="password_confirmation"]', CLAVE);
    await (await p.$('input[name="org_logo"]')).uploadFile('public/img/logo-fundacion-trascender.png');
    await p.waitForFunction(() => {
        const d = Alpine.$data(document.querySelector('[data-campo="org_logo"]'));
        return d.tiene && ! d.reduciendo;
    }, { timeout: 8000 });
};

const pulsar = async (sel) => {
    await Promise.all([
        p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => null),
        p.evaluate((s) => document.querySelector(s).click(), sel),
    ]);
};

limpiar();

try {

/* ═════════════════════════════════════════════════════════════════════ */
t('A. El lugar según el formato — wizard');

await abrirWizard();
await irAPaso(4);

let l = await lugar();
di('Presencial: región, comuna y dirección a la vista', l.bloque && ! l.aviso);
di('Presencial: los tres habilitados', l.activos);
di('Presencial: los tres con asterisco', l.asteriscos.every(Boolean), l.asteriscos.join(','));
di('Presencial: la guía los pide', ['region_id', 'commune_id', 'direccion'].every((c) => l.faltan.includes(c)));

await elegirFormato('Online');
l = await lugar();
di('Online: el bloque del lugar se esconde', ! l.bloque);
di('Online: y se dice por qué', l.aviso);
di('Online: los cinco campos deshabilitados (no viajan)', l.desactivados);
di('Online: la guía ya no los pide', ! ['region_id', 'commune_id', 'direccion'].some((c) => l.faltan.includes(c)), l.faltan.join(','));

await elegirFormato('Híbrido');
l = await lugar();
di('Híbrido: vuelven, habilitados y con asterisco', l.bloque && l.activos && l.asteriscos.every(Boolean));
di('Híbrido: la guía los pide', ['region_id', 'commune_id', 'direccion'].every((c) => l.faltan.includes(c)));

await elegirFormato('Presencial');
await p.click('input[name="sin_fecha_definida"]');
await esperar(150);
l = await lugar();
di('Permanente: el lugar se ve pero sin asteriscos', l.bloque && l.activos && l.asteriscos.every((a) => ! a), l.asteriscos.join(','));
di('Permanente: la guía no los pide', ! ['region_id', 'commune_id', 'direccion'].some((c) => l.faltan.includes(c)));
await p.click('input[name="sin_fecha_definida"]');
await esperar(150);

// El resumen se repasa al cambiar de formato: lo que dejó de pedirse se va.
await p.evaluate(() => { const d = Alpine.$data(document.querySelector('[x-data^="wizard"]')); d.errores = d.camposQueFaltan(); });
const antes = await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).errores.map((e) => e.campo));
await elegirFormato('Online');
const despues = await p.evaluate(() => Alpine.$data(document.querySelector('[x-data^="wizard"]')).errores.map((e) => e.campo));
di('al pasar a Online, el resumen suelta región, comuna y dirección',
    antes.includes('region_id') && ! despues.some((c) => ['region_id', 'commune_id', 'direccion'].includes(c)),
    despues.join(','));

/* ═════════════════════════════════════════════════════════════════════ */
t('B. Las reglas del servidor dicen lo mismo, en los dos formularios');

const matriz = json(
  `$prueba = function ($clase, $d) { $q = $clase::create('', 'POST', $d);`
  + ` $e = Illuminate\\Support\\Facades\\Validator::make($q->all(), Illuminate\\Support\\Arr::only($q->rules(), ['region_id', 'commune_id', 'direccion']), $q->messages())->errors();`
  + ` return array_values(array_intersect(['region_id', 'commune_id', 'direccion'], $e->keys())); };`
  + ` $r = []; foreach (['App\\Http\\Requests\\PublishActivityRequest' => 'wizard', 'App\\Http\\Requests\\UpdateActivityRequest' => 'editor'] as $clase => $n) {`
  + ` $r[$n] = ['Presencial' => $prueba($clase, ['formato' => 'Presencial']), 'Híbrido' => $prueba($clase, ['formato' => 'Híbrido']),`
  + ` 'Online' => $prueba($clase, ['formato' => 'Online']), 'permanente' => $prueba($clase, ['formato' => 'Presencial', 'sin_fecha_definida' => '1'])]; }`
  + ` $m = (new App\\Http\\Requests\\UpdateActivityRequest)->messages(); $r['mensaje'] = $m['region_id.required'] ?? null;`
  + ` $r['campos'] = App\\Support\\LugarDeActividad::campos(['formato' => 'Online', 'commune_id' => App\\Models\\Commune::value('id'), 'direccion' => 'x', 'latitud' => 1]);`
  + ` echo json_encode($r);`
);

for (const f of ['wizard', 'editor']) {
    di(`${f}: Presencial exige los tres`, matriz[f].Presencial.length === 3, matriz[f].Presencial.join(','));
    di(`${f}: Híbrido exige los tres`, matriz[f]['Híbrido'].length === 3, matriz[f]['Híbrido'].join(','));
    di(`${f}: Online no exige ninguno`, matriz[f].Online.length === 0, matriz[f].Online.join(','));
    di(`${f}: permanente no exige ninguno`, matriz[f].permanente.length === 0, matriz[f].permanente.join(','));
}
di('el mensaje sigue en castellano', matriz.mensaje === 'Elige la región donde ocurre la actividad.', matriz.mensaje);
di('online no guarda lugar aunque llegue en el envío',
    Object.values(matriz.campos).every((v) => v === null), JSON.stringify(matriz.campos));

/* ═════════════════════════════════════════════════════════════════════ */
t('C. Una actividad online, de punta a punta');

await abrirWizard();
await rellenarPaso3(ORG_ONLINE, CORREO_ONLINE);
await irAPaso(4);
await p.type('input[name="titulo"]', TITULO_ONLINE);
await p.type('textarea[name="descripcion"]', 'Un taller por videollamada para la prueba.');
await p.type('input[name="fecha_inicio"]', '04122026');
await elegirLugar();          // algo escrito en el lugar...
await elegirFormato('Online'); // ...que al pasar a online no debe viajar
await marcarChips();

await pulsar('[data-paso="4"] button[type="submit"]:not([data-guardar-borrador])');
di('se envía sin lugar', p.url().includes('/listo'), p.url());

let a = actividad(TITULO_ONLINE);
di('guardada como Online', a?.formato === 'Online', a?.formato);
di('sin región, comuna, dirección ni punto', a && a.region === null && a.comuna === null && a.direccion === null && a.lat === null,
    JSON.stringify({ r: a?.region, c: a?.comuna, d: a?.direccion }));
di('la pantalla de enviado dice «Online»', (await p.$eval('main', (m) => m.innerText)).includes('Online'));

// Para mirarla en el sitio, publicada y con fecha próxima.
tinker(`App\\Models\\Activity::find(${a.id})->forceFill(['estado' => 'publicada', 'published_at' => now(), 'fecha_inicio' => now()->addDays(10)->toDateString()])->save(); echo 'ok';`);
const mes = new Date(Date.now() + 10 * 864e5).toISOString().slice(0, 7);

await p.goto(`${B}/actividades?formato=Online`, { waitUntil: 'networkidle2' });
const tarjeta = await p.evaluate((titulo) => {
    const h = [...document.querySelectorAll('.act-card h3')].find((e) => e.textContent.includes(titulo));
    return h ? h.closest('.act-card').innerText : null;
}, TITULO_ONLINE);
di('el listado la trae con el filtro «Online»', tarjeta !== null);
di('y la tarjeta dice «Online» donde iba la comuna', tarjeta?.includes('Online') && ! tarjeta?.includes('Por definir'), (tarjeta ?? '').replace(/\s+/g, ' ').slice(0, 120));

const region = tinker(`echo App\\Models\\Region::where('activo', true)->value('id');`).split('\n').pop().trim();
await p.goto(`${B}/actividades?region=${region}`, { waitUntil: 'networkidle2' });
di('al filtrar por una región no aparece (no está en ninguna)', ! (await p.content()).includes(TITULO_ONLINE));

await p.goto(`${B}/actividades?vista=calendario&mes=${mes}`, { waitUntil: 'networkidle2' });
di('el calendario la pinta en su mes', (await p.content()).includes(TITULO_ONLINE));

await p.goto(`${B}/actividades/${a.slug}`, { waitUntil: 'networkidle2' });
const ficha = await p.evaluate(() => document.body.innerText);
const donde = await p.evaluate(() => {
    const r = [...document.querySelectorAll('.helper')].find((e) => e.textContent.trim() === 'Dónde');
    return r ? r.parentElement.innerText.replace(/\s+/g, ' ').trim() : null;
});
di('la ficha dice «Dónde: Online»', donde === 'Dónde Online', donde);
di('sin enlace al mapa', ! ficha.includes('Ver en el mapa'));
di('y no ofrece «otras actividades cerca»', ! ficha.includes('Otras actividades cerca'));

const ics = await p.evaluate(async (u) => (await fetch(u)).text(), `${B}/actividades/${a.slug}/calendario.ics`);
di('el .ics lleva LOCATION:Online', ics.includes('LOCATION:Online'), (ics.match(/LOCATION:.*/) ?? [''])[0]);

// Exportar actividades: las celdas del lugar vacías y el formato dicho. La de
// inscripciones no lleva lugar (sólo título y fecha de la actividad).
const celdas = json(
  `$a = App\\Models\\Activity::find(${a.id}); $c = App\\Http\\Controllers\\Admin\\ActivityController::COLUMNAS_EXPORTACION;`
  + ` echo json_encode(array_combine($c, App\\Http\\Controllers\\Admin\\ActivityController::filaDeExportacion($a)));`
);
di('Exportar actividades: dirección, comuna y región vacías', celdas['Dirección'] === '' && celdas.Comuna === '' && celdas['Región'] === '',
    JSON.stringify([celdas['Dirección'], celdas.Comuna, celdas['Región']]));
di('Exportar actividades: el formato dice «Online»', celdas.Formato === 'Online', celdas.Formato);

await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
const fila = await p.evaluate((titulo) => {
    const c = [...document.querySelectorAll('.actcard')].find((e) => e.innerText.includes(titulo));
    return c ? c.innerText : '';
}, TITULO_ONLINE);
di('Mis actividades dice «Online»', fila.includes('Online'), fila.replace(/\s+/g, ' ').slice(0, 100));

/* ═════════════════════════════════════════════════════════════════════ */
t('A2. El lugar según el formato — editor de «Mi cuenta»');

await p.goto(`${B}/mi-cuenta/actividades/${a.id}/editar`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);

l = await lugar();
di('una online abre con el lugar escondido', ! l.bloque && l.aviso && l.desactivados);

await elegirFormato('Presencial');
l = await lugar();
di('Presencial: el lugar vuelve, vacío y obligatorio', l.bloque && l.activos && l.asteriscos.every(Boolean));
await pulsar('form button[type="submit"].btn-primary');
let resumen = await textoResumen();
di('guardar sin lugar se frena y lo dice', resumen?.includes('Región') && resumen?.includes('Comuna') && resumen?.includes('Dirección'), resumen);

await elegirLugar();
await pulsar('form button[type="submit"].btn-primary');
a = actividad(TITULO_ONLINE);
di('con lugar se guarda como Presencial', a.formato === 'Presencial' && a.comuna !== null && a.direccion === 'Calle Falsa 123',
    JSON.stringify({ f: a.formato, c: a.comuna }));

await p.goto(`${B}/mi-cuenta/actividades/${a.id}/editar`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
await elegirFormato('Híbrido');
l = await lugar();
di('Híbrido: el lugar sigue ahí, con lo guardado', l.bloque && l.activos && l.asteriscos.every(Boolean) && l.faltan.length === 0, l.faltan.join(','));

await elegirFormato('Online');
await pulsar('form button[type="submit"].btn-primary');
a = actividad(TITULO_ONLINE);
di('pasarla a Online borra el lugar guardado', a.formato === 'Online' && a.region === null && a.comuna === null && a.direccion === null,
    JSON.stringify({ r: a.region, c: a.comuna, d: a.direccion }));

/* ═════════════════════════════════════════════════════════════════════ */
t('D. «Guardar borrador» en el wizard');

// Sin sesión: se cierra la de la cuenta online.
await p.evaluate(async () => {
    await fetch('/publicar-actividad/salir', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' } });
});

await abrirWizard();
di('el paso 3 ya no tiene «Guardar borrador»',
    await p.$$eval('[data-paso="3"] button', (b) => ! b.some((x) => x.textContent.includes('Guardar borrador'))));
di('el paso 4 sí, y es un envío de verdad',
    await p.$eval('[data-paso="4"] [data-guardar-borrador]', (b) => b.type === 'submit' && ! b.disabled && b.getAttribute('formaction').endsWith('/publicar-actividad/borrador')));
di('Enter en un campo sigue enviando la actividad, no el borrador',
    await p.$eval('form', (f) => ! f.querySelector('button[type=submit]').hasAttribute('data-guardar-borrador')));

await irAPaso(4);
await p.type('input[name="titulo"]', TITULO_BORRADOR);
const urlAntes = p.url();
await p.click('[data-guardar-borrador]');
await esperar(600);
resumen = await textoResumen();
di('con el paso 3 vacío no se va a ninguna parte', p.url() === urlAntes);
di('lleva al paso 3', await paso() === 3, String(await paso()));
di('y dice por qué, hablando del borrador', resumen?.startsWith('Para guardar el borrador faltan'), resumen?.slice(0, 80));
di('nombrando lo que falta', ['Nombre de la organización', 'Correo electrónico', 'Contraseña'].every((x) => resumen?.includes(x)));
di('sin pedir lo que el borrador no exige', ! resumen?.includes('Descripción') && ! resumen?.includes('Tema'));

await abrirWizard();
await rellenarPaso3(ORG_BORRADOR, CORREO_BORRADOR);
await irAPaso(4);
await p.click('[data-guardar-borrador]');
await esperar(600);
resumen = await textoResumen();
di('sin nombre de la actividad tampoco: es lo único que exige', resumen?.startsWith('Para guardar el borrador falta 1 campo') && resumen?.includes('Nombre de la actividad'), resumen);
di('y se queda en el paso 4', await paso() === 4);

// El servidor también: una fecha imposible rebota y el resumen sigue diciendo «borrador».
await p.type('input[name="titulo"]', TITULO_BORRADOR);
await p.evaluate(() => { document.querySelector('input[name="fecha_inicio"]').value = '31 / 02 / 2026'; });
await pulsar('[data-guardar-borrador]');
resumen = await textoResumen();
di('una fecha imposible la rechaza el servidor', actividad(TITULO_BORRADOR) === null && resumen !== null, resumen?.slice(0, 90));
di('y al volver el resumen sigue hablando del borrador', resumen?.startsWith('Para guardar el borrador'));

// Al rebotar, el logo queda retenido; lo demás del paso 3 vuelve escrito.
await irAPaso(4);
await p.evaluate(() => { document.querySelector('input[name="fecha_inicio"]').value = ''; });
const tituloEscrito = await p.$eval('input[name="titulo"]', (i) => i.value);
if (! tituloEscrito) await p.type('input[name="titulo"]', TITULO_BORRADOR);
await p.evaluate(() => { for (const n of ['password', 'password_confirmation']) document.querySelector(`input[name="${n}"]`).value = ''; });
await irAPaso(3);
await p.type('input[name="password"]', CLAVE);
await p.type('input[name="password_confirmation"]', CLAVE);
await irAPaso(4);
await pulsar('[data-guardar-borrador]');

di('con el paso 3 y el nombre, se guarda', p.url().includes('/borrador-guardado'), p.url());
const pantalla = await p.evaluate(() => document.querySelector('[data-borrador-guardado]')?.innerText.replace(/\s+/g, ' ') ?? '');
di('dice que se creó la cuenta, con su correo', pantalla.includes('Creamos tu cuenta') && pantalla.includes(CORREO_BORRADOR));
di('dice que NO se envió a revisión', pantalla.includes('no la enviaste a revisión'));
di('dice dónde encontrarlo', pantalla.includes('Mi cuenta → Mis actividades → Borradores'));
di('dice cómo volver otro día', pantalla.includes('Para volver otro día') && pantalla.includes('Enviar a revisión'));
di('no promete ningún correo', ! /te enviamos|revisa tu correo|recibirás/i.test(pantalla));

a = actividad(TITULO_BORRADOR);
di('existe la actividad, en borrador', a?.estado === 'borrador', a?.estado);
di('de la cuenta y la organización nuevas', a?.autor === CORREO_BORRADOR && a?.org === ORG_BORRADOR);
di('con lo vacío vacío (descripción y temas)', a?.descripcion === '' && a?.temas === 0);
di('sin pasar por moderación (ningún movimiento)', a?.logs === 0, String(a?.logs));

await pulsar('[data-ir-a-borradores]');
di('«Ir a mis borradores» lleva al filtro Borradores', p.url().includes('filtro=Borradores'));
di('y ahí está', (await p.content()).includes(TITULO_BORRADOR));

/* ═════════════════════════════════════════════════════════════════════ */
t('D2. Volver desde Borradores, completar y enviar');

await p.goto(`${B}/mi-cuenta/actividades/${a.id}/editar`, { waitUntil: 'networkidle2' });
await p.waitForFunction(() => window.Alpine !== undefined);
const botones = await p.$$eval('form button[type=submit]', (b) => b.map((x) => x.textContent.trim()));
di('el editor ofrece «Guardar borrador» y «Enviar a revisión»',
    botones.includes('Guardar borrador') && botones.includes('Enviar a revisión') && ! botones.includes('Actualizar actividad'), botones.join(' | '));
di('el formulario aparte de «Enviar a revisión» ya no está',
    await p.$$eval('form[action$="/enviar"]', (f) => f.length === 0));

await pulsar('form button[name="enviar_revision"]');
resumen = await textoResumen();
di('enviarlo a medias se frena con la lista completa', resumen?.startsWith('Faltan') && resumen?.includes('Descripción'), resumen?.slice(0, 80));
di('y sigue en borrador', actividad(TITULO_BORRADOR).estado === 'borrador');

await p.type('textarea[name="descripcion"]', 'Lo que quedó escrito a medias.');
await pulsar('form [data-guardar-borrador]');
a = actividad(TITULO_BORRADOR);
di('«Guardar borrador» guarda lo escrito y no lo envía', a.descripcion === 'Lo que quedó escrito a medias.' && a.estado === 'borrador');
di('y vuelve al editor diciéndolo', p.url().endsWith('/editar') && (await p.content()).includes('Guardamos el borrador'));

// La ruta vieja de enviar no se salta las reglas.
const token = await p.$eval('meta[name="csrf-token"]', (m) => m.content);
await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle2' }),
    p.evaluate((u, tk) => {
        const f = document.createElement('form');
        f.method = 'POST'; f.action = u;
        f.innerHTML = `<input name="_token" value="${tk}">`;
        document.body.appendChild(f); f.submit();
    }, `${B}/mi-cuenta/actividades/${a.id}/enviar`, token),
]);
di('enviarlo a medias por la ruta directa tampoco cuela', actividad(TITULO_BORRADOR).estado === 'borrador');
resumen = await textoResumen();
di('y vuelve al editor con lo que falta', p.url().endsWith('/editar') && resumen?.includes('Tema'), resumen?.slice(0, 80));

await p.type('input[name="fecha_inicio"]', '04122026');
await elegirLugar();
await marcarChips();
await pulsar('form button[name="enviar_revision"]');
a = actividad(TITULO_BORRADOR);
di('completo, «Enviar a revisión» lo envía', a.estado === 'revision', a.estado);
di('y deja un movimiento en el historial', a.logs >= 1);
di('vuelve a Mis actividades con el aviso', p.url().includes('/mi-cuenta/actividades') && (await p.content()).includes('Enviamos tu actividad a revisión'));

/* ═════════════════════════════════════════════════════════════════════ */
t('D3. Borrador con la sesión ya abierta');

await abrirWizard();
di('con sesión el paso 4 ofrece el borrador igual', await p.$('[data-paso="4"] [data-guardar-borrador]') !== null);
await irAPaso(4);
await p.type('input[name="titulo"]', TITULO_BORRADOR_2);
await pulsar('[data-guardar-borrador]');
di('se guarda sin pedir cuenta', p.url().includes('/borrador-guardado'), p.url());
di('y no dice que se creó una cuenta', await p.$('[data-cuenta-creada]') === null);
a = actividad(TITULO_BORRADOR_2);
di('en la misma organización', a?.org === ORG_BORRADOR && a?.estado === 'borrador');

// Si ya no es borrador, esta pantalla ya no dice la verdad: al editor.
tinker(`App\\Models\\Activity::find(${a.id})->forceFill(['estado' => 'revision'])->save(); echo 'ok';`);
await p.goto(`${B}/publicar-actividad/${a.slug}/borrador-guardado`, { waitUntil: 'networkidle2' });
di('la pantalla de un ex borrador lleva al editor', p.url().endsWith('/editar'), p.url());

await nav.close();
const sinSesion = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const q = await sinSesion.newPage();
const r = await q.goto(`${B}/publicar-actividad/${a.slug}/borrador-guardado`);
di('sin ser su dueño: 404', r.status() === 404, String(r.status()));
await sinSesion.close();

di('sin errores de JavaScript', errores.length === 0, errores.slice(0, 3).join(' | '));

} finally {
    console.log('\n  ' + ultima(limpiar()));
    try { await nav.close(); } catch {}
}

console.log(`\n${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
