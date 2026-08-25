<?php

namespace App\Mail;

use App\Models\Activity;
use App\Mail\Concerns\RegistraEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActivityPublished extends Mailable implements ShouldQueue
{
    use Queueable, RegistraEnvio, SerializesModels;

    /** Tres intentos con espera creciente: casi todo fallo de SMTP es pasajero. */
    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public function __construct(public Activity $actividad)
    {
        $this->marcarEnvio();

    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu actividad ya está publicada');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.activity.published', with: ['actividad' => $this->actividad]);
    }
}
