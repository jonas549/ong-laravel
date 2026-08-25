<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Filtro;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request, bool $soloPendientes = false)
    {
        $organizaciones = Organization::with('user')
            ->withCount('activities')
            ->when(Filtro::texto($request, 'q'), fn ($q, $b) => $q->where('nombre', 'like', '%'.Filtro::like($b).'%'))
            ->when($soloPendientes, fn ($q) => $q->where('verificada', false))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.organizations.index', [
            'organizaciones' => $organizaciones,
            'soloPendientes' => $soloPendientes,
            'pendientes' => Organization::where('verificada', false)->count(),
        ]);
    }

    /** Las que esperan verificacion, que es lo que se revisa a diario. */
    public function verificacion(Request $request)
    {
        return $this->index($request, true);
    }

    public function toggleVerified(Organization $organization)
    {
        $organization->update(['verificada' => ! $organization->verificada]);

        return back()->with('ok', 'Organización actualizada.');
    }
}
