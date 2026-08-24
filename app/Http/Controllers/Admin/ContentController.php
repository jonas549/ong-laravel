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
use Illuminate\Http\Request;

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
            'etiqueta' => 'nombre',
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

    public function index(string $tipo)
    {
        $def = $this->definicion($tipo);
        $modelo = $def['modelo'];

        return view('admin.content.index', [
            'tipo' => $tipo,
            'def' => $def,
            'filas' => $modelo::orderBy(in_array('orden', array_keys($def['campos']), true) ? 'orden' : 'id')->get(),
        ]);
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
