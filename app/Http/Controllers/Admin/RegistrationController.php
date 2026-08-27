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
            'nombre', 'correo', 'estado', 'created_at',
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
        $inscritos = $this->consulta($request)->with('activity.organization')->get();

        $archivo = 'inscripciones-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($inscritos) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValuesWithStyle(
                ['Nombre', 'Correo', 'Actividad', 'Organización', 'Fecha de inscripción', 'Estado'],
                (new Style)->withFontBold(true),
            ));

            foreach ($inscritos as $i) {
                $writer->addRow(Row::fromValues([
                    $i->nombre,
                    $i->correo,
                    $i->activity?->titulo ?? '(actividad borrada)',
                    $i->activity?->organization?->nombre ?? '',
                    $i->created_at->locale('es')->isoFormat('D MMM YYYY HH:mm'),
                    $i->estado,
                ]));
            }

            $writer->close();
        }, $archivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
