@extends('layouts.admin')
@section('title', ($fila->exists ? 'Editar' : 'Agregar') . ' · ' . $def['titulo'])
@section('miga', $fila->exists ? 'Editar' : 'Agregar')

{{--
    Formulario genérico de contenido, ya con `<x-panel.campo>`.

    Cada campo se pinta con el componente y recibe **las mismas reglas que usa
    el `validate()` del controlador**, así que el aviso en pantalla y el del
    servidor salen de una sola lista. Antes esta vista repetía cinco variantes
    de `<input>` con su etiqueta y su error a mano, y no avisaba de nada hasta
    enviar.
--}}

@section('content')
<a href="{{ route('admin.content.index', $tipo) }}" class="textlink" style="font-size:14px;">← Volver a {{ Str::lower($def['titulo']) }}</a>

<section class="card" style="padding:26px;margin-top:18px;max-width:680px;">
    <form method="POST"
          action="{{ $fila->exists ? route('admin.content.update', [$tipo, $fila->id]) : route('admin.content.store', $tipo) }}"
          style="display:flex;flex-direction:column;gap:18px;">
        @csrf
        @if ($fila->exists)
            @method('PUT')
        @endif

        @foreach ($def['campos'] as $campo => $meta)
            @php
                /*
                 * `viejoCrudo` y no `old()`: con `campo[]=x`, `old()` devuelve
                 * un array y el `{{ }}` del componente revienta con un 500.
                 * Crudo porque aquí el tipo importa —abajo se mira si es un
                 * Carbon para el campo de fecha—.
                 */
                $valor = \App\Support\Formulario::viejoCrudo($campo, $fila->{$campo} ?? null);

                if ($meta['tipo'] === 'datetime') {
                    $valor = \App\Support\Fecha::paraInput($valor);
                }
            @endphp

            @if ($meta['tipo'] === 'imagen')
                {{--
                    Antes esto era un campo de texto donde había que escribir la
                    ruta a mano, y el archivo tenía que estar ya en el servidor.
                    El selector envía exactamente la misma cadena, así que las
                    reglas de validación de arriba siguen valiendo tal cual.
                --}}
                <div style="margin-bottom:18px;">
                    <x-panel.imagen
                        :name="$campo"
                        :value="$valor"
                        :label="$meta['label']"
                        :ayuda="$meta['ayuda'] ?? null" />
                    @error($campo)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @else
                <x-panel.campo
                    :nombre="$campo"
                    :label="$meta['label']"
                    :tipo="$meta['tipo'] === 'datetime' ? 'datetime-local' : $meta['tipo']"
                    :valor="$valor"
                    :reglas="$meta['reglas'] ?? ''"
                    :opciones="$meta['opciones'] ?? []"
                    :ayuda="$meta['ayuda'] ?? null" />
            @endif
        @endforeach

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary" data-cargando="Guardando…">
                {{ $fila->exists ? 'Guardar cambios' : 'Crear' }}
            </button>
            <a href="{{ route('admin.content.index', $tipo) }}" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</section>
@endsection
