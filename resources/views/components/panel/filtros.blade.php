@props([
    // Texto del campo de búsqueda. Vacío lo quita.
    'buscar' => 'Buscar…',
    // Nombre del parámetro de búsqueda.
    'campo' => 'q',
    // Enlaces de exportación: ['xlsx' => url, 'csv' => url]. Vacío los quita.
    'exportar' => [],
    // Ofrecer el selector de filas por página.
    'tamano' => true,
])

{{--
    La barra de filtros de un listado.

    Se envía por GET, así que el estado del listado vive en la URL: se puede
    guardar en marcadores, compartir por chat y volver atrás con el botón del
    navegador. Guardarlo en sesión —la otra opción— hace que dos pestañas del
    panel se pisen entre ellas.

    Los `<select>` se envían solos al cambiar; el buscador espera a que dejes de
    escribir. Es la misma regla que el buscador del panel: teclear no puede
    disparar una petición por letra.

    Los filtros propios de cada listado van en el slot por defecto.
--}}

<form method="GET" class="panel-filtros" x-data="barraFiltros()">
    {{--
        Los parámetros que NO son filtros viajan escondidos para no perderse al
        filtrar: el orden de la tabla y el tamaño de página. Sin esto, filtrar
        reseteaba la columna por la que estabas ordenando.

        `page` se deja fuera a propósito: al cambiar un filtro hay que volver a
        la primera página, porque la 7 del listado anterior no significa nada en
        el nuevo.
    --}}
    @foreach (['orden', 'dir'] as $oculto)
        @if (request()->filled($oculto))
            <input type="hidden" name="{{ $oculto }}" value="{{ \App\Support\Filtro::texto(request(), $oculto) }}">
        @endif
    @endforeach

    @if ($buscar)
        <label class="panel-filtros-buscar">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path>
            </svg>
            <input class="fld" type="search" name="{{ $campo }}" x-ref="buscador" x-on:input="tecleo()"
                   value="{{ \App\Support\Filtro::texto(request(), $campo) }}"
                   placeholder="{{ $buscar }}" aria-label="{{ $buscar }}">
        </label>
    @endif

    {{ $slot }}

    {{--
        El botón existe aunque los selects se envíen solos: sin JavaScript, o
        con él a medio cargar, tiene que haber una forma de filtrar. Se esconde
        cuando Alpine ya está en pie.
    --}}
    <button type="submit" class="btn btn-outline btn-sm" x-show="!listo" x-cloak>Filtrar</button>

    @if (request()->hasAny(array_merge([$campo], $filtrosActivos ?? [])) && collect(request()->except(['page', 'orden', 'dir', 'filas']))->filter(fn ($v) => filled($v))->isNotEmpty())
        <a class="btn btn-ghost btn-sm" href="{{ url()->current() }}">Quitar filtros</a>
    @endif

    <span style="flex:1;"></span>

    @if ($tamano)
        <label class="panel-filtros-tamano">
            <span class="helper">Filas</span>
            <select class="fld" name="filas" x-on:change="enviar()" aria-label="Filas por página">
                @foreach (\App\Support\Listado::tamanos() as $n)
                    <option value="{{ $n }}" @selected(\App\Support\Listado::porPagina(request()) === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </label>
    @endif

    @if ($exportar)
        <span class="panel-filtros-exportar">
            @foreach ($exportar as $formato => $url)
                <a class="btn btn-outline btn-sm" href="{{ $url }}" data-cargando="Preparando…">
                    {{ strtoupper($formato) }}
                </a>
            @endforeach
        </span>
    @endif
</form>
