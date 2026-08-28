<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\Region;
use App\Services\Exportador;
use App\Support\Filtro;
use App\Support\Listado;
use Illuminate\Http\Request;

/**
 * Regiones y comunas.
 *
 * **Se consultan y se apagan, pero no se crean ni se borran.** Son la division
 * administrativa de Chile, no un catalogo de la ONG: un nombre mal escrito se
 * corrige por migracion, para que todas las instalaciones queden igual.
 *
 * Lo que si hacia falta es poder apagar una comuna que ya no aplica, para que
 * deje de salir en los selectores del wizard. Borrarla no vale: hay actividades
 * apuntando a ella y se quedarian sin ubicacion, sin arreglo desde el panel.
 *
 * El listado es de comunas y no de regiones, porque son las comunas lo que se
 * busca y lo que se apaga; la region es una columna mas y un filtro.
 */
class RegionController extends Controller
{
    public function index(Request $request)
    {
        $estado = Filtro::texto($request, 'estado');

        $consulta = Commune::query()
            ->with('region')
            ->withCount('activities')
            ->when(Filtro::texto($request, 'q'), fn ($q, $b) => $q->where('nombre', 'like', '%'.Filtro::like($b).'%'))
            ->when((int) Filtro::texto($request, 'region'), fn ($q, $r) => $q->where('region_id', $r))
            ->when($estado !== '', fn ($q) => $q->where('activo', $estado === 'si'));

        return view('admin.regiones.index', [
            'comunas' => Listado::ordenar($consulta, $request, ['nombre', 'activo'], 'nombre')
                ->paginate(Listado::porPagina($request))
                ->withQueryString(),
            'regiones' => Region::orderBy('orden')->orderBy('nombre')->get(),
            'apagadas' => Commune::where('activo', false)->count(),
        ]);
    }

    /**
     * Enciende o apaga una comuna.
     *
     * Apagarla no toca las actividades que ya la usan: siguen mostrando su
     * ubicacion. Lo unico que cambia es que deja de ofrecerse al publicar.
     */
    public function alternarComuna(Commune $commune)
    {
        $commune->update(['activo' => ! $commune->activo]);

        return back()->with('ok', $commune->activo
            ? "«{$commune->nombre}» vuelve a ofrecerse al publicar."
            : "«{$commune->nombre}» ya no se ofrece al publicar. Las actividades que estan ahi no cambian.");
    }

    /** Y lo mismo para una region entera, con todas sus comunas. */
    public function alternarRegion(Request $request, Region $region)
    {
        $encender = ! $region->activo;

        $region->update(['activo' => $encender]);
        $region->communes()->update(['activo' => $encender]);

        return back()->with('ok', $encender
            ? "«{$region->nombre}» y sus comunas vuelven a ofrecerse."
            : "«{$region->nombre}» y sus comunas ya no se ofrecen al publicar.");
    }

    public function exportar(Request $request, Exportador $exportador)
    {
        $formato = Filtro::texto($request, 'formato') === 'csv' ? 'csv' : 'xlsx';

        $filas = (function () {
            foreach (Commune::with('region')->withCount('activities')->orderBy('nombre')->cursor() as $c) {
                yield [$c->nombre, $c->region?->nombre, $c->activo ? 'Si' : 'No', $c->activities_count];
            }
        })();

        return $exportador->descargar($formato, 'Comunas',
            ['Comuna', 'Region', 'Se ofrece', 'Actividades'], $filas);
    }
}
