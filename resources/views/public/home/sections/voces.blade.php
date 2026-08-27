@php $b = $borrador ?? false; @endphp

<section style="background:var(--bg-warm);position:relative;overflow:hidden;">
    <img loading="lazy" decoding="async" class="linework" width="1045" height="721" src="{{ asset('img/manos.png') }}" alt="" aria-hidden="true">

    <div style="max-width:1180px;margin:0 auto;padding:88px 40px;text-align:center;position:relative;z-index:2;">
        <div class="reveal cabecera-seccion" style="display:flex;align-items:center;gap:28px;text-align:left;margin-bottom:40px;">
            <img loading="lazy" decoding="async" width="388" height="541" src="{{ asset('img/voces-crop.png') }}" alt="" aria-hidden="true" style="flex:none;width:112px;height:auto;">
            <div>
                <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:12px;">{{ $seccion->texto('antetitulo', $b) }}</div>
                <h2 style="font-weight:800;font-size:38px;margin:0;letter-spacing:-.01em;">{{ $seccion->texto('titulo', $b) }}</h2>
            </div>
        </div>

        <div class="carrusel-fila" style="display:flex;align-items:stretch;gap:20px;">
            <button type="button" class="icon-btn" data-carousel-prev="voces" aria-label="Testimonio anterior" style="align-self:center;flex:none;">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            </button>

            <div class="carousel" data-carousel="voces" style="display:flex;gap:24px;flex:1;overflow-x:auto;padding-bottom:8px;">
                @foreach ($voces as $q)
                    <figure class="reveal tarjeta-carrusel" style="flex:0 0 calc((100% - 24px) / 2);min-width:320px;margin:0;background:#fff;border-radius:22px;padding:52px 48px 44px;text-align:left;display:flex;flex-direction:column;gap:26px;box-shadow:0 14px 36px -24px rgba(0,0,0,.22);border:1px solid #eef0f1;position:relative;">
                        <span aria-hidden="true" style="font-family:Georgia,serif;font-size:72px;line-height:.4;color:var(--naranjo-100);position:absolute;top:44px;right:44px;">”</span>

                        <blockquote style="font-size:17px;line-height:1.65;margin:0;color:var(--gris-700);position:relative;">{{ $q->texto }}</blockquote>

                        <figcaption style="margin-top:auto;display:flex;align-items:center;gap:14px;">
                            <span style="flex:none;width:96px;height:96px;border-radius:999px;background:#fff;padding:11px;border:1px solid #e4e6e8;box-shadow:0 2px 10px -4px rgba(0,0,0,.14);display:grid;place-items:center;overflow:hidden;">
                                @if ($q->logo_path)
                                    <img loading="lazy" decoding="async" src="{{ $q->logo_url }}" alt="" aria-hidden="true"
                                         style="width:100%;height:100%;object-fit:contain;{{ $q->bleed ? 'transform:scale(2.15);' : '' }}">
                                @endif
                            </span>
                            <span>
                                <span style="display:block;font-weight:700;font-size:15.5px;color:{{ $q->color }};">{{ $q->autor }}</span>
                                <span style="display:block;font-size:13.5px;color:var(--gris);margin-top:2px;">{{ $q->cargo }}</span>
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <button type="button" class="icon-btn" data-carousel-next="voces" aria-label="Testimonio siguiente" style="align-self:center;flex:none;">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
            </button>
        </div>

        <div class="carousel-dots" data-carousel-dots="voces" style="display:flex;justify-content:center;gap:9px;margin-top:34px;"></div>
    </div>
</section>
