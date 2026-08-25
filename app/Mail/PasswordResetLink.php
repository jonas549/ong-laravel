<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
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
class PasswordResetLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $enlace,
        public int $minutos,
    ) {
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
