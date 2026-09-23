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
            'actividades' => Activity::orderBy('titulo')->pluck('titulo', 'id'),
            'cuantos' => $this->consulta($request)->count(),
        ]);
    }

    /** La descarga en sí. */
    public function descargar(Request $request): StreamedResponse
    {
        $inscritos = $this->consulta($request)
            ->with(['activity.organization', 'activity.terms', 'activity.collaborators', 'activity.commune', 'activity.region'])
            ->get();

        $archivo = 'inscripciones-'.\App\Support\Fecha::iso(now()).'.xlsx';

        return response()->streamDownload(function () use ($inscritos) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValuesWithStyle(
                /*
                 * Sin «Estado» (C3). Pintaba «Pendiente» en todas las filas:
                 * nada en la aplicación pasa nunca una inscripción a
                 * «confirmado». Lo que sí importa —si se dio de baja— va en su
                 * propia columna, que se lee sola.
                 */
                [...['ID', 'Nombre', 'Correo', 'Actividad', 'Organización', 'Fecha de inscripción', 'Baja'], ...self::COLUMNAS_ACTIVIDAD],
                (new Style)->withFontBold(true),
            ));

            foreach ($inscritos as $i) {
                $writer->addRow(Row::fromValues([
                    $i->id,
                    $i->nombre,
                    $i->correo,
                    $i->activity?->titulo ?? '(actividad borrada)',
                    $i->activity?->organization?->nombre ?? '',
                    \App\Support\Fecha::conHora($i->created_at),
                    $i->estado === 'cancelado' ? 'Cancelada' : '',
                    ...self::datosDeActividad($i->activity),
                ]));
            }

            $writer->close();
        }, $archivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Punto 6 del 23/09: cada fila trae además la actividad entera, para que
     * la hoja sirva sin tener que cruzarla con otra. Van detrás de las del
     * participante, y el ID sigue siendo la primera columna.
     */
    private const COLUMNAS_ACTIVIDAD = [
        'Fecha de inicio', 'Fecha de término', 'Hora de inicio', 'Hora de término',
        'Dirección', 'Comuna', 'Región', 'Formato',
        'Requiere inscripción previa', 'Cupos totales', 'Cupos disponibles',
        'Descripción', 'Temas', 'Características', 'Dirigido a', 'Colaboración',
        'Sitio web', 'Red social',
    ];

    /** @return list<string|int> Una celda por cada `COLUMNAS_ACTIVIDAD`. */
    private static function datosDeActividad(?Activity $a): array
    {
        if (! $a) {
            return array_fill(0, count(self::COLUMNAS_ACTIVIDAD), '');
        }

        /*
         * La fecha se escribe tal cual, sin pasar por `Fecha`: ésa convierte
         * de zona horaria, y un día sin hora a medianoche podía acabar en el
         * día anterior.
         */
        $dia = fn ($f) => $f ? $f->format('d-m-Y') : '';
        $hora = fn ($h) => $h ? substr((string) $h, 0, 5) : '';
        $terminos = fn (string $grupo) => $a->termsDe($grupo)->pluck('nombre')->implode(', ');

        $dirigido = collect([$terminos('publico'), $a->publico_otro])->filter()->implode(', ');

        return [
            $a->sin_fecha_definida ? 'Por definir' : $dia($a->fecha_inicio),
            $a->sin_fecha_definida ? '' : $dia($a->fecha_termino),
            $hora($a->hora_inicio),
            $hora($a->hora_termino),
            $a->direccion ?? '',
            $a->commune?->nombre ?? '',
            $a->region?->nombre ?? '',
            $a->formato ?? '',
            $a->inscripcion_habilitada ? 'Sí' : 'No',
            $a->cupos_totales ?? '',
            $a->cupos_disponibles ?? '',
            $a->descripcion ?? '',
            $terminos('tema'),
            $terminos('caracteristica'),
            $dirigido,
            $a->collaborators->map(fn ($c) => $c->tipo ? "{$c->nombre} ({$c->tipo})" : $c->nombre)->implode(', '),
            $a->organization?->enlace_web ?? '',
            $a->organization?->enlace_red_social ?? '',
        ];
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
            ->when($f['estado'], fn ($q, $e) => $q->where('estado', $e))
            ->when($f['actividad'], fn ($q, $a) => $q->where('activity_id', $a))
            ->when($f['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['hasta'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id');
    }
}
