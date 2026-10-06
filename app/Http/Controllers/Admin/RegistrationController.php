<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Registration;
use App\Support\Filtro;
use App\Support\Listado;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $inscritos = Registration::query()
            ->when(Filtro::texto($request, 'q'), function ($q, $b) {
                $q->where(function ($w) use ($b) {
                    $w->where('nombre', 'like', "%{$b}%")->orWhere('correo', 'like', "%{$b}%");
                });
            })
            /*
             * `estado=activas` no es un estado de la tabla: es «todas menos las
             * canceladas», que es justo lo que cuenta el KPI de la portada. Sin
             * esto, la tarjeta llevaba al listado completo y enseñaba más filas
             * que el número que se acababa de pulsar.
             */
            ->when(Filtro::texto($request, 'estado') === 'activas', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->when(
                in_array(Filtro::texto($request, 'estado'), Registration::ESTADOS, true),
                fn ($q) => $q->where('estado', Filtro::texto($request, 'estado')),
            )
            ->with('activity');

        $inscritos = Listado::ordenar($inscritos, $request, [
            // Sin 'estado': la columna se quitó (C3) y ordenar por lo que no
            // se ve deja la tabla barajada sin que nada lo explique.
            'id', 'nombre', 'correo', 'created_at',
        ], 'created_at', 'desc')
            ->paginate(Listado::porPagina($request))
            ->withQueryString();

        return view('admin.registrations.index', [
            'inscritos' => $inscritos,
            'estado' => Filtro::texto($request, 'estado'),
            'estados' => Registration::ESTADOS,
        ]);
    }

    /**
     * Pantalla de exportación.
     *
     * Separada del listado porque exportar es otra tarea: se elige el recorte
     * y se descarga. El organizador ya podía exportar los suyos; esto es lo
     * mismo para toda la edición.
     */
    public function exportar(Request $request)
    {
        return view('admin.registrations.exportar', [
            'filtros' => $this->filtros($request),
            'estados' => Registration::ESTADOS,
            // Sólo las que pueden tener inscritos (punto 5 del 30/09).
            'actividades' => Activity::conInscripcion()->orderBy('titulo')->pluck('titulo', 'id'),
            'cuantos' => $this->consulta($request)->count(),
        ]);
    }

    /**
     * La descarga en sí.
     *
     * Tanda del 05/10: SÓLO los participantes. Las columnas de la actividad
     * que se le añadieron el 23/09 se fueron a su propia exportación
     * (Actividades → Exportar); aquí queda lo justo para saber a qué se
     * inscribió cada persona. El ID sigue primero, como en todas.
     */
    public function descargar(Request $request): StreamedResponse
    {
        $inscritos = $this->consulta($request)
            ->with(['activity.organization'])
            ->get();

        /*
         * «¿Respondió la evaluación?», cruzando por correo y por actividad: la
         * encuesta no pide inscribirse, así que no hay otra llave. Se cargan
         * una sola vez las parejas que respondieron, y cada fila sólo mira si
         * la suya está. Sin tildes ni mayúsculas en el correo.
         */
        $evaluaron = \App\Models\ActivityEvaluation::query()
            ->whereIn('activity_id', $inscritos->pluck('activity_id')->unique())
            ->get(['activity_id', 'correo'])
            ->mapWithKeys(fn ($e) => [$e->activity_id.'|'.mb_strtolower(trim($e->correo)) => true]);

        $archivo = 'inscripciones-'.\App\Support\Fecha::iso(now()).'.xlsx';

        return response()->streamDownload(function () use ($inscritos, $evaluaron) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValuesWithStyle(
                /*
                 * Sin «Estado» (C3). Pintaba «Pendiente» en todas las filas:
                 * nada en la aplicación pasa nunca una inscripción a
                 * «confirmado». Lo que sí importa —si se dio de baja— va en su
                 * propia columna, que se lee sola.
                 */
                ['ID', 'Nombre', 'Correo', 'Mayor de edad', 'Actividad', 'Organización',
                    'Fecha de inscripción', 'Fecha de la actividad', 'Respondió la evaluación', 'Baja'],
                (new Style)->withFontBold(true),
            ));

            foreach ($inscritos as $i) {
                $a = $i->activity;

                $writer->addRow(Row::fromValues([
                    $i->id,
                    $i->nombre,
                    $i->correo,
                    $i->es_mayor_edad ? 'Sí' : 'No',
                    $a?->titulo ?? '(actividad borrada)',
                    $a?->organization?->nombre ?? '',
                    \App\Support\Fecha::conHora($i->created_at),
                    $a ? self::fechaDeActividad($a) : '',
                    isset($evaluaron[$i->activity_id.'|'.mb_strtolower(trim($i->correo))]) ? 'Sí' : 'No',
                    $i->estado === 'cancelado' ? 'Cancelada' : '',
                ]));
            }

            $writer->close();
        }, $archivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * «04-12-2026», o «04-12-2026 al 06-12-2026» si dura varios días.
     *
     * Tal cual, sin pasar por `Fecha`: ésa convierte de zona horaria, y un día
     * sin hora a medianoche podía acabar en el día anterior.
     */
    public static function fechaDeActividad(Activity $a): string
    {
        if ($a->sin_fecha_definida || ! $a->fecha_inicio) {
            return 'Por definir';
        }

        $ini = $a->fecha_inicio->format('d-m-Y');

        return $a->fecha_termino && ! $a->fecha_termino->isSameDay($a->fecha_inicio)
            ? $ini.' al '.$a->fecha_termino->format('d-m-Y')
            : $ini;
    }

    /** @return array<string, string> */
    private function filtros(Request $request): array
    {
        return [
            'q' => Filtro::texto($request, 'q'),
            'estado' => Filtro::texto($request, 'estado'),
            'actividad' => Filtro::texto($request, 'actividad'),
            'desde' => Filtro::texto($request, 'desde'),
            'hasta' => Filtro::texto($request, 'hasta'),
        ];
    }

    private function consulta(Request $request)
    {
        $f = $this->filtros($request);

        return Registration::query()
            ->when($f['q'], function ($q, $b) {
                $b = Filtro::like($b);

                $q->where(function ($w) use ($b) {
                    $w->where('nombre', 'like', "%{$b}%")->orWhere('correo', 'like', "%{$b}%");
                });
            })
            /*
             * «Sin las canceladas» llega como `activas`, que no es un estado
             * de la tabla: buscarlo tal cual devolvía cero (visto el 06/10).
             */
            ->when($f['estado'] === 'activas', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->when($f['estado'] && $f['estado'] !== 'activas', fn ($q) => $q->where('estado', $f['estado']))
            ->when($f['actividad'], fn ($q, $a) => $q->where('activity_id', $a))
            ->when($f['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['hasta'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id');
    }
}
