@php
    $b = $borrador ?? false;

    /**
     * El largo de cada barra sale de sus dos números, no de un 50% escrito a
     * mano como estaba antes: si no, cambiar «500 de 1.000» dejaba la barra
     * donde estaba y el dibujo contradecía al texto.
     *
     * Se admiten puntos de miles porque así están en el fuente («1.000»), y se
     * recorta a 100 para que pasarse de la meta no desborde la caja.
     */
    $porcentaje = function (string $actual, string $meta): int {
        $n = (int) preg_replace('/\D+/', '', $actual);
        $m = (int) preg_replace('/\D+/', '', $meta);

        return $m > 0 ? (int) round(min(100, max(0, $n / $m * 100))) : 0;
    };

    $barras = [
        ['label' => $seccion->texto('barra1_label', $b), 'actual' => $seccion->texto('barra1_actual', $b), 'meta' => $seccion->texto('barra1_meta', $b), 'color' => '#c63663', 'fondo' => 'rgba(198,54,99,.14)', 'margen' => '0 0 10px'],
        ['label' => $seccion->texto('barra2_label', $b), 'actual' => $seccion->texto('barra2_actual', $b), 'meta' => $seccion->texto('barra2_meta', $b), 'color' => '#5cb8b2', 'fondo' => 'rgba(92,184,178,.16)', 'margen' => '26px 0 10px'],
    ];
@endphp

<section style="background:linear-gradient(145deg,#fff7ef 0%,#fdeede 55%,#fbe6ce 100%);color:var(--ink);position:relative;overflow:hidden;">
    <img loading="lazy" decoding="async" class="linework" width="1045" height="721" src="{{ asset('img/manos.png') }}" alt="" aria-hidden="true">
    <div style="position:absolute;top:0;left:0;right:0;height:180px;background:linear-gradient(to bottom,#fff 0%,rgba(255,255,255,.75) 45%,rgba(255,255,255,0) 100%);z-index:1;"></div>

    <div class="reveal" style="max-width:720px;margin:0 auto;padding:76px 40px;text-align:center;position:relative;z-index:2;">
        <img loading="lazy" decoding="async" width="840" height="388" src="{{ asset('img/construyamos-crop.png') }}" alt="" aria-hidden="true"
             style="display:block;width:100%;max-width:420px;height:auto;margin:0 auto 18px;">

        <div class="dato-editable" style="font-size:13px;letter-spacing:.04em;font-weight:700;color:var(--naranjo);margin-bottom:14px;text-transform:uppercase;">{{ $seccion->texto('antetitulo', $b) }}</div>
        <h2 class="dato-editable" style="font-weight:800;font-size:38px;margin:0;letter-spacing:-.01em;">{{ $seccion->texto('titulo', $b) }}</h2>
        <p class="dato-editable" style="font-size:16px;margin:12px 0 0;color:var(--gris);">{{ $seccion->texto('bajada', $b) }}</p>

        <div style="margin-top:44px;text-align:left;">
            @foreach ($barras as $barra)
                <div style="display:flex;justify-content:space-between;align-items:baseline;margin:{{ $barra['margen'] }};">
                    <span class="dato-editable" style="font-size:15px;font-weight:600;">{{ $barra['label'] }}</span>
                    <span class="dato-editable" style="font-size:14px;font-variant-numeric:tabular-nums;color:var(--gris);"><span class="count">{{ $barra['actual'] }}</span> de {{ $barra['meta'] }}</span>
                </div>
                <div style="height:10px;border-radius:999px;background:{{ $barra['fondo'] }};overflow:hidden;">
                    <div class="barfill" style="width:{{ $porcentaje($barra['actual'], $barra['meta']) }}%;height:100%;border-radius:999px;background:{{ $barra['color'] }};"></div>
                </div>
            @endforeach
        </div>

        <p class="dato-editable" style="font-size:15px;margin:44px 0 18px;color:var(--gris);">{{ $seccion->texto('pregunta', $b) }}</p>
        <a href="{{ $seccion->enlace('cta_enlace', $b) ?: '#kit' }}" class="btn btn-primary dato-editable">{{ $seccion->texto('cta_texto', $b) }}</a>
        <p class="dato-editable" style="font-size:13px;margin:16px 0 0;color:#9a9ca0;">{{ $seccion->texto('nota', $b) }}</p>
    </div>
</section>
