<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\EmailLog;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\CorreoTransaccional;
use App\Services\Evaluaciones;
use App\Support\CatalogoAjustes;
use Illuminate\Console\Command;

/**
 * Invita por correo a evaluar la actividad, cuando ya pasó (P20).
 *
 * Hasta aquí la encuesta sólo se alcanzaba escaneando el QR del cartel. Quien
 * no lo escaneó ese día —casi todo el mundo, si se fue con prisa— no tenía
 * forma de volver. Esto le manda el mismo enlace por correo.
 *
 * **Cuándo sale lo decide la ONG** en Configuración → General: el mismo día de
 * la actividad o el siguiente, que es lo que pidió el cliente. Y hay una
 * tercera opción, «no enviar», porque el QR sigue funcionando y una ONG puede
 * preferir no escribirle a sus inscritos.
 *
 * Se apoya en el registro de correos para no repetir, igual que
 * `dps:recordatorios`: si ya salió la invitación de esa actividad a esa
 * persona, no se vuelve a mandar aunque el comando corra otra vez.
 *
 *   php artisan dps:invitar-evaluacion
 *   php artisan dps:invitar-evaluacion --seco
 *   php artisan dps:invitar-evaluacion --fecha=2026-09-14
 */
class InvitarAEvaluar extends Command
{
    protected $signature = 'dps:invitar-evaluacion
                            {--fecha= : Trata las actividades terminadas ese día, en vez de las que tocan hoy}
                            {--seco : Muestra a quién se escribiría, sin enviar}';

    protected $description = 'Manda a las personas inscritas el enlace de la encuesta de evaluación';

    /** Margen para dar por «en vuelo» una invitación recién encolada. */
    private const MARGEN_EN_VUELO = 60;

    public function handle(CorreoTransaccional $correos, Evaluaciones $evaluaciones): int
    {
        $cuando = (string) Setting::get('evaluacion_invitacion_cuando', 'dia_siguiente');

        if (! array_key_exists($cuando, CatalogoAjustes::EVALUACION_INVITACION)) {
            $cuando = 'dia_siguiente';
        }

        if ($cuando === 'no') {
            $this->info('La invitación por correo está desactivada en Configuración → General.');

            return self::SUCCESS;
        }

        $seco = (bool) $this->option('seco');

        /*
         * Qué día terminaron las actividades a las que les toca hoy.
         *
         * Con «mismo día» son las de hoy; con «día siguiente», las de ayer. Se
         * mira `fecha_termino` cuando la hay —una actividad de tres días no se
         * evalúa el primero— y si no, `fecha_inicio`.
         */
        $dia = $this->option('fecha')
            ? \Illuminate\Support\Carbon::parse($this->option('fecha'))->toDateString()
            : ($cuando === 'mismo_dia' ? now()->toDateString() : now()->subDay()->toDateString());

        $actividades = Activity::published()
            ->where('sin_fecha_definida', false)
            ->whereRaw('COALESCE(fecha_termino, fecha_inicio) = ?', [$dia])
            ->with(['organization', 'commune', 'region'])
            ->get();

        if ($actividades->isEmpty()) {
            $this->info("No hay actividades publicadas que terminaran el {$dia}.");

            return self::SUCCESS;
        }

        $enviados = 0;
        $omitidos = 0;
        $apagados = 0;
        $cerradas = 0;

        foreach ($actividades as $actividad) {
            /*
             * Si la encuesta de esa actividad no está abierta, no se invita.
             * Mandar a alguien a una pantalla de «encuesta cerrada» es peor
             * que no escribirle: parece que llega tarde por su culpa.
             */
            if ($evaluaciones->estado($actividad)['estado'] !== Evaluaciones::ABIERTA) {
                $this->line("· {$actividad->titulo} — encuesta cerrada, no se invita");
                $cerradas++;

                continue;
            }

            $this->line("· {$actividad->titulo} ({$dia})");

            $actividad->registrations()
                ->where('estado', '!=', 'cancelado')
                ->chunkById(100, function ($inscripciones) use ($correos, $actividad, $seco, &$enviados, &$omitidos, &$apagados) {
                    foreach ($inscripciones as $inscripcion) {
                        if ($this->yaInvitado($inscripcion)) {
                            $omitidos++;

                            continue;
                        }

                        if ($seco) {
                            $this->line("    escribiría a {$inscripcion->correo}");
                            $enviados++;

                            continue;
                        }

                        $inscripcion->setRelation('activity', $actividad);

                        if ($correos->invitacionEvaluacion($inscripcion)) {
                            // Se marca al encolar y no al enviar: el registro
                            // sólo se escribe cuando el worker despacha, y
                            // hasta entonces otra pasada volvería a encolar.
                            $inscripcion->forceFill(['invitacion_evaluacion_encolada_at' => now()])->save();
                            $enviados++;
                        } else {
                            $apagados++;
                        }
                    }
                });
        }

        $resumen = $seco
            ? "En seco: {$enviados} se escribirían, {$omitidos} ya tenían invitación."
            : "Invitaciones encoladas: {$enviados}. Omitidas por duplicado: {$omitidos}.";

        // Si la plantilla está apagada esa gente no recibe nada, y el reporte
        // tiene que decirlo: antes desaparecían de la cuenta sin más.
        if ($apagados) {
            $resumen .= " Sin enviar por plantilla desactivada: {$apagados}.";
        }

        if ($cerradas) {
            $resumen .= " Actividades con la encuesta ya cerrada: {$cerradas}.";
        }

        $this->info($resumen);

        return self::SUCCESS;
    }

    /**
     * ¿Hay que saltarse esta inscripción?
     *
     * Sí en dos casos: si ya se envió de verdad, o si se encoló hace poco y
     * todavía puede estar en vuelo. Si se encoló hace rato y acabó fallando,
     * se reintenta: un fallo pasajero de SMTP no debe dejar a esa persona sin
     * invitación para siempre.
     */
    private function yaInvitado(Registration $inscripcion): bool
    {
        $enviado = EmailLog::where('plantilla', 'invitacion_evaluacion')
            ->where('related_type', Registration::class)
            ->where('related_id', (string) $inscripcion->id)
            ->where('status', 'sent')
            ->exists();

        if ($enviado) {
            return true;
        }

        return $inscripcion->invitacion_evaluacion_encolada_at
            && $inscripcion->invitacion_evaluacion_encolada_at->gt(now()->subMinutes(self::MARGEN_EN_VUELO));
    }
}
