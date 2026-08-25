@extends('layouts.admin')
@section('title', 'Registro de accesos')

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px;">
    <div class="kpi"><span class="v" style="color:var(--turquesa);">{{ $exitos24h }}</span><span class="l">entradas · 24 h</span></div>
    <div class="kpi"><span class="v" style="color:{{ $fallos24h ? 'var(--rosa)' : 'var(--gris-700)' }};">{{ $fallos24h }}</span><span class="l">intentos fallidos · 24 h</span></div>
</div>

@if ($sospechosos->isNotEmpty())
    {{-- Lo que de verdad se mira: correos con varios fallos seguidos. --}}
    <div class="card" style="padding:22px;margin-bottom:20px;border-left:4px solid var(--rosa);">
        <div class="seclabel" style="margin-bottom:6px;">Intentos repetidos en las últimas 24 h</div>
        <p class="helper" style="margin:0 0 14px;max-width:70ch;">
            Tres o más fallos desde la misma IP con el mismo correo. A los cinco, la combinación queda bloqueada 15 minutos.
        </p>

        <div class="tabla-wrap">
            <table class="tabla">
                <thead><tr><th>Correo</th><th>Panel</th><th>IP</th><th>Fallos</th><th>Último</th><th></th></tr></thead>
                <tbody>
                    @foreach ($sospechosos as $s)
                        <tr>
                            <td style="font-weight:600;">{{ $s->email }}</td>
                            <td>{{ $s->panel }}</td>
                            <td>{{ $s->ip ?? '—' }}</td>
                            <td>{{ $s->intentos }}</td>
                            <td style="white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($s->ultimo)->locale('es')->diffForHumans() }}</td>
                            <td style="text-align:right;">
                                @if ($s->bloqueado)
                                    <form method="POST" action="{{ route('admin.accesos.desbloquear') }}" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="email" value="{{ $s->email }}">
                                        <input type="hidden" name="panel" value="{{ $s->panel }}">
                                        <input type="hidden" name="ip" value="{{ $s->ip }}">
                                        <button type="submit" class="btn btn-outline btn-sm">Levantar bloqueo</button>
                                    </form>
                                @else
                                    <span class="helper">Sin bloqueo</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<form method="GET" class="card" style="padding:18px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <label class="lbl" style="flex:1;min-width:220px;">Buscar
        <input class="fld" type="search" name="q" value="{{ $filtros['q'] }}" placeholder="Correo o IP…">
    </label>

    <label class="lbl" style="min-width:190px;">Resultado
        <select class="fld" name="resultado">
            <option value="">Todos</option>
            <option value="fallidos" @selected($filtros['resultado'] === 'fallidos')>Sólo fallidos</option>
            @foreach ($resultados as $clave => $etiqueta)
                <option value="{{ $clave }}" @selected($filtros['resultado'] === $clave)>{{ $etiqueta }}</option>
            @endforeach
        </select>
    </label>

    <label class="lbl" style="min-width:150px;">Panel
        <select class="fld" name="panel">
            <option value="">Los dos</option>
            <option value="admin" @selected($filtros['panel'] === 'admin')>Administración</option>
            <option value="organizador" @selected($filtros['panel'] === 'organizador')>Organizador</option>
        </select>
    </label>

    <label class="lbl" style="min-width:150px;">Desde
        <input class="fld" type="date" name="desde" value="{{ $filtros['desde'] }}">
    </label>

    <label class="lbl" style="min-width:150px;">Hasta
        <input class="fld" type="date" name="hasta" value="{{ $filtros['hasta'] }}">
    </label>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
        @if (array_filter($filtros))
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.accesos.index') }}">Limpiar</a>
        @endif
    </div>
</form>

<div class="tabla-wrap">
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Correo</th><th>Cuenta</th><th>Panel</th><th>Resultado</th><th>IP</th><th>Dispositivo</th></tr></thead>
        <tbody>
            @forelse ($accesos as $a)
                <tr>
                    <td style="white-space:nowrap;">{{ $a->created_at->locale('es')->isoFormat('D MMM HH:mm') }}</td>
                    <td>{{ $a->email ?? '—' }}</td>
                    <td style="color:var(--gris);font-size:13px;">{{ $a->user?->name ?? '—' }}</td>
                    <td style="color:var(--gris);font-size:13px;">{{ $a->panel }}</td>
                    <td>
                        <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;white-space:nowrap;background:{{ $a->exitoso ? '#eaf6f5' : '#fdeaf0' }};color:{{ $a->exitoso ? '#0d6b64' : '#a82249' }};">
                            {{ $a->resultado_label }}
                        </span>
                    </td>
                    <td style="color:var(--gris);font-size:13px;">{{ $a->ip ?? '—' }}</td>
                    <td style="color:var(--gris);font-size:13px;">{{ $a->dispositivo }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:34px;color:var(--gris);">Todavía no hay accesos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:18px;">{{ $accesos->links() }}</div>
@endsection
