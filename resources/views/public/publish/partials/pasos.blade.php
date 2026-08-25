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
@endphp

<div style="background:#fff;border-bottom:1px solid var(--linea);">
    <div style="max-width:900px;margin:0 auto;padding:18px 32px;display:flex;align-items:center;gap:14px;overflow-x:auto;">
        @foreach ($pasos as $n => $label)
            {{-- El paso 5 no se navega: se llega a él enviando el formulario. --}}
            <button type="button" class="steplink"
                    @if ($loop->last || ! $navegable) disabled @else x-on:click="irA({{ $n }})" @endif
                    x-bind:style="estiloPaso(paso, {{ $n }}, {{ Js::from($navegable) }})">
                <span x-bind:style="estiloCirculoPaso(paso, {{ $n }})">{{ $n }}</span>{{ $label }}
                @if (! $loop->last)
                    <span aria-hidden="true" style="width:26px;height:1.5px;background:#e6e8ea;margin-left:5px;"></span>
                @endif
            </button>
        @endforeach
    </div>
</div>
