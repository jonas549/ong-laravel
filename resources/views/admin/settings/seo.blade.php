@extends('layouts.admin')
@section('title', 'SEO')

@section('content')
<form method="POST" action="{{ route('admin.settings.seo.update') }}" class="card" style="padding:26px;max-width:720px;">
    @csrf
    @method('PUT')

    <p class="helper" style="margin:0 0 22px;max-width:64ch;">
        Lo que se ve en Google y al compartir un enlace en redes. Cada página puede traer lo suyo;
        esto es lo que se usa cuando no lo trae.
    </p>

    @foreach ($ajustes as $a)
        @php $valor = $valores[$a->clave] ?? null; @endphp

        <div style="margin-bottom:20px;">
            @if ($a->tipo === 'bool')
                <label class="lbl" style="flex-direction:row;align-items:center;gap:10px;">
                    <input type="checkbox" name="{{ $a->clave }}" value="1" @checked((bool) $valor)>
                    <span>{{ $a->label }}</span>
                </label>
            @else
                <label class="lbl">{{ $a->label }}
                    <input class="fld @error($a->clave) is-invalid @enderror" name="{{ $a->clave }}"
                           value="@viejo($a->clave, $valor)">
                </label>
            @endif

            @if ($a->descripcion)
                <span class="helper" style="display:block;margin-top:5px;">{{ $a->descripcion }}</span>
            @endif

            @error($a->clave) <span class="field-error">{{ $message }}</span> @enderror

            @if ($a->clave === 'seo_descripcion')
                <span class="helper" style="display:block;margin-top:5px;">
                    Ahora tiene {{ mb_strlen((string) $valor) }} caracteres.
                </span>
            @endif
        </div>
    @endforeach

    <button type="submit" class="btn btn-primary">Guardar</button>
</form>
@endsection
