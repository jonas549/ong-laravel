<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Punto 9 del 08/10: el botón de «¿Qué es el Patrimonio Social?» lleva a la
 * página «Qué es» del sitio del Día del Patrimonio Social.
 *
 * El valor por defecto ya cambió en `CatalogoHome`, pero eso sólo alcanza a
 * las secciones que no guardaron el campo. Aquí se corrige en las que sí lo
 * guardaron, en lo publicado y en el borrador, y en las copias de la sección
 * (`que-es--2`…).
 *
 * **Sólo si sigue en `/actividades`**, el destino de siempre. Si la ONG lo
 * apuntó a otro sitio desde el panel, manda ella.
 */
return new class extends Migration
{
    private const VIEJO = '/actividades';

    private const NUEVO = 'https://diadelpatrimoniosocial.cl/que-es/';

    public function up(): void
    {
        $filas = DB::table('home_sections')
            ->where(fn ($q) => $q->where('clave', 'que-es')->orWhere('clave', 'like', 'que-es--%'))
            ->get(['id', 'contenido', 'borrador']);

        foreach ($filas as $fila) {
            $cambios = [];

            foreach (['contenido', 'borrador'] as $columna) {
                $datos = json_decode((string) $fila->{$columna}, true);

                if (is_array($datos) && trim((string) ($datos['cta_enlace'] ?? '')) === self::VIEJO) {
                    $datos['cta_enlace'] = self::NUEVO;
                    $cambios[$columna] = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            if ($cambios) {
                DB::table('home_sections')->where('id', $fila->id)->update($cambios + ['updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // No se puede distinguir lo que puso esta migración de un enlace
        // escrito a mano igual, así que no se deshace.
    }
};
