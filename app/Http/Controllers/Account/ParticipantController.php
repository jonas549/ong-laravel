<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function index(Request $request, Activity $activity)
    {
        abort_unless(
            $activity->organization_id === $request->user()->organization?->id,
            403,
            'Esta actividad no es de tu organización.',
        );

        $busqueda = $request->string('q')->toString();

        $inscritos = $activity->registrations()
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->where(function ($w) use ($busqueda) {
                    $w->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('correo', 'like', "%{$busqueda}%");
                });
            })
            ->latest('created_at')
            ->get();

        return view('account.participants.index', compact('activity', 'inscritos', 'busqueda'));
    }

    public function updateCupos(Request $request, Activity $activity)
    {
        abort_unless(
            $activity->organization_id === $request->user()->organization?->id,
            403,
        );

        $datos = $request->validate([
            'cupos_disponibles' => ['required', 'integer', 'min:0', 'max:100000'],
        ], [], ['cupos_disponibles' => 'cupos disponibles']);

        $activity->update($datos);

        return back()->with('ok', 'Cupos actualizados.');
    }
}
