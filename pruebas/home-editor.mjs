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
const di = (que, bien) => console.log(`  ${que.padEnd(62)} ${veredicto(bien)}`);

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

/* ---------------------------------------------------------- limpieza */

sql('DELETE FROM home_section_versions; UPDATE home_sections SET contenido=NULL, borrador=NULL, borrador_at=NULL, borrador_por=NULL, activo=1;');
home = await html('/');
di('al dejarlo todo en blanco, vuelve el home original', home.includes('Miles de personas ya son parte de este') && home.includes('Chile está construyendo su Patrimonio Social'));

console.log(`\n${'='.repeat(72)}`);
console.log(`  ${ok} bien, ${mal} mal`);
console.log('='.repeat(72));
process.exit(mal === 0 ? 0 : 1);
