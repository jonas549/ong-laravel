<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Biblioteca;
use App\Support\Filtro;
use App\Support\Listado;
use App\Support\Texto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La biblioteca de medios.
 *
 * Es una cuadrícula y no una tabla porque lo que se busca aquí se reconoce
 * mirando, no leyendo: en una lista de nombres de archivo no se distingue una
 * foto de otra. Por lo demás usa las mismas piezas que el resto del panel
 * —`Filtro`, `Listado`, los avisos— para que el estado siga viviendo en la URL
 * y se pueda compartir un enlace a una búsqueda.
 */
class MediaController extends Controller
{
    /** Por dónde se puede ordenar. Lista blanca: lo que llega por la URL entra en la consulta. */
    private const ORDENABLES = ['created_at', 'nombre', 'peso'];

    public function __construct(private Biblioteca $biblioteca) {}

    public function index(Request $request)
    {
        $consulta = $this->consulta($request);

        $consulta = Listado::ordenar($consulta, $request, self::ORDENABLES, 'created_at', 'desc');

        return view('admin.medios.index', [
            'medios' => $consulta->paginate(Listado::porPagina($request, 24))->withQueryString(),
            'limites' => $this->biblioteca->limites(),
            'carpetas' => Media::query()->whereNotNull('carpeta')->distinct()->orderBy('carpeta')->pluck('carpeta'),
            'total' => Media::count(),
            'peso' => (int) Media::sum('peso'),
        ]);
    }

    /**
     * La misma consulta, servida como JSON para el selector.
     *
     * El selector es un diálogo dentro de otra pantalla: recargar la página
     * entera para pasar de página o buscar perdería lo que el usuario lleve
     * escrito en el formulario que hay detrás.
     */
    public function buscar(Request $request): JsonResponse
    {
        $medios = $this->consulta($request)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(24, ['*'], 'page', max(1, (int) $request->query('page', 1)));

        return response()->json([
            'medios' => collect($medios->items())->map(fn (Media $m) => [
                'id' => $m->id,
                'ruta' => $m->ruta,
                'url' => $m->url,
                'etiqueta' => $m->etiqueta,
                'alt' => $m->alt,
                'dimensiones' => $m->dimensiones,
                'peso' => $m->peso_legible,
                'esVectorial' => $m->es_vectorial,
            ])->all(),
            'pagina' => $medios->currentPage(),
            'paginas' => $medios->lastPage(),
            'totalTexto' => $medios->total().' '.Texto::plural('archivo', $medios->total()),
        ]);
    }

    public function show(Media $medio)
    {
        return view('admin.medios.show', [
            'medio' => $medio,
            'usos' => $this->biblioteca->usos($medio),
        ]);
    }

    /**
     * Subida múltiple.
     *
     * Responde JSON porque la pantalla sube arrastrando y va enseñando el
     * resultado archivo por archivo: una redirección dejaría al usuario sin
     * saber cuál de los ocho que soltó falló.
     */
    public function store(Request $request): JsonResponse
    {
        $limites = $this->biblioteca->limites();
        $maxKb = (int) floor($limites['efectivo'] / 1024);

        $datos = $request->validate([
            'archivos' => ['required', 'array', 'max:'.$limites['archivos']],
            'archivos.*' => ['file', 'mimes:'.implode(',', Biblioteca::EXTENSIONES), 'max:'.$maxKb],
            'carpeta' => ['nullable', 'string', 'max:100'],
        ], [
            'archivos.*.max' => 'Cada archivo tiene que pesar menos de '.Media::pesoLegible($limites['efectivo']).'.',
            'archivos.*.mimes' => 'Sólo se admiten imágenes: '.implode(', ', Biblioteca::EXTENSIONES).'.',
        ]);

        $guardados = [];

        foreach ($datos['archivos'] as $archivo) {
            $medio = $this->biblioteca->guardar($archivo, $datos['carpeta'] ?? null, $request->user());

            $guardados[] = [
                'id' => $medio->id,
                'ruta' => $medio->ruta,
                'url' => $medio->url,
                'etiqueta' => $medio->etiqueta,
                'peso' => $medio->peso_legible,
                'dimensiones' => $medio->dimensiones,
            ];
        }

        return response()->json([
            'ok' => true,
            'medios' => $guardados,
            'aviso' => count($guardados).' '.Texto::plural('archivo', count($guardados)).' '
                .(count($guardados) === 1 ? 'subido' : 'subidos').'.',
        ]);
    }

    public function update(Request $request, Media $medio)
    {
        $datos = $request->validate([
            'titulo' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:500'],
            'carpeta' => ['nullable', 'string', 'max:100'],
        ]);

        $medio->update($datos);

        return back()->with('ok', 'Datos del archivo guardados.');
    }

    /** Reemplaza el archivo conservando la URL. Sólo para lo subido. */
    public function reemplazar(Request $request, Media $medio)
    {
        if ($medio->es_del_codigo) {
            return back()->with('error', 'Las imágenes que vienen con el diseño no se reemplazan desde el panel: las repone el despliegue.');
        }

        $limites = $this->biblioteca->limites();

        $request->validate([
            // La misma extensión: la URL no cambia, así que el archivo nuevo
            // tiene que poder servirse por ella.
            'archivo' => ['required', 'file', 'mimes:'.$medio->extension, 'max:'.(int) floor($limites['efectivo'] / 1024)],
        ], [
            'archivo.mimes' => 'El reemplazo tiene que ser un .'.$medio->extension.', porque la dirección del archivo no cambia.',
        ]);

        $this->biblioteca->reemplazar($medio, $request->file('archivo'));

        return back()->with('ok', 'Archivo reemplazado. Todo lo que ya lo usaba sigue apuntando aquí.');
    }

    public function destroy(Request $request, Media $medio)
    {
        if ($medio->es_del_codigo) {
            return back()->with('error', 'Esta imagen viene con el diseño y el despliegue la repondría. No se puede borrar desde el panel.');
        }

        $usos = $this->biblioteca->usos($medio);

        // El aviso no basta: hay que poder confirmarlo. Sin la confirmación
        // explícita, un archivo en uso no se borra.
        if ($usos !== [] && ! $request->boolean('aunque_este_en_uso')) {
            return back()->with('error', 'Ese archivo se está usando en '.count($usos).' '
                .Texto::plural('sitio', count($usos)).'. Ábrelo para ver dónde.');
        }

        $medio->borrarArchivo();
        $medio->delete();

        return redirect()
            ->route('admin.medios.index')
            ->with('ok', 'Archivo borrado.'.($usos !== [] ? ' Estaba en uso: revisa dónde salía.' : ''));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Media> */
    private function consulta(Request $request)
    {
        $origen = Filtro::texto($request, 'origen');
        $carpeta = Filtro::texto($request, 'carpeta');

        return Media::query()
            ->when(Filtro::texto($request, 'q'), fn ($q, $b) => $q->where(function ($q) use ($b) {
                $like = '%'.Filtro::like($b).'%';
                $q->where('nombre', 'like', $like)
                    ->orWhere('titulo', 'like', $like)
                    ->orWhere('alt', 'like', $like)
                    ->orWhere('ruta', 'like', $like);
            }))
            ->when($origen !== '' && in_array($origen, [Media::ORIGEN_CODIGO, Media::ORIGEN_SUBIDO], true),
                fn ($q) => $q->where('origen', $origen))
            ->when($carpeta !== '', fn ($q) => $q->where('carpeta', $carpeta))
            ->when(Filtro::texto($request, 'tipo'), fn ($q, $t) => $q->where('extension', $t))
            ->when(Filtro::texto($request, 'desde'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when(Filtro::texto($request, 'hasta'), fn ($q, $h) => $q->whereDate('created_at', '<=', $h));
    }
}
