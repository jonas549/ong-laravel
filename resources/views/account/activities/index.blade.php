@extends('layouts.account')
@section('title', 'Mis actividades')

@section('content')
<div style="display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px;">
    <div>
        <h1 style="font-weight:800;font-size:30px;margin:0 0 6px;letter-spacing:-.01em;">Mis actividades</h1>
        <p class="helper" style="margin:0;">Gestiona lo que publicaste y revisa quién se inscribió.</p>
    </div>
    <a href="{{ route('publish.create') }}" class="btn btn-primary">Nueva actividad</a>
</div>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:26px;">
    @foreach ($filtros as $label => $n)
        <a class="tab {{ $filtroActivo === $label ? 'on' : '' }}"
           href="{{ route('account.activities.index', $label === 'Todas' ? [] : ['filtro' => $label]) }}">
            {{ $label }} <span class="count-badge">{{ $n }}</span>
        </a>
    @endforeach
</div>

@if ($actividades->isEmpty())
    <div class="card" style="padding:44px;text-align:center;">
        <p style="font-size:16px;color:var(--gris);margin:0 0 18px;">No hay actividades en este filtro.</p>
        <a href="{{ route('publish.create') }}" class="btn btn-outline">Publicar una actividad</a>
    </div>
@else
    <div style="display:flex;flex-direction:column;gap:14px;">
        @foreach ($actividades as $a)
            @php $tono = $a->estado_color; @endphp
            <article class="card actcard" style="padding:22px 24px;display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center;{{ $a->estado === 'cancelada' ? 'opacity:.55;' : '' }}">
                <div style="min-width:0;">
                    <div style="display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;padding:5px 12px;border-radius:999px;margin-bottom:10px;background:{{ $tono['bg'] }};color:{{ $tono['ink'] }};border:1px solid {{ $tono['borde'] }};">
                        <span style="width:7px;height:7px;border-radius:50%;background:{{ $tono['tono'] }};"></span>
                        {{ $a->estado_label }}
                    </div>

                    <h2 style="font-weight:700;font-size:19px;margin:0 0 8px;letter-spacing:-.01em;">{{ $a->titulo }}</h2>

                    <div style="display:flex;flex-wrap:wrap;gap:6px 18px;font-size:13.5px;color:var(--gris);">
                        <span>{{ $a->fecha_larga }}</span>
                        <span>{{ $a->lugar }}</span>
                        @if ($a->estado === 'publicada')
                            <span style="font-weight:600;color:var(--gris-700);">{{ $a->inscritos }} inscrito{{ $a->inscritos === 1 ? '' : 's' }}</span>
                        @endif
                    </div>

                    @if ($a->estado === 'ajustes' && $a->observaciones_revision)
                        <div class="alert alert-error" style="margin-top:12px;font-size:13.5px;">
                            <strong>Qué ajustar:</strong> {{ $a->observaciones_revision }}
                        </div>
                    @endif
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                    @if ($a->estado === 'publicada')
                        <a href="{{ route('account.participants.index', $a) }}" class="btn btn-outline btn-sm">Ver inscritos</a>
                        <a href="{{ route('activities.show', $a) }}" class="btn btn-ghost btn-sm">Ver publicada</a>
                    @endif

                    @if (in_array($a->estado, ['borrador', 'ajustes'], true))
                        <form method="POST" action="{{ route('account.activities.submit', $a) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">Enviar a revisión</button>
                        </form>
                    @endif

                    @if ($a->estado !== 'cancelada')
                        <form method="POST" action="{{ route('account.activities.cancel', $a) }}"
                              onsubmit="return confirm('¿Seguro que quieres cancelar esta actividad? Dejará de aparecer en el calendario.');">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">Cancelar</button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection
