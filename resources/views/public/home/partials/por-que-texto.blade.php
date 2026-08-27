{{--
    La columna de texto de «¿Por qué celebramos este día?».

    Está aparte porque la sección se pinta de dos formas —a dos columnas con el
    video, y a una sola cuando no hay video— y el texto es el mismo en las dos.
    Estaba duplicado, y la copia se quedó sin la clase `dato-editable` cuando se
    acotó la regla del desbordamiento: exactamente el fallo que provoca tener
    dos veces lo mismo.
--}}
<div class="reveal" style="position:relative;">
    <div class="dato-editable" style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:14px;">{{ $seccion->texto('antetitulo', $b) }}</div>
    <h2 class="dato-editable" style="font-weight:800;font-size:34px;line-height:1.12;margin:0 0 20px;letter-spacing:-.01em;">{{ $seccion->texto('titulo', $b) }}</h2>
    <div class="texto-editable" style="font-size:16px;line-height:1.65;margin:0 0 18px;color:var(--gris-700);">{!! $seccion->rico('cuerpo', $b) !!}</div>
    <p class="dato-editable" style="font-size:19px;line-height:1.4;margin:0;font-weight:700;color:var(--ink);">{{ $seccion->texto('remate', $b) }}</p>
    <img loading="lazy" decoding="async" width="665" height="403" src="{{ asset('img/por-que-celebramos-crop.png') }}" alt="" aria-hidden="true"
         style="display:block;width:380px;max-width:100%;height:auto;margin:22px 0 -30px auto;">
</div>
