<?php

namespace App\Support;

use Illuminate\Support\MessageBag;

/**
 * Convierte los errores de validación en la lista que pinta el resumen de
 * arriba del formulario.
 *
 * El formato de salida es el mismo que produce la revisión previa del navegador
 * (`camposQueFaltan()` en resources/js/formularios.js), para que el resumen sea
 * el mismo componente venga de donde venga el error:
 *
 *     [ ['campo' => 'publicos', 'etiqueta' => 'Público beneficiado',
 *        'paso' => 4, 'mensaje' => 'Indica a qué público está dirigida…'] ]
 *
 * Dos detalles que no son evidentes:
 *
 * - **Los errores de elementos de un array llegan como `temas.0`**, porque esa
 *   es la clave que usa la regla `temas.*`. Para el resumen son el mismo campo
 *   que `temas`: se recorta por el primer punto y se juntan, o la lista diría
 *   «Tema de la actividad» cuatro veces seguidas.
 * - **El orden es el del catálogo, no el del MessageBag.** El catálogo va en el
 *   orden en que los campos se ven en pantalla, y el resumen sirve para bajar
 *   corrigiendo de arriba abajo; el del MessageBag es el de las reglas.
 */
final class ResumenDeErrores
{
    /**
     * @param  array<string, array{0: string, 1: int|null}>  $catalogo
     * @return list<array{campo: string, etiqueta: string, paso: int|null, mensaje: string}>
     */
    public static function desde(MessageBag $errores, array $catalogo): array
    {
        $vistos = [];

        foreach ($errores->keys() as $clave) {
            $campo = explode('.', $clave)[0];

            // El primero que aparezca manda: si `temas` falla por vacío y
            // además `temas.3` no existe, lo que hay que arreglar es lo primero.
            $vistos[$campo] ??= $errores->first($clave);
        }

        $orden = array_keys($catalogo);

        uksort($vistos, function (string $a, string $b) use ($orden) {
            $pa = array_search($a, $orden, true);
            $pb = array_search($b, $orden, true);

            // Lo que no esté en el catálogo se va al final, no al principio.
            return ($pa === false ? PHP_INT_MAX : $pa) <=> ($pb === false ? PHP_INT_MAX : $pb);
        });

        $lista = [];

        foreach ($vistos as $campo => $mensaje) {
            $lista[] = [
                'campo' => $campo,
                'etiqueta' => $catalogo[$campo][0] ?? $campo,
                'paso' => $catalogo[$campo][1] ?? null,
                'mensaje' => $mensaje,
            ];
        }

        return $lista;
    }
}
