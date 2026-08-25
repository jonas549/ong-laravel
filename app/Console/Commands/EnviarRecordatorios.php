<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\EmailLog;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\CorreoTransaccional;
use Illuminate\Console\Command;

/**
 * Recordatorio a las personas inscritas los días previos a su actividad.
 *
 * Se apoya en el log de correos para no repetir: si ya salió un recordatorio
 * de esa actividad a ese correo, no se vuelve a enviar aunque el comando
 * corra varias veces al día.
 */
class EnviarRecordatorios extends Command
{
    protected $signature = 'dps:recordatorios
                            {--dias= : Días de antelación; por defecto, el ajuste recordatorio_dias}
                            {--seco : Muestra a quién se enviaría, sin enviar}';

    protected $description = 'Envía el recordatorio a las personas inscritas en actividades próximas';

    /** Margen para dar por "en vuelo" un recordatorio recién encolado. */
    private const MARGEN_EN_VUELO = 60;

    public function handle(CorreoTransaccional $correos): int
    {
        // ?: trataba "0" como vacío y caía al ajuste sin avisar.
        $opcion = $this->option('dias');
        $dias = (int) ($opcion === null || $opcion === '' ? Setting::get('recordatorio_dias', 3) : $opcion);
        $seco = (bool) $this->option('seco');

        if ($dias < 1) {
            $this->error('Los días de antelación deben ser al menos 1.');

            return self::FAILURE;
        }

        $fecha = now()->addDays($dias)->toDateString();

        $actividades = Activity::published()
            ->whereDate('fecha_inicio', $fecha)
            ->where('sin_fecha_definida', false)
            ->with(['organization', 'commune', 'region'])
            ->get();

        if ($actividades->isEmpty()) {
            $this->info("No hay actividades publicadas para el {$fecha}.");

            return self::SUCCESS;
        }

        $enviados = 0;
        $omitidos = 0;
        $apagados = 0;

        foreach ($actividades as $actividad) {
            $this->line("· {$actividad->titulo} ({$fecha})");

            $actividad->registrations()
                ->where('estado', '!=', 'cancelado')
                ->chunkById(100, function ($inscripciones) use ($correos, $actividad, $dias, $seco, &$enviados, &$omitidos, &$apagados) {
                    foreach ($inscripciones as $inscripcion) {
                        if ($this->yaAvisado($inscripcion)) {
                            $omitidos++;
                            continue;
                        }

                        if ($seco) {
                            $this->line("    enviaría a {$inscripcion->correo}");
                            $enviados++;
                            continue;
                        }

                        $inscripcion->setRelation('activity', $actividad);

                        if ($correos->recordatorio($inscripcion, $dias)) {
                            // Se marca al encolar, no al enviar: el log sólo se
                            // escribe cuando el worker despacha, y hasta
                            // entonces otra pasada volvería a encolar.
                            $inscripcion->forceFill(['recordatorio_encolado_at' => now()])->save();
                            $enviados++;
                        } else {
                            $apagados++;
                        }
                    }
                });
        }

        $resumen = $seco
            ? "En seco: {$enviados} se enviarían, {$omitidos} ya tenían recordatorio."
            : "Recordatorios encolados: {$enviados}. Omitidos por duplicado: {$omitidos}.";

        // Si la plantilla está apagada, esa gente no recibe nada y el reporte
        // tiene que decirlo: antes desaparecían de la cuenta sin más.
        if ($apagados) {
            $resumen .= " Sin enviar por plantilla desactivada: {$apagados}.";
        }

        $this->info($resumen);

        return self::SUCCESS;
    }

    /**
     * ¿Hay que saltarse esta inscripción?
     *
     * Sí en dos casos: si ya se envió de verdad, o si se encoló hace poco y
     * todavía puede estar en vuelo. Si se encoló hace rato y acabó fallando,
     * se vuelve a intentar: un fallo pasajero de SMTP no debe dejar a esa
     * persona sin aviso para siempre.
     */
    private function yaAvisado(Registration $inscripcion): bool
    {
        $enviado = EmailLog::where('plantilla', 'recordatorio')
            ->where('related_type', Registration::class)
            ->where('related_id', (string) $inscripcion->id)
            ->where('status', 'sent')
            ->exists();

        if ($enviado) {
            return true;
        }

        return $inscripcion->recordatorio_encolado_at
            && $inscripcion->recordatorio_encolado_at->gt(now()->subMinutes(self::MARGEN_EN_VUELO));
    }
}
