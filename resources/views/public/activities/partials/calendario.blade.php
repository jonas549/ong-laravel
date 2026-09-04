{{--
    El calendario mes a mes.

    **Una sola maqueta para las dos pantallas.** En escritorio las casillas se
    reparten en siete columnas, de lunes a domingo; por debajo de 760 px el CSS
    las apila y esconde las vacías, y lo que queda es la lista de los días que
    tienen algo. Por eso cada casilla lleva dos rótulos de fecha —el número
    suelto y el día largo— y el CSS enseña el que toca: siete columnas no caben
    en un teléfono, y mantener dos rejillas distintas en paralelo se rompería a
    la primera.

    Nada de esto necesita JavaScript, ni el desplegable de las casillas ni el de
    la franja: los dos son `<details>`. En una pantalla pública eso vale más que
    la animación, y evita que una actividad se quede escondida detrás de un
    «+2 más» que no responde.
--}}
@php
    // Las flechas viajan con los filtros puestos: cambiar de mes no los suelta.
    $conMes = fn (?string $mes) => $mes === null
        ? null
        : route('activities.index', $puestos + ['vista' => 'calendario', 'mes' => $mes]);

    $anterior = $conMes($calendario->anterior());
    $siguiente = $conMes($calendario->siguiente());
    $esteMes = route('activities.index', $puestos + ['vista' => 'calendario']);

    // Cuántas actividades caben en una casilla antes de plegar el resto.
    $tope = 3;
@endphp

@include('public.activities.partials.franja-sin-fecha')

<div class="cal-nav">
    @if ($anterior)
        <a class="cal-paso" href="{{ $anterior }}" rel="nofollow" aria-label="Mes anterior">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </a>
    @else
        <span class="cal-paso cal-paso--muerto" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </span>
    @endif

    <h2 class="cal-mes">{{ $calendario->titulo() }}</h2>

    @if ($siguiente)
        <a class="cal-paso" href="{{ $siguiente }}" rel="nofollow" aria-label="Mes siguiente">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </a>
    @else
        <span class="cal-paso cal-paso--muerto" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </span>
    @endif

    @unless ($calendario->esMesDeHoy())
        <a class="cal-hoy" href="{{ $esteMes }}">Ir a este mes</a>
    @endunless
</div>

<div class="cal-rejilla">
    {{-- Los rótulos de columna sobran cuando la rejilla se pliega a lista. --}}
    <div class="cal-cabecera" aria-hidden="true">
        @foreach (\App\Support\CalendarioMes::diasDeLaSemana() as $rotulo)
            <span>{{ $rotulo }}</span>
        @endforeach
    </div>

    <div class="cal-dias">
        @foreach ($calendario->semanas as $semana)
            @foreach ($semana as $casilla)
                @php
                    $primeras = $casilla['actividades']->take($tope);
                    $resto = $casilla['actividades']->slice($tope);
                @endphp

                <div @class([
                    'cal-dia',
                    'cal-dia--fuera' => ! $casilla['delMes'],
                    'cal-dia--hoy' => $casilla['esHoy'],
                    'cal-dia--vacio' => $casilla['actividades']->isEmpty(),
                ])>
                    <div class="cal-dia-fecha">
                        <span class="cal-dia-numero">{{ $casilla['fecha']->format('j') }}</span>
                        {{-- El rótulo largo sólo se ve cuando la rejilla es lista. --}}
                        <span class="cal-dia-largo">{{ Str::ucfirst($casilla['fecha']->locale('es')->isoFormat('ddd D [de] MMMM')) }}</span>
                        @if ($casilla['esHoy'])
                            <span class="cal-dia-marca">Hoy</span>
                        @endif
                    </div>

                    @foreach ($primeras as $act)
                        @include('public.activities.partials.cal-actividad', ['act' => $act, 'dia' => $casilla['fecha']])
                    @endforeach

                    @if ($resto->isNotEmpty())
                        {{--
                            Se despliega en la propia casilla y no lleva a otra
                            pantalla: no hay filtro por día al que mandar a
                            nadie, y una actividad escondida detrás de un «+2»
                            que no abre es una actividad perdida.
                        --}}
                        <details class="cal-mas">
                            <summary>+{{ $resto->count() }} más</summary>
                            @foreach ($resto as $act)
                                @include('public.activities.partials.cal-actividad', ['act' => $act, 'dia' => $casilla['fecha']])
                            @endforeach
                        </details>
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>
</div>

@if ($calendario->total === 0)
    <div class="card" style="padding:36px;text-align:center;margin-top:22px;">
        <p style="font-size:16px;color:var(--gris);margin:0 0 16px;">
            No hay actividades con fecha en {{ Str::lower($calendario->titulo()) }}{{ $puestos ? ' con esos filtros' : '' }}.
        </p>
        @if ($siguiente)
            <a href="{{ $siguiente }}" class="btn btn-outline">Mirar el mes siguiente</a>
        @endif
    </div>
@endif
