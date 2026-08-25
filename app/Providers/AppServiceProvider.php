<?php

namespace App\Providers;

use App\Mail\PasswordResetLink;
use App\Services\SmtpConfigService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * La configuración SMTP del panel se aplica desde el middleware `web`,
         * pero los correos van a la cola: quien los envía de verdad es el
         * worker, un proceso de consola que nunca pasa por ahí. Sin esto, todo
         * el correo saldría por el .env ignorando lo que configure la ONG.
         */
        Queue::before(fn () => app(SmtpConfigService::class)->aplicar());

        // El correo de recuperación usa el layout del sitio, no la plantilla
        // en inglés que trae el framework.
        ResetPassword::toMailUsing(function (object $usuario, string $token) {
            $correo = $usuario->getEmailForPasswordReset();
            $broker = config('auth.defaults.passwords');

            return (new PasswordResetLink(
                enlace: route('password.reset', ['token' => $token, 'email' => $correo]),
                minutos: (int) config("auth.passwords.{$broker}.expire", 60),
            ))->to($correo);
        });
    }
}
