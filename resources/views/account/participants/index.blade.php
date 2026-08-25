@extends('layouts.public')
@section('title', 'Participantes inscritos · ' . config('app.name'))

{{-- mi-cuenta.html lleva el footer compacto. --}}
@php $footerCompacto = true; @endphp

@section('content')

<main style="flex:1;">
@php
    $inscritosActivos = $activity->inscritos_count;

    $metricas = [
        ['valor' => $inscritosActivos, 'label' => 'inscritos', 'color' => 'var(--naranjo)'],
        ['valor' => $activity->cupos_totales ?? '—', 'label' => 'cupos totales', 'color' => 'var(--teal)'],
        ['valor' => $activity->cupos_disponibles ?? '—', 'label' => 'cupos disponibles', 'color' => 'var(--turquesa)'],
    ];

    $filtroActual = ['q' => $busqueda, 'estado' => $estado];
@endphp

{{-- PANTALLA 4 — PARTICIPANTES de mi-cuenta.html --}}
<div class="rise" style="max-width:1080px;margin:0 auto;padding:34px 32px 96px;">

    <div class="crumb" style="margin-bottom:20px;">Mi cuenta → <a href="{{ route('account.activities.index') }}">Mis actividades</a> → Participantes</div>

    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:26px;">
        <div>
            <a href="{{ route('account.activities.index') }}" class="btn btn-outline btn-sm" style="margin-bottom:16px;">← Volver a mis actividades</a>
            <h1 style="font-size:38px;font-weight:800;letter-spacing:-.02em;line-height:1.1;margin:0 0 8px;color:var(--ink);">Participantes inscritos</h1>
            <p style="font-size:16px;color:var(--gris);margin:0;">{{ $activity->titulo }} · {{ $activity->fecha_corta }} · {{ $activity->commune?->nombre ?? 'Por definir' }}</p>
        </div>

        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
            <a href="{{ route('account.participants.export', [$activity] + $filtroActual) }}" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>
                Exportar lista
            </a>
            <span class="helper">Exporta en formato Excel (.xlsx)</span>
        </div>
    </div>

    <div class="grid-3" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px;">
        @foreach ($metricas as $m)
            <div class="card" style="padding:22px 24px;">
                <div style="font-family:var(--font-title);font-size:38px;font-weight:800;line-height:1;color:{{ $m['color'] }};margin-bottom:6px;font-variant-numeric:tabular-nums;">{{ $m['valor'] }}</div>
                <div style="font-size:14px;color:var(--gris);">{{ $m['label'] }}</div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('account.participants.cupos', $activity) }}"
          class="card" style="padding:24px;margin-bottom:18px;display:flex;align-items:flex-end;gap:18px;flex-wrap:wrap;">
        @csrf
        @method('PATCH')

        <label class="lbl" style="max-width:200px;">Editar cupos disponibles
            <input class="fld @error('cupos_disponibles') is-invalid @enderror" name="cupos_disponibles"
                   inputmode="numeric" value="{{ old('cupos_disponibles', $activity->cupos_disponibles) }}">
            @error('cupos_disponibles') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div class="helper" style="flex:1;min-width:240px;max-width:56ch;">El número que definas aquí se refleja en la ficha pública de la actividad. Puedes ajustarlo manualmente para reflejar inscripciones recibidas por canales externos al sitio web.</div>

        <button type="submit" class="btn btn-outline btn-sm">Guardar cupos</button>
    </form>

    <div class="card" style="padding:24px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
            <input class="fld" style="flex:1;min-width:240px;" type="search" name="q"
                   value="{{ $busqueda }}" placeholder="Buscar por nombre o correo">

            <select class="fld" style="width:auto;min-width:180px;" name="estado" x-on:change="$el.form.submit()">
                <option value="">Todos los estados</option>
                @foreach ($estados as $e)
                    <option value="{{ $e }}" @selected($estado === $e)>{{ ucfirst($e) }}</option>
                @endforeach
            </select>

            @if ($verTodos)
                <input type="hidden" name="todos" value="1">
            @endif

            {{-- El prototipo no dibuja botón: el select se envía solo y el
                 buscador con Enter. Éste queda para quien navegue con teclado. --}}
            <button type="submit" class="btn btn-outline btn-sm">Buscar</button>
        </form>

        @if ($inscritos->isEmpty())
            <p style="color:var(--gris);margin:0;padding:20px 0;text-align:center;">
                {{ $busqueda || $estado ? 'No encontramos inscritos con ese filtro.' : 'Todavía no hay personas inscritas.' }}
            </p>
        @else
            <div style="overflow-x:auto;">
                <table class="plist">
                    <thead>
                        <tr><th>Nombre</th><th>Correo</th><th>Fecha inscripción</th><th>Mayor de edad</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($inscritos as $p)
                            @php $c = $p->estado_color; @endphp
                            <tr>
                                <td style="font-weight:600;color:var(--ink);">{{ $p->nombre }}</td>
                                <td>{{ $p->correo }}</td>
                                <td>{{ $p->created_at->locale('es')->isoFormat('D MMM YYYY') }}</td>
                                <td>{{ $p->es_mayor_edad ? 'Sí' : 'No' }}</td>
                                <td><span style="font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:999px;background:{{ $c['bg'] }};color:{{ $c['ink'] }};">{{ $p->estado_label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:20px;">
                <span class="helper">Mostrando {{ $inscritos->count() }} de {{ $total }} {{ Str::plural('participante', $total) }}</span>

                @if (! $verTodos && $total > $inscritos->count())
                    <a href="{{ route('account.participants.index', [$activity] + $filtroActual + ['todos' => 1]) }}"
                       class="btn btn-outline btn-sm">Ver todos</a>
                @endif
            </div>
        @endif
    </div>
</div>
</main>
@endsection
