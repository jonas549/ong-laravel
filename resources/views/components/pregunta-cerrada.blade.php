@props([
    // El estilo del rótulo de pregunta de cada formulario, para que se lea
    // igual que «¿Requiere inscripción previa?» justo encima.
    'estilo' => '',
    'clase' => '',
])

{{--
    Actividades cerradas (punto 6 del 05/10), igual en el wizard y en el editor
    de mi-cuenta.

    Sólo aparece cuando «¿Requiere inscripción previa?» es «No»: una actividad
    cerrada no recibe inscritos. Con «Sí» va deshabilitada y no viaja, y el
    servidor la descarta igualmente.

    Necesita en el componente de Alpine que lo rodea: `insc` y `cerrada`.
--}}
<div x-show="! insc" x-cloak data-pregunta-cerrada>
    <div class="{{ $clase }}" style="{{ $estilo }}">¿Es una actividad cerrada para un público específico?</div>
    <div style="display:flex;gap:8px;">
        <button type="button" x-bind:class="cerrada ? 'chip on' : 'chip'" x-bind:aria-pressed="cerrada ? 'true' : 'false'" x-on:click="cerrada = true">Sí</button>
        <button type="button" x-bind:class="cerrada ? 'chip' : 'chip on'" x-bind:aria-pressed="cerrada ? 'false' : 'true'" x-on:click="cerrada = false">No</button>
    </div>
    <span class="helper" style="display:block;margin-top:8px;">Elige Sí si solo quieres difundir tu actividad.</span>
    <input type="hidden" name="cerrada" x-bind:value="cerrada ? 1 : 0" x-bind:disabled="insc">
</div>
