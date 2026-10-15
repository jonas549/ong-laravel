<?php

/**
 * Tanda del 15/10, la lógica:
 *
 *   A. Cancelar desde Mis actividades la devuelve a borradores: historial
 *      «publicada → borrador», inscripciones canceladas y cupos devueltos,
 *      `inscripcion_habilitada` intacta, y los mismos correos de siempre (al
 *      organizador y a cada inscrito vivo, no a los que ya se habían dado de
 *      baja). Cancelar desde el panel sigue siendo «cancelada».
 *   B. Al volver a enviarla va SIEMPRE a revisión, aunque la aprobación
 *      automática la publicaría (se contaría a sí misma). Una que no viene de
 *      cancelación sigue decidiéndose como antes.
 *   C. Si se vuelve a publicar: con su formulario, sin inscritos, y
 *      `dps:recordatorios` no tiene a quién escribir.
 *   D. «Disponible de forma permanente»: el texto largo en la ficha, Mis
 *      actividades, el panel, los correos con {{ fecha }}, las dos
 *      exportaciones y la imagen de difusión; «Todo el año» en lo corto; y
 *      «Fecha por definir» sólo para las que no tienen fecha sin ser
 *      permanentes.
 *
 * Por el camino de verdad (el servicio, el controlador por HTTP interno y la
 * cola), mirando el registro de correos. Dentro de una transacción que se
 * deshace: no deja nada.
 *
 *     php artisan tinker --execute="require base_path('pruebas/cancelar-y-permanente.php');"
 */

use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\RegistrationController as AdminRegistrationController;
use App\Models\Activity;
use App\Models\EmailLog;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\ActivityModerationService;
use App\Services\AprobacionAutomatica;
use App\Services\CorreoTransaccional;
use App\Support\DatosDifusion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$ok = 0;
$mal = 0;
$di = function (string $que, bool $bien, string $extra = '') use (&$ok, &$mal) {
    $bien ? $ok++ : $mal++;
    echo '  '.str_pad($que, 72).' '.($bien ? 'OK' : '*** MAL ***').' '.$extra.PHP_EOL;
};
$t = fn (string $x) => print(PHP_EOL."=== {$x} ===".PHP_EOL.PHP_EOL);

$moderacion = app(ActivityModerationService::class);
$correos = app(CorreoTransaccional::class);
$aprobacion = app(AprobacionAutomatica::class);
$ultimo = fn () => (int) EmailLog::max('id');

DB::beginTransaction();

try {
    // La aprobación automática encendida con umbral 1: una cuenta con una
    // actividad publicada publicaría sola la siguiente.
    Setting::set('aprobacion_automatica', '1');
    Setting::set('aprobacion_automatica_desde', '1');
    Setting::set('avisos_email', 'equipo@comunidad-org.cl');
    Cache::forget(Setting::CACHE_KEY);

    $org = Organization::whereNotNull('user_id')->whereHas('user')->firstOrFail();
    $org->forceFill(['requiere_revision' => false])->saveQuietly();
    $cuenta = $org->user;

    $nueva = function (array $cambios = []) use ($org, $cuenta) {
        $base = Activity::where('organization_id', $org->id)->with('terms')->firstOrFail();
        $a = $base->replicate();
        $a->forceFill($cambios + [
            'user_id' => $cuenta->id,
            'titulo' => 'Prueba cancelar '.uniqid(),
            'slug' => 'prueba-cancelar-'.uniqid(),
            'estado' => 'publicada',
            'published_at' => now()->subDays(3),
            'sin_fecha_definida' => false,
            'fecha_inicio' => now()->addDays(10)->toDateString(),
            'fecha_termino' => null,
            'inscripcion_habilitada' => true,
            'cupos_totales' => 20,
            'cupos_disponibles' => 18,
        ])->save();
        // Temas, características y público van en la tabla de en medio, que
        // replicate() no copia: sin ellos no se podría enviar a revisión.
        $a->terms()->sync($base->terms->pluck('id'));

        return $a;
    };
    $inscribir = fn (Activity $a, string $estado) => Registration::create([
        'activity_id' => $a->id, 'nombre' => 'Persona '.uniqid(), 'correo' => uniqid().'@ejemplo.cl',
        'es_mayor_edad' => true, 'estado' => $estado, 'token' => Str::random(40),
    ]);

    $t('A · Cancelar desde Mis actividades');
    $a = $nueva();
    $viva1 = $inscribir($a, 'pendiente');
    $viva2 = $inscribir($a, 'confirmado');
    $baja = $inscribir($a, 'cancelado');
    $desde = $ultimo();

    $avisados = $moderacion->cancelarPorElOrganizador($a, $cuenta);
    $a->refresh();
    $log = $a->statusLogs()->reorder('id', 'desc')->first();

    $di('queda en borrador, no en cancelada', $a->estado === 'borrador', $a->estado);
    $di('historial: publicada → borrador, «Cancelada por el organizador.»',
        $log?->de_estado === 'publicada' && $log->a_estado === 'borrador' && $log->comentario === 'Cancelada por el organizador.' && $log->user_id === $cuenta->id);
    $di('devuelve a cuántos avisó: los 2 vivos', $avisados === 2, (string) $avisados);
    $di('las dos inscripciones vivas, canceladas', $viva1->fresh()->estado === 'cancelado' && $viva2->fresh()->estado === 'cancelado');
    $di('no quedan inscritos vivos', $a->registrations()->where('estado', '!=', 'cancelado')->count() === 0);
    $di('vuelven los 2 cupos (18 → 20)', $a->cupos_disponibles === 20, (string) $a->cupos_disponibles);
    $di('inscripcion_habilitada NO se apaga', $a->inscripcion_habilitada === true);
    $di('published_at se conserva (el slug y el QR no cambian)', $a->published_at !== null);
    $di('la ficha pública ya no la enseña', ! \Illuminate\Support\Facades\Gate::forUser(null)->allows('view', $a));
    $di('vuelveDeCancelacion() lo sabe', $a->vuelveDeCancelacion());

    $nuevos = EmailLog::where('id', '>', $desde)->get();
    $aInscritos = $nuevos->where('plantilla', 'inscripcion_cancelada');
    $di('correo de cancelación a cada inscrito vivo (2)', $aInscritos->count() === 2, (string) $aInscritos->count());
    $di('… y no al que ya se había dado de baja', ! $aInscritos->contains(fn ($l) => str_contains($l->to, $baja->correo)));
    $di('correo de «cancelada» al organizador', $nuevos->contains(fn ($l) => str_contains((string) $l->mailable, 'ActivityCancelled') && str_contains($l->to, $cuenta->email)),
        $nuevos->pluck('mailable')->filter()->implode(', '));

    $t('A2 · Cancelar desde el panel sigue igual');
    $p = $nueva();
    $vivaP = $inscribir($p, 'pendiente');
    $moderacion->cambiar($p, 'cancelada', $cuenta, null);
    $p->refresh();
    $di('el panel la deja en «cancelada»', $p->estado === 'cancelada');
    $di('el panel apaga inscripcion_habilitada, como antes', $p->inscripcion_habilitada === false);
    $di('el panel no toca las inscripciones, como antes', $vivaP->fresh()->estado === 'pendiente');
    $di('una cancelada por el panel no «vuelve de cancelación»', ! $p->vuelveDeCancelacion());

    $t('B · Volver a enviarla: siempre a revisión');
    $control = $nueva(['estado' => 'borrador', 'published_at' => null]);
    $di('(control) un borrador cualquiera de esta cuenta saldría publicado solo', $aprobacion->estadoAlEnviar($control)[0] === 'publicada', $aprobacion->estadoAlEnviar($control)[1]);
    $di('(control) y no «vuelve de cancelación»', ! $control->vuelveDeCancelacion());

    // Por el controlador de verdad: «Enviar a revisión» de Mis actividades.
    $peticion = Illuminate\Http\Request::create("/mi-cuenta/actividades/{$a->id}/enviar", 'POST');
    $peticion->setUserResolver(fn () => $cuenta);
    auth()->setUser($cuenta);
    app(\App\Http\Controllers\Account\MyActivityController::class)->submitForReview($peticion, $a, $moderacion, $aprobacion);
    $a->refresh();
    $log = $a->statusLogs()->reorder('id', 'desc')->first();
    $di('va a revisión aunque la regla la publicaría', $a->estado === 'revision', $a->estado);
    $di('con el motivo en el historial', $log?->comentario === 'A revisión: vuelve de una cancelación.', (string) $log?->comentario);
    $di('y ya no «vuelve de cancelación» (no es borrador)', ! $a->vuelveDeCancelacion());

    $t('C · Si se vuelve a publicar');
    $moderacion->cambiar($a, 'publicada', $cuenta);
    $a->refresh();
    $di('vuelve con su formulario de inscripción', $a->inscripcion_habilitada === true);
    $di('sin inscritos vivos', $a->registrations()->where('estado', '!=', 'cancelado')->count() === 0);
    $di('con todos sus cupos', $a->cupos_disponibles === 20, (string) $a->cupos_disponibles);
    $a->forceFill(['fecha_inicio' => now(\App\Support\Fecha::zona())->addDay()->toDateString()])->save();
    $desde = $ultimo();
    Illuminate\Support\Facades\Artisan::call('dps:recordatorios');
    $recordatorios = EmailLog::where('id', '>', $desde)->where('plantilla', 'recordatorio')->get()
        ->filter(fn ($l) => in_array(trim($l->to, '[]"'), [$viva1->correo, $viva2->correo], true) || str_contains($l->to, $viva1->correo) || str_contains($l->to, $viva2->correo));
    $di('dps:recordatorios no escribe a los que se avisó de la cancelación', $recordatorios->isEmpty(), (string) $recordatorios->count());

    $t('D · «Disponible de forma permanente»');
    $perm = $nueva(['sin_fecha_definida' => true, 'fecha_inicio' => null, 'hora_inicio' => '10:00', 'hora_termino' => '12:00']);
    $sinFecha = $nueva(['sin_fecha_definida' => false, 'fecha_inicio' => null]);
    $largo = 'Actividad disponible de forma permanente';

    $di('fecha_larga (ficha, panel, correos)', $perm->fecha_larga === $largo, $perm->fecha_larga);
    $di('fecha_lista (Mis actividades)', $perm->fecha_lista === $largo, $perm->fecha_lista);
    $di('fecha_corta (tarjeta, buscador, listados del panel): «Todo el año»', $perm->fecha_corta === 'Todo el año', $perm->fecha_corta);
    $di('exportar actividades, columna de fecha', AdminActivityController::filaDeExportacion($perm)[7] === $largo, (string) AdminActivityController::filaDeExportacion($perm)[7]);
    $di('exportar inscripciones, fecha de la actividad', AdminRegistrationController::fechaDeActividad($perm) === $largo);
    $di('imagen de difusión', DatosDifusion::de($perm)['cuando']['fecha'] === $largo, DatosDifusion::de($perm)['cuando']['fecha']);
    $di('sin fecha y no permanente: «Fecha por definir» (larga y lista)', $sinFecha->fecha_larga === 'Fecha por definir' && $sinFecha->fecha_lista === 'Fecha por definir');
    $di('sin fecha y no permanente: «Por definir» (corta y exportaciones)', $sinFecha->fecha_corta === 'Por definir' && AdminRegistrationController::fechaDeActividad($sinFecha) === 'Por definir');

    // Los siete correos con {{ fecha }}. Al encolar todavía no hay cuerpo, así
    // que se capturan con Mail::fake y se pinta cada uno tal cual saldría.
    $insc = $inscribir($perm, 'pendiente')->setRelation('activity', $perm);
    \Illuminate\Support\Facades\Mail::fake();
    $correos->inscripcionConfirmada($insc);
    $correos->nuevaInscripcion($insc);
    $correos->inscripcionCancelada($insc);
    $correos->actividadPublicada($perm);
    $correos->equipoActividadEnRevision($perm);
    $correos->equipoActividadAutopublicada($perm);
    $correos->equipoActividadEditada($perm);
    $plantillas = ['inscripcion_confirmada', 'nueva_inscripcion', 'inscripcion_cancelada', 'actividad_publicada',
        'equipo_actividad_en_revision', 'equipo_actividad_autopublicada', 'equipo_actividad_editada'];

    $capturados = collect(\Illuminate\Support\Facades\Mail::queued(\App\Mail\PlantillaMail::class))
        ->merge(\Illuminate\Support\Facades\Mail::sent(\App\Mail\PlantillaMail::class));

    foreach ($plantillas as $clave) {
        $m = $capturados->first(fn ($m) => $m->plantilla->clave === $clave);
        $cuerpo = $m ? $m->render() : '';
        $di("correo {$clave}", $m !== null && str_contains($cuerpo, $largo) && ! str_contains($cuerpo, 'Fecha por definir'), $m ? '' : 'no se encoló');
    }

    // El aviso de respaldo (el mailable de siempre) también.
    $respaldo = (new \App\Mail\ActivityPublished($perm))->render();
    $di('correo de respaldo de «publicada»', str_contains($respaldo, $largo));
} finally {
    DB::rollBack();
    Cache::forget(Setting::CACHE_KEY);
}

echo PHP_EOL."{$ok} bien, {$mal} mal".PHP_EOL;
