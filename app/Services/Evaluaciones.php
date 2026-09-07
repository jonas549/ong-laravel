<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityEvaluation;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Cuándo se puede responder la encuesta de una actividad, y qué se le enseña a
 * quien llega fuera de plazo.
 *
 * Las dos puntas del plazo las decide la ONG desde Configuración → General, y
 * eso es deliberado: las dos posturas son razonables. Abrir *desde que se
 * publica* deja al organizador probar su propio QR antes del día; abrir *desde
 * el día de la actividad* evita que alguien evalúe algo a lo que todavía no ha
 * ido. Elegir por ellos habría sido adivinar.
 *
 * **Fuera de plazo no es un 404.** Un 404 es para lo que no existe; una
 * encuesta cerrada existe y la persona tiene el cartel delante. Merece una
 * pantalla que se lo diga.
 */
class Evaluaciones
{
    public const ABIERTA = 'abierta';
    public const AUN_NO = 'aun_no';
    public const CERRADA = 'cerrada';

    /** Se abre al publicar la actividad, o el día en que ocurre. */
    public function apertura(): string
    {
        $valor = Setting::get('evaluacion_apertura', 'publicacion');

        return in_array($valor, ['publicacion', 'actividad'], true) ? $valor : 'publicacion';
    }

    /** Cuántos días sigue abierta tras terminar. 0 = para siempre. */
    public function diasAbierta(): int
    {
        return max(0, (int) Setting::get('evaluacion_dias_abierta', 30));
    }

    /**
     * El estado de la encuesta de esta actividad ahora mismo.
     *
     * @return array{estado: string, mensaje: string}
     */
    public function estado(Activity $actividad): array
    {
        if ($actividad->estado === 'cancelada') {
            return [
                'estado' => self::CERRADA,
                'mensaje' => 'Esta actividad fue cancelada, así que su encuesta ya no recibe respuestas.',
            ];
        }

        if (! $this->yaAbrio($actividad)) {
            return [
                'estado' => self::AUN_NO,
                'mensaje' => 'La encuesta se abre el día de la actividad. Vuelve a escanear el código cuando hayas participado.',
            ];
        }

        if ($this->yaCerro($actividad)) {
            return [
                'estado' => self::CERRADA,
                'mensaje' => 'El plazo para evaluar esta actividad ya terminó. Gracias de todas formas por haber participado.',
            ];
        }

        return ['estado' => self::ABIERTA, 'mensaje' => ''];
    }

    public function abierta(Activity $actividad): bool
    {
        return $this->estado($actividad)['estado'] === self::ABIERTA;
    }

    private function yaAbrio(Activity $actividad): bool
    {
        if ($this->apertura() === 'publicacion') {
            // Sin `published_at` no se ha publicado, y entonces no hay QR que
            // haya podido llegar a nadie.
            return $actividad->published_at !== null;
        }

        // «Desde el día de la actividad»: una actividad sin fecha no tiene ese
        // día, así que se abre en cuanto se publica. Lo contrario la dejaría
        // cerrada para siempre, que es peor que abrirla de más.
        if ($actividad->sin_fecha_definida || ! $actividad->fecha_inicio) {
            return $actividad->published_at !== null;
        }

        return ! Carbon::parse($actividad->fecha_inicio)->startOfDay()->isFuture();
    }

    private function yaCerro(Activity $actividad): bool
    {
        $dias = $this->diasAbierta();

        if ($dias === 0) {
            return false;
        }

        $cierre = $this->fechaDeCierre($actividad, $dias);

        return $cierre !== null && $cierre->isPast();
    }

    /**
     * El día en que deja de admitir respuestas, o null si no cierra nunca.
     *
     * Una actividad «disponible de forma permanente» no termina ningún día, y
     * cerrarle la encuesta a los treinta de publicarse sería inventar un plazo
     * que nadie pidió.
     */
    public function fechaDeCierre(Activity $actividad, ?int $dias = null): ?Carbon
    {
        $dias ??= $this->diasAbierta();

        if ($dias === 0 || $actividad->sin_fecha_definida) {
            return null;
        }

        $fin = $actividad->fecha_termino ?: $actividad->fecha_inicio;

        return $fin ? Carbon::parse($fin)->endOfDay()->addDays($dias) : null;
    }

    /* ── Lo que lee el panel ─────────────────────────────── */

    /**
     * Promedio y reparto de las dos escalas.
     *
     * El reparto va además del promedio y no en su lugar: un 3 de media puede
     * ser todo el mundo dando un 3, o media sala dando 1 y la otra media
     * dando 5. Son dos actividades distintas y el promedio solo no las
     * distingue.
     *
     * @param  \Illuminate\Support\Collection<int, ActivityEvaluation>  $evaluaciones
     * @return array<string, array{promedio: float|null, reparto: array<int, int>, total: int}>
     */
    public function resumen($evaluaciones): array
    {
        $resumen = [];

        foreach (array_keys(ActivityEvaluation::ESCALAS) as $escala) {
            $valores = $evaluaciones->pluck($escala)->filter()->values();

            $reparto = array_fill_keys([1, 2, 3, 4, 5], 0);

            foreach ($valores as $v) {
                if (isset($reparto[$v])) {
                    $reparto[$v]++;
                }
            }

            $resumen[$escala] = [
                'promedio' => $valores->isEmpty() ? null : round($valores->avg(), 2),
                'reparto' => $reparto,
                'total' => $valores->count(),
            ];
        }

        return $resumen;
    }
}
