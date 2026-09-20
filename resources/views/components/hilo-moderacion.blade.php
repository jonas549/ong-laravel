@props([
    // La actividad cuyo hilo se pinta.
    'activity',
    /*
     * Desde qué lado se está mirando: 'ong' o 'organizacion'. No cambia lo que
     * se enseña —los dos ven lo mismo, que es justo la gracia— sino de qué lado
     * cae cada burbuja y cómo se firma la propia.
     */
    'lado' => 'ong',
    // Qué decir cuando todavía no se ha dicho nada.
    'vacio' => 'Todavía no hay mensajes.',
])

{{--
    El hilo de moderación entre la ONG y la organización.

    Nace de que el circuito era de una sola dirección: la ONG escribía sus
    observaciones en un campo, el organizador corregía, y ahí se acababa el
    canal. El organizador no tenía dónde contar qué había cambiado, y la ONG
    no tenía dónde leerlo. Las dos partes trabajaban a ciegas sobre la misma
    actividad.

    Los mensajes salen de `activity_status_logs`, que ya guardaba el comentario
    de cada movimiento: no hizo falta tabla nueva, sólo dejar de tratar esa
    columna como «la nota del admin» y empezar a tratarla como «lo que dijo
    quien movió la actividad».

    Se pinta igual para los dos lados a propósito. Un hilo donde cada parte ve
    una versión distinta de la conversación es como no tener hilo.
--}}

@php
    $mensajes = $activity->hilo;
@endphp

<div class="hilo">
    @forelse ($mensajes as $m)
        @php $suyo = $m->lado() === $lado; @endphp

        <div @class(['hilo-mensaje', 'hilo-mensaje-propio' => $suyo, 'hilo-mensaje-sistema' => $m->lado() === 'sistema'])>
            <div class="hilo-firma">
                <strong>{{ $suyo ? 'Tú' : $m->firma() }}</strong>
                <span>{{ \App\Support\Fecha::relativa($m->created_at) }}</span>
            </div>

            <div class="hilo-texto">{{ $m->comentario }}</div>

            {{-- Qué le pasó a la actividad con ese mensaje. Sin esto, «lo
                 corregí» y «necesita ajustes» se leen igual de planos. --}}
            <div class="hilo-movimiento">
                {{ \App\Models\Activity::ESTADOS[$m->a_estado]['filtro'] ?? $m->a_estado }}
            </div>
        </div>
    @empty
        <p class="helper" style="margin:0;">{{ $vacio }}</p>
    @endforelse
</div>
