<?php

/*
 * Escenario del hilo de moderación (P2, P3 y P4).
 *
 * Deja la actividad 5 en «revisión» y sin historial, que es el punto de
 * partida desde el que la prueba recorre el circuito entero por la interfaz:
 * la ONG pide ajustes, la organización corrige y responde, y la ONG lo ve.
 *
 * Se limpia el historial a propósito: el hilo se lee entero, así que las
 * sobras de una pasada anterior harían fallar las cuentas de mensajes.
 *
 *   php artisan tinker --execute="require base_path('pruebas/datos-hilo.php');"
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
    'estado' => 'revision',
    'observaciones_revision' => null,
    'published_at' => null,
    'publicada_automaticamente' => false,
])->save();

echo "Actividad {$actividad->id} en revisión y sin historial.\n";

/*
 * Para dejarlo como estaba al terminar (lo pide el README: un escenario
 * olvidado hace parecer roto lo que funciona):
 *
 *   php artisan tinker --execute="require base_path('pruebas/limpia-hilo.php');"
 */
