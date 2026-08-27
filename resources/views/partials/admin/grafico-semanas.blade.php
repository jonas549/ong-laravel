{{--
    Evolución por semana, en SVG generado desde los datos.

    Sin librería de gráficos a propósito: el proyecto no mete nada por CDN
    —support.js, que descargaba React y Babel de unpkg en cada carga, se quitó
    por eso mismo— y un gráfico de barras son cuatro rectángulos. Cargar 200 KB
    de JavaScript para dibujarlos sería el mismo error con otro nombre.

    Espera `$evolucion` tal como lo devuelve ResumenPanel::evolucion().
--}}
@php
    $puntos = $evolucion['puntos'];
    $hayDatos = $evolucion['totalActividades'] + $evolucion['totalInscripciones'] > 0;

    // El techo se redondea hacia arriba a algo legible: con un máximo de 7, un
    // eje que llegue a 7 pone las marcas en 1,75 / 3,5 / 5,25 y no se puede
    // leer ninguna.
    $techo = $evolucion['techo'];
    $paso = max(1, (int) ceil($techo / 4));
    $techo = $paso * 4;

    // Lienzo. Se dibuja sobre una rejilla fija y se escala con el viewBox, así
    // que el gráfico ocupa el ancho que le den sin recalcular nada.
    $w = 720; $h = 210;
    $izq = 34; $der = 10; $arriba = 12; $abajo = 34;
    $ancho = $w - $izq - $der;
    $alto = $h - $arriba - $abajo;

    $paso_x = $ancho / max(1, count($puntos));
    $barra = min(16, ($paso_x - 8) / 2);

    $y = fn ($v) => $arriba + $alto - ($techo > 0 ? ($v / $techo) * $alto : 0);

    $series = [
        ['clave' => 'actividades', 'label' => 'Actividades creadas', 'color' => 'var(--naranjo)'],
        ['clave' => 'inscripciones', 'label' => 'Inscripciones', 'color' => 'var(--teal)'],
    ];
@endphp

<div style="display:flex;gap:18px;flex-wrap:wrap;align-items:baseline;margin-bottom:6px;">
    @foreach ($series as $s)
        <span style="display:inline-flex;align-items:center;gap:7px;font-size:13px;color:var(--gris-700);">
            <span style="width:10px;height:10px;border-radius:3px;background:{{ $s['color'] }};flex:none;"></span>
            {{ $s['label'] }}
            <strong style="font-variant-numeric:tabular-nums;">{{ $evolucion['total'.ucfirst($s['clave'])] ?? 0 }}</strong>
        </span>
    @endforeach
</div>

@if (! $hayDatos)
    <p class="helper" style="margin:14px 0 0;">
        No hubo ni una actividad ni una inscripción en las últimas {{ $evolucion['semanas'] }} semanas.
        El gráfico aparece en cuanto haya movimiento.
    </p>
@else
    {{--
        En una pantalla estrecha el gráfico no cabe entero y se desplaza, como
        las tablas del panel. Se abre por el final a propósito: si empieza por la
        izquierda se ve el hueco de hace tres meses y parece que no hay datos,
        que es justo lo contrario de lo que pasa.
    --}}
    <div style="overflow-x:auto;" x-data x-init="$el.scrollLeft = $el.scrollWidth">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" style="width:100%;min-width:520px;height:auto;display:block;"
             role="img" aria-label="Actividades e inscripciones por semana, últimas {{ $evolucion['semanas'] }} semanas">

            {{-- Rejilla y eje vertical --}}
            @for ($i = 0; $i <= 4; $i++)
                @php $valor = $paso * $i; @endphp
                <line x1="{{ $izq }}" y1="{{ $y($valor) }}" x2="{{ $w - $der }}" y2="{{ $y($valor) }}"
                      stroke="var(--linea)" stroke-width="1" />
                <text x="{{ $izq - 8 }}" y="{{ $y($valor) + 4 }}" text-anchor="end"
                      font-size="11" fill="var(--gris)">{{ $valor }}</text>
            @endfor

            {{-- Barras --}}
            @foreach ($puntos as $i => $p)
                @php $centro = $izq + $paso_x * $i + $paso_x / 2; @endphp

                @foreach ($series as $j => $s)
                    @php
                        $v = $p[$s['clave']];
                        $x = $centro - $barra - 2 + $j * ($barra + 4);
                        $altura = $arriba + $alto - $y($v);
                    @endphp
                    @if ($v > 0)
                        <rect x="{{ round($x, 2) }}" y="{{ round($y($v), 2) }}"
                              width="{{ round($barra, 2) }}" height="{{ round($altura, 2) }}"
                              rx="3" fill="{{ $s['color'] }}">
                            <title>{{ $p['etiqueta'] }}: {{ $v }} {{ mb_strtolower($s['label']) }}</title>
                        </rect>
                    @endif
                @endforeach

                {{-- Una etiqueta de cada dos: doce fechas seguidas se pisan --}}
                @if ($i % 2 === 0 || $i === count($puntos) - 1)
                    <text x="{{ round($centro, 2) }}" y="{{ $h - 12 }}" text-anchor="middle"
                          font-size="10.5" fill="var(--gris)">{{ $p['etiqueta'] }}</text>
                @endif
            @endforeach
        </svg>
    </div>
@endif

<p class="helper" style="margin:10px 0 0;">
    Semanas de lunes a domingo, las últimas {{ $evolucion['semanas'] }}.
    Las inscripciones canceladas no se cuentan, igual que en la cifra de arriba.
</p>
