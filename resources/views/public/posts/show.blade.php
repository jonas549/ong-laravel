@extends('layouts.public')
@section('title', $post->titulo . ' · ' . config('app.name'))
@section('meta', Str::limit(strip_tags($post->extracto ?? ''), 150))

@section('content')
<article style="max-width:760px;margin:0 auto;padding:40px 40px 80px;">
    <a href="{{ route('posts.index') }}" class="textlink" style="font-size:14px;">← Volver a noticias</a>

    <span style="display:block;font-size:13px;color:var(--gris);margin:24px 0 10px;">{{ $post->fecha }}</span>
    <h1 style="font-weight:800;font-size:38px;line-height:1.12;margin:0 0 20px;letter-spacing:-.02em;">{{ $post->titulo }}</h1>

    @if ($post->imagen)
        <div style="border-radius:22px;overflow:hidden;margin:0 0 28px;">
            <img loading="lazy" decoding="async" src="{{ $post->imagen_url }}" alt="{{ $post->titulo }}" style="width:100%;height:auto;display:block;">
        </div>
    @endif

    @if ($post->extracto)
        <p style="font-size:18px;line-height:1.6;color:var(--gris-700);font-weight:500;margin:0 0 20px;">{{ $post->extracto }}</p>
    @endif

    <div style="font-size:16px;line-height:1.75;color:var(--gris-700);white-space:pre-line;">{{ $post->contenido }}</div>

    @if ($otras->isNotEmpty())
        <hr style="border:0;border-top:1px solid var(--linea);margin:48px 0 28px;">
        <h2 style="font-size:20px;font-weight:700;margin:0 0 16px;">Otras noticias</h2>
        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">
            @foreach ($otras as $o)
                <li><a class="textlink" href="{{ route('posts.show', $o) }}">{{ $o->titulo }}</a></li>
            @endforeach
        </ul>
    @endif
</article>
@endsection
