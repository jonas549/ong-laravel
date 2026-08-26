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
 * Aviso de que un administrador cambió la contraseña de esta cuenta.
 *
 * **La contraseña nueva no viaja en el correo.** Un correo no es un canal
 * seguro: se queda en el buzón, se reenvía y se indexa. El administrador se la
 * entrega a la persona por donde corresponda; este mensaje sólo sirve para que
 * el titular se entere de que su cuenta cambió, que es lo que convierte un
 * cambio legítimo en algo auditable y uno ilegítimo en algo detectable.
 */
class ContrasenaCambiadaPorAdmin extends Mailable implements ShouldQueue
{
    use Queueable, RegistraEnvio, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $timeout = 60;

    public function __construct(
        public string $nombre,
        public string $adminNombre,
        public string $enlaceAcceso,
        public string $enlaceRecuperar,
    ) {
        $this->marcarEnvio();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cambiamos la contraseña de tu cuenta · '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contrasena-admin');
    }
}
