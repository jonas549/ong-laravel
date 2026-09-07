@extends('layouts.admin')
@section('title', 'Configuración general')

@section('content')
<section class="card" style="padding:26px;max-width:620px;">
    <form method="POST" action="{{ route('admin.settings.general.update') }}" style="display:flex;flex-direction:column;gap:18px;">
        @csrf
        @method('PUT')

        @foreach ($ajustes as $a)
            <div>
                @php $opciones = \App\Support\CatalogoAjustes::opciones($a->clave); @endphp

                @if ($opciones)
                    {{-- Ajuste de lista cerrada: se elige, no se escribe. --}}
                    <label class="helper" for="s-{{ $a->clave }}" style="display:block;margin-bottom:6px;font-weight:600;">{{ $a->label }}</label>
                    <select class="fld" id="s-{{ $a->clave }}" name="{{ $a->clave }}">
                        @foreach ($opciones as $valor => $texto)
                            <option value="{{ $valor }}" @selected(($valores[$a->clave] ?? null) === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                @elseif ($a->tipo === 'bool')
                    <label style="display:flex;align-items:center;gap:10px;font-size:14.5px;color:var(--gris-700);cursor:pointer;">
                        <input type="checkbox" name="{{ $a->clave }}" value="1" @checked($valores[$a->clave] ?? false)>
                        {{ $a->label }}
                    </label>
                @else
                    <label class="helper" for="s-{{ $a->clave }}" style="display:block;margin-bottom:6px;font-weight:600;">{{ $a->label }}</label>
                    <input class="fld" type="{{ $a->tipo === 'int' ? 'number' : 'text' }}" id="s-{{ $a->clave }}"
                           name="{{ $a->clave }}" value="{{ $valores[$a->clave] ?? '' }}">
                @endif

                @if ($a->descripcion)
                    <span class="helper" style="display:block;margin-top:5px;">{{ $a->descripcion }}</span>
                @endif
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary" style="align-self:flex-start;">Guardar</button>
    </form>
</section>
@endsection
