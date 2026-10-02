<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los bloques que faltan en los dos correos de inscripción.
 *
 * 1. `{{ bloque_soy_parte }}`, el botón «Cuenta que eres parte» de la
 *    confirmación de inscripción (decisión del 02/10 sobre el punto 7 del
 *    30/09).
 * 2. `{{ bloque_calendario }}`, en la confirmación y en el recordatorio. Se
 *    añadió a la siembra el 02/09 **sin migración**, y la siembra usa
 *    `firstOrCreate`: toda plantilla creada antes de ese día se quedó sin él,
 *    y el correo salía sin los enlaces para añadir la actividad al calendario
 *    sin que nadie lo notara.
 *
 * **Sólo se toca lo que siga como se sembró.** El ancla es el párrafo de
 * «cancelar» tal como lo escribe la siembra: los bloques van justo después,
 * que es donde los pone ella. Si la ONG reescribió ese párrafo, o el bloque ya
 * está, no se hace nada; los marcadores salen en la lista de variables de
 * Plantillas de correo y se pueden poner a mano.
 */
return new class extends Migration
{
    /** El párrafo de «cancelar» de cada plantilla, tal como lo siembra EmailTemplateSeeder. */
    private const ANCLAS = [
        'inscripcion_confirmada' => '<p style="margin:0 0 14px;">Si al final no puedes ir, avísanos para liberar tu cupo: '
            .'<a href="{{ enlace_cancelar }}" style="color:#cc6600;">cancelar mi inscripción</a>.</p>',
        'recordatorio' => '<p style="margin:0 0 14px;">Si ya no puedes asistir, <a href="{{ enlace_cancelar }}" style="color:#cc6600;">'
            .'cancela tu inscripción</a> para que otra persona pueda ocupar tu cupo.</p>',
    ];

    /** Lo que va después del ancla en cada una, en orden. */
    private const BLOQUES = [
        'inscripcion_confirmada' => ['{{ bloque_calendario }}', '{{ bloque_soy_parte }}'],
        'recordatorio' => ['{{ bloque_calendario }}'],
    ];

    public function up(): void
    {
        foreach (self::BLOQUES as $clave => $bloques) {
            $plantilla = DB::table('email_templates')->where('clave', $clave)->first();

            if (! $plantilla) {
                continue;
            }

            $cuerpo = (string) $plantilla->cuerpo_html;
            $original = $cuerpo;
            // Cada bloque va detrás del último que ya esté; el primero, detrás
            // del párrafo de «cancelar».
            $ancla = self::ANCLAS[$clave];

            foreach ($bloques as $bloque) {
                if (str_contains($cuerpo, trim($bloque, '{} '))) {
                    $ancla = $bloque;

                    continue;
                }

                if (! str_contains($cuerpo, $ancla)) {
                    break;
                }

                $cuerpo = preg_replace('/'.preg_quote($ancla, '/').'/', $ancla."\n".$bloque, $cuerpo, 1);
                $ancla = $bloque;
            }

            if ($cuerpo !== $original) {
                DB::table('email_templates')->where('id', $plantilla->id)->update([
                    'cuerpo_html' => $cuerpo,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No se deshace: el calendario faltaba por error, y quitar el botón
        // es cosa de la ONG desde el panel.
    }
};
