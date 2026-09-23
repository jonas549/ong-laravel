{{--
    Las respuestas de una evaluación, cada una bajo la pregunta que la originó
    (punto 8 del 23/09). Antes se veía «solidaridad» suelta, sin saber a qué
    contestaba. La usan el panel del admin y el del organizador.

    `$compacta` es para la tabla del admin: la misma lista, con menos aire.
    `$solo`, las claves que se quieren (la tabla lleva las notas en su propia
    columna, que se puede ordenar).
--}}
@php
    $compacta = $compacta ?? false;
    $respuestas = collect($evaluacion->respuestas())
        ->when(isset($solo), fn ($c) => $c->whereIn('clave', $solo));
@endphp

<dl @class(['eval-respuestas', 'eval-respuestas--compacta' => $compacta]) data-respuestas>
    @foreach ($respuestas as $r)
        <div class="eval-respuesta" data-respuesta="{{ $r['clave'] }}">
            <dt class="eval-pregunta">{{ $r['pregunta'] }}</dt>
            <dd class="eval-contestacion">{{ $r['respuesta'] }}</dd>
        </div>
    @endforeach
</dl>
