<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Aplica en caliente la configuración SMTP guardada en la base de datos.
 *
 * Se ejecuta desde un middleware en cada request. Si el ajuste `smtp_activo`
 * está apagado o falta el host, no toca nada y el sistema sigue usando lo que
 * diga el .env — así el sitio nunca queda sin correo por una config a medias.
 */
class SmtpConfigService
{
    public function aplicar(): bool
    {
        if (! Setting::get('smtp_activo', false)) {
            return false;
        }

        $host = Setting::get('smtp_host');

        if (blank($host)) {
            return false;
        }

        $encryption = Setting::get('smtp_encryption', 'tls');

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $host,
            'port' => (int) Setting::get('smtp_port', 587),
            'encryption' => in_array($encryption, ['tls', 'ssl'], true) ? $encryption : null,
            'username' => Setting::get('smtp_username') ?: null,
            'password' => Setting::get('smtp_password') ?: null,
            'timeout' => 15,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);

        $from = Setting::get('smtp_from_address');

        if (filled($from)) {
            Config::set('mail.from', [
                'address' => $from,
                'name' => Setting::get('smtp_from_name', config('app.name')),
            ]);
        }

        // El mailer ya resuelto queda con la config vieja; hay que descartarlo.
        Mail::purge('smtp');

        return true;
    }
}
