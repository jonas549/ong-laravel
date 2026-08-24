@extends('layouts.admin')
@section('title', 'Actividades')

@section('content')
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
    <a class="tab {{ $estado === '' ? 'on' : '' }}" href="{{ route('admin.activities.index') }}">
        Todas <span class="count-badge">{{ $conteos->sum() }}</span>
    </a>
    @foreach (\App\Models\Activity::ESTADOS as $clave => $meta)
        <a class="tab {{ $estado === $clave ? 'on' : '' }}" href="{{ route('admin.activities.index', ['estado' => $clave]) }}">
            {{ $meta['filtro'] }} <span class="count-badge">{{ $conteos[$clave] ?? 0 }}</span>
        </a>
    @endforeach
</div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;max-width:420px;">
    <input type="hidden" name="estado" value="{{ $estado }}">
    <input class="fld" type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre…">
    <button type="submit" class="btn btn-outline btn-sm">Buscar</button>
</form>

<div class="tabla-wrap">
    <table class="tabla">
        <thead>
            <tr>
                <th>Actividad</th>
                <th>Organización</th>
                <th>Fecha</th>
                <th>Lugar</th>
                <th class="num">Inscritos</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($actividades as $a)
                @php $t = $a->estado_color; @endphp
                <tr>
                    <td style="font-weight:600;">
                        {{ Str::limit($a->titulo, 46) }}
                        @if ($a->destacada)
                            <span title="Aparece en el home" style="color:var(--amarillo);">★</span>
                        @endif
                    </td>
                    <td>{{ $a->organization?->nombre }}</td>
                    <td style="white-space:nowrap;">{{ $a->fecha_corta }}</td>
                    <td>{{ $a->lugar }}</td>
                    <td class="num">{{ $a->inscritos }}</td>
                    <td>
                        <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;white-space:nowrap;background:{{ $t['bg'] }};color:{{ $t['ink'] }};">
                            {{ $a->estado_filtro }}
                        </span>
                    </td>
                    <td><a class="btn btn-outline btn-sm" href="{{ route('admin.activities.show', $a) }}">Revisar</a></td>
                </tr>
            @empty
                <tr><td colspan="7" style="color:var(--gris);">No hay actividades con ese filtro.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:22px;">{{ $actividades->links() }}</div>
@endsection
