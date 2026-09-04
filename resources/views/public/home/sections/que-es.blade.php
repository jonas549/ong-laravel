@php $b = $borrador ?? false; @endphp

<section id="que-es" style="scroll-margin-top:90px;background:linear-gradient(160deg,rgba(92,184,178,.14) 0%,rgba(92,184,178,.07) 100%);">
    <div style="max-width:1180px;margin:0 auto;padding:92px 40px;display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:60px;align-items:center;">
        <div class="reveal">
            <h2 class="dato-editable" style="font-weight:800;font-size:38px;margin:0 0 22px;letter-spacing:-.01em;">{{ $seccion->texto('titulo_antes', $b) }} <span style="color:var(--naranjo);">{{ $seccion->texto('titulo_destacado', $b) }}</span>{{ $seccion->texto('titulo_despues', $b) }}</h2>
            <div class="texto-editable" style="font-size:16px;line-height:1.65;margin:0 0 20px;color:var(--gris-700);">{!! $seccion->rico('cuerpo', $b) !!}</div>
            <p class="dato-editable" style="font-size:19px;line-height:1.4;margin:0 0 26px;font-weight:700;color:var(--ink);">{{ $seccion->texto('remate', $b) }}</p>
            <a href="{{ $seccion->enlace('cta_enlace', $b) ?: route('activities.index') }}" class="btn btn-primary boton-editable">{{ $seccion->texto('cta_texto', $b) }}</a>
        </div>

        <figure class="reveal" style="margin:0;position:relative;overflow:visible;">
            <span class="adorno" style="position:absolute;top:-18px;right:-18px;width:72px;height:72px;border-radius:18px;background:var(--amarillo);z-index:-1;opacity:.9;"></span>
            <div style="border-radius:26px;overflow:hidden;aspect-ratio:16/11;background:var(--gris-100);box-shadow:0 26px 50px -28px rgba(7,79,113,.4);">
                <img loading="lazy" decoding="async" width="1376" height="946" src="{{ asset($seccion->imagen('imagen', $b)) }}"
                     alt="{{ $seccion->texto('imagen_alt', $b) }}"
                     style="width:100%;height:100%;object-fit:cover;display:block;">
            </div>
            <img loading="lazy" decoding="async" class="sticker" width="400" height="400" src="{{ asset('img/dps-mascara-corazon-ronda.png') }}" alt="" aria-hidden="true"
                 style="--rot:-8deg;position:absolute;top:-52px;left:-52px;width:140px;height:auto;">
        </figure>
    </div>
</section>
