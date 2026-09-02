@extends('layouts.admin')
@section('title', 'Actividades')

@section('content')
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
    <a class="tab {{ $estado === '' ? 'on' : '' }}" href="{{ route('admin.activities.index') }}">
        Todas <span class="count-badge">{{ $conteos->sum() }}</span>
    </a>
    @foreach (\App\Models\Activity::ESTADOS as $clave => $meta)
        <a class="tab {{ $estado === $clave && ! $soloAutomaticas ? 'on' : '' }}" href="{{ route('admin.activities.index', ['estado' => $clave]) }}">
            {{ $meta['filtro'] }} <span class="count-badge">{{ $conteos[$clave] ?? 0 }}</span>
        </a>
    @endforeach

    {{--
        Lo que se publicó sin que nadie lo mirara.

        Es la contrapartida de la aprobación automática: esas actividades no
        pasan por «Estamos revisando», así que sin esta pestaña no hay ningún
        sitio donde encontrarlas. Sólo aparece cuando hay alguna, para no
        dejar una pestaña vacía en un panel que ya tiene seis.
    --}}
    @if ($automaticas > 0)
        <a class="tab {{ $soloAutomaticas ? 'on' : '' }}" href="{{ route('admin.activities.index', ['auto' => 1]) }}"
           title="Se publicaron solas, sin pasar por revisión">
            Publicadas solas <span class="count-badge">{{ $automaticas }}</span>
        </a>
    @endif
</div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;max-width:420px;">
    <input type="hidden" name="estado" value="{{ $estado }}">
    @if ($soloAutomaticas) <input type="hidden" name="auto" value="1"> @endif
    <input class="fld" type="search" name="q" value="{{ \App\Support\Filtro::texto(request(), 'q') }}" placeholder="Buscar por nombre…">
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
                        @if ($a->publicada_automaticamente)
                            <span title="Se publicó sola, sin pasar por revisión"
                                  style="margin-left:5px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:2px 7px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo-600);white-space:nowrap;">Sin revisar</span>
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
