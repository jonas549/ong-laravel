<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $inscritos = Registration::with('activity')
            ->when($request->string('q')->toString(), function ($q, $b) {
                $q->where(function ($w) use ($b) {
                    $w->where('nombre', 'like', "%{$b}%")->orWhere('correo', 'like', "%{$b}%");
                });
            })
            ->when($request->string('estado')->toString(), fn ($q, $e) => $q->where('estado', $e))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.registrations.index', compact('inscritos'));
    }
}
