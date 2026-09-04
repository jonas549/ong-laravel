{{--
    Una actividad dentro de una casilla del calendario: categoría, nombre y hora,
    que es lo que pidió el cliente y el orden en que se lee.

    Espera `$act` y `$dia` (el Carbon de la casilla, para saber si la actividad
    empieza ahí o viene de arrastre).
--}}
@php
    /*
     * Los días siguientes de una actividad de varios se marcan. Sin la marca
     * parecen actividades distintas repetidas por error, que es justo lo que
     * hace dudar de un calendario.
     */
    $arranca = $act->fecha_inicio->format('Y-m-d') === $dia->format('Y-m-d');
    $tema = $act->termsDe('tema')->first();
    $hora = $act->hora_inicio ? substr((string) $act->hora_inicio, 0, 5) : null;
@endphp

<a @class(['cal-act', 'cal-act--sigue' => ! $arranca])
   href="{{ route('activities.show', $act) }}"
   @unless ($arranca) title="Continúa desde el {{ $act->fecha_inicio->locale('es')->isoFormat('D [de] MMMM') }}" @endunless>

    @if ($tema)
        <span class="cal-act-tema">{{ $tema->nombre }}</span>
    @endif

    <span class="cal-act-nombre dato-editable">{{ $act->titulo }}</span>

    @if ($hora)
        <span class="cal-act-hora">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            {{ $hora }}@if ($act->hora_termino)–{{ substr((string) $act->hora_termino, 0, 5) }}@endif
        </span>
    @endif
</a>
