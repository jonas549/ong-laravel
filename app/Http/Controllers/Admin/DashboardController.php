<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\EmailLog;
use App\Models\Organization;
use App\Models\Registration;

class DashboardController extends Controller
{
    public function index()
    {
        $porEstado = Activity::selectRaw('estado, COUNT(*) n')->groupBy('estado')->pluck('n', 'estado');

        return view('admin.dashboard', [
            'porEstado' => $porEstado,
            'totalActividades' => $porEstado->sum(),
            'pendientes' => $porEstado['revision'] ?? 0,
            'organizaciones' => Organization::count(),
            'inscritos' => Registration::where('estado', '!=', 'cancelado')->count(),
            'correosFallidos' => EmailLog::fallidos()->count(),
            'ultimas' => Activity::with('organization')->latest('updated_at')->take(8)->get(),
        ]);
    }
}
