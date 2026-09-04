<?php

use App\Support\CatalogoHome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devuelve a ruta relativa las imágenes que quedaron guardadas como URL entera.
 *
 * **El fallo, que venía del bloque J (2026-09-02):** el selector de imagen
 * arrancaba con la URL absoluta en `ruta`, que es lo que va atado al campo
 * oculto que viaja al servidor. Así que abrir cualquier formulario con imagen y
 * guardarlo **sin tocar la imagen** escribía en la base
 * `https://ong.sandboxdelta.com/img/loquefuera.png` en vez de
 * `img/loquefuera.png`.
 *
 * No se notaba porque `asset()` devuelve tal cual lo que ya es una URL, así que
 * la imagen seguía viéndose. Lo que rompe es lo de después: la ruta lleva el
 * dominio pegado —y deja de servir el día que cambie— y el detector de «dónde
 * se usa esta imagen» de la biblioteca busca rutas relativas, así que daba por
 * libre una imagen que sí estaba en uso.
 *
 * El arreglo del selector va en `resources/js/medios.js`; esto limpia lo que ya
 * se hubiera guardado mal.
 *
 * **Sólo toca lo que claramente es nuestro:** una URL absoluta cuyo camino
 * empiece por `img/` o `storage/`, que es lo único que produce el selector. Un
 * enlace a otro sitio se queda como está.
 */
return new class extends Migration
{
    /** tabla => columna con la ruta de una imagen */
    private const COLUMNAS = [
        'partners' => 'logo_path',
        'testimonials' => 'logo_path',
        'organizations' => 'logo_path',
        'posts' => 'imagen',
        'editions' => 'imagen',
        'participation_cards' => 'mask_path',
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as $tabla => $columna) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
                continue;
            }

            DB::table($tabla)
                ->where($columna, 'like', 'http%')
                ->select(['id', $columna])
                ->orderBy('id')
                ->chunk(200, function ($filas) use ($tabla, $columna) {
                    foreach ($filas as $fila) {
                        $limpia = $this->relativa($fila->$columna);

                        if ($limpia !== null) {
                            DB::table($tabla)->where('id', $fila->id)->update([$columna => $limpia]);
                        }
                    }
                });
        }

        $this->seccionesDelHome();
    }

    /**
     * No se deshace, y es a propósito: `down()` tendría que volver a meter el
     * dominio en la base, que es exactamente lo que esto viene a quitar.
     */
    public function down(): void
    {
        //
    }

    /**
     * `img/loquesea.png` a partir de `https://donde-sea/img/loquesea.png`.
     *
     * Devuelve null si no hay nada que arreglar, para no escribir de más.
     */
    private function relativa(?string $valor): ?string
    {
        if (! is_string($valor) || ! preg_match('#^https?://[^/]+/(.+)$#i', $valor, $m)) {
            return null;
        }

        $camino = ltrim($m[1], '/');

        return str_starts_with($camino, 'img/') || str_starts_with($camino, 'storage/')
            ? $camino
            : null;
    }

    /**
     * Las secciones del home guardan sus campos en JSON, así que hay que entrar
     * a mirar. Sólo se tocan los campos que el catálogo declara como imagen: en
     * esas filas también hay enlaces —el botón del hero, por ejemplo— y ésos sí
     * pueden apuntar fuera con toda la razón.
     */
    private function seccionesDelHome(): void
    {
        if (! Schema::hasTable('home_sections')) {
            return;
        }

        foreach (DB::table('home_sections')->orderBy('id')->get() as $fila) {
            $deImagen = collect(CatalogoHome::campos($fila->clave))
                ->filter(fn ($def) => ($def['tipo'] ?? null) === 'imagen')
                ->keys();

            if ($deImagen->isEmpty()) {
                continue;
            }

            $cambios = [];

            foreach (['contenido', 'borrador'] as $columna) {
                $datos = json_decode((string) $fila->$columna, true);

                if (! is_array($datos)) {
                    continue;
                }

                $tocado = false;

                foreach ($deImagen as $campo) {
                    $limpia = $this->relativa($datos[$campo] ?? null);

                    if ($limpia !== null) {
                        $datos[$campo] = $limpia;
                        $tocado = true;
                    }
                }

                if ($tocado) {
                    $cambios[$columna] = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            if ($cambios) {
                DB::table('home_sections')->where('id', $fila->id)->update($cambios);
            }
        }
    }
};
