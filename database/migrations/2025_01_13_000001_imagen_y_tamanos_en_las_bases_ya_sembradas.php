<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dos cambios del 2026-09-04 que en local se vieron solos y en el servidor no.
 *
 * **El error que hay detrás, anotado para no repetirlo:** cambiar el valor por
 * defecto en `CatalogoHome` o en `ContentSeeder` **no cambia nada en una base
 * que ya existe**. El catálogo sólo pinta las secciones que no tienen fila
 * guardada, y `ContentSeeder` no lo ejecuta nadie en el servidor —`dps:instalar`
 * lo salta a propósito—. En local las dos cosas se vieron porque la sección no
 * estaba tocada y porque los partners los cambié a mano; en producción la
 * sección sí estaba guardada y los logos seguían en su tamaño de siembra. Se
 * comprobó mirando el HTML servido, no suponiéndolo.
 *
 * Las dos partes van con la misma guarda que las migraciones hermanas: **sólo
 * se toca lo que siga con el valor de siembra**. Si la ONG ya puso otra imagen
 * o cambió un tamaño desde el CRUD, aquí no se pisa nada.
 */
return new class extends Migration
{
    private const IMAGEN_ANTES = 'img/group-people-shaking-hands-with-one-that-says-h-it.jpg';

    private const IMAGEN_AHORA = 'img/manos-patrimonio-social.jpg';

    public function up(): void
    {
        $this->imagenDeQueEs(self::IMAGEN_ANTES, self::IMAGEN_AHORA);

        // «Participan» pasa al tamaño intermedio, que es lo que pidió el
        // cliente. Sólo las que sigan en el que sembró `ContentSeeder`.
        DB::table('partners')
            ->where('grupo', 'participan')
            ->where('tamano', 'chico')
            ->update(['tamano' => 'mediano']);
    }

    public function down(): void
    {
        $this->imagenDeQueEs(self::IMAGEN_AHORA, self::IMAGEN_ANTES);

        DB::table('partners')
            ->where('grupo', 'participan')
            ->where('tamano', 'mediano')
            ->update(['tamano' => 'chico']);
    }

    /**
     * Cambia la imagen guardada de la sección «¿Qué es el Patrimonio Social?»,
     * y sólo si es la que se espera.
     *
     * Se decodifica y se vuelve a codificar en PHP en vez de usar las funciones
     * JSON de MySQL: las pruebas corren sobre SQLite, que no las tiene.
     */
    private function imagenDeQueEs(string $de, string $a): void
    {
        $fila = DB::table('home_sections')->where('clave', 'que-es')->first();

        if (! $fila) {
            // Sin fila guardada, la sección se pinta con el catálogo y ahí el
            // valor ya es el nuevo. No hay nada que hacer.
            return;
        }

        $cambios = [];

        // `contenido` es lo publicado y `borrador` el autoguardado: si sólo se
        // tocara uno, publicar desde el panel devolvería la imagen vieja.
        foreach (['contenido', 'borrador'] as $columna) {
            $datos = json_decode((string) $fila->$columna, true);

            if (! is_array($datos) || ($datos['imagen'] ?? null) !== $de) {
                continue;
            }

            $datos['imagen'] = $a;
            $cambios[$columna] = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($cambios) {
            DB::table('home_sections')->where('clave', 'que-es')->update($cambios);
        }
    }
};
