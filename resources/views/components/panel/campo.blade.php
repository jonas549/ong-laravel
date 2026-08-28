@props([
    'nombre',
    'label',
    // 'text' | 'textarea' | 'number' | 'select' | 'bool' | 'email' | 'url' | 'password'
    'tipo' => 'text',
    'valor' => null,
    'reglas' => '',
    'ayuda' => null,
    // Para 'select': ['clave' => 'Texto'] o una lista simple.
    'opciones' => [],
    'filas' => 4,
    'placeholder' => null,
])

{{--
    Un campo de formulario del panel, con su etiqueta, su ayuda y su error.

    **La validación en tiempo real sale de las MISMAS reglas del servidor.** Se
    le pasa la cadena que ya usa el `validate()` —`required|string|max:255`— y
    de ahí salen los atributos HTML y las pistas del aviso. No hay una segunda
    lista de reglas que mantener de acuerdo con la primera, que es la forma
    habitual de que el formulario acabe diciendo una cosa y el servidor otra.

    Lo que no se puede traducir —`unique`, `exists`, una regla propia— no se
    finge: se queda sin aviso previo y lo dice el servidor al enviar.

    El aviso aparece al salir del campo, no en la primera letra: marcar en rojo
    un campo vacío que aún se está escribiendo es regañar antes de tiempo.
--}}

@php
    use App\Support\ReglasDeCampo;

    $id = 'c-'.$nombre;
    $attrs = ReglasDeCampo::atributos($reglas);
    $pistas = ReglasDeCampo::paraElNavegador($reglas, $label);
    $actual = old($nombre, $valor);
    $error = $errors->first($nombre);

    // El `type` que salga de las reglas (email, url) gana al genérico 'text'.
    $tipoHtml = $attrs['type'] ?? ($tipo === 'text' ? 'text' : $tipo);
    unset($attrs['type']);
@endphp

<div class="campo" x-data="campoValidado({{ Js::from($pistas) }})">
    @if ($tipo === 'bool')
        <label class="campo-bool" for="{{ $id }}">
            {{-- El hidden va delante: una casilla desmarcada no se envía, y sin
                 esto no había forma de distinguir «desmarcada» de «no vino». --}}
            <input type="hidden" name="{{ $nombre }}" value="0">
            <input type="checkbox" id="{{ $id }}" name="{{ $nombre }}" value="1" @checked($actual)>
            <span>{{ $label }}</span>
        </label>
    @else
        {{-- Clase propia y no `.lbl`: aquella es flex en columna —la usan las
             etiquetas que envuelven a su input— y el asterisco de obligatorio
             se iba a su propia linea. --}}
        <label class="campo-etiqueta" for="{{ $id }}">
            {{ $label }}
            @if ($attrs['required'] ?? false)
                <span class="campo-obligatorio" aria-hidden="true">*</span>
                <span class="visualmente-oculto">(obligatorio)</span>
            @elseif (isset($pistas['requeridoSi']))
                {{-- Con `required_if` el asterisco va y viene con el campo del
                     que depende, igual que la regla del servidor. --}}
                <span x-show="obligatorio" x-cloak>
                    <span class="campo-obligatorio" aria-hidden="true">*</span>
                    <span class="visualmente-oculto">(obligatorio)</span>
                </span>
            @endif
        </label>

        @if ($tipo === 'textarea')
            <textarea class="fld" id="{{ $id }}" name="{{ $nombre }}" rows="{{ $filas }}"
                      x-ref="entrada" x-on:blur="revisar()" x-on:input="alEscribir()"
                      :aria-invalid="aviso ? 'true' : 'false'"
                      @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                      {{ $attributes->merge($attrs) }}>{{ $actual }}</textarea>

        @elseif ($tipo === 'select')
            <select class="fld" id="{{ $id }}" name="{{ $nombre }}"
                    x-ref="entrada" x-on:change="revisar()"
                    {{ $attributes->merge($attrs) }}>
                <option value="">—</option>
                @foreach ($opciones as $clave => $texto)
                    @php $v = is_int($clave) ? $texto : $clave; @endphp
                    <option value="{{ $v }}" @selected((string) $actual === (string) $v)>{{ $texto }}</option>
                @endforeach
            </select>

        @else
            <input class="fld" id="{{ $id }}" name="{{ $nombre }}" type="{{ $tipoHtml }}"
                   value="{{ $actual }}"
                   x-ref="entrada" x-on:blur="revisar()" x-on:input="alEscribir()"
                   :aria-invalid="aviso ? 'true' : 'false'"
                   @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                   {{ $attributes->merge($attrs) }}>
        @endif
    @endif

    {{-- El contador sólo cuando queda poco: verlo desde el primer carácter es
         ruido, y verlo cuando faltan diez es un aviso útil. --}}
    @if (! empty($pistas['max']) && ! $pistas['numero'] && $tipo !== 'select' && $tipo !== 'bool')
        <span class="campo-contador" x-show="cerca()" x-cloak x-text="restantes() + ' caracteres'"></span>
    @endif

    @if ($ayuda)
        <p class="helper campo-ayuda">{{ $ayuda }}</p>
    @endif

    {{-- El del servidor manda: si viene, se enseña ése y no el del navegador. --}}
    @if ($error)
        <p class="field-error">{{ $error }}</p>
    @else
        <p class="field-error" x-show="aviso" x-cloak x-text="aviso"></p>
    @endif
</div>
