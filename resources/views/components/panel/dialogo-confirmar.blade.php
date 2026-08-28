{{--
    El único diálogo de confirmación de la página.

    Va en el layout del panel, una sola vez. Los botones `<x-panel.confirmar>`
    lo rellenan a través del almacén de Alpine `confirmacion`.

    Detalles que no son adorno:

    - **El foco entra al diálogo y vuelve a donde estaba** al cerrarse. Sin eso,
      cerrar con Escape deja el foco en el `<body>` y quien navega con teclado
      se queda sin sitio desde el que seguir.
    - **El foco arranca en «Cancelar»**, no en el botón que borra. Un Enter de
      más no puede ser lo que elimine el registro.
    - **`x-trap` no está disponible** (es un plugin de Alpine que el proyecto no
      carga), así que el ciclo del tabulador se hace a mano en `dialogoConfirmar`.
    - El formulario se envía de verdad, con su token y su `_method`: la acción
      destructiva sigue siendo un POST del servidor, no algo que decida el
      navegador.
    - **Cuando quien pregunta es una acción masiva de la tabla**, el envío no
      sale de aquí: el almacén guarda el botón que lo pidió y reenvía *su*
      formulario, que es el que lleva marcados los ids. Este de abajo se queda
      quieto.
--}}

<div x-data="dialogoConfirmar()" x-show="$store.confirmacion.abierto" x-cloak
     class="dialogo-fondo"
     x-on:keydown.escape.window="$store.confirmacion.cerrar()"
     x-on:keydown.tab="ciclarFoco($event)"
     x-on:click.self="$store.confirmacion.cerrar()">

    <div class="dialogo" role="dialog" aria-modal="true" tabindex="-1" :aria-label="$store.confirmacion.titulo" x-ref="caja">
        <h2 class="dialogo-titulo" x-text="$store.confirmacion.titulo"></h2>
        <p class="dialogo-texto" x-text="$store.confirmacion.texto"></p>

        <form method="POST" :action="$store.confirmacion.accion" class="dialogo-botones"
              x-on:submit="if ($store.confirmacion.objetivo) { $event.preventDefault(); $store.confirmacion.aceptar(); }">
            @csrf
            <input type="hidden" name="_method" :value="$store.confirmacion.metodo">

            <button type="button" class="btn btn-outline" x-ref="cancelar"
                    x-on:click="$store.confirmacion.cerrar()">Cancelar</button>

            <button type="submit" x-ref="aceptar" data-cargando="Un momento…"
                    :class="$store.confirmacion.peligro ? 'btn btn-danger' : 'btn btn-primary'"
                    x-text="$store.confirmacion.confirmar"></button>
        </form>
    </div>
</div>
