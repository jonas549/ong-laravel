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

/* Los trabajos fallidos se acumulan; un mes de historial es suficiente. */
Schedule::command('queue:prune-failed --hours=720')->weekly();
