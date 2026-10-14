{{-- La tarjeta de una actividad. El marcado vive en `<x-tarjeta-listado>`,
     que comparte con la de los voluntariados. --}}
@php
    $temas = $act->termsDe('tema');
    $caracs = $act->termsDe('caracteristica');
@endphp

<x-tarjeta-listado
    :href="route('activities.show', $act)"
    :imagen="$act->imagen_url"
    :titulo="$act->titulo"
    :etiqueta="$temas->first()?->nombre"
    :lugar="$act->lugar_corto"
    :fecha="$act->fecha_corta"
    :descripcion="(string) $act->descripcion"
    :etiquetas="$temas->concat($caracs)->take(3)->pluck('nombre')->all()"
    cta="Ver actividad" />
