<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Commune;
use App\Models\Region;
use App\Models\TaxonomyTerm;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $actividades = Activity::published()
            ->with(['commune', 'region', 'terms', 'organization'])
            ->byRegion($request->integer('region') ?: null)
            ->byCommune($request->integer('comuna') ?: null)
            ->byFormato($request->string('formato')->toString() ?: null)
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

    public function show(Activity $activity)
    {
        abort_unless($activity->estado === 'publicada', 404);

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
