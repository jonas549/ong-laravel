<?php

namespace App\Support;

/**
 * Lo que una persona escribe en un campo de fecha o de hora, puesto en ISO.
 *
 * Es lo contrario de `App\Support\Fecha`, que coge lo que hay guardado y lo
 * pinta. Esto coge lo que llega del formulario y lo deja como lo espera la base.
 *
 * **Existe porque los campos son de texto, y eso no es un descuido.** Los
 * nativos `type="date"` y `type="time"` no dejan pegar, y pegar la fecha desde
 * otro sitio es de lo que más se hace aquí; el prototipo también los trae como
 * texto. El precio es que hay que entender lo que la gente escriba, y ese
 * entendimiento tiene que ser **el mismo en el navegador y en el servidor**: si
 * el campo acepta algo que el servidor rechaza, el formulario rebota sin
 * motivo aparente; si el campo exige más que el servidor, frena envíos que
 * habrían valido. Su gemelo en el navegador son `campoFecha` y `campoHora`, en
 * resources/js/formularios.js.
 *
 * Antes vivía copiado en `PublishActivityRequest` y en `UpdateActivityRequest`.
 * Dos copias de una regla de lectura es como acaban diciendo cosas distintas.
 */
final class FechaEscrita
{
    /**
     * «04 / 12 / 2026», «4-12-2026» o «2026-12-04» → «2026-12-04».
     *
     * Lo que no se entienda se devuelve tal cual, para que falle la regla
     * `date` con el valor a la vista y el aviso hable de lo que la persona
     * escribió.
     */
    public static function fecha(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        if (preg_match('/^(\d{4})\D+(\d{1,2})\D+(\d{1,2})$/', $texto, $m)) {
            [, $anio, $mes, $dia] = $m;
        } elseif (preg_match('/^(\d{1,2})\D+(\d{1,2})\D+(\d{4})$/', $texto, $m)) {
            [, $dia, $mes, $anio] = $m;
        } else {
            return $texto;
        }

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /**
     * «09:00», «9.00», «9», «930», «0930» o «09:00:00» → «09:00».
     *
     * Los tres del medio son la parte nueva, del 2026-09-02. La versión
     * anterior exigía un separador, así que «0930» —que es lo que sale de
     * teclear cuatro cifras seguidas, y lo que se pega desde media hoja de
     * cálculo— se le atragantaba y rebotaba el formulario. El campo del
     * navegador ya lo entendía; esto es lo que hace que el servidor no sea más
     * estricto que él, y de paso vale para quien navegue sin JavaScript.
     */
    public static function hora(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        // Con separador: 9:00, 09.00, 09:00:00.
        if (preg_match('/^(\d{1,2})\D(\d{2})/', $texto, $m)) {
            [, $hora, $minuto] = $m;
        } elseif (preg_match('/^(\d{1,4})$/', $texto, $m)) {
            // Cifras seguidas: «9» son las nueve en punto; en «930» y «0930»
            // las dos últimas son los minutos.
            $digitos = $m[1];

            [$hora, $minuto] = strlen($digitos) <= 2
                ? [$digitos, '00']
                : [substr($digitos, 0, -2), substr($digitos, -2)];
        } else {
            return $texto;
        }

        /*
         * Una hora que no existe se devuelve tal cual, sin darle forma.
         * Redondearla a «99:00» hacía dos cosas malas: el formulario volvía con
         * un valor que la persona no había escrito, y el aviso hablaba de algo
         * que no estaba mirando. Que la rechace `date_format:H:i`, que para eso
         * tiene el mensaje escrito con un ejemplo.
         */
        if ((int) $hora > 23 || (int) $minuto > 59) {
            return $texto;
        }

        return sprintf('%02d:%02d', $hora, $minuto);
    }
}
