@extends('layouts.public')

@section('title', 'Actividades · ' . config('app.name'))

@section('content')
{{--
    Dos vistas de lo mismo: el listado de siempre y el calendario mes a mes.

    La cabecera, los filtros y el conmutador están una sola vez y valen para las
    dos; lo que cambia va en su parcial. Los filtros no se duplican porque
    tampoco se duplica la consulta que hay detrás: ver `ActivityController`.
--}}
@php
    $esCalendario = $vista === 'calendario';

    // Lo que hay ahora en la URL, para que el conmutador y «Limpiar» no se
    // lleven por delante los filtros puestos.
    $puestos = array_filter(
        request()->only(['region', 'comuna', 'tema', 'formato']),
        fn ($v) => $v !== null && $v !== '',
    );
@endphp

<section style="max-width:1180px;margin:0 auto;padding:56px 40px 88px;">
    <div style="margin-bottom:34px;">
        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:12px;">Calendario</div>
        <h1 style="font-weight:800;font-size:40px;line-height:1.1;margin:0 0 12px;letter-spacing:-.02em;">Actividades solidarias</h1>

        @if ($esCalendario)
            <p style="font-size:16px;color:var(--gris);margin:0;">{{ $calendario->total }} {{ \App\Support\Texto::plural('actividad', $calendario->total) }} en {{ Str::lower($calendario->titulo()) }}.</p>
        @else
            <p style="font-size:16px;color:var(--gris);margin:0;">{{ $actividades->total() }} {{ \App\Support\Texto::plural('actividad', $actividades->total()) }} {{ \App\Support\Texto::plural('publicada', $actividades->total()) }}.</p>
        @endif
    </div>

    <form method="GET" class="card" style="padding:20px 22px;margin-bottom:22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end;">
        {{-- Filtrar no cambia de vista ni de mes: se sigue donde se estaba. --}}
        @if ($esCalendario)
            <input type="hidden" name="vista" value="calendario">
            <input type="hidden" name="mes" value="{{ $calendario->mes->format('Y-m') }}">
        @endif

        <div>
            <label class="helper" for="f-region" style="display:block;margin-bottom:6px;font-weight:600;">Región</label>
            <select class="fld" name="region" id="f-region" onchange="this.form.submit()">
                <option value="">Todas</option>
                @foreach ($regiones as $r)
                    <option value="{{ $r->id }}" @selected(request('region') == $r->id)>{{ $r->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="helper" for="f-comuna" style="display:block;margin-bottom:6px;font-weight:600;">Comuna</label>
            <select class="fld" name="comuna" id="f-comuna" @disabled($comunas->isEmpty())>
                <option value="">{{ $comunas->isEmpty() ? 'Elige una región' : 'Todas' }}</option>
                @foreach ($comunas as $c)
                    <option value="{{ $c->id }}" @selected(request('comuna') == $c->id)>{{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="helper" for="f-tema" style="display:block;margin-bottom:6px;font-weight:600;">Tema</label>
            <select class="fld" name="tema" id="f-tema">
                <option value="">Todos</option>
                @foreach ($temas as $t)
                    <option value="{{ $t->id }}" @selected(request('tema') == $t->id)>{{ $t->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="helper" for="f-formato" style="display:block;margin-bottom:6px;font-weight:600;">Formato</label>
            <select class="fld" name="formato" id="f-formato">
                <option value="">Todos</option>
                @foreach ($formatos as $f)
                    <option value="{{ $f }}" @selected(request('formato') === $f)>{{ $f }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <a href="{{ route('activities.index', $esCalendario ? ['vista' => 'calendario'] : []) }}" class="btn btn-ghost btn-sm">Limpiar</a>
        </div>
    </form>

    {{--
        El conmutador conserva los filtros y suelta lo que sólo vale en una
        vista: la página al pasar a calendario, el mes al volver a la lista.
    --}}
    <div class="vista-conmutador" role="group" aria-label="Cómo ver las actividades">
        <a href="{{ route('activities.index', $puestos) }}"
           @class(['vista-btn', 'vista-btn--activo' => ! $esCalendario])
           @if (! $esCalendario) aria-current="page" @endif>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
            Lista
        </a>

        <a href="{{ route('activities.index', $puestos + ['vista' => 'calendario']) }}"
           @class(['vista-btn', 'vista-btn--activo' => $esCalendario])
           @if ($esCalendario) aria-current="page" @endif>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
            Calendario
        </a>
    </div>

    @if ($esCalendario)
        @include('public.activities.partials.calendario')
    @else
        @include('public.activities.partials.lista')
    @endif
</section>
@endsection
