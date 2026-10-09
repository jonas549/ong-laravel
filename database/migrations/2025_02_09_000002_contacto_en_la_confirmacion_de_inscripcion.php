<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Punto 8 del 09/10: la confirmación de inscripción lleva, después del
 * enlace del calendario, «Si tienes dudas y necesitas contactar al
 * organizador, escríbele», enlazado al correo público de la actividad.
 *
 * El bloque lo monta `CorreoTransaccional::bloqueContacto()` y la plantilla
 * nueva ya lo trae (EmailTemplateSeeder), pero la siembra no reescribe las
 * plantillas que existen —son de la ONG—. Aquí se mete el marcador en la que
 * hay, justo detrás de `{{ bloque_calendario }}`.
 *
 * **Sólo si el marcador del calendario sigue ahí y el nuevo no está.** Si la
 * ONG reescribió la plantilla sin el calendario, no se adivina dónde ponerlo:
 * lo añade ella desde el panel, donde ya sale en la lista de variables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plantilla = DB::table('email_templates')->where('clave', 'inscripcion_confirmada')->first(['id', 'cuerpo_html']);

        if (! $plantilla || str_contains((string) $plantilla->cuerpo_html, 'bloque_contacto')) {
            return;
        }

        $cuerpo = preg_replace(
            '/(\{\{\s*bloque_calendario\s*\}\})/',
            "$1\n                     {{ bloque_contacto }}",
            (string) $plantilla->cuerpo_html,
            1,
            $cambios,
        );

        if ($cambios === 1) {
            DB::table('email_templates')->where('id', $plantilla->id)->update(['cuerpo_html' => $cuerpo, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $plantilla = DB::table('email_templates')->where('clave', 'inscripcion_confirmada')->first(['id', 'cuerpo_html']);

        if ($plantilla) {
            DB::table('email_templates')->where('id', $plantilla->id)->update([
                'cuerpo_html' => preg_replace('/\s*\{\{\s*bloque_contacto\s*\}\}/', '', (string) $plantilla->cuerpo_html, 1),
            ]);
        }
    }
};
