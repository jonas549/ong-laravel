<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxonomyTerm;
use App\Services\Exportador;
use App\Support\Filtro;
use App\Support\Listado;
use App\Support\Papelera;
use App\Support\Texto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Los cuatro catálogos: temas, características, públicos y accesibilidad.
 *
 * Son una sola tabla con una columna `grupo`, así que este controlador sirve a
 * los cuatro y el grupo viaja en la URL. Eso es lo que permite añadir un
 * catálogo nuevo sin migración, y lo que hace que el menú distinga los cuatro
 * nodos por su parámetro.
 *
 * **Un término en uso no se borra, se apaga.** Está enganchado a actividades
 * publicadas; borrarlo las dejaría sin esa etiqueta y sin forma de recuperarla.
 * Apagarlo lo quita de los selectores y deja intactas las que ya lo tenían.
 */
class TaxonomyController extends Controller
{
    public function index(Request $request)
    {
        $grupo = Filtro::texto($request, 'grupo');

        // Sin grupo no hay nodo del menú que marcar, así que se manda al
        // primero con su grupo puesto en la URL.
        if ($grupo === '') {
            return redirect()->route('admin.taxonomies.index', ['grupo' => 'tema']);
        }

        abort_unless(isset(TaxonomyTerm::GRUPOS[$grupo]), 404);

        $estado = Filtro::texto($request, 'estado');

        $consulta = TaxonomyTerm::query()
            ->where('grupo', $grupo)
            ->withCount('activities')
            ->when(Filtro::texto($request, 'q'), fn ($q, $b) => $q->where('nombre', 'like', '%'.Filtro::like($b).'%'))
            ->when($estado !== '', fn ($q) => $q->where('activo', $estado === 'si'));

        $consulta = Papelera::aplicar($consulta, $request);

        // Igual que en el contenido: arrastrar sólo tiene sentido con la lista
        // entera delante y en su propio orden.
        $puedeReordenar = ! $request->hasAny(['q', 'estado', 'papelera']) && Filtro::texto($request, 'orden') === '';

        return view('admin.taxonomies.index', [
            'grupo' => $grupo,
            'grupos' => TaxonomyTerm::GRUPOS,
            'limite' => TaxonomyTerm::limiteDe($grupo),
            'puedeReordenar' => $puedeReordenar,
            'verEliminados' => Papelera::incluyeEliminados($request),
            'terminos' => Listado::ordenar($consulta, $request, ['nombre', 'orden', 'activo'], 'orden')
                ->paginate($puedeReordenar ? 200 : Listado::porPagina($request))
                ->withQueryString(),
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

        TaxonomyTerm::create($datos + ['slug' => Str::slug($datos['nombre']), 'activo' => true]);

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

    /** El interruptor suelto, sin pasar por el formulario de la fila. */
    public function alternar(TaxonomyTerm $term)
    {
        $term->update(['activo' => ! $term->activo]);

        return back()->with('ok', $term->activo
            ? 'Vuelve a ofrecerse al publicar.'
            : 'Ya no se ofrece al publicar. Las actividades que lo tenían lo conservan.');
    }

    public function destroy(TaxonomyTerm $term)
    {
        /*
         * En uso no se borra ni en blando: el término seguiría enganchado a las
         * actividades y saldría en sus fichas sin estar en ningún catálogo, que
         * es un estado del que no se sale desde el panel.
         */
        if ($term->activities()->exists()) {
            $cuantas = $term->activities()->count();

            return back()->with('error', 'No se puede eliminar: lo usan '
                .Texto::cuantos($cuantas, 'actividad').'. Desactívalo y dejará de ofrecerse al publicar.');
        }

        $term->delete();

        return back()->with('ok', 'Eliminado. Se puede recuperar con el filtro de la papelera.');
    }

    public function restaurar(int $id)
    {
        TaxonomyTerm::withTrashed()->findOrFail($id)->restore();

        return back()->with('ok', 'Restaurado.');
    }

    public function reordenar(Request $request)
    {
        $ids = Listado::ids($request, 'orden');

        if (! $ids) {
            return response()->json(['ok' => false], 422);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $posicion => $id) {
                TaxonomyTerm::whereKey($id)->update(['orden' => $posicion + 1]);
            }
        });

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Orden guardado.');
    }

    public function exportar(Request $request, Exportador $exportador)
    {
        $grupo = Filtro::texto($request, 'grupo') ?: 'tema';
        abort_unless(isset(TaxonomyTerm::GRUPOS[$grupo]), 404);

        $formato = Filtro::texto($request, 'formato') === 'csv' ? 'csv' : 'xlsx';

        $filas = (function () use ($grupo) {
            foreach (TaxonomyTerm::where('grupo', $grupo)->withCount('activities')->orderBy('orden')->cursor() as $t) {
                yield [$t->nombre, $t->orden, $t->activo ? 'Sí' : 'No', $t->activities_count];
            }
        })();

        return $exportador->descargar($formato, TaxonomyTerm::GRUPOS[$grupo],
            ['Nombre', 'Orden', 'Se ofrece', 'Actividades que lo usan'], $filas);
    }
}
