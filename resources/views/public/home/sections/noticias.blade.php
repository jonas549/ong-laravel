<section id="noticias" style="scroll-margin-top:90px;max-width:1180px;margin:0 auto;padding:88px 40px;">
    <div class="reveal" style="display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-bottom:36px;flex-wrap:wrap;">
        <div>
            <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:12px;">Al día</div>
            <h2 style="font-weight:800;font-size:38px;margin:0;letter-spacing:-.01em;">Noticias</h2>
        </div>
        <a href="{{ route('posts.index') }}" class="btn btn-outline">Ver todas</a>
    </div>

    @if ($noticias->isEmpty())
        <p style="color:var(--gris);font-size:15px;">Todavía no hay noticias publicadas.</p>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
            @foreach ($noticias as $post)
                <article class="act-card reveal" style="background:#fff;border:1px solid #eef0f1;border-radius:22px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 10px 30px -22px rgba(0,0,0,.2);">
                    @if ($post->imagen)
                        <div style="aspect-ratio:16/10;overflow:hidden;background:var(--gris-100);">
                            <img class="act-img" src="{{ $post->imagen_url }}" alt="{{ $post->titulo }}"
                                 style="width:100%;height:100%;object-fit:cover;display:block;">
                        </div>
                    @endif
                    <div style="padding:20px 22px 22px;display:flex;flex-direction:column;gap:9px;flex:1;">
                        <span style="font-size:12.5px;color:var(--gris);">{{ $post->fecha }}</span>
                        <h3 style="font-weight:700;font-size:19px;line-height:1.2;margin:0;letter-spacing:-.01em;">{{ $post->titulo }}</h3>
                        <p style="font-size:14.5px;line-height:1.5;margin:0;color:var(--gris);">{{ $post->extracto }}</p>
                        <a href="{{ route('posts.show', $post) }}" class="btn btn-outline btn-sm" style="align-self:flex-start;margin-top:auto;">Leer más</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
