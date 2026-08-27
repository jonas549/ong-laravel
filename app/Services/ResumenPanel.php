<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityStatusLog;
use App\Models\EmailLog;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Los números de la portada del panel.
 *
 * Todo sale de una consulta. No hay un solo valor escrito a mano ni datos de
 * ejemplo: si la base está vacía, la pantalla enseña ceros, que es la verdad.
 *
 * Vive fuera del controlador porque cada número lleva detrás una decisión
 * —qué cuenta como organización activa, desde cuándo se mide una espera— y esas
 * decisiones hay que poder leerlas en un sitio, no repartidas por una vista.
 */
class ResumenPanel
{
    /** Cuántas semanas mira el gráfico de evolución. */
    public const SEMANAS = 12;

    /**
     * Las cifras de cabecera.
     *
     * Cada una lleva su definición al lado porque «organizaciones activas» y
     * «inscritos» admiten más de una lectura, y un número sin definición es un
     * número que nadie puede comprobar.
     *
     * @return array<string, mixed>
     */
    public function kpis(): array
    {
        $porEstado = $this->porEstado();

        return [
            'porEstado' => $porEstado,
            'actividades' => $porEstado->sum(),
            'pendientes' => $porEstado['revision'] ?? 0,
            'publicadas' => $porEstado['publicada'] ?? 0,

            // Sin las canceladas: una inscripción cancelada no es una persona
            // que vaya a presentarse, y este número se lee como aforo.
            'inscritos' => Registration::activas()->count(),
            'inscritosConfirmados' => Registration::confirmadas()->count(),
            'inscritosCancelados' => Registration::where('estado', 'cancelado')->count(),

            /*
             * «Activa» = tiene al menos una actividad publicada. Es la lectura
             * útil para una campaña: mide quién está participando de verdad,
             * no quién se registró y se quedó a medias. Las otras dos lecturas
             * posibles —total y verificadas— van al lado para que se vea la
             * diferencia en vez de tener que suponerla.
             */
            'organizacionesActivas' => Organization::whereHas('activities', fn ($q) => $q->where('estado', 'publicada'))->count(),
            'organizaciones' => Organization::count(),
            'organizacionesVerificadas' => Organization::where('verificada', true)->count(),
            'organizacionesPorVerificar' => Organization::where('verificada', false)->count(),
        ];
    }

    /**
     * Actividades por estado, con los cinco estados siempre presentes.
     *
     * `groupBy` sólo devuelve los estados que existen en la tabla, así que sin
     * este relleno la lista se encogía y crecía sola según lo que hubiera ese
     * día: un estado en cero desaparecía de la pantalla en vez de decir cero.
     *
     * @return Collection<string, int>
     */
    public function porEstado(): Collection
    {
        $reales = Activity::selectRaw('estado, COUNT(*) n')->groupBy('estado')->pluck('n', 'estado');

        return collect(Activity::ESTADOS)->map(fn ($meta, $clave) => (int) ($reales[$clave] ?? 0));
    }

    /**
     * Evolución semanal de actividades creadas e inscripciones recibidas.
     *
     * Las semanas se arman en PHP y no con `YEARWEEK` de MySQL a propósito: así
     * el corte de semana lo decide Carbon —el mismo que pinta las fechas en el
     * resto del panel— y no la numeración de semanas del motor, que además
     * cambia según el modo que se le pase.
     *
     * @return array<string, mixed>
     */
    public function evolucion(int $semanas = self::SEMANAS): array
    {
        $desde = Carbon::now()->startOfWeek()->subWeeks($semanas - 1);

        $actividades = $this->porDia('activities', $desde, fn ($q) => $q->whereNull('deleted_at'));
        $inscripciones = $this->porDia('registrations', $desde, fn ($q) => $q->where('estado', '!=', 'cancelado'));

        $puntos = [];

        for ($i = 0; $i < $semanas; $i++) {
            $lunes = (clone $desde)->addWeeks($i);
            $domingo = (clone $lunes)->endOfWeek();

            $suma = function (array $dias) use ($lunes, $domingo) {
                $total = 0;

                foreach ($dias as $fecha => $n) {
                    if ($fecha >= $lunes->toDateString() && $fecha <= $domingo->toDateString()) {
                        $total += $n;
                    }
                }

                return $total;
            };

            $puntos[] = [
                'etiqueta' => $lunes->locale('es')->isoFormat('D MMM'),
                'desde' => $lunes->toDateString(),
                'hasta' => $domingo->toDateString(),
                'actividades' => $suma($actividades),
                'inscripciones' => $suma($inscripciones),
            ];
        }

        return [
            'puntos' => $puntos,
            'semanas' => $semanas,
            'totalActividades' => array_sum(array_column($puntos, 'actividades')),
            'totalInscripciones' => array_sum(array_column($puntos, 'inscripciones')),
            'techo' => max(1, max(array_merge(array_column($puntos, 'actividades'), array_column($puntos, 'inscripciones')))),
        ];
    }

    /**
     * Filas por día, para repartir después en semanas.
     *
     * Son como mucho 84 filas —doce semanas— así que traerlas y agruparlas en
     * memoria sale más barato que discutir con el motor sobre qué es una
     * semana.
     *
     * @return array<string, int>
     */
    private function porDia(string $tabla, Carbon $desde, ?callable $filtro = null): array
    {
        $consulta = DB::table($tabla)->where('created_at', '>=', $desde);

        if ($filtro) {
            $filtro($consulta);
        }

        return $consulta
            ->selectRaw('DATE(created_at) dia, COUNT(*) n')
            ->groupBy('dia')
            ->pluck('n', 'dia')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Lo que está esperando revisión, lo que lleva más tiempo esperando primero.
     *
     * El momento en que entró a revisión sale de `activity_status_logs`, no de
     * `updated_at`: cualquier otro cambio posterior movería `updated_at` y la
     * espera se reiniciaría sola, justo en el número que sirve para saber a
     * quién se está haciendo esperar. Si no hay registro —actividades anteriores
     * al historial— se cae a `updated_at`, que es lo único que hay.
     *
     * @return Collection<int, Activity>
     */
    public function pendientesDeRevision(int $limite = 8): Collection
    {
        return Activity::query()
            ->where('estado', 'revision')
            ->with('organization')
            ->withCount(['registrations as inscritos' => fn ($q) => $q->where('estado', '!=', 'cancelado')])
            ->addSelect(['enviada_at' => ActivityStatusLog::selectRaw('MAX(created_at)')
                ->whereColumn('activity_id', 'activities.id')
                ->where('a_estado', 'revision'),
            ])
            ->orderByRaw('COALESCE((SELECT MAX(created_at) FROM activity_status_logs WHERE activity_id = activities.id AND a_estado = ?), activities.updated_at) ASC', ['revision'])
            ->take($limite)
            ->get()
            ->each(function (Activity $a) {
                $a->esperando_desde = $a->enviada_at ? Carbon::parse($a->enviada_at) : $a->updated_at;
                $a->dias_esperando = $a->esperando_desde->diffInDays(Carbon::now());
            });
    }

    /**
     * @return Collection<int, Registration>
     */
    public function ultimasInscripciones(int $limite = 8): Collection
    {
        return Registration::with('activity.organization')
            ->latest('created_at')
            ->take($limite)
            ->get();
    }

    /**
     * Lo que está roto o a punto de estarlo.
     *
     * El correo tiene su propio aviso —`partials/admin/salud-correo`, que ya
     * sabe distinguir un transporte que no entrega de una cola parada— así que
     * aquí sólo van las alertas que ese no cubre. Duplicarlas dejaría la misma
     * avería contada dos veces con dos redacciones distintas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function alertas(): array
    {
        $alertas = [];

        $dias = (int) Setting::get('alerta_revision_dias', 3);

        $atrasadas = Activity::where('estado', 'revision')
            ->whereRaw('COALESCE((SELECT MAX(created_at) FROM activity_status_logs WHERE activity_id = activities.id AND a_estado = ?), activities.updated_at) < ?', ['revision', Carbon::now()->subDays($dias)])
            ->count();

        if ($atrasadas) {
            $alertas[] = [
                'nivel' => 'error',
                'titulo' => $atrasadas === 1
                    ? 'Hay una actividad esperando revisión hace más de '.$dias.' días.'
                    : 'Hay '.$atrasadas.' actividades esperando revisión hace más de '.$dias.' días.',
                'texto' => 'Quien las envió no tiene forma de saber si llegaron. El plazo se cambia en Configuración → General.',
                'accion' => ['Revisarlas', route('admin.activities.pendientes')],
            ];
        }

        $fallidos = EmailLog::fallidos()->count();

        if ($fallidos) {
            $alertas[] = [
                'nivel' => 'error',
                'titulo' => $fallidos === 1 ? 'Un correo no llegó a su destino.' : $fallidos.' correos no llegaron a su destino.',
                'texto' => 'Están en el registro con el error que devolvió el servidor, y se pueden reenviar desde ahí.',
                'accion' => ['Ver el registro', route('admin.emails.index', ['estado' => 'failed'])],
            ];
        }

        $sinVerificar = Organization::where('verificada', false)->count();

        if ($sinVerificar) {
            $alertas[] = [
                'nivel' => 'info',
                'titulo' => $sinVerificar === 1
                    ? 'Una organización está sin verificar.'
                    : $sinVerificar.' organizaciones están sin verificar.',
                'texto' => 'Verificar no bloquea nada: es la marca de que alguien comprobó quiénes son.',
                'accion' => ['Verificarlas', route('admin.organizations.verificacion')],
            ];
        }

        return $alertas;
    }
}
