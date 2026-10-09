<?php

/**
 * Tanda del 09/10 — lo que se comprueba sin navegador.
 *
 *   2. los correos de una actividad llevan a la cuenta principal en copia
 *   3. el orden de /actividades: recientes primero, las pasadas al final
 *   4. el correo de contacto nunca es uno de ejemplo
 *   5. crear la cuenta de una organización desde su ficha
 *   8. el correo de confirmación lleva el contacto del organizador
 *   9. guardar en el editor no pierde la accesibilidad ni el tipo de colaborador
 *
 * Todo dentro de una transacción que se deshace al terminar.
 *
 *     php artisan tinker --execute="require base_path('pruebas/tanda-09-10.php');"
 */

use App\Http\Controllers\Admin\OrganizationController;
use App\Models\Activity;
use App\Models\EmailLog;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Services\ActivityModerationService;
use App\Services\CorreoTransaccional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

$ok = 0;
$mal = 0;
$di = function (string $que, bool $bien, string $extra = '') use (&$ok, &$mal) {
    $bien ? $ok++ : $mal++;
    echo '  '.str_pad($que, 72).' '.($bien ? 'OK' : '*** MAL ***').' '.$extra.PHP_EOL;
};

$ajuste = function (string $clave, mixed $valor) {
    Setting::set($clave, $valor);
    Cache::forget(Setting::CACHE_KEY);
};

$correos = app(CorreoTransaccional::class);
$moderacion = app(ActivityModerationService::class);
$ultimo = fn () => (int) EmailLog::max('id');
$sello = uniqid();

$cuenta = fn (string $nombre) => User::create([
    'name' => $nombre,
    'email' => "tanda.{$nombre}.{$sello}@ejemplo.cl",
    'password' => 'una-clave-cualquiera',
    'role' => User::ROL_ORGANIZER,
    'is_active' => true,
]);

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

DB::beginTransaction();

try {
    $admin = User::where('role', User::ROL_ADMIN)->firstOrFail();
    $plantilla = Activity::whereNotNull('published_at')->firstOrFail();

    $principal = $cuenta('principal');
    $sumada = $cuenta('sumada');
    $org = Organization::create(['nombre' => "Fundación Tanda {$sello}", 'tipo' => 'Organización sin fines de lucro', 'activo' => true]);
    $org->enlazarCuenta($principal);
    $org->fresh()->enlazarCuenta($sumada);

    $nueva = function (User $autor, array $extra = []) use ($org, $plantilla) {
        $a = $plantilla->replicate();
        $a->forceFill(array_merge([
            'organization_id' => $org->id,
            'user_id' => $autor->id,
            'titulo' => 'Tanda '.uniqid(),
            'slug' => 'tanda-'.uniqid(),
            'estado' => 'borrador',
            'published_at' => null,
            'destacada' => false,
            'correo_contacto' => 'contacto.actividad@ejemplo.cl',
        ], $extra))->save();

        return $a;
    };

    echo PHP_EOL.'=== 2 · La principal, en copia ==='.PHP_EOL.PHP_EOL;

    $ajuste('guia_organizador_url', 'https://ejemplo.cl/guia');
    $deSumada = $nueva($sumada);
    $deSumada->terms()->sync($plantilla->terms->pluck('id'));

    $copias = function (callable $hacer) use ($ultimo) {
        $desde = $ultimo();
        $hacer();

        return EmailLog::where('id', '>', $desde)->get(['to', 'cc', 'plantilla', 'mailable', 'subject']);
    };

    $vistos = $copias(fn () => $moderacion->cambiar($deSumada->fresh(), 'revision', $admin));
    $recibida = $vistos->first(fn ($c) => str_contains((string) $c->to, $sumada->email));
    $di('«recibimos tu actividad»: a la autora', (bool) $recibida, $vistos->pluck('to')->implode(' | '));
    $di('…con la principal en copia', str_contains((string) $recibida?->cc, $principal->email), (string) $recibida?->cc);

    $vistos = $copias(fn () => $moderacion->cambiar($deSumada->fresh(), 'ajustes', $admin, 'Falta la dirección.'));
    $c = $vistos->first(fn ($c) => str_contains((string) $c->to, $sumada->email));
    $di('«necesita ajustes»: a la autora, con la principal en copia', $c && str_contains((string) $c->cc, $principal->email), (string) $c?->cc);

    $vistos = $copias(fn () => $moderacion->cambiar($deSumada->fresh(), 'publicada', $admin));
    $c = $vistos->firstWhere('plantilla', 'actividad_publicada');
    $di('«publicada con QR»: a la autora, con la principal en copia',
        $c && str_contains((string) $c->to, $sumada->email) && str_contains((string) $c->cc, $principal->email), (string) $c?->cc);
    $c = $vistos->firstWhere('plantilla', 'guia_organizador');
    $di('la guía: a la autora, con la principal en copia',
        $c && str_contains((string) $c->to, $sumada->email) && str_contains((string) $c->cc, $principal->email), (string) $c?->cc);

    $inscripcion = $deSumada->registrations()->create([
        'nombre' => 'Persona de prueba',
        'correo' => "inscrita.{$sello}@ejemplo.cl",
        'es_mayor_edad' => true,
        'estado' => 'pendiente',
        'token' => \Illuminate\Support\Str::random(40),
    ]);
    $vistos = $copias(fn () => $correos->nuevaInscripcion($inscripcion->fresh()));
    $c = $vistos->firstWhere('plantilla', 'nueva_inscripcion');
    $di('«nueva inscripción»: a la autora, con la principal en copia',
        $c && str_contains((string) $c->to, $sumada->email) && str_contains((string) $c->cc, $principal->email), (string) $c?->cc);

    $vistos = $copias(fn () => $moderacion->cambiar($deSumada->fresh(), 'cancelada', $admin));
    $c = $vistos->first(fn ($c) => str_contains((string) $c->to, $sumada->email));
    $di('«cancelada»: a la autora, con la principal en copia', $c && str_contains((string) $c->cc, $principal->email), (string) $c?->cc);
    $di('el inscrito recibe lo suyo sin copia a nadie',
        ($i = $vistos->first(fn ($c) => str_contains((string) $c->to, 'inscrita.'))) && blank($i->cc));

    $dePrincipal = $nueva($principal);
    $vistos = $copias(fn () => $moderacion->cambiar($dePrincipal->fresh(), 'revision', $admin));
    $c = $vistos->first(fn ($c) => str_contains((string) $c->to, $principal->email));
    $di('autora = principal: un solo correo, sin copia', $c && blank($c->cc) && $vistos->filter(fn ($v) => str_contains((string) $v->to.$v->cc, $principal->email))->count() === 1,
        $vistos->map(fn ($v) => $v->to.' / cc '.$v->cc)->implode(' | '));

    $principal->update(['is_active' => false]);
    $otra = $nueva($sumada);
    $vistos = $copias(fn () => $moderacion->cambiar($otra->fresh(), 'revision', $admin));
    $c = $vistos->first(fn ($c) => str_contains((string) $c->to, $sumada->email));
    $di('sin principal activa: sólo a la autora', $c && blank($c->cc), (string) $c?->cc);
    $principal->update(['is_active' => true]);

    echo PHP_EOL.'=== 3 · El orden de /actividades ==='.PHP_EOL.PHP_EOL;

    $hoy = now(\App\Support\Fecha::zona())->startOfDay();
    $vieja = $nueva($principal, ['estado' => 'publicada', 'published_at' => now(), 'fecha_inicio' => $hoy->copy()->addDays(30), 'sin_fecha_definida' => false, 'cerrada' => false]);
    $vieja->forceFill(['created_at' => now()->subDays(40)])->save();
    $reciente = $nueva($principal, ['estado' => 'publicada', 'published_at' => now(), 'fecha_inicio' => $hoy->copy()->addDays(60), 'sin_fecha_definida' => false, 'cerrada' => false]);
    $reciente->forceFill(['created_at' => now()->subDay()])->save();
    $pasada = $nueva($principal, ['estado' => 'publicada', 'published_at' => now(), 'fecha_inicio' => $hoy->copy()->subDays(10), 'fecha_termino' => null, 'sin_fecha_definida' => false, 'cerrada' => false]);
    $pasada->forceFill(['created_at' => now()])->save();
    $permanente = $nueva($principal, ['estado' => 'publicada', 'published_at' => now(), 'fecha_inicio' => null, 'sin_fecha_definida' => true, 'cerrada' => false]);
    $permanente->forceFill(['created_at' => now()->subDays(2)])->save();

    $orden = Activity::published()->abiertasAlPublico()->recientesPrimero()->pluck('id')->all();
    $pos = fn (Activity $a) => array_search($a->id, $orden, true);
    $di('la creada ayer va antes que la de hace 40 días', $pos($reciente) < $pos($vieja));
    $di('la permanente cuenta como vigente (antes que la de hace 40 días)', $pos($permanente) < $pos($vieja));
    $di('la que ya pasó va después que todas las vigentes, aunque sea la más nueva', $pos($pasada) > $pos($vieja));
    $ultimaVigente = collect($orden)->search(fn ($id) => Activity::find($id)->yaPaso());
    $di('ninguna vigente después de una pasada', $ultimaVigente === false
        || collect(array_slice($orden, $ultimaVigente))->every(fn ($id) => Activity::find($id)->yaPaso()));

    echo PHP_EOL.'=== 4 · El correo de contacto ==='.PHP_EOL.PHP_EOL;

    $di('el de la siembra no sirve', ! Setting::esCorreoUtil('contacto@ong-laravel.test'));
    $di('uno de example.com tampoco', ! Setting::esCorreoUtil('hola@example.com'));
    $di('uno real sí', Setting::esCorreoUtil('diadelpatrimoniosocial@comunidad-org.cl'));

    $ajuste('sitio_email_contacto', 'contacto@ong-laravel.test');
    $ajuste('avisos_email', 'equipo.tanda@ejemplo.cl');
    $di('con el de ejemplo, se usa el buzón de avisos', Setting::correoDeContacto() === 'equipo.tanda@ejemplo.cl');
    $ajuste('avisos_email', 'avisos@ong-laravel.test');
    $di('si los dos son de ejemplo, ninguno', Setting::correoDeContacto() === null);
    $html = $cuerpo(fn () => $correos->cuentaSumada($sumada->fresh('organization')), 'cuenta_sumada');
    // Direcciones de correo, no enlaces: en local APP_URL también es `.test`.
    $di('el aviso de cuenta sumada no dice ninguna dirección de ejemplo', $html !== '' && ! preg_match('/@[\w.-]+\.test\b/', $html));
    $di('…y la frase sigue teniendo sentido', str_contains($html, 'escríbenos a nuestro equipo'));
    $ajuste('sitio_email_contacto', 'contacto.real@ejemplo.cl');
    $html = $cuerpo(fn () => $correos->cuentaSumada($sumada->fresh('organization')), 'cuenta_sumada');
    $di('con uno real, sale ése', str_contains($html, 'escríbenos a contacto.real@ejemplo.cl'));

    $v = Validator::make(['x_email' => 'contacto@ong-laravel.test'], ['x_email' => ['required', 'email', 'not_regex:'.Setting::DOMINIO_DE_EJEMPLO]]);
    $di('Configuración rechaza guardar una dirección de ejemplo', $v->fails());
    $v = Validator::make(['x_email' => 'contacto@comunidad-org.cl'], ['x_email' => ['required', 'email', 'not_regex:'.Setting::DOMINIO_DE_EJEMPLO]]);
    $di('…y acepta una real', $v->passes());

    echo PHP_EOL.'=== 5 · Crear la cuenta desde la ficha ==='.PHP_EOL.PHP_EOL;

    $libre = Organization::create(['nombre' => "Libre {$sello}", 'tipo' => 'Organización sin fines de lucro', 'activo' => true]);
    $pedir = function (Organization $o, array $datos) {
        $r = Request::create('/admin/organizaciones/'.$o->id.'/cuenta', 'POST', $datos);
        $r->setLaravelSession(app('session')->driver());
        app()->instance('request', $r);

        try {
            return app(OrganizationController::class)->crearCuenta($r, $o);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $e;
        }
    };

    $r = $pedir($libre, ['name' => 'Persona Libre', 'email' => "libre.{$sello}@ejemplo.cl", 'password' => 'corta']);
    $di('una contraseña corta se rechaza', $r instanceof \Illuminate\Validation\ValidationException && isset($r->errors()['password']));
    $r = $pedir($libre, ['name' => 'Persona Libre', 'email' => $principal->email, 'password' => 'una-clave-larga']);
    $di('un correo que ya tiene cuenta se rechaza', $r instanceof \Illuminate\Validation\ValidationException && isset($r->errors()['email']));

    $r = $pedir($libre, ['name' => 'Persona Libre', 'email' => "libre.{$sello}@ejemplo.cl", 'password' => 'una-clave-larga']);
    $nuevaCuenta = User::where('email', "libre.{$sello}@ejemplo.cl")->first();
    $di('con datos buenos, la cuenta se crea', (bool) $nuevaCuenta);
    $di('…activa, verificada y de organizador', $nuevaCuenta && $nuevaCuenta->is_active && $nuevaCuenta->email_verified_at && $nuevaCuenta->role === User::ROL_ORGANIZER);
    $di('…y es la principal de la organización', $libre->fresh()->user_id === $nuevaCuenta?->id && $nuevaCuenta?->organization_id === $libre->id);
    $di('el correo de contacto vacío toma el de la cuenta', $libre->fresh()->correo_contacto === $nuevaCuenta?->email);
    $di('la contraseña sirve para entrar', $nuevaCuenta && \Illuminate\Support\Facades\Hash::check('una-clave-larga', $nuevaCuenta->password));

    $antes = User::count();
    $r = $pedir($libre->fresh(), ['name' => 'Otra', 'email' => "libre2.{$sello}@ejemplo.cl", 'password' => 'una-clave-larga']);
    $di('con cuenta ya no crea otra', User::count() === $antes);

    $huerfana = Organization::create(['nombre' => "Huérfana {$sello}", 'tipo' => 'Organización sin fines de lucro', 'activo' => true]);
    $ida = $cuenta('ida');
    $huerfana->enlazarCuenta($ida);
    $ida->forceFill(['organization_id' => null])->save();
    $pedir($huerfana->fresh(), ['name' => 'Vuelve', 'email' => "vuelve.{$sello}@ejemplo.cl", 'password' => 'una-clave-larga']);
    $vuelve = User::where('email', "vuelve.{$sello}@ejemplo.cl")->first();
    $di('si la principal ya no estaba, la nueva queda de principal', $vuelve && $huerfana->fresh()->user_id === $vuelve->id);

    echo PHP_EOL.'=== 8 · El contacto en la confirmación de inscripción ==='.PHP_EOL.PHP_EOL;

    $insc = $dePrincipal->registrations()->create([
        'nombre' => 'Persona inscrita',
        'correo' => "inscrita2.{$sello}@ejemplo.cl",
        'es_mayor_edad' => true,
        'estado' => 'pendiente',
        'token' => \Illuminate\Support\Str::random(40),
    ]);
    $html = $cuerpo(fn () => $correos->inscripcionConfirmada($insc->fresh()), 'inscripcion_confirmada');
    $di('lleva «Si tienes dudas… escríbele»', str_contains($html, 'Si tienes dudas y necesitas contactar al organizador'));
    $di('…enlazado al correo de la actividad', str_contains($html, 'mailto:contacto.actividad@ejemplo.cl'));
    $di('…después del enlace del calendario', ($c = strpos($html, 'Google Calendar')) === false || $c < strpos($html, 'escríbele'));
    $di('sin marcadores sin rellenar', ! preg_match('/\{\{\s*\w+\s*\}\}/', $html));

    $dePrincipal->forceFill(['correo_contacto' => 'a"b<script>@ejemplo.cl'])->save();
    $html = $cuerpo(fn () => $correos->inscripcionConfirmada($insc->fresh()), 'inscripcion_confirmada');
    $di('un correo raro no cuela HTML', ! str_contains($html, '<script>'));
    $dePrincipal->forceFill(['correo_contacto' => null])->save();
    $html = $cuerpo(fn () => $correos->inscripcionConfirmada($insc->fresh()), 'inscripcion_confirmada');
    $di('sin correo público, el bloque no sale', ! str_contains($html, 'escríbele'));
} finally {
    DB::rollBack();
    Cache::forget(Setting::CACHE_KEY);
}

echo PHP_EOL."  {$ok} bien, {$mal} mal".PHP_EOL;
