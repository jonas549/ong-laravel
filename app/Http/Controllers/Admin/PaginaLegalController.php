<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;

/**
 * La política de privacidad y, desde el 09/10, las preguntas frecuentes.
 *
 * En el árbol del panel es un nodo propio porque es lo que la ONG va a buscar
 * por su nombre, pero por debajo es una página más del CRUD de páginas. Se
 * pinta aquí el mismo formulario en vez de redirigir a él para que el menú
 * marque este nodo y las migas digan dónde estás: con una redirección se
 * acababa en «Páginas sueltas» sin entender muy bien por qué.
 */
class PaginaLegalController extends Controller
{
    private const TIPO = 'paginas';

    public function privacidad(ContentController $contenido)
    {
        return $this->abrir($contenido, 'privacidad', 'La política de privacidad');
    }

    /** Punto 7 del 09/10: la crea su migración; aquí se edita como la otra. */
    public function preguntasFrecuentes(ContentController $contenido)
    {
        return $this->abrir($contenido, 'preguntas-frecuentes', 'La página de preguntas frecuentes');
    }

    private function abrir(ContentController $contenido, string $slug, string $nombre)
    {
        $pagina = Page::where('slug', $slug)->first();

        if (! $pagina) {
            // Todavía no existe: se va al formulario de creación con el aviso
            // de qué slug tiene que llevar, en vez de dar un 404 por una página
            // que debería estar.
            return redirect()
                ->route('admin.content.create', ['tipo' => self::TIPO])
                ->with('error', $nombre.' todavía no existe. Créala con el slug «'.$slug.'».');
        }

        return $contenido->edit(self::TIPO, $pagina->id);
    }
}
