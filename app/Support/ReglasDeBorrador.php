<?php

namespace App\Support;

use Illuminate\Validation\Rules\RequiredIf;

/**
 * «Guardar borrador»: las reglas de la actividad, sin lo obligatorio.
 *
 * Un borrador se guarda para seguir después, así que de la actividad sólo se
 * exige lo imprescindible para crear la fila y reconocerla en «Borradores»: el
 * nombre. Lo demás puede quedar vacío, pero **lo que se escriba tiene que ser
 * válido** —una fecha que no existe o un correo mal escrito no se guardan—.
 *
 * No es una segunda lista de reglas: se parte de las del formulario completo y
 * se les quita lo obligatorio. Las de la cuenta y la organización ni llegan:
 * un borrador sólo se guarda con sesión y cuelga de las que ya existen.
 *
 * Al enviarla a revisión pasa por las reglas completas, sin esto.
 */
trait ReglasDeBorrador
{
    /** Lo único que exige un borrador. */
    private const OBLIGATORIO_EN_BORRADOR = ['titulo'];

    /**
     * @param  array<string, mixed>  $reglas
     * @return array<string, mixed>
     */
    protected function sinObligatoriosDeLaActividad(array $reglas): array
    {
        foreach ($reglas as $campo => $lista) {
            if (in_array($campo, self::OBLIGATORIO_EN_BORRADOR, true)) {
                continue;
            }

            $sinObligatorios = array_values(array_filter(
                is_array($lista) ? $lista : explode('|', $lista),
                fn ($regla) => ! ($regla instanceof RequiredIf)
                    && ! (is_string($regla) && str_starts_with($regla, 'required')),
            ));

            /*
             * Y `nullable` siempre. Un campo de texto vacío llega como null
             * (`ConvertEmptyStringsToNull`), y sin `nullable` el `string` de
             * la descripción rechazaba el borrador con «tiene que ser texto».
             */
            $reglas[$campo] = in_array('nullable', $sinObligatorios, true)
                ? $sinObligatorios
                : ['nullable', ...$sinObligatorios];
        }

        return $reglas;
    }
}
