<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Limpia el HTML que escribe la ONG en el editor del home.
 *
 * **No está escrito a mano.** Se apoya en el sanitizador de Symfony, que
 * implementa la especificación de HTML Sanitization del W3C y está mantenido y
 * atacado por mucha más gente de la que va a leer este archivo. Un filtro
 * casero a base de expresiones regulares es la forma clásica de dejar pasar un
 * XSS creyendo lo contrario, y en este proyecto ya apareció uno en la vista
 * previa de plantillas: aquel iframe sin `sandbox` con `document.write` dentro.
 *
 * El criterio es de lista blanca: **lo que no está permitido, no pasa**. Nada de
 * ir tapando lo que se va encontrando.
 *
 * Qué queda fuera y por qué:
 *
 * - `script`, `style`, `iframe`, `object`, `embed`, `form`, `input`: ejecutan
 *   código o traen contenido de fuera. El único video del sitio es el de
 *   YouTube, y ese lo pinta la plantilla a partir de un identificador que se
 *   valida aparte, no de un iframe que alguien pegue aquí.
 * - Todo atributo `on*` y todo `style`: un `onerror` es un XSS y un `style`
 *   suelto es la forma más fácil de romper el diseño sin darse cuenta
 *   (`position:fixed`, un `font-size:200px`), que es la regla 2 del bloque.
 * - `href` que no sea http, https, mailto o una ruta del propio sitio: cierra
 *   `javascript:` y `data:`.
 * - Las etiquetas de bloque que el diseño no contempla (`h1`, `h2`, `table`,
 *   `div`): cada sección ya tiene su titular con su tipografía exacta, y un
 *   `h1` dentro del cuerpo competiría con él.
 */
class SanitizadorHtml
{
    /** Lo que el editor ofrece y la plantilla sabe pintar. */
    private const PERMITIDAS = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'h3' => [],
        'h4' => [],
        'blockquote' => [],
        'a' => ['href', 'title'],
    ];

    private ?HtmlSanitizer $sanitizador = null;

    public function limpiar(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $limpio = trim($this->sanitizador()->sanitize($html));

        /*
         * Un editor `contenteditable` vacío no deja la cadena vacía: deja
         * `<p><br></p>` o un espacio duro. Sin esto, un campo que la persona
         * borró entero contaba como «tiene contenido» y no caía al valor por
         * defecto del catálogo, que es lo que pide la regla 5.
         */
        return $this->soloHuecos($limpio) ? '' : $limpio;
    }

    /**
     * Texto plano de un HTML, para resúmenes y para la lista de secciones.
     *
     * Mete un espacio entre bloques antes de quitar las etiquetas: sin eso,
     * `<p>uno</p><p>dos</p>` se leía «unodos».
     */
    public function texto(?string $html, int $limite = 0): string
    {
        $plano = trim(html_entity_decode(strip_tags(preg_replace('/<\/(p|li|h3|h4|blockquote)>/i', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plano = preg_replace('/\s+/u', ' ', $plano);

        return $limite > 0 ? mb_strimwidth($plano, 0, $limite, '…') : $plano;
    }

    /**
     * El identificador de un video de YouTube, venga como venga.
     *
     * Se guarda el identificador y **nunca la URL ni un iframe**: así la
     * plantilla construye el `embed` ella misma y no hay forma de colar otra
     * cosa por ese campo. Devuelve null si no reconoce nada, que es lo mismo
     * que decir «no pongas video».
     */
    public function idDeYoutube(?string $entrada): ?string
    {
        $entrada = trim((string) $entrada);

        if ($entrada === '') {
            return null;
        }

        // Un identificador tal cual: once caracteres del alfabeto de YouTube.
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $entrada)) {
            return $entrada;
        }

        // youtu.be/ID, /watch?v=ID, /embed/ID, /shorts/ID, /live/ID
        $patrones = [
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~[?&]v=([A-Za-z0-9_-]{11})~',
            '~youtube\.com/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~',
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $entrada, $coincide)) {
                return $coincide[1];
            }
        }

        return null;
    }

    /**
     * Una ruta interna o un enlace externo con esquema conocido.
     *
     * Se usa para los botones, que no pasan por el sanitizador de HTML porque
     * son un campo suelto y no un cuerpo de texto.
     */
    public function enlace(?string $valor): string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return '';
        }

        // Anclas y rutas del propio sitio.
        if (str_starts_with($valor, '#') || str_starts_with($valor, '/')) {
            return $valor;
        }

        $esquema = mb_strtolower((string) parse_url($valor, PHP_URL_SCHEME));

        return in_array($esquema, ['http', 'https', 'mailto', 'tel'], true) ? $valor : '';
    }

    /**
     * Una ruta de imagen dentro de `public/`.
     *
     * No se aceptan URLs externas: el sitio sirve sus propias imágenes, y un
     * `src` a otro dominio filtra a quién visita el home hacia un tercero.
     * Tampoco `..`, que se saldría de la carpeta.
     */
    public function rutaImagen(?string $valor): string
    {
        $valor = trim((string) $valor);
        $valor = ltrim(str_replace('\\', '/', $valor), '/');

        if ($valor === '' || str_contains($valor, '..') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $valor)) {
            return '';
        }

        return $valor;
    }

    private function sanitizador(): HtmlSanitizer
    {
        if ($this->sanitizador) {
            return $this->sanitizador;
        }

        /*
         * Se parte de la configuración vacía, **no** de `allowSafeElements()`
         * ni de `allowStaticElements()`: esos dos permiten el catálogo entero
         * del W3C —tablas, `div`, `img`, `span`…— y la lista de aquí dejaría
         * de ser la que manda.
         *
         * La acción por defecto se cambia de `Drop` a `Block`, y la diferencia
         * importa: `Drop` se lleva la etiqueta **y el texto que tiene dentro**;
         * `Block` quita sólo la etiqueta y deja el texto. Con `Drop`, pegar
         * desde Word —que envuelve cada frase en `<span style="mso-…">—
         * borraba el párrafo entero: la persona pegaba, veía su texto en el
         * editor, publicaba y se encontraba la sección vacía. Lo pilló
         * home-editor.mjs.
         *
         * `Block` por defecto no afloja la seguridad, porque lo peligroso se
         * nombra abajo una por una con `dropElement()`: un `<script>` sigue
         * yendo fuera con su código dentro, y una etiqueta desconocida pierde
         * sus atributos —con sus `onclick`— al desenvolverse.
         */
        $config = (new HtmlSanitizerConfig)->defaultAction(HtmlSanitizerAction::Block);

        foreach (self::PERMITIDAS as $etiqueta => $atributos) {
            $config = $config->allowElement($etiqueta, $atributos);
        }

        $config = $config
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            // Un enlace a /actividades o a #kit tiene que sobrevivir. Symfony
            // los tira por defecto, y sin esta línea todo enlace interno que
            // escribiera la ONG desaparecía en silencio al guardar.
            ->allowRelativeLinks()
            // Los enlaces salientes no deben poder tocar la pestaña de origen.
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            /*
             * Estas se van con lo que lleven dentro. Es la lista que hay que
             * mirar cuando alguien venga a añadir una etiqueta a la lista
             * blanca: todo lo demás sólo se desenvuelve, pero esto se borra.
             */
            ->dropElement('script')
            ->dropElement('style')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('embed')
            ->dropElement('form')
            ->dropElement('input')
            ->dropElement('textarea')
            ->dropElement('button')
            ->dropElement('svg')
            ->dropElement('math');

        return $this->sanitizador = new HtmlSanitizer($config);
    }

    /** ¿Lo que queda son sólo etiquetas vacías y espacios? */
    private function soloHuecos(string $html): bool
    {
        return trim(str_replace("\u{00A0}", ' ', strip_tags($html))) === ''
            && ! str_contains($html, '<img');
    }
}
