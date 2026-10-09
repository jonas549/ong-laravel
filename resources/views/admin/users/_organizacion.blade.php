{{--
    La organización de un organizador (decisión del 02/10 tras el punto 1 del
    30/09): desde el panel ya no se crea un organizador sin ella.

    Va dentro de un formulario con `x-data="organizacionDeUsuario(...)"`. Sólo
    cuenta con el rol «Organizador»: con «Administración» los campos van
    desactivados, que es lo que hace que no viajen —esconderlos no basta—.

    Varias cuentas por organización: el administrador puede asignar la cuenta
    a cualquier organización del listado, tenga ya cuenta o no, y esté como
    esté el interruptor de Configuración → General. El interruptor regula que
    la gente se sume sola; lo que hace el administrador lo decide él. Si ya
    tenía cuenta, ésta se suma y se avisa a su principal.
--}}
<div data-campo-organizacion x-show="rol === 'organizer'" x-cloak style="position:relative;">
    <label class="helper" for="u-org" style="display:block;margin-bottom:6px;font-weight:600;">Organización</label>
    <input class="fld @error('org_nombre') is-invalid @enderror" type="text" id="u-org" name="org_nombre"
           x-bind:disabled="rol !== 'organizer'" x-bind:value="buscarOrg"
           placeholder="Busca en el listado o escribe una nueva" autocomplete="off"
           x-on:input="escribirOrg($event.target.value)"
           x-on:focus="if (sugerencias.length) sugerenciasAbiertas = true"
           x-on:blur="setTimeout(() => sugerenciasAbiertas = false, 160)"
           x-on:keydown.escape.prevent="sugerenciasAbiertas = false">
    <input type="hidden" name="org_id" x-bind:value="orgElegida?.id ?? ''" x-bind:disabled="rol !== 'organizer'">

    <ul class="org-sugerencias" x-show="sugerenciasAbiertas" x-cloak role="listbox">
        <template x-for="o in sugerencias" x-bind:key="o.id">
            <li>
                <button type="button" class="org-sugerencia" x-on:click="elegirOrg(o)">
                    <span class="org-sugerencia-nombre" x-text="o.nombre"></span>
                    <span class="org-sugerencia-estado"
                          x-text="o.libre ? 'Libre en el listado' : 'Ya tiene cuenta'"></span>
                </button>
            </li>
        </template>
    </ul>

    <span class="helper" x-show="reclamando && ! sumandose" x-cloak style="color:var(--naranjo-600);" data-org-enlazara>
        Se le asignará esta organización del listado, como su cuenta principal.
    </span>
    <span class="helper" x-show="sumandose" x-cloak style="color:var(--naranjo-600);" data-org-sumara>
        Esta organización ya tiene cuenta: ésta se sumará a ella y se avisará a su cuenta principal.
    </span>
    <span class="helper" x-show="! reclamando">
        Elige una del listado o escribe el nombre de una nueva, que se creará con esta cuenta.
    </span>
    @error('org_nombre') <span class="field-error">{{ $message }}</span> @enderror
</div>
