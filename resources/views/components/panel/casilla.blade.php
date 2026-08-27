@props(['id'])

{{-- La casilla de una fila. Su nombre es `ids[]`, que es lo que lee Listado::ids(). --}}
<td style="width:34px;">
    <input type="checkbox" name="ids[]" value="{{ $id }}" x-on:change="recontar()"
           aria-label="Seleccionar esta fila">
</td>
