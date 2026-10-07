@extends('layouts.admin')
@section('title', 'Exportar inscripciones')

@section('content')
{{--
    Los filtros se envían por GET a esta misma pantalla: así se ve cuántas
    filas saldrían antes de descargar nada, que es lo que uno quiere saber.
--}}
<form method="GET" id="filtros-exportar" class="card" style="padding:26px;max-width:820px;margin-bottom:20px;">
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

        {{-- C3: sin estados. Ofrecía los tres del esquema y dos no separaban
             nada —«pendiente» devolvía todo y «confirmado» nada—, porque el
             doble opt-in no se construyó. --}}
        <label class="lbl">Mostrar
            <select class="fld" name="estado">
                <option value="">Todas las inscripciones</option>
                <option value="activas" @selected($filtros['estado'] === 'activas')>Sin las canceladas</option>
                <option value="cancelado" @selected($filtros['estado'] === 'cancelado')>Sólo las canceladas</option>
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

    {{-- Ajustes del 06/10: desmarcada, el Excel sale como siempre. No cambia
         cuántas salen, así que tocarla no deja viejo el número de abajo. --}}
    <label style="display:flex;align-items:center;gap:10px;margin-top:18px;cursor:pointer;font-size:14.5px;color:var(--ink);">
        <input type="checkbox" name="respuestas" value="1" data-no-cuenta @checked(request()->boolean('respuestas'))
               style="width:18px;height:18px;margin:0;accent-color:var(--naranjo);">
        Incluir también las respuestas de la evaluación
    </label>

    <div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-outline">Ver cuántas son</button>
        @if (array_filter($filtros))
            <a class="btn btn-ghost" href="{{ route('admin.registrations.exportar') }}">Limpiar</a>
        @endif
    </div>
</form>

<div class="card" style="padding:26px;max-width:820px;">
    <div class="seclabel" style="margin-bottom:6px;">Descargar</div>
    <p data-cuenta="filtros-exportar" style="font-size:15.5px;line-height:1.6;margin:0 0 18px;color:var(--ink);">
        Con estos filtros saldrían
        <strong>{{ $cuantos }}</strong> {{ $cuantos === 1 ? 'inscripción' : 'inscripciones' }}.
    </p>
    <p data-cuenta-vieja="filtros-exportar" hidden style="font-size:15.5px;line-height:1.6;margin:0 0 18px;color:var(--ink);">
        Cambiaste los filtros: pulsa «Ver cuántas son» para saber cuántas saldrían.
    </p>

    {{-- La descarga lee el formulario en el momento del clic (`data-filtros`,
         en panel.js): lo marcado sin pasar por «Ver cuántas son» también
         cuenta. El href de aquí es sólo lo que había al cargar. --}}
    @if ($cuantos > 0)
        <a class="btn btn-primary" href="{{ route('admin.registrations.descargar', request()->query()) }}" data-descarga data-filtros="filtros-exportar">
            Descargar en Excel
        </a>
    @else
        <p class="helper" style="margin:0;">No hay nada que descargar con este recorte.</p>
    @endif
</div>
@endsection
