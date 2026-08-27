@extends('layouts.admin')
@section('title', 'Páginas → Home')

{{--
    Las doce secciones del home, en el orden en que se pintan.

    El arrastre es HTML5 puro con Alpine, sin librería: el proyecto ya quitó
    support.js por descargar React y Babel de unpkg en cada carga, y arrastrar
    filas de una lista no justifica traer nada de fuera.

    Las dos primeras no se arrastran ni se apagan. No es una limitación
    caprichosa y por eso se explica en pantalla: «¿Cómo participar?» sube 96 px
    por encima del hero para que las tarjetas se monten sobre la imagen.
--}}

@section('content')

<div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
    <div style="flex:1;min-width:280px;">
        <h1 style="font-size:22px;font-weight:800;margin:0 0 6px;">Contenido del home</h1>
        <p class="helper" style="margin:0;">
            Cada sección se edita por separado. Arrastra para cambiar el orden en que se ven,
            y usa el interruptor para esconder una sección sin borrar lo que tiene escrito.
        </p>
    </div>
    <a class="btn btn-outline btn-sm" href="{{ route('admin.home.vista-previa') }}" target="_blank" rel="noopener">Ver el home con los borradores</a>
    <a class="btn btn-outline btn-sm" href="{{ route('home') }}" target="_blank" rel="noopener">Ver el sitio publicado</a>
</div>

<div class="card" style="padding:8px;"
     x-data="ordenSecciones({{ Js::from($secciones->pluck('clave')) }}, {{ Js::from(route('admin.home.orden')) }})">

    <p class="helper" x-show="guardando" x-cloak style="margin:8px 12px;">Guardando el orden…</p>
    <p class="helper" x-show="error" x-cloak style="margin:8px 12px;color:var(--rosa);" x-text="error"></p>

    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;">
        @foreach ($secciones as $s)
            @php $meta = \App\Support\CatalogoHome::seccion($s->clave); @endphp

            <li data-clave="{{ $s->clave }}"
                @if (! $s->esFija())
                    draggable="true"
                    x-on:dragstart="empezar($event, '{{ $s->clave }}')"
                    x-on:dragover.prevent="sobre($event, '{{ $s->clave }}')"
                    x-on:drop.prevent="soltar()"
                    x-on:dragend="terminar()"
                @endif
                {{--
                    El realce del arrastre va por clase y NO por :style. Alpine,
                    cuando el valor de :style es un string, reemplaza el atributo
                    entero en vez de fusionarlo: el style estático de esta misma
                    línea desaparecía y la fila se apilaba en vertical. Ya estaba
                    anotado en app.js por el mismo motivo, con los círculos del
                    wizard.
                --}}
                class="fila-seccion{{ $s->activo ? '' : ' fila-seccion-apagada' }}{{ $s->esFija() ? ' fila-seccion-fija' : '' }}"
                :class="arrastrando === '{{ $s->clave }}' ? 'arrastrando' : ''">

                <span aria-hidden="true" style="flex:none;width:20px;text-align:center;color:{{ $s->esFija() ? 'var(--linea)' : 'var(--gris)' }};font-size:15px;">
                    {{ $s->esFija() ? '🔒' : '⣿' }}
                </span>

                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;">
                        <a class="textlink" style="font-weight:700;font-size:15px;" href="{{ route('admin.home.editar', $s->clave) }}">{{ $meta['titulo'] }}</a>

                        @if (! $s->activo)
                            <span style="font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:999px;background:var(--gris-100);color:var(--gris);">No se ve</span>
                        @endif

                        @if ($s->tieneBorrador())
                            <span style="font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:999px;background:#fff8e6;color:#8a6a00;">Borrador sin publicar</span>
                        @endif

                        @if ($cambiados = $s->camposCambiados())
                            <span class="helper">{{ $cambiados }} {{ Str::plural('campo', $cambiados) }} {{ Str::plural('cambiado', $cambiados) }}</span>
                        @endif
                    </div>
                    <p class="helper" style="margin:3px 0 0;">
                        {{ $meta['resumen'] }}
                        @if ($s->esFija()) <strong>Va fija arriba: el diseño de la portada la da por hecha.</strong> @endif
                    </p>
                </div>

                <div style="flex:none;display:flex;align-items:center;gap:8px;">
                    @if (! $s->esFija())
                        <form method="POST" action="{{ route('admin.home.alternar', $s->clave) }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm">{{ $s->activo ? 'Esconder' : 'Mostrar' }}</button>
                        </form>
                    @endif
                    <a class="btn btn-outline btn-sm" href="{{ route('admin.home.editar', $s->clave) }}">Editar</a>
                </div>
            </li>
        @endforeach
    </ul>
</div>

<p class="helper" style="margin-top:18px;">
    Si una sección no tiene nada escrito, el home usa el texto original del diseño.
    Vaciar un campo en el editor es, justamente, volver a ese texto.
</p>
@endsection
