<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que le faltaba a los CRUD del bloque G.
 *
 * **Borrado suave.** Hasta ahora «Eliminar» borraba de verdad: un clic de más
 * en una noticia y a volver a escribirla. Con `deleted_at`, eliminar esconde la
 * fila y el listado la puede devolver desde su propio filtro «ver eliminados»,
 * que es donde uno va a buscar lo que acaba de borrar.
 *
 * Va en las tablas de contenido, en los términos de las taxonomías, en usuarios
 * y en organizaciones. **No** en regiones ni comunas: ahí no hay borrado.
 *
 * **`activo` en regiones, comunas y organizaciones.** Las tres necesitaban poder
 * apagarse sin desaparecer:
 *
 * - Una comuna que ya no aplica tiene que dejar de salir en los selectores del
 *   wizard, pero no puede borrarse: hay actividades apuntando a ella.
 * - Una organización que ya no participa deja de verse sin llevarse por delante
 *   sus actividades ni las inscripciones de esas actividades.
 *
 * Todas las columnas son anulables o con valor por defecto, así que el `migrate`
 * del cron las aplica sin tocar una sola fila existente.
 */
return new class extends Migration
{
    /** Contenido, catálogos y cuentas: todo lo que se puede eliminar. */
    private const CON_PAPELERA = [
        'posts',
        'editions',
        'testimonials',
        'stats',
        'partners',
        'participation_cards',
        'pages',
        'taxonomy_terms',
        'users',
        'organizations',
    ];

    /** Lo que se apaga en vez de borrarse. */
    private const CON_INTERRUPTOR = [
        'regions',
        'communes',
        'organizations',
    ];

    public function up(): void
    {
        foreach (self::CON_PAPELERA as $tabla) {
            if (Schema::hasColumn($tabla, 'deleted_at')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $t) {
                $t->softDeletes();
            });
        }

        foreach (self::CON_INTERRUPTOR as $tabla) {
            if (Schema::hasColumn($tabla, 'activo')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $t) {
                // Por defecto encendido: lo que ya existe estaba a la vista y
                // tiene que seguir estándolo tras el despliegue.
                $t->boolean('activo')->default(true);
            });
        }

        // Las comunas se listan y se filtran por región constantemente.
        if (! $this->tieneIndice('communes', 'communes_region_id_nombre_index')) {
            Schema::table('communes', function (Blueprint $t) {
                $t->index(['region_id', 'nombre']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::CON_PAPELERA as $tabla) {
            if (Schema::hasColumn($tabla, 'deleted_at')) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropSoftDeletes());
            }
        }

        foreach (self::CON_INTERRUPTOR as $tabla) {
            if (Schema::hasColumn($tabla, 'activo')) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn('activo'));
            }
        }
    }

    private function tieneIndice(string $tabla, string $indice): bool
    {
        return collect(Schema::getIndexes($tabla))->contains(fn ($i) => $i['name'] === $indice);
    }
};
