// Bloque C — que un organizador no llegue a los datos de otro cambiando el
// número de la URL, y que ninguno de los dos paneles se abra al rol contrario.
//
// La forma de probarlo es la única que vale: entrar de verdad como el
// organizador B y pedir las direcciones del organizador A. Si vuelve el
// formulario en vez de un 403, la fuga existe por mucho que el código parezca
// correcto.
import { execFileSync } from 'node:child_process';

const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const RAIZ = new URL('..', import.meta.url).pathname.slice(1);

let ok = 0, mal = 0;
const veredicto = (bien) => { bien ? ok++ : mal++; return bien ? 'OK' : '*** MAL ***'; };

/* ---------------------------------------------------------------- sesiones */

function sesion() {
  const jar = new Map();
  const guardar = (r) => { for (const c of (r.headers.getSetCookie?.() ?? [])) { const [kv] = c.split(';'); const i = kv.indexOf('='); jar.set(kv.slice(0, i), kv.slice(i + 1)); } };
  const cookies = () => [...jar].map(([k, v]) => `${k}=${v}`).join('; ');

  const pedir = async (ruta, opciones = {}) => {
    const r = await fetch(`${BASE}${ruta}`, {
      redirect: 'manual',
      ...opciones,
      headers: { cookie: cookies(), ...(opciones.headers ?? {}) },
    });
    guardar(r);
    return r;
  };

  // El token del formulario de la página que sea; hace falta para todo POST,
  // porque el CSRF se comprueba antes que los permisos y un 419 no diría nada.
  const token = async (ruta) => (await (await pedir(ruta)).text()).match(/name="_token"\s+value="([^"]+)"/)?.[1];

  const enviar = async (ruta, campos, _token) => pedir(ruta, {
    method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ _token, ...campos }).toString(),
  });

  return { pedir, token, enviar };
}

async function entrar(ruta, email, password) {
  const s = sesion();
  const _token = await s.token(ruta);
  await s.enviar(ruta, { email, password }, _token);
  s._token = await s.token(ruta === '/admin/login' ? '/admin/perfil' : '/mi-cuenta/perfil');
  return s;
}

/* ------------------------------------------------------------------- datos */

console.log('Sembrando dos organizadores de distinta organización…');
const salida = execFileSync('php', ['artisan', 'tinker', '--execute', "require base_path('pruebas/datos-permisos.php');"], { cwd: RAIZ, encoding: 'utf8' });
const D = JSON.parse(salida.match(/DATOS=(\{.*\})/)[1]);
console.log(`  A → usuario ${D.a.usuario}, actividad ${D.a.actividad} (${D.a.slug}, en revisión)`);
console.log(`  B → usuario ${D.b.usuario}, actividad ${D.b.actividad} (${D.b.slug}, borrador)\n`);

const B = await entrar('/mi-cuenta/login', 'org-b@prueba.test', 'prueba1234');
const A = await entrar('/mi-cuenta/login', 'org-a@prueba.test', 'prueba1234');
const ADMIN = await entrar('/admin/login', 'admin@ong-laravel.test', 'admin1234');
const NADIE = sesion();

/* ----------------------------------------- 1) B contra la actividad de A */

console.log('=== 1) El organizador B pide las direcciones de la actividad de A ===');
console.log('    (todas tienen que devolver 403, no el formulario)\n');

const id = D.a.actividad;

const lecturas = [
  ['GET   editar          ', `/mi-cuenta/actividades/${id}/editar`],
  ['GET   guardado        ', `/mi-cuenta/actividades/${id}/guardado`],
  ['GET   participantes   ', `/mi-cuenta/actividades/${id}/participantes`],
  ['GET   exportar lista  ', `/mi-cuenta/actividades/${id}/participantes/exportar`],
];

for (const [etq, ruta] of lecturas) {
  const r = await B.pedir(ruta);
  console.log(`  ${etq} → ${r.status}  ${veredicto(r.status === 403)}`);
}

const escrituras = [
  ['POST  duplicar        ', `/mi-cuenta/actividades/${id}/duplicar`, {}],
  ['POST  enviar a revisar', `/mi-cuenta/actividades/${id}/enviar`, {}],
  ['POST  cancelar        ', `/mi-cuenta/actividades/${id}/cancelar`, {}],
  ['PUT   actualizar      ', `/mi-cuenta/actividades/${id}`, { _method: 'PUT', titulo: 'Secuestrada' }],
  ['PATCH cupos           ', `/mi-cuenta/actividades/${id}/cupos`, { _method: 'PATCH', cupos_disponibles: '0' }],
];

for (const [etq, ruta, campos] of escrituras) {
  const r = await B.enviar(ruta, campos, B._token);
  console.log(`  ${etq} → ${r.status}  ${veredicto(r.status === 403)}`);
}

/* ------------------------------------------------ 2) B sobre lo suyo sí */

console.log('\n=== 2) …y sobre SU propia actividad tiene que poder ===');
for (const [etq, ruta] of [
  ['GET   editar          ', `/mi-cuenta/actividades/${D.b.actividad}/editar`],
  ['GET   participantes   ', `/mi-cuenta/actividades/${D.b.actividad}/participantes`],
]) {
  const r = await B.pedir(ruta);
  console.log(`  ${etq} → ${r.status}  ${veredicto(r.status === 200)}`);
}

/* ------------------------------------- 3) fichas sin publicar, en público */

console.log('\n=== 3) La ficha de A no está publicada: nadie de fuera debe verla ===');
for (const [quien, s, esperado] of [
  ['sin sesión      ', NADIE, 404],
  ['el organizador B', B, 404],
  ['su dueño, A     ', A, 200],
  ['un administrador', ADMIN, 200],
]) {
  const r = await s.pedir(`/actividades/${D.a.slug}`);
  console.log(`  /actividades/${D.a.slug}  ${quien} → ${r.status}  ${veredicto(r.status === esperado)}`);
}

console.log('\n=== 4) «Actividad enviada» era pública y enseñaba la ficha entera ===');
for (const [quien, s, esperado] of [
  ['sin sesión      ', NADIE, 404],
  ['el organizador B', B, 404],
  ['su dueño, A     ', A, 200],
]) {
  const r = await s.pedir(`/publicar-actividad/${D.a.slug}/listo`);
  console.log(`  …/listo  ${quien} → ${r.status}  ${veredicto(r.status === esperado)}`);
}

/* -------------------------------------------------- 5) los dos paneles */

console.log('\n=== 5) Ningún rol entra en el panel del otro ===');
const cruces = [
  ['organizador → /admin                ', B, '/admin', 403],
  ['organizador → /admin/usuarios       ', B, '/admin/usuarios', 403],
  ['organizador → /admin/configuracion  ', B, '/admin/configuracion', 403],
  ['organizador → /admin/correos        ', B, '/admin/correos', 403],
  ['admin       → /mi-cuenta/actividades', ADMIN, '/mi-cuenta/actividades', 403],
  ['admin       → /mi-cuenta/perfil     ', ADMIN, '/mi-cuenta/perfil', 403],
  ['sin sesión  → /admin/usuarios       ', NADIE, '/admin/usuarios', 302],
  ['sin sesión  → /mi-cuenta/actividades', NADIE, '/mi-cuenta/actividades', 302],
];

for (const [etq, s, ruta, esperado] of cruces) {
  const r = await s.pedir(ruta);
  console.log(`  ${etq} → ${r.status}  ${veredicto(r.status === esperado)}`);
}

/* --------------------------------- 6) límites del admin sobre sí mismo */

console.log('\n=== 6) Un admin no puede cerrarse la puerta desde dentro ===');
const yo = (await (await ADMIN.pedir('/admin/usuarios?rol=admin')).text()).match(/\/admin\/usuarios\/(\d+)\/editar/)?.[1];

let r = await ADMIN.enviar(`/admin/usuarios/${yo}/contrasena`, { password: 'otraclave1234', password_confirmation: 'otraclave1234' }, ADMIN._token);
const destino = (r.headers.get('location') ?? '').replace(BASE, '');
console.log(`  cambiarse la contraseña ahí → ${r.status} ${destino}  ${veredicto(destino.endsWith('/admin/perfil'))}`);

await ADMIN.enviar(`/admin/usuarios/${yo}/estado`, {}, ADMIN._token);
let html = await (await ADMIN.pedir('/admin/usuarios?rol=admin')).text();
console.log(`  desactivarse a sí mismo     → ${veredicto(/No puedes desactivar tu propia cuenta/.test(html))}`);

await ADMIN.enviar(`/admin/usuarios/${yo}`, { _method: 'PUT', name: 'Admin', email: 'admin@ong-laravel.test', role: 'organizer' }, ADMIN._token);
html = await (await ADMIN.pedir(`/admin/usuarios/${yo}/editar?rol=admin`)).text();
console.log(`  quitarse el rol de admin    → ${veredicto(/te quedarías fuera del panel/.test(html))}`);

/* --------------------------------------------------------------- resumen */

console.log(`\n${'='.repeat(58)}`);
console.log(`  ${ok} bien, ${mal} mal`);
console.log('='.repeat(58));
console.log('\nPara borrar los datos de prueba, ver el README de esta carpeta.');
process.exit(mal === 0 ? 0 : 1);
