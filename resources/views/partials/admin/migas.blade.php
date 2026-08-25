{{--
    Migas de pan.

    Salen del mismo árbol que el menú, así que no hay que escribirlas en cada
    vista ni se quedan desfasadas cuando un nodo cambia de sitio.
--}}
@php $camino = \App\Support\MenuPanel::camino(); @endphp

@if (count($camino) > 0)
    <nav class="migas" aria-label="Dónde estás">
        <a href="{{ route('admin.dashboard') }}">Panel</a>

        @foreach ($camino as $paso)
            @php $url = \App\Support\MenuPanel::url($paso); @endphp
            <span class="migas-sep" aria-hidden="true">→</span>

            @if ($url && ! $loop->last)
                <a href="{{ $url }}">{{ $paso['texto'] }}</a>
            @else
                <span @if ($loop->last) aria-current="page" @endif>{{ $paso['texto'] }}</span>
            @endif
        @endforeach

        @hasSection('miga')
            <span class="migas-sep" aria-hidden="true">→</span>
            <span aria-current="page">@yield('miga')</span>
        @endif
    </nav>
@endif
