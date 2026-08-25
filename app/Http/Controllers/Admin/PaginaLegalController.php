<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;

/**
 * La política de privacidad.
 *
 * En el árbol del panel es un nodo propio porque es lo que la ONG va a buscar
 * por su nombre, pero por debajo es una página más del CRUD de páginas. Se
 * pinta aquí el mismo formulario en vez de redirigir a él para que el menú
 * marque este nodo y las migas digan dónde estás: con una redirección se
 * acababa en «Páginas sueltas» sin entender muy bien por qué.
 */
class PaginaLegalController extends Controller
{
    private const SLUG = 'privacidad';

    private const TIPO = 'paginas';

    public function privacidad(ContentController $contenido)
    {
        $pagina = Page::where('slug', self::SLUG)->first();

        if (! $pagina) {
            // Todavía no existe: se va al formulario de creación con el aviso
            // de qué slug tiene que llevar, en vez de dar un 404 por una página
            // que debería estar.
            return redirect()
                ->route('admin.content.create', ['tipo' => self::TIPO])
                ->with('error', 'La política de privacidad todavía no existe. Créala con el slug «'.self::SLUG.'».');
        }

        return $contenido->edit(self::TIPO, $pagina->id);
    }
}
