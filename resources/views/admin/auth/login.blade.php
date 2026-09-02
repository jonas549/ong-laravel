<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/dps-logo-header.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="background:var(--bg-warm);display:grid;place-items:center;min-height:100vh;">

{{-- box-sizing aquí y no global: sin él, el padding se sumaba al 100% y a
     375px la caja desbordaba 24px. --}}
<div style="width:100%;max-width:400px;padding:24px;box-sizing:border-box;">
    <div style="text-align:center;margin-bottom:24px;">
        <img loading="lazy" decoding="async" width="400" height="120" src="{{ asset('img/dps-logo-header.png') }}" alt="{{ config('app.name') }}" style="height:48px;width:auto;">
    </div>

    <div class="card" style="padding:34px;">
        <h1 style="font-weight:800;font-size:22px;margin:0 0 6px;">Panel administrativo</h1>
        <p class="helper" style="margin:0 0 22px;">Acceso restringido al equipo organizador.</p>

        {{-- Esta pantalla no usa layout, así que el aviso hay que pintarlo aquí:
             sin esto se perdía el "ya puedes entrar" tras restablecer. --}}
        @if (session('ok'))
            <div class="alert alert-ok" style="margin-bottom:18px;">{{ session('ok') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error" style="margin-bottom:18px;">{{ session('error') }}</div>
        @endif

        <x-puerta-equivocada style="margin-bottom:18px;" />

        <form method="POST" action="{{ route('admin.login.attempt') }}" style="display:flex;flex-direction:column;gap:16px;">
            @csrf

            <div>
                <label class="helper" for="email" style="display:block;margin-bottom:6px;font-weight:600;">Correo</label>
                {{-- Si viene del otro acceso, el correo ya lo escribió allí y el
                     foco salta a la contraseña: es lo único que le queda. --}}
                <input class="fld @error('email') is-invalid @enderror" type="email" id="email" name="email"
                       value="{{ \App\Support\Formulario::viejo('email', $correoSugerido ?? '') }}"
                       required @empty($correoSugerido) autofocus @endempty autocomplete="username">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="helper" for="password" style="display:block;margin-bottom:6px;font-weight:600;">Contraseña</label>
                <input class="fld" type="password" id="password" name="password"
                       required @if ($correoSugerido ?? null) autofocus @endif autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary" style="justify-content:center;">Entrar</button>

            <a class="textlink" href="{{ route('admin.password.request') }}"
               style="font-size:13px;font-weight:600;text-align:center;">¿Olvidaste tu contraseña?</a>
        </form>
    </div>

    <p style="text-align:center;margin-top:18px;font-size:13px;">
        <a class="textlink" href="{{ route('home') }}">← Volver al sitio</a>
    </p>
</div>

</body>
</html>
