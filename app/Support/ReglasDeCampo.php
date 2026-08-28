<?php

namespace App\Support;

/**
 * Traduce las reglas de validación de Laravel a lo que entiende el navegador.
 *
 * **La validación en tiempo real no puede tener sus propias reglas.** Si el
 * cliente comprueba una cosa y el servidor otra, en algún momento dejan de
 * decir lo mismo: o el formulario deja pasar algo que luego rebota, o marca en
 * rojo algo que el servidor habría aceptado. Las dos son formas de que la
 * persona deje de creerse los avisos.
 *
 * Así que la fuente es una sola: las reglas que ya están escritas para el
 * servidor —`required|string|max:255`— se leen aquí y salen como atributos
 * (`required`, `maxlength`) y como pistas para el aviso en pantalla. El
 * servidor sigue validando igual: esto adelanta el aviso, no lo sustituye.
 *
 * Lo que no se puede traducir —`unique`, `exists`, una regla propia— no se
 * intenta: se queda sin aviso previo y lo dice el servidor al enviar. Fingir
 * que se comprueba algo que no se comprueba sería peor que no comprobarlo.
 *
 * `required_if` sí se traduce, pero **sólo como pista, nunca como atributo
 * HTML**: `required` a secas bloquearía el envío también cuando la condición no
 * se cumple. El aviso en pantalla mira el otro campo y aparece o no según su
 * valor, que es lo mismo que hace el servidor.
 */
class ReglasDeCampo
{
    /**
     * Los atributos HTML que corresponden a unas reglas.
     *
     * @param  string|array<int, mixed>  $reglas
     * @return array<string, string|bool>
     */
    public static function atributos(string|array $reglas): array
    {
        $lista = static::normalizar($reglas);
        $attrs = [];

        foreach ($lista as $regla) {
            [$nombre, $valor] = static::partir($regla);

            match ($nombre) {
                'required' => $attrs['required'] = true,
                // `required_if` a propósito no pone nada: ver la cabecera.
                'email' => $attrs['type'] = 'email',
                'url' => $attrs['type'] = 'url',
                'numeric', 'integer' => $attrs['inputmode'] = 'numeric',
                'max' => $attrs[static::esNumero($lista) ? 'max' : 'maxlength'] = $valor,
                'min' => $attrs[static::esNumero($lista) ? 'min' : 'minlength'] = $valor,
                default => null,
            };
        }

        return $attrs;
    }

    /**
     * Lo mismo, pero para el componente de Alpine que avisa mientras se escribe.
     *
     * Se manda como JSON al navegador. Va aparte de los atributos HTML porque
     * el aviso del navegador sale en el idioma del navegador y en su sitio, y
     * el panel está en español y con su propio estilo.
     *
     * @param  string|array<int, mixed>  $reglas
     * @return array<string, mixed>
     */
    public static function paraElNavegador(string|array $reglas, string $etiqueta = ''): array
    {
        $lista = static::normalizar($reglas);
        $numero = static::esNumero($lista);
        $pistas = ['etiqueta' => $etiqueta, 'numero' => $numero];

        foreach ($lista as $regla) {
            [$nombre, $valor] = static::partir($regla);

            match ($nombre) {
                'required' => $pistas['requerido'] = true,
                'required_if' => $pistas['requeridoSi'] = static::condicion($valor),
                'email' => $pistas['formato'] = 'email',
                'url' => $pistas['formato'] = 'url',
                'max' => $pistas['max'] = (int) $valor,
                'min' => $pistas['min'] = (int) $valor,
                default => null,
            };
        }

        return $pistas;
    }

    /**
     * `required_if:tipo,Otra` o `required_if:tipo,Otra,Ninguna`.
     *
     * Devuelve de qué campo depende y con qué valores hace falta.
     *
     * @return array{campo: string, valores: array<int, string>}
     */
    private static function condicion(string $valor): array
    {
        $trozos = array_map('trim', explode(',', $valor));
        $campo = array_shift($trozos) ?? '';

        return ['campo' => $campo, 'valores' => array_values(array_filter($trozos, fn ($v) => $v !== ''))];
    }

    /**
     * @param  string|array<int, mixed>  $reglas
     * @return array<int, string>
     */
    private static function normalizar(string|array $reglas): array
    {
        $lista = is_string($reglas) ? explode('|', $reglas) : $reglas;

        // Una regla puede ser un objeto (`new CorreoEnviable`) o un `Rule::in`:
        // esos no se traducen y se descartan aquí en vez de reventar abajo.
        return collect($lista)
            ->filter(fn ($r) => is_string($r))
            ->map(fn (string $r) => trim($r))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array{0: string, 1: string} */
    private static function partir(string $regla): array
    {
        $trozos = explode(':', $regla, 2);

        return [mb_strtolower($trozos[0]), $trozos[1] ?? ''];
    }

    /**
     * ¿`max:5` habla de un número o de cuántas letras?
     *
     * En Laravel depende del tipo del valor, así que hay que mirar las demás
     * reglas: con `integer` o `numeric` delante, `max` es un tope numérico;
     * si no, es la longitud del texto. Confundirlos pone `maxlength=5` en un
     * campo de cantidad y deja de poder escribirse «100000».
     *
     * @param  array<int, string>  $lista
     */
    private static function esNumero(array $lista): bool
    {
        foreach ($lista as $regla) {
            if (in_array(mb_strtolower(explode(':', $regla)[0]), ['integer', 'numeric'], true)) {
                return true;
            }
        }

        return false;
    }
}
