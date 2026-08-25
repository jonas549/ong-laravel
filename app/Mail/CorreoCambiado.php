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
 * Aviso a la dirección ANTERIOR de que el correo de la cuenta ha cambiado.
 *
 * Es el único correo que llega a una dirección que ya no está en la cuenta, y
 * ese es justo el punto: si alguien cambia el correo sin permiso, este mensaje
 * es la única forma que tiene la persona de enterarse.
 */
class CorreoCambiado extends Mailable implements ShouldQueue
{
    use Queueable, RegistraEnvio, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $timeout = 60;

    public function __construct(
        public string $nombre,
        public string $correoAnterior,
        public string $correoNuevo,
        public string $enlaceAyuda,
    ) {
        $this->marcarEnvio();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cambió el correo de tu cuenta · '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.correo-cambiado');
    }
}
