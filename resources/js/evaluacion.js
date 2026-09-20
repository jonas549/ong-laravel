import { guiaDeErrores } from './formularios';
import { reducirImagen } from './imagenes';

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

export const encuestaEvaluacion = (errores = [], maximoTexto = 300, maximoFotos = 1) => ({
    ...guiaDeErrores(errores),

    /*
     * ── Fotos ──
     *
     * Desde el 2026-09-20 son varias y no una, con el tope puesto por la ONG
     * en Configuración → General. Cada entrada es
     * `{ archivo, previa, nombre }`: el archivo YA reducido, que es lo que se
     * acaba enviando, y su URL de objeto para la miniatura.
     */
    fotos: [],
    maximoFotos,
    reduciendo: false,
    errorFoto: '',

    /* ── Texto ── */
    escritos: 0,
    maximoTexto,

    /*
     * La raíz del componente.
     *
     * NO se lee de `$el` fuera de `init()`: dentro de un manejador puesto en
     * un botón —«Quitar todas», la × de una miniatura— `$el` ES ese botón, y
     * `$el.querySelector('input[type=file]')` no encuentra nada. Eso dejaba el
     * input con el archivo viejo dentro: la miniatura desaparecía, el usuario
     * creía haberla quitado, y volvía a enviarse igual. Lo pilló
     * `encuesta-evaluacion.mjs` al reintentar la subida del mismo archivo y no
     * ver la segunda miniatura, porque el navegador no emite `change` cuando
     * el input ya tiene ese archivo.
     *
     * Es la misma trampa que ya se comió el autoguardado del editor del home,
     * el reordenar y el buscador del panel.
     */
    raiz: null,

    init() {
        this.raiz = this.$el;
        this.iniciarGuia(this.$el);

        // El navegador conserva lo escrito al volver atrás, así que el contador
        // tiene que nacer con la cuenta real y no en cero.
        const area = this.$el.querySelector('[data-contador]');
        if (area) this.escritos = area.value.length;
    },

    get tieneFoto() {
        return this.fotos.length > 0;
    },

    /** Cuántas más caben. Cero deja el botón de elegir fuera. */
    get huecos() {
        return Math.max(0, this.maximoFotos - this.fotos.length);
    },

    get textoBotonFoto() {
        if (this.maximoFotos === 1) return this.tieneFoto ? 'Cambiar imagen' : 'Elegir imagen';

        return this.tieneFoto ? 'Agregar otra' : 'Elegir imágenes';
    },

    get restantes() {
        return Math.max(0, this.maximoTexto - this.escritos);
    },

    contar(evento) {
        this.escritos = evento.target.value.length;
    },

    /* ─────────────────────────────────────────────── la foto ── */

    async elegirFotos(evento) {
        const entrada = evento.target;
        const elegidas = [...(entrada.files || [])];

        this.errorFoto = '';

        if (elegidas.length === 0) {
            // Cancelar el diálogo no puede borrar lo que ya estaba elegido:
            // el input se vacía solo y hay que devolverle su contenido.
            this.sincronizar();
            return;
        }

        /*
         * Con tope 1 el selector sustituye en vez de acumular. Es lo que
         * espera cualquiera de un campo que sólo admite una: «Cambiar imagen»
         * no puede dejar la anterior puesta y quejarse de que ya no caben.
         */
        if (this.maximoFotos === 1) this.vaciar();

        let sobran = 0;

        for (const archivo of elegidas) {
            if (!TIPOS.includes(archivo.type)) {
                this.errorFoto = 'Las fotografías tienen que ser archivos JPG o PNG.';
                continue;
            }

            if (this.fotos.length >= this.maximoFotos) { sobran++; continue; }

            this.reduciendo = true;

            try {
                const reducida = await this.reducir(archivo);
                const definitiva = reducida || archivo;

                /*
                 * Sin reducir y por encima del límite: el servidor la va a
                 * rechazar, así que se dice ahora y no después del envío.
                 */
                if (!reducida && archivo.size > PESO_MAXIMO) {
                    this.errorFoto = `«${archivo.name}» pesa más de 5 MB y este navegador no puede reducirla. `
                        + 'Prueba con una foto más pequeña.';
                    continue;
                }

                this.fotos.push({
                    archivo: definitiva,
                    nombre: definitiva.name,
                    previa: URL.createObjectURL(definitiva),
                });
            } finally {
                this.reduciendo = false;
            }
        }

        if (sobran > 0) {
            this.errorFoto = this.maximoFotos === 1
                ? 'Sólo se puede subir una fotografía.'
                : `Sólo se pueden subir ${this.maximoFotos} fotografías, así que se dejaron fuera `
                    + `${sobran === 1 ? 'la última' : 'las ' + sobran + ' últimas'}.`;
        }

        this.sincronizar();

        // La caja puede estar marcada como fallida de un rebote anterior; con
        // foto puesta, ya no lo está.
        if (this.tieneFoto) this.revisarCampo('fotos');
    },

    /**
     * Reduce la foto en el navegador antes de subirla.
     *
     * El cómo vive en `imagenes.js`, compartido con la portada de la actividad
     * y el logo de la organización (P19). Estaba escrito aquí y se sacó al
     * necesitarlo los tres: tres copias del mismo redimensionado son tres
     * sitios donde arreglar el mismo fallo.
     */
    reducir(archivo) {
        return reducirImagen(archivo, LADO_MAXIMO, PESO_MAXIMO);
    },

    /**
     * Vuelca la lista de fotos elegidas dentro del input, que es lo que se
     * acaba enviando.
     *
     * `input.files` sólo admite una `FileList`, y la única forma de fabricar
     * una es con `DataTransfer`. Donde no exista —navegadores viejos— se deja
     * lo que el usuario eligió tal cual: se subirán los originales sin reducir
     * y sin poder quitar ninguno, que es peor pero sigue funcionando.
     *
     * Se llama después de CADA cambio —elegir, quitar, vaciar—, porque el
     * input y la lista son dos cosas distintas y lo que viaja es el input.
     */
    sincronizar() {
        const entrada = this.campoFotos();

        if (!entrada || typeof DataTransfer !== 'function') return false;

        try {
            const paquete = new DataTransfer();
            for (const f of this.fotos) paquete.items.add(f.archivo);
            entrada.files = paquete.files;

            return true;
        } catch {
            return false;
        }
    },

    campoFotos() {
        return this.raiz?.querySelector('input[type="file"][name="fotos[]"]');
    },

    quitarFoto(indice) {
        const fuera = this.fotos[indice];

        if (fuera?.previa) URL.revokeObjectURL(fuera.previa);

        this.fotos.splice(indice, 1);
        this.errorFoto = '';
        this.sincronizar();

        if (!this.tieneFoto) this.desmarcarAutorizacion();
    },

    vaciar() {
        for (const f of this.fotos) {
            if (f.previa) URL.revokeObjectURL(f.previa);
        }

        this.fotos = [];
        this.sincronizar();
        this.desmarcarAutorizacion();
    },

    /*
     * Sin fotos no puede quedar una autorización en pie.
     *
     * La casilla se oculta pero sigue marcada, y al enviar viajaría un `true`
     * sin archivo. El servidor lo vuelve a comprobar —ahí está la garantía de
     * verdad— pero dejarlo marcado aquí es prometer algo que no es.
     */
    desmarcarAutorizacion() {
        const casilla = this.raiz?.querySelector('input[name="foto_autorizada"]');
        if (casilla) casilla.checked = false;
    },
});
