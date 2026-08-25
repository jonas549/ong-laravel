{{--
    El HTML fuente no usa un footer único: index.html trae el grande (redes,
    "Volver arriba", crédito de ilustraciones) y mi-cuenta.html y
    publicar-actividad.html traen uno compacto de una sola fila. Esos dos
    además llevan el body en --bg-warm, mientras index.html lo deja blanco.

    Las vistas de esos dos lo piden declarando, fuera de la sección:
        @php $footerCompacto = true; @endphp

    Con él vienen también el envoltorio en columna que necesita el
    margin-top:auto del footer y la escala de botón de esas dos páginas.
--}}
@php $footerCompacto = $footerCompacto ?? false; @endphp
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
<body @class(['fondo-calido' => $footerCompacto])>
<div @class(['escala-form' => $footerCompacto])
     style="overflow-x:hidden;@if ($footerCompacto) min-height:100vh;display:flex;flex-direction:column; @endif">

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

    @include($footerCompacto ? 'partials.public.footer-compact' : 'partials.public.footer')

</div>
@stack('scripts')
</body>
</html>
