<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\ActivityModerationService;
use App\Support\Filtro;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Cada estado tiene su nodo en el menu, asi que el estado puede venir fijo
     * desde la ruta. Sigue aceptandose por query para no romper los enlaces
     * que ya existian.
     */
    public function index(Request $request, ?string $estadoFijo = null)
    {
        $estado = $estadoFijo ?: Filtro::texto($request, 'estado');

        // Lo que se publicó solo, para poder repasarlo: sin este filtro no
        // aparece en «pendientes» y no hay forma de encontrarlo.
        $soloAutomaticas = $request->boolean('auto');

        // Las que volvieron corregidas de una petición de ajustes. Es a donde
        // apunta la alerta del escritorio.
        $soloVueltas = $request->boolean('vueltas');

        $actividades = Activity::with(['organization', 'commune', 'region'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($soloAutomaticas, fn ($q) => $q->where('publicada_automaticamente', true))
            ->when($soloVueltas, fn ($q) => $q->vueltasDeAjustes())
            ->when(Filtro::texto($request, 'q'), fn ($q, $b) => $q->where('titulo', 'like', "%{$b}%"))
            ->withCount(['registrations as inscritos' => fn ($q) => $q->where('estado', '!=', 'cancelado')])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $conteos = Activity::selectRaw('estado, COUNT(*) n')->groupBy('estado')->pluck('n', 'estado');
        $automaticas = Activity::where('publicada_automaticamente', true)->count();
        $vueltas = Activity::vueltasDeAjustes()->count();

        /*
         * Cuáles de las que se están enseñando vuelven de ajustes. Se resuelve
         * de una vez para toda la página en lugar de preguntárselo a cada fila:
         * `vuelveDeAjustes()` hace una consulta por actividad y en un listado
         * de veinte eso son veinte viajes a la base de datos.
         */
        $vuelvenDeAjustes = Activity::vueltasDeAjustes()
            ->whereIn('id', $actividades->pluck('id'))
            ->pluck('id')
            ->flip();

        return view('admin.activities.index', compact(
            'actividades', 'conteos', 'estado', 'estadoFijo', 'soloAutomaticas', 'automaticas',
            'soloVueltas', 'vueltas', 'vuelvenDeAjustes',
        ));
    }

    public function show(Activity $activity)
    {
        $activity->load(['organization.user', 'region', 'commune', 'terms', 'collaborators', 'statusLogs.user']);

        return view('admin.activities.show', compact('activity'));
    }

    public function approve(Request $request, Activity $activity, ActivityModerationService $moderacion)
    {
        $moderacion->cambiar($activity, 'publicada', $request->user());

        return back()->with('ok', 'Actividad publicada. Se avisó al organizador.');
    }

    public function requestChanges(Request $request, Activity $activity, ActivityModerationService $moderacion)
    {
        $datos = $request->validate([
            'comentario' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'comentario.required' => 'Escribe qué hay que ajustar: el organizador solo recibe este texto.',
            'comentario.min' => 'Explica el ajuste con un poco más de detalle.',
        ]);

        $moderacion->cambiar($activity, 'ajustes', $request->user(), $datos['comentario']);

        return back()->with('ok', 'Le pedimos ajustes al organizador.');
    }

    public function reject(Request $request, Activity $activity, ActivityModerationService $moderacion)
    {
        $moderacion->cambiar(
            $activity,
            'cancelada',
            $request->user(),
            Filtro::texto($request, 'comentario') ?: null,
        );

        return back()->with('ok', 'Actividad cancelada.');
    }

    public function toggleFeatured(Activity $activity)
    {
        $activity->update(['destacada' => ! $activity->destacada]);

        return back()->with('ok', $activity->destacada
            ? 'La actividad aparece ahora en el home.'
            : 'La actividad ya no aparece en el home.');
    }
}
