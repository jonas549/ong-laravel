// Bloque F — el editor de contenido del home.
//
// Cinco cosas que hay que demostrar, no suponer:
//   1. Sin nada guardado, el home dice lo que decía el HTML fuente (regla 5).
//   2. Lo que se publica se ve en el sitio, y lo que se vacía vuelve al original.
//   3. El HTML peligroso no sobrevive al guardado (regla 3).
//   4. El contenido extremo no rompe la maquetación (regla 2).
//   5. Borrador, vista previa, historial, encendido y orden hacen lo que dicen.
//
// La verificación no es «la pantalla carga»: se publica de verdad y se lee el
// home público para ver si cambió.
import { execFileSync } from 'node:child_process';

const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();

let ok = 0, mal = 0;
const veredicto = (bien) => { bien ? ok++ : mal++; return bien ? 'OK' : '*** MAL ***'; };
const di = (que, bien, extra = '') => console.log(`  ${que.padEnd(62)} ${veredicto(bien)} ${extra}`);

/* ---------------------------------------------------------------- sesión */

const jar = new Map();
const guardar = (r) => { for (const c of (r.headers.getSetCookie?.() ?? [])) { const [kv] = c.split(';'); const i = kv.indexOf('='); jar.set(kv.slice(0, i), kv.slice(i + 1)); } };
const cookies = () => [...jar].map(([k, v]) => `${k}=${v}`).join('; ');

async function pedir(ruta, opciones = {}) {
  const r = await fetch(`${BASE}${ruta}`, { redirect: 'manual', ...opciones, headers: { cookie: cookies(), ...(opciones.headers ?? {}) } });
  guardar(r);
  return r;
}

const html = async (ruta) => (await pedir(ruta)).text();
const token = async (ruta) => (await html(ruta)).match(/name="_token"\s+value="([^"]+)"/)?.[1];

async function enviar(ruta, campos, metodo = 'POST') {
  const _token = await token('/admin/paginas/home');
  const cuerpo = new URLSearchParams({ _token, ...campos });
  if (metodo !== 'POST') cuerpo.set('_method', metodo);
  return pedir(ruta, { method: 'POST', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: cuerpo.toString() });
}

const _t = await token('/admin/login');
await pedir('/admin/login', {
  method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({ _token: _t, email: 'admin@ong-laravel.test', password: 'admin1234' }).toString(),
});

// Los campos de una sección, para poder reenviarlos enteros: el formulario
// guarda todos a la vez y mandar sólo uno vaciaría los demás.
async function camposDe(clave) {
  const h = await html(`/admin/paginas/home/${clave}`);
  const campos = {};
  for (const m of h.matchAll(/<(?:input|textarea|select)[^>]*name="([a-z0-9_]+)"[^>]*>/gi)) {
    if (['_token', '_method'].includes(m[1])) continue;
    const val = /value="([^"]*)"/.exec(m[0]);
    campos[m[1]] = val ? val[1].replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>') : '';
  }
  return campos;
}

const publicar = (clave, cambios) => camposDe(clave).then((c) => enviar(`/admin/paginas/home/${clave}`, { ...c, ...cambios }, 'PUT'));

/* --------------------------------------- 1) sin nada guardado = el fuente */

console.log('=== 1) Con la base sin tocar, el home dice lo del HTML fuente ===\n');

// Abrir el panel es lo que crea las filas que falten; el sitio publico no
// las necesita, porque tira del catalogo.
await html('/admin/paginas/home');
sql('DELETE FROM home_section_versions; UPDATE home_sections SET contenido=NULL, borrador=NULL, activo=1;');

let home = await html('/');
const original = [
  ['el titular del hero', 'Miles de personas ya son parte de este'],
  ['las fechas de la píldora', '4 y 5 de diciembre'],
  ['el texto de ¿qué es?', 'Es todo aquello que construimos cuando nos unimos'],
  ['los contadores de la meta', '500'],
  ['el titular de las cifras', 'Chile está construyendo su Patrimonio Social'],
  ['el crédito de la COS', 'y sus organizaciones socias'],
];
for (const [que, texto] of original) di(`aparece ${que}`, home.includes(texto));

di('el video del fuente está puesto', home.includes('data-video="e8iqqzO3s7k"'));
di('las 12 secciones tienen fila en la base', sql('SELECT COUNT(*) FROM home_sections') === '12');

/* ------------------------------------------ 2) publicar y ver el cambio */

console.log('\n=== 2) Se publica y el sitio cambia; se vacía y vuelve al original ===\n');

await publicar('hero', { titulo: 'Un titular escrito desde el panel', pildora_fechas: '9 y 10 de enero' });
home = await html('/');
di('el titular nuevo se ve en el home', home.includes('Un titular escrito desde el panel'));
di('el viejo ya no está', !home.includes('Miles de personas ya son parte de este'));
di('y la fecha nueva también', home.includes('9 y 10 de enero'));

await publicar('hero', { titulo: '', pildora_fechas: '' });
home = await html('/');
di('vaciar el campo devuelve el texto del fuente', home.includes('Miles de personas ya son parte de este') && home.includes('4 y 5 de diciembre'));

// El texto rico, que es el que pasa por el sanitizador.
await publicar('que-es', { cuerpo: '<p>Un párrafo <strong>con negrita</strong> y un <a href="/actividades">enlace</a>.</p>' });
home = await html('/');
di('el texto rico se publica con su formato', home.includes('<strong>con negrita</strong>') && home.includes('href="/actividades"'));
di('a los enlaces se les pone rel=noopener', /<a[^>]*rel="noopener noreferrer"/.test(home) || home.includes('rel="noopener noreferrer"'));

/* --------------------------------------------------- 3) sanitización */

console.log('\n=== 3) Lo peligroso no sobrevive al guardado ===\n');

const ataques = [
  ['un <script>', '<p>hola</p><script>alert(document.domain)</script>', (h) => !h.includes('alert(document.domain)')],
  ['un onerror', '<p onerror="alert(1)">hola</p><img src=x onerror="alert(1)">', (h) => !h.includes('onerror')],
  ['un iframe ajeno', '<p>hola</p><iframe src="https://evil.example/x"></iframe>', (h) => !h.includes('evil.example')],
  ['un href javascript:', '<p><a href="javascript:alert(1)">pincha</a></p>', (h) => !h.includes('javascript:alert')],
  ['un style que rompe el diseño', '<p style="position:fixed;font-size:200px">hola</p>', (h) => !h.includes('position:fixed')],
  ['un formulario', '<form action="https://evil.example"><input name="clave"></form><p>hola</p>', (h) => !h.includes('<form action="https://evil.example"')],
  ['un svg con onload', '<svg onload="alert(1)"></svg><p>hola</p>', (h) => !h.includes('onload')],
];

for (const [que, carga, comprobar] of ataques) {
  await publicar('que-es', { cuerpo: carga });
  home = await html('/');
  const guardado = sql(`SELECT contenido FROM home_sections WHERE clave='que-es'`);
  di(`${que} no llega al sitio`, comprobar(home) && comprobar(guardado));
}

// El campo de video guarda un identificador, nunca un iframe.
await publicar('por-que', { video: '<iframe src="https://evil.example"></iframe>' });
di('un iframe en el campo de video no deja nada', sql(`SELECT contenido->>'$.video' FROM home_sections WHERE clave='por-que'`) === 'null');
await publicar('por-que', { video: 'https://www.youtube.com/watch?v=e8iqqzO3s7k&t=10' });
di('una URL de YouTube deja sólo el identificador', sql(`SELECT contenido->>'$.video' FROM home_sections WHERE clave='por-que'`) === 'e8iqqzO3s7k');

// Un campo de una línea guarda texto plano: se ve el HTML, no se ejecuta.
await publicar('hero', { titulo: '<script>alert(1)</script>' });
home = await html('/');
di('el HTML en un campo simple sale escapado, no ejecutado', home.includes('&lt;script&gt;') && !home.includes('<script>alert(1)</script>'));
await publicar('hero', { titulo: '' });

/* ------------------------------------------- 4) contenido extremo */

console.log('\n=== 4) Contenido extremo: la sección tiene que aguantar ===\n');

const larguisimo = 'Solidaridad '.repeat(400);
const palabrota = 'A'.repeat(500);
const desdeWord = '<p class="MsoNormal"><span style="font-family:Calibri;mso-fareast-language:ES"><o:p></o:p>Texto pegado desde Word</span></p><table><tr><td>maqueta</td></tr></table>';
const raros = 'Ñandú «comillas» — em-dash · 中文 · 🎉 <>&"\'';

await publicar('que-es', { cuerpo: `<p>${larguisimo}</p>` });
di('un texto larguísimo se publica', (await html('/')).includes('Solidaridad Solidaridad'));

await publicar('que-es', { cuerpo: `<p>${palabrota}</p>` });
di('una palabra de 500 letras sin espacios se publica', (await html('/')).includes(palabrota.slice(0, 100)));

await publicar('que-es', { cuerpo: desdeWord });
home = await html('/');
di('lo pegado desde Word conserva el texto', home.includes('Texto pegado desde Word'));
di('…y pierde la basura de Word', !home.includes('MsoNormal') && !home.includes('mso-fareast') && !home.includes('<o:p>'));
di('…y la tabla de maquetación no pasa', !home.includes('<td>maqueta'));

await publicar('hero', { bajada: raros });
home = await html('/');
di('los caracteres especiales sobreviven', home.includes('Ñandú') && home.includes('中文') && home.includes('🎉'));
di('…y los signos peligrosos van escapados', home.includes('&lt;&gt;&amp;'));
await publicar('hero', { bajada: '' });

/* -------------------------------------- 5) borrador y vista previa */

console.log('\n=== 5) El borrador no toca el sitio hasta publicar ===\n');

const campos = await camposDe('cifras');
await enviar('/admin/paginas/home/cifras/borrador', { ...campos, titulo: 'TITULAR SOLO EN BORRADOR' });

home = await html('/');
di('el borrador NO se ve en el sitio público', !home.includes('TITULAR SOLO EN BORRADOR'));
di('el borrador SÍ se ve en la vista previa', (await html('/admin/paginas/home/vista-previa')).includes('TITULAR SOLO EN BORRADOR'));
di('el editor avisa de que hay borrador sin publicar', (await html('/admin/paginas/home/cifras')).includes('Hay un borrador sin publicar'));

await enviar('/admin/paginas/home/cifras/borrador', { ...campos, titulo: 'TITULAR SOLO EN BORRADOR' });
await publicar('cifras', { ...campos, titulo: 'TITULAR SOLO EN BORRADOR' });
di('al publicar, el borrador se limpia', sql(`SELECT borrador IS NULL FROM home_sections WHERE clave='cifras'`) === '1');
di('…y ahora sí se ve en el sitio', (await html('/')).includes('TITULAR SOLO EN BORRADOR'));

/* ------------------------------------------------- 6) historial */

console.log('\n=== 6) Historial y restaurar ===\n');

await publicar('cifras', { ...campos, titulo: 'SEGUNDA VERSION' });
await publicar('cifras', { ...campos, titulo: 'TERCERA VERSION' });

const idSeccion = sql(`SELECT id FROM home_sections WHERE clave='cifras'`);
const cuantas = Number(sql(`SELECT COUNT(*) FROM home_section_versions WHERE home_section_id=${idSeccion}`));
di(`cada publicación deja una versión (hay ${cuantas})`, cuantas >= 2);

const primera = sql(`SELECT id FROM home_section_versions WHERE home_section_id=${idSeccion} ORDER BY id LIMIT 1`);
await enviar(`/admin/paginas/home/cifras/versiones/${primera}/restaurar`, {});
home = await html('/');
di('restaurar publica la versión antigua', home.includes('TITULAR SOLO EN BORRADOR'));
di('…y no borra las de después', Number(sql(`SELECT COUNT(*) FROM home_section_versions WHERE home_section_id=${idSeccion}`)) > cuantas);

const otra = sql(`SELECT id FROM home_section_versions WHERE home_section_id<>${idSeccion} ORDER BY id LIMIT 1`);
if (otra) {
  const r = await enviar(`/admin/paginas/home/cifras/versiones/${otra}/restaurar`, {});
  di('una versión de otra sección da 404', r.status === 404);
}

/* ------------------------------------ 7) encender, apagar y ordenar */

console.log('\n=== 7) Encender, apagar y reordenar ===\n');

await enviar('/admin/paginas/home/noticias/estado', {});
home = await html('/');
di('apagar una sección la quita del home', !home.includes('id="noticias"'));
await enviar('/admin/paginas/home/noticias/estado', {});
di('y volver a encenderla la devuelve', (await html('/')).includes('id="noticias"'));

let r = await enviar('/admin/paginas/home/hero/estado', {});
di('el hero no se puede apagar (403)', r.status === 403);

// Reordenar de verdad: se manda el orden al revés y se mira el home.
const alReves = ['participantes', 'partners', 'iniciativa', 'noticias', 'cifras', 'voces', 'por-que', 'que-es', 'actividades', 'meta', 'participar', 'hero'];
const _tk = await token('/admin/paginas/home');
await pedir('/admin/paginas/home/orden', {
  method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams([['_token', _tk], ...alReves.map((c) => ['orden[]', c])]).toString(),
});

home = await html('/');
const posMeta = home.indexOf('Construyamos juntos');
const posNoticias = home.indexOf('id="noticias"');
di('el orden nuevo se refleja en el home', posNoticias > 0 && posMeta > posNoticias);
di('el hero sigue el primero pese a mandarlo último', sql(`SELECT orden FROM home_sections WHERE clave='hero'`) === '1');
di('«participar» sigue el segundo', sql(`SELECT orden FROM home_sections WHERE clave='participar'`) === '2');

// Se devuelve el orden del catálogo.
const _tk2 = await token('/admin/paginas/home');
await pedir('/admin/paginas/home/orden', {
  method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams([['_token', _tk2], ...['hero', 'participar', 'meta', 'actividades', 'que-es', 'por-que', 'voces', 'cifras', 'noticias', 'iniciativa', 'partners', 'participantes'].map((c) => ['orden[]', c])]).toString(),
});
di('se puede volver al orden original', sql(`SELECT orden FROM home_sections WHERE clave='participantes'`) === '12');

/* --------------------------------------------------- 8) permisos */

console.log('\n=== 8) El editor es sólo del panel ===\n');

const sinSesion = await fetch(`${BASE}/admin/paginas/home`, { redirect: 'manual' });
di('sin sesión, el editor redirige al login', sinSesion.status === 302);
const previaSinSesion = await fetch(`${BASE}/admin/paginas/home/vista-previa`, { redirect: 'manual' });
di('la vista previa tampoco es pública', previaSinSesion.status === 302);

/* ------------------------------------ 9) los fallos del testing en produccion */

console.log('');
console.log('=== 9) Los cinco fallos que encontro Cowork ===');
console.log('');

// FALLO 1 - la negrita y la cursiva se perdian al publicar.
await publicar('que-es', { cuerpo: '<p>Primer parrafo con<b> negrit</b><i>a y cursiv</i>a de prueba.</p>' });
let guardado = sql(`SELECT contenido->>'$.cuerpo' FROM home_sections WHERE clave='que-es'`);
di('F1: <b> se guarda como <strong>', guardado.includes('<strong> negrit</strong>'));
di('F1: <i> se guarda como <em>', guardado.includes('<em>a y cursiv</em>'));
home = await html('/');
di('F1: la negrita llega al sitio', home.includes('<strong> negrit</strong>'));

await publicar('que-es', { cuerpo: '<p>Con <strong>strong</strong> y <em>em</em> directos.</p>' });
guardado = sql(`SELECT contenido->>'$.cuerpo' FROM home_sections WHERE clave='que-es'`);
di('F1: strong y em de siempre siguen pasando', guardado.includes('<strong>strong</strong>') && guardado.includes('<em>em</em>'));

// Los <p></p> huerfanos que deja el editor al meter una lista.
await publicar('que-es', { cuerpo: '<p><ul><li>Uno</li><li>Dos</li></ul></p><p><br></p><p>Texto</p>' });
guardado = sql(`SELECT contenido->>'$.cuerpo' FROM home_sections WHERE clave='que-es'`);
di('parrafos vacios de las listas: fuera', !guardado.includes('<p></p>') && guardado.includes('<li>Uno</li>') && guardado.includes('<p>Texto</p>'));

// FALLO 3 - el endpoint de orden ya funcionaba; se comprueba que sigue.
const _tk3 = await token('/admin/paginas/home');
let r3 = await pedir('/admin/paginas/home/orden', {
  method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded', accept: 'application/json' },
  body: new URLSearchParams([['_token', _tk3]]).toString(),
});
di('F3: un POST de orden sin campos da 422', r3.status === 422, `(${r3.status})`);

// Y con campos, 200: es lo que el arreglo del JavaScript tiene que conseguir.
const _tk3b = await token('/admin/paginas/home');
const r3b = await pedir('/admin/paginas/home/orden', {
  method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded', accept: 'application/json' },
  body: new URLSearchParams([['_token', _tk3b], ...['hero', 'participar', 'meta', 'actividades', 'que-es', 'por-que', 'voces', 'cifras', 'noticias', 'iniciativa', 'partners', 'participantes'].map((c) => ['orden[]', c])]).toString(),
});
di('F3: con los campos puestos, 200', r3b.status === 200, `(${r3b.status})`);

// FALLO 4 - el borrador guarda y DEVUELVE la hora.
const cs = await camposDe('voces');
const rb = await pedir(`/admin/paginas/home/voces/borrador`, {
  method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded', accept: 'application/json' },
  body: new URLSearchParams({ _token: await token('/admin/paginas/home'), ...cs, titulo: 'BORRADOR CON HORA' }).toString(),
});
const cuerpo = await rb.json().catch(() => ({}));
di('F4: el borrador responde 200', rb.status === 200);
di('F4: y devuelve la hora, no undefined', typeof cuerpo.cuando === 'string' && /^\d{2}:\d{2}$/.test(cuerpo.cuando), cuerpo.cuando ?? '(nada)');

// FALLO 5 - la vista previa ensena el borrador y se distingue.
const previa = await html('/admin/paginas/home/vista-previa');
di('F5: la vista previa ensena el borrador', previa.includes('BORRADOR CON HORA'));
di('F5: y lleva su distintivo', previa.includes('aviso-previa') && previa.includes('Vista previa'));
di('F5: el sitio publico sigue sin el borrador', !(await html('/')).includes('BORRADOR CON HORA'));

await enviar('/admin/paginas/home/voces/borrador', {}, 'DELETE');

/* ---------------------------------------- 10) correcciones menores */

console.log('');
console.log('=== 10) Correcciones menores ===');
console.log('');

const panel = await html('/admin');
di('el KPI dice "inscripciones", no "personas inscritas"', panel.includes('>inscripciones<') && !panel.includes('personas inscritas'));
di('y dice de cuantas personas distintas son', /de \d+ personas?/.test(panel));
di('el KPI de inscripciones filtra el listado', panel.includes('inscripciones?estado=activas') || panel.includes('estado=activas'));
di('el KPI de organizaciones activas filtra', panel.includes('filtro=activas'));
di('el acceso rapido a Usuarios lleva ?rol', panel.includes('usuarios?rol='));

// El listado filtrado tiene que ensenar lo que dice el KPI.
const kpiInscripciones = Number(sql("SELECT COUNT(*) FROM registrations WHERE estado<>'cancelado'"));
const listado = await html('/admin/inscripciones?estado=activas');
const canceladas = sql("SELECT COALESCE((SELECT correo FROM registrations WHERE estado='cancelado' LIMIT 1),'')");
di('el listado filtrado no trae canceladas', !canceladas || !listado.includes(canceladas), canceladas ? `(${canceladas})` : '(no hay canceladas)');

const orgsActivas = Number(sql("SELECT COUNT(DISTINCT o.id) FROM organizations o JOIN activities a ON a.organization_id=o.id AND a.estado='publicada' AND a.deleted_at IS NULL"));
const listaOrgs = await html('/admin/organizaciones?filtro=activas');
const filasOrgs = (listaOrgs.match(/\/admin\/organizaciones\/\d+\/verificar/g) ?? []).length;
di(`el listado de organizaciones activas trae ${orgsActivas}`, filasOrgs === orgsActivas, `${filasOrgs} filas`);

// Migas en las tres pantallas que no las tenian.
for (const [etq, ruta, texto] of [
  ['perfil', '/admin/perfil', 'Mi perfil'],
  ['buscador', '/admin/buscar?q=dps', 'Resultados'],
  ['usuarios sin ?rol', '/admin/usuarios', 'Todos'],
]) {
  const h = await html(ruta);
  const nav = (h.split('class="migas"')[1] ?? '').split('</nav>')[0];
  di(`migas en ${etq}`, nav.includes(texto), nav ? '' : '(sin nav de migas)');
}

// La errata del plural en la alerta.
sql("UPDATE settings SET valor='1' WHERE clave='alerta_revision_dias'; DELETE FROM cache;");
const idAct = sql("SELECT COALESCE((SELECT id FROM activities WHERE deleted_at IS NULL ORDER BY id LIMIT 1),0)");
const estadoPrev = sql(`SELECT estado FROM activities WHERE id=${idAct}`);
sql(`UPDATE activities SET estado='revision', updated_at=(UTC_TIMESTAMP() - INTERVAL 5 DAY) WHERE id=${idAct}`);
sql(`DELETE FROM activity_status_logs WHERE activity_id=${idAct} AND a_estado='revision'`);
const conAlerta = await html('/admin');
di('con el plazo en 1 dice «un día», no «1 días»', /hace m.s de un d.a/.test(conAlerta));
di('y no dice "1 dias"', !/hace m.s de 1 d.as/.test(conAlerta));
sql(`UPDATE activities SET estado='${estadoPrev}', updated_at=UTC_TIMESTAMP() WHERE id=${idAct}`);
sql("UPDATE settings SET valor='3' WHERE clave='alerta_revision_dias'; DELETE FROM cache;");

/* ---------------------------------------------------------- limpieza */

sql('DELETE FROM home_section_versions; UPDATE home_sections SET contenido=NULL, borrador=NULL, borrador_at=NULL, borrador_por=NULL, activo=1;');
home = await html('/');
di('al dejarlo todo en blanco, vuelve el home original', home.includes('Miles de personas ya son parte de este') && home.includes('Chile está construyendo su Patrimonio Social'));

console.log(`\n${'='.repeat(72)}`);
console.log(`  ${ok} bien, ${mal} mal`);
console.log('='.repeat(72));
process.exit(mal === 0 ? 0 : 1);
