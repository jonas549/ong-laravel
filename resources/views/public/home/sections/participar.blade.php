@php
    $iconos = [
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'cal'  => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    ];
@endphp

<section id="voluntariado" style="scroll-margin-top:180px;position:relative;z-index:4;max-width:1180px;margin:-96px auto 0;padding:0 40px 88px;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;align-items:stretch;">
        @foreach ($tarjetas as $w)
            <a href="{{ $w->href }}" class="part-card reveal"
               style="position:relative;overflow:hidden;background:#fff;border:1px solid #eef0f1;border-top:4px solid {{ $w->color }};border-radius:18px;padding:18px 18px 16px;display:flex;flex-direction:column;height:100%;box-shadow:0 22px 44px -26px rgba(0,0,0,.34);">

                @if ($w->art_path)
                    <img class="pc-art" src="{{ asset($w->art_path) }}" alt="" aria-hidden="true"
                         style="position:absolute;right:-30px;bottom:-30px;width:170px;opacity:.18;pointer-events:none;">
                @endif

                @if ($w->mask_path)
                    <img class="pc-icon" src="{{ asset($w->mask_path) }}" alt="" aria-hidden="true"
                         style="position:relative;z-index:1;height:78px;width:auto;max-width:96px;object-fit:contain;object-position:left bottom;align-self:flex-start;margin-bottom:12px;">
                @endif

                <h3 style="position:relative;z-index:1;font-weight:700;font-size:17px;margin:0 0 5px;letter-spacing:-.01em;">{{ $w->titulo }}</h3>
                <p style="position:relative;z-index:1;font-size:13.5px;line-height:1.4;margin:0;color:var(--gris);max-width:88%;">{{ $w->descripcion }}</p>

                @if ($w->nota)
                    <p style="position:relative;z-index:1;font-size:12.5px;font-style:italic;line-height:1.4;margin:8px 0 0;color:#9a9ca0;">{{ $w->nota }}</p>
                @endif

                <span class="pc-cta">
                    <span class="pc-ctatext">{{ $w->cta }}</span>
                    <span class="pc-arrow" aria-hidden="true">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                    </span>
                </span>
            </a>
        @endforeach
    </div>
</section>
