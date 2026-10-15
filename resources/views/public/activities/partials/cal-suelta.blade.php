{{-- Una actividad sin día, en la franja de arriba del calendario. Espera `$act`. --}}
@php $tema = $act->termsDe('tema')->first(); @endphp
<a class="cal-suelta" href="{{ route('activities.show', $act) }}">
    @if ($tema)
        <span class="cal-act-tema">{{ $tema->nombre }}</span>
    @endif
    <span class="cal-suelta-nombre dato-editable">{{ $act->titulo }}</span>
    <span class="cal-suelta-lugar">{{ $act->lugar }}</span>
</a>
