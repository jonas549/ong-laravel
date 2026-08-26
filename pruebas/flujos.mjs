// Flujos reales: registro (bienvenida + verificación) y "olvidé mi contraseña".
const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123'; const jar=new Map();
const guardar=(r)=>{for(const c of (r.headers.getSetCookie?.()??[])){const[kv]=c.split(';');const i=kv.indexOf('=');jar.set(kv.slice(0,i),kv.slice(i+1));}};
const cookies=()=>[...jar].map(([k,v])=>`${k}=${v}`).join('; ');
async function get(p){const r=await fetch(BASE+p,{headers:{cookie:cookies()},redirect:'manual'});guardar(r);return await r.text();}
async function post(p, campos, pagina){
  const html = await get(pagina ?? p);
  const _token = html.match(/name="_token"\s+value="([^"]+)"/)?.[1];
  const r = await fetch(BASE+p,{method:'POST',headers:{'content-type':'application/x-www-form-urlencoded',cookie:cookies()},
    body:new URLSearchParams({_token, ...campos}).toString(), redirect:'manual'});
  guardar(r);
  return r;
}

const sello = Date.now();
const correo = `prueba-${sello}@ejemplo.cl`;

console.log('1) REGISTRO');
let r = await post('/mi-cuenta/registro', {
  org_nombre:'ONG de Prueba', org_tipo:'Organización sin fines de lucro', name:'Jonas de Prueba', email:correo,
  password:'clave-larga-1234', password_confirmation:'clave-larga-1234',
}, '/mi-cuenta/registro');
console.log(`   POST /mi-cuenta/registro -> ${r.status} ${r.headers.get('location') ?? ''}`);
{ const h = await get('/mi-cuenta/registro');
  const err = [...h.matchAll(/class="field-error">([^<]+)</g)].map(m=>m[1]);
  if (err.length) console.log('   errores de validación: ' + err.join(' | ')); }

console.log('\n2) OLVIDÉ MI CONTRASEÑA');
jar.clear();
r = await post('/mi-cuenta/recuperar-contrasena', { email: correo }, '/mi-cuenta/recuperar-contrasena');
console.log(`   POST /mi-cuenta/recuperar-contrasena -> ${r.status} ${r.headers.get('location') ?? ''}`);

console.log(`\ncorreo usado: ${correo}`);
