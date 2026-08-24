<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Registra todo correo que sale del sistema.
 *
 * Se engancha a los eventos del propio mailer de Laravel en vez de llamar a
 * un logger en cada punto de envío: así queda registrado también lo que no
 * escribimos nosotros, como el correo de recuperación de contraseña.
 */
class LogSentMail
{
    /**
     * Antes de enviar dejamos la fila en estado `failed`. Si el envío
     * funciona, MessageSent la corrige. Si el SMTP lanza una excepción,
     * MessageSent nunca llega y la fila queda marcada como fallida —
     * que es exactamente lo que se necesita ver en el panel.
     */
    public function sending(MessageSending $event): void
    {
        try {
            EmailLog::create($this->extraer($event->message) + [
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
            $datos = $this->extraer($event->message);

            $registro = EmailLog::where('status', 'failed')
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

    /** @return array<string, mixed> */
    private function extraer(Email $mensaje): array
    {
        $lista = fn (array $direcciones) => collect($direcciones)
            ->map(fn ($a) => $a->getAddress())
            ->implode(', ');

        $html = $mensaje->getHtmlBody();

        return [
            'to' => $lista($mensaje->getTo()),
            'cc' => $lista($mensaje->getCc()) ?: null,
            'bcc' => $lista($mensaje->getBcc()) ?: null,
            'subject' => $mensaje->getSubject(),
            'body_html' => is_string($html) ? $html : (string) $mensaje->getTextBody(),
            'mailable' => null,
        ];
    }
}
