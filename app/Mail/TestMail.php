<?php

namespace App\Mail;

use App\Mail\Concerns\RegistraEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El correo del botón "probar configuración" del panel.
 *
 * A propósito NO se encola, al revés que el resto. Es la única herramienta de
 * diagnóstico del panel: encolado siempre respondería "enviado" aunque el
 * servidor estuviera caído, porque el fallo ocurriría después, en el worker.
 * Se envía en caliente para poder devolver el error real del servidor.
 */
class TestMail extends Mailable
{
    use Queueable, RegistraEnvio, SerializesModels;

    public function __construct()
    {
        $this->marcarEnvio();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Correo de prueba · ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test');
    }
}
