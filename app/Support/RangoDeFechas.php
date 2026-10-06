<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Cómo se escribe la fecha de una actividad que puede durar varios días.
 *
 * Nació en la imagen de difusión («13 al 15 de septiembre de 2026») y se sacó
 * aquí en la tanda del 05/10, cuando la fecha de término pasó a pedirse en el
 * wizard: la ficha, las tarjetas, mi-cuenta y los correos sólo enseñaban el
 * primer día. Un solo sitio para que todos lo digan igual.
 *
 * Con un solo día (o término igual al inicio) devuelve exactamente lo de
 * antes, así que las actividades de un día no cambian en ninguna pantalla.
 */
class RangoDeFechas
{
    /** «13 al 15 de septiembre de 2026», «30 de septiembre al 2 de octubre de 2026». */
    public static function largo(CarbonInterface $ini, ?CarbonInterface $fin): string
    {
        $ini = $ini->locale('es');
        $fin = $fin?->locale('es');

        return match (true) {
            ! $fin || $fin->isSameDay($ini) => $ini->isoFormat('D [de] MMMM [de] YYYY'),
            $fin->isSameMonth($ini) => $ini->isoFormat('D').' al '.$fin->isoFormat('D [de] MMMM [de] YYYY'),
            $fin->isSameYear($ini) => $ini->isoFormat('D [de] MMMM').' al '.$fin->isoFormat('D [de] MMMM [de] YYYY'),
            default => $ini->isoFormat('D [de] MMMM [de] YYYY').' al '.$fin->isoFormat('D [de] MMMM [de] YYYY'),
        };
    }

    /** Las tarjetas: «Sáb 26 jul» y, con varios días, «13 al 15 sept.» */
    public static function corto(CarbonInterface $ini, ?CarbonInterface $fin): string
    {
        $ini = $ini->locale('es');
        $fin = $fin?->locale('es');

        if (! $fin || $fin->isSameDay($ini)) {
            return \Illuminate\Support\Str::ucfirst($ini->isoFormat('ddd D MMM'));
        }

        return $fin->isSameMonth($ini)
            ? $ini->isoFormat('D').' al '.$fin->isoFormat('D MMM')
            : $ini->isoFormat('D MMM').' al '.$fin->isoFormat('D MMM');
    }

    /** El listado de mi-cuenta: «26 julio 2026» y «13 al 15 septiembre 2026». */
    public static function lista(CarbonInterface $ini, ?CarbonInterface $fin): string
    {
        $ini = $ini->locale('es');
        $fin = $fin?->locale('es');

        return match (true) {
            ! $fin || $fin->isSameDay($ini) => $ini->isoFormat('D MMMM YYYY'),
            $fin->isSameMonth($ini) => $ini->isoFormat('D').' al '.$fin->isoFormat('D MMMM YYYY'),
            $fin->isSameYear($ini) => $ini->isoFormat('D MMMM').' al '.$fin->isoFormat('D MMMM YYYY'),
            default => $ini->isoFormat('D MMMM YYYY').' al '.$fin->isoFormat('D MMMM YYYY'),
        };
    }
}
