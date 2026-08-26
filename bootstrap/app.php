<?php

use App\Http\Middleware\ApplySmtpSettings;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SoloInvitados;
use App\Listeners\LogSentMail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // La configuración SMTP vive en la base de datos, no en el .env,
        // así que hay que aplicarla en cada request antes de enviar nada.
        $middleware->appendToGroup('web', ApplySmtpSettings::class);

        $middleware->alias([
            'role' => EnsureRole::class,
            'invitado' => SoloInvitados::class,
        ]);

        // Hay dos logins distintos (panel y organizador) y ninguna ruta se
        // llama "login", que es la que el middleware `auth` busca por defecto.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('account.login'));
    })
    ->withEvents(discover: false)
    ->withExceptions(function (Exceptions $exceptions) {
        /*
         * El enlace de verificación caduca a los 60 minutos, y abrir el correo
         * al día siguiente es lo normal. Sin esto se llegaba a un 403 en inglés
         * del framework, sin ninguna salida. Ahora se vuelve a la pantalla de
         * "revisa tu correo", que trae el botón de reenviar.
         */
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if (! $request->routeIs('verification.verify')) {
                return null;
            }

            return $request->user()
                ? redirect()->route('verification.notice')
                    ->with('error', 'Ese enlace ya caducó. Pulsa "Reenviar el correo" y te mandamos otro.')
                : redirect()->route('account.login')
                    ->with('error', 'Ese enlace ya caducó. Entra en tu cuenta y te enviamos otro.');
        });
    })
    ->booted(function ($app) {
        // Registro de correos: al engancharse a los eventos del mailer,
        // captura todo lo que sale, incluido el reset de contraseña.
        $events = $app['events'];
        $events->listen(MessageSending::class, [LogSentMail::class, 'sending']);
        $events->listen(MessageSent::class, [LogSentMail::class, 'sent']);

        /*
         * Y al de la cola. Todo el correo de este sistema es ShouldQueue, así
         * que entre "el usuario pulsó el botón" y "el mailer lo entregó" hay un
         * hueco que dura para siempre si el worker no corre. Sin esto, ese
         * correo no aparecía en ninguna pantalla: desaparecía en silencio.
         */
        $events->listen(JobQueued::class, [LogSentMail::class, 'encolado']);
    })
    ->create();
