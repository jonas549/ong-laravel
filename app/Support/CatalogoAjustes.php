<?php

namespace App\Support;

/**
 * Los ajustes que no son ni un texto libre ni un número: los que se eligen de
 * una lista corta y cerrada.
 *
 * La lista vive en el código y no en una columna nueva de `settings`, por la
 * misma razón que los textos del home viven en `CatalogoHome`: son opciones que
 * el código tiene que saber interpretar. Añadir una tercera forma de abrir la
 * encuesta no es escribir una fila, es escribir el `match` que la resuelve; una
 * columna editable prometería lo contrario.
 */
final class CatalogoAjustes
{
    /**
     * Cuándo se abre la encuesta de evaluación de una actividad.
     *
     * Las dos son razonables y por eso lo decide la ONG y no nosotros:
     * «publicacion» sirve para que el organizador pueda probar su propio QR
     * antes del día, y «actividad» evita que alguien evalúe algo a lo que
     * todavía no ha ido.
     */
    public const EVALUACION_APERTURA = [
        'publicacion' => 'Desde que se publica la actividad',
        'actividad' => 'Desde el día de la actividad',
    ];

    /** @var array<string, array<string, string>> ajuste => opciones */
    public const OPCIONES = [
        'evaluacion_apertura' => self::EVALUACION_APERTURA,
    ];

    /** @return array<string, string> */
    public static function opciones(string $clave): array
    {
        return self::OPCIONES[$clave] ?? [];
    }

    public static function tieneOpciones(string $clave): bool
    {
        return isset(self::OPCIONES[$clave]);
    }
}
