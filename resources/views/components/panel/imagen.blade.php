@props([
    'name',
    'value' => null,
    'label' => 'Imagen',
    'ayuda' => null,
    'required' => false,
    // Alto de la vista previa. Un logo y una foto de portada no piden lo mismo.
    'alto' => 150,
])

@php
    $id = 'medio-'.Str::slug($name).'-'.Str::random(4);
    $actual = old($name, $value);
    $limites = app(\App\Services\Biblioteca::class)->limites();
@endphp

{{--
    Un campo de imagen que abre la biblioteca.

    Sustituye a escribir la ruta a mano, que es lo que había y lo que obligaba a
    que el archivo estuviera ya en el servidor.

    **Lo que se envía sigue siendo la misma cadena de siempre**: la ruta
    relativa a `public/`, en un campo oculto con el mismo `name`. Por eso este
    componente entra en un formulario que ya existía sin tocar su controlador ni
    su validación.

    El diálogo carga la biblioteca por `fetch`, no navegando: esto vive dentro
    de un formulario a medio rellenar y una recarga se llevaría lo escrito.
--}}

<div class="campo-medio"
     x-data="selectorMedio({
        urlBuscar: {{ Js::from(route('admin.medios.buscar')) }},
        urlSubir: {{ Js::from(route('admin.medios.store')) }},
        valor: {{ Js::from($actual ? asset($actual) : '') }},
        maxBytes: {{ $limites['efectivo'] }},
        maxTexto: {{ Js::from(\App\Models\Media::pesoLegible($limites['efectivo'])) }},
     })"
     x-on:keydown.escape.window="cerrar()">

    {{-- La ruta es lo único que viaja al servidor. --}}
    <input type="hidden" name="{{ $name }}" x-bind:value="ruta" value="{{ $actual }}">

    <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:8px;">
        {{ $label }}@if ($required) <span aria-hidden="true">*</span>@endif
    </div>

    <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
        <span class="campo-medio-vista" style="height:{{ $alto }}px;">
            <template x-if="vistaPrevia">
                <img x-bind:src="vistaPrevia" alt="">
            </template>
            <template x-if="!vistaPrevia">
                <span class="helper" style="font-size:12px;">Sin imagen</span>
            </template>
        </span>

        <div style="display:flex;flex-direction:column;gap:9px;">
            <div style="display:flex;gap:9px;flex-wrap:wrap;">
                <button type="button" class="btn btn-outline btn-sm" x-on:click="abrir()">
                    <span x-show="!ruta">Elegir imagen</span>
                    <span x-show="ruta" x-cloak>Cambiar</span>
                </button>

                <button type="button" class="btn btn-outline btn-sm" x-show="ruta" x-cloak x-on:click="quitar(); $dispatch('medio-cambiado')">
                    Quitar
                </button>
            </div>

            @if ($ayuda)
                <span class="helper">{{ $ayuda }}</span>
            @endif

            <code class="helper" style="font-size:11.5px;word-break:break-all;" x-text="ruta || '—'"></code>
        </div>
    </div>

    {{-- ══ DIÁLOGO ══ --}}
    <div x-show="abierto" x-cloak
         style="position:fixed;inset:0;z-index:90;background:rgba(51,54,58,.45);backdrop-filter:blur(3px);display:grid;place-items:center;padding:20px;"
         x-on:click.self="cerrar()">

        <div role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-t"
             style="background:#fff;border-radius:22px;width:100%;max-width:900px;max-height:88vh;display:flex;flex-direction:column;box-sizing:border-box;box-shadow:0 40px 80px -40px rgba(0,0,0,.5);">

            <div style="padding:22px 24px 16px;border-bottom:1px solid var(--linea);">
                <h2 id="{{ $id }}-t" style="font-size:20px;font-weight:800;margin:0 0 14px;color:var(--ink);">Elegir una imagen</h2>

                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <input class="fld" type="search" style="flex:1;min-width:200px;"
                           placeholder="Buscar por nombre o descripción…"
                           x-model="busqueda" x-on:input="buscarConRetardo()">

                    <select class="fld" style="width:auto;" x-model="origen" x-on:change="pagina = 1; cargar()">
                        <option value="">De donde sea</option>
                        <option value="subido">Subidas al panel</option>
                        <option value="codigo">Del diseño original</option>
                    </select>

                    <label class="btn btn-primary btn-sm" style="cursor:pointer;margin:0;">
                        <span x-show="!subiendo">Subir una</span>
                        <span x-show="subiendo" x-cloak>Subiendo…</span>
                        <input type="file" class="visualmente-oculto"
                               accept="{{ collect(\App\Services\Biblioteca::EXTENSIONES)->map(fn ($e) => '.'.$e)->implode(',') }}"
                               x-on:change="subirAqui($event.target).then(() => $dispatch('medio-cambiado'))">
                    </label>
                </div>
            </div>

            <div style="padding:20px 24px;overflow-y:auto;flex:1;min-height:220px;">
                <template x-if="error">
                    <p class="alert alert-error" style="margin:0 0 14px;" x-text="error"></p>
                </template>

                <template x-if="cargando">
                    <p class="helper" style="margin:0;">Cargando la biblioteca…</p>
                </template>

                <template x-if="!cargando && !medios.length && !error">
                    <p class="helper" style="margin:0;">Ningún archivo coincide con lo que buscas.</p>
                </template>

                <div class="rejilla-medios" x-show="!cargando && medios.length">
                    <template x-for="m in medios" x-bind:key="m.id">
                        <button type="button" class="ficha-medio"
                                x-bind:class="m.ruta === ruta ? 'esta-elegida' : ''"
                                style="text-align:left;cursor:pointer;font:inherit;padding:0;"
                                x-on:click="elegir(m); $dispatch('medio-cambiado')">
                            <span class="ficha-medio-imagen">
                                <img loading="lazy" decoding="async" x-bind:src="m.url" x-bind:alt="m.alt || m.etiqueta">
                            </span>
                            <span class="ficha-medio-pie">
                                <span class="ficha-medio-nombre" x-text="m.etiqueta"></span>
                                <span class="helper" x-text="(m.dimensiones || '—') + ' · ' + m.peso"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            <div style="padding:16px 24px;border-top:1px solid var(--linea);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <span class="helper" x-text="totalTexto"></span>

                <div style="display:flex;gap:10px;align-items:center;">
                    <button type="button" class="btn btn-outline btn-sm"
                            x-bind:disabled="pagina <= 1" x-on:click="irA(pagina - 1)">‹ Anterior</button>
                    <span class="helper" x-text="pagina + ' de ' + paginas"></span>
                    <button type="button" class="btn btn-outline btn-sm"
                            x-bind:disabled="pagina >= paginas" x-on:click="irA(pagina + 1)">Siguiente ›</button>
                    <button type="button" class="btn btn-outline btn-sm" x-on:click="cerrar()">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
