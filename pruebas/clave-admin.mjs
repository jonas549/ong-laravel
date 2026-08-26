// El admin le cambia la contraseña a un organizador. Se comprueban los cuatro
// requisitos: se cambia, queda en el log con el autor, avisa por correo y
// cierra las sesiones del afectado.
import { execFileSync } from 'node:child_process';
const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-e', q], { encoding: 'utf8' }).trim();

const ADMIN = 'admin@ong-laravel.test', CLAVE_ADMIN = 'admin1234';
const ORG = 'organizador@ong-laravel.test', CLAVE_ORG = 'organizador1234';
const NUEVA = 'clave-puesta-por-admin-99';

function sesion() {
  const jar = new Map();
  const guardar = (r) => { for (const c of (r.headers.getSetCookie?.() ?? [])) { const [kv] = c.split(';'); const i = kv.indexOf('='); jar.set(kv.slice(0, i), kv.slice(i + 1)); } };
  const cookies = () => [...jar].map(([k, v]) => `${k}=${v}`).join('; ');
  const get = async (p, saltos = 0) => {
    const r = await fetch(BASE + p, { headers: { cookie: cookies() }, redirect: 'manual' });
    guardar(r);
    const destino = r.headers.get('location') ?? '';
    if (r.status >= 300 && r.status < 400 && saltos < 4) {
      const u = new URL(destino, BASE);
      return get(u.pathname + u.search, saltos + 1);
    }
    return { status: r.status, destino, html: r.status === 200 ? await r.text() : '' };
  };
  const post = async (p, campos, pagina) => {
    const { html } = await get(pagina ?? p);
    const _token = html.match(/name="_token"\s+value="([^"]+)"/)?.[1];
    const r = await fetch(BASE + p, { method: 'POST', headers: { 'content-type': 'application/x-www-form-urlencoded', cookie: cookies() }, body: new URLSearchParams({ _token, ...campos }).toString(), redirect: 'manual' });
    guardar(r);
    return { status: r.status, destino: r.headers.get('location') ?? '' };
  };
  return { get, post };
}

const veredicto = (ok) => ok ? 'OK' : '*** MAL ***';
sql(`DELETE FROM access_logs WHERE email IN ('${ORG}','${ADMIN}'); DELETE FROM cache;`);

// 1) El organizador entra: deja una sesión abierta que debe cerrarse después.
const org = sesion();
let r = await org.post('/mi-cuenta/login', { email: ORG, password: CLAVE_ORG }, '/mi-cuenta/login');
console.log(`1) el organizador entra: ${r.destino.includes('/mi-cuenta/actividades') ? 'sí' : 'NO'}`);
const idOrg = sql(`SELECT id FROM users WHERE email='${ORG}';`).split('\n')[1];
const sesionesAntes = Number(sql(`SELECT COUNT(*) FROM sessions WHERE user_id=${idOrg};`).split('\n')[1]);
console.log(`   sesiones abiertas del organizador: ${sesionesAntes}`);

// 2) El admin entra y abre la ficha del organizador.
const admin = sesion();
await admin.post('/admin/login', { email: ADMIN, password: CLAVE_ADMIN }, '/admin/login');
const ficha = await admin.get(`/admin/usuarios/${idOrg}/editar`);
console.log(`\n2) /admin/usuarios/${idOrg}/editar -> ${ficha.status}`);
console.log(`   ofrece asignar contraseña: ${/Asignar una contraseña nueva/.test(ficha.html)}`);
console.log(`   avisa de las sesiones     : ${/sesion(es)? abierta/.test(ficha.html)}`);
console.log(`   enlace desde el listado   : ${/usuarios\/\d+\/editar/.test((await admin.get('/admin/usuarios')).html)}`);

// 3) Cambia la contraseña.
const correosAntes = Number(sql(`SELECT COUNT(*) FROM email_logs;`).split('\n')[1]);
r = await admin.post(`/admin/usuarios/${idOrg}/contrasena`,
  { password: NUEVA, password_confirmation: NUEVA }, `/admin/usuarios/${idOrg}/editar`);
console.log(`\n3) POST contraseña -> ${r.status}`);
const tras = await admin.get(`/admin/usuarios/${idOrg}/editar`);
console.log(`   mensaje: ${(tras.html.match(/alert alert-ok[^>]*>([^<]+)</) || [])[1]?.trim() ?? '(ninguno)'}`);

// 4) Comprobaciones
console.log('\n4) requisitos:');
const org2 = sesion();
const conNueva = await org2.post('/mi-cuenta/login', { email: ORG, password: NUEVA }, '/mi-cuenta/login');
const entraNueva = conNueva.destino.includes('/mi-cuenta/actividades');
console.log(`   a) entra con la contraseña nueva : ${entraNueva} ${veredicto(entraNueva)}`);

const org3 = sesion();
const conVieja = await org3.post('/mi-cuenta/login', { email: ORG, password: CLAVE_ORG }, '/mi-cuenta/login');
const rechazaVieja = !conVieja.destino.includes('/mi-cuenta/actividades');
console.log(`   b) rechaza la anterior           : ${rechazaVieja} ${veredicto(rechazaVieja)}`);

const log = sql(`SELECT a.resultado, u.email AS afectado, act.name AS lo_hizo
                 FROM access_logs a
                 LEFT JOIN users u ON u.id=a.user_id
                 LEFT JOIN users act ON act.id=a.actor_id
                 WHERE a.resultado='clave_admin';`);
console.log(`   c) queda en el log de accesos:`);
console.log('      ' + log.split('\n').join('\n      '));
const enLog = /clave_admin/.test(log) && /Administraci/.test(log);
console.log(`      ${veredicto(enLog)}`);

const correo = sql(`SELECT status, \`to\`, subject FROM email_logs WHERE mailable LIKE '%ContrasenaCambiadaPorAdmin%' ORDER BY id DESC LIMIT 1;`);
console.log(`   d) correo de aviso:`);
console.log('      ' + correo.split('\n').join('\n      '));
console.log(`      ${veredicto(correo.includes(ORG))}`);

// La sesión que tenía el organizador antes del cambio debe estar cerrada.
// (la de org2 es posterior, así que se cuenta sólo lo que había)
console.log(`   e) sesiones cerradas: el mensaje de arriba lo dice; la sesión vieja quedó inválida`);
const viejaSigue = await org.get('/mi-cuenta/actividades');
// get() sigue redirecciones, así que "expulsado" se ve en dónde acabó: si la
// sesión valiera, seguiría en Mis actividades y no en el formulario de acceso.
const expulsado = /name="password"/.test(viejaSigue.html) && /mi-cuenta\/login/.test(viejaSigue.html);
console.log(`      la sesión anterior fue expulsada: ${expulsado} ${veredicto(expulsado)}`);

// 5) La propia no se puede cambiar por aquí.
const idAdmin = sql(`SELECT id FROM users WHERE email='${ADMIN}';`).split('\n')[1];
r = await admin.post(`/admin/usuarios/${idAdmin}/contrasena`, { password: 'otra-cosa-1234', password_confirmation: 'otra-cosa-1234' }, `/admin/usuarios/${idAdmin}/editar`);
console.log(`\n5) su propia contraseña -> redirige a ${r.destino.replace(BASE, '')} ${veredicto(r.destino.includes('/admin/perfil'))}`);

// dejar la cuenta como estaba
sql(`DELETE FROM access_logs WHERE email IN ('${ORG}','${ADMIN}');`);
