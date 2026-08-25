{{--
    Un nodo del menú, con lo que cuelgue de él.

    Se llama a sí mismo, así que sirve igual para un enlace suelto, para una
    sección con hijos y para el Home, que tiene un nivel más.

    Variables: $nodo, $nivel (0 arriba del todo).
--}}
@php
    $nivel = $nivel ?? 0;
    $activo = \App\Support\MenuPanel::activo($nodo);
    $url = \App\Support\MenuPanel::url($nodo);
    $tieneHijos = $nodo['hijos'] !== [];
    // La sangría crece con el nivel; el resto del espaciado lo pone el CSS.
    $sangria = 22 + $nivel * 14;
@endphp

@if (! $tieneHijos)
    <a href="{{ $url ?? '#' }}"
       class="nav-hoja {{ $activo ? 'on' : '' }}"
       style="padding-left:{{ $sangria }}px;"
       @if ($activo) aria-current="page" @endif
       x-on:click="nav = false">{{ $nodo['texto'] }}</a>
@else
    @php
        // La clave del estado abierto/cerrado. Va por texto y no por índice
        // para que reordenar el árbol no le cambie el sitio a nadie.
        $clave = \Illuminate\Support\Str::slug($nodo['texto']);
    @endphp

    <div x-data="seccionMenu('{{ $clave }}', {{ $activo ? 'true' : 'false' }})" class="nav-seccion">
        @if ($url)
            {{-- Con destino propio: el texto navega y la flecha despliega. --}}
            <div class="nav-rama {{ $activo ? 'on' : '' }}" style="padding-left:{{ $sangria }}px;">
                <a href="{{ $url }}" class="nav-rama-texto" x-on:click="nav = false">{{ $nodo['texto'] }}</a>
                <button type="button" class="nav-flecha" x-on:click="alternar()"
                        x-bind:aria-expanded="abierta ? 'true' : 'false'"
                        aria-label="Desplegar {{ $nodo['texto'] }}">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                         x-bind:style="abierta ? 'transform:rotate(90deg)' : ''">
                        <path d="M9 6l6 6-6 6"></path>
                    </svg>
                </button>
            </div>
        @else
            {{-- Sin destino: sólo agrupa, así que toda la fila despliega. --}}
            <button type="button" class="nav-rama {{ $activo ? 'on' : '' }}"
                    style="padding-left:{{ $sangria }}px;"
                    x-on:click="alternar()"
                    x-bind:aria-expanded="abierta ? 'true' : 'false'">
                <span class="nav-rama-texto">{{ $nodo['texto'] }}</span>
                <span class="nav-flecha" aria-hidden="true">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                         x-bind:style="abierta ? 'transform:rotate(90deg)' : ''">
                        <path d="M9 6l6 6-6 6"></path>
                    </svg>
                </span>
            </button>
        @endif

        <div class="nav-hijos" x-show="abierta" x-cloak>
            @foreach ($nodo['hijos'] as $hijo)
                @include('partials.admin.nodo', ['nodo' => $hijo, 'nivel' => $nivel + 1])
            @endforeach
        </div>
    </div>
@endif
