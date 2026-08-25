@extends('layouts.admin')
@section('title', 'Registro de correos')

@section('actions')
    <a class="btn btn-outline btn-sm" href="{{ route('admin.templates.index') }}">Plantillas de correo</a>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px;">
    <div class="kpi"><span class="v" style="color:var(--turquesa);">{{ $enviados }}</span><span class="l">enviados</span></div>
    <div class="kpi"><span class="v" style="color:{{ $fallidos ? 'var(--rosa)' : 'var(--gris-700)' }};">{{ $fallidos }}</span><span class="l">fallidos</span></div>
</div>

<form method="GET" class="card" style="padding:18px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <label class="lbl" style="flex:1;min-width:220px;">Buscar
        <input class="fld" type="search" name="q" value="{{ $filtros['q'] }}" placeholder="Destinatario, asunto o texto…">
    </label>

    <label class="lbl" style="min-width:150px;">Estado
        <select class="fld" name="status">
            <option value="">Todos</option>
            <option value="sent" @selected($filtros['status'] === 'sent')>Enviados</option>
            <option value="failed" @selected($filtros['status'] === 'failed')>Fallidos</option>
        </select>
    </label>

    <label class="lbl" style="min-width:190px;">Tipo
        <select class="fld" name="plantilla">
            <option value="">Todos</option>
            @foreach ($plantillas as $clave => $nombre)
                <option value="{{ $clave }}" @selected($filtros['plantilla'] === $clave)>{{ $nombre }}</option>
            @endforeach
            {{-- Recuperación de contraseña, avisos de moderación y demás: no
                 salen de una plantilla y antes ningún filtro los encontraba. --}}
            <option value="sin_plantilla" @selected($filtros['plantilla'] === 'sin_plantilla')>Otros (sin plantilla)</option>
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
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.emails.index') }}">Limpiar</a>
        @endif
    </div>
</form>

<div class="tabla-wrap">
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Para</th><th>Asunto</th><th>Tipo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @forelse ($correos as $c)
                <tr>
                    <td style="white-space:nowrap;">{{ $c->created_at->locale('es')->isoFormat('D MMM HH:mm') }}</td>
                    <td>{{ Str::limit($c->to, 30) }}</td>
                    <td>{{ Str::limit($c->subject, 40) }}</td>
                    <td style="color:var(--gris);font-size:13px;">{{ $plantillas[$c->plantilla] ?? '—' }}</td>
                    <td>
                        <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;white-space:nowrap;background:{{ $c->status === 'sent' ? '#eaf6f5' : '#fdeaf0' }};color:{{ $c->status === 'sent' ? '#0d6b64' : '#a82249' }};">
                            {{ $c->status_label }}
                        </span>
                        @if ($c->reenviado_at)
                            <div class="helper" style="margin-top:4px;">Reenviado</div>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        <a class="btn btn-outline btn-sm" href="{{ route('admin.emails.show', $c) }}">Ver</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="color:var(--gris);">
                    {{ array_filter($filtros) ? 'Ningún correo coincide con el filtro.' : 'Todavía no se ha enviado ningún correo.' }}
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:22px;">{{ $correos->links() }}</div>
@endsection
