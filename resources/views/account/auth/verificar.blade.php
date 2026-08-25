@extends('layouts.public')
@section('title', 'Confirma tu correo · ' . config('app.name'))

@php $footerCompacto = true; @endphp

@section('content')
<main style="flex:1;">
<div class="rise" style="max-width:640px;margin:0 auto;padding:88px 32px 120px;text-align:center;">
    <img loading="lazy" decoding="async" width="486" height="375" src="{{ asset('img/logo-corazon-15f12e4a.png') }}" alt="" aria-hidden="true"
         style="width:180px;max-width:100%;height:auto;display:block;margin:0 auto 24px;filter:drop-shadow(0 14px 26px rgba(0,0,0,.14));">

    <h1 style="font-size:34px;font-weight:800;letter-spacing:-.02em;line-height:1.12;margin:0 0 14px;color:var(--ink);text-wrap:pretty;">Revisa tu correo</h1>
    <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 auto 28px;max-width:46ch;text-wrap:pretty;">
        Te enviamos un enlace a <strong style="color:var(--ink);">{{ auth()->user()->email }}</strong> para confirmar que la dirección es tuya.
        Puedes seguir usando tu cuenta mientras tanto.
    </p>

    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">Reenviar el correo</button>
        </form>
        <a href="{{ route('account.activities.index') }}" class="btn btn-outline">Ir a mis actividades</a>
    </div>
</div>
</main>
@endsection
