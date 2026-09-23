<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El correo de la guía para organizadores sale ahora al PUBLICAR la
 * actividad, no al registrarla (punto 11 del 23/09).
 *
 * Su texto decía «te avisaremos por correo cuando esté publicada», que ya no
 * es verdad: llega justo cuando lo está. Y la descripción de la plantilla en
 * el panel decía que salía al registrar.
 *
 * **Sólo se toca lo que siga como se sembró.** Si la ONG ha reescrito el
 * cuerpo o la descripción desde el panel, se respeta: se cambia la frase
 * exacta de la siembra y nada más, y si no está, no pasa nada.
 */
return new class extends Migration
{
    private const FRASE_VIEJA = 'Tu actividad sigue su curso: te avisaremos por correo cuando esté publicada, y mientras tanto puedes revisarla desde';

    private const FRASE_NUEVA = 'Tu actividad ya está publicada en el calendario. Puedes revisarla y editarla cuando quieras desde';

    private const DESCRIPCION_VIEJA = 'Se envía a la organización cada vez que registra una actividad, con el enlace a la guía para organizadores. '
        .'El enlace se cambia en Configuración → General; si está vacío, este correo no sale.';

    private const DESCRIPCION_NUEVA = 'Se envía a la organización cuando se publica cada una de sus actividades, con el enlace a la guía para organizadores. '
        .'El enlace se cambia en Configuración → General; si está vacío, este correo no sale.';

    public function up(): void
    {
        $plantilla = DB::table('email_templates')->where('clave', 'guia_organizador')->first();

        if (! $plantilla) {
            return;
        }

        $cambios = [];

        if (str_contains((string) $plantilla->cuerpo_html, self::FRASE_VIEJA)) {
            $cambios['cuerpo_html'] = str_replace(self::FRASE_VIEJA, self::FRASE_NUEVA, $plantilla->cuerpo_html);
        }

        if ($plantilla->descripcion === self::DESCRIPCION_VIEJA) {
            $cambios['descripcion'] = self::DESCRIPCION_NUEVA;
        }

        if ($cambios) {
            DB::table('email_templates')->where('id', $plantilla->id)->update($cambios + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // El texto viejo describía un momento de envío que ya no existe.
    }
};
