@extends('layouts.public')
@section('title', 'Accede a tu cuenta · ' . config('app.name'))

{{-- mi-cuenta.html lleva el footer compacto. --}}
@php $footerCompacto = true; @endphp

@section('content')

<main style="flex:1;">
{{-- PANTALLA 0 — LOGIN de mi-cuenta.html --}}
<div class="rise grid-2" style="max-width:1080px;margin:0 auto;padding:72px 32px 110px;display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center;">
    <div>
        <h1 style="font-size:42px;font-weight:800;letter-spacing:-.02em;line-height:1.08;margin:0 0 14px;color:var(--ink);text-wrap:pretty;">Accede a tu cuenta</h1>
        <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 0 28px;max-width:44ch;text-wrap:pretty;">Desde aquí podrás publicar, editar y hacer seguimiento a tus actividades del Día del Patrimonio Social.</p>
        <img loading="lazy" decoding="async" width="1008" height="490" src="{{ asset('img/construyamos-juntos-c2664680.png') }}" alt="" aria-hidden="true"
             style="width:100%;max-width:520px;height:auto;display:block;">
    </div>

    <div class="card" style="padding:34px 32px;">
        <form method="POST" action="{{ route('account.login.attempt') }}"
              style="display:flex;flex-direction:column;gap:18px;">
            @csrf

            <label class="lbl">Correo electrónico
                <input class="fld @error('email') is-invalid @enderror" type="email" name="email"
                       value="{{ old('email') }}" placeholder="contacto@organizacion.cl"
                       required autofocus autocomplete="email">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Contraseña
                <input class="fld @error('password') is-invalid @enderror" type="password" name="password"
                       placeholder="••••••••" required autocomplete="current-password">
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="btn btn-primary" style="justify-content:center;">Ingresar</button>

            <a class="textlink" href="{{ route('password.request') }}"
               style="font-size:13.5px;font-weight:600;text-align:center;">¿Olvidaste tu contraseña?</a>
        </form>
    </div>
</div>
</main>
@endsection
