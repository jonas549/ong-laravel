<?php

namespace App\Support;

use App\Models\Activity;

/**
 * Las dos barras de «¡Súmate y juntos llegaremos más lejos!» (tanda del 05/10).
 *
 * Cada barra enseña una BASE —lo de años anteriores, que la ONG escribe en
 * Panel → Páginas → Home → «Súmate a la meta»— más lo que ya hay en la
 * plataforma. La meta sigue siendo un número del panel.
 *
 * Las dos cuentas usan la misma definición que la portada del panel, para que
 * no haya dos verdades sobre el mismo número:
 *
 *  - actividades: las que están publicadas ahora. Una cancelada deja de
 *    contar; una cerrada (sólo para difusión) sigue contando, porque está
 *    publicada.
 *  - personas (desde el 08/10, sustituye a «inscripciones sin las
 *    canceladas»): la suma de los participantes estimados que el organizador
 *    declara al publicar, en las publicadas, abiertas y cerradas por igual.
 *    Una sin el dato cuenta cero. Es el mismo conjunto que la barra de
 *    actividades: lo que se publicó, se cuenta.
 */
class ContadoresHome
{
    public static function actividades(): int
    {
        return Activity::where('estado', 'publicada')->count();
    }

    public static function participantesEstimados(): int
    {
        return (int) Activity::where('estado', 'publicada')->sum('participantes_estimados');
    }

    /**
     * La base escrita en el panel más lo contado. La base admite puntos de
     * miles («50.000»), como admitía antes el campo.
     */
    public static function sumar(string $base, int $contado): string
    {
        $n = (int) preg_replace('/\D+/', '', $base);

        return number_format($n + $contado, 0, ',', '.');
    }
}
