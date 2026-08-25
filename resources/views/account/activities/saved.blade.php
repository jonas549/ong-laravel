@extends('layouts.public')
@section('title', 'Cambios guardados · ' . config('app.name'))

{{-- mi-cuenta.html lleva el footer compacto. --}}
@php $footerCompacto = true; @endphp

@section('content')

<main style="flex:1;">
{{--
    PANTALLA 2B — CAMBIOS GUARDADOS de mi-cuenta.html.

    El prototipo sólo contempla editar una actividad ya publicada, así que su
    texto da por hecho que los cambios quedan a la vista. Cuando la actividad
    todavía no está publicada eso no es cierto y el copy se ajusta.
--}}
<div class="rise" style="max-width:720px;margin:0 auto;padding:88px 32px 120px;text-align:center;">
    <img loading="lazy" decoding="async" width="486" height="375" src="{{ asset('img/logo-corazon-15f12e4a.png') }}" alt="" aria-hidden="true"
         style="width:200px;max-width:100%;height:auto;display:block;margin:0 auto 24px;filter:drop-shadow(0 14px 26px rgba(0,0,0,.14));">

    @if ($activity->estado === 'publicada')
        <h1 style="font-size:38px;font-weight:800;letter-spacing:-.02em;line-height:1.12;margin:0 0 14px;color:var(--ink);text-wrap:pretty;">¡Tus cambios ya están publicados!</h1>
        <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 auto 32px;max-width:46ch;text-wrap:pretty;">La información de tu actividad se actualizó correctamente y ya está visible para las personas que visitan el sitio.</p>
    @else
        <h1 style="font-size:38px;font-weight:800;letter-spacing:-.02em;line-height:1.12;margin:0 0 14px;color:var(--ink);text-wrap:pretty;">¡Guardamos tus cambios!</h1>
        <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 auto 32px;max-width:46ch;text-wrap:pretty;">La información de <strong style="color:var(--ink);">{{ $activity->titulo }}</strong> quedó actualizada. Se verá en el sitio cuando la actividad esté publicada.</p>
    @endif

    <a href="{{ route('account.activities.index') }}" class="btn btn-primary">Volver a mis actividades</a>
</div>
</main>
@endsection
