<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/dps-logo-header.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="admin-shell" x-data="{ nav: false }" x-on:keydown.escape.window="nav = false">
    {{-- Sólo se ve bajo 900px: en escritorio manda la barra lateral. --}}
    <div class="admin-topbar">
        <a href="{{ route('admin.dashboard') }}" style="display:inline-flex;">
            <img decoding="async" width="400" height="120" src="{{ asset('img/dps-logo-header.png') }}" alt="Panel" style="height:34px;width:auto;">
        </a>

        <button type="button" class="icon-btn"
                x-on:click="nav = !nav"
                x-bind:aria-expanded="nav ? 'true' : 'false'"
                aria-controls="admin-nav"
                x-bind:aria-label="nav ? 'Cerrar menú' : 'Abrir menú'">
            <svg x-show="!nav" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M4 7h16M4 12h16M4 17h16"></path>
            </svg>
            <svg x-show="nav" x-cloak width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M6 6l12 12M18 6L6 18"></path>
            </svg>
        </button>
    </div>

    <aside id="admin-nav" class="admin-side" x-bind:class="nav ? 'abierta' : ''">
        <div style="padding:0 22px 14px;">
            <a href="{{ route('admin.dashboard') }}" style="display:inline-flex;">
                <img loading="lazy" decoding="async" width="400" height="120" src="{{ asset('img/dps-logo-header.png') }}" alt="Panel" style="height:38px;width:auto;">
            </a>
        </div>

        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'on' : '' }}" x-on:click="nav = false">Resumen</a>

        <div class="grp">Moderación</div>
        <a href="{{ route('admin.activities.index') }}" class="{{ request()->routeIs('admin.activities.*') ? 'on' : '' }}">Actividades</a>
        <a href="{{ route('admin.organizations.index') }}" class="{{ request()->routeIs('admin.organizations.*') ? 'on' : '' }}">Organizaciones</a>
        <a href="{{ route('admin.registrations.index') }}" class="{{ request()->routeIs('admin.registrations.*') ? 'on' : '' }}">Inscripciones</a>

        <div class="grp">Contenido</div>
        @foreach (\App\Http\Controllers\Admin\ContentController::menu() as $slug => $label)
            <a href="{{ route('admin.content.index', $slug) }}"
               class="{{ request()->routeIs('admin.content.*') && request()->route('tipo') === $slug ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ route('admin.taxonomies.index') }}" class="{{ request()->routeIs('admin.taxonomies.*') ? 'on' : '' }}">Catálogos</a>

        <div class="grp">Sistema</div>
        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'on' : '' }}">Usuarios</a>
        <a href="{{ route('admin.settings.general') }}" class="{{ request()->routeIs('admin.settings.general*') ? 'on' : '' }}">Configuración</a>
        <a href="{{ route('admin.settings.smtp') }}" class="{{ request()->routeIs('admin.settings.smtp*') ? 'on' : '' }}">Correo (SMTP)</a>
        <a href="{{ route('admin.emails.index') }}" class="{{ request()->routeIs('admin.emails.*') ? 'on' : '' }}">Log de correos</a>

        <div style="margin-top:auto;padding:18px 22px 0;">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:22px;flex-wrap:wrap;">
            <h1 style="font-size:26px;font-weight:800;margin:0;letter-spacing:-.01em;">@yield('title', 'Panel')</h1>
            @yield('actions')
        </div>

        @if (session('ok'))
            <div class="alert alert-ok" style="margin-bottom:18px;">{{ session('ok') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error" style="margin-bottom:18px;">
                {{ session('error') }}
                @if (session('detalle_smtp'))
                    <pre style="margin:10px 0 0;font-size:12px;white-space:pre-wrap;opacity:.85;">{{ session('detalle_smtp') }}</pre>
                @endif
            </div>
        @endif

        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>
