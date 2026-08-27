@props(['filas' => 5, 'columnas' => 4])

{{--
    Esqueleto de tabla, para cuando hay que enseñar algo mientras se carga.

    El panel se pinta en el servidor, así que la mayoría de las pantallas llegan
    ya hechas y esto no hace falta. Está para lo que sí tarda y sí es asíncrono:
    una exportación que se está preparando, o un listado que se recarga solo al
    filtrar.

    `aria-hidden` porque no es contenido: para un lector de pantalla el aviso
    útil es el `aria-busy` del contenedor, no doce cajas grises.
--}}
<div class="esqueleto" aria-hidden="true" {{ $attributes }}>
    @for ($f = 0; $f < $filas; $f++)
        <div class="esqueleto-fila">
            @for ($c = 0; $c < $columnas; $c++)
                <span class="esqueleto-celda" style="width:{{ [70, 45, 60, 30, 50][$c % 5] }}%;"></span>
            @endfor
        </div>
    @endfor
</div>
