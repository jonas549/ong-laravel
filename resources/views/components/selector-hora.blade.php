@props([
    'name',
    // La hora guardada, «HH:MM», o vacío.
    'valor' => '',
    // Expresión de Alpine que lo desactiva, si la hay.
    'desactivar' => null,
])

{{--
    B4 de la sexta tanda: un <select> normal con las 24 horas en punto.

    Antes había un campo de texto con un desplegable propio de seis filas
    visibles, y al cliente sólo le salían de las 00:00 a las 05:00 sin poder
    bajar: la lista iba dentro de una tarjeta con `overflow:hidden` y en el
    teléfono se recortaba. El desplegable nativo no tiene ese problema en
    ningún sitio: lo pinta el sistema, fuera de la página.

    **La opción vacía va entre las 8:00 AM y las 9:00 AM, y es a propósito.**
    Un <select> nativo se abre por la opción elegida, y el encargo pide que
    abra en las 9:00 AM *sin* elegirla: una hora puesta de oficio se enviaría
    aunque nadie la hubiera tocado. Con la vacía ahí, el desplegable abre justo
    encima de las 9:00 AM, se puede subir a la madrugada y bajar hasta las
    11:00 PM, y además sirve para quitar una hora ya puesta.

    No mira la hora actual, a propósito también.

    Lo que viaja es «HH:MM», lo mismo que antes, así que las reglas del
    servidor no cambian. Una hora guardada que no sea en punto —las fichas
    antiguas se escribían a mano— se añade en su sitio para no perderla al
    guardar.
--}}
@php
    $valor = $valor ? substr((string) $valor, 0, 5) : '';

    $etiqueta = function (string $hhmm): string {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return (($h % 12) ?: 12).':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).($h < 12 ? ' AM' : ' PM');
    };

    $horas = array_map(fn ($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00', range(0, 23));

    if ($valor !== '' && preg_match('/^\d{2}:\d{2}$/', $valor) && ! in_array($valor, $horas, true)) {
        $horas[] = $valor;
        sort($horas);
    }
@endphp

<select {{ $attributes->merge(['class' => 'fld selector-hora']) }} name="{{ $name }}"
        @if ($desactivar) x-bind:disabled="{{ $desactivar }}" @endif>
    @foreach ($horas as $h)
        @if ($h === '09:00')
            <option value="" @selected($valor === '')>Elegir hora</option>
        @endif
        <option value="{{ $h }}" @selected($valor === $h)>{{ $etiqueta($h) }}</option>
    @endforeach
</select>
