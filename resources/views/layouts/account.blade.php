<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mi cuenta') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/dps-logo-header.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="background:var(--bg-warm);">

<header style="background:#fff;border-bottom:1px solid var(--linea);">
    <div style="max-width:1180px;margin:0 auto;padding:14px 40px;display:flex;align-items:center;gap:20px;">
        <a href="{{ route('home') }}" style="display:inline-flex;">
            <img src="{{ asset('img/dps-logo-header.png') }}" alt="{{ config('app.name') }}" style="height:42px;width:auto;">
        </a>
        <span style="font-size:14px;color:var(--gris);">Mi cuenta</span>

        @auth
            <div style="margin-left:auto;display:flex;align-items:center;gap:14px;">
                <span style="font-size:14px;color:var(--gris-700);">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('account.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Cerrar sesión</button>
                </form>
            </div>
        @endauth
    </div>
</header>

<main style="max-width:1180px;margin:0 auto;padding:32px 40px 80px;">
    @if (session('ok'))
        <div class="alert alert-ok" style="margin-bottom:20px;">{{ session('ok') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error" style="margin-bottom:20px;">{{ session('error') }}</div>
    @endif

    @yield('content')
</main>

</body>
</html>
