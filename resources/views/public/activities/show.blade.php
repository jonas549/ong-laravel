@extends('layouts.public')

@section('title', $activity->titulo . ' · ' . config('app.name'))
@section('meta', Str::limit(strip_tags($activity->descripcion), 150))
{{-- Al compartir sale la portada de ESTA actividad, no la genérica del sitio. --}}
@section('imagen', $activity->imagen_url)

@section('content')
<article style="max-width:1180px;margin:0 auto;padding:40px 40px 88px;">

    <a href="{{ route('activities.index') }}" class="textlink" style="font-size:14px;">← Volver a actividades</a>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:44px;margin-top:24px;align-items:start;">
        <div>
            <div style="border-radius:24px;overflow:hidden;aspect-ratio:16/10;background:var(--gris-100);">
                <img loading="lazy" decoding="async" src="{{ $activity->imagen_url }}" alt="{{ $activity->titulo }}" style="width:100%;height:100%;object-fit:cover;display:block;">
            </div>

            <h1 style="font-weight:800;font-size:36px;line-height:1.12;margin:26px 0 14px;letter-spacing:-.02em;">{{ $activity->titulo }}</h1>

            {{--
                Quién organiza, pegado al título y con peso propio.

                Antes era una línea más de la ficha lateral, en gris y sin logo,
                y el cliente pidió lo contrario: que se vea de quién es la
                actividad al mismo tiempo que se lee de qué va.
            --}}
            @php
                $org = $activity->organization;

                // Los dos enlaces viven sólo en la organización: son el dato
                // de la entidad y no de cada actividad. Ver 2025_01_12_000001.
                $web = $org->enlace_web;
                $red = $org->enlace_red_social;
            @endphp
            <div class="org-firma">
                @if ($org->logo_url)
                    <img loading="lazy" decoding="async" class="org-logo" src="{{ $org->logo_url }}" alt="Logo de {{ $org->nombre }}">
                @else
                    {{--
                        Sin logo van las iniciales. Un hueco vacío junto al nombre
                        se lee como una imagen que no cargó, y hoy ninguna de las
                        organizaciones de la base tiene logo.
                    --}}
                    <span class="org-logo org-logo--iniciales" aria-hidden="true">{{ $org->iniciales }}</span>
                @endif

                <div class="org-datos">
                    <span class="seclabel">Organiza</span>
                    <span class="org-nombre dato-editable">{{ $org->nombre }}</span>

                    @if ($web || $red)
                        {{--
                            `nofollow ugc`: son direcciones que escribe cualquiera
                            desde el wizard, así que no reparten autoridad ni
                            responden de a dónde llevan.
                        --}}
                        <span class="org-enlaces">
                            @if ($web)
                                <a href="{{ $web }}" target="_blank" rel="noopener nofollow ugc">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                    Sitio web
                                </a>
                            @endif

                            @if ($red)
                                <a href="{{ $red }}" target="_blank" rel="noopener nofollow ugc">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                                    {{ \App\Support\RedSocial::nombre($red) }}
                                </a>
                            @endif
                        </span>
                    @endif
                </div>
            </div>

            <p style="font-size:16px;line-height:1.7;color:var(--gris-700);white-space:pre-line;margin:0 0 24px;">{{ $activity->descripcion }}</p>

            @foreach (['tema' => 'Temas', 'caracteristica' => 'Características', 'publico' => 'Dirigida a', 'acceso' => 'Accesibilidad'] as $grupo => $label)
                @php $items = $activity->termsDe($grupo); @endphp
                @if ($items->isNotEmpty())
                    <div style="margin-bottom:18px;">
                        <div class="seclabel" style="margin-bottom:8px;">{{ $label }}</div>
                        <div style="display:flex;flex-wrap:wrap;gap:7px;">
                            @foreach ($items as $t)
                                <span style="font-size:12.5px;font-weight:600;padding:6px 12px;border-radius:999px;background:var(--gris-100);color:var(--gris-700);">{{ $t->nombre }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            @if ($activity->info_previa)
                <div class="alert alert-info" style="margin-top:22px;">
                    <strong>Antes de asistir:</strong> {{ $activity->info_previa }}
                </div>
            @endif

            @if ($activity->collaborators->isNotEmpty())
                <div style="margin-top:26px;">
                    <div class="seclabel" style="margin-bottom:8px;">En colaboración con</div>
                    <p style="margin:0;font-size:15px;color:var(--gris-700);">{{ $activity->collaborators->pluck('nombre')->implode(' · ') }}</p>
                </div>
            @endif

            @include('public.partials.compartir', ['activity' => $activity])
        </div>

        <aside style="position:sticky;top:100px;">
            <div class="card" style="padding:26px;">
                <div style="display:flex;flex-direction:column;gap:14px;font-size:15px;">
                    <div>
                        <div class="helper" style="font-weight:700;">Cuándo</div>
                        <div style="color:var(--gris-700);">{{ $activity->fecha_larga }}</div>
                        @if ($activity->hora_inicio)
                            <div style="color:var(--gris);font-size:14px;">
                                {{ substr($activity->hora_inicio, 0, 5) }}@if ($activity->hora_termino) – {{ substr($activity->hora_termino, 0, 5) }}@endif
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="helper" style="font-weight:700;">Dónde</div>
                        <div style="color:var(--gris-700);">{{ $activity->lugar }}</div>
                        @if ($activity->direccion)
                            <div style="color:var(--gris);font-size:14px;">{{ $activity->direccion }}</div>
                        @endif
                    </div>

                    <div>
                        <div class="helper" style="font-weight:700;">Formato</div>
                        <div style="color:var(--gris-700);">{{ $activity->formato }}</div>
                    </div>

                    @if ($activity->cupos_disponibles !== null)
                        <div>
                            <div class="helper" style="font-weight:700;">Cupos disponibles</div>
                            <div style="color:var(--gris-700);font-variant-numeric:tabular-nums;">{{ $activity->cupos_disponibles }}</div>
                        </div>
                    @endif
                </div>

                @if ($activity->puedeRecibirInscripciones())
                    <hr style="border:0;border-top:1px solid var(--linea);margin:22px 0;">
                    @include('public.partials.registration-form', ['activity' => $activity])
                @else
                    <div class="alert alert-info" style="margin-top:20px;">Esta actividad no está recibiendo inscripciones.</div>
                @endif
            </div>
        </aside>
    </div>

    @if ($relacionadas->isNotEmpty())
        <section style="margin-top:72px;">
            <h2 style="font-weight:800;font-size:26px;margin:0 0 22px;">Otras actividades cerca</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;">
                @foreach ($relacionadas as $act)
                    @include('public.partials.activity-card', ['act' => $act])
                @endforeach
            </div>
        </section>
    @endif
</article>
@endsection
