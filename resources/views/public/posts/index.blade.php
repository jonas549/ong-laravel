@extends('layouts.public')
@section('title', 'Noticias · ' . config('app.name'))

@section('content')
<section style="max-width:1180px;margin:0 auto;padding:56px 40px 88px;">
    <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:12px;">Al día</div>
    <h1 style="font-weight:800;font-size:40px;margin:0 0 34px;letter-spacing:-.02em;">Noticias</h1>

    @if ($noticias->isEmpty())
        <p style="color:var(--gris);">Todavía no hay noticias publicadas.</p>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:26px;">
            @foreach ($noticias as $post)
                <article class="act-card" style="background:#fff;border:1px solid #eef0f1;border-radius:22px;overflow:hidden;display:flex;flex-direction:column;">
                    @if ($post->imagen)
                        <div style="aspect-ratio:16/10;overflow:hidden;background:var(--gris-100);">
                            <img loading="lazy" decoding="async" class="act-img" src="{{ $post->imagen_url }}" alt="{{ $post->titulo }}" style="width:100%;height:100%;object-fit:cover;display:block;">
                        </div>
                    @endif
                    <div style="padding:20px 22px 22px;display:flex;flex-direction:column;gap:9px;flex:1;">
                        <span style="font-size:12.5px;color:var(--gris);">{{ $post->fecha }}</span>
                        <h2 style="font-weight:700;font-size:19px;line-height:1.2;margin:0;">{{ $post->titulo }}</h2>
                        <p style="font-size:14.5px;line-height:1.5;margin:0;color:var(--gris);">{{ $post->extracto }}</p>
                        <a href="{{ route('posts.show', $post) }}" class="btn btn-outline btn-sm" style="align-self:flex-start;margin-top:auto;">Leer más</a>
                    </div>
                </article>
            @endforeach
        </div>
        <div style="margin-top:40px;">{{ $noticias->links() }}</div>
    @endif
</section>
@endsection
