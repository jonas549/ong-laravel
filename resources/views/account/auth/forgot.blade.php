@extends('layouts.public')
@section('title', 'Recupera tu contraseña · ' . config('app.name'))

@php $footerCompacto = true; @endphp

@section('content')

<main style="flex:1;">
{{--
    No está en el prototipo: mi-cuenta.html sólo dibuja el enlace
    "¿Olvidaste tu contraseña?" sin destino. La pantalla reusa la
    composición del login para que no desentone.
--}}
<div class="rise grid-2" style="max-width:1080px;margin:0 auto;padding:72px 32px 110px;display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center;">
    <div>
        <h1 style="font-size:42px;font-weight:800;letter-spacing:-.02em;line-height:1.08;margin:0 0 14px;color:var(--ink);text-wrap:pretty;">Recupera tu contraseña</h1>
        <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 0 28px;max-width:44ch;text-wrap:pretty;">{{ $esAdmin ? 'Escribe el correo de tu cuenta del panel y te enviamos un enlace para crear una contraseña nueva.' : 'Escribe el correo con el que publicaste tu actividad y te enviamos un enlace para crear una contraseña nueva.' }}</p>
        <img loading="lazy" decoding="async" width="1008" height="490" src="{{ asset('img/construyamos-juntos-c2664680.png') }}" alt="" aria-hidden="true"
             style="width:100%;max-width:520px;height:auto;display:block;">
    </div>

    <div class="card" style="padding:34px 32px;">
        <form method="POST" action="{{ route('password.email') }}" style="display:flex;flex-direction:column;gap:18px;">
            @csrf

            <label class="lbl">Correo electrónico
                <input class="fld @error('email') is-invalid @enderror" type="email" name="email"
                       value="@viejo('email')" placeholder="contacto@organizacion.cl"
                       required autofocus autocomplete="email">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="btn btn-primary" style="justify-content:center;">Enviar enlace</button>

            <a class="textlink" href="{{ route($esAdmin ? 'admin.login' : 'account.login') }}"
               style="font-size:13.5px;font-weight:600;text-align:center;">Volver a iniciar sesión</a>
        </form>
    </div>
</div>
</main>
@endsection
