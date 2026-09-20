<?php

namespace App\Support;

/**
 * Un enlace escrito a mano, completado.
 *
 * Nadie escribe `https://` al copiar la dirección de su Instagram. Se escribe
 * `instagram.com/loquesea` o `www.tusitio.cl`, y el formulario lo rechazaba
 * con «el campo debe ser una URL válida» — un error que no dice qué falta y
 * que además culpa a la persona de algo que el servidor puede resolver solo.
 *
 * Así que se completa antes de validar: lo que se guarda lleva siempre
 * esquema, y la regla `url` sólo tiene que rechazar lo que de verdad no es una
 * dirección.
 *
 * **Se completa con `https` y no con `http`.** Un sitio que sólo hable http
 * redirigirá; uno que hable https y reciba http viaja en claro la primera vez.
 */
final class Enlace
{
    /**
     * Lo que ya trae esquema se respeta tal cual: puede ser `http://` de un
     * sitio viejo, y también puede ser `javascript:`, que se deja pasar aquí
     * a propósito para que lo rechace la regla `url:http,https` y el error
     * salga en el campo, donde se lee.
     */
    private const CON_ESQUEMA = '#^[a-z][a-z0-9+.\-]*:#i';

    /**
     * Devuelve el enlace listo para guardar, o `null` si venía vacío.
     *
     * No valida: eso es trabajo de las reglas. Aquí sólo se quitan los
     * espacios —pegar de un correo arrastra alguno, y también el `<` `>` con
     * que algunos clientes rodean las direcciones— y se pone el esquema.
     */
    public static function normalizar(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }

        $limpio = trim($valor, " \t\n\r\0\x0B<>");

        if ($limpio === '') {
            return null;
        }

        /*
         * Una dirección sin punto no es un dominio: `loquesea` se quedaría en
         * `https://loquesea`, que la regla `url` da por buena y no lleva a
         * ninguna parte. Se devuelve sin tocar para que rebote con su error.
         */
        if (preg_match(self::CON_ESQUEMA, $limpio) || ! str_contains($limpio, '.')) {
            return $limpio;
        }

        return 'https://'.$limpio;
    }

    /**
     * Los mismos campos, de una tanda, para el `prepareForValidation` de un
     * formulario que lleve varios.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<int, string>  $campos
     * @return array<string, mixed> sólo los que venían en la petición
     */
    public static function normalizarCampos(array $datos, array $campos): array
    {
        $salida = [];

        foreach ($campos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $salida[$campo] = self::normalizar($datos[$campo]);
            }
        }

        return $salida;
    }

    /**
     * La regla de los campos de enlace.
     *
     * `url:http,https` y no `url` a secas: sin acotar el esquema, un
     * `javascript:alert(1)` pasa la validación y acaba en el `href` de la
     * ficha pública, que es donde lo pulsa cualquiera.
     *
     * @return array<int, string>
     */
    public static function reglas(): array
    {
        return ['nullable', 'url:http,https', 'max:255'];
    }
}
