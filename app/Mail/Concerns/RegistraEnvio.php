<?php

namespace App\Mail\Concerns;

use App\Models\EmailLog;
use Illuminate\Support\Str;
use Throwable;

/**
 * Da a cada correo un identificador propio y guarda el error real si falla.
 *
 * El identificador es una propiedad pública, así que Laravel lo incluye en los
 * datos de la vista y `LogSentMail` puede leerlo del evento sin necesidad de
 * cabeceras. Eso permite que el log actualice siempre la fila correcta, en vez
 * de buscarla por destinatario y asunto.
 *
 * `failed()` la llama Laravel cuando el trabajo en cola agota sus intentos:
 * es el único punto donde se conoce la excepción de verdad, porque el mailer
 * no emite ningún evento de fallo.
 */
trait RegistraEnvio
{
    public string $envioUuid = '';

    /** Se llama desde el constructor de cada mailable. */
    public function marcarEnvio(): void
    {
        $this->envioUuid = (string) Str::uuid();
    }

    public function failed(Throwable $e): void
    {
        if ($this->envioUuid === '') {
            return;
        }

        EmailLog::where('mensaje_uuid', $this->envioUuid)->update([
            'status' => 'failed',
            'error' => Str::limit($e->getMessage(), 2000),
        ]);
    }
}
