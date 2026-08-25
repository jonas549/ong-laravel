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

    @php
        // Lo que cada vista declare manda; lo de Configuracion > SEO es el
        // valor por defecto para las que no declaran nada.
        $seoTitulo = trim($__env->yieldContent('title')) ?: (\App\Models\Setting::get('seo_titulo') ?: config('app.name'));
        $seoDescripcion = trim($__env->yieldContent('meta')) ?: (\App\Models\Setting::get('seo_descripcion') ?: '');
        $seoImagen = \App\Models\Setting::get('seo_imagen');
    @endphp

    <title>{{ $seoTitulo }}</title>
    <meta name="description" content="{{ $seoDescripcion }}">

    @unless (\App\Models\Setting::get('seo_indexable', true))
        {{-- Mientras el sitio no esta listo, que no lo indexe nadie. --}}
        <meta name="robots" content="noindex, nofollow">
    @endunless

    {{-- Lo que se ve al compartir el enlace en redes o en WhatsApp. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ \App\Models\Setting::get('sitio_nombre') ?: config('app.name') }}">
    <meta property="og:title" content="{{ $seoTitulo }}">
    <meta property="og:description" content="{{ $seoDescripcion }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($seoImagen)
        <meta property="og:image" content="{{ \Illuminate\Support\Str::startsWith($seoImagen, ['http://', 'https://']) ? $seoImagen : asset($seoImagen) }}">
    @endif
    <meta name="twitter:card" content="{{ $seoImagen ? 'summary_large_image' : 'summary' }}">

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
