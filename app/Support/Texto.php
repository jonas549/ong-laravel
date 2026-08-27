<?php

namespace App\Support;

/**
 * Plurales en español.
 *
 * `Str::plural` de Laravel pluraliza en inglés: le añade una `s` y arregla los
 * casos raros del inglés. Acierta por casualidad con las palabras que acaban en
 * vocal —«dato», «persona», «registro»— y falla con todo lo demás. En pantalla
 * salió como **«Historial · 5 versións»**, y `Str::plural('actividad')` da
 * «actividads»; en una vista se había parcheado a mano con un `. 's'` pegado
 * fuera, que es la señal de que la herramienta no era la correcta.
 *
 * Las reglas del castellano son cuatro y caben aquí:
 *
 * - Vocal átona o `-é` tónica: se añade `-s`. «casa» → «casas».
 * - Consonante: se añade `-es`. «papel» → «papeles».
 * - `-z`: pasa a `-ces`. «vez» → «veces».
 * - `-s` o `-x` sin acento en la última sílaba: no cambia. «crisis» → «crisis».
 *
 * Y una que se olvida siempre: al añadir `-es`, **el acento de la última sílaba
 * desaparece**, porque deja de hacer falta. «versión» → «versiones».
 */
class Texto
{
    private const ACENTUADAS = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u'];

    /**
     * El plural de una palabra.
     *
     * Con `$cantidad`, devuelve el singular cuando vale uno: es lo que se
     * quiere el 99 % de las veces y evita el `{{ $n === 1 ? … : … }}` repetido
     * por todas las vistas.
     */
    public static function plural(string $palabra, ?int $cantidad = null): string
    {
        if ($cantidad === 1 || $cantidad === -1) {
            return $palabra;
        }

        if ($palabra === '') {
            return $palabra;
        }

        $ultima = mb_strtolower(mb_substr($palabra, -1));

        // «vez» → «veces», «lápiz» → «lápices».
        if ($ultima === 'z') {
            return static::sinAcentoFinal(mb_substr($palabra, 0, -1)).'ces';
        }

        // Vocal átona, o `-é`: basta con la `s`.
        if (in_array($ultima, ['a', 'e', 'i', 'o', 'u', 'é'], true)) {
            return $palabra.'s';
        }

        /*
         * `-s` y `-x`: sólo cambian si la última sílaba va acentuada. «autobús»
         * → «autobuses», pero «crisis» y «tórax» se quedan igual. Se aproxima
         * mirando si hay tilde en la última vocal, que es lo que distingue los
         * dos casos en la práctica.
         */
        if (in_array($ultima, ['s', 'x'], true)) {
            return static::acabaEnTonica($palabra) ? static::sinAcentoFinal($palabra).'es' : $palabra;
        }

        // `-í` y `-ú` tónicas admiten las dos formas; se usa la culta.
        if (in_array($ultima, ['í', 'ú'], true)) {
            return $palabra.'es';
        }

        if (in_array($ultima, ['á', 'ó'], true)) {
            return $palabra.'s';
        }

        // Consonante: `-es`, y sin la tilde que ya no hace falta.
        return static::sinAcentoFinal($palabra).'es';
    }

    /**
     * «3 versiones» / «1 versión», con el número delante.
     *
     * Existe porque el patrón `{{ $n }} {{ plural(...) }}` estaba escrito una
     * docena de veces y siempre se puede escribir una vez.
     */
    public static function cuantos(int $cantidad, string $palabra): string
    {
        return $cantidad.' '.static::plural($palabra, $cantidad);
    }

    /** Quita la tilde de la última vocal acentuada de la palabra. */
    private static function sinAcentoFinal(string $palabra): string
    {
        $letras = mb_str_split($palabra);

        for ($i = count($letras) - 1; $i >= 0; $i--) {
            $minuscula = mb_strtolower($letras[$i]);

            if (isset(self::ACENTUADAS[$minuscula])) {
                $limpia = self::ACENTUADAS[$minuscula];
                $letras[$i] = $letras[$i] === $minuscula ? $limpia : mb_strtoupper($limpia);

                return implode('', $letras);
            }
        }

        return $palabra;
    }

    private static function acabaEnTonica(string $palabra): bool
    {
        // La tilde en las dos últimas letras: «autobús», «francés».
        foreach (mb_str_split(mb_substr($palabra, -3)) as $letra) {
            if (isset(self::ACENTUADAS[mb_strtolower($letra)])) {
                return true;
            }
        }

        return false;
    }
}
