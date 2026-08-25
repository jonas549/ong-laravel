@extends('layouts.admin')
@section('title', ($fila->exists ? 'Editar' : 'Agregar') . ' · ' . $def['titulo'])
@section('miga', $fila->exists ? 'Editar' : 'Agregar')

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
                // viejoCrudo y no old(): con `campo[]=x` old() devuelve un
                // array y el {{ }} de abajo revienta con un 500. Crudo porque
                // aquí el tipo importa: más abajo se comprueba si es un Carbon.
                $valor = \App\Support\Formulario::viejoCrudo($campo, $fila->{$campo} ?? null);
                $id = 'c-' . $campo;
            @endphp

            <div>
                @if ($meta['tipo'] === 'bool')
                    <label style="display:flex;align-items:center;gap:10px;font-size:14.5px;color:var(--gris-700);cursor:pointer;">
                        <input type="checkbox" name="{{ $campo }}" value="1" @checked((bool) $valor)>
                        {{ $meta['label'] }}
                    </label>

                @elseif ($meta['tipo'] === 'textarea')
                    <label class="helper" for="{{ $id }}" style="display:block;margin-bottom:6px;font-weight:600;">{{ $meta['label'] }}</label>
                    <textarea class="fld @error($campo) is-invalid @enderror" id="{{ $id }}" name="{{ $campo }}" rows="5">{{ $valor }}</textarea>

                @elseif ($meta['tipo'] === 'select')
                    <label class="helper" for="{{ $id }}" style="display:block;margin-bottom:6px;font-weight:600;">{{ $meta['label'] }}</label>
                    <select class="fld @error($campo) is-invalid @enderror" id="{{ $id }}" name="{{ $campo }}">
                        @foreach ($meta['opciones'] as $k => $label)
                            <option value="{{ $k }}" @selected($valor === $k)>{{ $label }}</option>
                        @endforeach
                    </select>

                @elseif ($meta['tipo'] === 'datetime')
                    <label class="helper" for="{{ $id }}" style="display:block;margin-bottom:6px;font-weight:600;">{{ $meta['label'] }}</label>
                    <input class="fld @error($campo) is-invalid @enderror" type="datetime-local" id="{{ $id }}" name="{{ $campo }}"
                           value="{{ $valor instanceof \Illuminate\Support\Carbon ? $valor->format('Y-m-d\TH:i') : $valor }}">

                @else
                    <label class="helper" for="{{ $id }}" style="display:block;margin-bottom:6px;font-weight:600;">{{ $meta['label'] }}</label>
                    <input class="fld @error($campo) is-invalid @enderror" type="{{ $meta['tipo'] === 'number' ? 'number' : 'text' }}"
                           id="{{ $id }}" name="{{ $campo }}" value="{{ $valor }}">
                @endif

                @if (! empty($meta['ayuda']))
                    <span class="helper" style="display:block;margin-top:5px;">{{ $meta['ayuda'] }}</span>
                @endif

                @error($campo) <span class="field-error">{{ $message }}</span> @enderror
            </div>
        @endforeach

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">{{ $fila->exists ? 'Guardar cambios' : 'Crear' }}</button>
            <a href="{{ route('admin.content.index', $tipo) }}" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</section>
@endsection
