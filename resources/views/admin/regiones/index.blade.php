@extends('layouts.admin')
@section('title', 'Regiones y comunas')

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px;">
    <div class="kpi"><span class="v">{{ $regiones->count() }}</span><span class="l">regiones</span></div>
    <div class="kpi"><span class="v">{{ $totalComunas }}</span><span class="l">comunas</span></div>
</div>

<div class="alert alert-info" style="margin-bottom:20px;">
    Este catálogo no se edita desde el panel: es la división administrativa de Chile, no una lista
    de la organización. Si hay que corregir algo, se hace por migración para que todas las
    instalaciones queden igual.
</div>

<form method="GET" class="card" style="padding:18px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <label class="lbl" style="flex:1;min-width:220px;">Buscar
        <input class="fld" type="search" name="q" value="{{ $busqueda }}" placeholder="Región o comuna…">
    </label>
    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
        @if ($busqueda)
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.regiones.index') }}">Limpiar</a>
        @endif
    </div>
</form>

@forelse ($regiones as $region)
    <div class="card" style="padding:20px 22px;margin-bottom:14px;">
        <div style="display:flex;align-items:baseline;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:10px;">
            <strong style="font-size:15.5px;">{{ $region->nombre }}</strong>
            <span class="helper">{{ $region->communes_count }} {{ $region->communes_count === 1 ? 'comuna' : 'comunas' }}</span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            @foreach ($region->communes as $comuna)
                <span style="font-size:12.5px;padding:4px 10px;border-radius:999px;background:var(--bg-warm);color:var(--gris-700);">{{ $comuna->nombre }}</span>
            @endforeach
        </div>
    </div>
@empty
    <div class="card" style="padding:34px;text-align:center;color:var(--gris);">
        No hay ninguna región que coincida con «{{ $busqueda }}».
    </div>
@endforelse
@endsection
