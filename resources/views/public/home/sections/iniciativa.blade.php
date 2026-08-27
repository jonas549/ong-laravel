@php $b = $borrador ?? false; @endphp

<section class="reveal" style="max-width:900px;margin:0 auto;padding:20px 40px 72px;text-align:center;">
    <p style="font-size:24px;font-weight:700;line-height:1.35;letter-spacing:-.01em;margin:0 auto 34px;max-width:22ch;color:var(--ink);">
        {{ $seccion->texto('texto_antes', $b) }} <span style="color:var(--naranjo);">{{ $seccion->texto('texto_destacado', $b) }}</span> {{ $seccion->texto('texto_despues', $b) }}
    </p>
    <div style="display:flex;align-items:center;justify-content:center;gap:40px;flex-wrap:wrap;">
        <img loading="lazy" decoding="async" width="400" height="313" src="{{ asset($seccion->imagen('logo', $b)) }}" alt="{{ $seccion->texto('logo_alt', $b) }}"
             style="height:156px;width:auto;object-fit:contain;">
    </div>
    <p style="font-size:24px;font-weight:700;line-height:1.35;letter-spacing:-.01em;margin:30px 0 0;color:var(--ink);">{{ $seccion->texto('texto_final', $b) }}</p>
</section>
