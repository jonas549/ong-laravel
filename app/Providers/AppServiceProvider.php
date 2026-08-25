<?php

namespace App\Providers;

use App\Services\SmtpConfigService;
use App\Support\Formulario;
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
    }
}
