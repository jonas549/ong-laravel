@extends('layouts.admin')
@section('title', 'Log de correos')

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px;">
    <div class="kpi"><span class="v" style="color:var(--turquesa);">{{ $enviados }}</span><span class="l">enviados</span></div>
    <div class="kpi"><span class="v" style="color:{{ $fallidos ? 'var(--rosa)' : 'var(--gris-700)' }};">{{ $fallidos }}</span><span class="l">fallidos</span></div>
</div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <input class="fld" style="max-width:280px;" type="search" name="q" value="{{ request('q') }}" placeholder="Buscar destinatario o asunto…">
    <select class="fld" style="max-width:180px;" name="status">
        <option value="">Todos</option>
        <option value="sent" @selected(request('status') === 'sent')>Enviados</option>
        <option value="failed" @selected(request('status') === 'failed')>Fallidos</option>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
</form>

<div class="tabla-wrap">
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Para</th><th>Asunto</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @forelse ($correos as $c)
                <tr>
                    <td style="white-space:nowrap;">{{ $c->created_at->locale('es')->isoFormat('D MMM HH:mm') }}</td>
                    <td>{{ Str::limit($c->to, 34) }}</td>
                    <td>{{ Str::limit($c->subject, 46) }}</td>
                    <td>
                        <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;white-space:nowrap;background:{{ $c->status === 'sent' ? '#eaf6f5' : '#fdeaf0' }};color:{{ $c->status === 'sent' ? '#0d6b64' : '#a82249' }};">
                            {{ $c->status_label }}
                        </span>
                    </td>
                    <td><a class="btn btn-outline btn-sm" href="{{ route('admin.emails.show', $c) }}">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:var(--gris);">Todavía no se ha enviado ningún correo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:22px;">{{ $correos->links() }}</div>
@endsection
