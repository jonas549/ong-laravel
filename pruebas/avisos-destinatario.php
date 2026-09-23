<?php

/**
 * Punto 9 del 23/09 — a quién llega el aviso de «una actividad volvió de
 * ajustes».
 *
 * Pasa por el camino de verdad (`ActivityModerationService::cambiar`, de
 * «ajustes» a «revisión») con el correo falseado, y mira a quién iría. Todo
 * dentro de una transacción que se deshace al terminar: no deja nada.
 *
 *     php artisan tinker --execute="require base_path('pruebas/avisos-destinatario.php');"
 */

use App\Mail\ActivityResubmitted;
use App\Models\Activity;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityModerationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

$ok = 0;
$mal = 0;
$di = function (string $que, bool $bien, string $extra = '') use (&$ok, &$mal) {
    $bien ? $ok++ : $mal++;
    echo '  '.str_pad($que, 62).' '.($bien ? 'OK' : '*** MAL ***').' '.$extra.PHP_EOL;
};

// A quién iría el aviso, pasando una actividad de «ajustes» a «revisión».
$destinos = function () {
    Mail::fake();

    $a = Activity::whereNotNull('organization_id')->firstOrFail();
    $a->forceFill(['estado' => 'ajustes'])->save();

    app(ActivityModerationService::class)->cambiar($a, 'revision', $a->organization?->user, 'Ya lo corregí.');

    $a_quien = [];
    Mail::assertQueued(ActivityResubmitted::class, function ($m) use (&$a_quien) {
        foreach ($m->to as $t) {
            $a_quien[] = $t['address'];
        }

        return true;
    });

    return $a_quien;
};

DB::beginTransaction();

try {
    $admins = User::where('role', 'admin')->where('is_active', true)->pluck('email')->all();

    echo PHP_EOL.'=== 1 · Con el valor sembrado ==='.PHP_EOL.PHP_EOL;
    $di('el ajuste existe con el buzón del equipo', Setting::get('avisos_email') === 'diadelpatrimoniosocial@comunidad-org.cl', (string) Setting::get('avisos_email'));
    $d = $destinos();
    $di('el aviso va al buzón del equipo', $d === ['diadelpatrimoniosocial@comunidad-org.cl'], implode(', ', $d));
    $di('y a ningún administrador por su cuenta', array_intersect($d, $admins) === [], count($admins).' admins activos');

    echo PHP_EOL.'=== 2 · Cambiado desde el panel ==='.PHP_EOL.PHP_EOL;
    Setting::set('avisos_email', 'otro-buzon@ejemplo.cl');
    Cache::forget(Setting::CACHE_KEY);
    $d = $destinos();
    $di('sigue al ajuste', $d === ['otro-buzon@ejemplo.cl'], implode(', ', $d));

    echo PHP_EOL.'=== 3 · Vacío: no se pierde el aviso ==='.PHP_EOL.PHP_EOL;
    Setting::set('avisos_email', '');
    Cache::forget(Setting::CACHE_KEY);
    $d = $destinos();
    sort($d);
    sort($admins);
    $di('vuelve a los administradores activos', $d === $admins, implode(', ', $d));
} finally {
    DB::rollBack();
    Cache::forget(Setting::CACHE_KEY);
}

echo PHP_EOL."{$ok} bien, {$mal} mal".PHP_EOL;
