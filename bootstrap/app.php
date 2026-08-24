<?php

use App\Http\Middleware\ApplySmtpSettings;
use App\Http\Middleware\EnsureRole;
use App\Listeners\LogSentMail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

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
        ]);

        // Hay dos logins distintos (panel y organizador) y ninguna ruta se
        // llama "login", que es la que el middleware `auth` busca por defecto.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('account.login'));
    })
    ->withEvents(discover: false)
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->booted(function ($app) {
        // Registro de correos: al engancharse a los eventos del mailer,
        // captura todo lo que sale, incluido el reset de contraseña.
        $events = $app['events'];
        $events->listen(MessageSending::class, [LogSentMail::class, 'sending']);
        $events->listen(MessageSent::class, [LogSentMail::class, 'sent']);
    })
    ->create();
