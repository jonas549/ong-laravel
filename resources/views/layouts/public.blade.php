<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta', 'Participa como voluntario, visita actividades solidarias o comparte la tuya.')">

    <link rel="icon" href="{{ asset('img/dps-logo-header.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
<div style="overflow-x:hidden;">

    @include('partials.public.header')

    @if (session('ok') || session('error'))
        <div style="max-width:1180px;margin:18px auto 0;padding:0 40px;">
            @if (session('ok'))
                <div class="alert alert-ok">{{ session('ok') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
        </div>
    @endif

    @yield('content')

    @include('partials.public.footer')

</div>
@stack('scripts')
</body>
</html>
