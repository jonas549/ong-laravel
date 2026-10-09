<?php

namespace App\Services;

use App\Mail\PlantillaMail;
use App\Models\Activity;
use App\Models\EmailTemplate;
use App\Models\Setting;
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
                'bloque_soy_parte' => $actividad ? $this->bloqueSoyParte($actividad) : '',
                'bloque_contacto' => $actividad ? $this->bloqueContacto($actividad) : '',
            ], $inscripcion);
    }

    /**
     * «Si tienes dudas, escríbele» con el correo público de la actividad
     * (punto 8 del 09/10). Va después del enlace del calendario.
     *
     * **El correo lo escribe una persona** en el formulario, y los `bloque_`
     * entran sin escapar (ver EmailTemplateRenderer): por eso se escapa aquí,
     * entero, antes de meterlo en el HTML. Sin correo, el bloque no sale.
     */
    private function bloqueContacto(Activity $actividad): string
    {
        $correo = trim((string) $actividad->correo_contacto);

        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        $enlace = e('mailto:'.$correo.'?subject='.rawurlencode('Consulta sobre «'.$actividad->titulo.'»'));

        return '<p style="margin:18px 0 0;font-size:14px;color:#63666A;">'
            .'Si tienes dudas y necesitas contactar al organizador, '
            .'<a href="'.$enlace.'" style="color:#cc6600;font-weight:600;">escríbele</a>.</p>';
    }

    /**
     * «Cuenta que eres parte»: lleva a la pantalla de la imagen para compartir
     * (punto 7 del 30/09). Un enlace y no la imagen adjunta: así el correo no
     * engorda y no depende del trabajo de adjuntos, que está aplazado.
     *
     * Montado aquí por lo mismo que el del QR: la plantilla la edita la ONG y
     * no tiene condicionales, y sin actividad el bloque entero desaparece.
     */
    private function bloqueSoyParte(Activity $actividad): string
    {
        $enlace = e(route('registrations.soy-parte', $actividad));

        return trim(<<<HTML
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
            <tr>
                <td style="padding:20px;background:#faf7f3;border:1px solid #eceef0;border-radius:16px;text-align:center;">
                    <p style="margin:0 0 8px;font-size:15px;font-weight:700;color:#33363a;">Cuenta que eres parte</p>
                    <p style="margin:0 0 14px;font-size:13.5px;line-height:1.6;color:#63666a;">
                        Comparte en tus redes que te sumas al Día del Patrimonio Social
                        e invita a otras personas a participar.
                    </p>
                    <a href="{$enlace}" style="display:inline-block;background:#ffffff;color:#cc6600;font-weight:600;font-size:13.5px;padding:10px 20px;border:1.5px solid #e57200;border-radius:999px;text-decoration:none;">
                        Cuenta que eres parte
                    </a>
                </td>
            </tr>
        </table>
        HTML);
    }

    /**
     * Aviso de que alguien se inscribió, a quien creó la actividad.
     *
     * **Todos los correos sobre una actividad van a su `responsable()`** —su
     * autora, o la cuenta principal si se quedó sin autor—, no a la cuenta
     * principal de la organización. Con varias personas publicando, mandarlos
     * a la principal le llenaría el buzón con lo de todas y la autora no se
     * enteraría de nada.
     *
     * La principal va **en copia** (tanda del 09/10), salvo que sea ella la
     * autora o no haya principal activa: `Activity::copiaParaLaPrincipal()`.
     */
    public function nuevaInscripcion(Registration $inscripcion): bool
    {
        $actividad = $inscripcion->activity;
        $destino = $actividad?->responsable()?->email;

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
        ], $inscripcion, copia: $actividad->copiaParaLaPrincipal($destino));
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

    /**
     * Invitación a evaluar, cuando la actividad ya pasó (P20).
     *
     * Va al enlace de la encuesta, el mismo al que lleva el QR del cartel.
     * Hasta aquí ese QR era el único camino: quien no lo escaneó ese día no
     * tenía forma de volver, y las respuestas que se perdían eran justo las de
     * quien se fue con prisa.
     *
     * El enlace va por slug, como el del QR, para que los dos lleven al mismo
     * sitio y una misma persona no tenga dos direcciones distintas de la misma
     * encuesta.
     */
    public function invitacionEvaluacion(Registration $inscripcion): bool
    {
        $actividad = $inscripcion->activity;

        if (! $actividad) {
            return false;
        }

        return $this->enviar('invitacion_evaluacion', $inscripcion->correo,
            $this->datosDeActividad($actividad) + [
                'nombre' => $inscripcion->nombre,
                'enlace_encuesta' => route('evaluar.show', $actividad->slug),
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
        $cuenta = $actividad->responsable();
        $destino = $cuenta?->email;

        if (blank($destino)) {
            return false;
        }

        return $this->enviar('actividad_publicada', $destino, [
            'nombre' => $cuenta->name ?? '',
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
        ], copia: $actividad->copiaParaLaPrincipal($destino));
    }

    /**
     * La guía para organizadores (D1 de la sexta tanda), a la organización
     * cuya actividad acaba de publicarse (punto 11 del 23/09; antes salía al
     * registrarla).
     *
     * Sin enlace configurado no sale: un correo cuyo botón no lleva a ninguna
     * parte es peor que ninguno.
     *
     * Y una vez por actividad. Las que estaban en revisión cuando se cambió
     * el momento ya la recibieron al registrarse, y al aprobarlas les llegaría
     * otra vez. El registro de correos guarda a qué actividad fue cada una.
     */
    public function guiaOrganizador(Activity $actividad): bool
    {
        $cuenta = $actividad->responsable();
        $destino = $cuenta?->email;
        $guia = trim((string) Setting::get('guia_organizador_url'));

        if (blank($destino) || $guia === '') {
            return false;
        }

        $yaEnviada = \App\Models\EmailLog::where('plantilla', 'guia_organizador')
            ->where('related_type', Activity::class)
            ->where('related_id', $actividad->getKey())
            ->exists();

        if ($yaEnviada) {
            return false;
        }

        return $this->enviar('guia_organizador', $destino, [
            'nombre' => $cuenta->name ?? '',
            'organizacion' => $actividad->organization?->nombre ?? '',
            'actividad' => $actividad->titulo,
            'enlace_guia' => $guia,
            'enlace_cuenta' => route('account.activities.index'),
            'sitio' => config('app.name'),
        ], $actividad, copia: $actividad->copiaParaLaPrincipal($destino));
    }

    /**
     * Aviso a la cuenta principal de que otra cuenta se sumó a su
     * organización (varias cuentas por organización).
     *
     * Quien se suma entra directo, sin aprobación previa: este aviso es lo que
     * impide que cualquiera se meta como «Fundación X» y publique en su nombre
     * sin que nadie de la fundación se entere. Por eso, si la organización no
     * tiene una principal que lo pueda leer —borrada, desactivada o fuera de
     * la organización—, va al buzón de avisos del equipo en vez de a nadie, y
     * `nota` dice por qué le llega.
     */
    public function cuentaSumada(User $nueva): bool
    {
        $organizacion = $nueva->organization;

        if (! $organizacion) {
            return false;
        }

        $principal = $organizacion->principalActiva();

        $datos = [
            'organizacion' => $organizacion->nombre,
            'nombre_cuenta' => $nueva->name,
            'correo_cuenta' => $nueva->email,
            'fecha' => \App\Support\Fecha::corta(now()),
            // El correo de contacto del sitio (Configuración → General): a
            // dónde escribir si no conoce a esa persona. El de avisos, si no hay.
            // Nunca una dirección de ejemplo (09/10): sin correo útil, la
            // frase queda en «escríbenos a nuestro equipo».
            'correo_sitio' => Setting::correoDeContacto() ?? 'nuestro equipo',
            'enlace_cuenta' => route('account.login'),
            'sitio' => config('app.name'),
        ];

        if ($principal) {
            return $this->enviar('cuenta_sumada', $principal->email, $datos + [
                'nombre' => $principal->name,
                'nota' => '',
            ], $nueva);
        }

        $alguno = false;

        foreach ($this->destinosDelEquipo() as $destino) {
            $alguno = $this->enviar('cuenta_sumada', $destino, $datos + [
                'nombre' => 'equipo',
                'nota' => 'Te llega a ti porque «'.$organizacion->nombre.'» no tiene una cuenta principal activa. '
                    .'Puedes elegir una en Panel → Organizaciones.',
            ], $nueva) || $alguno;
        }

        return $alguno;
    }

    /**
     * Aviso al equipo de que una actividad quedó esperando revisión.
     *
     * @param  string|null  $motivo  el que dejó escrito la aprobación automática
     *                               en el historial («es la primera actividad…»)
     */
    public function equipoActividadEnRevision(Activity $actividad, ?string $motivo = null): bool
    {
        return $this->avisarAlEquipo('equipo_actividad_en_revision', $actividad, [
            'motivo' => $motivo ?: 'no consta',
        ]);
    }

    /** Aviso al equipo de que una actividad se publicó sola, sin revisión. */
    public function equipoActividadAutopublicada(Activity $actividad): bool
    {
        return $this->avisarAlEquipo('equipo_actividad_autopublicada', $actividad);
    }

    /**
     * Aviso al equipo de que se editó una actividad ya publicada.
     *
     * Como mucho uno por actividad y día —el día de Chile, no el de UTC—: una
     * organización que corrige su ficha guarda varias veces seguidas, y diez
     * correos iguales en una mañana acaban haciendo que nadie lea ninguno.
     * Cuenta lo que ya consta en el registro de correos, que es lo que guarda
     * a qué actividad fue cada uno, igual que la guía para organizadores.
     */
    public function equipoActividadEditada(Activity $actividad): bool
    {
        $desde = now(\App\Support\Fecha::zona())->startOfDay()->utc();

        $yaAvisada = \App\Models\EmailLog::where('plantilla', 'equipo_actividad_editada')
            ->where('related_type', Activity::class)
            ->where('related_id', $actividad->getKey())
            ->where('created_at', '>=', $desde)
            ->exists();

        if ($yaAvisada) {
            return false;
        }

        return $this->avisarAlEquipo('equipo_actividad_editada', $actividad);
    }

    /**
     * A quién van los avisos al equipo: el buzón de Configuración → General
     * (`avisos_email`) y, sólo si está vacío o no es un correo, los
     * administradores activos. Un aviso que no llega a nadie es peor que uno
     * que llega a quien no era.
     *
     * Es el mismo criterio que el aviso de vuelta de ajustes, y lo usan los dos.
     *
     * @return array<int, string>
     */
    public function destinosDelEquipo(): array
    {
        $buzon = trim((string) Setting::get('avisos_email'));

        return filter_var($buzon, FILTER_VALIDATE_EMAIL)
            ? [$buzon]
            : User::where('role', 'admin')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->pluck('email')
                ->all();
    }

    /** @param  array<string, string>  $extra */
    private function avisarAlEquipo(string $clave, Activity $actividad, array $extra = []): bool
    {
        $destinos = $this->destinosDelEquipo();

        if (! $destinos) {
            return false;
        }

        $datos = [
            'actividad' => $actividad->titulo,
            'organizacion' => $actividad->organization?->nombre ?? '',
            // El de la cuenta que la publicó, que es a quien hay que escribir.
            'correo_organizacion' => $actividad->responsable()?->email ?? '',
            'fecha' => $actividad->fecha_larga,
            'lugar' => trim(($actividad->direccion ? $actividad->direccion.', ' : '').$actividad->lugar, ', '),
            'enlace_revisar' => route('admin.activities.show', $actividad),
            'enlace_actividad' => route('activities.show', $actividad),
            'sitio' => config('app.name'),
        ] + $extra;

        $alguno = false;

        foreach ($destinos as $destino) {
            $alguno = $this->enviar($clave, $destino, $datos, $actividad) || $alguno;
        }

        return $alguno;
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
    private function enviar(string $clave, string $destino, array $datos, ?Model $relacionado = null, array $incrustadas = [], ?string $copia = null): bool
    {
        $plantilla = EmailTemplate::porClave($clave);

        if (! $plantilla) {
            return false;
        }

        try {
            $this->smtp->aplicar();
            $correo = Mail::to($destino);

            if (filled($copia)) {
                $correo->cc($copia);
            }

            $correo->send(new PlantillaMail($plantilla, $datos, relacionado: $relacionado, incrustadas: $incrustadas));

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
