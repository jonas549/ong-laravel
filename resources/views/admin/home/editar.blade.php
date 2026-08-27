@extends('layouts.admin')
@section('title', $meta['titulo'].' · Home')

{{--
    Editor de una sección. Un formulario por sección, no uno gigante: son doce
    secciones y más de sesenta campos entre todas.

    Lo que se escribe aquí no toca el sitio hasta pulsar «Publicar». El
    autoguardado escribe en una columna aparte, así que se puede cerrar el
    navegador a media frase sin perder nada y sin haber cambiado el home.
--}}

@section('content')

<div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px;">
    <div style="flex:1;min-width:260px;">
        <a class="textlink" style="font-size:13px;" href="{{ route('admin.home.index') }}">← Todas las secciones</a>
        <h1 style="font-size:22px;font-weight:800;margin:6px 0 4px;">{{ $meta['titulo'] }}</h1>
        <p class="helper" style="margin:0;">{{ $meta['resumen'] }}</p>
    </div>
    <a class="btn btn-outline btn-sm" href="{{ route('admin.home.vista-previa') }}" target="_blank" rel="noopener">Vista previa</a>
</div>

@if ($seccion->tieneBorrador())
    <div class="alert alert-info" style="margin-bottom:16px;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:240px;">
            <strong style="display:block;margin-bottom:4px;">Hay un borrador sin publicar.</strong>
            <span style="font-size:13px;">
                Guardado {{ $seccion->borrador_at?->diffForHumans() }}. Lo que se ve abajo es el borrador;
                el sitio sigue mostrando lo último que se publicó.
            </span>
        </div>
        <form method="POST" action="{{ route('admin.home.borrador.descartar', $seccion->clave) }}">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-ghost btn-sm">Descartar el borrador</button>
        </form>
    </div>
@endif

@if (! empty($meta['crud']))
    <div class="alert alert-ok" style="margin-bottom:16px;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:240px;font-size:13.5px;">
            {{ $meta['crud']['nota'] ?? 'El contenido de esta sección se administra en su propia pantalla.' }}
        </div>
        <a class="btn btn-outline btn-sm" href="{{ route($meta['crud']['ruta'], $meta['crud']['parametros'] ?? []) }}">
            {{ $meta['crud']['texto'] }}
        </a>
    </div>
@endif

{{-- ------------------------------------------------------------ historial --}}

@if ($versiones->isNotEmpty())
    {{--
        Va aquí arriba y plegado, no al final de la página.

        Estaba debajo del botón de publicar, donde sólo lo encuentra quien baja
        del todo —y quien busca el historial suele estar buscando cómo deshacer
        algo, que es justo cuando no apetece explorar—. Plegado no estorba, y el
        número de versiones se ve sin abrirlo.
    --}}
    <section class="card" style="padding:0;margin-bottom:16px;overflow:hidden;"
             x-data="{ abierto: false, confirmando: null, viendo: null }">

        <button type="button" x-on:click="abierto = ! abierto"
                style="width:100%;display:flex;align-items:center;gap:10px;padding:14px 22px;background:none;border:0;cursor:pointer;text-align:left;">
            <span style="font-size:15px;font-weight:700;color:var(--ink);flex:1;">
                Historial · {{ $versiones->count() }} {{ Str::plural('versión', $versiones->count()) }}
            </span>
            <span class="helper" x-text="abierto ? 'Ocultar' : 'Ver'"></span>
        </button>

        <div x-show="abierto" x-cloak style="padding:0 22px 20px;">
            <p class="helper" style="margin:0 0 14px;">
                Cada vez que se publica, lo anterior queda guardado aquí. Restaurar no borra nada:
                publica una copia de esa versión y deja la actual en el historial.
            </p>

            <div class="tabla-wrap" style="border:0;">
                <table class="tabla">
                    <thead><tr><th>Cuándo</th><th>Quién</th><th>Qué pasó</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($versiones as $v)
                            <tr>
                                <td style="white-space:nowrap;">{{ $v->created_at->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</td>
                                <td>{{ $v->quien() }}</td>
                                <td class="helper">{{ $v->nota }}</td>
                                <td style="text-align:right;white-space:nowrap;">
                                    {{-- Ver qué decía antes de decidir. Sin esto, la única forma
                                         de saberlo era restaurarla y mirar. --}}
                                    <button type="button" class="btn btn-ghost btn-sm"
                                            x-on:click="viendo = viendo === {{ $v->id }} ? null : {{ $v->id }}"
                                            x-text="viendo === {{ $v->id }} ? 'Ocultar' : 'Ver'"></button>

                                    <template x-if="confirmando !== {{ $v->id }}">
                                        <button type="button" class="btn btn-outline btn-sm"
                                                x-on:click="confirmando = {{ $v->id }}">Restaurar</button>
                                    </template>

                                    {{-- La confirmación va en la propia fila y no en un confirm()
                                         del navegador: aquel desentonaba con el resto del panel y
                                         además no dice de qué versión está hablando. --}}
                                    <template x-if="confirmando === {{ $v->id }}">
                                        <span style="display:inline-flex;align-items:center;gap:6px;">
                                            <span class="helper">¿Publicar esta versión?</span>
                                            <form method="POST" action="{{ route('admin.home.restaurar', [$seccion->clave, $v]) }}" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm">Sí, restaurar</button>
                                            </form>
                                            <button type="button" class="btn btn-ghost btn-sm" x-on:click="confirmando = null">Cancelar</button>
                                        </span>
                                    </template>
                                </td>
                            </tr>

                            <tr x-show="viendo === {{ $v->id }}" x-cloak>
                                <td colspan="4" style="background:#fcfcfc;">
                                    <div style="display:flex;flex-direction:column;gap:10px;padding:6px 0 10px;">
                                        @foreach ($campos as $clave => $campo)
                                            @php
                                                $antes = $v->contenido[$clave] ?? null;
                                                $ahora = $seccion->contenido[$clave] ?? null;
                                            @endphp
                                            <div>
                                                <span class="helper" style="display:block;">
                                                    {{ $campo['label'] }}
                                                    @if ($antes !== $ahora)
                                                        <strong style="color:var(--naranjo);">· distinto de lo publicado</strong>
                                                    @endif
                                                </span>
                                                <span style="font-size:13.5px;color:var(--gris-700);overflow-wrap:anywhere;">
                                                    @if (blank($antes))
                                                        <em class="helper">(vacío — se usaba el texto original del diseño)</em>
                                                    @elseif ($campo['tipo'] === 'rico')
                                                        {{ app(\App\Services\SanitizadorHtml::class)->texto($antes, 300) }}
                                                    @else
                                                        {{ Str::limit((string) $antes, 300) }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endif

@if (! $campos)
    <div class="card" style="padding:26px;">
        <p class="helper" style="margin:0;">Esta sección no tiene textos propios: todo lo que muestra sale de su propia pantalla.</p>
    </div>
@else
<form method="POST" action="{{ route('admin.home.actualizar', $seccion->clave) }}"
      x-data="editorSeccion({{ Js::from(route('admin.home.borrador', $seccion->clave)) }})"
      x-on:submit="sincronizarRicos()">
    @csrf @method('PUT')

    <div class="card" style="padding:26px;">
        @if (! empty($meta['ayuda']))
            <p class="helper" style="margin:0 0 20px;">{{ $meta['ayuda'] }}</p>
        @endif

        <div style="display:flex;flex-direction:column;gap:20px;">
            @foreach ($campos as $clave => $campo)
                @php $valor = old($clave, $seccion->valor($clave, borrador: true)); @endphp

                <div>
                    <label class="lbl" for="c-{{ $clave }}">{{ $campo['label'] }}</label>

                    @if ($campo['tipo'] === 'rico')
                        @include('partials.admin.editor-rico', ['clave' => $clave, 'valor' => $valor])

                    @elseif ($campo['tipo'] === 'parrafo')
                        <textarea class="fld" id="c-{{ $clave }}" name="{{ $clave }}" rows="3" x-on:input="tocado()">{{ $valor }}</textarea>

                    @elseif ($campo['tipo'] === 'numero')
                        <input class="fld" id="c-{{ $clave }}" name="{{ $clave }}" type="number"
                               min="{{ $campo['min'] ?? 0 }}" max="{{ $campo['max'] ?? 9999 }}"
                               value="{{ $valor }}" x-on:input="tocado()">

                    @elseif ($campo['tipo'] === 'opciones')
                        <select class="fld" id="c-{{ $clave }}" name="{{ $clave }}" x-on:change="tocado()">
                            @foreach ($campo['opciones'] as $op => $texto)
                                <option value="{{ $op }}" @selected($valor === $op)>{{ $texto }}</option>
                            @endforeach
                        </select>

                    @else
                        <input class="fld" id="c-{{ $clave }}" name="{{ $clave }}" type="text"
                               value="{{ $valor }}" x-on:input="tocado()">
                    @endif

                    @if ($campo['tipo'] === 'imagen' && $seccion->imagen($clave, borrador: true))
                        <img src="{{ asset($seccion->imagen($clave, borrador: true)) }}" alt=""
                             style="margin-top:10px;max-width:200px;height:auto;border-radius:10px;border:1px solid var(--linea);display:block;">
                    @endif

                    @if (! empty($campo['ayuda']))
                        <p class="helper" style="margin:6px 0 0;">{{ $campo['ayuda'] }}</p>
                    @endif

                    @error($clave)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>

        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:26px;padding-top:20px;border-top:1px solid var(--linea);">
            <button type="submit" class="btn btn-primary">Publicar</button>

            {{--
                Botón explícito además del autoguardado.

                La pantalla promete que lo escrito se guarda solo, y el
                autoguardado lo cumple. Pero es una promesa invisible: no había
                forma de forzarla ni de comprobar que había pasado, y cuando el
                autoguardado se rompió nadie lo notó hasta el testing en
                producción. Con el botón, quien no se fíe tiene dónde pulsar.
            --}}
            <button type="button" class="btn btn-outline" x-on:click="guardarAhora()">Guardar borrador</button>

            <a class="btn btn-ghost" href="{{ route('admin.home.vista-previa') }}" target="_blank" rel="noopener">Vista previa</a>
            <span class="helper" x-text="aviso" x-show="aviso" x-cloak></span>
        </div>

        <p class="helper" style="margin:14px 0 0;">
            Lo que escribas se guarda solo como borrador cada pocos segundos, y con «Guardar borrador»
            lo fuerzas cuando quieras. El sitio no cambia hasta que pulses «Publicar».
            Un campo vacío vuelve al texto original del diseño.
        </p>
    </div>
</form>
@endif

@endsection
