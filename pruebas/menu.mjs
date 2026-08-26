// ¿La ficha de usuario marca el nodo correcto del menú y pinta las migas?
const BASE = process.env.DPS_URL ?? 'http://127.0.0.1:8123'; const jar=new Map();
const guardar=(r)=>{for(const c of (r.headers.getSetCookie?.()??[])){const[kv]=c.split(';');const i=kv.indexOf('=');jar.set(kv.slice(0,i),kv.slice(i+1));}};
const cookies=()=>[...jar].map(([k,v])=>`${k}=${v}`).join('; ');
async function get(p,saltos=0){const r=await fetch(BASE+p,{headers:{cookie:cookies()},redirect:'manual'});guardar(r);
  if(r.status>=300&&r.status<400&&saltos<4){const u=new URL(r.headers.get('location'),BASE);return get(u.pathname+u.search,saltos+1);}
  return r.status===200?await r.text():'';}
let h=await get('/admin/login');
const t=h.match(/name="_token"\s+value="([^"]+)"/)[1];
guardar(await fetch(BASE+'/admin/login',{method:'POST',headers:{'content-type':'application/x-www-form-urlencoded',cookie:cookies()},body:new URLSearchParams({_token:t,email:'admin@ong-laravel.test',password:'admin1234'}).toString(),redirect:'manual'}));

for (const [etq, ruta] of [['organizador (id 2)','/admin/usuarios/2/editar?rol=organizer'],
                           ['admin (id 1)','/admin/usuarios/1/editar?rol=admin'],
                           ['sin ?rol','/admin/usuarios/2/editar']]) {
  const html = await get(ruta);
  const activos = [...html.matchAll(/class="nav-hoja on"[^>]*>([^<]+)</g)].map(m=>m[1].trim());
  const migas = html.match(/<nav class="migas"[\s\S]*?<\/nav>/)?.[0]
                 ?.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim() ?? '(sin migas)';
  console.log(`${etq}`);
  console.log(`  nodos marcados: ${activos.length ? activos.join(' · ') : '(ninguno)'}`);
  console.log(`  migas: ${migas.slice(0,110)}`);
}
