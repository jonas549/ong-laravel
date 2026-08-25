<?php

namespace App\Listeners;

use App\Mail\PlantillaMail;
use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
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
 */
class LogSentMail
{
    /** Cabecera que usa el reenvío manual para no duplicar la fila. */
    public const CAB_REENVIO = 'X-DPS-Reenvio-De';

    /**
     * La fila nace en estado `failed`: si el envío funciona, MessageSent la
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
                    'status' => 'sent',
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

                $registro->fill($datos + [
                    'status' => 'failed',
                    'error' => 'Envío iniciado sin confirmación del servidor.',
                ]);
                $registro->attempts = $registro->exists ? $registro->attempts + 1 : 1;
                $registro->save();

                return;
            }

            EmailLog::create($datos + [
                'status' => 'failed',
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
                : EmailLog::where('status', 'failed')
                    ->where('to', $datos['to'])
                    ->where('subject', $datos['subject'])
                    ->latest('id')
                    ->first();

            $registro?->update([
                'status' => 'sent',
                'error' => null,
                'sent_at' => now(),
            ]);
        } catch (Throwable) {
            // Idem.
        }
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
