<?php

namespace App\Providers;

use App\Mail\PasswordResetLink;
use Illuminate\Auth\Notifications\ResetPassword;
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
