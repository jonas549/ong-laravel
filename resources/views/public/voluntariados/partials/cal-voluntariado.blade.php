{{--
    Un voluntariado dentro de una casilla del calendario: categoría y nombre,
    como `cal-actividad`. Sin hora: la API sólo trae fechas.

    Espera `$act` y `$dia` (el Carbon de la casilla, para saber si empieza ahí
    o viene de arrastre).
--}}
@php
    $arranca = $act->fecha_inicio->format('Y-m-d') === $dia->format('Y-m-d');
@endphp

<a @class(['cal-act', 'cal-act--sigue' => ! $arranca])
   href="{{ route('voluntariados.show', $act) }}"
   @unless ($arranca) title="Continúa desde el {{ $act->fecha_inicio->locale('es')->isoFormat('D [de] MMMM') }}" @endunless>

    @if ($act->categoria_nombre)
        <span class="cal-act-tema">{{ $act->categoria_nombre }}</span>
    @endif

    <span class="cal-act-nombre dato-editable">{{ $act->titulo }}</span>
</a>
