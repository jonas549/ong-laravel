/*
 * Reducir una imagen en el navegador, antes de subirla.
 *
 * Vivía dentro de `evaluacion.js`, para las fotos de la encuesta. Se sacó aquí
 * al pedirlo también para la portada de la actividad y el logo de la
 * organización (P19), que es el mismo problema con otros límites.
 *
 * **Esto no es una optimización, es lo que evita un fallo mudo.** Cuando lo
 * que se envía pasa de `post_max_size`, PHP entrega un `$_POST` VACÍO: sin
 * token CSRF, así que Laravel responde 419 y la persona pierde el formulario
 * entero sin un solo mensaje que explique nada.
 *
 * La decisión de P19, entre las dos que pedían el ticket y el Word: **se
 * reduce automáticamente**, y sólo si el navegador no puede se avisa y se
 * corta el envío. Rechazar una foto de teléfono por pesar cuatro megas es
 * mandar a alguien a buscar un editor de imágenes para poder publicar.
 */

/** Calidad del JPEG de salida. Por encima de .85 el archivo crece y no se nota. */
const CALIDAD = 0.85;

const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Reduce un archivo de imagen a `ladoMaximo` píxeles de lado largo.
 *
 * **Se conserva el tipo del archivo.** Pasar un PNG a JPEG lo comprime mucho
 * más, pero un PNG con transparencia sale con el fondo negro, que es peor que
 * subir un archivo grande. El JPEG se recomprime como JPEG y el PNG sigue
 * siendo PNG.
 *
 * Devuelve `null` cuando no hay nada que hacer o cuando el navegador no puede
 * —falta `createImageBitmap`, falta `toBlob`, la imagen no se deja decodificar—,
 * y entonces quien llama decide qué hacer con el original.
 *
 * @param {File} archivo
 * @param {number} ladoMaximo  píxeles del lado largo
 * @param {number} pesoMaximo  bytes que se admiten sin tocar nada
 * @returns {Promise<File|null>}
 */
export async function reducirImagen(archivo, ladoMaximo, pesoMaximo) {
    if (typeof createImageBitmap !== 'function' || typeof document === 'undefined') {
        return null;
    }

    let bitmap;

    try {
        /*
         * `from-image` aplica la orientación EXIF al decodificar. Sin esto,
         * una foto tomada en vertical con el teléfono se sube tumbada: el
         * navegador la enseña bien —respeta el EXIF al pintarla— pero el
         * lienzo dibuja los píxeles crudos, y la orientación se pierde al
         * volver a codificar.
         */
        bitmap = await createImageBitmap(archivo, { imageOrientation: 'from-image' });
    } catch {
        return null;
    }

    const escala = Math.min(1, ladoMaximo / Math.max(bitmap.width, bitmap.height));

    // Ya cabe de sobra: recomprimirla sólo le quitaría calidad.
    if (escala === 1 && archivo.size <= pesoMaximo) {
        bitmap.close?.();

        return null;
    }

    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(bitmap.width * escala);
    lienzo.height = Math.round(bitmap.height * escala);

    const pincel = lienzo.getContext('2d');

    if (! pincel) {
        bitmap.close?.();

        return null;
    }

    pincel.drawImage(bitmap, 0, 0, lienzo.width, lienzo.height);
    bitmap.close?.();

    const blob = await new Promise((listo) => {
        try {
            lienzo.toBlob(listo, archivo.type, CALIDAD);
        } catch {
            listo(null);
        }
    });

    if (! blob) return null;

    /*
     * ── Cuándo NO nos quedamos con la reducida ──
     *
     * Sólo cuando no había nada que reducir de tamaño (`escala === 1`) y la
     * recompresión ha salido más pesada. Ahí el original es mejor en las dos
     * cosas y no hay nada que ganar.
     *
     * **Cuando sí se ha reducido el tamaño, la reducida se queda aunque pese
     * más.** Suena raro y no lo es: el tope de píxeles es un requisito, no una
     * optimización. Una imagen muy comprimible —un degradado, una captura
     * plana— puede ocupar más al reencodearla, porque el lienzo le mete el
     * suavizado del redimensionado y eso es ruido que el PNG ya no comprime
     * igual. Preferir el original por unos kilobytes deja guardada una imagen
     * de 3000 px que va a ralentizar cada miniatura del panel.
     *
     * Lo pilló `pruebas/encuesta-evaluacion.mjs`: la primera versión comparaba
     * los pesos siempre, y una foto de 3000x2000 se guardaba intacta porque su
     * versión de 1600 px pesaba 42 KB frente a 27 KB.
     */
    if (escala === 1 && blob.size >= archivo.size) {
        return null;
    }

    return new File([blob], archivo.name, { type: archivo.type, lastModified: Date.now() });
}

/**
 * Mete un archivo dentro de un `<input type="file">`.
 *
 * `input.files` sólo admite una `FileList`, y la única forma de fabricar una
 * es con `DataTransfer`. Donde no exista se devuelve false y quien llama se
 * queda con el original sin reducir.
 */
export function meterEnElCampo(entrada, archivo) {
    if (typeof DataTransfer !== 'function') return false;

    try {
        const paquete = new DataTransfer();
        paquete.items.add(archivo);
        entrada.files = paquete.files;

        return true;
    } catch {
        return false;
    }
}

/** «1,4 MB», «480 KB». Para decirle a alguien cuánto pesa lo que eligió. */
export function pesoLegible(bytes) {
    if (bytes >= 1024 * 1024) {
        return (bytes / (1024 * 1024)).toFixed(1).replace('.', ',') + ' MB';
    }

    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

/**
 * El campo de imagen del wizard y del editor: elegir, reducir y avisar.
 *
 * P19. Antes el aviso de peso llegaba DESPUÉS de enviar, del servidor, con el
 * formulario entero rebotado. Ahora pasan tres cosas al elegir el archivo:
 *
 *   1. Si no es una imagen, se dice y no se acepta.
 *   2. Si pasa del límite, se reduce en el navegador. Casi siempre entra:
 *      una foto de teléfono de cuatro megas baja a unos cientos de kilobytes.
 *   3. Si aun así no entra —o el navegador no sabe reducir— se avisa con el
 *      peso de verdad y **se corta el envío**, marcando la caja para que la
 *      guía de errores la enseñe arriba con las demás.
 *
 * @param {object} opciones
 * @param {number} opciones.maxKb      lo que admite el servidor, en KB
 * @param {number} opciones.ladoMaximo píxeles del lado largo
 * @param {string} opciones.que        cómo llamarlo en los avisos
 */
export const campoImagen = ({ maxKb, ladoMaximo = 1600, que = 'La imagen' }) => ({
    maxBytes: maxKb * 1024,
    ladoMaximo,
    que,

    nombre: '',
    previa: '',
    peso: '',
    error: '',
    reduciendo: false,
    raiz: null,

    init() {
        // La raíz, aquí y sólo aquí: dentro del manejador de un botón `$el` es
        // ese botón. Es la trampa de Alpine que ya costó cuatro fallos.
        this.raiz = this.$el;
    },

    get tiene() {
        return this.nombre !== '';
    },

    async elegir(evento) {
        const entrada = evento.target;
        const archivo = entrada.files && entrada.files[0];

        this.limpiarAviso();

        if (! archivo) {
            this.quitar(entrada);

            return;
        }

        if (! TIPOS.includes(archivo.type)) {
            /*
             * Primero se vacía el campo y DESPUÉS se avisa. Al revés no
             * funciona: `quitar()` limpia el aviso —tiene que hacerlo, es lo
             * que borra el error al elegir otra imagen— así que avisar antes
             * dejaba el campo vacío y sin decir por qué.
             */
            this.quitar(entrada);
            this.avisar(`${this.que} tiene que ser un archivo JPG, PNG o WebP.`);

            return;
        }

        this.reduciendo = true;

        let definitivo = archivo;

        try {
            const reducida = await reducirImagen(archivo, this.ladoMaximo, this.maxBytes);

            if (reducida && meterEnElCampo(entrada, reducida)) {
                definitivo = reducida;
            }
        } finally {
            this.reduciendo = false;
        }

        this.nombre = definitivo.name;
        this.peso = pesoLegible(definitivo.size);

        if (this.previa) URL.revokeObjectURL(this.previa);
        this.previa = URL.createObjectURL(definitivo);

        /*
         * Y si después de reducirla sigue sin entrar, se dice AHORA y se corta
         * el envío. Es el caso raro —un PNG enorme sin nada que comprimir, un
         * navegador sin `createImageBitmap`— pero es justo el que antes
         * acababa en un rebote del servidor con el formulario perdido.
         */
        if (definitivo.size > this.maxBytes) {
            this.avisar(
                `${this.que} pesa ${pesoLegible(definitivo.size)} y el máximo son ${pesoLegible(this.maxBytes)}. `
                + 'Prueba con una más pequeña.'
            );
        }
    },

    quitar(entrada) {
        const campo = entrada || this.raiz?.querySelector('input[type="file"]');

        if (campo) campo.value = '';

        if (this.previa) URL.revokeObjectURL(this.previa);

        this.previa = '';
        this.nombre = '';
        this.peso = '';
        this.limpiarAviso();
    },

    /**
     * El aviso, en dos sitios: junto al campo y en el resumen de arriba.
     *
     * Lo segundo es lo que corta el envío. La guía lee `data-error-propio` de
     * la caja, así que basta con ponerlo ahí; un aviso que sólo esté junto al
     * campo se queda fuera de pantalla al volver del POST, que es la lección
     * del bloque K.
     */
    avisar(mensaje) {
        this.error = mensaje;

        const caja = this.raiz?.closest('[data-campo]') ?? this.raiz?.querySelector('[data-campo]');

        if (caja) caja.dataset.errorPropio = mensaje;
    },

    limpiarAviso() {
        this.error = '';

        const caja = this.raiz?.closest('[data-campo]') ?? this.raiz?.querySelector('[data-campo]');

        if (caja) delete caja.dataset.errorPropio;
    },
});
