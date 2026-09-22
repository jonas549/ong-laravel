@props([
    // Expresión de Alpine que lo desactiva, si la hay.
    'desactivar' => null,
])

{{--
    B8 de la sexta tanda: el calendario, evidente en el teléfono.

    Va dentro de un `campoFecha()` y debajo de su campo de texto, que se queda
    donde está —se puede seguir pegando una fecha, que era la decisión del
    bloque K—. En el teléfono el calendario era un icono de 17 px metido en el
    campo, y el cliente no lo encontraba: tocaba el campo, salía el teclado
    numérico y ahí se quedaba.

    **El botón ES un `input[type=date]`**, transparente y encima del texto, y no
    un botón que llame a `showPicker()`. Tocar un campo de fecha nativo abre el
    calendario del sistema en cualquier navegador de teléfono; `showPicker()`
    no lo soportan todos, y donde no lo soporta el icono de siempre acaba
    abriendo el teclado. Lo que se elige se escribe en el campo de texto por el
    mismo camino que el calendario de escritorio (`desdeCalendario()`).

    En escritorio no se pinta: ahí el icono del campo abre el desplegable del
    navegador sin problema.
--}}
<span class="fecha-calendario-movil">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16" rx="3"></rect><path d="M3 9.5h18M8 2.5v4M16 2.5v4"></path></svg>
    Elegir en el calendario
    <input type="date" class="fecha-calendario-movil-nativo" aria-label="Elegir la fecha en un calendario"
           @if ($desactivar) x-bind:disabled="{{ $desactivar }}" @endif
           x-on:focus="sincronizarCalendario(); $event.target.value = $refs.calendario.value"
           x-on:change="$refs.calendario.value = $event.target.value; desdeCalendario()">
</span>
