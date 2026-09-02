@php
    $puerta = \App\Support\PuertaDeAcceso::sugerida();
@endphp

{{--
    «Te has equivocado de puerta», con la puerta buena a un clic.

    El sitio tiene dos accesos y cada uno rechaza a la cuenta del otro. El
    rechazo era correcto y estaba bien explicado, pero se quedaba en texto: decía
    la dirección y no llevaba. El 2026-09-01 dejó a una persona del cliente
    parada delante del mensaje.

    Va en naranja y no en rosa a propósito. La contraseña era correcta y la
    cuenta existe: no ha fallado nada, sólo hay que ir al otro sitio. El rosa
    está debajo, en el campo, para lo que sí es un error.

    El correo viaja por la sesión, así que al llegar al otro acceso el campo sale
    relleno y el foco cae en la contraseña.
--}}

@if ($puerta)
    <div class="alert alert-info puerta-equivocada" role="alert" {{ $attributes }}>
        <span>{{ $puerta['texto'] }}</span>
        <a href="{{ $puerta['url'] }}" class="btn btn-primary btn-sm">{{ $puerta['boton'] }} →</a>
    </div>
@endif
