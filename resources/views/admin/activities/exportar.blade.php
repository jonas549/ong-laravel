@extends('layouts.admin')
@section('title', 'Exportar actividades')

@section('content')
{{--
    La misma pantalla que Exportar inscripciones (ajustes del 06/10): los
    filtros van por GET a esta misma pantalla, para ver cuántas saldrían antes
    de descargar nada. Sin filtros salen TODAS, en cualquier estado.
--}}
<form method="GET" id="filtros-exportar" class="card" style="padding:26px;max-width:820px;margin-bottom:20px;">
    <div class="seclabel" style="margin-bottom:16px;">Qué exportar</div>

    <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <label class="lbl">Actividad u organización
            <input class="fld" type="search" name="q" value="{{ $filtros['q'] }}" placeholder="Todas">
        </label>

        <label class="lbl">Mostrar
            <select class="fld" name="estado">
                <option value="">Todas las actividades</option>
                @foreach ($estados as $clave => $meta)
                    <option value="{{ $clave }}" @selected($filtros['estado'] === $clave)>{{ $meta['filtro'] }}</option>
                @endforeach
            </select>
        </label>

        <div>
            <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <label class="lbl">Desde
                    <input class="fld" type="date" name="desde" value="{{ $filtros['desde'] }}">
                </label>
                <label class="lbl">Hasta
                    <input class="fld" type="date" name="hasta" value="{{ $filtros['hasta'] }}">
                </label>
            </div>
            <span class="helper" style="display:block;margin-top:6px;">Por la fecha en que se registró la actividad.</span>
        </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-outline">Ver cuántas son</button>
        @if (array_filter($filtros))
            <a class="btn btn-ghost" href="{{ route('admin.activities.exportar') }}">Limpiar</a>
        @endif
    </div>
</form>

<div class="card" style="padding:26px;max-width:820px;">
    <div class="seclabel" style="margin-bottom:6px;">Descargar</div>
    <p data-cuenta="filtros-exportar" style="font-size:15.5px;line-height:1.6;margin:0 0 18px;color:var(--ink);">
        Con estos filtros saldrían
        <strong data-cuantas>{{ $cuantas }}</strong> {{ $cuantas === 1 ? 'actividad' : 'actividades' }}.
    </p>
    <p data-cuenta-vieja="filtros-exportar" hidden style="font-size:15.5px;line-height:1.6;margin:0 0 18px;color:var(--ink);">
        Cambiaste los filtros: pulsa «Ver cuántas son» para saber cuántas saldrían.
    </p>

    {{-- La descarga lee el formulario en el momento del clic (`data-filtros`,
         en panel.js), como en Exportar inscripciones. --}}
    @if ($cuantas > 0)
        <a class="btn btn-primary" href="{{ route('admin.activities.descargar', request()->query()) }}" data-descarga data-filtros="filtros-exportar">
            Descargar en Excel
        </a>
    @else
        <p class="helper" style="margin:0;">No hay nada que descargar con este recorte.</p>
    @endif
</div>
@endsection
