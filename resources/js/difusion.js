/*
 * La imagen de difusión de una actividad: 1080×1350, lista para Instagram.
 *
 * Se dibuja aquí, en un <canvas>, apilando las capas de la plantilla de la
 * ONG (public/img/difusion/) y escribiendo encima los datos de la actividad,
 * que llegan ya redactados desde `DatosDifusion`. Nada sale del sitio: ni
 * servicios de fuera ni nada instalado en el servidor.
 *
 * Las medidas están en píxeles del lienzo final y salen de la pieza de
 * referencia que armó la ONG (completo.jpeg). Todo lo que no cabe se
 * resuelve aquí: el titular baja de tamaño hasta dos líneas, y el titular y
 * la descripción se reparten tres líneas entre los dos; lo que sobra se corta
 * con «…» en la última palabra entera.
 */

const ANCHO = 1080;
const ALTO = 1350;

const NARANJO = '#e67824';
const TINTA = '#33363a';
const GRIS = '#63666a';
const TITULO = 'Raleway';
const TEXTO = 'Inter';

/** Las capas fijas y dónde van. */
const CAPAS = {
    fondo: { x: 0, y: 0, w: 1080, h: 1350 },
    caja: { x: 56, y: 851, w: 941, h: 296 },
    corazon: { x: 747, y: 1111, w: 297, h: 218 },
    'icono-cuando': { x: 97, y: 874, w: 231, h: 114 },
    'icono-donde': { x: 354, y: 872, w: 212, h: 114 },
    'icono-cupos': { x: 586, y: 871, w: 190, h: 112 },
    'icono-formato': { x: 805, y: 872, w: 45, h: 42 },
    // El de la capa venía más arriba y más pequeño que en la pieza de
    // referencia; manda la referencia.
    'icono-web': { x: 496, y: 1063, w: 54, h: 54 },
    // Los dos logos están guardados al doble, para que no se vean blandos.
    'logo-dps': { x: 657, y: 28, w: 363, h: 99 },
    'logo-comunidad': { x: 38, y: 1213, w: 152, h: 112 },
};

/** El hueco de la foto: la franja naranja de arriba se pinta encima. */
const FOTO = { x: 60, y: 40, w: 959, h: 599, radio: 33 };

const fuente = (peso, tam, familia) => `${peso} ${tam}px "${familia}"`;

function cargarImagen(src) {
    return new Promise((ok, mal) => {
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => ok(img);
        img.onerror = () => mal(new Error(`No se pudo cargar ${src}`));
        img.src = src;
    });
}

/** Reparte un texto en líneas de como mucho `ancho` px. */
function repartir(ctx, texto, ancho) {
    const palabras = String(texto ?? '').split(/\s+/).filter(Boolean);
    const lineas = [];
    let linea = '';

    for (const p of palabras) {
        const prueba = linea ? `${linea} ${p}` : p;
        if (ctx.measureText(prueba).width <= ancho || ! linea) {
            linea = prueba;
        } else {
            lineas.push(linea);
            linea = p;
        }
    }
    if (linea) lineas.push(linea);

    return lineas;
}

/**
 * Como `repartir`, pero con un máximo de líneas: la última se corta en la
 * última palabra entera que deje sitio a «…». Una palabra sola más ancha que
 * todo (una URL pegada) se corta por letras.
 */
function repartirHasta(ctx, texto, ancho, max) {
    const lineas = repartir(ctx, texto, ancho);
    if (lineas.length <= max && lineas.every((l) => ctx.measureText(l).width <= ancho)) return lineas;

    const quedan = lineas.slice(0, max);
    let ultima = lineas.slice(max - 1).join(' ');
    while (ultima.includes(' ') && ctx.measureText(`${ultima}…`).width > ancho) {
        ultima = ultima.slice(0, ultima.lastIndexOf(' '));
    }
    while (ultima.length > 1 && ctx.measureText(`${ultima}…`).width > ancho) {
        ultima = ultima.slice(0, -1);
    }
    quedan[max - 1] = `${ultima.replace(/[\s,.;:–-]+$/, '')}…`;

    return quedan.map((l, i) => (i < max - 1 && ctx.measureText(l).width > ancho ? recortar(ctx, l, ancho) : l));
}

/**
 * Busca el mayor tamaño, de `tam` hacia abajo hasta `min`, con el que el texto
 * cabe en `max` líneas; deja puesta esa fuente y devuelve las líneas. Si ni
 * con el mínimo cabe, corta con «…». Achicar antes que cortar: un dato a
 * medias («13 de septiembre…») no sirve en una pieza de difusión.
 */
function ajustar(ctx, texto, ancho, max, tam, min, peso, familia) {
    for (let t = tam; t >= min; t -= 1) {
        ctx.font = fuente(peso, t, familia);
        const lineas = repartir(ctx, texto, ancho);
        if (lineas.length <= max && lineas.every((l) => ctx.measureText(l).width <= ancho)) return { lineas, tam: t };
    }
    ctx.font = fuente(peso, min, familia);
    return { lineas: repartirHasta(ctx, texto, ancho, max), tam: min };
}

function recortar(ctx, texto, ancho) {
    let t = texto;
    while (t.length > 1 && ctx.measureText(`${t}…`).width > ancho) t = t.slice(0, -1);
    return t === texto ? t : `${t}…`;
}

function escribir(ctx, lineas, x, y, alto, alinear = 'left') {
    ctx.textAlign = alinear;
    lineas.forEach((l, i) => ctx.fillText(l, x, y + i * alto));
    return y + (lineas.length - 1) * alto;
}

function rectRedondeado(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

/** Dibuja `img` llenando el hueco, recortando lo que sobre (como `cover`). */
function llenar(ctx, img, x, y, w, h) {
    const escala = Math.max(w / img.naturalWidth, h / img.naturalHeight);
    const sw = w / escala;
    const sh = h / escala;
    ctx.drawImage(img, (img.naturalWidth - sw) / 2, (img.naturalHeight - sh) / 2, sw, sh, x, y, w, h);
}

/** Dibuja `img` entera dentro del hueco, centrada (como `contain`). */
function encajar(ctx, img, x, y, w, h) {
    const escala = Math.min(w / img.naturalWidth, h / img.naturalHeight);
    const dw = img.naturalWidth * escala;
    const dh = img.naturalHeight * escala;
    ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
}

/**
 * Titular y descripción entre y=668 y y=846, centrados en ese hueco. El
 * titular prueba de 54 a 40 px hasta caber en dos líneas; la descripción se
 * queda con las líneas que sobren de tres.
 */
function textosPrincipales(ctx, datos) {
    // En una línea, el titular a 54 px; si no cabe, baja hasta 40 en dos.
    let t = ajustar(ctx, datos.titulo, 940, 1, 54, 46, 800, TITULO);
    if (ctx.measureText(t.lineas[0]).width > 940 || t.lineas[0].endsWith('…')) {
        t = ajustar(ctx, datos.titulo, 940, 2, 50, 40, 800, TITULO);
    }
    const titulo = t.lineas;
    const tam = t.tam;
    const altoTitulo = Math.round(tam * 1.12);

    // La descripción se queda con las líneas que dejen libres: tres en total.
    const d = datos.descripcion
        ? ajustar(ctx, datos.descripcion, 940, 3 - titulo.length, 29, 26, 400, TEXTO)
        : { lineas: [], tam: 29 };
    const altoDesc = Math.round(d.tam * 1.45);

    const alto = titulo.length * altoTitulo + (d.lineas.length ? 12 + d.lineas.length * altoDesc : 0);
    let y = 668 + Math.max(0, (178 - alto) / 2) + tam * 0.9;

    ctx.fillStyle = NARANJO;
    ctx.font = fuente(800, tam, TITULO);
    y = escribir(ctx, titulo, ANCHO / 2, y, altoTitulo, 'center');

    if (d.lineas.length) {
        ctx.fillStyle = TINTA;
        ctx.font = fuente(400, d.tam, TEXTO);
        escribir(ctx, d.lineas, ANCHO / 2, y + 12 + altoDesc - 4, altoDesc, 'center');
    }
}

/** Las cuatro columnas de la caja: cuándo, dónde, cupos, formato. */
function datosDeLaCaja(ctx, datos) {
    const etiqueta = (texto, x, y) => {
        ctx.fillStyle = NARANJO;
        ctx.font = fuente(800, 17, TITULO);
        ctx.textAlign = 'left';
        ctx.fillText(texto.toUpperCase(), x, y);
    };
    // Cada valor baja de tamaño antes de cortarse: ver `ajustar`.
    const valor = (texto, x, y, ancho, max, tam = 20, min = 16, color = TINTA) => {
        ctx.fillStyle = color;
        const a = ajustar(ctx, texto, ancho, max, tam, min, 400, TEXTO);
        return escribir(ctx, a.lineas, x, y, Math.round(a.tam * 1.3));
    };

    /*
     * Las columnas van pegadas a sus iconos y antes de su divisoria, medidos
     * en las capas: cuándo 97–146 | 326, dónde 354–390 | 564, cupos
     * 587–652 | 775, formato 805–849. Las capas no coinciden al píxel con la
     * pieza de referencia, y colocar el texto respecto a ellas garantiza que
     * nunca pise un icono.
     */
    // Cuándo: la fecha en dos líneas, o en tres si es de varios meses; debajo,
    // las horas.
    etiqueta('Cuándo', 162, 897);
    let fecha = ajustar(ctx, datos.cuando.fecha, 154, 2, 20, 17, 400, TEXTO);
    if (fecha.lineas.some((l) => l.endsWith('…'))) fecha = ajustar(ctx, datos.cuando.fecha, 154, 3, 18, 15, 400, TEXTO);
    ctx.fillStyle = TINTA;
    let y = escribir(ctx, fecha.lineas, 162, 925, Math.round(fecha.tam * 1.3));
    if (datos.cuando.horas) valor(datos.cuando.horas, 162, y + 30, 154, 1);

    /*
     * Dónde: comuna y región; la dirección, más pequeña, debajo. La columna es
     * estrecha y hay regiones muy largas («Metropolitana de Santiago»,
     * «Libertador General Bernardo O'Higgins»): el lugar puede ocupar tres
     * líneas, y entonces la dirección se queda en una.
     */
    etiqueta('Dónde', 404, 897);
    const lugar = ajustar(ctx, datos.donde.lugar, 152, 2, 20, 17, 400, TEXTO).lineas.some((l) => l.endsWith('…'))
        ? ajustar(ctx, datos.donde.lugar, 152, 3, 18, 15, 400, TEXTO)
        : ajustar(ctx, datos.donde.lugar, 152, 2, 20, 17, 400, TEXTO);
    ctx.fillStyle = TINTA;
    y = escribir(ctx, lugar.lineas, 404, 925, Math.round(lugar.tam * 1.25));
    if (datos.donde.direccion) {
        valor(datos.donde.direccion, 404, y + 24, 152, lugar.lineas.length > 2 ? 1 : 2, 15, 13, GRIS);
    }

    etiqueta('Cupos', 664, 897);
    valor(datos.cupos, 590, 935, 176, 2);

    etiqueta('Formato', 805, 942);
    valor(datos.formato, 805, 972, 180, 1);
}

/** «Organiza», con su logo o sus iniciales, y «Más información en:». */
function pieDeLaCaja(ctx, datos, logo) {
    ctx.fillStyle = NARANJO;
    ctx.font = fuente(800, 16, TITULO);
    ctx.textAlign = 'left';
    ctx.fillText('ORGANIZA', 133, 1047);

    const L = { x: 133, y: 1063, s: 66, r: 9 };
    if (logo) {
        ctx.save();
        rectRedondeado(ctx, L.x, L.y, L.s, L.s, L.r);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.strokeStyle = '#f0dcc8';
        ctx.lineWidth = 1.5;
        ctx.stroke();
        ctx.clip();
        encajar(ctx, logo, L.x + 5, L.y + 5, L.s - 10, L.s - 10);
        ctx.restore();
    } else {
        rectRedondeado(ctx, L.x, L.y, L.s, L.s, L.r);
        ctx.fillStyle = NARANJO;
        ctx.fill();
        ctx.fillStyle = '#ffffff';
        ctx.font = fuente(800, 27, TITULO);
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(datos.organizacion.iniciales || '·', L.x + L.s / 2, L.y + L.s / 2 + 1);
        ctx.textBaseline = 'alphabetic';
    }

    // El nombre, en una o dos líneas, centrado respecto al cuadrado.
    ctx.fillStyle = TINTA;
    const n = ajustar(ctx, datos.organizacion.nombre, 218, 2, 23, 18, 600, TEXTO);
    const altoNombre = Math.round(n.tam * 1.25);
    const yNombre = 1096 - ((n.lineas.length - 1) * altoNombre) / 2 + n.tam * 0.35;
    escribir(ctx, n.lineas, 218, yNombre, altoNombre);

    // La divisoria entre «Organiza» y «Más información»: no venía como pieza.
    ctx.strokeStyle = '#efd7c0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(455, 1064);
    ctx.lineTo(455, 1128);
    ctx.stroke();

    ctx.fillStyle = TINTA;
    ctx.textAlign = 'left';
    ctx.font = fuente(700, 20, TEXTO);
    ctx.fillText('Más información en:', 572, 1080);
    const w = ajustar(ctx, datos.web, 370, 1, 25, 18, 400, TEXTO);
    ctx.fillText(w.lineas[0], 572, 1112);
}

/** El pie blanco sobre la franja naranja de abajo. */
function pie(ctx, datos) {
    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'left';
    // Hasta x=740, donde empieza el corazón.
    ctx.font = fuente(500, 24, TITULO);
    ctx.fillText('Súmate a la celebración nacional del', 222, 1233);
    let a = ajustar(ctx, `Día del Patrimonio Social - ${datos.fechas}`, 518, 1, 24, 18, 800, TITULO);
    ctx.fillText(a.lineas[0], 222, 1263);
    a = ajustar(ctx, datos.hashtag, 518, 1, 31, 20, 500, TITULO);
    ctx.fillText(a.lineas[0], 222, 1318);
}

/**
 * Dibuja la imagen entera en `canvas`.
 *
 * Una foto o un logo que no carguen no rompen la imagen: sin foto queda el
 * banner de la edición (lo que ya enseña la ficha) y sin logo, las iniciales.
 */
export async function dibujarDifusion(canvas, datos, rutaCapas, fotoPorDefecto) {
    canvas.width = ANCHO;
    canvas.height = ALTO;
    const ctx = canvas.getContext('2d');

    // Lo escrito, con dónde y cuánto ocupa: lo mira `pruebas/difusion.mjs`
    // para comprobar que nada se sale de su sitio.
    const textos = [];
    const escribirOriginal = ctx.fillText.bind(ctx);
    ctx.fillText = (t, x, y) => {
        const ancho = ctx.measureText(t).width;
        const izq = ctx.textAlign === 'center' ? x - ancho / 2 : x;
        textos.push({ t: String(t), x: Math.round(izq), y: Math.round(y), ancho: Math.round(ancho), fuente: ctx.font });
        escribirOriginal(t, x, y);
    };

    // Las fuentes del sitio tienen que estar cargadas antes de medir texto.
    await Promise.all([
        document.fonts.load(fuente(800, 54, TITULO)),
        document.fonts.load(fuente(500, 25, TITULO)),
        document.fonts.load(fuente(400, 30, TEXTO)),
        document.fonts.load(fuente(600, 23, TEXTO)),
        document.fonts.load(fuente(700, 20, TEXTO)),
    ]);

    const nombres = Object.keys(CAPAS);
    const capas = Object.fromEntries(await Promise.all(
        nombres.map(async (n) => [n, await cargarImagen(`${rutaCapas}/${n}.png`)]),
    ));
    const foto = await cargarImagen(datos.foto).catch(() => cargarImagen(fotoPorDefecto).catch(() => null));
    const logo = datos.organizacion.logo ? await cargarImagen(datos.organizacion.logo).catch(() => null) : null;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, ANCHO, ALTO);

    if (foto) {
        ctx.save();
        rectRedondeado(ctx, FOTO.x, FOTO.y, FOTO.w, FOTO.h, FOTO.radio);
        ctx.clip();
        llenar(ctx, foto, FOTO.x, FOTO.y, FOTO.w, FOTO.h);
        ctx.restore();
    }

    for (const n of ['fondo', 'caja', 'icono-cuando', 'icono-donde', 'icono-cupos', 'icono-formato', 'icono-web', 'corazon', 'logo-dps', 'logo-comunidad']) {
        const c = CAPAS[n];
        ctx.drawImage(capas[n], c.x, c.y, c.w, c.h);
    }

    ctx.fillStyle = '#ffffff';
    ctx.font = fuente(800, 44, TITULO);
    ctx.textAlign = 'left';
    ctx.fillText('¡Súmate a esta actividad!', 80, 76);

    textosPrincipales(ctx, datos);
    datosDeLaCaja(ctx, datos);
    pieDeLaCaja(ctx, datos, logo);
    pie(ctx, datos);

    return { foto: !! foto, logo: !! logo, textos };
}

/** El componente de Alpine de la pantalla: vista previa, descargar y compartir. */
export function difusion(datos, rutaCapas, fotoPorDefecto) {
    return {
        estado: 'dibujando',
        url: '',
        archivo: null,
        puedeCompartir: false,
        dibujo: null,

        async init() {
            try {
                const canvas = document.createElement('canvas');
                this.dibujo = await dibujarDifusion(canvas, datos, rutaCapas, fotoPorDefecto);
                const blob = await new Promise((ok) => canvas.toBlob(ok, 'image/png'));
                this.archivo = new File([blob], datos.archivo, { type: 'image/png' });
                this.url = URL.createObjectURL(blob);
                // En el teléfono, «Compartir» la manda directo a Instagram o WhatsApp.
                this.puedeCompartir = !! navigator.canShare?.({ files: [this.archivo] });
                this.estado = 'lista';
            } catch (e) {
                console.error(e);
                this.estado = 'error';
            }
        },

        async compartir() {
            try {
                await navigator.share({ files: [this.archivo], title: datos.titulo });
            } catch {
                // Cancelar el diálogo de compartir no es un error que haya que contar.
            }
        },
    };
}
