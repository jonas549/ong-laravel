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
     * Imágenes que viajan DENTRO del correo, **en base64**.
     *
     * @var array<string, array{datos: string, nombre: string, mime: string}>
     */
    public array $incrustadas = [];

    /**
     * @param  array<string, string|null>  $datos
     * @param  array<int, string>  $adjuntos  Rutas relativas en el disco público.
     * @param  array<string, array{datos: string, nombre: string, mime: string}>  $incrustadas
     *         Imágenes que viajan DENTRO del correo, con los bytes EN CRUDO. La
     *         clave es el nombre con el que se citan desde el cuerpo:
     *         `<img src="cid:qr">`.
     *
     *         Van incrustadas y no enlazadas a propósito. Una imagen enlazada
     *         necesita una URL pública —y la descarga del QR pide sesión— y
     *         además muchos clientes de correo no cargan imágenes remotas hasta
     *         que la persona lo autoriza. Incrustada llega con el correo y no
     *         depende de nada. La sustitución del `cid:` se hace en
     *         `resources/views/emails/plantilla.blade.php`.
     */
    public function __construct(
        public EmailTemplate $plantilla,
        public array $datos = [],
        public array $adjuntos = [],
        public ?Model $relacionado = null,
        array $incrustadas = [],
    ) {
        $this->marcarEnvio();

        /*
         * ── Por qué se guardan en base64 y no en crudo ──
         *
         * **Este mailable es `ShouldQueue`, y la cola serializa sus propiedades
         * a JSON.** Un PNG en crudo no es UTF-8 válido, así que `json_encode`
         * falla con «Malformed UTF-8 characters» y el correo no llega a
         * encolarse nunca. Y falla EN SILENCIO: `CorreoTransaccional` atrapa la
         * excepción, deja una línea en el log y devuelve false, de modo que el
         * organizador no recibe su aviso y nadie se entera.
         *
         * Que es, exactamente, el fallo mudo del bloque A con otra cara.
         *
         * En base64 el contenido es texto ASCII y la cola lo lleva sin
         * enterarse. El coste es un tercio más de tamaño sobre un PNG de 600
         * bytes: nada. **Si alguien "limpia" esto quitando el base64, el correo
         * de actividad publicada deja de salir.**
         */
        $this->incrustadas = array_map(
            fn (array $imagen) => ['datos' => base64_encode($imagen['datos'])] + $imagen,
            $incrustadas,
        );

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
            'incrustadas' => $this->incrustadas,
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
