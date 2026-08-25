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

<div style="width:100%;max-width:400px;padding:24px;">
    <div style="text-align:center;margin-bottom:24px;">
        <img loading="lazy" decoding="async" width="400" height="120" src="{{ asset('img/dps-logo-header.png') }}" alt="{{ config('app.name') }}" style="height:48px;width:auto;">
    </div>

    <div class="card" style="padding:34px;">
        <h1 style="font-weight:800;font-size:22px;margin:0 0 6px;">Panel administrativo</h1>
        <p class="helper" style="margin:0 0 22px;">Acceso restringido al equipo organizador.</p>

        <form method="POST" action="{{ route('admin.login.attempt') }}" style="display:flex;flex-direction:column;gap:16px;">
            @csrf

            <div>
                <label class="helper" for="email" style="display:block;margin-bottom:6px;font-weight:600;">Correo</label>
                <input class="fld @error('email') is-invalid @enderror" type="email" id="email" name="email"
                       value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="helper" for="password" style="display:block;margin-bottom:6px;font-weight:600;">Contraseña</label>
                <input class="fld" type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary" style="justify-content:center;">Entrar</button>
        </form>
    </div>

    <p style="text-align:center;margin-top:18px;font-size:13px;">
        <a class="textlink" href="{{ route('home') }}">← Volver al sitio</a>
    </p>
</div>

</body>
</html>
