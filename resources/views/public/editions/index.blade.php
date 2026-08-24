@extends('layouts.public')
@section('title', 'Ediciones · ' . config('app.name'))

@section('content')
<section style="max-width:1180px;margin:0 auto;padding:56px 40px 88px;">
    <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:12px;">Desde 2024</div>
    <h1 style="font-weight:800;font-size:40px;margin:0 0 34px;letter-spacing:-.02em;">Ediciones</h1>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:26px;">
        @foreach ($ediciones as $e)
            <article class="card" style="overflow:hidden;">
                @if ($e->imagen)
                    <div style="aspect-ratio:16/9;overflow:hidden;background:var(--gris-100);">
                        <img src="{{ $e->imagen_url }}" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
                    </div>
                @endif
                <div style="padding:22px 24px 24px;">
                    <div style="font-size:12.5px;font-weight:700;letter-spacing:.06em;color:var(--naranjo);text-transform:uppercase;">{{ $e->anio }}</div>
                    <h2 style="font-size:20px;font-weight:700;margin:8px 0 10px;">{{ $e->titulo }}</h2>
                    <p style="font-size:15px;line-height:1.55;margin:0;color:var(--gris);">{{ $e->descripcion }}</p>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
