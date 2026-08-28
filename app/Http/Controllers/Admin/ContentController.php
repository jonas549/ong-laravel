<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Page;
use App\Models\ParticipationCard;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Stat;
use App\Models\Testimonial;
use App\Services\Exportador;
use App\Support\Filtro;
use App\Support\Listado;
use App\Support\Papelera;
use App\Support\Texto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CRUD genérico para el contenido editable del home.
 *
 * Un solo controlador para siete entidades: todas comparten la misma forma
 * (listar, crear, editar, borrar, ordenar) y solo cambian sus campos, así
 * que se describen en un mapa en vez de repetir siete clases iguales.
 */
class ContentController extends Controller
{
    /** @var array<string, array<string, mixed>> */
    private const TIPOS = [
        'testimonios' => [
            'modelo' => Testimonial::class,
            'titulo' => 'Voces del movimiento',
            'etiqueta' => 'autor',
            'campos' => [
                'autor' => ['label' => 'Autor', 'tipo' => 'text', 'reglas' => 'required|string|max:255'],
                'cargo' => ['label' => 'Cargo u organización', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255'],
                'texto' => ['label' => 'Testimonio', 'tipo' => 'textarea', 'reglas' => 'required|string|max:2000'],
                'logo_path' => ['label' => 'Ruta del logo', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255', 'ayuda' => 'Por ejemplo img/logo-cos-color.png'],
                'color' => ['label' => 'Color', 'tipo' => 'text', 'reglas' => 'nullable|string|max:40'],
                'bleed' => ['label' => 'Logo a sangre (se amplía)', 'tipo' => 'bool', 'reglas' => 'nullable|boolean'],
                'orden' => ['label' => 'Orden', 'tipo' => 'number', 'reglas' => 'nullable|integer|min:0'],
                'activo' => ['label' => 'Visible', 'tipo' => 'bool', 'reglas' => 'nullable|boolean'],
            ],
        ],
        'cifras' => [
            'modelo' => Stat::class,
            'titulo' => 'Cifras del home',
            'etiqueta' => 'etiqueta',
            'campos' => [
                'numero' => ['label' => 'Número', 'tipo' => 'text', 'reglas' => 'required|string|max:40', 'ayuda' => 'Se anima al hacer scroll. Ejemplos: 200+, 50.000+'],
                'etiqueta' => ['label' => 'Etiqueta', 'tipo' => 'text', 'reglas' => 'required|string|max:255'],
                'color' => ['label' => 'Color', 'tipo' => 'text', 'reglas' => 'nullable|string|max:40'],
                'orden' => ['label' => 'Orden', 'tipo' => 'number', 'reglas' => 'nullable|integer|min:0'],
                'activo' => ['label' => 'Visible', 'tipo' => 'bool', 'reglas' => 'nullable|boolean'],
            ],
        ],
        'partners' => [
            'modelo' => Partner::class,
            'titulo' => 'Auspiciadores y participantes',
            'etiqueta' => 'el nombre',
            'campos' => [
                'nombre' => ['label' => 'Nombre', 'tipo' => 'text', 'reglas' => 'required|string|max:255'],
                'grupo' => ['label' => 'Grupo', 'tipo' => 'select', 'reglas' => 'required|string|max:30', 'opciones' => Partner::GRUPOS],
                'logo_path' => ['label' => 'Ruta del logo', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255'],
                'url' => ['label' => 'Enlace', 'tipo' => 'text', 'reglas' => 'nullable|url|max:255'],
                'color' => ['label' => 'Color (si no hay logo)', 'tipo' => 'text', 'reglas' => 'nullable|string|max:40'],
                'orden' => ['label' => 'Orden', 'tipo' => 'number', 'reglas' => 'nullable|integer|min:0'],
                'activo' => ['label' => 'Visible', 'tipo' => 'bool', 'reglas' => 'nullable|boolean'],
            ],
        ],
        'tarjetas' => [
            'modelo' => ParticipationCard::class,
            'titulo' => 'Tarjetas "¿cómo participar?"',
            'etiqueta' => 'titulo',
            'campos' => [
                'titulo' => ['label' => 'Título', 'tipo' => 'text', 'reglas' => 'required|string|max:255'],
                'descripcion' => ['label' => 'Descripción', 'tipo' => 'textarea', 'reglas' => 'nullable|string|max:1000'],
                'nota' => ['label' => 'Nota al pie', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255'],
                'cta' => ['label' => 'Texto del botón', 'tipo' => 'text', 'reglas' => 'nullable|string|max:60'],
                'href' => ['label' => 'Enlace', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255'],
                'color' => ['label' => 'Color del borde', 'tipo' => 'text', 'reglas' => 'nullable|string|max:40'],
                'mask_path' => ['label' => 'Ilustración', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255'],
                'art_path' => ['label' => 'Arte de fondo', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255'],
                'orden' => ['label' => 'Orden', 'tipo' => 'number', 'reglas' => 'nullable|integer|min:0'],
                'activo' => ['label' => 'Visible', 'tipo' => 'bool', 'reglas' => 'nullable|boolean'],
            ],
        ],
        'noticias' => [
            'modelo' => Post::class,
            'titulo' => 'Noticias',
            'etiqueta' => 'titulo',
            'campos' => [
                'titulo' => ['label' => 'Título', 'tipo' => 'text', 'reglas' => 'required|string|max:255'],
                'extracto' => ['label' => 'Extracto', 'tipo' => 'textarea', 'reglas' => 'nullable|string|max:500'],
                'contenido' => ['label' => 'Contenido', 'tipo' => 'textarea', 'reglas' => 'nullable|string'],
                'imagen' => ['label' => 'Imagen', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255'],
                'published_at' => ['label' => 'Fecha de publicación', 'tipo' => 'datetime', 'reglas' => 'nullable|date'],
                'activo' => ['label' => 'Visible', 'tipo' => 'bool', 'reglas' => 'nullable|boolean'],
            ],
        ],
        'ediciones' => [
            'modelo' => Edition::class,
            'titulo' => 'Ediciones',
            'etiqueta' => 'titulo',
            'campos' => [
                'anio' => ['label' => 'Año', 'tipo' => 'number', 'reglas' => 'required|integer|min:2000|max:2100'],
                'titulo' => ['label' => 'Título', 'tipo' => 'text', 'reglas' => 'required|string|max:255'],
                'descripcion' => ['label' => 'Descripción', 'tipo' => 'textarea', 'reglas' => 'nullable|string|max:2000'],
                'imagen' => ['label' => 'Imagen', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255'],
                'activo' => ['label' => 'Visible', 'tipo' => 'bool', 'reglas' => 'nullable|boolean'],
            ],
        ],
        'paginas' => [
            'modelo' => Page::class,
            'titulo' => 'Páginas',
            'etiqueta' => 'titulo',
            'campos' => [
                'titulo' => ['label' => 'Título', 'tipo' => 'text', 'reglas' => 'required|string|max:255'],
                'slug' => ['label' => 'URL', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255', 'ayuda' => 'Se genera desde el título si lo dejas en blanco.'],
                'meta_descripcion' => ['label' => 'Meta descripción', 'tipo' => 'text', 'reglas' => 'nullable|string|max:255'],
                'contenido' => ['label' => 'Contenido (HTML)', 'tipo' => 'textarea', 'reglas' => 'nullable|string'],
                'activo' => ['label' => 'Visible', 'tipo' => 'bool', 'reglas' => 'nullable|boolean'],
            ],
        ],
    ];

    public function index(Request $request, string $tipo)
    {
        $def = $this->definicion($tipo);
        $modelo = $def['modelo'];
        $campos = array_keys($def['campos']);

        $consulta = $modelo::query();

        // El buscador mira los campos de texto de este tipo, que cambian de uno
        // a otro: en noticias es el titulo y en cifras la etiqueta.
        if ($termino = Filtro::texto($request, 'q')) {
            $textos = collect($def['campos'])
                ->filter(fn ($m) => in_array($m['tipo'], ['text', 'textarea'], true))
                ->keys();

            $consulta->where(function ($q) use ($textos, $termino) {
                foreach ($textos as $campo) {
                    $q->orWhere($campo, 'like', '%'.Filtro::like($termino).'%');
                }
            });
        }

        if (in_array('activo', $campos, true) && ($estado = Filtro::texto($request, 'estado')) !== '') {
            $consulta->where('activo', $estado === 'si');
        }

        $consulta = Papelera::aplicar($consulta, $request);

        $ordenables = array_values(array_intersect(['orden', 'titulo', 'nombre', 'autor', 'etiqueta', 'anio', 'activo', 'created_at'], $campos));
        $ordenables[] = 'created_at';
        $porDefecto = in_array('orden', $campos, true) ? 'orden' : 'created_at';

        $tieneOrden = in_array('orden', $campos, true);

        /*
         * Arrastrar para reordenar solo tiene sentido con la lista entera
         * delante y en su propio orden: con un filtro puesto, o paginado, mover
         * la fila 3 sobre la 5 no dice nada de lo que hay entre medias. Cuando
         * no se puede, la pantalla lo explica en vez de dejar el arrastre
         * puesto haciendo cosas que no se corresponden con lo que se ve.
         */
        $puedeReordenar = $tieneOrden
            && ! $request->hasAny(['q', 'estado', 'papelera'])
            && Filtro::texto($request, 'orden') === '';

        return view('admin.content.index', [
            'tipo' => $tipo,
            'def' => $def,
            'ordenables' => $ordenables,
            'tieneActivo' => in_array('activo', $campos, true),
            'tieneOrden' => $tieneOrden,
            'puedeReordenar' => $puedeReordenar,
            'verEliminados' => Papelera::incluyeEliminados($request),
            'filas' => Listado::ordenar($consulta, $request, $ordenables, $porDefecto, $porDefecto === 'created_at' ? 'desc' : 'asc')
                ->paginate($puedeReordenar ? 200 : Listado::porPagina($request))
                ->withQueryString(),
        ]);
    }

    /** Enciende o apaga una fila desde su propio boton. */
    public function alternar(string $tipo, int $id)
    {
        $def = $this->definicion($tipo);

        abort_unless(array_key_exists('activo', $def['campos']), 404);

        $fila = $def['modelo']::findOrFail($id);
        $fila->update(['activo' => ! $fila->activo]);

        return back()->with('ok', $fila->activo ? 'Ya se ve en el sitio.' : 'Escondido del sitio.');
    }

    /**
     * Devuelve una fila eliminada.
     *
     * `withTrashed` porque el registro esta borrado: sin eso no se encuentra y
     * el boton de restaurar daria 404 justo sobre la fila que lo ensena.
     */
    public function restaurar(string $tipo, int $id)
    {
        $def = $this->definicion($tipo);

        $fila = $def['modelo']::withTrashed()->findOrFail($id);
        $fila->restore();

        return back()->with('ok', 'Restaurado. Vuelve a estar donde estaba.');
    }

    /**
     * Guarda el orden que ha quedado tras arrastrar.
     *
     * Solo para los contenidos con columna `orden`; los demas se ordenan por
     * fecha y ahi no hay nada que arrastrar.
     */
    public function reordenar(Request $request, string $tipo)
    {
        $def = $this->definicion($tipo);

        abort_unless(array_key_exists('orden', $def['campos']), 404);

        $ids = Listado::ids($request, 'orden');

        if (! $ids) {
            return response()->json(['ok' => false, 'error' => 'No llego ningun orden.'], 422);
        }

        DB::transaction(function () use ($def, $ids) {
            foreach ($ids as $posicion => $id) {
                $def['modelo']::whereKey($id)->update(['orden' => $posicion + 1]);
            }
        });

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('ok', 'Orden guardado.');
    }

    /**
     * Las acciones masivas del listado.
     *
     * Los ids se leen con `Listado::ids()`, que devuelve enteros y descarta lo
     * que no lo sea: la casilla de una tabla es una entrada de usuario como
     * cualquier otra, y llega en bloque.
     */
    public function masivas(Request $request, string $tipo)
    {
        $def = $this->definicion($tipo);
        $ids = Listado::ids($request);
        $accion = Filtro::texto($request, 'accion');

        if (! $ids) {
            return back()->with('error', 'No habia ninguna fila seleccionada.');
        }

        // `withTrashed` para que «restaurar» encuentre lo que hay que restaurar.
        $filas = $def['modelo']::withTrashed()->whereIn('id', $ids)->get();
        $tieneActivo = array_key_exists('activo', $def['campos']);

        $hechas = match (true) {
            $accion === 'activar' && $tieneActivo => $this->cambiarVisible($filas, true),
            $accion === 'desactivar' && $tieneActivo => $this->cambiarVisible($filas, false),
            $accion === 'eliminar' => $this->eliminar($filas),
            $accion === 'restaurar' => $this->restaurarVarias($filas),
            default => null,
        };

        if ($hechas === null) {
            return back()->with('error', 'Esa accion no existe para este contenido.');
        }

        return back()->with('ok', match ($accion) {
            'activar' => Texto::cuantos($hechas, 'registro').' '.Texto::plural('visible', $hechas).' en el sitio.',
            'desactivar' => Texto::cuantos($hechas, 'registro').' '.Texto::plural('escondido', $hechas).' del sitio.',
            'restaurar' => Texto::cuantos($hechas, 'registro').' '.Texto::plural('restaurado', $hechas).'.',
            default => Texto::cuantos($hechas, 'registro').' '.Texto::plural('eliminado', $hechas).'. Se recuperan con el filtro de la papelera.',
        });
    }

    /** @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $filas */
    private function cambiarVisible($filas, bool $visible): int
    {
        $filas->each(fn ($f) => $f->update(['activo' => $visible]));

        return $filas->count();
    }

    /** @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $filas */
    private function eliminar($filas): int
    {
        $vivas = $filas->reject(fn ($f) => $f->trashed());
        $vivas->each(fn ($f) => $f->delete());

        return $vivas->count();
    }

    /** @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $filas */
    private function restaurarVarias($filas): int
    {
        $muertas = $filas->filter(fn ($f) => $f->trashed());
        $muertas->each(fn ($f) => $f->restore());

        return $muertas->count();
    }

    public function create(string $tipo)
    {
        $def = $this->definicion($tipo);

        return view('admin.content.form', [
            'tipo' => $tipo,
            'def' => $def,
            'fila' => new $def['modelo'],
        ]);
    }

    public function store(Request $request, string $tipo)
    {
        $def = $this->definicion($tipo);
        $datos = $this->validar($request, $def);

        $def['modelo']::create($datos);

        return redirect()
            ->route('admin.content.index', $tipo)
            ->with('ok', 'Registro creado.');
    }

    public function edit(string $tipo, int $id)
    {
        $def = $this->definicion($tipo);

        return view('admin.content.form', [
            'tipo' => $tipo,
            'def' => $def,
            'fila' => $def['modelo']::findOrFail($id),
        ]);
    }

    public function update(Request $request, string $tipo, int $id)
    {
        $def = $this->definicion($tipo);
        $fila = $def['modelo']::findOrFail($id);

        $fila->update($this->validar($request, $def));

        return redirect()
            ->route('admin.content.index', $tipo)
            ->with('ok', 'Cambios guardados.');
    }

    public function destroy(string $tipo, int $id)
    {
        $def = $this->definicion($tipo);
        $def['modelo']::findOrFail($id)->delete();

        return redirect()
            ->route('admin.content.index', $tipo)
            ->with('ok', 'Registro eliminado.');
    }

    /**
     * Exporta el listado tal como se esta viendo.
     *
     * Respeta los filtros de la URL: exportar «lo que hay en pantalla» y que
     * salga otra cosa es la forma mas rapida de que nadie vuelva a fiarse del
     * boton. Se usa un cursor y no un `get()` porque el exportador va en
     * streaming y no hace falta traerse la tabla entera a memoria.
     */
    public function exportar(Request $request, string $tipo, Exportador $exportador)
    {
        $def = $this->definicion($tipo);
        $formato = Filtro::texto($request, 'formato') === 'csv' ? 'csv' : 'xlsx';

        $campos = collect($def['campos'])->reject(fn ($m) => $m['tipo'] === 'textarea');

        $consulta = $def['modelo']::query();

        if ($termino = Filtro::texto($request, 'q')) {
            $textos = collect($def['campos'])->filter(fn ($m) => in_array($m['tipo'], ['text', 'textarea'], true))->keys();

            $consulta->where(function ($q) use ($textos, $termino) {
                foreach ($textos as $campo) {
                    $q->orWhere($campo, 'like', '%'.Filtro::like($termino).'%');
                }
            });
        }

        if (array_key_exists('activo', $def['campos']) && ($estado = Filtro::texto($request, 'estado')) !== '') {
            $consulta->where('activo', $estado === 'si');
        }

        $filas = (function () use ($consulta, $campos) {
            foreach ($consulta->cursor() as $fila) {
                yield $campos->map(function ($meta, $campo) use ($fila) {
                    $valor = $fila->{$campo};

                    return match ($meta['tipo']) {
                        'bool' => $valor ? 'Si' : 'No',
                        'datetime' => \App\Support\Fecha::iso($valor),
                        'select' => $meta['opciones'][$valor] ?? $valor,
                        default => $valor,
                    };
                })->values()->all();
            }
        })();

        return $exportador->descargar($formato, $def['titulo'], $campos->pluck('label')->all(), $filas);
    }

    /** @return array<string, mixed> */
    private function definicion(string $tipo): array
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        return self::TIPOS[$tipo];
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, array $def): array
    {
        $reglas = [];
        $nombres = [];

        foreach ($def['campos'] as $campo => $meta) {
            $reglas[$campo] = $meta['reglas'];
            $nombres[$campo] = mb_strtolower($meta['label']);
        }

        $datos = $request->validate($reglas, [], $nombres);

        // Un checkbox no marcado no llega en el request: hay que forzarlo a
        // false, o al desmarcarlo el valor anterior quedaría intacto.
        foreach ($def['campos'] as $campo => $meta) {
            if ($meta['tipo'] === 'bool') {
                $datos[$campo] = $request->boolean($campo);
            }
        }

        return $datos;
    }

    /** @return array<string, string> */
    public static function menu(): array
    {
        return collect(self::TIPOS)->map(fn ($d) => $d['titulo'])->all();
    }
}
