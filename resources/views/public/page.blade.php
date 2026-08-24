@extends('layouts.public')
@section('title', $page->titulo . ' · ' . config('app.name'))
@section('meta', $page->meta_descripcion ?? '')

@section('content')
<article style="max-width:760px;margin:0 auto;padding:56px 40px 88px;">
    <h1 style="font-weight:800;font-size:38px;line-height:1.12;margin:0 0 24px;letter-spacing:-.02em;">{{ $page->titulo }}</h1>
    <div style="font-size:16px;line-height:1.75;color:var(--gris-700);">{!! $page->contenido !!}</div>
</article>
@endsection
