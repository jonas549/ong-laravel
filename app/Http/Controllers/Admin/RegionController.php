<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Support\Filtro;
use Illuminate\Http\Request;

/**
 * Regiones y comunas.
 *
 * Se consultan pero no se editan: son la división administrativa de Chile, no
 * un catálogo de la ONG. Si algún día hay que corregir un nombre, se hace por
 * migración para que todas las instalaciones queden igual.
 */
class RegionController extends Controller
{
    public function index(Request $request)
    {
        $busqueda = Filtro::texto($request, 'q');

        $regiones = Region::query()
            ->with(['communes' => fn ($q) => $q->orderBy('nombre')])
            ->withCount('communes')
            ->when($busqueda, function ($q, $b) {
                $b = Filtro::like($b);

                $q->where(function ($w) use ($b) {
                    $w->where('nombre', 'like', "%{$b}%")
                        ->orWhereHas('communes', fn ($c) => $c->where('nombre', 'like', "%{$b}%"));
                });
            })
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('admin.regiones.index', [
            'regiones' => $regiones,
            'busqueda' => $busqueda,
            'totalComunas' => $regiones->sum('communes_count'),
        ]);
    }
}
