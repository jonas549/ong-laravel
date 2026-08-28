@extends('layouts.admin')
@section('title', 'Regiones y comunas')

{{--
    Regiones y comunas.

    Se consultan y se apagan, pero no se crean ni se borran: son la división
    administrativa de Chile, no un catálogo de la ONG.

    El listado es de comunas y no de regiones porque son las comunas lo que se
    busca y lo que se apaga; la región es una columna más y un filtro. La
    columna «Actividades» está para que apagar una comuna sea una decisión
    informada: si tiene actividades dentro, seguirán viéndose.
--}}

@section('content')

<p class="helper" style="margin:-6px 0 16px;">
    Apagar una comuna la quita de los selectores del wizard. Las actividades que ya están ahí no cambian:
    conservan su ubicación y se siguen viendo. No se pueden crear ni borrar, porque hay actividades apuntando a ellas.
    @if ($apagadas)
        <strong>Ahora mismo hay {{ \App\Support\Texto::cuantos($apagadas, 'comuna') }} {{ \App\Support\Texto::plural('apagada', $apagadas) }}.</strong>
    @endif
</p>

<x-panel.filtros
    buscar="Buscar una comuna…"
    :exportar="[
        'xlsx' => route('admin.regiones.exportar', ['formato' => 'xlsx'] + request()->query()),
        'csv' => route('admin.regiones.exportar', ['formato' => 'csv'] + request()->query()),
    ]">

    <select class="fld" name="region" x-on:change="enviar()" aria-label="Región">
        <option value="">Todas las regiones</option>
        @foreach ($regiones as $r)
            <option value="{{ $r->id }}" @selected((int) request('region') === $r->id)>{{ $r->nombre }}</option>
        @endforeach
    </select>

    <select class="fld" name="estado" x-on:change="enviar()" aria-label="Se ofrece">
        <option value="">Encendidas y apagadas</option>
        <option value="si" @selected(request('estado') === 'si')>Sólo las encendidas</option>
        <option value="no" @selected(request('estado') === 'no')>Sólo las apagadas</option>
    </select>
</x-panel.filtros>

<x-panel.tabla
    :filas="$comunas"
    :columnas="5"
    :vacio="request()->hasAny(['q', 'region', 'estado']) ? 'Ninguna comuna coincide con el filtro.' : 'No hay comunas cargadas. Corre php artisan dps:instalar.'">

    <x-slot:cabecera>
        <x-panel.columna campo="nombre">Comuna</x-panel.columna>
        <th>Región</th>
        <th class="num">Actividades</th>
        <x-panel.columna campo="activo">Se ofrece</x-panel.columna>
        <th></th>
    </x-slot:cabecera>

    @foreach ($comunas as $c)
        <tr>
            <td style="font-weight:600;">{{ $c->nombre }}</td>
            <td>{{ $c->region?->nombre ?? '—' }}</td>
            <td class="num">
                @if ($c->activities_count)
                    <a class="textlink" href="{{ route('admin.activities.index', ['q' => $c->nombre]) }}">{{ $c->activities_count }}</a>
                @else
                    <span style="color:var(--gris);">0</span>
                @endif
            </td>
            <td><span class="insignia insignia-{{ $c->activo ? 'si' : 'no' }}">{{ $c->activo ? 'Sí' : 'No' }}</span></td>
            <td class="col-acciones">
                <form method="POST" action="{{ route('admin.regiones.comuna.estado', $c) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">{{ $c->activo ? 'Apagar' : 'Encender' }}</button>
                </form>
            </td>
        </tr>
    @endforeach
</x-panel.tabla>

<section class="card" style="padding:20px 24px;margin-top:22px;">
    <div class="seclabel" style="margin-bottom:12px;">Apagar una región entera</div>
    <p class="helper" style="margin:0 0 14px;">Apaga o enciende la región y todas sus comunas de una vez.</p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @foreach ($regiones as $r)
            <form method="POST" action="{{ route('admin.regiones.estado', $r) }}">
                @csrf
                <button type="submit" class="btn btn-sm {{ $r->activo ? 'btn-outline' : 'btn-ghost' }}"
                        title="{{ $r->activo ? 'Apagar esta región y sus comunas' : 'Encender esta región y sus comunas' }}">
                    {{ $r->nombre }}
                    <span aria-hidden="true" style="margin-left:6px;opacity:.6;">{{ $r->activo ? '●' : '○' }}</span>
                </button>
            </form>
        @endforeach
    </div>
</section>
@endsection
