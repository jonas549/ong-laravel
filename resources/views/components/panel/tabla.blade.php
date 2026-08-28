@props([
    // La colección o el paginador de filas.
    'filas',
    // Qué decir cuando no hay ninguna.
    'vacio' => 'Todavía no hay registros.',
    // A dónde van las acciones masivas. Sin esto no hay casillas de selección.
    'accionesEn' => null,
    /*
     * Las acciones masivas: ['clave' => ['texto' => …, 'peligro' => bool]].
     * Cada una manda `accion=<clave>` junto con los ids marcados.
     */
    'acciones' => [],
    // Nombre de lo que se lista, para los mensajes («3 noticias seleccionadas»).
    'que' => 'registros',
    // Cuántas columnas tiene la tabla, para el colspan del estado vacío.
    'columnas' => 1,
    // El id del formulario de acciones. Sólo hay que cambiarlo si en una misma
    // pantalla conviven dos tablas con selección.
    'idAcciones' => 'acciones-de-la-tabla',
])

{{--
    La tabla del panel.

    Reúne lo que estaba copiado en cada listado: el envoltorio con scroll, el
    estado vacío con su colspan, la paginación y la selección múltiple con sus
    acciones.

    **El formulario de las acciones masivas NO envuelve la tabla**, y esto no es
    un detalle de estilo: HTML no admite formularios anidados. Cuando envolvía,
    el analizador se comía los `<form>` de cada fila —el de esconder, el de
    restaurar, el de verificar— y sus botones acababan enviando el formulario de
    las acciones masivas sin ninguna fila marcada. El síntoma era «no había
    ninguna fila seleccionada» al pulsar un botón que no tenía nada que ver.

    Se resuelve con el atributo `form` de HTML5: el formulario va vacío y aparte,
    y las casillas y los botones se asocian a él por su id desde donde estén.

    La cabecera va en el slot `cabecera` y las filas en el slot por defecto, en
    vez de declararse como un array de columnas: cada listado pinta sus celdas
    de forma distinta —una insignia de estado, una miniatura, dos botones— y un
    componente que intentara cubrir todo eso acabaría con más opciones que
    código.

    La selección vive en Alpine. La raíz se guarda en `init()` y no se lee de
    `$el`: dentro de un manejador puesto en una casilla, `$el` es esa casilla.
    Ya costó cuatro fallos en este proyecto.
--}}

@php
    $seleccionable = $accionesEn && count($acciones) > 0;
    $totalColumnas = (int) $columnas + ($seleccionable ? 1 : 0);
    $paginado = $filas instanceof \Illuminate\Contracts\Pagination\Paginator;
@endphp

<div {{ $attributes->merge(['class' => 'panel-tabla']) }}
     @if ($seleccionable) x-data="tablaSeleccion({{ Js::from($que) }})" @endif>

    @if ($seleccionable)
        {{-- Vacío y aparte. Las casillas y los botones se enganchan por `form`. --}}
        <form method="POST" action="{{ $accionesEn }}" id="{{ $idAcciones }}" x-ref="formulario"
              x-on:submit="if (! confirmarAccion($event)) $event.preventDefault()">
            @csrf
        </form>
    @endif

    <div class="tabla-wrap">
        <table class="tabla">
            <thead>
                <tr>
                    @if ($seleccionable)
                        <th style="width:34px;">
                            <input type="checkbox" x-ref="todas" x-on:change="marcarTodas($event.target.checked)"
                                   aria-label="Seleccionar todo lo que se ve">
                        </th>
                    @endif
                    {{ $cabecera }}
                </tr>
            </thead>
            <tbody>
                @if (count($filas) === 0)
                    <tr>
                        <td colspan="{{ $totalColumnas }}" style="color:var(--gris);padding:26px 14px;">{{ $vacio }}</td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @if ($seleccionable)
        {{--
            La barra de acciones aparece al marcar algo y va abajo, pegada a la
            tabla: arriba obligaría a subir después de marcar la última fila, que
            es justo donde está la mano.
        --}}
        <div class="tabla-acciones" x-show="marcadas > 0" x-cloak>
            <span class="tabla-acciones-cuenta" x-text="resumen()"></span>

            <div class="tabla-acciones-botones">
                @foreach ($acciones as $clave => $accion)
                    <button type="submit" form="{{ $idAcciones }}" name="accion" value="{{ $clave }}"
                            class="btn btn-sm {{ ($accion['peligro'] ?? false) ? 'btn-danger' : 'btn-outline' }}"
                            @if ($accion['peligro'] ?? false)
                                data-confirmar="{{ $accion['confirmar'] ?? '¿Seguro? Esta acción no se puede deshacer.' }}"
                            @endif>
                        {{ $accion['texto'] }}
                    </button>
                @endforeach

                <button type="button" class="btn btn-ghost btn-sm" x-on:click="limpiar()">Quitar la selección</button>
            </div>
        </div>
    @endif

    @if ($paginado && $filas->hasPages())
        <div style="margin-top:20px;">{{ $filas->onEachSide(1)->links() }}</div>
    @endif
</div>
