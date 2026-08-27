@props([
    'accion',
    'metodo' => 'DELETE',
    'titulo' => '¿Seguro?',
    'texto' => 'Esta acción no se puede deshacer.',
    'confirmar' => 'Sí, continuar',
    'peligro' => true,
    // Cómo se ve el botón que abre el diálogo.
    'boton' => 'Eliminar',
    'clase' => 'btn btn-danger btn-sm',
])

{{--
    El botón de una acción destructiva.

    No abre un `confirm()` del navegador: aquél no se puede escribir en
    castellano del proyecto, no dice de qué registro habla, y sale con la
    tipografía del sistema en medio de un panel que no se parece en nada. Ya se
    quitó del historial del home por lo mismo.

    **No pinta un diálogo por fila.** Un listado de cien registros tendría cien
    diálogos ocultos en el DOM. El botón sólo rellena un almacén de Alpine y el
    único diálogo de la página —`<x-panel.dialogo-confirmar>`, que va en el
    layout— se pinta con esos datos.
--}}

<button type="button" class="{{ $clase }}"
        x-data
        x-on:click="$store.confirmacion.abrir({{ Js::from([
            'accion' => $accion,
            'metodo' => strtoupper($metodo),
            'titulo' => $titulo,
            'texto' => $texto,
            'confirmar' => $confirmar,
            'peligro' => (bool) $peligro,
        ]) }})">
    {{ $boton !== '' ? $boton : $slot }}
</button>
