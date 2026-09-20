@props([
    // El identificador del registro.
    'valor',
])

{{--
    La celda del identificador, primera columna de toda tabla del panel.

    Está como componente y no copiada en cada listado para que el día que el
    cliente pida verlo de otra forma —con prefijo, enlazado, copiable— se
    cambie en un sitio y no en las quince tablas.

    El `title` no es decoración: la columna va en gris y estrecha a propósito,
    y quien no sepa qué es ese número lo descubre al pasar por encima.
--}}

<td class="col-id" title="Identificador del registro">{{ $valor }}</td>
