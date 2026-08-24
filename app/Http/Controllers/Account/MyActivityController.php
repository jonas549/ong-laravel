<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\ActivityModerationService;
use Illuminate\Http\Request;

class MyActivityController extends Controller
{
    public function index(Request $request)
    {
        $organizacion = $request->user()->organization;

        if (! $organizacion) {
            return view('account.activities.index', [
                'actividades' => collect(),
                'filtros' => collect(),
                'filtroActivo' => 'Todas',
            ]);
        }

        $base = Activity::where('organization_id', $organizacion->id);

        $conteos = (clone $base)->selectRaw('estado, COUNT(*) n')->groupBy('estado')->pluck('n', 'estado');

        $filtros = collect(['Todas' => (clone $base)->count()]);

        foreach (Activity::ESTADOS as $clave => $meta) {
            $filtros->put($meta['filtro'], $conteos[$clave] ?? 0);
        }

        $filtroActivo = $request->string('filtro')->toString() ?: 'Todas';

        $estadoBuscado = collect(Activity::ESTADOS)
            ->search(fn ($m) => $m['filtro'] === $filtroActivo);

        $actividades = (clone $base)
            ->when($estadoBuscado, fn ($q) => $q->where('estado', $estadoBuscado))
            ->with(['commune', 'region', 'terms'])
            ->withCount(['registrations as inscritos' => fn ($q) => $q->where('estado', '!=', 'cancelado')])
            ->latest('updated_at')
            ->get();

        return view('account.activities.index', compact('actividades', 'filtros', 'filtroActivo'));
    }

    public function cancel(Request $request, Activity $activity, ActivityModerationService $moderacion)
    {
        $this->autorizar($request, $activity);

        $moderacion->cambiar($activity, 'cancelada', $request->user(), 'Cancelada por el organizador.');

        return redirect()
            ->route('account.activities.index')
            ->with('ok', 'La actividad fue cancelada.');
    }

    public function submitForReview(Request $request, Activity $activity, ActivityModerationService $moderacion)
    {
        $this->autorizar($request, $activity);

        $moderacion->cambiar($activity, 'revision', $request->user());

        return back()->with('ok', 'Enviamos tu actividad a revisión.');
    }

    private function autorizar(Request $request, Activity $activity): void
    {
        abort_unless(
            $activity->organization_id === $request->user()->organization?->id,
            403,
            'Esta actividad no es de tu organización.',
        );
    }
}
