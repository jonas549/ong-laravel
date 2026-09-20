<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Registration;
use App\Support\Filtro;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParticipantController extends Controller
{
    /** Cuántas filas muestra la tabla antes de pedir "Ver todos". */
    private const POR_PAGINA = 8;

    public function index(Request $request, Activity $activity)
    {
        $this->authorize('manageParticipants', $activity);

        $busqueda = Filtro::texto($request, 'q');
        $estado = Filtro::texto($request, 'estado');

        $consulta = $this->consulta($activity, $busqueda, $estado);

        $total = (clone $consulta)->count();
        $verTodos = $request->boolean('todos');

        $inscritos = $consulta
            ->when(! $verTodos, fn ($q) => $q->limit(self::POR_PAGINA))
            ->get();

        return view('account.participants.index', [
            'activity' => $activity,
            'inscritos' => $inscritos,
            'busqueda' => $busqueda,
            'estado' => $estado,
            'total' => $total,
            'verTodos' => $verTodos,
        ]);
    }

    /** "Exportar lista" — el .xlsx que anuncia el propio botón. */
    public function export(Request $request, Activity $activity): StreamedResponse
    {
        $this->authorize('manageParticipants', $activity);

        $inscritos = $this->consulta(
            $activity,
            Filtro::texto($request, 'q'),
            Filtro::texto($request, 'estado'),
        )->get();

        $archivo = Str::slug($activity->titulo).'-participantes.xlsx';

        return response()->streamDownload(function () use ($inscritos) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValuesWithStyle(
                ['ID', 'Nombre', 'Correo', 'Fecha inscripción', 'Mayor de edad', 'Baja'],
                (new Style)->withFontBold(true),
            ));

            foreach ($inscritos as $i) {
                $writer->addRow(Row::fromValues([
                    $i->id,
                    $i->nombre,
                    $i->correo,
                    $i->created_at->locale('es')->isoFormat('D MMM YYYY'),
                    $i->es_mayor_edad ? 'Sí' : 'No',
                    // La misma regla que la pantalla: sólo se dice quién se dio de baja.
                    $i->estado_visible ?? '',
                ]));
            }

            $writer->close();
        }, $archivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function updateCupos(Request $request, Activity $activity)
    {
        $this->authorize('manageParticipants', $activity);

        $datos = $request->validate([
            'cupos_disponibles' => ['required', 'integer', 'min:0', 'max:100000'],
        ], [], ['cupos_disponibles' => 'cupos disponibles']);

        $activity->update($datos);

        return back()->with('ok', 'Cupos actualizados.');
    }

    private function consulta(Activity $activity, string $busqueda, string $estado)
    {
        return $activity->registrations()
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->where(function ($w) use ($busqueda) {
                    $w->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('correo', 'like', "%{$busqueda}%");
                });
            })
            /*
             * Sólo dos filtros, y no los tres estados de la columna: desde el
             * 18/09 al organizador no se le enseña «Pendiente» —no lo cambia
             * nunca nadie—, así que filtrar por él devolvería la lista entera
             * y por «confirmado» una lista vacía. Ver `estado_visible`.
             */
            ->when($estado === 'cancelado', fn ($q) => $q->where('estado', 'cancelado'))
            ->when($estado === 'activas', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->latest('created_at');
    }
}
