@extends('layouts.admin')
@section('title', 'Registro de accesos')

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:14px;">
    <div class="kpi"><span class="v" style="color:var(--turquesa);">{{ $exitos24h }}</span><span class="l">entradas · 24 h</span></div>
    <div class="kpi"><span class="v" style="color:{{ $fallos24h ? 'var(--rosa)' : 'var(--gris-700)' }};">{{ $fallos24h }}</span><span class="l">intentos fallidos · 24 h</span></div>
    <div class="kpi"><span class="v">{{ $intentos }}</span><span class="l">intentos antes de bloquear</span></div>
</div>

{{-- La regla vigente, dicha en voz alta. Antes había que suponerla, y suponerla
     mal es justo lo que hace parecer que el bloqueo no funciona. --}}
<p class="helper" style="margin:0 0 22px;max-width:75ch;">
    Ahora mismo se bloquea tras <strong>{{ $intentos }}</strong>
    {{ $intentos === 1 ? 'intento fallido' : 'intentos fallidos' }} seguidos con el mismo correo desde la
    misma IP, durante <strong>{{ $minutosBloqueo }}</strong> minutos. Con menos fallos que eso, entrar con
    la contraseña correcta es el comportamiento esperado.
    Se cambia en <a class="textlink" href="{{ route('admin.settings.general') }}">Configuración → General</a>.
</p>

@if ($sospechosos->isNotEmpty())
    {{-- Lo que de verdad se mira: correos con varios fallos seguidos. --}}
    <div class="card" style="padding:22px;margin-bottom:20px;border-left:4px solid var(--rosa);">
        <div class="seclabel" style="margin-bottom:6px;">Intentos repetidos en las últimas 24 h</div>
        <p class="helper" style="margin:0 0 14px;max-width:70ch;">
            Tres o más fallos desde la misma IP con el mismo correo. A los {{ $intentos }},
            la combinación queda bloqueada {{ $minutosBloqueo }} minutos.
            La columna «Acumulados» es lo que cuenta para el bloqueo: los fallos posteriores
            a la última entrada correcta.
        </p>

        <div class="tabla-wrap">
            <table class="tabla">
                <thead><tr><th>Correo</th><th>Panel</th><th>IP</th><th>Fallos 24 h</th><th>Acumulados</th><th>Último</th><th></th></tr></thead>
                <tbody>
                    @foreach ($sospechosos as $s)
                        <tr>
                            <td style="font-weight:600;">{{ $s->email }}</td>
                            <td>{{ $s->panel }}</td>
                            <td>{{ $s->ip ?? '—' }}</td>
                            <td>{{ $s->intentos }}</td>
                            <td style="font-weight:600;color:{{ $s->acumulados >= $intentos ? 'var(--rosa)' : 'inherit' }};">
                                {{ $s->acumulados }} / {{ $intentos }}
                            </td>
                            <td style="white-space:nowrap;">{{ \App\Support\Fecha::relativa($s->ultimo) }}</td>
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
        <thead><tr><th>Fecha</th><th>Correo</th><th>Cuenta</th><th>Panel</th><th>Resultado</th><th>Lo hizo</th><th>IP</th><th>Dispositivo</th></tr></thead>
        <tbody>
            @forelse ($accesos as $a)
                <tr>
                    <td style="white-space:nowrap;">{{ \App\Support\Fecha::diaYHora($a->created_at) }}</td>
                    <td>{{ $a->email ?? '—' }}</td>
                    <td style="color:var(--gris);font-size:13px;">{{ $a->user?->name ?? '—' }}</td>
                    <td style="color:var(--gris);font-size:13px;">{{ $a->panel }}</td>
                    <td>
                        <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;white-space:nowrap;background:{{ $a->colorFondo }};color:{{ $a->colorTexto }};">
                            {{ $a->resultado_label }}
                        </span>
                    </td>
                    {{-- Quién lo hizo, cuando no fue el titular: levantar un
                         bloqueo o cambiar una contraseña ajena. --}}
                    <td style="color:var(--gris);font-size:13px;">{{ $a->actor?->name ?? '—' }}</td>
                    <td style="color:var(--gris);font-size:13px;">{{ $a->ip ?? '—' }}</td>
                    <td style="color:var(--gris);font-size:13px;">{{ $a->dispositivo }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:34px;color:var(--gris);">Todavía no hay accesos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:18px;">{{ $accesos->links() }}</div>
@endsection
