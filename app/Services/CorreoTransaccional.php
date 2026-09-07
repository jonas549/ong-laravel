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

    /**
     * Aviso al organizador de que su actividad ya está publicada.
     *
     * Es el correo que lleva el QR de la encuesta de evaluación, y va DENTRO
     * de éste y no en un correo aparte a propósito: es el mismo momento, y la
     * persona recibe todo junto en vez de dos correos que tiene que relacionar.
     *
     * Antes era un mailable con vista fija (`App\Mail\ActivityPublished`), que
     * la ONG no podía editar. Ahora es una plantilla del panel como las otras
     * cinco. Si la plantilla no existiera —una base a la que todavía no ha
     * llegado la siembra— esto devuelve false y quien llama vuelve al mailable
     * de siempre, para que nadie se quede sin su aviso.
     *
     * @param  string  $qr  el PNG del código, ya generado
     */
    public function actividadPublicada(Activity $actividad, string $qr = ''): bool
    {
        $destino = $actividad->organization?->user?->email;

        if (blank($destino)) {
            return false;
        }

        return $this->enviar('actividad_publicada', $destino, [
            'nombre' => $actividad->organization?->user?->name ?? '',
            'organizacion' => $actividad->organization?->nombre ?? '',
            'actividad' => $actividad->titulo,
            'fecha' => $actividad->fecha_larga,
            'lugar' => $actividad->lugar,
            'enlace_actividad' => route('activities.show', $actividad),
            'enlace_qr' => route('account.activities.edit', $actividad),
            'bloque_qr' => $this->bloqueQr($actividad, $qr !== ''),
            'sitio' => config('app.name'),
        ], $actividad, incrustadas: $qr === '' ? [] : [
            'qr' => ['datos' => $qr, 'nombre' => 'qr-evaluacion.png', 'mime' => 'image/png'],
        ]);
    }

    /**
     * El bloque del QR, montado aquí y no dejado a la plantilla.
     *
     * Mismo motivo que el `bloque_calendario`: las plantillas las edita la ONG
     * desde el panel y no tienen condicionales. Con la imagen y el enlace como
     * variables sueltas, un correo en el que el QR no se hubiera podido generar
     * dejaría un `<img>` roto debajo de un texto que sigue hablando de un
     * código. Así el bloque entero desaparece y no queda nada a medias.
     *
     * **Lleva imagen Y enlace, y eso no es redundancia.** Muchos clientes de
     * correo no cargan imágenes hasta que la persona lo autoriza: sin el enlace
     * debajo, para esa gente el correo no diría nada. El enlace lleva a su
     * actividad en «Mi cuenta», que es donde puede descargarlo para imprimir.
     */
    private function bloqueQr(Activity $actividad, bool $conImagen): string
    {
        $enlace = e(route('account.activities.edit', $actividad));

        $imagen = $conImagen
            ? '<img src="cid:qr" width="200" height="200" alt="Código QR de la encuesta de evaluación"'
                .' style="display:block;margin:0 auto 14px;width:200px;height:200px;border:1px solid #eceef0;border-radius:12px;">'
            : '';

        return trim(<<<HTML
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
            <tr>
                <td style="padding:20px;background:#faf7f3;border:1px solid #eceef0;border-radius:16px;text-align:center;">
                    <p style="margin:0 0 14px;font-size:15px;font-weight:700;color:#33363a;">El código QR de tu actividad</p>
                    {$imagen}
                    <p style="margin:0 0 14px;font-size:13.5px;line-height:1.6;color:#63666a;">
                        Imprímelo y ponlo a la vista el día de la actividad. Quien lo escanee podrá
                        contarte cómo le fue en menos de un minuto.
                    </p>
                    <a href="{$enlace}" style="display:inline-block;background:#ffffff;color:#cc6600;font-weight:600;font-size:13.5px;padding:10px 20px;border:1.5px solid #e57200;border-radius:999px;text-decoration:none;">
                        Descargar el QR para imprimir
                    </a>
                </td>
            </tr>
        </table>
        HTML);
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
    private function enviar(string $clave, string $destino, array $datos, ?Model $relacionado = null, array $incrustadas = []): bool
    {
        $plantilla = EmailTemplate::porClave($clave);

        if (! $plantilla) {
            return false;
        }

        try {
            $this->smtp->aplicar();
            Mail::to($destino)->send(new PlantillaMail($plantilla, $datos, relacionado: $relacionado, incrustadas: $incrustadas));

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
