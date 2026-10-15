// /voluntariados y su ficha (13/10), en Chrome.
//
//   1 · El interruptor de Configuración «Mostrar la sección de voluntariados»
//       (13/10). Apagado: el menú lleva a voluntariadoschile.cl en otra
//       pestaña, ninguna página pública enlaza a /voluntariados, y la sección
//       lleva noindex (meta y cabecera). Encendido: el menú lleva a
//       /voluntariados en la misma pestaña y sin noindex. Lo deja como estaba.
//   2 · Con lo que haya en la base (hoy, una sola oportunidad): la tarjeta es
//       la misma que la de las actividades, con la barra de filtros siempre
//       (15/10), y con el enlace a todo Voluntariados Chile al final.
//   3 · Con cuatro voluntariados sembrados: la barra con búsqueda, región
//       (las 16) y formato (los 3), sin tema, y cada filtro filtra. El
//       calendario y lo demás del 15/10, en `voluntariados-calendario.mjs`.
//   4 · La ficha: postular lleva a su sitio en otra pestaña, «Qué harás» y
//       «Requisitos», categoría tal cual, y NUNCA el nombre ni el correo del
//       contacto. Una dada de baja o cerrada da 404.
//   5 · A 390 y 1440 px, nada se sale de la pantalla.
//
// Siembra con un sello y lo borra al terminar. No toca el voluntariado real.
//   node pruebas/voluntariados.mjs        (desde la raíz del repo; no contra producción)
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(70)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim().split('\n').filter((l) => l.trim()).pop()?.trim();

const SELLO = `pv${Date.now()}`;
const CONTACTO = { name: `Contacto Secreto ${SELLO}`, email: `contacto-${SELLO}@secreto.test` };
const regionId = (nombre) => +tinker(`echo App\\Models\\Region::where('nombre', '${nombre}')->value('id');`);
const RM = regionId('Metropolitana de Santiago');
const VALPO = regionId('Valparaíso');

// Uno de cada: [sufijo, región, formato, título, activo, estado]
const SIEMBRA = [
  ['rm-presencial', RM, 'Presencial', `Huerta comunitaria ${SELLO}`, true, 'postulación_abierta'],
  ['valpo-online', VALPO, 'Online', `Tutorías en línea ${SELLO}`, true, 'postulación_abierta'],
  ['valpo-hibrido', VALPO, 'Híbrido', `Ropero solidario ${SELLO}`, true, 'postulación_abierta'],
  ['baja', RM, 'Presencial', `Dado de baja ${SELLO}`, false, 'postulación_abierta'],
  ['cerrada', RM, 'Presencial', `Postulación cerrada ${SELLO}`, true, 'postulación_cerrada'],
];
const sembrar = () => tinker(SIEMBRA.map(([s, r, f, titulo, activo, estado]) => `App\\Models\\Voluntariado::create([`
  + `'externo_id' => '${SELLO}-${s}', 'slug' => '${SELLO}-${s}', 'titulo' => '${titulo}', 'descripcion' => 'Descripción de prueba',`
  + `'responsabilidades' => 'Regar y cosechar', 'estado_api' => '${estado}', 'organizacion_nombre' => 'Fundación ${SELLO}',`
  + `'categoria_nombre' => 'Categoría ${SELLO}', 'enlace_postular' => 'https://voluntariadoschile.cl/oportunidades?id=${SELLO}-${s}',`
  + `'formato_original' => '${f}', 'formato' => '${f}', 'regiones_originales' => ['x'], 'region_ids' => [${r}], 'comunas_originales' => [],`
  + `'horario_tipo' => 'continuous', 'requisitos' => ['minimum_age' => ['required' => true, 'detail' => '18 años']],`
  + `'crudo' => ['contact' => ['name' => '${CONTACTO.name}', 'email' => '${CONTACTO.email}']], 'activo' => ${activo}]);`).join(' ') + " echo 'OK';");
const limpiar = () => tinker(`App\\Models\\Voluntariado::where('externo_id', 'like', '${SELLO}-%')->delete(); echo 'OK';`);

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
await p.setViewport({ width: 1440, height: 1000 });
const ir = async (ruta) => { const r = await p.goto(B + ruta, { waitUntil: 'networkidle0' }); return r.status(); };
const tarjetas = () => p.$$eval('.act-card', (cs) => cs.map((c) => ({ titulo: c.querySelector('h3')?.textContent.trim(), cta: c.querySelector('.btn')?.textContent.trim(), href: c.querySelector('.btn')?.getAttribute('href') })));

let visibleAntes = null;
try {
  const menus = async () => {
    await ir('/');
    const escritorio = await p.$eval('.nav-links a.navlink:nth-child(2)', (a) => ({ texto: a.textContent.trim(), href: a.getAttribute('href'), target: a.getAttribute('target') }));
    const movil = await p.$$eval('#menu-movil a.navlink-movil', (as) => as.map((a) => ({ texto: a.textContent.trim(), href: a.getAttribute('href'), target: a.getAttribute('target') })).find((a) => a.texto === 'Voluntariado'));
    return { escritorio, movil };
  };
  const noindex = async (ruta) => {
    const r = await p.goto(B + ruta, { waitUntil: 'networkidle0' });
    const meta = await p.$eval('meta[name="robots"]', (m) => m.content).catch(() => '');
    return { meta: /noindex/.test(meta), cabecera: /noindex/.test(r.headers()['x-robots-tag'] ?? '') };
  };
  // Setting::get devuelve un booleano, y `echo false` no imprime nada: se
  // pide '1' o '0' explícito.
  visibleAntes = tinker("echo App\\Models\\Setting::get('voluntariados_visible', false) ? '1' : '0';") === '1';
  const ponerVisible = (v) => tinker(`App\\Models\\Setting::where('clave', 'voluntariados_visible')->first()->update(['valor' => '${v}']); echo 'OK';`);
  const indexable = tinker("echo App\\Models\\Setting::get('seo_indexable', '1') ? 'si' : 'no';") === 'si';

  t('1 · Interruptor APAGADO (así llega a producción)');
  ponerVisible('0');
  let m = await menus();
  di('**«Voluntariado» lleva a voluntariadoschile.cl**', m.escritorio.texto === 'Voluntariado' && m.escritorio.href === 'https://voluntariadoschile.cl/oportunidades', m.escritorio.href);
  di('en otra pestaña, como antes', m.escritorio.target === '_blank');
  di('y en el menú del teléfono, igual', m.movil?.href === 'https://voluntariadoschile.cl/oportunidades' && m.movil.target === '_blank', JSON.stringify(m.movil));
  const enlazan = [];
  for (const ruta of ['/', '/actividades', '/actividades?vista=calendario', '/preguntas-frecuentes', '/privacidad', '/terminos-y-condiciones']) {
    await ir(ruta);
    if (await p.$$eval('a[href]', (as) => as.some((a) => new URL(a.href).pathname.startsWith('/voluntariados')))) enlazan.push(ruta);
  }
  di('**ninguna página pública enlaza a /voluntariados**', enlazan.length === 0, enlazan.join(' '));
  let n = await noindex('/voluntariados');
  di('**el listado lleva noindex: meta y cabecera X-Robots-Tag**', n.meta && n.cabecera, JSON.stringify(n));
  const unaFicha = tinker("echo App\\Models\\Voluntariado::publicos()->value('slug') ?? '';");
  if (unaFicha) {
    n = await noindex(`/voluntariados/${unaFicha}`);
    di('y la ficha también', n.meta && n.cabecera, JSON.stringify(n));
  }
  di('la sección sigue abriéndose por su dirección', await ir('/voluntariados') === 200);

  t('1 · Interruptor ENCENDIDO');
  ponerVisible('1');
  m = await menus();
  di('**«Voluntariado» lleva a /voluntariados**', m.escritorio.href.endsWith('/voluntariados'), m.escritorio.href);
  di('en la misma pestaña', m.escritorio.target === null);
  di('y en el menú del teléfono, igual', m.movil?.href.endsWith('/voluntariados') && m.movil.target === null, JSON.stringify(m.movil));
  n = await noindex('/voluntariados');
  di('**sin cabecera noindex**', ! n.cabecera, JSON.stringify(n));
  if (indexable) di('y sin meta noindex (el sitio es indexable)', ! n.meta, JSON.stringify(n));

  t('2 · Con lo que hay hoy en la base');
  const reales = +tinker('echo App\\Models\\Voluntariado::publicos()->count();');
  di('el listado responde', await ir('/voluntariados') === 200);
  const hoy = await tarjetas();
  di('una tarjeta por voluntariado público', hoy.length === reales, `${hoy.length} de ${reales}`);
  di('con la tarjeta de las actividades y «Ver voluntariado»', hoy.every((c) => /Ver voluntariado/.test(c.cta) && c.href.includes('/voluntariados/')));
  // Desde el 15/10 la barra es la de actividades: siempre visible.
  di('**la barra de filtros está siempre, aunque haya pocos**', !! await p.$('[data-filtros-voluntariados]'));
  const todos = await p.$eval('[data-todos-vch]', (a) => ({ href: a.href, target: a.target, texto: a.textContent.trim() })).catch(() => null);
  di('al final, «Ver todos los voluntariados en Voluntariados Chile»', todos?.href === 'https://voluntariadoschile.cl/oportunidades' && todos.target === '_blank', todos?.texto);
  const actividadesEnVol = await p.$$eval('.act-card .btn', (bs) => bs.filter((b) => /Ver actividad/.test(b.textContent)).length);
  di('ninguna actividad del DPS mezclada', actividadesEnVol === 0);

  t('3 · Con cuatro sembrados: los filtros');
  di('escenario sembrado', sembrar() === 'OK');
  await ir('/voluntariados');
  di('**la barra aparece**', !! await p.$('[data-filtros-voluntariados]'));
  di('con búsqueda, región y formato', !! await p.$('#f-q') && !! await p.$('#f-region') && !! await p.$('#f-formato'));
  di('**sin filtro de tema**', ! await p.$('[name="tema"]') && ! await p.$('#f-tema'));
  const opcionesRegion = await p.$$eval('#f-region option', (os) => os.map((o) => o.value).filter(Boolean).map(Number));
  di('región: las 16, como en actividades', opcionesRegion.includes(VALPO) && opcionesRegion.includes(RM) && opcionesRegion.length === 16, `(${opcionesRegion.length})`);
  const opcionesFormato = await p.$$eval('#f-formato option', (os) => os.map((o) => o.value).filter(Boolean));
  di('formato: los tres de actividades', opcionesFormato.length === 3 && ['Híbrido', 'Online', 'Presencial'].every((f) => opcionesFormato.includes(f)), opcionesFormato.join(','));
  const enLista = (await tarjetas()).map((c) => c.titulo);
  di('salen las tres abiertas', [`Huerta comunitaria ${SELLO}`, `Tutorías en línea ${SELLO}`, `Ropero solidario ${SELLO}`].every((x) => enLista.includes(x)));
  di('**ni la dada de baja ni la cerrada**', ! enLista.some((x) => /Dado de baja|Postulación cerrada/.test(x)));

  await ir(`/voluntariados?region=${VALPO}`);
  let r = (await tarjetas()).map((c) => c.titulo);
  di('filtro por región: las dos de Valparaíso', r.length === 2 && r.every((x) => /Tutorías|Ropero/.test(x)), r.join(' | '));
  await ir(`/voluntariados?formato=Online`);
  r = (await tarjetas()).map((c) => c.titulo);
  di('filtro por formato: sólo la online', r.length === 1 && /Tutorías/.test(r[0]), r.join(' | '));
  await ir(`/voluntariados?q=${encodeURIComponent('fundación ' + SELLO)}`);
  r = (await tarjetas()).map((c) => c.titulo);
  di('búsqueda por organización, sin mayúsculas ni tildes', r.length === 3, r.join(' | '));
  await ir(`/voluntariados?q=${encodeURIComponent('Huerta comunitaria ' + SELLO)}`);
  r = (await tarjetas()).map((c) => c.titulo);
  di('búsqueda por título', r.length === 1 && /Huerta/.test(r[0]), r.join(' | '));
  await ir(`/voluntariados?q=zzz-no-existe-${SELLO}`);
  di('sin resultados: lo dice y ofrece verlos todos', /No encontramos voluntariados con esos filtros/.test(await p.$eval('main, body', (b) => b.innerText)) && !! await p.$('[data-filtros-voluntariados]'));

  t('4 · La ficha');
  di('responde', await ir(`/voluntariados/${SELLO}-rm-presencial`) === 200);
  const ficha = await p.evaluate(() => ({
    h1: document.querySelector('h1')?.textContent.trim(),
    postular: (() => { const a = document.querySelector('[data-postular]'); return a && { href: a.getAttribute('href'), target: a.target, rel: a.rel }; })(),
    responsabilidades: document.querySelector('[data-ficha-responsabilidades]')?.innerText,
    requisitos: document.querySelector('[data-ficha-requisitos]')?.innerText,
    texto: document.body.innerText,
    html: document.documentElement.outerHTML,
  }));
  di('el título', ficha.h1 === `Huerta comunitaria ${SELLO}`);
  di('**«Postular en Voluntariados Chile» a su sitio, en otra pestaña**', ficha.postular?.href === `https://voluntariadoschile.cl/oportunidades?id=${SELLO}-rm-presencial` && ficha.postular.target === '_blank' && /noopener/.test(ficha.postular.rel), JSON.stringify(ficha.postular));
  di('sin formulario de inscripción', ! await p.$('form[action*="inscrip"], [data-campo="correo"]'));
  di('«Qué harás» con las responsabilidades', /Regar y cosechar/.test(ficha.responsabilidades ?? ''));
  di('«Requisitos» con su detalle', /Edad mínima/.test(ficha.requisitos ?? '') && /18 años/.test(ficha.requisitos ?? ''), ficha.requisitos);
  di('la categoría, tal cual', ficha.texto.includes(`Categoría ${SELLO}`));
  di('**ni el nombre ni el correo del contacto, en ninguna parte del HTML**', ! ficha.html.includes(CONTACTO.name) && ! ficha.html.includes(CONTACTO.email));
  di('compartir lleva al voluntariado', /Comparte este voluntariado/i.test(ficha.texto) && ficha.html.includes(encodeURIComponent(`/voluntariados/${SELLO}-rm-presencial`)));
  di('otros voluntariados cerca, de la misma región', await p.$$eval('h2', (hs) => hs.some((h) => /Otros voluntariados cerca/.test(h.textContent))) === false
    || (await tarjetas()).every((c) => ! /Tutorías|Ropero/.test(c.titulo)));
  di('**una dada de baja: 404**', await ir(`/voluntariados/${SELLO}-baja`) === 404);
  di('**una cerrada: 404**', await ir(`/voluntariados/${SELLO}-cerrada`) === 404);

  t('5 · Nada se sale');
  for (const ancho of [390, 1440]) {
    await p.setViewport({ width: ancho, height: 900 });
    for (const ruta of ['/voluntariados', `/voluntariados/${SELLO}-rm-presencial`]) {
      await ir(ruta);
      const sobra = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      di(`${ruta.replace(SELLO, '…')} a ${ancho}px sin desborde horizontal`, sobra <= 0, `${sobra}px`);
    }
  }
} finally {
  limpiar();
  // El interruptor, como estaba.
  if (visibleAntes !== null) tinker(`App\\Models\\Setting::where('clave', 'voluntariados_visible')->first()->update(['valor' => '${visibleAntes ? '1' : '0'}']); echo 'OK';`);
  await nav.close();
}

console.log(`\n  ${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
