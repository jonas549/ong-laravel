// P20 — el correo que invita a evaluar cuando la actividad ya pasó.
//
// Hasta aquí la encuesta sólo se alcanzaba escaneando el QR del cartel: quien
// no lo escaneó ese día no tenía forma de volver. Se comprueban las tres
// cosas que pidió el punto: que el correo sale, que cuándo sale lo decide la
// ONG, y que no se repite.
//
//   node pruebas/invitacion-evaluacion.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const MAILPIT = process.env.DPS_MAILPIT ?? 'http://127.0.0.1:8025';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const artisan = (...args) => execFileSync(PHP, ['artisan', ...args], { encoding: 'utf8' }).trim();
const tinker = (php) => artisan('tinker', '--execute', php);
const ultima = (s) => s.split('\n').filter((l) => l.trim()).pop().trim();

/* ═══════════════ El ajuste, en el panel ══════════════════════════ */

t('P20 — cuándo sale lo decide la ONG');

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });

await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
await p.type('input[name="email"]', 'admin@ong-laravel.test');
await p.type('input[name="password"]', 'admin1234');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type="submit"]')]);
await p.goto(`${B}/admin/configuracion`, { waitUntil: 'networkidle2' });

const campo = await p.$('[name="evaluacion_invitacion_cuando"]');
di('El ajuste está en Configuración → General', campo !== null);

const opciones = campo
  ? await p.$$eval('[name="evaluacion_invitacion_cuando"] option', (n) => n.map((o) => ({ v: o.value, t: o.textContent.trim() })))
  : [];

di('Ofrece el mismo día y el día siguiente',
  opciones.some((o) => o.v === 'mismo_dia') && opciones.some((o) => o.v === 'dia_siguiente'),
  opciones.map((o) => o.t).join(' · '));
di('Y permite apagarlo', opciones.some((o) => o.v === 'no'));
di('Por defecto, el día siguiente',
  campo && await p.$eval('[name="evaluacion_invitacion_cuando"]', (n) => n.value) === 'dia_siguiente');

/* ═══════════════ La plantilla, editable ══════════════════════════ */

t('Y el texto lo puede redactar la ONG');

await p.goto(`${B}/admin/plantillas`, { waitUntil: 'networkidle2' });
const plantillas = await p.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
di('La plantilla está en el catálogo del panel',
  plantillas.includes('Invitación a evaluar la actividad'));

/* ═══════════════ El envío ════════════════════════════════════════ */

t('El correo sale, con el enlace de la encuesta');

// Una actividad publicada, con fecha y con inscritos.
const actividad = ultima(tinker(
  "$a = App\\Models\\Activity::published()->where('sin_fecha_definida', false)"
  + "->whereHas('registrations')->orderBy('fecha_inicio')->first();"
  + " echo $a ? $a->id.'|'.$a->slug.'|'.$a->fecha_inicio->toDateString() : 'NO';"
));

if (actividad === 'NO') {
  di('Hay una actividad con inscritos para probar', false, 'ninguna');
} else {
  const [id, slug, fecha] = actividad.split('|');

  // Se limpian las marcas de una pasada anterior para que la prueba se pueda
  // repetir: si no, la segunda vez no enviaría nada y parecería rota.
  tinker(
    `App\\Models\\Registration::where('activity_id',${id})->update(['invitacion_evaluacion_encolada_at' => null]);`
    + ` App\\Models\\EmailLog::where('plantilla','invitacion_evaluacion')->delete();`
    + ` echo 'limpio';`
  );

  /*
   * A Mailpit se le habla desde Node y no desde la pestaña: la página está en
   * el 8123 y Mailpit en el 8025, así que un `fetch` desde el navegador es de
   * otro origen y lo corta CORS.
   */
  await fetch(MAILPIT + '/api/v1/messages', { method: 'DELETE' }).catch(() => {});

  const salida = artisan('dps:invitar-evaluacion', `--fecha=${fecha}`);
  const cuantos = Number((salida.match(/Invitaciones encoladas: (\d+)/) ?? [0, 0])[1]);

  di('El comando encola invitaciones', cuantos > 0, `${cuantos} inscritos`);

  // Y no se repiten: es lo que evita escribirle tres veces a la misma persona.
  const repetido = artisan('dps:invitar-evaluacion', `--fecha=${fecha}`);
  di('**Una segunda pasada no vuelve a escribir**',
    /Invitaciones encoladas: 0/.test(repetido),
    (repetido.match(/Invitaciones encoladas: \d+\. Omitidas por duplicado: \d+/) ?? [''])[0]);

  artisan('queue:work', '--stop-when-empty');

  const buzon = await fetch(MAILPIT + '/api/v1/messages?limit=50')
    .then((r) => (r.ok ? r.json() : null))
    .catch(() => null);

  if (! buzon) {
    di('Mailpit está escuchando', false, 'no responde en ' + MAILPIT);
  } else {
    const invitaciones = (buzon.messages ?? []).filter((m) => /Cómo te fue en/.test(m.Subject));
    di('Los correos llegan al servidor', invitaciones.length === cuantos,
      `${invitaciones.length} de ${cuantos}`);

    if (invitaciones.length) {
      const cuerpo = await fetch(`${MAILPIT}/api/v1/message/${invitaciones[0].ID}`)
        .then((r) => r.json())
        .then((j) => j.HTML ?? j.Text ?? '')
        .catch(() => '');

      di('Con el enlace a la encuesta de esa actividad',
        cuerpo.includes(`/evaluar/${slug}`),
        (cuerpo.match(/https?:\/\/[^"']*evaluar[^"']*/) ?? ['(ninguno)'])[0]);
      di('Y con el nombre de la actividad', /Jornada|actividad/i.test(cuerpo));
    }
  }

  /* Y la encuesta a la que lleva, abierta. */
  await p.goto(`${B}/evaluar/${slug}`, { waitUntil: 'networkidle2' });
  di('El enlace lleva a una encuesta que se puede responder',
    (await p.$('form[action*="evaluar"]')) !== null);
}

/* ═══════════════ Apagarlo lo apaga ═══════════════════════════════ */

t('Con el ajuste en «no enviar», no sale nada');

tinker("App\\Models\\Setting::set('evaluacion_invitacion_cuando','no'); cache()->flush(); echo 'apagado';");

const apagado = artisan('dps:invitar-evaluacion');
di('El comando no manda nada y lo dice', /desactivada/.test(apagado), apagado.split('\n').pop().trim());

tinker("App\\Models\\Setting::set('evaluacion_invitacion_cuando','dia_siguiente'); cache()->flush(); echo 'restaurado';");

console.log('');
console.log(`  ${ok} bien, ${mal} mal`);
await nav.close();
process.exit(mal ? 1 : 0);
