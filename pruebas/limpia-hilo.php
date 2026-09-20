<?php

/*
 * Deshace el escenario de `datos-hilo.php` y deja la actividad 5 como la
 * siembra: en «necesita ajustes» y con la observación del seeder.
 *
 * Hay que correrlo al terminar. Esta actividad es la única sembrada en
 * «ajustes», así que dejarla en «revisión» descuadra las cuentas por estado de
 * cualquier suite que las mire, y el fallo aparece lejos de aquí.
 *
 *   php artisan tinker --execute="require base_path('pruebas/limpia-hilo.php');"
 */

use App\Models\Activity;
use App\Models\ActivityStatusLog;

$actividad = Activity::find(5);

if (! $actividad) {
    echo "No está la actividad 5.\n";

    return;
}

ActivityStatusLog::where('activity_id', $actividad->id)->delete();

$actividad->forceFill([
    'estado' => 'ajustes',
    'observaciones_revision' => 'Falta indicar el punto de encuentro exacto y confirmar si la ruta '
        .'es accesible para personas con movilidad reducida.',
])->save();

echo "Actividad {$actividad->id} devuelta a «ajustes».\n";
