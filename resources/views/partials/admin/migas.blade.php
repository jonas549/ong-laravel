{{--
    Migas de pan.

    Salen del mismo árbol que el menú, así que no hay que escribirlas en cada
    vista ni se quedan desfasadas cuando un nodo cambia de sitio.

    Pero no todas las pantallas están en el árbol —el perfil, el buscador, el
    listado de usuarios sin `?rol`— y ahí antes no salía ninguna miga: la página
    quedaba sin decir dónde estabas ni cómo volver. Lo reportó el testing en
    producción. Cuando el árbol no reconoce la pantalla, la vista puede declarar
    las suyas con `@section('migaPadre')` y `@section('miga')`.
--}}
@php $camino = \App\Support\MenuPanel::camino(); @endphp

@if (count($camino) > 0 || View::hasSection('miga'))
    <nav class="migas" aria-label="Dónde estás">
        <a href="{{ route('admin.dashboard') }}">Panel</a>

        @foreach ($camino as $paso)
            @php $url = \App\Support\MenuPanel::url($paso); @endphp
            <span class="migas-sep" aria-hidden="true">→</span>

            @if ($url && ! $loop->last)
                <a href="{{ $url }}">{{ $paso['texto'] }}</a>
            @else
                <span @if ($loop->last && ! View::hasSection('miga')) aria-current="page" @endif>{{ $paso['texto'] }}</span>
            @endif
        @endforeach

        @hasSection('migaPadre')
            <span class="migas-sep" aria-hidden="true">→</span>
            <span>@yield('migaPadre')</span>
        @endif

        @hasSection('miga')
            <span class="migas-sep" aria-hidden="true">→</span>
            <span aria-current="page">@yield('miga')</span>
        @endif
    </nav>
@endif
