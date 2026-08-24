@extends('layouts.admin')
@section('title', 'Inscripciones')

@section('content')
<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <input class="fld" style="max-width:280px;" type="search" name="q" value="{{ request('q') }}" placeholder="Buscar nombre o correo…">
    <select class="fld" style="max-width:180px;" name="estado">
        <option value="">Todos</option>
        @foreach (\App\Models\Registration::ESTADOS as $e)
            <option value="{{ $e }}" @selected(request('estado') === $e)>{{ ucfirst($e) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
</form>

<div class="tabla-wrap">
    <table class="tabla">
        <thead><tr><th>Persona</th><th>Correo</th><th>Actividad</th><th>Fecha</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($inscritos as $i)
                @php $c = $i->estado_color; @endphp
                <tr>
                    <td style="font-weight:600;">{{ $i->nombre }}</td>
                    <td>{{ $i->correo }}</td>
                    <td>{{ Str::limit($i->activity?->titulo, 38) }}</td>
                    <td style="white-space:nowrap;">{{ $i->created_at->locale('es')->isoFormat('D MMM YYYY') }}</td>
                    <td>
                        <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:{{ $c['bg'] }};color:{{ $c['ink'] }};">
                            {{ $i->estado_label }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:var(--gris);">Sin inscripciones.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:22px;">{{ $inscritos->links() }}</div>
@endsection
