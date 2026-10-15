@extends('layouts.public')

@section('title', 'Voluntariados · ' . config('app.name'))
@section('meta', 'Oportunidades de voluntariado de Voluntariados Chile para el Día del Patrimonio Social.')
{{-- Con la sección apagada, que no la indexe nadie. Ver Voluntariado::seccionVisible(). --}}
@unless (\App\Models\Voluntariado::seccionVisible())
    @section('noindex', '1')
@endunless

@section('content')
{{--
    Las oportunidades de Voluntariados Chile (13/10), con la misma pantalla que
    /actividades (15/10): cabecera, barra de filtros, conmutador lista /
    calendario, tarjetas y paginación. El calendario es el mismo parcial que el
    de actividades, con la casilla y la franja de los voluntariados; las de
    convocatoria permanente van a la franja de arriba.

    Los filtros son sólo los que respalda un campo de la API —búsqueda, región
    y formato—; ver `VoluntariadoController`.
--}}
@php
    $esCalendario = $vista === 'calendario';

    // Lo que hay ahora en la URL, para que el conmutador y «Limpiar» no se
    // lleven por delante los filtros puestos.
    $puestos = array_filter(
        request()->only(['q', 'region', 'formato']),
        fn ($v) => $v !== null && $v !== '',
    );
@endphp

<section style="max-width:1180px;margin:0 auto;padding:56px 40px 88px;">
    <div style="margin-bottom:34px;">
        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:12px;">Voluntariados Chile</div>
        <h1 style="font-weight:800;font-size:40px;line-height:1.1;margin:0 0 12px;letter-spacing:-.02em;">Oportunidades de voluntariado</h1>

        @if ($esCalendario)
            <p style="font-size:16px;color:var(--gris);margin:0;">{{ $calendario->total }} {{ \App\Support\Texto::plural('voluntariado', $calendario->total) }} con fecha en {{ Str::lower($calendario->titulo()) }}.</p>
        @else
            <p style="font-size:16px;color:var(--gris);margin:0;">{{ $voluntariados->total() }} {{ \App\Support\Texto::plural('voluntariado', $voluntariados->total()) }} {{ \App\Support\Texto::plural('disponible', $voluntariados->total()) }}. Se postula en el sitio de Voluntariados Chile.</p>
        @endif
    </div>

    <form method="GET" class="card" data-filtros-voluntariados style="padding:20px 22px;margin-bottom:22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end;">
        {{-- Filtrar no cambia de vista ni de mes: se sigue donde se estaba. --}}
        @if ($esCalendario)
            <input type="hidden" name="vista" value="calendario">
            <input type="hidden" name="mes" value="{{ $calendario->mes->format('Y-m') }}">
        @endif

        <div style="grid-column:1 / -1;">
            <label class="helper" for="f-q" style="display:block;margin-bottom:6px;font-weight:600;">Buscar</label>
            <div style="position:relative;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                     style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gris);pointer-events:none;"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input class="fld" type="search" name="q" id="f-q" value="{{ request('q') }}" maxlength="100"
                       placeholder="Nombre del voluntariado u organización" style="padding-left:42px;">
            </div>
        </div>

        <div>
            <label class="helper" for="f-region" style="display:block;margin-bottom:6px;font-weight:600;">Región</label>
            <select class="fld" name="region" id="f-region">
                <option value="">Todas</option>
                @foreach ($regiones as $r)
                    <option value="{{ $r->id }}" @selected(request('region') == $r->id)>{{ $r->nombre }}</option>
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
            <a href="{{ route('voluntariados.index', $esCalendario ? ['vista' => 'calendario'] : []) }}" class="btn btn-ghost btn-sm">Limpiar</a>
        </div>
    </form>

    {{--
        El conmutador conserva los filtros y suelta lo que sólo vale en una
        vista: la página al pasar a calendario, el mes al volver a la lista.
    --}}
    <div class="vista-conmutador" role="group" aria-label="Cómo ver los voluntariados">
        <a href="{{ route('voluntariados.index', $puestos) }}"
           @class(['vista-btn', 'vista-btn--activo' => ! $esCalendario])
           @if (! $esCalendario) aria-current="page" @endif>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
            Lista
        </a>

        <a href="{{ route('voluntariados.index', $puestos + ['vista' => 'calendario']) }}"
           @class(['vista-btn', 'vista-btn--activo' => $esCalendario])
           @if ($esCalendario) aria-current="page" @endif>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
            Calendario
        </a>
    </div>

    @if ($esCalendario)
        @include('public.activities.partials.calendario', [
            'rutaListado' => 'voluntariados.index',
            'parcialCasilla' => 'public.voluntariados.partials.cal-voluntariado',
            'parcialSuelta' => 'public.voluntariados.partials.cal-suelta',
            'nombrePlural' => 'voluntariados',
            'textoPermanentes' => ['Convocatoria permanente', 'Sin fecha concreta: puedes postular cuando quieras.'],
        ])
    @else
        @include('public.voluntariados.partials.lista')
    @endif

    {{-- El camino a todo su catálogo, que es más que lo que comparten con el DPS. --}}
    <p style="margin:40px 0 0;font-size:15px;">
        <a class="textlink" href="https://voluntariadoschile.cl/oportunidades" target="_blank" rel="noopener" data-todos-vch>
            Ver todos los voluntariados en Voluntariados Chile →
        </a>
    </p>
</section>
@endsection
