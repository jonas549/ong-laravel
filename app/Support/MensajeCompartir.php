<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Setting;

/**
 * El mensaje que se propone al compartir una actividad por WhatsApp.
 *
 * Lo escribe la ONG en Configuración → General (`compartir_mensaje`), con dos
 * marcadores: `{nombre}` y `{enlace}`, que se cambian por los de la
 * actividad. Vacío, se usa el de siempre.
 *
 * Si el texto no lleva `{enlace}`, el enlace se añade al final igual: un
 * mensaje de compartir que no lleva a la actividad no sirve para nada, y
 * quitarlo sin querer al reescribir el texto es fácil.
 */
class MensajeCompartir
{
    public const POR_DEFECTO = 'Súmate a esta actividad de celebración del Día del Patrimonio Social: {nombre} {enlace}';

    public static function para(Activity $actividad): string
    {
        $plantilla = trim((string) Setting::get('compartir_mensaje')) ?: self::POR_DEFECTO;
        $enlace = route('activities.show', $actividad);

        $texto = strtr($plantilla, ['{nombre}' => $actividad->titulo, '{enlace}' => $enlace]);

        return str_contains($plantilla, '{enlace}') ? $texto : $texto.' '.$enlace;
    }
}
