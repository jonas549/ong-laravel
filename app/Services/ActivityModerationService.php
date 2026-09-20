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
    /**
     * Mailable por estado destino.
     *
     * `publicada` sigue aquí pero como RESPALDO, no como camino normal: ese
     * aviso pasó a ser una plantilla editable desde el panel, porque es el que
     * lleva el QR de la encuesta y la ONG tiene que poder redactarlo. Ver
     * `avisarDePublicacion()`.
     */
    private const AVISOS = [
        'revision' => \App\Mail\ActivityReceived::class,
        'publicada' => \App\Mail\ActivityPublished::class,
        'ajustes' => \App\Mail\ActivityChangesRequested::class,
        'cancelada' => \App\Mail\ActivityCancelled::class,
    ];

    /**
     * @param  bool  $automatica  la publicó la regla de aprobación automática,
     *                           no una persona. Queda marcado en la actividad
     *                           para que la ONG pueda repasar después lo que
     *                           se publicó sin que nadie lo mirara.
     */
    public function cambiar(
        Activity $actividad,
        string $nuevoEstado,
        ?User $autor = null,
        ?string $comentario = null,
        bool $notificar = true,
        bool $automatica = false,
    ): Activity {
        $anterior = $actividad->estado;

        if ($anterior === $nuevoEstado) {
            return $actividad;
        }

        $actividad->estado = $nuevoEstado;

        if ($nuevoEstado === 'publicada') {
            $actividad->published_at ??= now();
            $actividad->publicada_automaticamente = $automatica;
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

            // Cancelar afecta a quien ya se inscribió, no sólo a la organización.
            if ($nuevoEstado === 'cancelada') {
                $this->avisarInscritos($actividad);
            }

            /*
             * Una actividad que vuelve de ajustes es la única transición que
             * va en el otro sentido —de la organización hacia la ONG—, y hasta
             * ahora no avisaba a nadie: la ONG tenía que entrar a Pendientes
             * por su cuenta y adivinar cuál de las que había llegado volvía
             * corregida.
             */
            if ($anterior === 'ajustes' && $nuevoEstado === 'revision') {
                $this->avisarDeVueltaDeAjustes($actividad, $comentario);
            }
        }

        return $actividad;
    }

    /**
     * Avisa a la ONG de que una actividad volvió corregida, con el mensaje que
     * escribió el organizador dentro.
     *
     * Va a todos los administradores activos, y no a una dirección fija de
     * configuración, porque el encargo es que se entere quien modera y no hay
     * un buzón del equipo: el que primero la vea la atiende.
     *
     * Como todo el correo de este proyecto, un fallo aquí no puede tumbar la
     * moderación: el cambio de estado ya está guardado.
     */
    private function avisarDeVueltaDeAjustes(Activity $actividad, ?string $mensaje): void
    {
        $destinos = User::where('role', 'admin')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        if (! $destinos) {
            return;
        }

        try {
            app(SmtpConfigService::class)->aplicar();

            foreach ($destinos as $destino) {
                Mail::to($destino)->send(new \App\Mail\ActivityResubmitted($actividad, $mensaje));
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo avisar a la ONG de la vuelta de ajustes', [
                'activity' => $actividad->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Avisa a cada persona inscrita de que la actividad se canceló.
     *
     * Por lotes: una actividad con cientos de inscritos no debe cargarlos
     * todos en memoria ni encolar de golpe.
     */
    private function avisarInscritos(Activity $actividad): void
    {
        $correos = app(CorreoTransaccional::class);

        $actividad->registrations()
            ->where('estado', '!=', 'cancelado')
            ->chunkById(100, function ($inscripciones) use ($correos, $actividad) {
                foreach ($inscripciones as $inscripcion) {
                    $inscripcion->setRelation('activity', $actividad);
                    $correos->inscripcionCancelada($inscripcion);
                }
            });
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

        // El de publicación va por la plantilla del panel, que es la que lleva
        // el QR. Sólo si esa plantilla no existe se cae al mailable de siempre.
        if ($estado === 'publicada' && $this->avisarDePublicacion($actividad)) {
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

    /**
     * El aviso de «tu actividad ya está publicada», con su código QR dentro.
     *
     * **Este es el único punto por el que se genera el QR, y por eso funciona
     * igual con aprobación automática.** Los dos caminos —la revisión a mano y
     * la regla que publica sola— acaban en `cambiar($actividad, 'publicada')`,
     * así que no hay una segunda rama que recordar. Si mañana aparece un tercer
     * camino, hereda el QR sin tocar nada.
     *
     * Un fallo generando el QR no puede impedir el aviso: si el código no sale,
     * el correo se manda igual y el bloque del QR desaparece entero. Es
     * preferible un correo sin código a que el organizador no se entere de que
     * su actividad está publicada.
     *
     * @return bool  true si la plantilla existe y se encoló; false para que
     *               quien llama use el mailable de respaldo.
     */
    private function avisarDePublicacion(Activity $actividad): bool
    {
        $png = '';

        try {
            $png = app(CodigoQr::class)->png($actividad, CodigoQr::LADO_CORREO);
        } catch (Throwable $e) {
            Log::warning('No se pudo generar el QR de la actividad', [
                'activity' => $actividad->id,
                'error' => $e->getMessage(),
            ]);
        }

        return app(CorreoTransaccional::class)->actividadPublicada($actividad, $png);
    }
}
