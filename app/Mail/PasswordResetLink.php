<?php

namespace App\Mail;

use App\Mail\Concerns\RegistraEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El correo de "¿Olvidaste tu contraseña?".
 *
 * Reemplaza la notificación por defecto de Laravel, que llega en inglés y
 * con la plantilla genérica del framework, para que use el mismo layout
 * que el resto de los avisos del sitio.
 */
class PasswordResetLink extends Mailable implements ShouldQueue
{
    use Queueable, RegistraEnvio, SerializesModels;

    /** Tres intentos con espera creciente: casi todo fallo de SMTP es pasajero. */
    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public function __construct(
        public string $enlace,
        public int $minutos,
    ) {
        $this->marcarEnvio();

    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recupera tu contraseña');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }
}
