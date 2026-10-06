<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Registration;

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
 *  - personas: inscripciones sin las canceladas. Son inscripciones, no
 *    personas distintas: quien se apunta a dos actividades cuenta dos veces,
 *    que es lo que pidió el cliente («suma las inscripciones»).
 */
class ContadoresHome
{
    public static function actividades(): int
    {
        return Activity::where('estado', 'publicada')->count();
    }

    public static function inscripciones(): int
    {
        return Registration::activas()->count();
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
