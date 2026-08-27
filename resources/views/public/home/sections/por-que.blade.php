@php
    $b = $borrador ?? false;
    $video = $seccion->video('video', $b);
@endphp

{{--
    Con video, la sección es de dos columnas —video a la izquierda, texto a la
    derecha— como en el HTML fuente. Sin video vuelve a una sola columna, que es
    como estaba antes de que existiera este campo: así vaciar el campo no deja
    media sección en blanco.

    El iframe se construye aquí a partir del identificador, y el identificador
    lo valida SanitizadorHtml::idDeYoutube(). Nunca se guarda ni se pinta un
    iframe que venga escrito desde el panel: por ahí es por donde entraría
    cualquier cosa.
--}}

@if ($video)
    <section style="max-width:1180px;margin:0 auto;padding:92px 40px;display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:60px;align-items:center;">
        <figure class="reveal" style="margin:0;position:relative;overflow:visible;order:0;">
            <span class="adorno" style="position:absolute;bottom:-18px;left:-18px;width:76px;height:76px;border-radius:18px;background:var(--turquesa);z-index:-1;opacity:.9;"></span>

            <div data-video="{{ $video }}" role="button" tabindex="0" aria-label="Reproducir video"
                 style="position:relative;cursor:pointer;border-radius:26px;overflow:hidden;aspect-ratio:16/9;background:#000;box-shadow:0 26px 50px -28px rgba(0,0,0,.5);">
                <img loading="lazy" decoding="async" src="https://img.youtube.com/vi/{{ $video }}/hqdefault.jpg" alt="Portada del video"
                     style="width:100%;height:100%;object-fit:cover;display:block;">
                <span class="video-play" style="position:absolute;inset:0;display:grid;place-items:center;background:rgba(0,0,0,.18);transition:background .2s ease;">
                    <span style="width:76px;height:76px;border-radius:999px;background:var(--naranjo);display:grid;place-items:center;box-shadow:0 10px 28px -8px rgba(229,114,0,.7);">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="#fff" style="margin-left:4px;"><path d="M8 5v14l11-7z"></path></svg>
                    </span>
                </span>
            </div>

            <img loading="lazy" decoding="async" class="sticker" width="400" height="400" src="{{ asset('img/dps-mascara-corazon-mural-9b8c8912.png') }}" alt="" aria-hidden="true"
                 style="--rot:-6deg;position:absolute;top:-56px;left:-56px;width:150px;height:auto;">
        </figure>

        <div class="reveal" style="position:relative;">
            <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:14px;">{{ $seccion->texto('antetitulo', $b) }}</div>
            <h2 style="font-weight:800;font-size:34px;line-height:1.12;margin:0 0 20px;letter-spacing:-.01em;">{{ $seccion->texto('titulo', $b) }}</h2>
            <div class="texto-editable" style="font-size:16px;line-height:1.65;margin:0 0 18px;color:var(--gris-700);">{!! $seccion->rico('cuerpo', $b) !!}</div>
            <p style="font-size:19px;line-height:1.4;margin:0;font-weight:700;color:var(--ink);">{{ $seccion->texto('remate', $b) }}</p>
            <img loading="lazy" decoding="async" width="665" height="403" src="{{ asset('img/por-que-celebramos-crop.png') }}" alt="" aria-hidden="true"
                 style="display:block;width:380px;max-width:100%;height:auto;margin:22px 0 -30px auto;">
        </div>
    </section>
@else
    <section style="max-width:1180px;margin:0 auto;padding:88px 40px 40px;">
        <div class="reveal" style="position:relative;">
            <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:14px;">{{ $seccion->texto('antetitulo', $b) }}</div>
            <h2 style="font-weight:800;font-size:34px;line-height:1.12;margin:0 0 20px;letter-spacing:-.01em;">{{ $seccion->texto('titulo', $b) }}</h2>
            <div class="texto-editable" style="font-size:16px;line-height:1.65;margin:0 0 18px;color:var(--gris-700);">{!! $seccion->rico('cuerpo', $b) !!}</div>
            <p style="font-size:19px;line-height:1.4;margin:0;font-weight:700;color:var(--ink);">{{ $seccion->texto('remate', $b) }}</p>
            <img loading="lazy" decoding="async" width="665" height="403" src="{{ asset('img/por-que-celebramos-crop.png') }}" alt="" aria-hidden="true"
                 style="display:block;width:380px;max-width:100%;height:auto;margin:22px 0 -30px auto;">
        </div>
    </section>
@endif
