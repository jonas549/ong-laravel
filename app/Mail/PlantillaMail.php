<?php

namespace App\Mail;

use App\Mail\Concerns\RegistraEnvio;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Envía una plantilla editable del panel.
 *
 * Va a la cola: un envío SMTP lento no debe dejar esperando a quien acaba de
 * inscribirse. Si el servidor de correo falla, se reintenta con espera
 * creciente en vez de perderse.
 */
class PlantillaMail extends Mailable implements ShouldQueue
{
    use Queueable, RegistraEnvio, SerializesModels;

    /** Cabeceras propias para que el log sepa de dónde salió cada correo. */
    public const CAB_PLANTILLA = 'X-DPS-Plantilla';
    public const CAB_TIPO = 'X-DPS-Relacionado-Tipo';
    public const CAB_ID = 'X-DPS-Relacionado-Id';

    /** Tres intentos: la mayoría de fallos de SMTP son pasajeros. */
    public int $tries = 3;

    /** Un minuto, cinco y quince: da margen a que el servidor se recupere. */
    public array $backoff = [60, 300, 900];

    /** Que un correo atascado no bloquee la cola. */
    public int $timeout = 60;

    private array $render;

    /**
     * @param  array<string, string|null>  $datos
     * @param  array<int, string>  $adjuntos  Rutas relativas en el disco público.
     */
    public function __construct(
        public EmailTemplate $plantilla,
        public array $datos = [],
        public array $adjuntos = [],
        public ?Model $relacionado = null,
    ) {
        $this->marcarEnvio();

        $this->render = app(EmailTemplateRenderer::class)->render($plantilla, $datos);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->render['asunto']);
    }

    public function headers(): Headers
    {
        $texto = [self::CAB_PLANTILLA => $this->plantilla->clave];

        if ($this->relacionado) {
            $texto[self::CAB_TIPO] = $this->relacionado::class;
            $texto[self::CAB_ID] = (string) $this->relacionado->getKey();
        }

        return new Headers(text: $texto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.plantilla', with: [
            'cuerpo' => $this->render['html'],
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return collect($this->adjuntos)
            ->filter(fn ($ruta) => Storage::disk('public')->exists($ruta))
            ->map(fn ($ruta) => Attachment::fromStorageDisk('public', $ruta))
            ->values()
            ->all();
    }
}
