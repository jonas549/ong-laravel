/**
 * Compartir una imagen generada en el navegador: la de difusión de una
 * actividad y la de «Soy parte del DPS» de la inscripción.
 *
 * Punto 4 del 30/09: el botón «Compartir» se quedaba muerto. Se pintaba
 * siempre que `navigator.canShare({ files })` dijera que sí, y eso también lo
 * dice Chrome en Windows, donde el diálogo del sistema puede no abrirse o
 * fallar; el error se tragaba en silencio y no pasaba nada al pulsar. Ahora
 * hay tres salidas, y el botón siempre hace una de ellas:
 *
 *   - **En el teléfono**, el diálogo nativo: de ahí va directo a Instagram,
 *     WhatsApp o donde sea. Es para lo que existe.
 *   - **En el escritorio**, copiar la imagen al portapapeles para pegarla en
 *     la publicación (Ctrl+V en Facebook, LinkedIn o WhatsApp Web).
 *   - **Si ninguna de las dos se puede**, o la nativa falla por algo que no
 *     sea cancelar, se descarga. Nunca un clic sin respuesta.
 *
 * «Teléfono» es puntero grueso (dedo) y además `canShare` con archivos: un
 * portátil con pantalla táctil y ratón tiene puntero fino y va por copiar.
 */

/** Qué hará el botón aquí: 'compartir', 'copiar' o 'descargar'. */
export function modoDeCompartir(archivo) {
    const dedo = window.matchMedia?.('(pointer: coarse)').matches;

    if (dedo && archivo && navigator.canShare?.({ files: [archivo] })) return 'compartir';
    if (navigator.clipboard?.write && window.ClipboardItem && window.isSecureContext) return 'copiar';

    return 'descargar';
}

/** Descarga un archivo que ya está en memoria. */
export function descargar(archivo) {
    const url = URL.createObjectURL(archivo);
    const a = document.createElement('a');
    a.href = url;
    a.download = archivo.name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10000);
}

/**
 * El portapapeles de los navegadores sólo admite PNG: un JPEG —la imagen de
 * «Soy parte del DPS»— lo rechaza. Se redibuja en PNG antes de copiarlo.
 */
async function comoPng(archivo) {
    if (archivo.type === 'image/png') return archivo;

    const bitmap = await createImageBitmap(archivo);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0);

    return new Promise((ok, mal) => canvas.toBlob((b) => (b ? ok(b) : mal(new Error('sin PNG'))), 'image/png'));
}

async function copiarImagen(archivo) {
    // Se le pasa la promesa y no el blob: Safari exige que `write` se llame
    // dentro del clic, y convertir antes de llamarlo lo dejaría fuera.
    await navigator.clipboard.write([new ClipboardItem({ 'image/png': comoPng(archivo) })]);
}

/**
 * Hace lo que toque y dice qué pasó, para que la pantalla lo cuente:
 * 'compartida', 'cancelada', 'copiada' o 'descargada'.
 *
 * `texto` acompaña a la imagen donde el destino lo admite (WhatsApp sí,
 * Instagram lo ignora). En el escritorio no viaja con la imagen: el
 * portapapeles guarda una cosa, y la pantalla ofrece copiar el texto aparte.
 */
export async function compartirImagen(archivo, { texto = '', titulo = '' } = {}) {
    const modo = modoDeCompartir(archivo);

    if (modo === 'compartir') {
        try {
            await navigator.share({ files: [archivo], ...(texto ? { text: texto } : {}), ...(titulo ? { title: titulo } : {}) });
            return 'compartida';
        } catch (e) {
            // Cerrar el diálogo sin elegir nada no es un fallo.
            if (e?.name === 'AbortError') return 'cancelada';
            // Cualquier otra cosa: que al menos se lleve la imagen.
        }
    }

    if (modo === 'copiar') {
        try {
            await copiarImagen(archivo);
            return 'copiada';
        } catch {
            // Sin permiso o sin foco: se descarga.
        }
    }

    descargar(archivo);

    return 'descargada';
}

/** El aviso que sigue a cada resultado. */
export const AVISOS = {
    compartida: '',
    cancelada: '',
    copiada: 'Imagen copiada. Pégala (Ctrl+V) en tu publicación o en el chat.',
    descargada: 'La imagen se descargó: súbela desde tu equipo a la red social.',
};
