<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Support\Filtro;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $inscritos = Registration::with('activity')
            ->when(Filtro::texto($request, 'q'), function ($q, $b) {
                $q->where(function ($w) use ($b) {
                    $w->where('nombre', 'like', "%{$b}%")->orWhere('correo', 'like', "%{$b}%");
                });
            })
            ->when(Filtro::texto($request, 'estado'), fn ($q, $e) => $q->where('estado', $e))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.registrations.index', compact('inscritos'));
    }
}
