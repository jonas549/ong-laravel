<?php

/**
 * Varias cuentas por organización — la lógica, sin navegador.
 *
 *   0. la migración dejó cada cuenta en su organización y cada actividad con autor
 *   1. el interruptor: apagado no admite cuentas nuevas en una con cuenta
 *   2. enlazar: la primera es principal, las siguientes se suman
 *   3. de quién es cada actividad (`responsable()` y `deLaCuenta()`)
 *   4. permisos: cada cuenta sólo lo suyo; la ficha, sólo la principal
 *   5. aprobación automática por cuenta
 *   6. correos: al autor; el aviso de cuenta sumada a la principal o al equipo
 *
 * Todo dentro de una transacción que se deshace al terminar: no deja nada,
 * ni filas en `jobs`.
 *
 *     php artisan tinker --execute="require base_path('pruebas/varias-cuentas.php');"
 */

use App\Models\Activity;
use App\Models\EmailLog;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityModerationService;
use App\Services\AprobacionAutomatica;
use App\Services\CorreoTransaccional;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

$ok = 0;
$mal = 0;
$di = function (string $que, bool $bien, string $extra = '') use (&$ok, &$mal) {
    $bien ? $ok++ : $mal++;
    echo '  '.str_pad($que, 70).' '.($bien ? 'OK' : '*** MAL ***').' '.$extra.PHP_EOL;
};

$ajuste = function (string $clave, mixed $valor) {
    Setting::set($clave, $valor);
    Cache::forget(Setting::CACHE_KEY);
};

$aprobacion = app(AprobacionAutomatica::class);
$correos = app(CorreoTransaccional::class);
$moderacion = app(ActivityModerationService::class);
$ultimo = fn () => (int) EmailLog::max('id');
$sello = uniqid();

$cuenta = fn (string $nombre) => User::create([
    'name' => $nombre,
    'email' => "varias.{$nombre}.{$sello}@ejemplo.cl",
    'password' => 'una-clave-cualquiera',
    'role' => User::ROL_ORGANIZER,
    'is_active' => true,
]);

DB::beginTransaction();

try {
    echo PHP_EOL.'=== 0 · Lo que dejó la migración ==='.PHP_EOL.PHP_EOL;

    $descolgadas = Organization::whereNotNull('user_id')
        ->whereHas('user', fn ($q) => $q->where(fn ($w) => $w->whereNull('organization_id')->orWhereColumn('organization_id', '!=', 'organizations.id')))
        ->count();
    $di('cada principal es de su organización', $descolgadas === 0, $descolgadas.' descolgada(s)');

    $sinAutor = Activity::withTrashed()
        ->whereNull('user_id')
        ->whereHas('organization', fn ($q) => $q->whereNotNull('user_id'))
        ->count();
    $di('no hay actividades sin autor en organizaciones con cuenta', $sinAutor === 0, (string) $sinAutor);

    $di('el interruptor existe y nace apagado', Setting::where('clave', 'organizacion_varias_cuentas')->value('valor') === '0');

    echo PHP_EOL.'=== 1 · El interruptor ==='.PHP_EOL.PHP_EOL;

    $principal = $cuenta('principal');
    $org = Organization::create(['nombre' => "Fundación Varias {$sello}", 'tipo' => 'Organización sin fines de lucro', 'activo' => true]);
    $di('la primera cuenta que llega queda como principal', $org->enlazarCuenta($principal) === false
        && $org->fresh()->user_id === $principal->id && $principal->fresh()->organization_id === $org->id);

    $ajuste('organizacion_varias_cuentas', '0');
    $di('apagado: no admite cuenta nueva', ! $org->fresh()->admiteCuentaNueva());
    $di('apagado: la consulta tampoco la ofrece', Organization::admitenCuentaNueva()->whereKey($org->id)->doesntExist());
    $sug = Organization::buscarPorNombre("Varias {$sello}")->firstWhere('id', $org->id);
    $di('apagado: el buscador la da como tomada', $sug && ! $sug['libre'] && ! $sug['sumable']);

    $ajuste('organizacion_varias_cuentas', '1');
    $di('encendido: admite cuenta nueva', $org->fresh()->admiteCuentaNueva());
    $sug = Organization::buscarPorNombre("Varias {$sello}")->firstWhere('id', $org->id);
    $di('encendido: el buscador la da como sumable', $sug && $sug['sumable']);
    $sug = Organization::buscarPorNombre("Varias {$sello}", $principal->fresh())->firstWhere('id', $org->id);
    $di('a su propia cuenta se la marca como propia', $sug && $sug['propia']);

    $org->update(['activo' => false]);
    $di('desactivada no admite a nadie, ni encendido', ! $org->fresh()->admiteCuentaNueva());
    $org->update(['activo' => true]);

    echo PHP_EOL.'=== 2 · Sumarse ==='.PHP_EOL.PHP_EOL;

    $sumada = $cuenta('sumada');
    $di('la segunda se suma (devuelve true)', $org->fresh()->enlazarCuenta($sumada) === true);
    $di('y la principal no cambia', $org->fresh()->user_id === $principal->id);
    $di('la sumada es de la organización', $sumada->fresh()->organization_id === $org->id);
    $di('la sumada no es principal', ! $sumada->fresh()->esPrincipal());
    $di('la principal sí', $principal->fresh()->esPrincipal());
    $di('la organización tiene dos cuentas', $org->cuentas()->count() === 2);

    $ajuste('organizacion_varias_cuentas', '0');
    $di('apagar el interruptor no saca a la sumada', $sumada->fresh()->organization_id === $org->id);
    $ajuste('organizacion_varias_cuentas', '1');

    echo PHP_EOL.'=== 3 · De quién es cada actividad ==='.PHP_EOL.PHP_EOL;

    $plantilla = Activity::whereNotNull('published_at')->firstOrFail();
    $nueva = function (?User $autor, string $estado = 'borrador', bool $publicada = false) use ($org, $plantilla) {
        $a = $plantilla->replicate();
        $a->forceFill([
            'organization_id' => $org->id,
            'user_id' => $autor?->id,
            'titulo' => 'Prueba varias cuentas '.uniqid(),
            'slug' => 'prueba-varias-cuentas-'.uniqid(),
            'estado' => $estado,
            'published_at' => $publicada ? now() : null,
            'publicada_automaticamente' => false,
        ])->save();

        return $a->fresh();
    };

    $dePrincipal = $nueva($principal);
    $deSumada = $nueva($sumada);
    $sinAutor = $nueva(null);

    $di('la de la principal es de la principal', $dePrincipal->responsable()?->id === $principal->id);
    $di('la de la sumada es de la sumada', $deSumada->responsable()?->id === $sumada->id);
    $di('la que no tiene autor es de la principal', $sinAutor->responsable()?->id === $principal->id);

    $ids = fn (User $u) => Activity::deLaCuenta($u->fresh())->pluck('id')->sort()->values()->all();
    $di('la principal ve la suya y la sin autor, no la de la sumada',
        $ids($principal) === collect([$dePrincipal->id, $sinAutor->id])->sort()->values()->all());
    $di('la sumada ve sólo la suya', $ids($sumada) === [$deSumada->id]);

    echo PHP_EOL.'=== 4 · Permisos ==='.PHP_EOL.PHP_EOL;

    $puede = fn (User $u, string $que, $cosa) => Gate::forUser($u->fresh())->inspect($que, $cosa);

    $di('la sumada edita la suya', $puede($sumada, 'update', $deSumada)->allowed());
    $r = $puede($sumada, 'update', $dePrincipal);
    $di('la sumada NO edita la de la principal', $r->denied(), (string) $r->message());
    $di('…y el mensaje dice que es de otra cuenta de su organización', str_contains((string) $r->message(), 'otra cuenta de tu organización'));
    $di('la principal NO edita la de la sumada', $puede($principal, 'update', $deSumada)->denied());
    $di('la sumada no ve los inscritos de la principal', $puede($sumada, 'manageParticipants', $dePrincipal)->denied());
    $di('la sumada no ve las evaluaciones de la principal', $puede($sumada, 'viewEvaluations', $dePrincipal)->denied());
    $di('la sumada no ve una no publicada de la principal', $puede($sumada, 'view', $dePrincipal)->denied());
    $ajena = Activity::where('organization_id', '!=', $org->id)->firstOrFail();
    $r = $puede($principal, 'update', $ajena);
    $di('una de otra organización sigue cerrada, con su mensaje', $r->denied() && str_contains((string) $r->message(), 'no es de tu organización'));
    $admin = User::where('role', 'admin')->firstOrFail();
    $di('el administrador las edita todas', $puede($admin, 'update', $deSumada)->allowed() && $puede($admin, 'update', $dePrincipal)->allowed());

    $di('la ficha: la principal la edita', $puede($principal, 'update', $org)->allowed());
    $r = $puede($sumada, 'update', $org);
    $di('la ficha: la sumada no', $r->denied(), (string) $r->message());
    $di('la ficha: la sumada sí la ve', Gate::forUser($sumada->fresh())->allows('view', $org));
    $di('el wizard no le pregunta nada de la ficha a la sumada', $org->fresh()->faltanEnElPaso3(null, $sumada->fresh()) === []
        && $org->fresh()->fichaParaElWizard($sumada->fresh())['soloLectura'] === true);
    $di('a la principal sí, si le falta algo (el logo)', in_array('org_logo', $org->fresh()->faltanEnElPaso3(null, $principal->fresh()), true));

    // Sacar a la sumada de la organización: sus actividades pasan a la principal.
    $sumada->forceFill(['organization_id' => null])->save();
    $di('sacada de la organización, su actividad pasa a la principal', $deSumada->fresh()->responsable()?->id === $principal->id);
    $di('…y la principal la ve en su listado', in_array($deSumada->id, $ids($principal), true));
    $di('…y la sacada ya no la edita', $puede($sumada, 'update', $deSumada)->denied());
    $sumada->forceFill(['organization_id' => $org->id])->save();
    $di('si vuelve, la recupera', $deSumada->fresh()->responsable()?->id === $sumada->id);

    // Borrada en blando: igual.
    $sumada->delete();
    $di('borrada la sumada, su actividad pasa a la principal', $deSumada->fresh()->responsable()?->id === $principal->id);
    $sumada->restore();

    echo PHP_EOL.'=== 5 · Aprobación automática, por cuenta ==='.PHP_EOL.PHP_EOL;

    $ajuste('aprobacion_automatica', '1');
    $ajuste('aprobacion_automatica_desde', '1');

    $motivo = fn (Activity $a) => $aprobacion->motivoDeRevision($a->fresh());

    $di('umbral 1: la primera de la principal se revisa', $motivo($dePrincipal) === 'es la primera actividad de esta cuenta', (string) $motivo($dePrincipal));
    $nueva($principal, 'publicada', true);
    $di('umbral 1: con una publicada, la principal publica sola', $motivo($dePrincipal) === null, (string) $motivo($dePrincipal));
    $m = $motivo($deSumada);
    $di('umbral 1: la sumada NO hereda la confianza de la organización', $m !== null, (string) $m);
    $di('…y el motivo dice que es una cuenta sumada', str_contains((string) $m, 'se sumó'));

    $ajuste('aprobacion_automatica_desde', '0');
    $m = $motivo($deSumada);
    $di('umbral 0: la primera de la sumada se revisa IGUAL', $m !== null, (string) $m);

    $nueva($sumada, 'publicada', true);
    $di('umbral 0: con una publicada, la sumada ya publica sola', $motivo($deSumada) === null, (string) $motivo($deSumada));
    $ajuste('aprobacion_automatica_desde', '1');
    $di('umbral 1: con una publicada, la sumada publica sola', $motivo($deSumada) === null);

    $ajuste('aprobacion_automatica_desde', '2');
    $di('umbral 2: una publicada no basta para la sumada', $motivo($deSumada) !== null, (string) $motivo($deSumada));
    $ajuste('aprobacion_automatica_desde', '1');

    $org->update(['requiere_revision' => true]);
    $di('«revisión siempre» de la organización alcanza a la sumada', $motivo($deSumada) === 'esta organización está marcada para revisión siempre');
    $org->update(['requiere_revision' => false]);

    $enAjustes = $nueva($principal, 'ajustes');
    $di('unos ajustes pendientes de la principal pausan también a la sumada', $motivo($deSumada) === 'la organización tiene una actividad esperando correcciones');
    $enAjustes->forceFill(['estado' => 'cancelada'])->save();

    [$estado] = $aprobacion->estadoAlEnviar($deSumada->fresh());
    $di('estadoAlEnviar con la actividad: publicada', $estado === 'publicada');
    $di('con una organización a secas cuenta su principal (lo de antes)', $aprobacion->cuantasPublico($org->fresh()) === $aprobacion->cuantasPublico($principal->fresh()));

    echo PHP_EOL.'=== 6 · Correos ==='.PHP_EOL.PHP_EOL;

    $buzon = 'avisos.varias.'.$sello.'@ejemplo.cl';
    $ajuste('avisos_email', $buzon);

    $desde = $ultimo();
    $moderacion->cambiar($deSumada->fresh(), 'publicada', $admin);
    $publicada = EmailLog::where('id', '>', $desde)->where('plantilla', 'actividad_publicada')->where('related_id', $deSumada->id)->first();
    $di('«tu actividad está publicada» va a la sumada, que la creó', $publicada?->to === $sumada->email, (string) $publicada?->to);
    $guia = EmailLog::where('id', '>', $desde)->where('plantilla', 'guia_organizador')->where('related_id', $deSumada->id)->first();
    $di('la guía también va a la sumada (si hay enlace configurado)',
        $guia === null ? blank(Setting::get('guia_organizador_url')) : $guia->to === $sumada->email, (string) $guia?->to);

    $desde = $ultimo();
    $moderacion->cambiar($dePrincipal->fresh(), 'ajustes', $admin, 'Falta la dirección completa.');
    $ajustes = EmailLog::where('id', '>', $desde)->where('to', $principal->email)->exists();
    $di('«necesita ajustes» de la de la principal va a la principal', $ajustes);
    $di('…y no a la sumada', EmailLog::where('id', '>', $desde)->where('to', $sumada->email)->doesntExist());

    $inscripcion = $deSumada->registrations()->create([
        'nombre' => 'Persona de prueba',
        'correo' => "inscrita.{$sello}@ejemplo.cl",
        'es_mayor_edad' => true,
        'estado' => 'pendiente',
        'token' => \Illuminate\Support\Str::random(40),
    ]);
    $desde = $ultimo();
    $correos->nuevaInscripcion($inscripcion->fresh());
    $aviso = EmailLog::where('id', '>', $desde)->where('plantilla', 'nueva_inscripcion')->first();
    $di('el aviso de nueva inscripción va a la sumada', $aviso?->to === $sumada->email, (string) $aviso?->to);

    /*
     * El registro de correos no guarda el cuerpo al encolar, sólo al enviar.
     * Para leer qué dice, se encola contra `Mail::fake()` y se pinta.
     */
    $cuerpo = function (callable $enviar, string $plantilla) {
        $real = Illuminate\Support\Facades\Mail::getFacadeRoot();
        Illuminate\Support\Facades\Mail::fake();
        $enviar();
        $m = Illuminate\Support\Facades\Mail::queued(App\Mail\PlantillaMail::class)
            ->first(fn ($m) => $m->plantilla->clave === $plantilla);
        $html = $m ? $m->render() : '';
        Illuminate\Support\Facades\Mail::swap($real);

        return $html;
    };

    $html = $cuerpo(fn () => $correos->equipoActividadEditada($deSumada->fresh()), 'equipo_actividad_editada');
    $di('el aviso al equipo lleva el correo de quien la registró', str_contains($html, $sumada->email));

    $otra = $cuenta('otra');
    $org->fresh()->enlazarCuenta($otra);
    $desde = $ultimo();
    $correos->cuentaSumada($otra->fresh('organization'));
    $sumadaAviso = EmailLog::where('id', '>', $desde)->where('plantilla', 'cuenta_sumada')->get();
    $di('«se sumó una cuenta» va a la principal', $sumadaAviso->count() === 1 && $sumadaAviso->first()->to === $principal->email,
        $sumadaAviso->pluck('to')->implode(', '));
    $html = $cuerpo(fn () => $correos->cuentaSumada($otra->fresh('organization')), 'cuenta_sumada');
    $di('…y dice quién se sumó y a qué organización', str_contains($html, $otra->email) && str_contains($html, $org->nombre));
    $di('…y dónde avisar si no la conoce', str_contains($html, 'escríbenos a') && ! str_contains($html, '{{'));

    $principal->update(['is_active' => false]);
    $desde = $ultimo();
    $correos->cuentaSumada($otra->fresh('organization'));
    $alEquipo = EmailLog::where('id', '>', $desde)->where('plantilla', 'cuenta_sumada')->get();
    $di('principal desactivada: el aviso va al equipo', $alEquipo->count() === 1 && $alEquipo->first()->to === $buzon,
        $alEquipo->pluck('to')->implode(', '));
    $html = $cuerpo(fn () => $correos->cuentaSumada($otra->fresh('organization')), 'cuenta_sumada');
    $di('…y dice por qué le llega', str_contains($html, 'no tiene una cuenta principal activa'));
    $di('la organización sigue funcionando para la sumada', $deSumada->fresh()->responsable()?->id === $sumada->id);
    $principal->update(['is_active' => true]);
} finally {
    DB::rollBack();
    Cache::forget(Setting::CACHE_KEY);
}

echo PHP_EOL."  {$ok} bien, {$mal} mal".PHP_EOL;
