// Comprobación de humo: que nada de lo tocado haya roto una pantalla.
const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123'; const jar=new Map();
const guardar=(r)=>{for(const c of (r.headers.getSetCookie?.()??[])){const[kv]=c.split(';');const i=kv.indexOf('=');jar.set(kv.slice(0,i),kv.slice(i+1));}};
const cookies=()=>[...jar].map(([k,v])=>`${k}=${v}`).join('; ');
async function crudo(p){const r=await fetch(BASE+p,{headers:{cookie:cookies()},redirect:'manual'});guardar(r);return r;}
async function get(p){let r=await crudo(p);let saltos=0;
  while(r.status>=300&&r.status<400&&saltos++<4){r=await crudo(new URL(r.headers.get('location'),BASE).pathname+ (new URL(r.headers.get('location'),BASE).search||''));}
  return {status:r.status, html:await r.text()};}

let {html} = await get('/admin/login');
const t = html.match(/name="_token"\s+value="([^"]+)"/)[1];
guardar(await fetch(BASE+'/admin/login',{method:'POST',headers:{'content-type':'application/x-www-form-urlencoded',cookie:cookies()},body:new URLSearchParams({_token:t,email:'admin@ong-laravel.test',password:'admin1234'}).toString(),redirect:'manual'}));

const rutas = [
  '/', '/actividades', '/noticias', '/publicar-actividad', '/mi-cuenta/login',
  '/mi-cuenta/recuperar-contrasena', '/admin/recuperar-contrasena',
  '/admin', '/admin/perfil', '/admin/actividades', '/admin/actividades/pendientes',
  '/admin/organizaciones', '/admin/inscripciones', '/admin/usuarios',
  '/admin/plantillas', '/admin/correos', '/admin/accesos',
  '/admin/configuracion', '/admin/configuracion/smtp', '/admin/configuracion/seo',
  '/admin/regiones', '/admin/taxonomias', '/admin/contenido/noticias',
  '/admin/paginas/privacidad', '/admin/buscar?q=dps', '/admin/usuarios/2/editar',
];

let malas = 0;
for (const ruta of rutas) {
  const r = await get(ruta);
  const ok = r.status === 200;
  if (!ok) malas++;
  console.log(`${ok?'ok ':'MAL'}  ${String(r.status).padEnd(4)} ${ruta}`);
}
// la primera plantilla, que es donde viven variables/previa/prueba
const idx = await get('/admin/plantillas');
const primera = idx.html.match(/href="[^"]*\/admin\/plantillas\/(\d+)"/)?.[1];
if (primera) {
  const e = await get(`/admin/plantillas/${primera}`);
  const tiene = (re) => re.test(e.html);
  console.log(`\neditor de plantilla #${primera}: ${e.status}`);
  console.log(`  lista de variables : ${tiene(/Variables disponibles|\{\{\s*nombre\s*\}\}/)}`);
  console.log(`  vista previa       : ${tiene(/previsualizar\(\)/)}`);
  console.log(`  envío de prueba    : ${tiene(/templates\.test|Enviar una prueba/)}`);
  if (e.status !== 200) malas++;
}
console.log(`\n${malas === 0 ? 'TODAS OK' : malas + ' RUTAS MAL'}`);
