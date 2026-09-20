@props([
    // La etiqueta del campo.
    'label' => 'Nombre de la organización *',
    // El texto de ayuda de debajo, o null para no poner ninguno.
    'ayuda' => null,
    // Lo que se escribe en el campo al pintar la página.
    'valor' => '',
    // `required` del navegador. El wizard no lo usa —su guía de errores cubre
    // los cinco pasos y un `required` dentro de un paso oculto hace que Chrome
    // corte el envío sin decir nada— y el registro sí, que es una sola página.
    'requerido' => false,
])

{{--
    El campo «nombre de la organización», con sugerencias.

    **Lo usan las dos pantallas donde nace una organización:** el paso 3 del
    wizard de publicar actividad y la de crear cuenta de organizador. Está
    aquí, y no copiado en cada una, porque ya pasó lo contrario: el buscador se
    hizo en el wizard y la pantalla de registro se quedó con un campo de texto
    normal durante una tanda entera sin que nadie lo notara (C1).

    **Al añadir un sitio nuevo que cree organizaciones**, monta este componente
    y el mixin `buscadorOrganizaciones` de `resources/js/organizaciones.js`. No
    copies el comportamiento.

    Necesita, en el ámbito de Alpine: `buscarOrg`, `sugerencias`, `buscando`,
    `sugerenciasAbiertas`, `orgElegida`, `orgTomada`, `reclamando`,
    `escribirOrg()`, `elegirOrg()` y `soltarOrg()`.

    La lista se cierra con Escape y al salir del campo, con un respiro para que
    el clic en una sugerencia llegue antes que el `blur` — si no, se cierra
    justo antes de registrar el clic y no se puede elegir nada con el ratón.
--}}

<label class="lbl" style="position:relative;" data-campo="org_nombre" data-obligatorio
       data-etiqueta="Nombre de la organización">{{ $label }}
    <input class="fld @error('org_nombre') is-invalid @enderror" name="org_nombre"
           value="{{ $valor }}"
           placeholder="Ej. Fundación Junto al Barrio"
           autocomplete="off" role="combobox" aria-autocomplete="list"
           @if ($requerido) required @endif
           x-bind:aria-expanded="sugerenciasAbiertas"
           x-on:input="escribirOrg($event.target.value)"
           x-on:focus="if (sugerencias.length) sugerenciasAbiertas = true"
           x-on:blur="setTimeout(() => sugerenciasAbiertas = false, 160)"
           x-on:keydown.escape.prevent="sugerenciasAbiertas = false">

    <span class="helper" x-show="buscando" x-cloak>Buscando…</span>

    <ul class="org-sugerencias" x-show="sugerenciasAbiertas" x-cloak role="listbox">
        <template x-for="o in sugerencias" x-bind:key="o.id">
            <li>
                <button type="button" class="org-sugerencia" x-on:click="elegirOrg(o)">
                    <span class="org-sugerencia-nombre" x-text="o.nombre"></span>
                    <span class="org-sugerencia-estado"
                          x-text="o.libre ? 'En el listado' : 'Ya tiene cuenta'"
                          x-bind:class="o.libre ? '' : 'org-sugerencia-estado-tomada'"></span>
                </button>
            </li>
        </template>
    </ul>

    {{-- Reclamada: se dice cuál y se ofrece deshacerlo. --}}
    <span class="helper" x-show="reclamando" x-cloak style="color:var(--naranjo-600);">
        Encontramos tu organización en nuestro listado. No hace falta que vuelvas a cargar sus datos.
        <button type="button" class="textlink" style="background:none;border:0;padding:0;cursor:pointer;font:inherit;"
                x-on:click="soltarOrg()">No es ésta</button>
    </span>

    {{-- Y la otra cara: la organización ya tiene cuenta. --}}
    <span class="field-error" x-show="orgTomada" x-cloak>
        <span x-text="orgTomada?.nombre"></span> ya tiene una cuenta.
        <a class="textlink" href="{{ route('account.login') }}">Inicia sesión</a>
        o <a class="textlink" href="{{ route('password.request') }}">recupera la contraseña</a>
        para publicar con ella.
    </span>

    {{-- El id viaja aparte del nombre: el servidor no se fía del nombre para
         decidir a qué organización se suma. --}}
    <input type="hidden" name="org_id" x-bind:value="orgElegida?.id ?? ''">

    @if ($ayuda)
        <span class="helper" x-show="! reclamando" x-cloak>{{ $ayuda }}</span>
    @endif

    @error('org_nombre') <span class="field-error">{{ $message }}</span> @enderror
    @error('org_id') <span class="field-error">{{ $message }}</span> @enderror
</label>
