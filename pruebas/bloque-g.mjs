// Bloque G — los once CRUD, en Chrome real.
//
// Para cada uno: el listado carga y filtra, se crea, se edita, se apaga, se
// enciende, se elimina, se restaura. Y lo que de verdad importa en los seis que
// están conectados al home: que el cambio se vea en el sitio público.
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = 'http://127.0.0.1:8123';
// Las capturas van al temporal del sistema salvo que se diga otra cosa:
// versionado, no puede apuntar al scratchpad de una sesión concreta.
const S = process.env.DPS_SALIDA ?? tmpdir();
const MYSQL = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(56)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const nav = await puppeteer.launch({
  executablePath: CHROME,
  headless: 'new',
  args: ['--no-sandbox'],
  // Un dialogo nativo sin atender bloquea la pagina y CDP acaba dando
  // «Runtime.callFunctionOn timed out», que no dice nada de la causa.
  protocolTimeout: 30000,
});
const errores = [];
const p = await nav.newPage();
p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
p.on('pageerror', (e) => errores.push(String(e)));
// Los dialogos nativos bloquean la pagina hasta que alguien los atiende.
p.on('dialog', async (d) => { console.log(`  [dialogo ${d.type()}] ${d.message().slice(0, 80)}`); await d.accept(); });
await p.setViewport({ width: 1440, height: 1000 });

await p.goto(`${B}/admin/login`, { waitUntil: 'networkidle0' });
await p.type('input[name=email]', 'admin@ong-laravel.test');
await p.type('input[name=password]', 'admin1234');
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.click('button[type=submit]')]);

const publico = await nav.newPage();
await publico.setViewport({ width: 1440, height: 1000 });
/*
 * Busca el texto en el home, tambien en los `alt` de las imagenes.
 *
 * Un partner con logo se pinta como <img alt="su nombre">, no como texto: sin
 * mirar el alt parecia que el CRUD no llegaba al sitio publico cuando si
 * llegaba.
 */
const enElHome = async (texto) => {
  await publico.goto(B, { waitUntil: 'networkidle0' });
  return publico.evaluate((x) => {
    const alts = [...document.querySelectorAll('img[alt]')].map((i) => i.alt).join(' | ');
    return document.body.textContent.includes(x) || alts.includes(x);
  }, texto);
};

/*
 * Se enfoca por `evaluate` y se teclea con el teclado, sin `page.click()`.
 *
 * `click()` llama por dentro a `scrollIntoViewIfNeeded` y espera a que el
 * elemento se quede quieto; con `html { scroll-behavior: smooth }` —que este
 * sitio tiene— esa espera se puede eternizar, y CDP acaba dando
 * «Runtime.callFunctionOn timed out», que no dice nada de la causa.
 */
const escribir = async (sel, texto) => {
  await p.evaluate((s) => {
    const e = document.querySelector(s);
    if (!e) throw new Error('no existe ' + s);
    e.focus();
    e.value = '';
  }, sel);

  if (texto) await p.keyboard.type(texto);
};

/*
 * Pulsa el boton visible que dice ese texto.
 *
 * Lo de «visible» no es adorno: «Esconder» y «Restaurar» estan dos veces en la
 * pagina, una en la fila y otra en la barra de acciones masivas, y esa barra
 * solo aparece cuando hay algo marcado. Sin filtrar, la prueba pulsaba a veces
 * la que no era.
 */
/*
 * Abre el asistente publico en una sesion limpia.
 *
 * Con la sesion de admin abierta, `/publicar-actividad` no pinta el mismo
 * formulario, y las comprobaciones de «ya no se ofrece» pasaban solas por no
 * encontrar ningun selector.
 */
const deIncognito = async () => {
  const contexto = await nav.createBrowserContext();
  const pagina = await contexto.newPage();
  await pagina.setViewport({ width: 1440, height: 1000 });
  await pagina.goto(`${B}/publicar-actividad`, { waitUntil: 'networkidle0' });

  return pagina;
};

const pulsar = async (texto) => {
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle0' }),
    p.evaluate((x) => {
      const todos = [...document.querySelectorAll('button, a.btn')].filter((e) => e.textContent.trim() === x);
      const b = todos.find((e) => e.offsetParent !== null) ?? todos[0];
      if (!b) throw new Error('no hay ningun boton que diga ' + x);
      b.click();
    }, texto),
  ]);
};

/* ══════════════ los siete del controlador generico ══════════════ */

const GENERICOS = [
  { tipo: 'noticias', etiqueta: 'titulo', enHome: true, orden: false },
  { tipo: 'ediciones', etiqueta: 'titulo', enHome: true, orden: false },
  { tipo: 'testimonios', etiqueta: 'autor', enHome: true, orden: true },
  { tipo: 'partners', etiqueta: 'nombre', enHome: true, orden: true },
  { tipo: 'cifras', etiqueta: 'etiqueta', enHome: true, orden: true },
  { tipo: 'tarjetas', etiqueta: 'titulo', enHome: true, orden: true },
  { tipo: 'paginas', etiqueta: 'titulo', enHome: false, orden: false },
];

for (const crud of GENERICOS) {
  t(`CRUD «${crud.tipo}»`);

  const MARCA = `ZZPrueba ${crud.tipo}`;
  const tabla = { noticias: 'posts', ediciones: 'editions', testimonios: 'testimonials', partners: 'partners', cifras: 'stats', tarjetas: 'participation_cards', paginas: 'pages' }[crud.tipo];
  const limpiar = () => sql(`DELETE FROM ${tabla} WHERE ${crud.etiqueta} LIKE 'ZZPrueba%'`);
  limpiar();

  await p.goto(`${B}/admin/contenido/${crud.tipo}`, { waitUntil: 'networkidle0' });
  di('el listado carga', (await p.evaluate(() => !!document.querySelector('.panel-tabla'))));

  // ── crear ──
  await p.goto(`${B}/admin/contenido/${crud.tipo}/nuevo`, { waitUntil: 'networkidle0' });

  /*
   * Solo los campos DEL FORMULARIO. `[name]` a secas coge tambien las etiquetas
   * <meta name="viewport"> y <meta name="csrf-token">, y clicar un <meta>
   * revienta el arrastre de la prueba sin decir por que.
   */
  const campos = await p.evaluate(() => [...document.querySelectorAll('main form.card, main section.card form, main form')]
    .flatMap((f) => [...f.querySelectorAll('input[name], textarea[name], select[name]')])
    .map((e) => ({ n: e.name, t: e.tagName === 'SELECT' ? 'select' : e.type }))
    .filter((c) => !['_token', '_method'].includes(c.n) && c.t !== 'hidden' && c.t !== 'checkbox'));

  for (const { n, t } of campos) {
    const sel = `main [name="${n}"]`;

    if (t === 'select') { await p.evaluate((c) => { const e = document.querySelector(`main [name="${c}"]`); if (e.options[1]) e.value = e.options[1].value; }, n); continue; }
    /*
     * Un numero tiene que caer dentro de su propio `min`/`max`, o el navegador
     * no deja enviar el formulario y la prueba se queda esperando una
     * navegacion que nunca llega. El «anio» de las ediciones pide 2000..2100.
     */
    if (t === 'number') {
      const valor = await p.evaluate((c) => {
        const e = document.querySelector(`main [name="${c}"]`);
        const min = e.min === '' ? null : Number(e.min);
        const max = e.max === '' ? null : Number(e.max);
        let v = 3;
        if (min !== null && v < min) v = min;
        if (max !== null && v > max) v = max;
        return String(v);
      }, n);
      await escribir(sel, valor);
      continue;
    }
    // Un `url` o un `email` mal formado tampoco deja enviar el formulario.
    if (t === 'url') { await escribir(sel, 'https://ejemplo.test/zzprueba'); continue; }
    if (t === 'email') { await escribir(sel, 'zzprueba@ejemplo.test'); continue; }
    if (t === 'date') { await escribir(sel, '2026-08-24'); continue; }
    if (t === 'datetime-local') { await p.evaluate((c) => { document.querySelector(`main [name="${c}"]`).value = '2026-08-24T10:00'; }, n); continue; }
    await escribir(sel, n === crud.etiqueta ? MARCA : `texto ${n}`);
  }

  // El interruptor de visible, marcado.
  await p.evaluate(() => { const c = document.querySelector('input[type=checkbox][name=activo]'); if (c && !c.checked) c.click(); });

  await pulsar('Crear');
  const creado = Number(sql(`SELECT COUNT(*) FROM ${tabla} WHERE ${crud.etiqueta} LIKE 'ZZPrueba%'`));
  di('crear guarda en la base', creado === 1, `${creado} fila(s)`);

  const id = sql(`SELECT id FROM ${tabla} WHERE ${crud.etiqueta} LIKE 'ZZPrueba%' LIMIT 1`);

  if (crud.enHome) di('y se ve en el home público', await enElHome(MARCA));

  // ── editar ──
  await p.goto(`${B}/admin/contenido/${crud.tipo}/${id}/editar`, { waitUntil: 'networkidle0' });
  await escribir(`[name="${crud.etiqueta}"]`, `${MARCA} editado`);
  await pulsar('Guardar cambios');
  di('editar guarda el cambio', sql(`SELECT ${crud.etiqueta} FROM ${tabla} WHERE id=${id}`) === `${MARCA} editado`);

  // ── apagar / encender ──
  const tieneActivo = sql(`SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='ong_laravel' AND TABLE_NAME='${tabla}' AND COLUMN_NAME='activo'`) === '1';

  if (tieneActivo) {
    await p.goto(`${B}/admin/contenido/${crud.tipo}?q=ZZPrueba`, { waitUntil: 'networkidle0' });
    await pulsar('Esconder');
    di('esconder lo apaga en la base', sql(`SELECT activo FROM ${tabla} WHERE id=${id}`) === '0');
    if (crud.enHome) di('y deja de verse en el home', !(await enElHome(`${MARCA} editado`)));

    await p.goto(`${B}/admin/contenido/${crud.tipo}?q=ZZPrueba`, { waitUntil: 'networkidle0' });
    await pulsar('Mostrar');
    di('mostrar lo vuelve a encender', sql(`SELECT activo FROM ${tabla} WHERE id=${id}`) === '1');
  }

  // ── eliminar (blando) y restaurar ──
  await p.goto(`${B}/admin/contenido/${crud.tipo}?q=ZZPrueba`, { waitUntil: 'networkidle0' });
  await p.evaluate(() => [...document.querySelectorAll('tbody button')].find((b) => b.textContent.trim() === 'Borrar')?.click());
  await esperar(400);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.evaluate(() => document.querySelector('.dialogo button[type=submit]')?.click())]);

  di('eliminar es en blando, no borra la fila', sql(`SELECT COUNT(*) FROM ${tabla} WHERE id=${id}`) === '1'
      && sql(`SELECT deleted_at IS NOT NULL FROM ${tabla} WHERE id=${id}`) === '1');
  di('y desaparece del listado normal', !(await p.evaluate((x) => document.querySelector('tbody')?.textContent.includes(x), MARCA)));
  if (crud.enHome) di('y del home público', !(await enElHome(`${MARCA} editado`)));

  await p.goto(`${B}/admin/contenido/${crud.tipo}?papelera=eliminados`, { waitUntil: 'networkidle0' });
  di('el filtro de la papelera lo enseña', await p.evaluate((x) => document.querySelector('tbody')?.textContent.includes(x), MARCA));
  await pulsar('Restaurar');
  di('restaurar lo devuelve', sql(`SELECT deleted_at IS NULL FROM ${tabla} WHERE id=${id}`) === '1');

  limpiar();
}

/* ══════════════════════ taxonomias ══════════════════════ */

t('CRUD «taxonomías»');

sql("DELETE FROM taxonomy_terms WHERE nombre LIKE 'ZZTerm%'");
await p.goto(`${B}/admin/taxonomias?grupo=tema`, { waitUntil: 'networkidle0' });
di('el listado carga con sus pestañas', await p.evaluate(() => document.querySelectorAll('.tab').length >= 4));

await escribir('main aside [name=nombre]', 'ZZTerm de prueba');
await pulsar('Agregar');
di('crear un término', sql("SELECT COUNT(*) FROM taxonomy_terms WHERE nombre='ZZTerm de prueba'") === '1');

const idTerm = sql("SELECT id FROM taxonomy_terms WHERE nombre='ZZTerm de prueba'");

await p.goto(`${B}/admin/taxonomias?grupo=tema&q=ZZTerm`, { waitUntil: 'networkidle0' });
await escribir('main tbody [name=nombre]', 'ZZTerm editado');
await pulsar('Guardar');
di('editar el nombre', sql(`SELECT nombre FROM taxonomy_terms WHERE id=${idTerm}`) === 'ZZTerm editado');

await p.goto(`${B}/admin/taxonomias?grupo=tema&q=ZZTerm`, { waitUntil: 'networkidle0' });
await pulsar('Apagar');
di('apagar lo quita de los selectores', sql(`SELECT activo FROM taxonomy_terms WHERE id=${idTerm}`) === '0');

const enWizard = await deIncognito();
const opcionesTema = await enWizard.evaluate(() => [...document.querySelectorAll('option, label')].map((o) => o.textContent.trim()));
di('el asistente publico ofrece opciones', opcionesTema.length > 4, `${opcionesTema.length}`);
di('y el termino apagado ya no sale al publicar', !opcionesTema.includes('ZZTerm editado'));
await enWizard.close();

// Un termino EN USO no se puede borrar.
const idTema = sql("SELECT id FROM taxonomy_terms WHERE grupo='tema' AND id<>" + idTerm + " LIMIT 1");
const enUso = Number(sql(`SELECT COUNT(*) FROM activity_taxonomy_term WHERE taxonomy_term_id=${idTema}`));
if (enUso > 0) {
  await p.goto(`${B}/admin/taxonomias?grupo=tema`, { waitUntil: 'networkidle0' });
  const sinBoton = await p.evaluate((id) => {
    const fila = document.querySelector(`tr[data-fila="${id}"]`);
    return fila ? !fila.textContent.includes('Borrar') && fila.textContent.includes('En uso') : null;
  }, idTema);
  di('un término en uso no ofrece borrar', sinBoton === true);
}

await p.goto(`${B}/admin/taxonomias?grupo=tema&q=ZZTerm`, { waitUntil: 'networkidle0' });
await p.evaluate(() => [...document.querySelectorAll('tbody button')].find((b) => b.textContent.trim() === 'Borrar')?.click());
await esperar(400);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.evaluate(() => document.querySelector('.dialogo button[type=submit]')?.click())]);
di('uno sin uso sí se elimina, en blando', sql(`SELECT deleted_at IS NOT NULL FROM taxonomy_terms WHERE id=${idTerm}`) === '1');

sql("DELETE FROM taxonomy_terms WHERE nombre LIKE 'ZZTerm%'");

/* ══════════════════════ regiones y comunas ══════════════════════ */

t('CRUD «regiones y comunas»');

await p.goto(`${B}/admin/regiones`, { waitUntil: 'networkidle0' });
di('el listado de comunas carga', await p.evaluate(() => document.querySelectorAll('tbody tr').length > 0));
di('no ofrece crear ni borrar', await p.evaluate(() => {
  const t = document.body.textContent;
  return !t.includes('Agregar') && ![...document.querySelectorAll('tbody button')].some((b) => b.textContent.includes('Borrar'));
}));

await p.goto(`${B}/admin/regiones?q=Santiago`, { waitUntil: 'networkidle0' });
const idComuna = sql("SELECT id FROM communes WHERE nombre='Santiago' LIMIT 1");
await pulsar('Apagar');
di('apagar una comuna', sql(`SELECT activo FROM communes WHERE id=${idComuna}`) === '0');

const wizard2 = await deIncognito();
const opciones = await wizard2.evaluate(() => [...document.querySelectorAll('option')].map((o) => o.textContent.trim()));
di('el asistente publico ofrece comunas', opciones.length > 4, `${opciones.length} opciones`);
di('y la comuna apagada deja de ofrecerse', !opciones.includes('Santiago'));
await wizard2.close();

await p.goto(`${B}/admin/regiones?q=Santiago`, { waitUntil: 'networkidle0' });
await pulsar('Encender');
di('encenderla la devuelve', sql(`SELECT activo FROM communes WHERE id=${idComuna}`) === '1');

/* ══════════════════════ usuarios ══════════════════════ */

t('CRUD «usuarios»');

sql("DELETE FROM users WHERE email='zzprueba@ejemplo.test'");
await p.goto(`${B}/admin/usuarios?rol=organizer`, { waitUntil: 'networkidle0' });
di('el listado carga', await p.evaluate(() => !!document.querySelector('.panel-tabla')));

await escribir('main aside [name=name]', 'ZZ Usuario Prueba');
await escribir('main aside [name=email]', 'zzprueba@ejemplo.test');
await escribir('main aside [name=password]', 'prueba1234');
await pulsar('Crear usuario');
const idUser = sql("SELECT COALESCE((SELECT id FROM users WHERE email='zzprueba@ejemplo.test'),0)");
di('crear un usuario', idUser !== '0', `id ${idUser}`);

if (idUser !== '0') {
  await p.goto(`${B}/admin/usuarios?rol=organizer&q=zzprueba`, { waitUntil: 'networkidle0' });
  await pulsar('Desactivar');
  di('desactivar', sql(`SELECT is_active FROM users WHERE id=${idUser}`) === '0');

  await p.goto(`${B}/admin/usuarios?rol=organizer&q=zzprueba`, { waitUntil: 'networkidle0' });
  await pulsar('Activar');
  di('activar', sql(`SELECT is_active FROM users WHERE id=${idUser}`) === '1');

  await p.goto(`${B}/admin/usuarios?rol=organizer&q=zzprueba`, { waitUntil: 'networkidle0' });
  await p.evaluate(() => [...document.querySelectorAll('tbody button')].find((b) => b.textContent.trim() === 'Borrar')?.click());
  await esperar(400);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.evaluate(() => document.querySelector('.dialogo button[type=submit]')?.click())]);
  di('eliminar es en blando', sql(`SELECT deleted_at IS NOT NULL FROM users WHERE id=${idUser}`) === '1');

  const intento = await nav.newPage();
  await intento.goto(`${B}/mi-cuenta/login`, { waitUntil: 'networkidle0' });
  await intento.type('input[name=email]', 'zzprueba@ejemplo.test');
  await intento.type('input[name=password]', 'prueba1234');
  await Promise.all([intento.waitForNavigation({ waitUntil: 'networkidle0' }), intento.click('button[type=submit]')]);
  di('y la cuenta eliminada ya no puede entrar', intento.url().includes('login'));
  await intento.close();

  await p.goto(`${B}/admin/usuarios?rol=organizer&papelera=eliminados`, { waitUntil: 'networkidle0' });
  await pulsar('Restaurar');
  di('restaurar la devuelve', sql(`SELECT deleted_at IS NULL FROM users WHERE id=${idUser}`) === '1');
}

// No se puede eliminar la propia cuenta.
await p.goto(`${B}/admin/usuarios?rol=admin`, { waitUntil: 'networkidle0' });
di('la cuenta propia no ofrece borrar', await p.evaluate(() => document.body.textContent.includes('Tu cuenta')));

sql("DELETE FROM users WHERE email='zzprueba@ejemplo.test'");

/* ══════════════════════ organizaciones ══════════════════════ */

t('CRUD «organizaciones»');

await p.goto(`${B}/admin/organizaciones`, { waitUntil: 'networkidle0' });
di('el listado carga', await p.evaluate(() => !!document.querySelector('.panel-tabla')));

const conActividades = sql("SELECT COALESCE((SELECT o.id FROM organizations o JOIN activities a ON a.organization_id=o.id GROUP BY o.id LIMIT 1),0)");
if (conActividades !== '0') {
  const fila = await p.evaluate(() => {
    const filas = [...document.querySelectorAll('tbody tr')];
    const f = filas.find((x) => x.textContent.includes('No se puede eliminar'));
    return f ? f.textContent.replace(/\s+/g, ' ').slice(0, 60) : null;
  });
  di('una organización con actividades no ofrece borrar', fila !== null, fila ?? '');
}

// Una vacía sí.
// `organizations.user_id` es NOT NULL y cuelga de `users` en cascada, asi que
// la organizacion de prueba trae su propia cuenta y se van las dos juntas.
const limpiarOrg = () => {
  sql("DELETE FROM organizations WHERE nombre LIKE 'ZZOrg%'");
  sql("DELETE FROM users WHERE email='zzorg@ejemplo.test'");
};
limpiarOrg();
sql("INSERT INTO users (name, email, password, role, is_active, created_at, updated_at) VALUES ('ZZ Org','zzorg@ejemplo.test','x','organizer',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
const idUserOrg = sql("SELECT id FROM users WHERE email='zzorg@ejemplo.test'");
sql(`INSERT INTO organizations (user_id, nombre, slug, tipo, activo, created_at, updated_at) VALUES (${idUserOrg},'ZZOrg vacia','zzorg-vacia','Otra',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())`);
const idOrg = sql("SELECT id FROM organizations WHERE nombre='ZZOrg vacia'");

await p.goto(`${B}/admin/organizaciones?q=ZZOrg`, { waitUntil: 'networkidle0' });
di('una organización vacía sí ofrece borrar', await p.evaluate(() => [...document.querySelectorAll('tbody button')].some((b) => b.textContent.trim() === 'Borrar')));

await pulsar('Desactivar');
di('desactivar', sql(`SELECT activo FROM organizations WHERE id=${idOrg}`) === '0');

await p.goto(`${B}/admin/organizaciones/${idOrg}/editar`, { waitUntil: 'networkidle0' });

/*
 * Con el tipo «Otra», el servidor exige decir cual (`required_if`). El
 * formulario tiene que decirlo ANTES: marcar el campo como obligatorio en
 * cuanto el tipo lo es. Antes ponia `nullable` y prometia que se podia dejar
 * vacio, y luego rebotaba sin haber avisado.
 */
di('con tipo «Otra», el campo «cual» sale marcado', await p.evaluate(() => {
  const campo = document.querySelector('main [name=tipo_otro]')?.closest('.campo');
  const marca = campo?.querySelector('.campo-obligatorio');
  return !!marca && marca.offsetParent !== null;
}));

await escribir('main [name=tipo_otro]', 'Fundacion de prueba');
await escribir('main [name=nombre]', 'ZZOrg vacia editada');
await pulsar('Guardar cambios');
di('editar guarda', sql(`SELECT nombre FROM organizations WHERE id=${idOrg}`) === 'ZZOrg vacia editada');

await p.goto(`${B}/admin/organizaciones?q=ZZOrg`, { waitUntil: 'networkidle0' });
await p.evaluate(() => [...document.querySelectorAll('tbody button')].find((b) => b.textContent.trim() === 'Borrar')?.click());
await esperar(400);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle0' }), p.evaluate(() => document.querySelector('.dialogo button[type=submit]')?.click())]);
di('eliminar la vacía, en blando', sql(`SELECT deleted_at IS NOT NULL FROM organizations WHERE id=${idOrg}`) === '1');

await p.goto(`${B}/admin/organizaciones?papelera=eliminados&q=ZZOrg`, { waitUntil: 'networkidle0' });
await pulsar('Restaurar');
di('restaurar la devuelve', sql(`SELECT deleted_at IS NULL FROM organizations WHERE id=${idOrg}`) === '1');

limpiarOrg();

// El servidor no se fía del botón escondido.
if (conActividades !== '0') {
  const r = await p.evaluate(async (url, token) => {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': token },
      body: '_method=DELETE',
    });
    return res.status;
  }, `${B}/admin/organizaciones/${conActividades}`, await p.evaluate(() => document.querySelector('meta[name=csrf-token]').content));
  di('un POST directo tampoco la borra', sql(`SELECT deleted_at IS NULL FROM organizations WHERE id=${conActividades}`) === '1', `respondió ${r}`);
}

await p.screenshot({ path: `${S}/g-listado.png`, fullPage: true });

console.log('');
console.log(`errores de consola: ${errores.length ? errores.slice(0, 4).join(' | ') : 'ninguno'}`);
if (errores.length) mal++;

console.log('');
console.log('='.repeat(60));
console.log(`  ${ok} bien, ${mal} mal`);
console.log('='.repeat(60));

await nav.close();
process.exit(mal === 0 ? 0 : 1);
