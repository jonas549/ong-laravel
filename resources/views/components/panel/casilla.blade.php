@props([
    'id',
    // El formulario al que se engancha. Tiene que coincidir con el `idAcciones`
    // de la tabla: la casilla vive dentro de la tabla y el formulario está
    // fuera, porque HTML no admite formularios anidados.
    'form' => 'acciones-de-la-tabla',
])

{{-- La casilla de una fila. Su nombre es `ids[]`, que es lo que lee Listado::ids(). --}}
<td style="width:34px;">
    <input type="checkbox" name="ids[]" value="{{ $id }}" form="{{ $form }}" x-on:change="recontar()"
           aria-label="Seleccionar esta fila">
</td>
