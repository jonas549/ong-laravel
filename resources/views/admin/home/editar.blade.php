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
            <a class="btn btn-outline" href="{{ route('admin.home.vista-previa') }}" target="_blank" rel="noopener">Vista previa</a>
            <span class="helper" x-text="aviso" x-show="aviso" x-cloak></span>
        </div>

        <p class="helper" style="margin:14px 0 0;">
            Lo que escribas se guarda solo como borrador mientras trabajas. El sitio no cambia hasta que pulses «Publicar».
            Un campo vacío vuelve al texto original del diseño.
        </p>
    </div>
</form>
@endif

{{-- ------------------------------------------------------------ historial --}}

@if ($versiones->isNotEmpty())
    <section class="card" style="padding:22px 24px;margin-top:20px;">
        <h2 style="font-size:16px;font-weight:700;margin:0 0 6px;">Historial</h2>
        <p class="helper" style="margin:0 0 16px;">
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
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('admin.home.restaurar', [$seccion->clave, $v]) }}"
                                      x-data x-on:submit="if (! confirm('Se publicará esta versión. La actual quedará guardada en el historial.')) $event.preventDefault()">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">Restaurar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
