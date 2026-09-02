<?php

namespace App\Services;

use App\Models\EmailTemplate;

/**
 * Resuelve los marcadores {{ variable }} de una plantilla.
 *
 * A propósito NO usa Blade ni `eval`: el cuerpo lo escribe una persona desde
 * el panel, y Blade ejecutaría PHP arbitrario. Aquí sólo se sustituyen los
 * nombres que estén en la lista blanca de la plantilla; cualquier otro se
 * deja tal cual, que es visible y no hace daño.
 */
class EmailTemplateRenderer
{
    /**
     * @param  array<string, string|null>  $datos
     * @return array{asunto: string, html: string}
     */
    public function render(EmailTemplate $plantilla, array $datos): array
    {
        $permitidas = $plantilla->variablesDisponibles();

        return [
            'asunto' => $this->sustituir($plantilla->asunto, $datos, $permitidas, escapar: false),
            'html' => $this->sustituir($plantilla->cuerpo_html, $datos, $permitidas, escapar: true),
        ];
    }

    /**
     * Datos de ejemplo para la vista previa y el envío de prueba, para que se
     * vea cómo queda sin necesidad de una inscripción real.
     *
     * @return array<string, string>
     */
    public function datosDeEjemplo(EmailTemplate $plantilla): array
    {
        $muestras = [
            'nombre' => 'María González',
            'correo' => 'maria@ejemplo.cl',
            'correo_inscrito' => 'maria@ejemplo.cl',
            'organizacion' => 'Fundación Junto al Barrio',
            'actividad' => 'Jornada de reforestación urbana',
            'fecha' => '4 de diciembre de 2026',
            'hora' => '09:00 a 13:00',
            'lugar' => 'Parque Juan XXIII, Ñuñoa',
            'dias' => '3',
            'cupos_disponibles' => '62',
            'sitio' => config('app.name'),
            'enlace_cuenta' => route('account.login'),
            'enlace_actividad' => url('/actividades'),
            'enlace_actividades' => url('/actividades'),
            'enlace_cancelar' => url('/inscripcion/ejemplo/cancelar'),
            'enlace_participantes' => route('account.activities.index'),
            'bloque_calendario' => '<p style="margin:22px 0 0;font-size:14px;color:#63666A;">'
                .'Añádelo a tu calendario: <a href="#" style="color:#cc6600;font-weight:600;">Google Calendar</a>'
                .' &nbsp;·&nbsp; <a href="#" style="color:#cc6600;font-weight:600;">Apple, Outlook y otros</a></p>',
        ];

        return collect($plantilla->variablesDisponibles())
            ->mapWithKeys(fn ($v) => [$v => $muestras[$v] ?? '—'])
            ->all();
    }

    /**
     * @param  array<string, string|null>  $datos
     * @param  array<int, string>  $permitidas
     */
    private function sustituir(string $texto, array $datos, array $permitidas, bool $escapar): string
    {
        foreach ($permitidas as $clave) {
            $valor = (string) ($datos[$clave] ?? '');

            /*
             * Dos prefijos entran sin escapar, y conviene saber por qué:
             *
             * - `enlace_`: va dentro de un href, donde escapar rompe la URL.
             * - `bloque_`: ES html, montado por nosotros pieza a pieza en
             *   `App\Services\Calendario`, con sus propias URLs ya escapadas.
             *
             * **Ninguno de los dos puede alimentarse de texto que escriba
             * una persona.** Todo lo que llega por ahí lo genera el código a
             * partir de rutas y datos ya validados. El día que alguien meta
             * un `bloque_` con contenido de un formulario, esto es una
             * inyección de HTML en el correo de otro.
             */
            $confiable = str_starts_with($clave, 'enlace_') || str_starts_with($clave, 'bloque_');

            $valor = $escapar && ! $confiable ? e($valor) : $valor;

            $texto = preg_replace(
                '/\{\{\s*' . preg_quote($clave, '/') . '\s*\}\}/',
                // El valor puede traer $ o \, que en el reemplazo son especiales.
                str_replace(['\\', '$'], ['\\\\', '\\$'], $valor),
                $texto
            );
        }

        return $texto;
    }
}
