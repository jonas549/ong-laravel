{{--
    Paginación del panel.

    Laravel 11 y 12 traen las vistas de paginación en Tailwind, y este proyecto
    no carga Tailwind: `{{ $filas->links() }}` estaba pintando su marcado sin
    ninguno de sus estilos. Funcionaba —los enlaces iban— pero se veía roto.

    Esta usa el CSS propio del panel. Se registra en AppServiceProvider con
    `Paginator::defaultView()`, así que vale para todos los listados sin que
    haya que decirlo en cada uno.
--}}
@if ($paginator->hasPages())
    <nav class="paginacion" aria-label="Páginas">
        <span class="paginacion-cuenta">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </span>

        <span class="paginacion-botones">
            @if ($paginator->onFirstPage())
                <span class="paginacion-enlace paginacion-inerte" aria-disabled="true">‹ Anterior</span>
            @else
                <a class="paginacion-enlace" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Anterior</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="paginacion-hueco" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="paginacion-enlace paginacion-actual" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="paginacion-enlace" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="paginacion-enlace" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente ›</a>
            @else
                <span class="paginacion-enlace paginacion-inerte" aria-disabled="true">Siguiente ›</span>
            @endif
        </span>
    </nav>
@endif
