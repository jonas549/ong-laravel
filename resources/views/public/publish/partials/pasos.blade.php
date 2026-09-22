{{--
    Barra de pasos del wizard.

    Réplica del bloque que va justo bajo el header en publicar-actividad.html:
    franja blanca a todo el ancho, círculos numerados de 28px y una línea
    conectora de 26×1.5px entre pasos. Los cinco pasos y sus colores salen
    del stepDefs del prototipo.

    Necesita un `paso` en el ámbito de Alpine. En el wizard lo aporta el
    componente; en la pantalla de envío basta con x-data="{ paso: 5 }" más
    ['navegable' => false], porque ahí ya no hay formulario al que volver.
--}}
@php
    $pasos = [
        1 => '¿Voluntariado?',
        2 => 'Tipo de org.',
        3 => 'Tu organización',
        4 => 'Tu actividad',
        5 => 'Enviado',
    ];

    $navegable = $navegable ?? true;

    /*
     * C4: con la ficha de la organización completa, el paso 3 no se pinta, y
     * la barra **renumera los que quedan**: dejar el hueco —1, 2, 4, 5— o
     * dejar el círculo apagado eran las dos formas de que la barra dijera que
     * hay un paso que no se está viendo. Los números de dentro no cambian:
     * `paso` sigue siendo 4 para «Tu actividad».
     *
     * El botón se pinta igualmente y lo esconde Alpine, porque esto puede
     * cambiar sin recargar: si en el paso 2 se elige otro tipo de
     * organización, el 3 vuelve a hacer falta para pedir lo que ese tipo
     * exige. El servidor pinta el estado de partida —así no hay parpadeo— y a
     * partir de ahí manda el componente.
     */
    $saltable = isset($saltarPaso3);

    // B1: con sesión y tipo en la ficha, el 2 también se salta, y la barra
    // renumera igual.
    $saltados = array_keys(array_filter([2 => $saltarPaso2 ?? false, 3 => $saltarPaso3 ?? false]));
    $numero = fn (int $n) => $n - count(array_filter($saltados, fn ($s) => $s < $n));
@endphp

<div style="background:#fff;border-bottom:1px solid var(--linea);">
    <div style="max-width:900px;margin:0 auto;padding:18px 32px;display:flex;align-items:center;gap:14px;overflow-x:auto;">
        @foreach ($pasos as $n => $label)
            @php $oculto = in_array($n, $saltados, true); @endphp
            {{-- El paso 5 no se navega: se llega a él enviando el formulario. --}}
            <button type="button" class="steplink"
                    @if ($loop->last || ! $navegable) disabled @else x-on:click="irA({{ $n }})" @endif
                    @if ($oculto) style="display:none;" @endif
                    x-bind:style="estiloPaso(paso, {{ $n }}, {{ Js::from($navegable) }}@if ($saltable && $n === 2), saltaPaso2()@elseif ($saltable && $n === 3), saltaPaso3()@endif)">
                {{-- El número que se ve es la posición en la barra, no la clave
                     interna: con el paso 3 fuera, «Tu actividad» se enseña como
                     el 3 aunque dentro siga siendo el 4. --}}
                <span x-bind:style="estiloCirculoPaso(paso, {{ $n }})"
                      @if ($saltable) x-text="numeroEnBarra({{ $n }})" @endif>{{ $numero($n) }}</span>{{ $label }}
                @if (! $loop->last)
                    <span aria-hidden="true" style="width:26px;height:1.5px;background:#e6e8ea;margin-left:5px;"></span>
                @endif
            </button>
        @endforeach
    </div>
</div>
