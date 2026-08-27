<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Commune;
use App\Models\Region;
use App\Models\TaxonomyTerm;
use App\Support\Filtro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $actividades = Activity::published()
            ->with(['commune', 'region', 'terms', 'organization'])
            ->byRegion($request->integer('region') ?: null)
            ->byCommune($request->integer('comuna') ?: null)
            ->byFormato(Filtro::texto($request, 'formato') ?: null)
            ->byTerm($request->integer('tema') ?: null)
            ->ordered()
            ->paginate(12)
            ->withQueryString();

        return view('public.activities.index', [
            'actividades' => $actividades,
            'regiones' => Region::ordered()->get(),
            'comunas' => $request->integer('region')
                ? Commune::where('region_id', $request->integer('region'))->orderBy('nombre')->get()
                : collect(),
            'temas' => TaxonomyTerm::grupo('tema')->activos()->ordered()->get(),
            'formatos' => Activity::FORMATOS,
        ]);
    }

    public function show(Request $request, Activity $activity)
    {
        // La ficha es pública sólo si está publicada; su organización puede
        // verla igual, que es lo que hace el botón "Vista previa" del editor.
        //
        // 404 y no 403: un 403 confirmaría que esa dirección existe, y una ficha
        // sin publicar no debería ni asomar para quien no es de la casa.
        abort_unless(Gate::allows('view', $activity), 404);

        $activity->load(['organization', 'region', 'commune', 'terms', 'collaborators']);

        $relacionadas = Activity::published()
            ->where('id', '!=', $activity->id)
            ->when($activity->region_id, fn ($q) => $q->where('region_id', $activity->region_id))
            ->with(['commune', 'region', 'terms'])
            ->take(3)
            ->get();

        return view('public.activities.show', compact('activity', 'relacionadas'));
    }
}
