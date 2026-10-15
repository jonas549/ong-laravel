{{--
    Un voluntariado sin día —convocatoria permanente— en la franja de arriba
    del calendario. Igual que el de actividades, con su categoría tal cual en
    el lugar del tema. Espera `$act`.
--}}
<a class="cal-suelta" href="{{ route('voluntariados.show', $act) }}">
    @if ($act->categoria_nombre)
        <span class="cal-act-tema">{{ $act->categoria_nombre }}</span>
    @endif
    <span class="cal-suelta-nombre dato-editable">{{ $act->titulo }}</span>
    <span class="cal-suelta-lugar">{{ $act->lugar }}</span>
</a>
