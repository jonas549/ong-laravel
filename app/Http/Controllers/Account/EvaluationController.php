<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityEvaluation;
use App\Models\EvaluationPhoto;
use App\Services\Evaluaciones;
use App\Support\Fecha;
use App\Support\Filtro;
use App\Support\Listado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las evaluaciones que recibió una actividad, para su organizador.
 *
 * Hasta el 2026-09-20 esto sólo lo veía el administrador. Lo abrió el cliente,
 * y la condición con la que lo abrió es lo que hay que respetar al tocar este
 * archivo: **el organizador ve las de SUS actividades y ninguna más, tampoco
 * escribiendo la dirección a mano.**
 *
 * Por eso aquí no hay ni una consulta que arranque de `ActivityEvaluation` sin
 * pasar antes por una actividad autorizada. El permiso se pide por su nombre
 * —`viewEvaluations`— y lo resuelve `ActivityPolicy`, que es donde vive la
 * regla de a quién pertenece una actividad.
 *
 * Las fotos siguen en el disco privado. Abrir esta pantalla no abre los
 * archivos: cada uno se sirve por `foto()`, que vuelve a pedir el permiso
 * sobre la actividad de esa foto.
 */
class EvaluationController extends Controller
{
    /**
     * Todas las evaluaciones de las actividades de su organización.
     *
     * El listado nace de las actividades del organizador y no de la tabla de
     * evaluaciones: así el acotamiento no depende de que alguien se acuerde de
     * añadir un `where`, que es como se escapan estas cosas.
     */
    public function index(Request $request, Evaluaciones $evaluaciones)
    {
        $suyas = $this->actividadesDeSuOrganizacion($request);

        $consulta = ActivityEvaluation::query()
            ->whereIn('activity_id', $suyas->keys())
            ->when(Filtro::texto($request, 'actividad'), function ($q, $id) use ($suyas) {
                // Un id que no sea suyo no filtra: deja el listado como estaba,
                // que es lo mismo que si no hubiera pedido nada.
                if ($suyas->has((int) $id)) {
                    $q->where('activity_id', $id);
                }
            })
            ->when(Filtro::texto($request, 'foto') === 'con', fn ($q) => $q->conFoto());

        $resumen = $evaluaciones->resumen(
            (clone $consulta)->reorder()->get(['experiencia', 'motivacion'])
        );

        return view('account.evaluaciones.index', [
            'evaluaciones' => Listado::ordenar(
                $consulta->with(['activity', 'fotos']),
                $request,
                ['id', 'nombre', 'experiencia', 'motivacion', 'created_at'],
                'created_at',
                'desc',
            )->paginate(Listado::porPagina($request))->withQueryString(),
            'resumen' => $resumen,
            'actividades' => $suyas,
            'filtros' => [
                'actividad' => Filtro::texto($request, 'actividad'),
                'foto' => Filtro::texto($request, 'foto'),
            ],
            'escalas' => ActivityEvaluation::ESCALAS,
        ]);
    }

    /**
     * Sirve una fotografía del disco privado.
     *
     * **Ésta es la puerta que de verdad hay que vigilar.** La pantalla se puede
     * acotar con un `whereIn` y aun así dejar los archivos abiertos si la ruta
     * que los sirve no comprueba nada: bastaría con probar números hasta dar
     * con una foto ajena. Se pide el permiso sobre la actividad de esta foto.
     *
     * 404 y no 403, como en el resto de lo que se pide por la URL: un 403
     * confirmaría que esa fotografía existe.
     */
    public function foto(Request $request, EvaluationPhoto $foto): StreamedResponse
    {
        $actividad = $foto->evaluation?->activity;

        abort_unless($actividad && $request->user()?->can('viewEvaluations', $actividad), 404);
        abort_unless(Storage::disk('local')->exists($foto->ruta), 404);

        return Storage::disk('local')->response(
            $foto->ruta,
            null,
            ['Content-Disposition' => 'inline'],
        );
    }

    /**
     * Las actividades de su organización, en un mapa id => título.
     *
     * Se usa para dos cosas: acotar el listado y llenar el desplegable del
     * filtro. Que sean la misma lista no es casualidad —es lo que garantiza
     * que el filtro no pueda ofrecer algo que el listado no dejaría ver—.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function actividadesDeSuOrganizacion(Request $request)
    {
        $organizacion = $request->user()?->organization?->id;

        if (! $organizacion) {
            return collect();
        }

        return Activity::where('organization_id', $organizacion)
            ->orderByDesc('id')
            ->pluck('titulo', 'id');
    }
}
