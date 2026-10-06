<?php

/**
 * Tanda del 05/10 — los tres avisos al equipo.
 *
 *   1. actividad nueva en revisión
 *   2. actividad publicada sola por la aprobación automática
 *   3. actividad ya publicada que la organización edita (uno por día)
 *
 * Pasa por el camino de verdad (`ActivityModerationService::cambiar` y
 * `CorreoTransaccional`) con la cola de verdad, y mira lo que quedó en el
 * registro de correos. Todo dentro de una transacción que se deshace al
 * terminar: no deja nada, ni filas en `jobs`.
 *
 * Comprueba además que la aprobación automática decide lo mismo que antes:
 * el aviso sólo observa.
 *
 *     php artisan tinker --execute="require base_path('pruebas/avisos-equipo.php');"
 */

use App\Models\Activity;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityModerationService;
use App\Services\AprobacionAutomatica;
use App\Services\CorreoTransaccional;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$ok = 0;
$mal = 0;
$di = function (string $que, bool $bien, string $extra = '') use (&$ok, &$mal) {
    $bien ? $ok++ : $mal++;
    echo '  '.str_pad($que, 66).' '.($bien ? 'OK' : '*** MAL ***').' '.$extra.PHP_EOL;
};

$moderacion = app(ActivityModerationService::class);
$correos = app(CorreoTransaccional::class);
$aprobacion = app(AprobacionAutomatica::class);
$buzon = 'diadelpatrimoniosocial@comunidad-org.cl';

// Los correos de una actividad que llegaron al registro desde $desde.
$avisos = function (Activity $a, string $plantilla, int $desde) {
    return EmailLog::where('id', '>', $desde)
        ->where('plantilla', $plantilla)
        ->where('related_type', Activity::class)
        ->where('related_id', $a->id)
        ->get();
};
$ultimo = fn () => (int) EmailLog::max('id');

DB::beginTransaction();

try {
    Setting::set('avisos_email', $buzon);
    Cache::forget(Setting::CACHE_KEY);

    $org = Organization::whereNotNull('user_id')->whereHas('user')->firstOrFail();
    $nueva = function (string $estado) use ($org) {
        $a = Activity::where('organization_id', $org->id)->firstOrFail()->replicate();
        $a->forceFill([
            'titulo' => 'Prueba avisos '.uniqid(),
            'slug' => 'prueba-avisos-'.uniqid(),
            'estado' => $estado,
            'published_at' => null,
            'publicada_automaticamente' => false,
        ])->save();

        return $a;
    };

    echo PHP_EOL.'=== 0 · Las tres plantillas existen y son editables ==='.PHP_EOL.PHP_EOL;
    foreach (['equipo_actividad_en_revision', 'equipo_actividad_autopublicada', 'equipo_actividad_editada'] as $clave) {
        $p = EmailTemplate::where('clave', $clave)->first();
        $di("plantilla {$clave}", $p !== null && $p->activo, $p ? 'activa' : 'falta: correr dps:instalar');
    }

    echo PHP_EOL.'=== 1 · Nueva en revisión ==='.PHP_EOL.PHP_EOL;
    $a = $nueva('borrador');
    $desde = $ultimo();
    $moderacion->cambiar($a, 'revision', null, 'A revisión: es la primera actividad de esta organización.');
    $r = $avisos($a, 'equipo_actividad_en_revision', $desde);
    $di('sale un aviso al equipo', $r->count() === 1, $r->count().' aviso(s)');
    $di('va al buzón de Configuración → General', $r->first()?->to === $buzon, (string) $r->first()?->to);
    $di('queda en cola, como todo el correo', $r->first()?->status === EmailLog::EN_COLA, (string) $r->first()?->status);
    $di('el asunto lleva el nombre de la actividad', str_contains((string) $r->first()?->subject, $a->titulo));
    $di('el organizador sigue recibiendo su «recibimos tu actividad»',
        EmailLog::where('id', '>', $desde)->where('mailable', App\Mail\ActivityReceived::class)->exists());
    $di('no sale el de autopublicada', $avisos($a, 'equipo_actividad_autopublicada', $desde)->isEmpty());

    echo PHP_EOL.'=== 2 · Vuelve de ajustes: sólo el aviso de siempre ==='.PHP_EOL.PHP_EOL;
    $a->forceFill(['estado' => 'ajustes'])->save();
    $desde = $ultimo();
    $moderacion->cambiar($a, 'revision', $org->user, 'Ya lo corregí.');
    $di('no sale el de «nueva en revisión»', $avisos($a, 'equipo_actividad_en_revision', $desde)->isEmpty());
    $di('sigue saliendo el de vuelta de ajustes',
        EmailLog::where('id', '>', $desde)->where('mailable', App\Mail\ActivityResubmitted::class)->where('to', $buzon)->exists());

    echo PHP_EOL.'=== 3 · Publicada sola por la aprobación automática ==='.PHP_EOL.PHP_EOL;
    $b = $nueva('borrador');
    $desde = $ultimo();
    $moderacion->cambiar($b, 'publicada', null, 'Publicada automáticamente.', automatica: true);
    $r = $avisos($b, 'equipo_actividad_autopublicada', $desde);
    $di('sale un aviso al equipo', $r->count() === 1, $r->count().' aviso(s)');
    $di('va al buzón', $r->first()?->to === $buzon);
    $di('el organizador sigue recibiendo el suyo, con el QR', $avisos($b, 'actividad_publicada', $desde)->count() === 1);
    $di('no sale el de «nueva en revisión»', $avisos($b, 'equipo_actividad_en_revision', $desde)->isEmpty());

    echo PHP_EOL.'=== 4 · Aprobada a mano: no es automática ==='.PHP_EOL.PHP_EOL;
    $c = $nueva('revision');
    $desde = $ultimo();
    $moderacion->cambiar($c, 'publicada', User::where('role', 'admin')->first());
    $di('no sale el de autopublicada', $avisos($c, 'equipo_actividad_autopublicada', $desde)->isEmpty());
    $di('el organizador recibe su aviso de siempre', $avisos($c, 'actividad_publicada', $desde)->count() === 1);

    echo PHP_EOL.'=== 5 · Editada: una vez por actividad y día ==='.PHP_EOL.PHP_EOL;
    $desde = $ultimo();
    $primero = $correos->equipoActividadEditada($b);
    $segundo = $correos->equipoActividadEditada($b);
    $tercero = $correos->equipoActividadEditada($b);
    $r = $avisos($b, 'equipo_actividad_editada', $desde);
    $di('el primer guardado avisa', $primero === true);
    $di('el segundo y el tercero del día, no', $segundo === false && $tercero === false);
    $di('y en el registro hay uno solo', $r->count() === 1, $r->count().' aviso(s)');
    $di('otra actividad editada el mismo día sí avisa', $correos->equipoActividadEditada($c) === true);

    // El de ayer no cuenta: se mueve a ayer, en hora de Chile.
    EmailLog::whereKey($r->first()->id)->update(['created_at' => now(App\Support\Fecha::zona())->startOfDay()->subMinute()->utc()]);
    $di('un aviso de ayer no frena el de hoy', $correos->equipoActividadEditada($b) === true);

    echo PHP_EOL.'=== 6 · Buzón vacío: a los administradores ==='.PHP_EOL.PHP_EOL;
    Setting::set('avisos_email', '');
    Cache::forget(Setting::CACHE_KEY);
    $admins = User::where('role', 'admin')->where('is_active', true)->whereNotNull('email')->pluck('email')->sort()->values()->all();
    $d = $nueva('borrador');
    $desde = $ultimo();
    $moderacion->cambiar($d, 'revision', null, 'A revisión: prueba.');
    $a_quien = $avisos($d, 'equipo_actividad_en_revision', $desde)->pluck('to')->sort()->values()->all();
    $di('uno a cada administrador activo', $a_quien === $admins, implode(', ', $a_quien));
    Setting::set('avisos_email', $buzon);
    Cache::forget(Setting::CACHE_KEY);

    echo PHP_EOL.'=== 7 · Plantilla apagada desde el panel: no sale ==='.PHP_EOL.PHP_EOL;
    EmailTemplate::where('clave', 'equipo_actividad_en_revision')->update(['activo' => false]);
    $e = $nueva('borrador');
    $desde = $ultimo();
    $moderacion->cambiar($e, 'revision', null, 'A revisión: prueba.');
    $di('apagada, no se envía', $avisos($e, 'equipo_actividad_en_revision', $desde)->isEmpty());
    $di('y el cambio de estado se guardó igual', $e->fresh()->estado === 'revision');

    echo PHP_EOL.'=== 8 · La aprobación automática no cambió ==='.PHP_EOL.PHP_EOL;
    // Cuatro reglas, contra la misma organización.
    $antes = $aprobacion->cuantasPublico($org);
    $di('cuenta por published_at (la autopublicada de la prueba suma)', $antes >= 1);
    $cancelada = $nueva('borrador');
    $cancelada->forceFill(['estado' => 'cancelada', 'published_at' => now()])->save();
    $di('una cancelada que estuvo publicada cuenta', $aprobacion->cuantasPublico($org) === $antes + 1);
    $pausa = $nueva('ajustes');
    $di('un «ajustes» abierto la pausa', $aprobacion->motivoDeRevision($org) !== null, (string) $aprobacion->motivoDeRevision($org));
    $pausa->forceFill(['estado' => 'borrador'])->save();
    $org->forceFill(['requiere_revision' => true])->save();
    $di('el interruptor de la organización sigue mandando', $aprobacion->motivoDeRevision($org) === 'esta organización está marcada para revisión siempre');
} finally {
    DB::rollBack();
    Cache::forget(Setting::CACHE_KEY);
}

echo PHP_EOL."{$ok} bien, {$mal} mal".PHP_EOL;
