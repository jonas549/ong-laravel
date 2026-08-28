<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * El filtro «ver eliminados» de los listados del panel.
 *
 * No hay una pantalla de papelera aparte, y es una decisión: lo eliminado se
 * recupera **en el listado donde se eliminó**, que es donde uno va a buscarlo.
 * Una papelera común obligaría además a mantener una pantalla más cada vez que
 * se añade un CRUD.
 *
 * Tres estados, y el nombre del parámetro es el mismo en todos los listados
 * para que la URL se lea igual en todas partes:
 *
 *   (vacío)     lo de siempre: sin lo eliminado
 *   eliminados  sólo lo eliminado, para restaurar
 *   todos       las dos cosas, para ver el conjunto
 */
class Papelera
{
    public const OPCIONES = [
        '' => 'Sin lo eliminado',
        'eliminados' => 'Sólo lo eliminado',
        'todos' => 'Todo, eliminado o no',
    ];

    /** Aplica a la consulta lo que pida la URL. */
    public static function aplicar(Builder $consulta, Request $request): Builder
    {
        return match (static::estado($request)) {
            'eliminados' => $consulta->onlyTrashed(),
            'todos' => $consulta->withTrashed(),
            default => $consulta,
        };
    }

    public static function estado(Request $request): string
    {
        $pedido = Filtro::texto($request, 'papelera');

        return array_key_exists($pedido, self::OPCIONES) ? $pedido : '';
    }

    /** ¿Se están viendo filas eliminadas ahora mismo? */
    public static function incluyeEliminados(Request $request): bool
    {
        return in_array(static::estado($request), ['eliminados', 'todos'], true);
    }
}
