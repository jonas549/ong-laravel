@extends('layouts.public')
@section('title', 'Evaluaciones de mis actividades · ' . config('app.name'))

{{-- mi-cuenta.html lleva el footer compacto. --}}
@php $footerCompacto = true; @endphp

{{--
    Lo que contestaron los asistentes, para el organizador.

    Es la misma información que ve la ONG en su panel, acotada a las actividades
    de esta organización. Lo que NO se enseña aquí es el correo de quien
    respondió: el organizador necesita saber qué le dijeron, no a quién
    escribirle. Quien quiera contactar a un asistente tiene la lista de
    inscritos, que es donde esa persona sí dejó sus datos para eso.
--}}

@section('content')

<main style="flex:1;">
<div class="rise" style="max-width:1080px;margin:0 auto;padding:34px 32px 96px;">

    <x-cuenta.barra>
        <a href="{{ route('home') }}">Inicio</a> →
        <a href="{{ route('account.activities.index') }}">Mi cuenta</a> → Evaluaciones
    </x-cuenta.barra>

    <h1 style="font-family:var(--font-title);font-size:34px;font-weight:800;letter-spacing:-.02em;margin:0 0 8px;color:var(--ink);">
        Evaluaciones de tus actividades
    </h1>
    <p style="font-size:15.5px;color:var(--gris);margin:0 0 28px;max-width:62ch;">
        Lo que respondieron quienes escanearon el código QR de tus actividades.
    </p>

    @if ($actividades->isEmpty())
        <section class="card" style="padding:34px;text-align:center;color:var(--gris);">
            Todavía no tienes actividades, así que no hay evaluaciones que mostrar.
        </section>
    @else
        {{-- ── Los promedios, de lo que esté filtrado ── --}}
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
            @foreach ($escalas as $clave => $escala)
                @php $r = $resumen[$clave]; @endphp
                <section class="card" style="padding:20px 24px;flex:1;min-width:250px;">
                    <p class="helper" style="margin:0 0 6px;">{{ $clave === 'experiencia' ? 'Experiencia' : 'Motivación para volver' }}</p>
                    <p style="font-size:30px;font-weight:800;color:var(--ink);margin:0;font-variant-numeric:tabular-nums;">
                        {{ $r['promedio'] !== null ? number_format($r['promedio'], 2, ',', '.') : '—' }}
                        <span style="font-size:15px;font-weight:600;color:var(--gris);">/ 5</span>
                    </p>
                    <p class="helper" style="margin:4px 0 0;">
                        {{ $r['total'] }} {{ $r['total'] === 1 ? 'respuesta' : 'respuestas' }}
                    </p>
                </section>
            @endforeach
        </div>

        {{-- ── Filtros ── --}}
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:22px;">
            <label class="lbl" style="max-width:340px;margin:0;">Actividad
                <select class="fld" name="actividad" onchange="this.form.submit()">
                    <option value="">Todas mis actividades</option>
                    @foreach ($actividades as $id => $titulo)
                        <option value="{{ $id }}" @selected((string) $filtros['actividad'] === (string) $id)>
                            #{{ $id }} · {{ Str::limit($titulo, 44) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="lbl" style="max-width:220px;margin:0;">Fotografías
                <select class="fld" name="foto" onchange="this.form.submit()">
                    <option value="">Todas las respuestas</option>
                    <option value="con" @selected($filtros['foto'] === 'con')>Sólo con fotografía</option>
                </select>
            </label>

            <noscript><button type="submit" class="btn btn-outline btn-sm">Filtrar</button></noscript>
        </form>

        @if ($evaluaciones->isEmpty())
            <section class="card" style="padding:34px;text-align:center;color:var(--gris);">
                {{ $filtros['actividad'] || $filtros['foto']
                    ? 'Ninguna evaluación coincide con el filtro.'
                    : 'Todavía nadie ha respondido la encuesta de tus actividades.' }}
            </section>
        @else
            <div style="display:flex;flex-direction:column;gap:14px;">
                @foreach ($evaluaciones as $e)
                    <article class="card" style="padding:22px 24px;">
                        <div style="display:flex;align-items:baseline;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:10px;">
                            <div>
                                <span style="font-weight:700;color:var(--ink);">{{ $e->nombre }}</span>
                                <span class="helper" style="margin-left:8px;">{{ \App\Support\Fecha::corta($e->created_at) }}</span>
                            </div>

                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <span class="eval-nota" title="Experiencia">{{ $e->experiencia }}</span>
                                <span class="eval-nota eval-nota-motivacion" title="Motivación para volver">{{ $e->motivacion }}</span>
                            </div>
                        </div>

                        <p class="helper" style="margin:0 0 10px;">
                            {{ Str::limit($e->activity?->titulo ?? '(actividad borrada)', 60) }}
                        </p>

                        {{-- Punto 8 del 23/09: cada respuesta bajo su pregunta. --}}
                        @include('partials.evaluacion-respuestas', ['evaluacion' => $e])

                        @if ($e->fotos->isNotEmpty())
                            {{--
                                Las fotos se sirven por una ruta con permiso, no
                                por URL pública: viven en el disco privado justo
                                para que nadie llegue a ellas sin sesión.
                            --}}
                            <div class="evaluacion-fotos-tira">
                                @foreach ($e->fotos as $foto)
                                    <a class="evaluacion-foto-ficha" target="_blank" rel="noopener"
                                       href="{{ route('account.evaluaciones.foto', $foto) }}">
                                        <img class="evaluacion-foto-imagen" loading="lazy"
                                             src="{{ route('account.evaluaciones.foto', $foto) }}"
                                             alt="Fotografía enviada por {{ $e->nombre }}">
                                    </a>
                                @endforeach
                            </div>

                            @unless ($e->foto_autorizada)
                                {{-- Va aquí, pegado a las fotos, y no arriba en la
                                     pantalla: quien mire ésta tiene que leerlo
                                     mirando estas fotos y no otras. --}}
                                <p class="helper" style="margin:10px 0 0;color:var(--rosa);">
                                    Sin autorización de difusión: quien las subió no autorizó su uso público.
                                </p>
                            @endunless
                        @endif
                    </article>
                @endforeach
            </div>

            <div style="margin-top:22px;">{{ $evaluaciones->onEachSide(1)->links() }}</div>
        @endif
    @endif
</div>
</main>
@endsection
