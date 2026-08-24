<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * El botón "enviar correo de prueba" del panel.
 *
 * Devuelve el error crudo del servidor SMTP cuando falla: es lo único
 * realmente útil para diagnosticar por qué no sale el correo.
 */
class MailTestService
{
    public function __construct(private SmtpConfigService $smtp)
    {
    }

    /** @return array{ok: bool, mensaje: string, detalle: ?string} */
    public function enviar(string $destino): array
    {
        $usandoBd = $this->smtp->aplicar();

        try {
            Mail::to($destino)->send(new \App\Mail\TestMail());

            return [
                'ok' => true,
                'mensaje' => $usandoBd
                    ? "Correo de prueba enviado a {$destino} usando la configuración del panel."
                    : "Correo de prueba enviado a {$destino} usando la configuración del archivo .env "
                        . '(la del panel está desactivada).',
                'detalle' => null,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'mensaje' => 'No se pudo enviar el correo de prueba.',
                'detalle' => $e->getMessage(),
            ];
        }
    }

    public function remitente(): string
    {
        return (string) (Setting::get('smtp_from_address') ?: config('mail.from.address'));
    }
}
