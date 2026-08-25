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
 * Verificación de la dirección de correo.
 *
 * Reemplaza la notificación por defecto de Laravel, que llega en inglés y con
 * la plantilla genérica del framework.
 */
class VerificacionCorreo extends Mailable implements ShouldQueue
{
    use Queueable, RegistraEnvio, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $timeout = 60;

    public function __construct(
        public string $nombre,
        public string $enlace,
        public int $minutos,
    ) {
        $this->marcarEnvio();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirma tu correo · '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verificacion');
    }
}
