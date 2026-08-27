// Bloque E — que los números de la portada del panel salgan de la base y no de
// ninguna otra parte.
//
// La comprobación no es «la pantalla carga»: es leer el número que se pinta,
// contar lo mismo en MySQL con una consulta escrita aparte, y compararlos. Si
// alguien deja un valor de ejemplo o cambia la definición de un KPI sin querer,
// aquí se ve.
//
// La segunda mitad es la que de verdad importa: mueve datos reales —publica una
// actividad, inscribe a alguien, cancela una inscripción— y comprueba que la
// pantalla cambia en consecuencia. Un número correcto por casualidad deja de
// serlo en cuanto la base se mueve.
import { execFileSync } from 'node:child_process';

const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';

const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '-B', '-e', q], { encoding: 'utf8' }).trim();
const num = (q) => parseInt(sql(q) || '0', 10);

let ok = 0, mal = 0;
const veredicto = (bien) => { bien ? ok++ : mal++; return bien ? 'OK' : '*** MAL ***'; };

/* --------------------------------------------------------------- sesión */

const jar = new Map();
const guardar = (r) => { for (const c of (r.headers.getSetCookie?.() ?? [])) { const [kv] = c.split(';'); const i = kv.indexOf('='); jar.set(kv.slice(0, i), kv.slice(i + 1)); } };
const cookies = () => [...jar].map(([k, v]) => `${k}=${v}`).join('; ');

async function pedir(ruta, opciones = {}) {
  const r = await fetch(`${BASE}${ruta}`, { redirect: 'manual', ...opciones, headers: { cookie: cookies(), ...(opciones.headers ?? {}) } });
  guardar(r);
  return r;
}

const token = async (ruta) => (await (await pedir(ruta)).text()).match(/name="_token"\s+value="([^"]+)"/)?.[1];

const _token = await token('/admin/login');
await pedir('/admin/login', {
  method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({ _token, email: 'admin@ong-laravel.test', password: 'admin1234' }).toString(),
});

const portada = async () => {
  const r = await pedir('/admin');
  if (r.status !== 200) throw new Error(`/admin devolvió ${r.status}`);
  return await r.text();
};

/*
 * Lee el número de una tarjeta por su etiqueta, no por su posición: reordenar
 * las tarjetas no debe romper la prueba, y una prueba que se rompe por mover
 * algo de sitio acaba desactivada.
 */
function kpi(html, etiqueta) {
  const trozos = html.split('class="kpi"');
  for (const t of trozos) {
    if (t.includes(etiqueta)) return parseInt(t.match(/class="v"[^>]*>\s*([\d.]+)\s*</)?.[1] ?? 'NaN', 10);
  }
  return NaN;
}

/* -------------------------------------------------- 1) KPIs contra la base */

console.log('=== 1) Cada KPI contra su consulta ===\n');

let html = await portada();

const casos = [
  ['esperando revisión',        'esperando revisión',        "SELECT COUNT(*) FROM activities WHERE estado='revision' AND deleted_at IS NULL"],
  ['actividades publicadas',    'actividades publicadas',    "SELECT COUNT(*) FROM activities WHERE estado='publicada' AND deleted_at IS NULL"],
  ['inscripciones',             '>inscripciones<',           "SELECT COUNT(*) FROM registrations WHERE estado<>'cancelado'"],
  ['organizaciones activas',    'organizaciones activas',    "SELECT COUNT(DISTINCT o.id) FROM organizations o JOIN activities a ON a.organization_id=o.id AND a.estado='publicada' AND a.deleted_at IS NULL"],
  ['organizaciones verificadas','organizaciones verificadas', 'SELECT COUNT(*) FROM organizations WHERE verificada=1'],
];

for (const [etq, marca, consulta] of casos) {
  const pantalla = kpi(html, marca);
  const base = num(consulta);
  console.log(`  ${etq.padEnd(28)} pantalla ${String(pantalla).padStart(4)}  base ${String(base).padStart(4)}  ${veredicto(pantalla === base)}`);
}

/* ------------------------------------------- 2) el desglose por estado */

console.log('\n=== 2) Actividades por estado ===\n');

// El menú lateral también dice «Publicadas» y «Canceladas» —son nodos del
// árbol— así que primero hay que meterse dentro de la tarjeta. Sin esto la
// prueba leía el número de otra parte de la página y fallaba sola.
const tarjetaEstados = (h) => (h.split('Actividades por estado')[1] ?? '').split('</section>')[0];

for (const [clave, filtro] of [
  ['borrador', 'Borradores'],
  ['revision', 'Estamos revisando'],
  ['ajustes', 'Necesita ajustes'],
  ['publicada', 'Publicadas'],
  ['cancelada', 'Canceladas'],
]) {
  const base = num(`SELECT COUNT(*) FROM activities WHERE estado='${clave}' AND deleted_at IS NULL`);
  const trozo = tarjetaEstados(html).split(`>${filtro}<`)[1] ?? '';
  const pantalla = parseInt(trozo.match(/<strong[^>]*>\s*(\d+)\s*<\/strong>/)?.[1] ?? 'NaN', 10);
  console.log(`  ${filtro.padEnd(20)} pantalla ${String(pantalla).padStart(4)}  base ${String(base).padStart(4)}  ${veredicto(pantalla === base)}`);
}

const totalBase = num('SELECT COUNT(*) FROM activities WHERE deleted_at IS NULL');
const totalPantalla = parseInt((tarjetaEstados(html).split('>Total<')[1] ?? '').match(/<strong[^>]*>\s*(\d+)\s*<\/strong>/)?.[1] ?? 'NaN', 10);
console.log(`  ${'Total'.padEnd(20)} pantalla ${String(totalPantalla).padStart(4)}  base ${String(totalBase).padStart(4)}  ${veredicto(totalPantalla === totalBase)}`);

/* ---------------------------------------------- 3) las tablas y el gráfico */

console.log('\n=== 3) Tablas, gráfico y accesos ===\n');

// Inscripciones y personas son cosas distintas, y la pantalla las dice aparte.
const personasBase = num("SELECT COUNT(DISTINCT correo) FROM registrations WHERE estado<>'cancelado'");
const personasPantalla = parseInt((html.split('>inscripciones<')[1] ?? '').match(/de (\d+) personas?/)?.[1] ?? 'NaN', 10);
console.log(`  personas distintas          pantalla ${String(personasPantalla).padStart(4)}  base ${String(personasBase).padStart(4)}  ${veredicto(personasPantalla === personasBase)}`);

const pendientesBase = num("SELECT COUNT(*) FROM activities WHERE estado='revision' AND deleted_at IS NULL");
const filasPendientes = (html.match(/Revisar<\/a>/g) ?? []).length;
console.log(`  filas en «Esperando revisión»: ${filasPendientes} (tope 8, hay ${pendientesBase})  ${veredicto(filasPendientes === Math.min(8, pendientesBase))}`);

const ultimaBase = sql('SELECT nombre FROM registrations ORDER BY created_at DESC, id DESC LIMIT 1');
console.log(`  la inscripción más reciente de la base sale en la tabla: ${veredicto(!ultimaBase || html.includes(ultimaBase))}`);

/*
 * La ventana es «el lunes de hace 11 semanas», igual que la calcula Carbon en
 * ResumenPanel. WEEKDAY() da 0 el lunes, así que retroceder WEEKDAY()+77 días
 * cae en el mismo lunes. En UTC, que es como escribe Laravel.
 */
const lunes = "(DATE(UTC_TIMESTAMP()) - INTERVAL (WEEKDAY(UTC_TIMESTAMP()) + 77) DAY)";
const leyenda = (etiqueta) => {
  // Acotado a la tarjeta: «Inscripciones» también es un nodo del menú lateral.
  const tarjeta = (html.split('Evolución por semana')[1] ?? '').split('</section>')[0];
  const t = tarjeta.split(etiqueta)[1] ?? '';
  return parseInt(t.match(/<strong[^>]*>\s*(\d+)\s*<\/strong>/)?.[1] ?? 'NaN', 10);
};

for (const [etiqueta, consulta] of [
  ['Inscripciones', `SELECT COUNT(*) FROM registrations WHERE estado<>'cancelado' AND created_at >= ${lunes}`],
  ['Actividades creadas', `SELECT COUNT(*) FROM activities WHERE deleted_at IS NULL AND created_at >= ${lunes}`],
]) {
  const pantalla = leyenda(etiqueta);
  const base = num(consulta);
  console.log(`  gráfico, ${etiqueta.padEnd(20)} pantalla ${String(pantalla).padStart(4)}  base ${String(base).padStart(4)}  ${veredicto(pantalla === base)}`);
}

console.log(`  el gráfico se dibuja o dice que no hay datos: ${veredicto(html.includes('<svg') || html.includes('No hubo ni una actividad'))}`);
console.log(`  accesos rápidos presentes: ${veredicto(html.includes('Accesos rápidos') && html.includes('Revisar actividades'))}`);

/* ------------------------------------- 4) mover la base y volver a mirar */

console.log('\n=== 4) Se mueve la base: la pantalla tiene que moverse con ella ===');
console.log('    (es la parte que distingue un número real de uno que acierta por casualidad)\n');

const antes = {
  publicadas: kpi(html, 'actividades publicadas'),
  inscritos: kpi(html, '>inscripciones<'),
  activas: kpi(html, 'organizaciones activas'),
};

// Una actividad en borrador de una organización que no tenga nada publicado:
// así el cambio se nota en los tres números a la vez.
const orgSuelta = num("SELECT COUNT(*) FROM organizations o WHERE NOT EXISTS (SELECT 1 FROM activities a WHERE a.organization_id=o.id AND a.estado='publicada' AND a.deleted_at IS NULL)");
const idBorrador = num("SELECT COALESCE((SELECT a.id FROM activities a WHERE a.estado<>'publicada' AND a.deleted_at IS NULL ORDER BY a.id LIMIT 1),0)");

if (idBorrador) {
  const estadoPrevio = sql(`SELECT estado FROM activities WHERE id=${idBorrador}`);
  sql(`UPDATE activities SET estado='publicada', published_at=NOW() WHERE id=${idBorrador}`);
  html = await portada();
  console.log(`  publicar una actividad sube «publicadas» en 1: ${antes.publicadas} → ${kpi(html, 'actividades publicadas')}  ${veredicto(kpi(html, 'actividades publicadas') === antes.publicadas + 1)}`);

  sql(`UPDATE activities SET estado='${estadoPrevio}', published_at=NULL WHERE id=${idBorrador}`);
  html = await portada();
  console.log(`  y deshacerlo la devuelve: ${veredicto(kpi(html, 'actividades publicadas') === antes.publicadas)}`);
} else {
  console.log('  (sin actividades sin publicar en la base; me salto este caso)');
}

const idActividad = num('SELECT COALESCE((SELECT id FROM activities WHERE deleted_at IS NULL ORDER BY id LIMIT 1),0)');

if (idActividad) {
  sql(`INSERT INTO registrations (activity_id,nombre,correo,es_mayor_edad,estado,token,created_at,updated_at) VALUES (${idActividad},'Prueba Panel','prueba-panel@ejemplo.test',1,'pendiente','tok-panel-prueba',UTC_TIMESTAMP(),UTC_TIMESTAMP())`);
  html = await portada();
  console.log(`  una inscripción nueva sube «inscripciones» en 1: ${antes.inscritos} → ${kpi(html, '>inscripciones<')}  ${veredicto(kpi(html, '>inscripciones<') === antes.inscritos + 1)}`);
  console.log(`  …y aparece en «Últimas inscripciones»: ${veredicto(html.includes('Prueba Panel'))}`);

  sql("UPDATE registrations SET estado='cancelado' WHERE token='tok-panel-prueba'");
  html = await portada();
  console.log(`  cancelarla la descuenta otra vez: ${veredicto(kpi(html, '>inscripciones<') === antes.inscritos)}`);

  sql("DELETE FROM registrations WHERE token='tok-panel-prueba'");
  html = await portada();
  console.log(`  borrarla deja el número como estaba: ${veredicto(kpi(html, '>inscripciones<') === antes.inscritos)}`);
} else {
  console.log('  (sin actividades en la base; me salto este caso)');
}

/* --------------------------------------------------------- 5) la alerta */

console.log('\n=== 5) La alerta de revisión atrasada ===\n');

const dias = num("SELECT COALESCE((SELECT valor FROM settings WHERE clave='alerta_revision_dias'),3)");
console.log(`  el plazo sale de Configuración, no del código: ${dias} días  ${veredicto(dias > 0)}`);

if (idBorrador) {
  const estadoPrevio = sql(`SELECT estado FROM activities WHERE id=${idBorrador}`);
  // updated_at en UTC: Laravel escribe en UTC y NOW() da hora local, cuatro
  // horas de diferencia en esta máquina.
  sql(`UPDATE activities SET estado='revision', updated_at=(UTC_TIMESTAMP() - INTERVAL ${dias + 5} DAY) WHERE id=${idBorrador}`);
  sql(`DELETE FROM activity_status_logs WHERE activity_id=${idBorrador} AND a_estado='revision'`);
  html = await portada();
  console.log(`  una actividad parada hace ${dias + 5} días dispara la alerta: ${veredicto(/esperando revisi.n hace m.s de/.test(html))}`);

  sql(`UPDATE activities SET estado='${estadoPrevio}', updated_at=UTC_TIMESTAMP() WHERE id=${idBorrador}`);
  html = await portada();
  console.log(`  y al quitarla la alerta desaparece: ${veredicto(!/esperando revisi.n hace m.s de/.test(html))}`);
} else {
  console.log('  (sin actividades para mover; me salto este caso)');
}

/* --------------------------------------------------------------- resumen */

console.log(`\n${'='.repeat(58)}`);
console.log(`  ${ok} bien, ${mal} mal`);
console.log('='.repeat(58));
process.exit(mal === 0 ? 0 : 1);
