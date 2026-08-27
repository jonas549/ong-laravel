@php $b = $borrador ?? false; @endphp

<section id="ediciones" style="scroll-margin-top:90px;position:relative;background:url('{{ asset('img/fondo-02.png') }}') center/cover no-repeat;">
    <div aria-hidden="true" style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.94) 0%,rgba(255,255,255,.7) 42%,rgba(255,255,255,.9) 100%);"></div>

    <div style="position:relative;z-index:1;max-width:1040px;margin:0 auto;padding:92px 40px 84px;text-align:center;">
        <h2 class="reveal dato-editable" style="font-weight:800;font-size:38px;line-height:1.12;margin:0 auto;max-width:22ch;letter-spacing:-.01em;">{{ $seccion->texto('titulo', $b) }}</h2>
        <p class="reveal dato-editable" style="font-size:15px;margin:18px 0 48px;color:var(--gris);">{{ $seccion->texto('bajada', $b) }}</p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:28px;">
            @foreach ($cifras as $s)
                <div class="reveal" style="background:#fff;border-radius:20px;border:1px solid #eef0f1;padding:32px 20px;box-shadow:0 10px 28px -22px rgba(0,0,0,.2);">
                    <div class="count" style="font-weight:800;font-size:46px;line-height:1;color:{{ $s->color }};letter-spacing:-.02em;">{{ $s->numero }}</div>
                    <div style="font-size:13px;letter-spacing:.02em;font-weight:600;margin-top:12px;color:var(--gris);">{{ $s->etiqueta }}</div>
                </div>
            @endforeach
        </div>

        @if ($ediciones->isNotEmpty())
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-top:52px;text-align:left;">
                @foreach ($ediciones as $e)
                    <article class="reveal" style="background:#fff;border:1px solid #eef0f1;border-radius:18px;overflow:hidden;">
                        @if ($e->imagen)
                            <div style="aspect-ratio:16/9;overflow:hidden;background:var(--gris-100);">
                                <img loading="lazy" decoding="async" src="{{ $e->imagen_url }}" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
                            </div>
                        @endif
                        <div style="padding:18px 20px 20px;">
                            <div style="font-size:12.5px;font-weight:700;letter-spacing:.06em;color:var(--naranjo);text-transform:uppercase;">{{ $e->anio }}</div>
                            <h3 style="font-size:18px;font-weight:700;margin:6px 0 8px;">{{ $e->titulo }}</h3>
                            <p style="font-size:14px;line-height:1.5;margin:0;color:var(--gris);">{{ $e->descripcion }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
