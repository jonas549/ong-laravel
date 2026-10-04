// La imagen de difusión de cada actividad (1080×1350, para Instagram).
//
// Siembra `datos-difusion.php` (sin foto, larga, en línea, en revisión), genera
// la imagen de cada una en Chrome y mira lo que se dibujó: que cada texto quede
// dentro de su columna, que los casos límite digan lo que tienen que decir, y
// que sin logo salgan las iniciales. Además, los botones, la descarga, el pie
// editable, los permisos y el teléfono. Limpia el escenario al terminar.
//
//   node pruebas/difusion.mjs      (desde la raíz del repo)
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ORG, CLAVE_ORG } from './credenciales.mjs';
import { PNG } from 'pngjs';
import { writeFileSync, rmSync } from 'node:fs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const MYSQL = process.env.DPS_MYSQL ?? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const sql = (q) => execFileSync(MYSQL, ['-uroot', 'ong_laravel', '-N', '--default-character-set=utf8mb4', '-e', q]).toString().trim();
const tinker = (php) => execFileSync(PHP, ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' });
const olvidarAjustes = () => tinker("Illuminate\\Support\\Facades\\Cache::forget(App\\Models\\Setting::CACHE_KEY);");

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(64)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

const salida = tinker("require base_path('pruebas/datos-difusion.php');");
const ids = JSON.parse(salida.slice(salida.indexOf('{'), salida.lastIndexOf('}') + 1));

const orgId = sql(`select o.id from organizations o join users u on u.id = o.user_id where u.email = '${ORG}'`);
const logoAntes = sql(`select ifnull(logo_path, '') from organizations where id = ${orgId}`);
const fechasAntes = sql(`select ifnull(valor, '') from settings where clave = 'difusion_fechas'`);

// Las columnas del dibujo, en píxeles del lienzo (ver resources/js/difusion.js).
const COLUMNAS = [
    { nombre: 'cuándo', x0: 162, x1: 316, y0: 905, y1: 1005 },
    // La dirección usa la columna entera, desde el borde del pin (punto 3 del 30/09).
    { nombre: 'dónde', x0: 354, x1: 556, y0: 905, y1: 1001 },
    { nombre: 'cupos', x0: 590, x1: 766, y0: 915, y1: 1005 },
    { nombre: 'formato', x0: 805, x1: 990, y0: 950, y1: 1005 },
    { nombre: 'organiza', x0: 218, x1: 440, y0: 1070, y1: 1140 },
    { nombre: 'web', x0: 572, x1: 945, y0: 1050, y1: 1100 },
    { nombre: 'pie', x0: 222, x1: 742, y0: 1220, y1: 1330 },
];

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1440, height: 900 });

const generar = async (id) => {
    await p.goto(`${B}/mi-cuenta/actividades/${id}/difusion`, { waitUntil: 'networkidle2' });
    await p.waitForFunction(() => ['lista', 'error'].includes(Alpine.$data(document.querySelector('.difusion'))?.estado), { timeout: 20000 });
    return p.evaluate(async () => {
        const d = Alpine.$data(document.querySelector('.difusion'));
        const img = document.querySelector('[data-difusion-imagen]');
        await img.decode().catch(() => {});
        const a = document.querySelector('[data-descargar]');
        return {
            estado: d.estado,
            ancho: img.naturalWidth,
            alto: img.naturalHeight,
            foto: d.dibujo?.foto,
            logo: d.dibujo?.logo,
            // El estado de Alpine es un proxy: se copia a JSON para que llegue entero.
            textos: JSON.parse(JSON.stringify(d.dibujo?.textos ?? [])),
            descarga: a.getAttribute('download'),
            href: a.getAttribute('href') ?? '',
        };
    });
};

// Todo texto dentro del lienzo y, si cae en una columna, dentro de ella.
const fueraDeSitio = (textos) => textos.filter((x) => {
    if (x.x < 0 || x.x + x.ancho > 1080) return true;
    const col = COLUMNAS.find((c) => x.y >= c.y0 && x.y <= c.y1 && x.x >= c.x0 - 2 && x.x <= c.x1);
    return col ? x.x + x.ancho > col.x1 + 1 : false;
}).map((x) => `«${x.t}» (${x.x}+${x.ancho})`);

const hay = (textos, re) => textos.some((x) => re.test(x.t));

/**
 * La dirección tal como quedó escrita: las líneas grises de la columna,
 * junto al pin o desde su borde. Las de la región van en tinta oscura.
 */
const direccion = (textos) => textos.filter((x) => [354, 404].includes(x.x) && x.y > 930 && x.y <= 1003 && x.color === '#63666a')
    .map((x) => x.t).join(' ');

/*
 * El corazón asoma por encima de la caja: su borde de arriba está en y=1111
 * desde x≈787 (medido en la capa). Ningún texto que llegue hasta ahí puede
 * bajar de y=1098 contando el descendente (punto 3 del 30/09).
 */
const tocaElCorazon = (textos) => textos.filter((x) => x.y > 1040 && x.y < 1200 && x.x < 990 && x.x + x.ancho > 787
    && x.y + 6 > 1104).map((x) => `«${x.t}» y=${x.y}`);

try {
    await p.goto(`${B}/mi-cuenta/login`);
    await p.type('[name="email"]', ORG);
    await p.type('[name="password"]', CLAVE_ORG);
    await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

    t('1 · Con foto: la actividad de la pieza de referencia');
    const ref = await generar(1);
    di('se genera', ref.estado === 'lista', ref.estado);
    di('a 1080×1350', ref.ancho === 1080 && ref.alto === 1350, `${ref.ancho}×${ref.alto}`);
    di('con su foto y su logo', ref.foto && ref.logo);
    di('el titular', hay(ref.textos, /^Jornada de reforestación urbana$/));
    di('«¡Súmate a esta actividad!»', hay(ref.textos, /^¡Súmate a esta actividad!$/));
    di('la web del sitio, sacada de APP_URL', hay(ref.textos, new RegExp(`^${tinker("echo parse_url(config('app.url'), PHP_URL_HOST);").trim().split('\n').pop().replace(/\./g, '\\.')}$`)));
    di('ningún texto fuera de su sitio', fueraDeSitio(ref.textos).length === 0, fueraDeSitio(ref.textos).join(' · '));
    di('se descarga como PNG con nombre propio', /^difusion-.+\.png$/.test(ref.descarga ?? '') && ref.href.startsWith('blob:'), ref.descarga);

    t('2 · Sin foto, con horas y de varios días');
    const sinFoto = await generar(ids['sin-foto']);
    di('se genera igual, con el banner de la edición', sinFoto.estado === 'lista' && sinFoto.foto);
    // Sin espacio al final: según el mes, el nombre baja a la línea siguiente.
    di('la fecha de varios días', hay(sinFoto.textos, /^\d+ al \d+ de\b/), sinFoto.textos.find((x) => / al /.test(x.t))?.t);
    di('las horas', hay(sinFoto.textos, /^10:00 – 13:00$/));
    di('los cupos', hay(sinFoto.textos, /^55 disponibles$/));
    di('la descripción sin los saltos de línea', hay(sinFoto.textos, /barrio\. Trae/));
    di('ningún texto fuera de su sitio', fueraDeSitio(sinFoto.textos).length === 0, fueraDeSitio(sinFoto.textos).join(' · '));
    di('la dirección, entera', direccion(sinFoto.textos) === 'Sede vecinal, calle Los Aromos 123', direccion(sinFoto.textos));
    di('«Más información en:» y la web no tocan el corazón', tocaElCorazon(sinFoto.textos).length === 0, tocaElCorazon(sinFoto.textos).join(' · '));

    t('3 · Todo al máximo');
    const larga = await generar(ids.larga);
    const titulo = larga.textos.filter((x) => x.fuente.startsWith('800') && x.y > 660 && x.y < 850);
    di('el titular en dos líneas como mucho', titulo.length === 2, `${titulo.length} líneas`);
    di('cortado con «…» en palabra entera', titulo.at(-1)?.t.endsWith('…') && ! / …$/.test(titulo.at(-1)?.t), titulo.at(-1)?.t);
    // El navegador normaliza la fuente y el peso 400 no aparece: se busca por familia.
    const desc = larga.textos.filter((x) => /Inter/.test(x.fuente) && x.y > 700 && x.y < 860);
    di('la descripción, con la línea que queda', desc.length === 1 && desc[0].t.endsWith('…'), desc[0]?.t);
    di('los cupos agotados', hay(larga.textos, /^Cupos agotados$/));
    di('la región larga no se sale', fueraDeSitio(larga.textos).length === 0, fueraDeSitio(larga.textos).join(' · '));
    di('ni la fecha de varios meses', ! larga.textos.some((x) => x.y > 900 && x.x === 162 && x.t.endsWith('…')));
    /*
     * La columna tiene 76 px de alto para comuna, región y dirección. Con la
     * región más larga del país en tres líneas, una dirección de ~90
     * caracteres sale entera; la del escenario (130) ya no cabe ni a 11 px y
     * se corta con «…», pero dentro de la columna.
     */
    di('la dirección absurda se corta con «…», dentro de la columna',
        direccion(larga.textos).endsWith('…') && ! larga.textos.some((x) => x.x === 354 && x.x + x.ancho > 556), direccion(larga.textos));
    const DIR_REAL = 'Av. Libertador Bernardo O\'Higgins 1234, departamento 56, frente a la plaza de armas';
    sql(`update activities set direccion = '${DIR_REAL.replace(/'/g, "''")}' where id = ${ids.larga}`);
    const larga2 = await generar(ids.larga);
    di(`una dirección larga real (${DIR_REAL.length} caracteres), entera, con la región más larga`, direccion(larga2.textos) === DIR_REAL, direccion(larga2.textos));
    di('y sin salirse de su sitio', fueraDeSitio(larga2.textos).length === 0, fueraDeSitio(larga2.textos).join(' · '));

    t('4 · En línea, sin fecha y sin inscripción');
    const online = await generar(ids.online);
    di('«Actividad en línea»', hay(online.textos, /^Actividad en/));
    di('«Fecha por definir»', hay(online.textos, /Fecha por/));
    di('«Sin inscripción previa»', hay(online.textos, /Sin inscripción/));
    di('ningún texto fuera de su sitio', fueraDeSitio(online.textos).length === 0, fueraDeSitio(online.textos).join(' · '));

    t('5 · Sin logo: las iniciales');
    sql(`update organizations set logo_path = null where id = ${orgId}`);
    const sinLogo = await generar(ids['sin-foto']);
    const iniciales = tinker(`echo App\\Models\\Organization::find(${orgId})->iniciales;`).trim().split('\n').pop();
    di('no intenta un logo', sinLogo.logo === false);
    di(`salen sus iniciales («${iniciales}»)`, hay(sinLogo.textos, new RegExp(`^${iniciales}$`)));
    sql(`update organizations set logo_path = ${logoAntes ? `'${logoAntes}'` : 'null'} where id = ${orgId}`);

    /*
     * 5b · Un logo blanco no desaparece (04/10). Iba en un cuadrado blanco:
     * dentro no se veía nada. Se fabrica un logo blanco sobre transparente y
     * uno naranja, y se leen los píxeles del cuadrado en la imagen generada.
     */
    t('5b · Un logo blanco o claro se distingue en su cuadrado');
    const fabricarLogo = (nombre, [r, g, b]) => {
        const png = new PNG({ width: 200, height: 200 });
        for (let y = 0; y < 200; y++) for (let x = 0; x < 200; x++) {
            const i = (200 * y + x) << 2;
            const dentro = (x - 100) ** 2 + (y - 100) ** 2 < 80 ** 2;
            png.data[i] = r; png.data[i + 1] = g; png.data[i + 2] = b; png.data[i + 3] = dentro ? 255 : 0;
        }
        writeFileSync(`storage/app/public/organizaciones/${nombre}`, PNG.sync.write(png));
        return `storage/organizaciones/${nombre}`;
    };
    /** Luminancia (0-1) de un punto de la imagen generada, en sus coordenadas de 1080×1350. */
    const luz = (x, y) => p.evaluate((x, y) => {
        const img = document.querySelector('[data-difusion-imagen]');
        const c = document.createElement('canvas');
        c.width = img.naturalWidth; c.height = img.naturalHeight;
        const g = c.getContext('2d');
        g.drawImage(img, 0, 0);
        const [r, v, a] = g.getImageData(x, y, 1, 1).data;
        return (0.2126 * r + 0.7152 * v + 0.0722 * a) / 255;
    }, x, y);
    // El cuadrado va en x 133-199, y 1063-1129: una esquina de fondo y el centro.
    const FONDO_XY = [138, 1068], CENTRO_XY = [166, 1096];

    const blanco = fabricarLogo(`prueba-blanco-${Date.now()}.png`, [255, 255, 255]);
    sql(`update organizations set logo_path = '${blanco}' where id = ${orgId}`);
    await generar(ids['sin-foto']);
    const fondoBlanco = await luz(...FONDO_XY), logoBlanco = await luz(...CENTRO_XY);
    di('con un logo blanco, el fondo del cuadrado es oscuro', fondoBlanco < 0.4, fondoBlanco.toFixed(2));
    di('y el logo se ve: contraste de verdad entre los dos', logoBlanco - fondoBlanco > 0.5, `${logoBlanco.toFixed(2)} sobre ${fondoBlanco.toFixed(2)}`);

    const naranja = fabricarLogo(`prueba-naranja-${Date.now()}.png`, [229, 114, 0]);
    sql(`update organizations set logo_path = '${naranja}' where id = ${orgId}`);
    await generar(ids['sin-foto']);
    const fondoColor = await luz(...FONDO_XY);
    di('con un logo de color, el fondo es un gris muy suave', fondoColor > 0.9 && fondoColor < 0.99, fondoColor.toFixed(3));

    sql(`update organizations set logo_path = ${logoAntes ? `'${logoAntes}'` : 'null'} where id = ${orgId}`);
    for (const f of [blanco, naranja]) rmSync(f.replace('storage/', 'storage/app/public/'), { force: true });

    t('6 · El pie se edita desde Configuración');
    sql(`update settings set valor = '8 y 9 de noviembre' where clave = 'difusion_fechas'`);
    olvidarAjustes();
    const conPie = await generar(ids['sin-foto']);
    di('la fecha nueva sale en el pie', hay(conPie.textos, /^Día del Patrimonio Social - 8 y 9 de noviembre$/));

    t('7 · Sólo actividades publicadas y propias');
    await p.goto(`${B}/mi-cuenta/actividades/${ids.revision}/difusion`, { waitUntil: 'networkidle2' });
    di('en revisión no se genera: vuelve a Mis actividades', new URL(p.url()).pathname === '/mi-cuenta/actividades');
    di('y dice por qué', (await p.evaluate(() => document.body.innerText)).includes('cuando la actividad se publica'));
    const ajena = sql(`select id from activities where organization_id != ${orgId} and estado = 'publicada' limit 1`);
    const r = await p.goto(`${B}/mi-cuenta/actividades/${ajena}/difusion`);
    di('la de otra organización no se puede ver', [403, 404].includes(r.status()), String(r.status()));

    t('8 · Los botones');
    await p.goto(`${B}/mi-cuenta/actividades`, { waitUntil: 'networkidle2' });
    const botones = await p.evaluate((ids) => {
        const de = (id) => [...document.querySelectorAll('[data-difusion]')].find((b) =>
            b.closest('article, .card, li, div[style]')?.innerHTML.includes(`/actividades/${id}/editar`));
        const pub = de(ids['sin-foto']);
        const rev = de(ids.revision);
        return {
            pub: pub?.tagName === 'A' && pub.getAttribute('href').endsWith(`/actividades/${ids['sin-foto']}/difusion`),
            rev: rev?.getAttribute('aria-disabled') === 'true',
            textos: [pub?.textContent.trim(), rev?.textContent.trim()],
        };
    }, ids);
    di('en Mis actividades, junto a Editar y Duplicar', botones.pub);
    di('desactivado mientras no esté publicada', botones.rev);
    // Se llama igual en todo el sitio.
    di('y dice «Imagen de difusión»', botones.textos.every((x) => x === 'Imagen de difusión'), botones.textos.join(' | '));
    const slug = sql(`select slug from activities where id = ${ids['sin-foto']}`);
    await p.goto(`${B}/publicar-actividad/${slug}/listo`, { waitUntil: 'networkidle2' });
    const final = await p.evaluate(() => {
        const a = document.querySelector('[data-difusion]');
        return { href: a?.getAttribute('href') ?? '', texto: a?.textContent.trim() };
    });
    di('y en la pantalla final de publicar', final.href.endsWith(`/actividades/${ids['sin-foto']}/difusion`));
    di('con el mismo nombre', final.texto === 'Imagen de difusión', final.texto);
    await p.goto(`${B}/mi-cuenta/actividades/${ids['sin-foto']}/difusion`, { waitUntil: 'networkidle2' });
    di('y la pantalla se titula igual', await p.$eval('h1', (h) => h.textContent.trim()) === 'Imagen de difusión');

    t('9 · En el teléfono');
    await p.setViewport({ width: 390, height: 844 });
    await generar(1);
    di('la pantalla no desborda', await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
    di('la vista previa cabe', await p.evaluate(() => document.querySelector('.difusion-vista').getBoundingClientRect().right <= window.innerWidth));

    t('10 · El botón de compartir hace siempre algo (punto 4 del 30/09)');
    await p.setViewport({ width: 1440, height: 900 });
    await nav.defaultBrowserContext().overridePermissions(B, ['clipboard-read', 'clipboard-write', 'clipboard-sanitized-write']);
    await generar(1);
    const escritorio = await p.evaluate(() => {
        const b = document.querySelector('[data-compartir]');
        return { modo: Alpine.$data(document.querySelector('.difusion')).modo, visible: b?.getBoundingClientRect().height > 0, texto: b?.textContent.trim() };
    });
    di('en el escritorio el botón está y dice «Copiar imagen»', escritorio.visible && escritorio.texto === 'Copiar imagen', JSON.stringify(escritorio));
    await p.click('[data-compartir]');
    await new Promise((r) => setTimeout(r, 800));
    const copiado = await p.evaluate(async () => {
        const items = await navigator.clipboard.read();
        const blob = await items[0]?.getType('image/png');
        const img = await createImageBitmap(blob);
        return { tipo: blob?.type, ancho: img.width, alto: img.height, aviso: document.querySelector('[data-aviso-compartir]')?.innerText };
    }).catch((e) => ({ error: String(e) }));
    di('al pulsarlo, la imagen queda en el portapapeles a 1080×1350', copiado.ancho === 1080 && copiado.alto === 1350, JSON.stringify(copiado));
    di('y lo dice en pantalla', /copiada/i.test(copiado.aviso ?? ''), copiado.aviso);

    /*
     * El teléfono, simulado: puntero de dedo y `navigator.share` de mentira que
     * apunta lo que recibe. Con Chrome sin pantalla no hay diálogo de compartir
     * de verdad; esto prueba la lógica, no el diálogo del sistema.
     */
    const telefono = async (comoAcaba) => {
        const pg = await nav.newPage();
        await pg.evaluateOnNewDocument((comoAcaba) => {
            const mm = window.matchMedia.bind(window);
            window.matchMedia = (q) => (q === '(pointer: coarse)' ? { matches: true, addEventListener() {}, removeEventListener() {} } : mm(q));
            window.__compartido = [];
            navigator.canShare = () => true;
            navigator.share = async (d) => {
                window.__compartido.push({ archivos: d.files?.map((f) => `${f.name}:${f.type}:${f.size > 0}`) });
                if (comoAcaba === 'cancela') throw new DOMException('cancelado', 'AbortError');
                if (comoAcaba === 'falla') throw new DOMException('no permitido', 'NotAllowedError');
            };
            window.__descargas = 0;
            const clic = HTMLAnchorElement.prototype.click;
            HTMLAnchorElement.prototype.click = function () { if (this.download) { window.__descargas++; return; } return clic.call(this); };
        }, comoAcaba);
        await pg.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
        await pg.goto(`${B}/mi-cuenta/actividades/1/difusion`, { waitUntil: 'networkidle2' });
        await pg.waitForFunction(() => Alpine.$data(document.querySelector('.difusion'))?.estado === 'lista', { timeout: 20000 });
        const texto = await pg.$eval('[data-compartir]', (b) => b.textContent.trim());
        await pg.click('[data-compartir]');
        await new Promise((r) => setTimeout(r, 600));
        const r = await pg.evaluate(() => ({ compartido: window.__compartido, descargas: window.__descargas,
            aviso: document.querySelector('[data-aviso-compartir]')?.innerText ?? '' }));
        await pg.close();
        await p.bringToFront();
        return { texto, ...r };
    };
    const tBien = await telefono('bien');
    di('en el teléfono dice «Compartir»', tBien.texto === 'Compartir', tBien.texto);
    di('y abre el compartir nativo con el PNG', tBien.compartido.length === 1 && /\.png:image\/png:true$/.test(tBien.compartido[0].archivos?.[0] ?? ''), JSON.stringify(tBien.compartido));
    const tCancela = await telefono('cancela');
    di('cerrar el diálogo sin elegir no descarga ni avisa', tCancela.descargas === 0 && ! tCancela.aviso, JSON.stringify(tCancela));
    const tFalla = await telefono('falla');
    di('si el diálogo falla, se descarga y lo dice', tFalla.descargas === 1 && /descarg/i.test(tFalla.aviso), JSON.stringify(tFalla));

    t('11 · Sin errores de JavaScript');
    di('ninguno', errores.length === 0, errores.join(' | '));
} finally {
    sql(`update organizations set logo_path = ${logoAntes ? `'${logoAntes}'` : 'null'} where id = ${orgId}`);
    sql(`update settings set valor = '${fechasAntes.replace(/'/g, "''")}' where clave = 'difusion_fechas'`);
    olvidarAjustes();
    tinker("$limpiar = true; require base_path('pruebas/datos-difusion.php');");
    await nav.close();
}

console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
