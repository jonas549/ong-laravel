@props([
    // Lo que va en cada campo, ya en «dd / mm / aaaa».
    'inicio' => '',
    'termino' => '',
    // El rótulo del campo de inicio de cada formulario («Fecha *» en el wizard,
    // «Fecha de inicio *» en mi-cuenta, como en sus HTML fuente).
    'etiqueta' => 'Fecha *',
])

@php use App\Support\CamposDeActividad; @endphp

{{--
    Las fechas de una actividad, iguales en el wizard y en el editor de
    mi-cuenta (tanda del 05/10, puntos 4 y 5). Un solo bloque para los dos y no
    dos copias: «que los dos formularios se vean y se porten igual».

    Lo que lleva:
    - La fecha de inicio, con el aviso de «ya pasó» que no corta el envío
      (`data-hoy`, ver `campoFecha`).
    - «La actividad dura varios días», que enseña la fecha de término. Sin
      marcar, el campo no se ve y va deshabilitado, así que ni viaja.
    - En el editor, si la actividad ya pasó (`fechaBloqueada`), las fechas
      salen bloqueadas y se dice por qué. El servidor tampoco las cambia.

    Necesita en el componente de Alpine que lo rodea: `sinFecha`, `varios`,
    `fechaBloqueada` y `repasar()`. Las horas de cada formulario van en el slot,
    en la misma fila que la fecha de inicio.

    Campos de texto y no input[type=date]: los nativos no dejan pegar, que era
    la decisión del bloque K. Ver `campoFecha` en resources/js/formularios.js.
--}}
<div class="grid-2" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;">
    <label class="lbl" data-campo="fecha_inicio" data-obligatorio
           data-etiqueta="{{ CamposDeActividad::etiqueta('fecha_inicio') }}"
           x-data="campoFecha()">{{ $etiqueta }}
        <span class="campo-selector">
            {{-- Sin maxlength: cortaría lo que se pegue antes de poder
                 ordenarlo. Al teclear ya lo acota la máscara. --}}
            <input class="fld @error('fecha_inicio') is-invalid @enderror" name="fecha_inicio"
                   x-ref="fecha" inputmode="numeric" autocomplete="off"
                   placeholder="dd / mm / aaaa"
                   data-hoy="{{ now(\App\Support\Fecha::zona())->toDateString() }}"
                   x-on:input="alEscribir($event)" x-on:blur="normalizar()"
                   x-bind:disabled="sinFecha || fechaBloqueada" value="{{ $inicio }}">

            {{-- El botón abre el desplegable; el input[type=date] está debajo,
                 transparente y sin recibir clics, sólo para que el calendario
                 del navegador salga anclado aquí. --}}
            <input type="date" class="campo-selector-nativo" x-ref="calendario"
                   tabindex="-1" aria-hidden="true" x-bind:disabled="sinFecha || fechaBloqueada"
                   x-on:change="desdeCalendario()">

            <button type="button" class="campo-selector-boton"
                    x-bind:disabled="sinFecha || fechaBloqueada"
                    x-on:click="sincronizarCalendario(); $refs.calendario.showPicker ? $refs.calendario.showPicker() : $refs.fecha.focus()"
                    aria-label="Elegir la fecha en un calendario">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="3"></rect><path d="M3 9.5h18M8 2.5v4M16 2.5v4"></path></svg>
            </button>
        </span>
        <x-fecha-calendario-movil desactivar="sinFecha || fechaBloqueada" />
        <span class="helper">Ej. 04 / 12 / 2026</span>
        <span class="helper aviso-fecha-pasada" x-show="pasada && ! sinFecha && ! fechaBloqueada" x-cloak role="status">
            Elegiste una fecha que ya pasó. Corrígela en caso de que te hayas equivocado.
        </span>
        @error('fecha_inicio') <span class="field-error">{{ $message }}</span> @enderror
    </label>

    {{ $slot }}
</div>

<div class="fechas-bloqueadas" x-show="fechaBloqueada" x-cloak role="note">
    Esta actividad ya se realizó, así que sus fechas no se pueden cambiar.
    Todo lo demás —textos, imagen, cupos, lugar— sí se puede editar.
</div>

<label class="casilla-varios-dias" x-show="! sinFecha">
    <input type="checkbox" name="varios_dias" value="1" x-model="varios"
           x-bind:disabled="sinFecha || fechaBloqueada"
           x-on:change="$nextTick(() => repasar())">
    <span>La actividad dura varios días</span>
</label>

{{-- El `x-show` va en una caja aparte: al mostrar, Alpine quita `display`
     del estilo en línea y se llevaría el `display:grid` de la rejilla. --}}
<div class="fechas-termino" x-show="varios && ! sinFecha" x-cloak>
<div class="grid-2" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;">
    <label class="lbl" data-campo="fecha_termino" data-obligatorio
           data-etiqueta="{{ CamposDeActividad::etiqueta('fecha_termino') }}"
           x-data="campoFecha()">Fecha de término *
        <span class="campo-selector">
            <input class="fld @error('fecha_termino') is-invalid @enderror" name="fecha_termino"
                   x-ref="fecha" inputmode="numeric" autocomplete="off"
                   placeholder="dd / mm / aaaa"
                   x-on:input="alEscribir($event)" x-on:blur="normalizar()"
                   x-bind:disabled="! varios || sinFecha || fechaBloqueada" value="{{ $termino }}">
            <input type="date" class="campo-selector-nativo" x-ref="calendario"
                   tabindex="-1" aria-hidden="true" x-bind:disabled="! varios || sinFecha || fechaBloqueada"
                   x-on:change="desdeCalendario()">
            <button type="button" class="campo-selector-boton"
                    x-bind:disabled="! varios || sinFecha || fechaBloqueada"
                    x-on:click="sincronizarCalendario(); $refs.calendario.showPicker ? $refs.calendario.showPicker() : $refs.fecha.focus()"
                    aria-label="Elegir la fecha de término en un calendario">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="3"></rect><path d="M3 9.5h18M8 2.5v4M16 2.5v4"></path></svg>
            </button>
        </span>
        <x-fecha-calendario-movil desactivar="! varios || sinFecha || fechaBloqueada" />
        <span class="helper">El último día de la actividad.</span>
        @error('fecha_termino') <span class="field-error">{{ $message }}</span> @enderror
    </label>
</div>
</div>
