@props([
    // El nombre con el que viaja en la URL. Sin él, la columna no ordena.
    'campo' => null,
    // Alinear a la derecha, para las de números.
    'num' => false,
])

{{--
    Un encabezado de tabla que ordena al pulsarlo.

    El enlace conserva el resto de la consulta —filtros, buscador, tamaño de
    página— y sólo cambia `orden` y `dir`. Sin eso, ordenar borraba el filtro
    que acababas de poner.

    **Vuelve a la página 1.** Ordenar de otra forma cambia qué filas caen en
    cada página, así que quedarse en la 7 lleva a un sitio que no tiene nada que
    ver con lo que estabas mirando.

    Qué columnas se admiten lo decide el servidor con `Listado::ordenar()`: esto
    sólo dibuja el enlace.
--}}

@php
    $activo = $campo && request('orden') === $campo;
    $direccion = $activo && request('dir') === 'asc' ? 'desc' : 'asc';

    $enlace = $campo
        ? request()->fullUrlWithQuery(['orden' => $campo, 'dir' => $direccion, 'page' => null])
        : null;
@endphp

<th @class(['num' => $num]) @if ($activo) aria-sort="{{ request('dir') === 'desc' ? 'descending' : 'ascending' }}" @endif>
    @if ($enlace)
        {{-- Un solo `class`: `@class` emite el atributo entero, asi que junto a
             un `class=` estatico salian dos y el navegador se queda con el
             primero. La clase de activa no llegaba a aplicarse nunca. --}}
        <a href="{{ $enlace }}" @class(['col-orden', 'col-orden-activa' => $activo])>
            {{ $slot }}
            <span class="col-orden-flecha" aria-hidden="true">
                @if ($activo)
                    {{ request('dir') === 'desc' ? '↓' : '↑' }}
                @else
                    ↕
                @endif
            </span>
        </a>
    @else
        {{ $slot }}
    @endif
</th>
