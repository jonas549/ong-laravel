<?php

namespace App\Listeners;

use App\Mail\Concerns\RegistraEnvio;
use App\Mail\PlantillaMail;
use App\Models\EmailLog;
use App\Services\DiagnosticoCorreo;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Registra todo correo que sale del sistema.
 *
 * Se engancha a los eventos del propio mailer en vez de llamar a un logger en
 * cada punto de envío: así queda registrado también lo que no escribimos
 * nosotros, como el correo de recuperación de contraseña.
 *
 * Cada mailable trae un identificador propio (`envioUuid`, del trait
 * RegistraEnvio) que Laravel incluye en los datos de la vista. Sobre él se
 * apoya todo: la fila se crea una sola vez aunque haya reintentos, se marca
 * como enviada sin ambigüedad, y cuando el trabajo agota sus intentos el
 * propio mailable vuelve a esa fila para escribir el error de verdad.
 *
 * Dos cosas que este registro callaba y ahora no:
 *
 * 1. **Correo encolado.** Todo el correo de este sistema es ShouldQueue y la
 *    fila sólo nacía cuando el worker enviaba. Si el worker no corría, el
 *    correo no existía para el panel: ni enviado, ni fallido, ni nada. Ahora
 *    `encolado()` deja la fila en cuanto el trabajo entra en la cola.
 * 2. **Transporte que no entrega.** `log` y `array` terminan sin excepción, y
 *    eso se registraba como "Enviado". Ahora se guarda qué transporte lo llevó
 *    y, si no entrega a nadie, el estado es `no_entregado`.
 */
class LogSentMail
{
    /** Cabecera que usa el reenvío manual para no duplicar la fila. */
    public const CAB_REENVIO = 'X-DPS-Reenvio-De';

    /**
     * Un correo acaba de entrar en la cola.
     *
     * Se registra aquí y no al enviar porque entre encolar y enviar puede no
     * pasar nunca nada, y ese hueco es justo el que dejaba correos invisibles.
     */
    public function encolado(JobQueued $event): void
    {
        try {
            $trabajo = $event->job;

            if (! $trabajo instanceof SendQueuedMailable) {
                return;
            }

            $mailable = $trabajo->mailable;
            $uuid = $this->uuidDe($mailable);

            if ($uuid === null) {
                return;
            }

            EmailLog::updateOrCreate(['mensaje_uuid' => $uuid], [
                'to' => $this->direcciones($mailable->to ?? []),
                'cc' => $this->direcciones($mailable->cc ?? []) ?: null,
                'bcc' => $this->direcciones($mailable->bcc ?? []) ?: null,
                'subject' => $this->asuntoDe($mailable),
                'mailable' => $mailable::class,
                'plantilla' => $mailable instanceof PlantillaMail ? $mailable->plantilla->clave : null,
                'status' => EmailLog::EN_COLA,
                'error' => null,
                'encolado_at' => now(),
                'attempts' => 0,
            ]);
        } catch (Throwable) {
            // Nunca romper un envío por no poder registrarlo.
        }
    }

    /**
     * La fila pasa a `failed` provisional: si el envío funciona, MessageSent la
     * corrige, y si el SMTP revienta, MessageSent no llega nunca y queda
     * marcada como fallida, que es lo que hay que ver en el panel.
     */
    public function sending(MessageSending $event): void
    {
        try {
            $datos = $this->extraer($event->message, $event->data);

            // Reenvío manual: no se crea fila nueva, se actualiza la original.
            if ($origen = $datos['reenvio_de']) {
                EmailLog::whereKey($origen)->update([
                    'status' => EmailLog::ENVIADO,
                    'transporte' => $this->transporte(),
                    'error' => null,
                    'reenviado_at' => now(),
                ]);

                return;
            }

            unset($datos['reenvio_de']);

            if ($datos['mensaje_uuid']) {
                // Un reintento vuelve a pasar por aquí: se reutiliza la fila y
                // se cuenta el intento, en vez de dejar una fila por intento.
                $registro = EmailLog::firstOrNew(['mensaje_uuid' => $datos['mensaje_uuid']]);
                $veniaDeLaCola = $registro->exists && $registro->status === EmailLog::EN_COLA;

                $registro->fill($datos + [
                    'status' => EmailLog::FALLIDO,
                    'transporte' => $this->transporte(),
                    'error' => 'Envío iniciado sin confirmación del servidor.',
                ]);

                // La fila de la cola nace con attempts 0 y no cuenta como intento.
                $registro->attempts = $registro->exists && ! $veniaDeLaCola
                    ? $registro->attempts + 1
                    : 1;
                $registro->save();

                return;
            }

            EmailLog::create($datos + [
                'status' => EmailLog::FALLIDO,
                'transporte' => $this->transporte(),
                'error' => 'Envío iniciado sin confirmación del servidor.',
            ]);
        } catch (Throwable) {
            // Nunca romper un envío por no poder registrarlo.
        }
    }

    public function sent(MessageSent $event): void
    {
        try {
            $datos = $this->extraer($event->message, $event->data);

            if ($datos['reenvio_de']) {
                return;
            }

            $registro = $datos['mensaje_uuid']
                ? EmailLog::where('mensaje_uuid', $datos['mensaje_uuid'])->first()
                // Sin identificador (correo suelto) sólo queda la correlación
                // por destinatario y asunto, que es lo que había antes.
                : EmailLog::whereIn('status', [EmailLog::FALLIDO, EmailLog::NO_ENTREGADO])
                    ->where('to', $datos['to'])
                    ->where('subject', $datos['subject'])
                    ->latest('id')
                    ->first();

            if (! $registro) {
                return;
            }

            /*
             * Aquí está la diferencia entre lo que el mailer sabe y lo que pasó
             * de verdad. `log` y `array` llegan hasta este punto sin un solo
             * error, y hasta ahora eso se escribía como "Enviado". No lo es:
             * nadie recibió nada.
             */
            [$transporte, $entrega] = $this->transporteYEntrega();

            $registro->update([
                'status' => $entrega ? EmailLog::ENVIADO : EmailLog::NO_ENTREGADO,
                'transporte' => $transporte,
                'error' => $entrega
                    ? null
                    : "El mailer activo es «{$transporte}», que no entrega a nadie: el correo no salió del servidor.",
                'sent_at' => now(),
            ]);
        } catch (Throwable) {
            // Idem.
        }
    }

    /** Nombre corto del transporte que está activo ahora mismo. */
    private function transporte(): string
    {
        return $this->transporteYEntrega()[0];
    }

    /**
     * El transporte activo y si entrega de verdad.
     *
     * @return array{0: string, 1: bool}
     */
    private function transporteYEntrega(): array
    {
        try {
            $transporte = Mail::mailer(config('mail.default'))->getSymfonyTransport();

            return [
                (string) $transporte,
                ! in_array($transporte::class, DiagnosticoCorreo::TRANSPORTES_FALSOS, true),
            ];
        } catch (Throwable) {
            // Si no se puede resolver, no se inventa un veredicto: se da por
            // bueno lo que diga el mailer y se deja el transporte sin nombre.
            return ['', true];
        }
    }

    /** @param array<int, array{name: ?string, address: string}> $direcciones */
    private function direcciones(array $direcciones): string
    {
        return collect($direcciones)
            ->pluck('address')
            ->filter()
            ->implode(', ');
    }

    private function uuidDe(Mailable $mailable): ?string
    {
        if (! in_array(RegistraEnvio::class, class_uses_recursive($mailable), true)) {
            return null;
        }

        return $mailable->envioUuid !== '' ? $mailable->envioUuid : null;
    }

    /**
     * El asunto de un mailable antes de construirlo. Los que usan `envelope()`
     * lo tienen ahí; los que llaman a `->subject()` lo dejan en la propiedad.
     */
    private function asuntoDe(Mailable $mailable): ?string
    {
        try {
            if (method_exists($mailable, 'envelope')) {
                $asunto = $mailable->envelope()->subject;

                if (filled($asunto)) {
                    return $asunto;
                }
            }
        } catch (Throwable) {
            // Un envelope que necesite algo del contenedor puede fallar aquí;
            // el asunto real se rellenará al enviar.
        }

        return $mailable->subject ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extraer(Email $mensaje, array $data = []): array
    {
        $lista = fn (array $direcciones) => collect($direcciones)
            ->map(fn ($a) => $a->getAddress())
            ->implode(', ');

        $html = $mensaje->getHtmlBody();
        $cabeceras = $mensaje->getHeaders();

        $cabecera = function (string $nombre) use ($cabeceras) {
            $h = $cabeceras->get($nombre);

            return $h ? $h->getBodyAsString() : null;
        };

        // De los adjuntos se guarda el nombre: el contenido ya viaja en el
        // correo y duplicarlo en la base de datos no aporta nada.
        $adjuntos = collect($mensaje->getAttachments())
            ->map(fn ($a) => $a->getFilename())
            ->filter()
            ->values()
            ->all();

        return [
            'mensaje_uuid' => is_string($data['envioUuid'] ?? null) && $data['envioUuid'] !== ''
                ? $data['envioUuid']
                : null,
            'reenvio_de' => $cabecera(self::CAB_REENVIO),
            'to' => $lista($mensaje->getTo()),
            'cc' => $lista($mensaje->getCc()) ?: null,
            'bcc' => $lista($mensaje->getBcc()) ?: null,
            'subject' => $mensaje->getSubject(),
            'body_html' => is_string($html) ? $html : (string) $mensaje->getTextBody(),
            // Laravel deja aquí la clase del mailable: sirve para filtrar por tipo.
            'mailable' => is_string($data['__laravel_mailable'] ?? null) ? $data['__laravel_mailable'] : null,
            'plantilla' => $cabecera(PlantillaMail::CAB_PLANTILLA),
            'related_type' => $cabecera(PlantillaMail::CAB_TIPO),
            'related_id' => $cabecera(PlantillaMail::CAB_ID) ?: null,
            'adjuntos' => $adjuntos ?: null,
        ];
    }
}
