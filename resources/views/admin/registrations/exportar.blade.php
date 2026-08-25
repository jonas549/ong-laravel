@extends('layouts.admin')
@section('title', 'Exportar inscripciones')

@section('content')
{{--
    Los filtros se envían por GET a esta misma pantalla: así se ve cuántas
    filas saldrían antes de descargar nada, que es lo que uno quiere saber.
--}}
<form method="GET" class="card" style="padding:26px;max-width:820px;margin-bottom:20px;">
    <div class="seclabel" style="margin-bottom:16px;">Qué exportar</div>

    <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <label class="lbl">Nombre o correo
            <input class="fld" type="search" name="q" value="{{ $filtros['q'] }}" placeholder="Todos">
        </label>

        <label class="lbl">Actividad
            <select class="fld" name="actividad">
                <option value="">Todas</option>
                @foreach ($actividades as $id => $titulo)
                    <option value="{{ $id }}" @selected($filtros['actividad'] == $id)>{{ $titulo }}</option>
                @endforeach
            </select>
        </label>

        <label class="lbl">Estado
            <select class="fld" name="estado">
                <option value="">Todos</option>
                @foreach ($estados as $e)
                    <option value="{{ $e }}" @selected($filtros['estado'] === $e)>{{ ucfirst($e) }}</option>
                @endforeach
            </select>
        </label>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <label class="lbl">Desde
                <input class="fld" type="date" name="desde" value="{{ $filtros['desde'] }}">
            </label>
            <label class="lbl">Hasta
                <input class="fld" type="date" name="hasta" value="{{ $filtros['hasta'] }}">
            </label>
        </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-outline">Ver cuántas son</button>
        @if (array_filter($filtros))
            <a class="btn btn-ghost" href="{{ route('admin.registrations.exportar') }}">Limpiar</a>
        @endif
    </div>
</form>

<div class="card" style="padding:26px;max-width:820px;">
    <div class="seclabel" style="margin-bottom:6px;">Descargar</div>
    <p style="font-size:15.5px;line-height:1.6;margin:0 0 18px;color:var(--ink);">
        Con estos filtros saldrían
        <strong>{{ $cuantos }}</strong> {{ $cuantos === 1 ? 'inscripción' : 'inscripciones' }}.
    </p>

    @if ($cuantos > 0)
        <a class="btn btn-primary" href="{{ route('admin.registrations.descargar', request()->query()) }}">
            Descargar en Excel
        </a>
    @else
        <p class="helper" style="margin:0;">No hay nada que descargar con este recorte.</p>
    @endif
</div>
@endsection
