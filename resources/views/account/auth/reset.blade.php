@extends('layouts.public')
@section('title', 'Crea tu contraseña nueva · ' . config('app.name'))

@php $footerCompacto = true; @endphp

@section('content')

<main style="flex:1;">
{{-- Segundo paso de la recuperación; tampoco existe en el prototipo. --}}
<div class="rise grid-2" style="max-width:1080px;margin:0 auto;padding:72px 32px 110px;display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center;">
    <div>
        <h1 style="font-size:42px;font-weight:800;letter-spacing:-.02em;line-height:1.08;margin:0 0 14px;color:var(--ink);text-wrap:pretty;">Crea tu contraseña nueva</h1>
        <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 0 28px;max-width:44ch;text-wrap:pretty;">Elige una contraseña de al menos 8 caracteres. Después podrás entrar con ella a tu cuenta.</p>
        <img loading="lazy" decoding="async" width="1008" height="490" src="{{ asset('img/construyamos-juntos-c2664680.png') }}" alt="" aria-hidden="true"
             style="width:100%;max-width:520px;height:auto;display:block;">
    </div>

    <div class="card" style="padding:34px 32px;">
        <form method="POST" action="{{ route('password.update') }}" style="display:flex;flex-direction:column;gap:18px;">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label class="lbl">Correo electrónico
                <input class="fld @error('email') is-invalid @enderror" type="email" name="email"
                       value="@viejo('email', $email)" required autocomplete="email">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Contraseña nueva
                <input class="fld @error('password') is-invalid @enderror" type="password" name="password"
                       placeholder="••••••••" required autocomplete="new-password">
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Repite la contraseña
                <input class="fld" type="password" name="password_confirmation"
                       placeholder="••••••••" required autocomplete="new-password">
            </label>

            <button type="submit" class="btn btn-primary" style="justify-content:center;">Guardar contraseña</button>
        </form>
    </div>
</div>
</main>
@endsection
