@extends('layouts.account')
@section('title', 'Accede a tu cuenta')

@section('content')
<div style="max-width:420px;margin:40px auto 0;">
    <div class="card" style="padding:38px;">
        <h1 style="font-weight:800;font-size:26px;margin:0 0 8px;">Accede a tu cuenta</h1>
        <p class="helper" style="margin:0 0 24px;">Gestiona tus actividades y revisa quién se inscribió.</p>

        <form method="POST" action="{{ route('account.login.attempt') }}" style="display:flex;flex-direction:column;gap:16px;">
            @csrf

            <div>
                <label class="helper" for="email" style="display:block;margin-bottom:6px;font-weight:600;">Correo electrónico</label>
                <input class="fld @error('email') is-invalid @enderror" type="email" id="email" name="email"
                       value="{{ old('email') }}" placeholder="contacto@organizacion.cl" required autofocus autocomplete="email">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="helper" for="password" style="display:block;margin-bottom:6px;font-weight:600;">Contraseña</label>
                <input class="fld @error('password') is-invalid @enderror" type="password" id="password" name="password"
                       placeholder="••••••••" required autocomplete="current-password">
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <label style="display:flex;align-items:center;gap:9px;font-size:14px;color:var(--gris-700);cursor:pointer;">
                <input type="checkbox" name="remember" value="1"> Mantener sesión iniciada
            </label>

            <button type="submit" class="btn btn-primary" style="justify-content:center;">Entrar</button>
        </form>

        <hr style="border:0;border-top:1px solid var(--linea);margin:24px 0 18px;">
        <p class="helper" style="margin:0;">
            ¿Todavía no publicas? <a class="textlink" href="{{ route('publish.create') }}">Publica tu actividad</a> y creamos tu cuenta en el mismo paso.
        </p>
    </div>
</div>
@endsection
