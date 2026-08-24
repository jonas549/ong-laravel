<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Único punto por el que pasa un cambio de estado: registra la transición
 * y dispara el correo correspondiente al organizador.
 */
class ActivityModerationService
{
    /** Mailable por estado destino. */
    private const AVISOS = [
        'revision' => \App\Mail\ActivityReceived::class,
        'publicada' => \App\Mail\ActivityPublished::class,
        'ajustes' => \App\Mail\ActivityChangesRequested::class,
        'cancelada' => \App\Mail\ActivityCancelled::class,
    ];

    public function cambiar(
        Activity $actividad,
        string $nuevoEstado,
        ?User $autor = null,
        ?string $comentario = null,
        bool $notificar = true,
    ): Activity {
        $anterior = $actividad->estado;

        if ($anterior === $nuevoEstado) {
            return $actividad;
        }

        $actividad->estado = $nuevoEstado;

        if ($nuevoEstado === 'publicada') {
            $actividad->published_at ??= now();
        }

        if ($nuevoEstado === 'ajustes') {
            $actividad->observaciones_revision = $comentario;
        }

        if ($nuevoEstado === 'cancelada') {
            $actividad->inscripcion_habilitada = false;
        }

        $actividad->save();

        ActivityStatusLog::create([
            'activity_id' => $actividad->id,
            'user_id' => $autor?->id,
            'de_estado' => $anterior,
            'a_estado' => $nuevoEstado,
            'comentario' => $comentario,
        ]);

        if ($notificar) {
            $this->avisar($actividad, $nuevoEstado);
        }

        return $actividad;
    }

    /**
     * Un fallo de correo no debe tumbar la moderación: el cambio de estado
     * ya está guardado y el intento queda en el log de correos.
     */
    private function avisar(Activity $actividad, string $estado): void
    {
        $mailable = self::AVISOS[$estado] ?? null;
        $destino = $actividad->organization?->user?->email;

        if (! $mailable || blank($destino)) {
            return;
        }

        try {
            app(SmtpConfigService::class)->aplicar();
            Mail::to($destino)->send(new $mailable($actividad));
        } catch (Throwable $e) {
            Log::warning('No se pudo avisar del cambio de estado', [
                'activity' => $actividad->id,
                'estado' => $estado,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
