<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Filtro;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $organizaciones = Organization::with('user')
            ->withCount('activities')
            ->when(Filtro::texto($request, 'q'), fn ($q, $b) => $q->where('nombre', 'like', "%{$b}%"))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.organizations.index', compact('organizaciones'));
    }

    public function toggleVerified(Organization $organization)
    {
        $organization->update(['verificada' => ! $organization->verificada]);

        return back()->with('ok', 'Organización actualizada.');
    }
}
