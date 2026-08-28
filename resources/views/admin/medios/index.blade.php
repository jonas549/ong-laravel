@extends('layouts.admin')
@section('title', 'Medios')

{{--
    La biblioteca.

    Cuadrícula y no tabla: lo que se busca aquí se reconoce mirando, no
    leyendo. En una lista de nombres de archivo no se distingue una foto de
    otra, y el 80% de estos nombres vienen de un banco de imágenes.

    El estado —búsqueda, filtros, página— sigue viviendo en la URL, como en el
    resto del panel, así que una búsqueda se puede compartir y dos pestañas no
    se pisan.
--}}

@section('content')

<div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:22px;">
    <p class="helper" style="margin:0;">
        {{ $total }} {{ \App\Support\Texto::plural('archivo', $total) }} ·
        {{ \App\Models\Media::pesoLegible($peso) }} en total
    </p>

    <button type="button" class="btn btn-primary btn-sm" x-on:click="$dispatch('abrir-subida')">
        Subir archivos
    </button>
</div>

{{-- ══ SUBIDA ══ --}}
<div x-data="subidorMedios({
        url: {{ Js::from(route('admin.medios.store')) }},
        maxBytes: {{ $limites['efectivo'] }},
        maxArchivos: {{ $limites['archivos'] }},
        maxTexto: {{ Js::from(\App\Models\Media::pesoLegible($limites['efectivo'])) }},
     })"
     x-on:abrir-subida.window="abierto = true"
     style="margin-bottom:26px;">

    <div x-show="abierto" x-cloak
         style="background:#fff;border:1px solid var(--linea);border-radius:18px;padding:22px;margin-bottom:18px;">

        <div x-ref="zona"
             x-on:dragover.prevent="encima = true"
             x-on:dragleave.prevent="encima = false"
             x-on:drop.prevent="encima = false; añadir($event.dataTransfer.files)"
             x-bind:style="encima
                ? 'border-color:var(--naranjo);background:var(--naranjo-100);'
                : 'border-color:#dcdee1;background:#fbfbfc;'"
             style="border:2px dashed;border-radius:16px;padding:30px;text-align:center;transition:background .15s ease,border-color .15s ease;">

            <p style="margin:0 0 6px;font-weight:700;color:var(--ink);">Arrastra aquí las imágenes</p>
            <p class="helper" style="margin:0 0 14px;">
                o <button type="button" class="enlace-boton" x-on:click="$refs.entrada.click()">búscalas en tu equipo</button>.
                Hasta <strong x-text="maxArchivos"></strong> a la vez, <strong x-text="maxTexto"></strong> cada una.
            </p>

            <input type="file" x-ref="entrada" multiple class="visualmente-oculto"
                   accept="{{ collect(\App\Services\Biblioteca::EXTENSIONES)->map(fn ($e) => '.'.$e)->implode(',') }}"
                   x-on:change="añadir($event.target.files); $event.target.value = ''">

            <label class="lbl" style="max-width:280px;margin:0 auto;text-align:left;">Carpeta (opcional)
                <input class="fld" type="text" x-model="carpeta" placeholder="Ej. edición 2026" maxlength="100">
                <span class="helper">Sirve para agrupar y filtrar después.</span>
            </label>
        </div>

        {{-- Lo que se va subiendo, uno por uno --}}
        <template x-if="cola.length">
            <ul style="list-style:none;margin:18px 0 0;padding:0;display:flex;flex-direction:column;gap:8px;">
                <template x-for="f in cola" x-bind:key="f.id">
                    <li style="display:flex;align-items:center;gap:12px;font-size:14px;padding:10px 12px;border:1px solid var(--linea);border-radius:12px;">
                        <span x-text="f.nombre" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        <span class="helper" x-text="f.detalle"></span>
                        <span x-bind:style="f.estado === 'mal'
                                ? 'color:var(--rosa);font-weight:700;'
                                : (f.estado === 'ok' ? 'color:#1c7c3f;font-weight:700;' : 'color:var(--gris);')"
                              x-text="f.estado === 'ok' ? 'subido' : (f.estado === 'mal' ? 'no se pudo' : 'subiendo…')"></span>
                    </li>
                </template>
            </ul>
        </template>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px;flex-wrap:wrap;">
            <span class="helper" x-text="resumen"></span>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-outline btn-sm" x-on:click="cerrar()">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm"
                        x-bind:disabled="subiendo || !pendientes.length"
                        x-on:click="subir()">
                    <span x-show="!subiendo">Subir</span>
                    <span x-show="subiendo" x-cloak>Subiendo…</span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══ FILTROS ══ --}}
<x-panel.filtros buscar="Buscar por nombre, título o texto alternativo…" :exportar="[]">
    <select class="fld" name="origen" x-on:change="enviar()" aria-label="Procedencia">
        <option value="">De donde sea</option>
        <option value="subido" @selected(request('origen') === 'subido')>Subidas al panel</option>
        <option value="codigo" @selected(request('origen') === 'codigo')>Del diseño original</option>
    </select>

    <select class="fld" name="tipo" x-on:change="enviar()" aria-label="Tipo de archivo">
        <option value="">Todos los formatos</option>
        @foreach (['jpg', 'png', 'webp', 'svg', 'gif', 'avif'] as $ext)
            <option value="{{ $ext }}" @selected(request('tipo') === $ext)>{{ strtoupper($ext) }}</option>
        @endforeach
    </select>

    @if ($carpetas->isNotEmpty())
        <select class="fld" name="carpeta" x-on:change="enviar()" aria-label="Carpeta">
            <option value="">Todas las carpetas</option>
            @foreach ($carpetas as $c)
                <option value="{{ $c }}" @selected(request('carpeta') === $c)>{{ $c }}</option>
            @endforeach
        </select>
    @endif

    <label class="lbl" style="font-size:12px;">Desde
        <input class="fld" type="date" name="desde" value="{{ request('desde') }}" x-on:change="enviar()">
    </label>

    <label class="lbl" style="font-size:12px;">Hasta
        <input class="fld" type="date" name="hasta" value="{{ request('hasta') }}" x-on:change="enviar()">
    </label>
</x-panel.filtros>

{{-- ══ CUADRÍCULA ══ --}}
@if ($medios->isEmpty())
    <div style="background:#fff;border:1px solid var(--linea);border-radius:18px;padding:44px;text-align:center;">
        <p style="margin:0 0 8px;font-weight:700;color:var(--ink);">Aquí no hay nada que enseñar.</p>
        <p class="helper" style="margin:0;">
            @if (request()->hasAny(['q', 'origen', 'tipo', 'carpeta', 'desde', 'hasta']))
                Ningún archivo coincide con el filtro.
                <a href="{{ route('admin.medios.index') }}">Quitar filtros</a>
            @else
                Sube la primera imagen con el botón de arriba.
            @endif
        </p>
    </div>
@else
    <div class="rejilla-medios">
        @foreach ($medios as $medio)
            <a href="{{ route('admin.medios.show', $medio) }}" class="ficha-medio">
                <span class="ficha-medio-imagen">
                    @if ($medio->existe)
                        <img loading="lazy" decoding="async" src="{{ $medio->url }}" alt="{{ $medio->alt ?: $medio->nombre }}">
                    @else
                        {{-- El registro está pero el archivo no: decirlo, no pintar un hueco. --}}
                        <span class="ficha-medio-perdida">Falta el archivo</span>
                    @endif
                </span>

                <span class="ficha-medio-pie">
                    <span class="ficha-medio-nombre">{{ $medio->etiqueta }}</span>
                    <span class="helper">
                        {{ $medio->dimensiones ?? '—' }} · {{ $medio->peso_legible }}
                        @if ($medio->es_del_codigo)
                            · <span title="Viene con el diseño y la repone el despliegue">del diseño</span>
                        @endif
                    </span>
                </span>
            </a>
        @endforeach
    </div>

    <div style="margin-top:24px;">
        {{ $medios->links('vendor.pagination.dps') }}
    </div>
@endif

@endsection
