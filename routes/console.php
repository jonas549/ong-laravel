<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| El servidor sólo necesita una entrada de cron para todo esto:
|
|     * * * * * cd ~/ong-laravel && php artisan schedule:run >> /dev/null 2>&1
|
*/

/*
 * La cola de correos. Con --stop-when-empty el proceso termina en cuanto la
 * vacía, así que no queda un worker permanente ocupando un hosting
 * compartido. withoutOverlapping evita que dos pasadas se pisen, y max-time
 * corta antes del minuto siguiente.
 */
// Sin --tries: el que manda es el $tries de cada mailable.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

/*
 * Recordatorio a las personas inscritas. Una vez al día basta, y el comando
 * se protege de duplicados por su cuenta.
 */
Schedule::command('dps:recordatorios')
    ->dailyAt('09:00')
    ->withoutOverlapping();

/*
 * La invitación a evaluar, cuando la actividad ya pasó (P20).
 *
 * A media mañana y no de madrugada: con el ajuste en «el mismo día», salir a
 * las 00:05 sería escribirle a alguien horas antes de su actividad. A las
 * 10:00 la del mismo día ya empezó —casi todas son de mañana o de tarde— y la
 * del día anterior lleva ya un día entero.
 *
 * El comando decide solo qué día le toca según el ajuste, y se protege de
 * duplicados por su cuenta.
 */
Schedule::command('dps:invitar-evaluacion')
    ->dailyAt('10:00')
    ->withoutOverlapping();

/* Los trabajos fallidos se acumulan; un mes de historial es suficiente. */
Schedule::command('queue:prune-failed --hours=720')->weekly();
