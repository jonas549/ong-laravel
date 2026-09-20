<?php

namespace App\Mail;

use App\Mail\Concerns\RegistraEnvio;
use App\Models\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa a la ONG de que una actividad volvió corregida de «necesita ajustes».
 *
 * Es el único correo del proyecto que va de la organización hacia la ONG, y
 * por eso no está en `EmailTemplate::CATALOGO`: las plantillas editables son
 * las que la ONG le escribe a sus organizaciones, y ésta es una notificación
 * interna. Si algún día quiere redactarla, se migra como se migró la de
 * «actividad publicada».
 */
class ActivityResubmitted extends Mailable implements ShouldQueue
{
    use Queueable, RegistraEnvio, SerializesModels;

    /** Tres intentos con espera creciente: casi todo fallo de SMTP es pasajero. */
    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public function __construct(public Activity $actividad, public ?string $mensaje = null)
    {
        $this->marcarEnvio();
    }

    public function envelope(): Envelope
    {
        /*
         * El título va en el asunto y no sólo en el cuerpo: quien modera recibe
         * varios de estos al día y desde la bandeja tiene que poder distinguir
         * cuál es cuál sin abrirlos.
         */
        return new Envelope(subject: 'Volvió corregida: '.$this->actividad->titulo);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.activity.resubmitted', with: [
            'actividad' => $this->actividad,
            'mensaje' => $this->mensaje,
        ]);
    }
}
