import { guiaDeErrores } from './formularios';

/*
 * La encuesta de evaluación que se responde tras escanear el QR.
 *
 * Hace cuatro cosas, y tres de ellas son correcciones a fallos que en este
 * proyecto ya han costado tiempo:
 *
 * 1. La guía de errores del bloque K, entera. Es un formulario público que
 *    rellena gente sin cuenta, desde el móvil, de pie y con prisa: si el aviso
 *    de lo que falta no se ve, no existe.
 * 2. El contador de caracteres de la respuesta abierta.
 * 3. La casilla de autorización, que aparece **sólo** cuando hay fotografía.
 * 4. **Reducir la fotografía antes de subirla**, que es lo que evita el fallo
 *    más feo de todos. Ver `reducir()`.
 *
 * REGLA DE ESTE ARCHIVO, la misma que la de formularios.js y panel.js: en
 * Alpine `$el` es el elemento del manejador que se está ejecutando, NO la raíz
 * del componente. El ámbito se guarda en `init()`, que es el único sitio donde
 * `$el` sí lo es.
 */

/** Lado largo máximo de la foto que se sube. */
const LADO_MAXIMO = 1600;

/** Calidad del JPEG de salida. Por encima de .85 el archivo crece y no se nota. */
const CALIDAD = 0.85;

/** Lo que promete el formulario, en bytes. */
const PESO_MAXIMO = 5 * 1024 * 1024;

const TIPOS = ['image/jpeg', 'image/png'];

export const encuestaEvaluacion = (errores = [], maximoTexto = 300) => ({
    ...guiaDeErrores(errores),

    /* ── Foto ── */
    nombreFoto: '',
    previa: '',
    reduciendo: false,
    errorFoto: '',

    /* ── Texto ── */
    escritos: 0,
    maximoTexto,

    init() {
        this.iniciarGuia(this.$el);

        // El navegador conserva lo escrito al volver atrás, así que el contador
        // tiene que nacer con la cuenta real y no en cero.
        const area = this.$el.querySelector('[data-contador]');
        if (area) this.escritos = area.value.length;
    },

    get tieneFoto() {
        return this.nombreFoto !== '';
    },

    get restantes() {
        return Math.max(0, this.maximoTexto - this.escritos);
    },

    contar(evento) {
        this.escritos = evento.target.value.length;
    },

    /* ─────────────────────────────────────────────── la foto ── */

    async elegirFoto(evento) {
        const entrada = evento.target;
        const archivo = entrada.files && entrada.files[0];

        this.errorFoto = '';

        if (!archivo) {
            this.quitarFoto(entrada);
            return;
        }

        if (!TIPOS.includes(archivo.type)) {
            this.errorFoto = 'La fotografía tiene que ser un archivo JPG o PNG.';
            this.quitarFoto(entrada);
            return;
        }

        this.reduciendo = true;

        try {
            const reducida = await this.reducir(archivo);

            if (reducida && this.reemplazar(entrada, reducida)) {
                this.mostrar(reducida);
            } else {
                // No se pudo reducir ni reemplazar: se sube el original, que es
                // lo que había antes de todo esto. Sólo hay que avisar si además
                // se pasa del límite, porque entonces el servidor lo va a
                // rechazar y más vale decirlo ahora que después del envío.
                if (archivo.size > PESO_MAXIMO) {
                    this.errorFoto = 'La fotografía pesa más de 5 MB y este navegador no puede reducirla. '
                        + 'Prueba con una foto más pequeña.';
                    this.quitarFoto(entrada);
                    return;
                }

                this.mostrar(archivo);
            }
        } finally {
            this.reduciendo = false;
        }
    },

    /**
     * Reduce la foto en el navegador antes de subirla.
     *
     * **Esto no es una optimización, es lo que evita un fallo mudo.** Cuando lo
     * que se envía pasa de `post_max_size`, PHP entrega un `$_POST` VACÍO: sin
     * token CSRF, así que Laravel responde 419 y la persona pierde el
     * formulario entero sin un solo mensaje que explique nada. Una foto de
     * teléfono son cuatro u ocho megas; a 1600 px de lado largo se queda en
     * unos cuatrocientos kilobytes, sube mucho más rápido con datos móviles y
     * el límite del servidor deja de estar en juego.
     *
     * **Se conserva el tipo del archivo.** Pasar un PNG a JPEG lo comprime
     * mucho más, pero un PNG con transparencia sale con el fondo negro, que es
     * peor que subir un archivo grande. El JPEG se recomprime como JPEG y el
     * PNG sigue siendo PNG.
     *
     * Si el navegador no puede —falta `createImageBitmap`, falta `toBlob`, la
     * imagen no se deja decodificar— devuelve null y se sube el original.
     */
    async reducir(archivo) {
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

        const escala = Math.min(1, LADO_MAXIMO / Math.max(bitmap.width, bitmap.height));

        // Ya cabe de sobra: recomprimirla sólo le quitaría calidad.
        if (escala === 1 && archivo.size <= PESO_MAXIMO) {
            bitmap.close?.();
            return null;
        }

        const lienzo = document.createElement('canvas');
        lienzo.width = Math.round(bitmap.width * escala);
        lienzo.height = Math.round(bitmap.height * escala);

        const pincel = lienzo.getContext('2d');

        if (!pincel) {
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

        if (!blob) return null;

        /*
         * ── Cuándo NO nos quedamos con la reducida ──
         *
         * Sólo cuando no había nada que reducir de tamaño (`escala === 1`) y la
         * recompresión ha salido más pesada. Ahí el original es mejor en las
         * dos cosas y no hay nada que ganar.
         *
         * **Cuando sí se ha reducido el tamaño, la reducida se queda aunque
         * pese más.** Suena raro y no lo es: el tope de 1600 px es un
         * requisito, no una optimización. Una imagen muy comprimible —un
         * degradado, una captura de pantalla plana— puede ocupar más al
         * reencodearla, porque el lienzo le mete el suavizado del redimensionado
         * y eso es ruido que el PNG ya no comprime igual. Preferir el original
         * por unos kilobytes deja guardada una imagen de 3000 px que va a
         * ralentizar cada miniatura del panel.
         *
         * Esto lo pilló `pruebas/encuesta-evaluacion.mjs`: la primera versión
         * comparaba los pesos siempre, y una foto de 3000x2000 se guardaba
         * intacta porque su versión de 1600 px pesaba 42 KB frente a 27 KB.
         */
        if (escala === 1 && blob.size >= archivo.size) {
            return null;
        }

        return new File([blob], archivo.name, { type: archivo.type, lastModified: Date.now() });
    },

    /**
     * Mete el archivo reducido en el input, que es lo que se acaba enviando.
     *
     * `input.files` sólo admite una `FileList`, y la única forma de fabricar
     * una es con `DataTransfer`. Donde no exista, se devuelve false y se sube
     * el original.
     */
    reemplazar(entrada, archivo) {
        if (typeof DataTransfer !== 'function') return false;

        try {
            const paquete = new DataTransfer();
            paquete.items.add(archivo);
            entrada.files = paquete.files;

            return true;
        } catch {
            return false;
        }
    },

    mostrar(archivo) {
        this.nombreFoto = archivo.name;

        if (this.previa) URL.revokeObjectURL(this.previa);

        this.previa = URL.createObjectURL(archivo);

        // La caja de la foto puede estar marcada como fallida de un rebote
        // anterior; con foto puesta, ya no lo está.
        this.revisarCampo('foto');
    },

    quitarFoto(entrada) {
        const campo = entrada || this.$el.querySelector('input[type="file"][name="foto"]');

        if (campo) campo.value = '';

        if (this.previa) URL.revokeObjectURL(this.previa);

        this.previa = '';
        this.nombreFoto = '';

        /*
         * Y se desmarca la autorización.
         *
         * Sin esto queda una autorización sobre una foto que ya no está: la
         * casilla se oculta pero sigue marcada, y al enviar viajaría un `true`
         * sin archivo. El servidor lo vuelve a comprobar —ahí está la garantía
         * de verdad— pero dejarlo marcado aquí es prometer algo que no es.
         */
        const casilla = this.$el.querySelector('input[name="foto_autorizada"]');
        if (casilla) casilla.checked = false;
    },
});
