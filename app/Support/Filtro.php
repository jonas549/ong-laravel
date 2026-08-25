<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Lectura de los filtros que llegan por la URL en los listados del panel.
 *
 * Existe porque los dos problemas que resuelve se repetían en cada pantalla:
 * un parámetro enviado como array tumbaba la página con un 500, y los
 * comodines de LIKE colados en el buscador devolvían la tabla entera.
 */
class Filtro
{
    /**
     * El valor de un parámetro, siempre como texto.
     *
     * `$request->string()` revienta si el parámetro llega como array
     * (`?resultado[]=exito`), y un filtro mal escrito no puede tumbar una
     * pantalla del panel: si no es un escalar, no es un filtro.
     */
    public static function texto(Request $request, string $campo): string
    {
        $valor = $request->input($campo);

        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /**
     * Prepara un término para un LIKE, neutralizando sus comodines.
     *
     * Buscar `%` devolvía todas las filas en vez de las que contienen ese
     * carácter, así que el filtro mentía. La barra invertida es la de escape
     * por defecto en MySQL.
     */
    public static function like(string $termino): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $termino);
    }
}
