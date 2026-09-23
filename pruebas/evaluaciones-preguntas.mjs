// Punto 8 del 23/09 — en las evaluaciones, cada respuesta junto a su pregunta.
//
// Antes se veía «Respuesta…» suelta, sin saber a qué contestaba. Ahora, en el
// panel del admin y en el del organizador, cada respuesta va bajo la pregunta
// que la originó, con el mismo texto que vio quien respondió la encuesta.
//
// Siembra `datos-evaluacion.php` y lo limpia al terminar (no puede quedarse
// puesto: ver README).
//
//   node pruebas/evaluaciones-preguntas.mjs      (desde la raíz del repo)
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG } from './credenciales.mjs';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const B = process.env.DPS_URL ?? 'http://127.0.0.1:8123';
const PHP = process.env.DPS_PHP ?? 'php';
const tinker = (linea) => execFileSync(PHP, ['artisan', 'tinker', '--execute', linea], { encoding: 'utf8' });

let ok = 0, mal = 0;
const di = (q, bien, extra = '') => { bien ? ok++ : mal++; console.log(`  ${q.padEnd(62)} ${bien ? 'OK' : '*** MAL ***'} ${extra}`); };
const t = (x) => { console.log(''); console.log(`=== ${x} ===`); console.log(''); };

// Los textos, de donde los lee el formulario: si alguien cambia uno, la
// prueba sigue comparando contra lo que de verdad se preguntó.
const preguntas = JSON.parse(tinker(`echo json_encode([
    App\\Models\\ActivityEvaluation::ESCALAS['experiencia']['pregunta'],
    App\\Models\\ActivityEvaluation::ESCALAS['motivacion']['pregunta'],
    App\\Models\\ActivityEvaluation::PREGUNTA_SIGNIFICADO,
    App\\Models\\ActivityEvaluation::PREGUNTA_ORIGEN,
], JSON_UNESCAPED_UNICODE);`).trim().split('\n').pop());

tinker("require base_path('pruebas/datos-evaluacion.php');");

const nav = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox'] });
const p = await nav.newPage();
const errores = [];
p.on('pageerror', (e) => errores.push(String(e)));
await p.setViewport({ width: 1440, height: 900 });

const entrar = async (login, correo, clave) => {
    await p.goto(`${B}${login}`);
    await p.type('[name="email"]', correo);
    await p.type('[name="password"]', clave);
    await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);
};

// Pares [pregunta, respuesta] de la primera evaluación de la pantalla.
const pares = () => p.evaluate(() => {
    const dl = document.querySelector('[data-respuestas]');
    if (! dl) return null;
    return [...dl.querySelectorAll('.eval-respuesta')].map((r) => ({
        pregunta: r.querySelector('dt').textContent.trim(),
        respuesta: r.querySelector('dd').textContent.trim(),
        altoPregunta: r.querySelector('dt').getBoundingClientRect().height,
    }));
});

const comprobar = async (donde) => {
    const r = await pares();
    di(`${donde}: las respuestas llevan pregunta`, !! r && r.length > 0, `${r?.length ?? 0} respuestas`);
    di(`${donde}: y la pregunta se ve`, !! r && r.every((x) => x.altoPregunta > 0));
    di(`${donde}: las cuatro, en el orden del formulario`, JSON.stringify(r?.map((x) => x.pregunta)) === JSON.stringify(preguntas),
        r?.map((x) => x.pregunta.slice(0, 30)).join(' | '));
    const sig = r?.find((x) => x.pregunta === preguntas[2]);
    di(`${donde}: la abierta, bajo «¿qué significa…?»`, !! sig && /^Respuesta de prueba/.test(sig.respuesta), sig?.respuesta);
    const nota = r?.find((x) => x.pregunta === preguntas[0]);
    di(`${donde}: la nota dice en qué escala va`, /^\d de 5 \(1 = Muy mala, 5 = Excelente\)$/.test(nota?.respuesta ?? ''), nota?.respuesta);
    di(`${donde}: cómo se enteró, bajo su pregunta`, r?.find((x) => x.pregunta === preguntas[3])?.respuesta === 'Redes sociales');
};

try {
    t('1 · Panel del organizador');
    await entrar('/mi-cuenta/login', ORG, CLAVE_ORG);
    await p.goto(`${B}/mi-cuenta/evaluaciones`, { waitUntil: 'networkidle2' });
    await comprobar('organizador');
    di('organizador: ya no hay «Se enteró por:» suelto', ! (await p.evaluate(() => document.body.innerText)).includes('Se enteró por:'));

    await p.goto(`${B}/`, { waitUntil: 'domcontentloaded' });
    await p.evaluate(async () => {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        await fetch('/mi-cuenta/logout', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + encodeURIComponent(token) });
    });

    t('2 · Panel del admin');
    await entrar('/admin/login', ADMIN, CLAVE_ADMIN);
    await p.goto(`${B}/admin/evaluaciones`, { waitUntil: 'networkidle2' });
    await comprobar('admin');
    di('admin: la columna se llama «Respuestas»', await p.evaluate(() => [...document.querySelectorAll('thead th')].some((th) => th.textContent.trim() === 'Respuestas')));
    di('admin: sigue pudiendo ordenar por notas', await p.evaluate(() => !! [...document.querySelectorAll('thead a')].find((a) => a.textContent.trim().startsWith('Notas'))));

    t('3 · El formulario sigue preguntando lo mismo');
    const slug = tinker(`echo App\\Models\\Activity::where('slug','like','prueba-eval-%')->where('estado','publicada')->value('slug');`).trim().split('\n').pop();
    await p.goto(`${B}/evaluar/${slug}`, { waitUntil: 'networkidle2' });
    const etiquetas = await p.evaluate(() => [...document.querySelectorAll('.evaluacion-lbl')].map((l) => l.textContent.replace('*', '').trim()));
    di('«¿qué significa…?» con el mismo texto', etiquetas.includes(preguntas[2]));
    di('«¿cómo te enteraste…?» con el mismo texto', etiquetas.includes(preguntas[3]));

    t('4 · Sin errores de JavaScript');
    di('ninguno', errores.length === 0, errores.join(' | '));
} finally {
    await nav.close();
    tinker("$limpiar = true; require base_path('pruebas/datos-evaluacion.php');");
}

console.log('');
console.log(`${ok} bien, ${mal} mal`);
process.exit(mal ? 1 : 0);
