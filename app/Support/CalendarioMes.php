<?php

namespace App\Support;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * La rejilla de un mes para la vista de calendario de `/actividades`.
 *
 * Vive aquí y no en el controlador porque cada decisión de las de abajo hay que
 * poder leerla en un sitio, y porque la consulta la pone quien llama: este
 * objeto **no sabe filtrar**. Recibe la misma consulta ya filtrada que alimenta
 * el listado —los scopes `byRegion`, `byCommune`, `byFormato` y `byTerm` de
 * `Activity`— y sólo la acota al mes. Así región, comuna, tema y formato se
 * comportan igual en las dos vistas sin que nadie tenga que acordarse de
 * copiarlos, que era la condición de este encargo.
 *
 * Tres cosas que conviene saber antes de tocarlo:
 *
 * 1. **Las semanas empiezan en lunes**, como el calendario de referencia y como
 *    se lee un calendario en Chile. Carbon por defecto arranca en domingo.
 * 2. **Una actividad de varios días se pinta en todos sus días.** Es lo que
 *    espera cualquiera que mire un calendario, y lo pidió el cliente. Por eso
 *    el mapa es día → actividades y no actividad → día: la misma actividad
 *    aparece en varias casillas.
 * 3. **Las que no tienen fecha no entran aquí.** No hay casilla en la que
 *    caigan y meterlas en una al azar sería inventar un dato. Van en la franja
 *    de arriba, que se monta aparte con `sinFecha()`.
 */
class CalendarioMes
{
    /** Cuántos meses se puede navegar hacia delante y hacia atrás. */
    private const MESES_DE_MARGEN = 60;

    private function __construct(
        public readonly Carbon $mes,
        /** @var Collection<int, Collection<int, array>> semanas de siete días */
        public readonly Collection $semanas,
        public readonly int $total,
    ) {}

    /**
     * @param  Builder<Activity>  $consulta  ya filtrada, sin acotar por fecha
     * @param  string|null  $mesPedido  «2026-09»; si no vale, el mes de hoy
     */
    public static function montar(Builder $consulta, ?string $mesPedido): self
    {
        $mes = static::interpretarMes($mesPedido);

        $desde = $mes->copy()->startOfMonth();
        $hasta = $mes->copy()->endOfMonth();

        $actividades = (clone $consulta)
            ->where('sin_fecha_definida', false)
            ->whereNotNull('fecha_inicio')
            /*
             * Solapamiento con el mes, no «empieza dentro del mes»: una
             * actividad del 28 de agosto al 3 de septiembre tiene que salir
             * también en septiembre. `COALESCE` es para las de un solo día,
             * que dejan `fecha_termino` en nulo.
             */
            ->whereDate('fecha_inicio', '<=', $hasta)
            ->whereRaw('COALESCE(fecha_termino, fecha_inicio) >= ?', [$desde->toDateString()])
            ->with(['commune', 'region', 'terms'])
            ->orderBy('fecha_inicio')
            ->orderByRaw('hora_inicio IS NULL, hora_inicio')
            ->orderBy('titulo')
            ->get();

        $porDia = static::repartirPorDia($actividades, $desde, $hasta);

        return new self($mes, static::semanas($mes, $porDia), $actividades->count());
    }

    /**
     * Las que no caben en ninguna casilla, para la franja de arriba.
     *
     * Son dos casos distintos y se cuentan aparte porque significan cosas
     * distintas: «disponible todo el año» es una decisión del organizador y
     * «por definir» es un hueco que todavía tiene que rellenar.
     *
     * @param  Builder<Activity>  $consulta  la misma, ya filtrada
     * @return array{permanentes: Collection<int, Activity>, porDefinir: Collection<int, Activity>}
     */
    public static function sinFecha(Builder $consulta): array
    {
        $sueltas = (clone $consulta)
            ->where(fn ($q) => $q->where('sin_fecha_definida', true)->orWhereNull('fecha_inicio'))
            ->with(['commune', 'region', 'terms'])
            ->orderBy('titulo')
            ->get();

        return [
            'permanentes' => $sueltas->filter->sin_fecha_definida->values(),
            'porDefinir' => $sueltas->reject->sin_fecha_definida->values(),
        ];
    }

    /** «Septiembre de 2026», para el encabezado. */
    public function titulo(): string
    {
        return Str::ucfirst($this->mes->locale('es')->isoFormat('MMMM [de] YYYY'));
    }

    /** «2026-08», o null si se sale del margen navegable. */
    public function anterior(): ?string
    {
        return static::dentroDeMargen($paso = $this->mes->copy()->subMonthNoOverflow())
            ? $paso->format('Y-m')
            : null;
    }

    public function siguiente(): ?string
    {
        return static::dentroDeMargen($paso = $this->mes->copy()->addMonthNoOverflow())
            ? $paso->format('Y-m')
            : null;
    }

    public function esMesDeHoy(): bool
    {
        return $this->mes->isSameMonth(static::hoy());
    }

    /** Los rótulos de las columnas: lun, mar, mié… */
    public static function diasDeLaSemana(): array
    {
        $lunes = static::hoy()->startOfWeek(Carbon::MONDAY);

        return collect(range(0, 6))
            ->map(fn ($i) => Str::ucfirst($lunes->copy()->addDays($i)->locale('es')->isoFormat('ddd')))
            ->all();
    }

    // ── Interioridades ───────────────────────────────────────────

    /**
     * Hoy, en la hora de Chile.
     *
     * La aplicación guarda en UTC, así que `now()` a secas puede ir un día por
     * delante desde las 20:00 de Chile: el «hoy» del calendario se marcaría en
     * la casilla de mañana. Ver `App\Support\Fecha`.
     */
    private static function hoy(): Carbon
    {
        return Carbon::now(Fecha::zona())->startOfDay();
    }

    /** El día que trae un Carbon, rehecho en la zona de la pantalla. */
    private static function dia(Carbon $fecha): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'), Fecha::zona())->startOfDay();
    }

    private static function interpretarMes(?string $pedido): Carbon
    {
        if (! is_string($pedido) || ! preg_match('/^\d{4}-\d{2}$/', $pedido)) {
            return static::hoy()->startOfMonth();
        }

        try {
            $mes = Carbon::createFromFormat('Y-m-d', $pedido.'-01', Fecha::zona())->startOfMonth();
        } catch (\Throwable) {
            return static::hoy()->startOfMonth();
        }

        /*
         * `2026-13` pasa el patrón y no lanza nada: PHP lo desborda a enero de
         * 2027 tan tranquilo, y el calendario enseñaba un mes que nadie había
         * pedido. Que el resultado vuelva a escribirse igual que lo pedido es
         * lo que distingue un mes de verdad de uno desbordado.
         */
        if ($mes->format('Y-m') !== $pedido) {
            return static::hoy()->startOfMonth();
        }

        /*
         * Fuera del margen se vuelve al mes de hoy en vez de dar un 404. Un
         * `?mes=1204-07` escrito a mano no es un error del visitante que
         * merezca una pantalla de error, y dejar el margen abierto convierte
         * las flechas en un pozo sin fondo por el que un buscador puede
         * seguir bajando meses para siempre.
         */
        return static::dentroDeMargen($mes) ? $mes : static::hoy()->startOfMonth();
    }

    private static function dentroDeMargen(Carbon $mes): bool
    {
        $hoy = static::hoy()->startOfMonth();

        return $mes->between(
            $hoy->copy()->subMonthsNoOverflow(self::MESES_DE_MARGEN),
            $hoy->copy()->addMonthsNoOverflow(self::MESES_DE_MARGEN),
        );
    }

    /**
     * Día («Y-m-d») → las actividades que caen en él.
     *
     * El recorrido se recorta al mes que se está pintando: una actividad de dos
     * semanas que empieza en agosto sólo aporta sus días de septiembre.
     *
     * @param  Collection<int, Activity>  $actividades
     * @return Collection<string, Collection<int, Activity>>
     */
    private static function repartirPorDia(Collection $actividades, Carbon $desde, Carbon $hasta): Collection
    {
        $mapa = collect();

        foreach ($actividades as $actividad) {
            /*
             * Se rehacen en la zona de la pantalla a partir del día tal cual
             * está guardado. `fecha_inicio` es una columna DATE que Eloquent
             * devuelve como Carbon en UTC, y mezclar en la misma comparación
             * un Carbon en UTC con otro en Chile deja los días de borde
             * bailando cuatro horas: la actividad del día 1 caería en la
             * casilla del 31. Aquí todo va en la misma zona.
             */
            $inicio = static::dia($actividad->fecha_inicio)->max($desde);
            $fin = static::dia($actividad->fecha_termino ?? $actividad->fecha_inicio)->min($hasta);

            // Una `fecha_termino` anterior al inicio es un dato imposible que
            // el formulario no deja meter, pero si llegara dejaría el bucle
            // sin dar una sola vuelta y la actividad no se pintaría en ningún
            // sitio. Se pinta al menos su día de inicio.
            if ($fin->lt($inicio)) {
                $fin = $inicio->copy();
            }

            for ($dia = $inicio->copy(); $dia->lte($fin); $dia->addDay()) {
                $mapa->put(
                    $clave = $dia->format('Y-m-d'),
                    $mapa->get($clave, collect())->push($actividad),
                );
            }
        }

        return $mapa;
    }

    /**
     * Las semanas de la rejilla, de lunes a domingo y con los días de relleno
     * de los meses vecinos, que son los que mantienen las columnas alineadas.
     *
     * @param  Collection<string, Collection<int, Activity>>  $porDia
     * @return Collection<int, Collection<int, array>>
     */
    private static function semanas(Carbon $mes, Collection $porDia): Collection
    {
        $hoy = static::hoy();
        $primera = $mes->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $ultima = $mes->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $dias = collect();

        for ($dia = $primera->copy(); $dia->lte($ultima); $dia->addDay()) {
            $actividades = $porDia->get($dia->format('Y-m-d'), collect());

            $dias->push([
                'fecha' => $dia->copy(),
                'delMes' => $dia->isSameMonth($mes),
                'esHoy' => $dia->isSameDay($hoy),
                'actividades' => $actividades,
            ]);
        }

        return $dias->chunk(7)->values();
    }
}
