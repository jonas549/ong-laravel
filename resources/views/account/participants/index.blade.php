@extends('layouts.account')
@section('title', 'Participantes inscritos')

@section('content')
<a href="{{ route('account.activities.index') }}" class="textlink" style="font-size:14px;">← Volver a mis actividades</a>

<div style="margin:18px 0 26px;">
    <h1 style="font-weight:800;font-size:28px;margin:0 0 6px;letter-spacing:-.01em;">Participantes inscritos</h1>
    <p class="helper" style="margin:0;">{{ $activity->titulo }}</p>
</div>

@php
    $confirmados = $inscritos->where('estado', 'confirmado')->count();
    $activos = $inscritos->where('estado', '!=', 'cancelado')->count();
@endphp

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:26px;">
    <div class="kpi"><span class="v" style="color:var(--naranjo);">{{ $activos }}</span><span class="l">inscritos</span></div>
    <div class="kpi"><span class="v" style="color:var(--teal);">{{ $activity->cupos_totales ?? '—' }}</span><span class="l">cupos totales</span></div>
    <div class="kpi"><span class="v" style="color:var(--turquesa);">{{ $activity->cupos_disponibles ?? '—' }}</span><span class="l">cupos disponibles</span></div>
    <div class="kpi"><span class="v" style="color:var(--gris-700);">{{ $confirmados }}</span><span class="l">confirmados</span></div>
</div>

<div class="card" style="padding:20px 22px;margin-bottom:22px;display:flex;gap:14px;flex-wrap:wrap;align-items:end;">
    <form method="GET" style="display:flex;gap:10px;align-items:end;flex:1;min-width:240px;">
        <div style="flex:1;">
            <label class="helper" for="q" style="display:block;margin-bottom:6px;font-weight:600;">Buscar</label>
            <input class="fld" type="search" id="q" name="q" value="{{ $busqueda }}" placeholder="Buscar por nombre o correo">
        </div>
        <button type="submit" class="btn btn-outline btn-sm">Buscar</button>
    </form>

    <form method="POST" action="{{ route('account.participants.cupos', $activity) }}" style="display:flex;gap:10px;align-items:end;">
        @csrf
        @method('PATCH')
        <div>
            <label class="helper" for="cupos_disponibles" style="display:block;margin-bottom:6px;font-weight:600;">Editar cupos disponibles</label>
            <input class="fld @error('cupos_disponibles') is-invalid @enderror" type="number" min="0" style="width:130px;"
                   id="cupos_disponibles" name="cupos_disponibles" value="{{ old('cupos_disponibles', $activity->cupos_disponibles) }}">
            @error('cupos_disponibles') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
    </form>
</div>

@if ($inscritos->isEmpty())
    <div class="card" style="padding:40px;text-align:center;">
        <p style="color:var(--gris);margin:0;">
            {{ $busqueda ? 'No encontramos inscritos con esa búsqueda.' : 'Todavía no hay personas inscritas.' }}
        </p>
    </div>
@else
    <div class="tabla-wrap">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Fecha</th>
                    <th>Mayor de edad</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($inscritos as $i)
                    @php $c = $i->estado_color; @endphp
                    <tr>
                        <td style="font-weight:600;">{{ $i->nombre }}</td>
                        <td>{{ $i->correo }}</td>
                        <td>{{ $i->created_at->locale('es')->isoFormat('D MMM YYYY') }}</td>
                        <td>{{ $i->es_mayor_edad ? 'Sí' : 'No' }}</td>
                        <td>
                            <span style="font-size:12.5px;font-weight:600;padding:4px 11px;border-radius:999px;background:{{ $c['bg'] }};color:{{ $c['ink'] }};">
                                {{ $i->estado_label }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
