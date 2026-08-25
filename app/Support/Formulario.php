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

    /**
     * Igual, pero sin convertir a texto.
     *
     * Hace falta donde el valor por defecto no es una cadena y el tipo importa
     * —una fecha Carbon que la vista formatea, un booleano de una casilla—:
     * ahí convertir a texto rompería el repintado. Lo único que se descarta es
     * lo que no cabe en un campo de formulario.
     */
    public static function viejoCrudo(string $campo, mixed $defecto = null): mixed
    {
        $valor = old($campo, $defecto);

        return is_array($valor) ? $defecto : $valor;
    }
}
