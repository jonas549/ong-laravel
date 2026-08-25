{{--
    Pantalla de error, compartida por todos los códigos.

    No usa `layouts.public` a propósito: la cabecera y el pie leen ajustes y
    menús de la base de datos, y un 500 provocado justamente por la base de
    datos volvería a fallar al pintar la propia página de error. Esta se
    sostiene sola, sólo con la hoja de estilos.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('img/dps-logo-header.png') }}">
    @vite(['resources/css/app.css'])
</head>
<body style="background:var(--bg-warm);display:grid;place-items:center;min-height:100vh;margin:0;">

<div style="width:100%;max-width:520px;padding:32px;box-sizing:border-box;text-align:center;">
    <a href="{{ url('/') }}" style="display:inline-block;margin-bottom:26px;">
        <img src="{{ asset('img/dps-logo-header.png') }}" alt="{{ config('app.name') }}"
             width="400" height="120" style="height:44px;width:auto;">
    </a>

    <div class="card" style="padding:38px 32px;">
        <div style="font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--gris);margin-bottom:10px;">Error {{ $codigo }}</div>

        <h1 style="font-size:27px;font-weight:800;letter-spacing:-.015em;line-height:1.15;margin:0 0 12px;color:var(--ink);text-wrap:pretty;">{{ $titulo }}</h1>

        <p style="font-size:16px;line-height:1.6;color:var(--gris);margin:0 0 26px;text-wrap:pretty;">{{ $texto }}</p>

        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <a href="{{ url('/') }}" class="btn btn-primary">Volver al inicio</a>
            {{-- El destino sale de la cabecera Referer y no de url()->previous(),
                 que lee la sesión: si el 500 lo provocó la base de datos, la
                 propia página de error volvería a caerse al pintarse. --}}
            @php $atras = request()->headers->get('referer'); @endphp
            @if ($atras && $atras !== request()->fullUrl())
                <a href="{{ $atras }}" class="btn btn-outline">Volver atrás</a>
            @else
                <a href="{{ url('/actividades') }}" class="btn btn-outline">Ver actividades</a>
            @endif
        </div>
    </div>
</div>

</body>
</html>
