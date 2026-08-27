<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\UserPolicy;
use App\Services\SmtpConfigService;
use App\Support\Formulario;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
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
         * Se registran a mano y no por el descubrimiento automático de Laravel.
         * Ese descubrimiento va por convención de nombres, así que renombrar un
         * modelo o mover una policy la desactiva **sin decir nada**: las
         * comprobaciones pasan a devolver «no hay policy» y todo queda abierto.
         * Escritas aquí, un nombre que no cuadre revienta al arrancar.
         */
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

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
    }
}
