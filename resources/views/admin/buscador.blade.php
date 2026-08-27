@extends('layouts.admin')
@section('title', 'Buscar')
@section('migaPadre', 'Buscar')
@section('miga', 'Resultados')

@section('content')
<form method="GET" class="card" style="padding:20px;margin-bottom:20px;max-width:720px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
    <label class="lbl" style="flex:1;min-width:240px;">Buscar en el panel
        <input class="fld" type="search" name="q" value="{{ $termino }}" autofocus
               placeholder="Una actividad, una organización, una persona, una pantalla…">
    </label>
    <button type="submit" class="btn btn-primary">Buscar</button>
</form>

@if ($termino === '')
    <p class="helper">Escribe algo para empezar.</p>
@elseif (mb_strlen($termino) < 2)
    <p class="helper">Con una sola letra saldría medio panel. Escribe al menos dos.</p>
@elseif ($grupos === [])
    <div class="card" style="padding:34px;text-align:center;color:var(--gris);">
        No encontramos nada que se llame «{{ $termino }}».
    </div>
@else
    @foreach ($grupos as $grupo)
        <div class="card" style="padding:22px;margin-bottom:14px;max-width:820px;">
            <div class="seclabel" style="margin-bottom:12px;">{{ $grupo['titulo'] }}</div>

            <div style="display:flex;flex-direction:column;">
                @foreach ($grupo['items'] as $item)
                    <a href="{{ $item['url'] }}"
                       style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:10px 0;border-top:1px solid var(--linea);">
                        <span style="font-weight:600;color:var(--ink);">{{ $item['texto'] }}</span>
                        <span class="helper">{{ $item['detalle'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
@endif
@endsection
