// El bloqueo, ahora sobre access_logs. La prueba clave: vaciar la caché a
// media tanda no debe reiniciar el contador, que es lo que lo rompía.
import { execFileSync } from 'node:child_process';
const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-e', q], { encoding: 'utf8' });

const EMAIL = 'admin@ong-laravel.test';
const CLAVE = 'admin1234';

// Cada intento con su propia tanda de cookies: sin esto, una entrada correcta
// deja la sesión abierta y los intentos siguientes ya no pasan por el login.
async function intento(email, password) {
  const jar = new Map();
  const guardar = (r) => { for (const c of (r.headers.getSetCookie?.() ?? [])) { const [kv] = c.split(';'); const i = kv.indexOf('='); jar.set(kv.slice(0, i), kv.slice(i + 1)); } };
  const cookies = () => [...jar].map(([k, v]) => `${k}=${v}`).join('; ');

  const r0 = await fetch(`${BASE}/admin/login`, { headers: { cookie: cookies() } }); guardar(r0);
  const _token = (await r0.text()).match(/name="_token"\s+value="([^"]+)"/)[1];

  const r = await fetch(`${BASE}/admin/login`, { method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded', cookie: cookies() },
    body: new URLSearchParams({ _token, email, password }).toString(), redirect: 'manual' });
  guardar(r);

  const destino = r.headers.get('location') ?? '';
  const entro = destino.endsWith('/admin');

  const seg = await fetch(`${BASE}/admin/login`, { headers: { cookie: cookies() }, redirect: 'manual' });
  const html = seg.status === 200 ? await seg.text() : '';
  const msg = html.match(/class="field-error">([^<]+)</)?.[1]?.trim() ?? '';

  return { entro, bloqueado: /Demasiados intentos/.test(msg), msg };
}

const limpiar = () => sql(`DELETE FROM access_logs WHERE email='${EMAIL}'; DELETE FROM cache;`);
const fallar = async (n, etq = 'mala') => { for (let i = 1; i <= n; i++) await intento(EMAIL, `${etq}-${i}`); };
const veredicto = (ok) => ok ? 'OK' : '*** MAL ***';

console.log('=== A) con 4 fallos (tope 5) la correcta debe entrar ===');
limpiar(); await fallar(4);
let r = await intento(EMAIL, CLAVE);
console.log(`  entró: ${r.entro}   ${veredicto(r.entro && !r.bloqueado)}`);

console.log('\n=== B) con 5 fallos debe bloquear, incluso con la clave correcta ===');
limpiar(); await fallar(5);
r = await intento(EMAIL, CLAVE);
console.log(`  entró: ${r.entro}  bloqueado: ${r.bloqueado}  «${r.msg}»`);
console.log(`  ${veredicto(!r.entro && r.bloqueado)}`);

console.log('\n=== C) vaciar la caché en medio NO debe reiniciar el contador ===');
console.log('     (esto es lo que rompía el bloqueo en producción)');
limpiar(); await fallar(3);
sql('DELETE FROM cache;');                     // lo que hace el cron de despliegue
console.log('  → caché vaciada tras 3 fallos');
await fallar(2, 'otra');
r = await intento(EMAIL, CLAVE);
console.log(`  entró: ${r.entro}  bloqueado: ${r.bloqueado}`);
console.log(`  ${veredicto(!r.entro && r.bloqueado)}`);

console.log('\n=== D) entrar bien pone el contador a cero ===');
limpiar(); await fallar(4);
await intento(EMAIL, CLAVE);                   // éxito: reinicia
await fallar(4, 'otra');
r = await intento(EMAIL, CLAVE);
console.log(`  4 fallos + éxito + 4 fallos → entró: ${r.entro}   ${veredicto(r.entro)}`);


console.log('\n=== E) levantar el bloqueo desde el panel lo libera ===');
// Se bloquea al organizador y lo levanta el admin desde su panel: es el caso
// real, porque quien se queda fuera no puede desbloquearse a sí mismo.
const ORG = 'organizador@ong-laravel.test';
const limpiarOrg = () => sql(`DELETE FROM access_logs WHERE email='${ORG}';`);
limpiarOrg();

async function intentoOrg(password) {
  const jar = new Map();
  const guardar = (r) => { for (const c of (r.headers.getSetCookie?.() ?? [])) { const [kv] = c.split(';'); const i = kv.indexOf('='); jar.set(kv.slice(0, i), kv.slice(i + 1)); } };
  const cookies = () => [...jar].map(([k, v]) => `${k}=${v}`).join('; ');
  const r0 = await fetch(`${BASE}/mi-cuenta/login`, { headers: { cookie: cookies() } }); guardar(r0);
  const _token = (await r0.text()).match(/name="_token"\s+value="([^"]+)"/)[1];
  const r = await fetch(`${BASE}/mi-cuenta/login`, { method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded', cookie: cookies() },
    body: new URLSearchParams({ _token, email: ORG, password }).toString(), redirect: 'manual' });
  guardar(r);
  const seg = await fetch(`${BASE}/mi-cuenta/login`, { headers: { cookie: cookies() }, redirect: 'manual' });
  const html = seg.status === 200 ? await seg.text() : '';
  const msg = html.match(/class="field-error">([^<]+)</)?.[1]?.trim() ?? '';
  return { destino: r.headers.get('location') ?? '', bloqueado: /Demasiados intentos/.test(msg) };
}

for (let i = 1; i <= 5; i++) await intentoOrg('mala-' + i);
const antes = await intentoOrg('organizador1234');
console.log(`  organizador tras 5 fallos: bloqueado=${antes.bloqueado}`);

// El admin entra y pulsa "Levantar bloqueo".
const jarA = new Map();
const guardarA = (r) => { for (const c of (r.headers.getSetCookie?.() ?? [])) { const [kv] = c.split(';'); const i = kv.indexOf('='); jarA.set(kv.slice(0, i), kv.slice(i + 1)); } };
const cookiesA = () => [...jarA].map(([k, v]) => `${k}=${v}`).join('; ');
let ra = await fetch(`${BASE}/admin/login`, { headers: { cookie: cookiesA() } }); guardarA(ra);
let tk = (await ra.text()).match(/name="_token"\s+value="([^"]+)"/)[1];
guardarA(await fetch(`${BASE}/admin/login`, { method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded', cookie: cookiesA() },
  body: new URLSearchParams({ _token: tk, email: EMAIL, password: CLAVE }).toString(), redirect: 'manual' }));

ra = await fetch(`${BASE}/admin/accesos`, { headers: { cookie: cookiesA() } }); guardarA(ra);
const htmlAcc = await ra.text();
tk = htmlAcc.match(/name="_token"\s+value="([^"]+)"/)[1];
console.log(`  el panel ofrece levantarlo: ${/Levantar bloqueo/.test(htmlAcc)}`);

guardarA(await fetch(`${BASE}/admin/accesos/desbloquear`, { method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded', cookie: cookiesA() },
  body: new URLSearchParams({ _token: tk, email: ORG, panel: 'organizador', ip: '127.0.0.1' }).toString(),
  redirect: 'manual' }));

const despues = await intentoOrg('organizador1234');
const entro = despues.destino.includes('/mi-cuenta/actividades');
console.log(`  tras levantar el bloqueo: entró=${entro}`);
console.log(`  ${veredicto(antes.bloqueado && entro)}`);

limpiar();
limpiarOrg();
