<?php

namespace App\Support;

/**
 * Ayudas para repintar formularios tras un error de validación.
 */
class Formulario
{
    /**
     * Lo mismo que old(), pero garantizando un escalar.
     *
     * Si alguien envía `name[]=x`, old() devuelve un array y `{{ }}` revienta
     * con un 500 al repintar el formulario. Como el valor sólo sirve para
     * rellenar un campo de texto, un array nunca es un valor válido: se
     * descarta y se vuelve al valor por defecto.
     */
    public static function viejo(string $campo, mixed $defecto = ''): string
    {
        $valor = old($campo, $defecto);

        if (is_array($valor) || is_object($valor)) {
            return is_string($defecto) ? $defecto : '';
        }

        return (string) $valor;
    }
}
