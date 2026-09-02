@props(['errores' => []])

{{--
    El resumen de lo que falta, arriba del formulario.

    Esto es lo que no había, y por lo que llegó «tira error aunque los campos
    estén completos»: la validación acertaba, el aviso estaba, pero estaba
    dentro del formulario, en letra pequeña rosa, a novecientos píxeles del
    sitio donde el navegador deja la página al volver del POST. No se veía sin
    ir a buscarlo, y para buscarlo hay que saber ya que existe.

    Lo pinta Alpine y no Blade a propósito: la misma caja sirve para los errores
    que devuelve el servidor —que llegan sembrados en `errores` desde PHP— y
    para los que encuentra la revisión previa antes de enviar. Un solo sitio que
    mantener, y el usuario ve siempre lo mismo venga de donde venga el fallo.

    Cada renglón es un botón que salta a su campo, cambiando de paso si el campo
    está en otro. Es lo que sustituye a bajar leyendo.

    Necesita, en el ámbito de Alpine, lo que trae `guiaDeErrores`
    (resources/js/formularios.js): `errores`, `tituloDeErrores()` e
    `irAlCampo()`.
--}}

<div data-resumen-errores role="alert" tabindex="-1" class="resumen-errores"
     x-show="errores.length > 0" x-cloak
     {{ $attributes }}>

    <div class="resumen-errores-cabecera">
        <span class="resumen-errores-icono" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5"></path><path d="M12 17h.01"></path><circle cx="12" cy="12" r="10"></circle></svg>
        </span>
        <strong x-text="tituloDeErrores()"></strong>
    </div>

    <p class="resumen-errores-pie">Toca cualquiera de la lista para ir directo al campo.</p>

    <ul class="resumen-errores-lista">
        <template x-for="e in errores" x-bind:key="e.campo">
            <li>
                <button type="button" class="resumen-errores-salto" x-on:click="irAlCampo(e.campo)">
                    <span x-text="e.etiqueta"></span>
                    <span class="resumen-errores-motivo" x-show="e.mensaje" x-cloak x-text="e.mensaje"></span>
                </button>
            </li>
        </template>
    </ul>
</div>

@if (count($errores) > 0)
    {{--
        Sin JavaScript no hay ni resumen ni saltos, pero el aviso no puede
        desaparecer: se pinta la misma lista en plano. Es lo que había antes de
        todo esto, sólo que ahora también dice cuáles son.
    --}}
    <noscript>
        <div class="alert alert-error" style="margin-bottom:20px;">
            <strong>{{ count($errores) === 1 ? 'Falta 1 campo por completar' : 'Faltan '.count($errores).' campos por completar' }}:</strong>
            {{ collect($errores)->pluck('etiqueta')->implode(' · ') }}
        </div>
    </noscript>
@endif
