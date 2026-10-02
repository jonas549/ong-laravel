// Puntos 11 y 12 de la tanda del 30/09. En Chrome.
//
//   11 · Panel → Organizaciones → «+ Nueva organización» crea una
//        organización sin cuenta, como las importadas, y alguien sin cuenta la
//        encuentra en el wizard, la reclama y publica con ella.
//   12 · Quien publica con un nombre que no está en la lista crea la
//        organización al enviar, y la siguiente persona la ve en el
//        autocompletado (como «Ya tiene cuenta»).
//
//   node pruebas/organizaciones-panel.mjs
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const W = '[x-data^="wizard"]';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(66)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (m) => new Promise((r) => setTimeout(r, m));
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').pop();

const SELLO = Date.now();
const NUEVA_PANEL = `Fundación Desde El Panel ${SELLO}`;
const NUEVA_WIZARD = `Agrupación Al Vuelo ${SELLO}`;
const C1 = `reclama.${SELLO}@ejemplo.cl`;
const C2 = `alvuelo.${SELLO}@ejemplo.cl`;
const CLAVE = 'organizaciones-panel-2026';
const LOGO = tinker("echo App\\Models\\Organization::whereNotNull('logo_path')->value('logo_path');");

const limpiar = () => tinker(
  `foreach (App\\Models\\Organization::withTrashed()->whereIn('nombre', ['${NUEVA_PANEL}', '${NUEVA_WIZARD}'])->get() as $o) {`
  + ` foreach ($o->activities()->withTrashed()->get() as $a) { $a->terms()->detach(); $a->collaborators()->delete(); $a->statusLogs()->delete(); $a->forceDelete(); } $o->forceDelete(); }`
  + ` foreach (['${C1}', '${C2}'] as $c) { $u = App\\Models\\User::where('email', $c)->first(); if ($u) { App\\Models\\AccessLog::where('user_id', $u->id)->delete(); $u->forceDelete(); } }`
  + ` echo 'LIMPIO';`
);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));

const alp = (src, ...a) => p.evaluate((W, src, ...a) => new Function('d', '...a', src)(Alpine.$data(document.querySelector(W)), ...a), W, src, ...a);
const irA = async (n) => { await alp('d.paso = a[0]', n); await esperar(250); };
const valores = (sel) => p.$$eval(sel + ' option', (o) => o.map((x) => x.value).filter(Boolean));
const sinSesion = async () => { for (const k of await p.cookies()) await p.deleteCookie(k); };

const buscar = async (texto) => {
  await p.click('input[name="org_nombre"]', { clickCount: 3 });
  await p.keyboard.down('Control'); await p.keyboard.press('A'); await p.keyboard.up('Control');
  await p.keyboard.press('Backspace');
  await p.type('input[name="org_nombre"]', texto);
  await p.waitForFunction((W, t) => { const d = Alpine.$data(document.querySelector(W)); return ! d.buscando && d.sugerencias.some((s) => s.nombre === t); },
    { timeout: 10000 }, W, texto).catch(() => null);
  return alp('return JSON.parse(JSON.stringify(d.sugerencias))');
};

/** Rellena el wizard sin sesión hasta el envío; `org` decide si se elige del buscador o se escribe nueva. */
const publicar = async ({ correo, org, elegir, titulo }) => {
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await p.waitForFunction(() => window.Alpine !== undefined);
  await alp("d.tipo = 'Otra'");
  await irA(3);
  const sug = await buscar(org);
  if (elegir) {
    await alp('d.elegirOrg(d.sugerencias.find((s) => s.nombre === a[0]))', org);
    await esperar(300);
  }
  const reclamando = await alp('return d.reclamando');
  // Con «Otra» hay que describirla, se reclame o no: la del panel no traía tipo.
  await p.type('input[name="org_tipo_otro"]', 'Junta de vecinos');
  await p.type('input[name="email"]', correo);
  await p.type('input[name="password"]', CLAVE);
  await p.type('input[name="password_confirmation"]', CLAVE);
  await irA(4);
  await p.type('input[name="titulo"]', titulo);
  await p.type('textarea[name="descripcion"]', 'Prueba de organizaciones del panel.');
  await p.type('input[name="fecha_inicio"]', '04122026');
  await p.select('select[name="region_id"]', (await valores('select[name="region_id"]'))[0]);
  await p.waitForFunction(() => [...document.querySelectorAll('select[name="commune_id"] option')].some((o) => o.value));
  await p.select('select[name="commune_id"]', (await valores('select[name="commune_id"]'))[0]);
  await p.type('input[name="direccion"]', 'Calle Falsa 123');
  await p.evaluate(() => { for (const g of ['temas', 'caracteristicas', 'publicos']) document.querySelector(`[data-campo="${g}"] button.chip`).click(); });
  await esperar(200);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => null), p.click('button[type="submit"]')]);
  const avisos = await p.$$eval('.field-error, [data-resumen-errores] li', (n) => n.filter((e) => e.offsetParent).map((e) => e.innerText.trim()).filter(Boolean));
  return { sug, reclamando, url: p.url().replace(B, ''), avisos };
};

try {
  t('11 · Crear una organización desde el panel');
  await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle2' });
  await p.type('input[type="email"]', ADMIN);
  await p.type('input[type="password"]', CLAVE_ADMIN);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[method="POST"]').submit())]);

  await p.goto(`${B}/admin/organizaciones`, { waitUntil: 'networkidle2' });
  const boton = await p.$eval('[data-nueva-organizacion]', (a) => ({ texto: a.innerText.trim(), href: a.getAttribute('href') }));
  di('el listado tiene «+ Nueva organización»', boton.texto.includes('Nueva organización'), JSON.stringify(boton));
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('[data-nueva-organizacion]')]);
  di('lleva a /admin/organizaciones/crear', p.url().endsWith('/admin/organizaciones/crear'), p.url().replace(B, ''));

  await p.type('[data-crear-organizacion] [name="nombre"]', NUEVA_PANEL);
  await p.type('[data-crear-organizacion] [name="enlace_web"]', 'desdeelpanel.cl');
  await p.type('[data-crear-organizacion] [name="anios_participacion"]', '2026');
  // El logo, elegido de la biblioteca: el selector deja la ruta en su campo oculto.
  await p.evaluate((ruta) => { const i = document.querySelector('[data-crear-organizacion] input[type="hidden"][name="logo_path"]');
    const raiz = i.closest('[x-data]'); Alpine.$data(raiz).ruta = ruta; }, LOGO);
  await esperar(200);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('[data-crear-organizacion] button[type="submit"]')]);
  di('guarda y lleva a su ficha con un aviso', /\/admin\/organizaciones\/\d+\/editar$/.test(p.url())
    && (await p.evaluate(() => document.body.innerText)).includes('ya está en el listado, sin cuenta'), p.url().replace(B, ''));

  const fila = JSON.parse(tinker(`echo json_encode(App\\Models\\Organization::where('nombre', '${NUEVA_PANEL}')->first()?->only(['user_id','verificada','activo','tipo','enlace_web','anios_participacion','logo_path','slug']));`));
  di('sin cuenta, sin verificar y activa, como las importadas', fila && fila.user_id === null && fila.verificada === false && fila.activo === true, JSON.stringify(fila));
  di('con su logo, su web (con https://) y sus años', fila?.logo_path === LOGO && fila?.enlace_web === 'https://desdeelpanel.cl' && fila?.anios_participacion === '2026');
  di('el tipo puede quedar vacío', fila?.tipo === null);

  await p.goto(`${B}/admin/organizaciones/crear`, { waitUntil: 'networkidle2' });
  await p.type('[data-crear-organizacion] [name="nombre"]', NUEVA_PANEL.toUpperCase());
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('[data-crear-organizacion] button[type="submit"]')]);
  di('el mismo nombre (en mayúsculas) no se repite', (await p.evaluate(() => document.body.innerText)).includes('Ya hay una organización con ese nombre'));

  t('11 · Alguien la reclama en el wizard');
  await sinSesion();
  let r = await publicar({ correo: C1, org: NUEVA_PANEL, elegir: true, titulo: `Actividad reclamada ${SELLO}` });
  const ofrecida = r.sug.find((s) => s.nombre === NUEVA_PANEL);
  di('el buscador la ofrece como libre', ofrecida?.libre === true, JSON.stringify(ofrecida));
  di('al elegirla, se reclama', r.reclamando);
  di('publica', /\/listo$/.test(r.url) && r.avisos.length === 0, `${r.url} ${r.avisos.join(' / ')}`);
  const tras = JSON.parse(tinker(`$o = App\\Models\\Organization::where('nombre', '${NUEVA_PANEL}')->first(); echo json_encode(['dueno' => $o->user?->email, 'n' => App\\Models\\Organization::where('nombre', '${NUEVA_PANEL}')->count(), 'tipo' => $o->tipo]);`));
  di('queda con la cuenta nueva, sin duplicarse', tras.dueno === C1 && tras.n === 1, JSON.stringify(tras));
  di('y con el tipo que se eligió en el paso 2', tras.tipo === 'Otra');

  t('12 · Un nombre que no está en la lista');
  await sinSesion();
  r = await publicar({ correo: C2, org: NUEVA_WIZARD, elegir: false, titulo: `Actividad al vuelo ${SELLO}` });
  di('no estaba en el buscador', ! r.sug.some((s) => s.nombre === NUEVA_WIZARD));
  di('se publica igual', /\/listo$/.test(r.url) && r.avisos.length === 0, `${r.url} ${r.avisos.join(' / ')}`);
  const nueva = JSON.parse(tinker(`$o = App\\Models\\Organization::where('nombre', '${NUEVA_WIZARD}')->first(); echo json_encode(['dueno' => $o?->user?->email, 'activo' => $o?->activo]);`));
  di('la organización se creó al enviar, con su cuenta', nueva.dueno === C2 && nueva.activo === true, JSON.stringify(nueva));

  await sinSesion();
  await p.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle2' });
  await irA(3);
  const sig = (await buscar('Agrupación Al Vuelo')).find((s) => s.nombre === NUEVA_WIZARD);
  di('la siguiente persona la ve en el autocompletado', !! sig, JSON.stringify(sig));
  di('marcada «Ya tiene cuenta» (no se puede reclamar otra vez)', sig?.libre === false);

  t('Consola');
  di('Sin errores de JavaScript', errores.length === 0, errores.slice(0, 3).join(' | '));
} catch (err) {
  di('La prueba terminó sin excepciones', false, String(err).slice(0, 200));
} finally {
  await nav.close();
  console.log('');
  console.log(limpiar());
  console.log(`\n${ok} OK · ${mal} MAL`);
  process.exit(mal ? 1 : 0);
}
