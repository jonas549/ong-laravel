<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxonomyTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaxonomyController extends Controller
{
    public function index(Request $request)
    {
        $grupo = $request->string('grupo')->toString() ?: 'tema';

        abort_unless(isset(TaxonomyTerm::GRUPOS[$grupo]), 404);

        return view('admin.taxonomies.index', [
            'grupo' => $grupo,
            'grupos' => TaxonomyTerm::GRUPOS,
            'limite' => TaxonomyTerm::limiteDe($grupo),
            'terminos' => TaxonomyTerm::grupo($grupo)->ordered()->withCount('activities')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'grupo' => ['required', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:255'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ], [], ['nombre' => 'el nombre']);

        abort_unless(isset(TaxonomyTerm::GRUPOS[$datos['grupo']]), 404);

        TaxonomyTerm::create($datos + [
            'slug' => Str::slug($datos['nombre']),
            'activo' => true,
        ]);

        return back()->with('ok', 'Término agregado.');
    }

    public function update(Request $request, TaxonomyTerm $term)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ], [], ['nombre' => 'el nombre']);

        $term->update([
            'nombre' => $datos['nombre'],
            'orden' => $datos['orden'] ?? $term->orden,
            'activo' => $request->boolean('activo'),
        ]);

        return back()->with('ok', 'Término actualizado.');
    }

    public function destroy(TaxonomyTerm $term)
    {
        if ($term->activities()->exists()) {
            return back()->with(
                'error',
                'Ese término está en uso por al menos una actividad. Desactívalo en vez de borrarlo.',
            );
        }

        $term->delete();

        return back()->with('ok', 'Término eliminado.');
    }
}
