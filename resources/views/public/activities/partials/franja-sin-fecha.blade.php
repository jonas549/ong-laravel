{{--
    Las actividades que no caben en ninguna casilla.

    Son dos casos y se cuentan por separado porque significan cosas distintas:
    «disponible todo el año» es una decisión del organizador y «por definir» es
    un hueco que aún tiene que rellenar. Meterlas en un día al azar sería
    inventar un dato, y esconderlas les quitaría la visibilidad que hoy sí
    tienen en el listado.

    La franja va **encima de la rejilla y en todos los meses**: no dependen del
    mes que se esté mirando, así que desaparecer al pasar de página sería
    mentira.

    Se pliega con `<details>` y no con Alpine: es lo que hace el navegador sin
    JavaScript, ya trae el teclado resuelto y aquí no hace falta nada más.
--}}
@php
    $grupos = [
        [
            'clave' => 'permanentes',
            'titulo' => 'Disponibles todo el año',
            'pie' => 'Sin fecha concreta: se puede participar cuando quieras.',
            'items' => $sinFecha['permanentes'],
        ],
        [
            'clave' => 'por-definir',
            'titulo' => 'Con fecha por definir',
            'pie' => 'El organizador todavía no ha puesto el día.',
            'items' => $sinFecha['porDefinir'],
        ],
    ];

    $conAlgo = collect($grupos)->filter(fn ($g) => $g['items']->isNotEmpty());
@endphp

@if ($conAlgo->isNotEmpty())
    <div class="cal-franja">
        @foreach ($conAlgo as $grupo)
            <details class="cal-grupo" open>
                <summary class="cal-grupo-cabecera">
                    <svg class="cal-grupo-flecha" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    <span class="cal-grupo-titulo">{{ $grupo['titulo'] }}</span>
                    <span class="cal-grupo-cuenta">{{ $grupo['items']->count() }}</span>
                </summary>

                <p class="cal-grupo-pie">{{ $grupo['pie'] }}</p>

                <div class="cal-sueltas">
                    @foreach ($grupo['items'] as $act)
                        @php $tema = $act->termsDe('tema')->first(); @endphp
                        <a class="cal-suelta" href="{{ route('activities.show', $act) }}">
                            @if ($tema)
                                <span class="cal-act-tema">{{ $tema->nombre }}</span>
                            @endif
                            <span class="cal-suelta-nombre dato-editable">{{ $act->titulo }}</span>
                            <span class="cal-suelta-lugar">{{ $act->lugar }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
@endif
