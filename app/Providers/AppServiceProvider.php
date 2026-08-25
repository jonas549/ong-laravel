<?php

namespace App\Providers;

use App\Mail\PasswordResetLink;
use App\Mail\VerificacionCorreo;
use App\Services\SmtpConfigService;
use App\Support\Formulario;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Blade;
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

        /*
         * @viejo('campo') repinta un formulario como old(), pero sin morir si
         * llega `campo[]=x`: old() devolvería un array y {{ }} lo pasa a
         * htmlspecialchars, que revienta con un 500.
         */
        Blade::directive('viejo', fn (string $expresion) => '<?php echo e('.Formulario::class."::viejo({$expresion})); ?>");

        /*
         * Tanto la verificación de correo como la recuperación usan el layout
         * del sitio, no las plantillas en inglés que trae el framework.
         */
        VerifyEmail::toMailUsing(function (object $usuario, string $url) {
            return (new VerificacionCorreo(
                nombre: $usuario->name,
                enlace: $url,
                minutos: (int) config('auth.verification.expire', 60),
            ))->to($usuario->getEmailForVerification());
        });

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
