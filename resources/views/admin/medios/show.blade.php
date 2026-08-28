@extends('layouts.admin')
@section('title', $medio->etiqueta)

{{--
    La ficha de un archivo.

    «Dónde se usa» es la mitad de la pantalla, y no un dato de adorno: es lo
    que decide si borrar es seguro. Cuenta tres procedencias distintas —filas
    de las tablas, el JSON de las secciones del home, y los valores por defecto
    que viven en el código— porque una imagen puede estar sosteniendo la
    portada sin que ninguna fila la nombre.
--}}

@section('content')

<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,340px);gap:26px;align-items:start;">

    {{-- ── Vista y acciones ── --}}
    <div style="min-width:0;">
        <div style="background:#fff;border:1px solid var(--linea);border-radius:18px;overflow:hidden;">
            <div class="ficha-medio-imagen" style="height:auto;min-height:280px;padding:24px;">
                @if ($medio->existe)
                    <img src="{{ $medio->url }}" alt="{{ $medio->alt ?: $medio->nombre }}"
                         style="max-width:100%;max-height:460px;width:auto;object-fit:contain;">
                @else
                    <span class="ficha-medio-perdida">
                        El archivo no está en el disco. Queda la ficha, pero la imagen no se puede mostrar.
                    </span>
                @endif
            </div>

            <div style="padding:18px 20px;border-top:1px solid var(--linea);display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <a class="btn btn-outline btn-sm" href="{{ $medio->url }}" target="_blank" rel="noopener">Ver a tamaño real</a>

                <button type="button" class="btn btn-outline btn-sm"
                        x-data="{ copiado: false }"
                        x-on:click="navigator.clipboard.writeText({{ Js::from($medio->ruta) }}).then(() => { copiado = true; setTimeout(() => copiado = false, 1800); })">
                    <span x-show="!copiado">Copiar la ruta</span>
                    <span x-show="copiado" x-cloak>Copiada</span>
                </button>

                <code style="font-size:12.5px;color:var(--gris);background:var(--gris-100);padding:5px 9px;border-radius:8px;">{{ $medio->ruta }}</code>
            </div>
        </div>

        {{-- ── Reemplazar ── --}}
        @if (! $medio->es_del_codigo)
            <div style="background:#fff;border:1px solid var(--linea);border-radius:18px;padding:22px;margin-top:22px;">
                <div class="seclabel" style="margin-bottom:6px;">Reemplazar el archivo</div>
                <p class="helper" style="margin:0 0 16px;max-width:62ch;">
                    Se sube encima conservando la dirección, así que todo lo que ya usa esta imagen
                    seguirá funcionando sin tocar nada. Tiene que ser otro <strong>.{{ $medio->extension }}</strong>,
                    porque la dirección no cambia.
                </p>

                <form method="POST" action="{{ route('admin.medios.reemplazar', $medio) }}" enctype="multipart/form-data"
                      style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                    @csrf
                    <label class="lbl" style="flex:1;min-width:240px;">Archivo nuevo
                        <input class="fld @error('archivo') is-invalid @enderror" type="file" name="archivo"
                               accept=".{{ $medio->extension }}" required>
                        @error('archivo') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm" data-cargando="Subiendo…">Reemplazar</button>
                </form>
            </div>
        @else
            <div class="alert alert-info" style="margin-top:22px;">
                Esta imagen viene con el diseño original y se versiona con el código.
                No se reemplaza ni se borra desde el panel: el siguiente despliegue la repondría.
            </div>
        @endif
    </div>

    {{-- ── Datos, uso y borrado ── --}}
    <div style="display:flex;flex-direction:column;gap:22px;min-width:0;">

        <div style="background:#fff;border:1px solid var(--linea);border-radius:18px;padding:22px;">
            <div class="seclabel" style="margin-bottom:14px;">Datos</div>

            <dl style="margin:0 0 18px;display:grid;grid-template-columns:auto 1fr;gap:7px 14px;font-size:13.5px;">
                <dt class="helper">Archivo</dt>
                <dd style="margin:0;word-break:break-word;">{{ $medio->nombre }}</dd>

                <dt class="helper">Formato</dt>
                <dd style="margin:0;">{{ strtoupper($medio->extension) }}</dd>

                <dt class="helper">Medidas</dt>
                <dd style="margin:0;">{{ $medio->dimensiones ?? 'no aplica' }}</dd>

                <dt class="helper">Peso</dt>
                <dd style="margin:0;">{{ $medio->peso_legible }}</dd>

                <dt class="helper">Subido</dt>
                <dd style="margin:0;">{{ \App\Support\Fecha::conHora($medio->created_at) }}</dd>

                @if ($medio->autor)
                    <dt class="helper">Por</dt>
                    <dd style="margin:0;">{{ $medio->autor->name }}</dd>
                @endif

                <dt class="helper">Procedencia</dt>
                <dd style="margin:0;">{{ $medio->es_del_codigo ? 'Del diseño original' : 'Subida al panel' }}</dd>
            </dl>

            <form method="POST" action="{{ route('admin.medios.update', $medio) }}">
                @csrf
                @method('PUT')

                <label class="lbl" style="margin-bottom:14px;">Título
                    <input class="fld" type="text" name="titulo" value="{{ old('titulo', $medio->titulo) }}"
                           maxlength="255" placeholder="{{ $medio->nombre }}">
                    <span class="helper">Cómo se llama en la biblioteca. Si lo dejas vacío, se usa el nombre del archivo.</span>
                </label>

                <label class="lbl" style="margin-bottom:14px;">Texto alternativo
                    <textarea class="fld" name="alt" rows="3" maxlength="500"
                              placeholder="Qué se ve en la imagen">{{ old('alt', $medio->alt) }}</textarea>
                    <span class="helper">Lo lee quien no puede ver la imagen. Describe lo que se ve, no repitas el título.</span>
                </label>

                <label class="lbl" style="margin-bottom:18px;">Carpeta
                    <input class="fld" type="text" name="carpeta" value="{{ old('carpeta', $medio->carpeta) }}"
                           maxlength="100" placeholder="Sin carpeta">
                </label>

                <button type="submit" class="btn btn-primary btn-sm" data-cargando="Guardando…">Guardar</button>
            </form>
        </div>

        {{-- ── Dónde se usa ── --}}
        <div style="background:#fff;border:1px solid var(--linea);border-radius:18px;padding:22px;">
            <div class="seclabel" style="margin-bottom:10px;">Dónde se usa</div>

            @if ($usos === [])
                <p class="helper" style="margin:0;">
                    En ningún sitio. Se puede borrar sin romper nada.
                </p>
            @else
                <p class="helper" style="margin:0 0 12px;">
                    En {{ count($usos) }} {{ \App\Support\Texto::plural('sitio', count($usos)) }}.
                    Si la borras, ahí quedará un hueco.
                </p>

                <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;">
                    @foreach ($usos as $uso)
                        <li style="font-size:13.5px;display:flex;gap:8px;align-items:baseline;">
                            <span style="flex:none;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--naranjo);">{{ $uso['que'] }}</span>
                            @if ($uso['url'])
                                <a href="{{ $uso['url'] }}">{{ $uso['rotulo'] }}</a>
                            @else
                                <span>{{ $uso['rotulo'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ── Borrar ── --}}
        @if (! $medio->es_del_codigo)
            <div style="background:#fff;border:1px solid #f3d9e2;border-radius:18px;padding:22px;">
                <div class="seclabel" style="margin-bottom:8px;color:var(--rosa);">Borrar</div>
                <p class="helper" style="margin:0 0 14px;">
                    Se borra el archivo del disco y no se puede deshacer.
                </p>

                {{--
                    El permiso para borrar algo en uso viaja en la URL, no en una
                    casilla: `<x-panel.confirmar>` monta su propio formulario desde
                    el diálogo del layout y no puede llevar campos extra.

                    Sigue haciendo falta en el servidor. Así un DELETE lanzado a
                    mano contra un archivo en uso se para solo, y el único camino
                    que lo borra es éste, que enseña antes dónde sale.
                --}}
                <x-panel.confirmar
                    :accion="route('admin.medios.destroy', $medio).($usos !== [] ? '?aunque_este_en_uso=1' : '')"
                    :titulo="'Borrar «'.\Illuminate\Support\Str::limit($medio->etiqueta, 40).'»'"
                    :texto="$usos === []
                        ? 'Se borra el archivo del disco. No se puede deshacer.'
                        : 'Se está usando en '.count($usos).' '.\App\Support\Texto::plural('sitio', count($usos)).', y ahí quedará un hueco. No se puede deshacer.'"
                    confirmar="Sí, borrar"
                    boton="Borrar el archivo" />
            </div>
        @endif
    </div>
</div>

@endsection
