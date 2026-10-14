<?php

namespace App\Support;

use App\Models\Commune;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cuándo una actividad tiene lugar físico, y qué se le pide de él.
 *
 * **Un solo sitio para la condición.** Vivía copiada en los dos Form Requests
 * —el del wizard y el del editor de «Mi cuenta»— y en las marcas `data-*` del
 * formulario, y por eso el arreglo de la dirección online se quedó a medias:
 * se tocó la dirección y no la región ni la comuna. Ahora la leen de aquí las
 * reglas de los dos formularios, el parcial que pinta los campos y lo que se
 * guarda en la base.
 *
 * Las reglas son dos y van juntas:
 *
 *  - Una actividad **online** no tiene lugar: ni región, ni comuna, ni
 *    dirección. No se piden, no se validan y no se guardan.
 *  - Una **disponible de forma permanente** puede no tener sitio fijo: los
 *    tres campos se ofrecen, pero son opcionales.
 *
 * Híbrido sigue pidiéndolo todo: tiene una parte presencial.
 */
final class LugarDeActividad
{
    /** Los formatos sin lugar físico. */
    public const FORMATOS_SIN_LUGAR = ['Online'];

    public static function tieneLugar(?string $formato): bool
    {
        return ! in_array($formato, self::FORMATOS_SIN_LUGAR, true);
    }

    /** Si hay que exigir región, comuna y dirección. */
    public static function seExige(?string $formato, bool $permanente): bool
    {
        return ! $permanente && self::tieneLugar($formato);
    }

    /**
     * Las reglas de los cinco campos del lugar.
     *
     * Un `requiredIf` con la condición entera y no dos `required_*` sueltos:
     * Laravel evalúa cada regla por separado y basta con que una dispare para
     * que el campo sea obligatorio, así que sumarlas daría un O y lo que hace
     * falta es un Y —sólo si NO es permanente y ADEMÁS tiene lugar—.
     *
     * El borrador se las quita con el resto de lo obligatorio
     * (`ReglasDeBorrador`).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function reglas(Request $request): array
    {
        $obligatorio = [Rule::requiredIf(
            fn () => self::seExige($request->input('formato'), $request->boolean('sin_fecha_definida'))
        )];

        return [
            'region_id' => ['nullable', ...$obligatorio, 'exists:regions,id'],
            'commune_id' => ['nullable', ...$obligatorio, 'exists:communes,id'],
            'direccion' => ['nullable', ...$obligatorio, 'string', 'max:255'],

            /*
             * El punto de la dirección (P16). Nulos si nadie eligió una
             * sugerencia, que es lo normal: el campo es texto libre y esto
             * ayuda, no obliga. Los rangos son los del planeta; lo que llega
             * aquí lo escribe el navegador.
             */
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        return [
            'region_id.required' => 'Elige la región donde ocurre la actividad.',
            'commune_id.required' => 'Elige la comuna donde ocurre la actividad.',
            'direccion.required' => 'Escribe la dirección, o marca que está disponible de forma permanente.',
        ];
    }

    /**
     * La marca que lee la guía de errores del navegador:
     * `data-obligatorio-salvo-valor="formato:Online"`.
     */
    public static function marcaDelNavegador(): string
    {
        return 'formato:'.implode(',', self::FORMATOS_SIN_LUGAR);
    }

    /**
     * Lo que se guarda en la actividad.
     *
     * Sin lugar, todo vacío aunque llegue algo: en pantalla esos campos van
     * deshabilitados y no viajan, pero un envío hecho a mano —o una ficha que
     * era presencial y pasa a online— no puede dejar una comuna colgando de
     * una actividad que no ocurre en ninguna.
     *
     * La región sale de la comuna cuando la hay, y si no, de la elegida: un
     * borrador puede tener región y todavía no comuna.
     *
     * @param  array<string, mixed>  $datos  lo validado
     * @return array<string, mixed>
     */
    public static function campos(array $datos): array
    {
        if (! self::tieneLugar($datos['formato'] ?? null)) {
            return [
                'region_id' => null,
                'commune_id' => null,
                'direccion' => null,
                'latitud' => null,
                'longitud' => null,
            ];
        }

        $comuna = Commune::find($datos['commune_id'] ?? null);

        return [
            'region_id' => $comuna?->region_id ?? ($datos['region_id'] ?? null),
            'commune_id' => $comuna?->id,
            'direccion' => $datos['direccion'] ?? null,
            // El punto de la sugerencia elegida (P16). Cambiar la dirección a
            // mano lo borra: el navegador manda los dos campos vacíos.
            'latitud' => $datos['latitud'] ?? null,
            'longitud' => $datos['longitud'] ?? null,
        ];
    }
}
