@extends('layouts.admin')
@section('title', 'Resumen')

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:26px;">
    <div class="kpi"><span class="v" style="color:var(--naranjo);">{{ $pendientes }}</span><span class="l">esperando revisión</span></div>
    <div class="kpi"><span class="v">{{ $totalActividades }}</span><span class="l">actividades en total</span></div>
    <div class="kpi"><span class="v" style="color:var(--teal);">{{ $organizaciones }}</span><span class="l">organizaciones</span></div>
    <div class="kpi"><span class="v" style="color:var(--turquesa);">{{ $inscritos }}</span><span class="l">inscritos</span></div>
    <div class="kpi">
        <span class="v" style="color:{{ $correosFallidos ? 'var(--rosa)' : 'var(--gris-700)' }};">{{ $correosFallidos }}</span>
        <span class="l">correos fallidos</span>
    </div>
</div>

@if ($pendientes > 0)
    <div class="alert alert-info" style="margin-bottom:22px;">
        Hay {{ $pendientes }} {{ Str::plural('actividad', $pendientes) }} esperando revisión.
        <a class="textlink" href="{{ route('admin.activities.index', ['estado' => 'revision']) }}">Revisar ahora</a>
    </div>
@endif

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;">
    <section class="card" style="padding:22px 24px;">
        <h2 style="font-size:16px;font-weight:700;margin:0 0 16px;">Actividades por estado</h2>
        <div style="display:flex;flex-direction:column;gap:10px;">
            @foreach (\App\Models\Activity::ESTADOS as $clave => $meta)
                @php $n = $porEstado[$clave] ?? 0; @endphp
                <a href="{{ route('admin.activities.index', ['estado' => $clave]) }}"
                   style="display:flex;align-items:center;gap:12px;font-size:14px;color:var(--gris-700);">
                    <span style="width:9px;height:9px;border-radius:50%;background:{{ $meta['tono'] }};flex:none;"></span>
                    <span style="flex:1;">{{ $meta['filtro'] }}</span>
                    <strong style="font-variant-numeric:tabular-nums;">{{ $n }}</strong>
                </a>
            @endforeach
        </div>
    </section>

    <section class="card" style="padding:22px 24px;grid-column:span 2;min-width:0;">
        <h2 style="font-size:16px;font-weight:700;margin:0 0 16px;">Movimiento reciente</h2>
        <div class="tabla-wrap" style="border:0;">
            <table class="tabla">
                <thead><tr><th>Actividad</th><th>Organización</th><th>Estado</th><th>Actualizada</th></tr></thead>
                <tbody>
                    @forelse ($ultimas as $a)
                        @php $t = $a->estado_color; @endphp
                        <tr>
                            <td><a class="textlink" href="{{ route('admin.activities.show', $a) }}">{{ Str::limit($a->titulo, 42) }}</a></td>
                            <td>{{ $a->organization?->nombre }}</td>
                            <td>
                                <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:{{ $t['bg'] }};color:{{ $t['ink'] }};">
                                    {{ $a->estado_filtro }}
                                </span>
                            </td>
                            <td style="white-space:nowrap;">{{ $a->updated_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="color:var(--gris);">Todavía no hay actividades.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<p class="helper" style="margin-top:26px;">
    El diseño definitivo del panel se define más adelante: por ahora esta pantalla prioriza que todo sea operable.
</p>
@endsection
