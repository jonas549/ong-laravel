<?php

namespace App\Services;

use App\Mail\PlantillaMail;
use App\Models\Activity;
use App\Models\EmailTemplate;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Único punto por el que salen los correos automáticos.
 *
 * Cada método arma los datos de su plantilla y la encola. Si la plantilla está
 * desactivada desde el panel, no se envía nada y se devuelve false: apagar un
 * aviso es una decisión legítima de la ONG, no un error.
 */
class CorreoTransaccional
{
    public function __construct(private SmtpConfigService $smtp)
    {
    }

    /** Bienvenida a quien acaba de crear su cuenta. */
    public function bienvenida(User $usuario): bool
    {
        return $this->enviar('bienvenida', $usuario->email, [
            'nombre' => $usuario->name,
            'organizacion' => $usuario->organization?->nombre ?? $usuario->name,
            'correo' => $usuario->email,
            'enlace_cuenta' => route('account.login'),
            'sitio' => config('app.name'),
        ], $usuario);
    }

    /** Confirmación a quien se inscribe. */
    public function inscripcionConfirmada(Registration $inscripcion): bool
    {
        $actividad = $inscripcion->activity;

        return $this->enviar('inscripcion_confirmada', $inscripcion->correo,
            $this->datosDeActividad($actividad) + [
                'nombre' => $inscripcion->nombre,
                'enlace_cancelar' => route('registrations.cancel', $inscripcion->token),
            ], $inscripcion);
    }

    /** Aviso a la organización de que alguien se inscribió. */
    public function nuevaInscripcion(Registration $inscripcion): bool
    {
        $actividad = $inscripcion->activity;
        $destino = $actividad?->organization?->user?->email;

        if (blank($destino)) {
            return false;
        }

        return $this->enviar('nueva_inscripcion', $destino, [
            'nombre' => $inscripcion->nombre,
            'correo_inscrito' => $inscripcion->correo,
            'actividad' => $actividad->titulo,
            'fecha' => $actividad->fecha_larga,
            'cupos_disponibles' => $actividad->cupos_disponibles === null
                ? 'sin límite'
                : (string) $actividad->cupos_disponibles,
            'enlace_participantes' => route('account.participants.index', $actividad),
            'sitio' => config('app.name'),
        ], $inscripcion);
    }

    /** Recordatorio los días previos. */
    public function recordatorio(Registration $inscripcion, int $dias): bool
    {
        $actividad = $inscripcion->activity;

        return $this->enviar('recordatorio', $inscripcion->correo,
            $this->datosDeActividad($actividad) + [
                'nombre' => $inscripcion->nombre,
                'dias' => (string) $dias,
                'enlace_cancelar' => route('registrations.cancel', $inscripcion->token),
            ], $inscripcion);
    }

    /** Aviso a la persona inscrita de que se canceló la actividad. */
    public function inscripcionCancelada(Registration $inscripcion): bool
    {
        $actividad = $inscripcion->activity;

        return $this->enviar('inscripcion_cancelada', $inscripcion->correo, [
            'nombre' => $inscripcion->nombre,
            'actividad' => $actividad?->titulo ?? '',
            'fecha' => $actividad?->fecha_larga ?? '',
            'organizacion' => $actividad?->organization?->nombre ?? '',
            'enlace_actividades' => route('activities.index'),
            'sitio' => config('app.name'),
        ], $inscripcion);
    }

    /** @return array<string, string> */
    private function datosDeActividad(?Activity $actividad): array
    {
        if (! $actividad) {
            return ['sitio' => config('app.name')];
        }

        $hora = collect([$actividad->hora_inicio, $actividad->hora_termino])
            ->filter()
            ->map(fn ($h) => substr((string) $h, 0, 5))
            ->implode(' a ');

        return [
            'actividad' => $actividad->titulo,
            'fecha' => $actividad->fecha_larga,
            'hora' => $hora ?: 'Por confirmar',
            'lugar' => trim(($actividad->direccion ? $actividad->direccion . ', ' : '') . $actividad->lugar, ', '),
            'organizacion' => $actividad->organization?->nombre ?? '',
            'enlace_actividad' => route('activities.show', $actividad),
            // Los dos enlaces de calendario, ya montados. Sale vacío si la
            // actividad no tiene fecha, y entonces el bloque desaparece
            // entero en vez de dejar un enlace sin destino.
            'bloque_calendario' => app(Calendario::class)->bloqueHtml($actividad),
            'sitio' => config('app.name'),
        ];
    }

    /**
     * Encola el envío. Un fallo aquí nunca debe tumbar la acción del usuario:
     * quien se acaba de inscribir no tiene por qué ver un error 500 porque el
     * SMTP esté caído.
     */
    private function enviar(string $clave, string $destino, array $datos, ?Model $relacionado = null): bool
    {
        $plantilla = EmailTemplate::porClave($clave);

        if (! $plantilla) {
            return false;
        }

        try {
            $this->smtp->aplicar();
            Mail::to($destino)->send(new PlantillaMail($plantilla, $datos, relacionado: $relacionado));

            return true;
        } catch (Throwable $e) {
            Log::warning('No se pudo encolar un correo transaccional', [
                'plantilla' => $clave,
                'destino' => $destino,
                'relacionado' => $relacionado?->getKey(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
