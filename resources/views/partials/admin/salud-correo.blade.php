{{--
    Aviso de estado del correo.

    Va arriba del registro de correos y de la pantalla de SMTP porque los dos
    fallos que dejaron el sistema mudo —un mailer que escribe en un archivo y
    una cola sin worker— no se ven en ninguna tabla. La tabla se veía normal;
    lo que fallaba estaba fuera de ella.

    Espera `$salud` con las claves transporte, cola y plantillas.
--}}
@php
    $t = $salud['transporte'];
    $c = $salud['cola'];
    $p = $salud['plantillas'];

    $problemas = [];

    if (! $t['entrega_de_verdad']) {
        $problemas[] = [
            'titulo' => 'El correo no está saliendo del servidor.',
            'texto' => 'El mailer activo es «'.($t['transporte'] ?: $t['mailer_efectivo']).'», que no entrega a nadie: '
                .'escribe el correo en el servidor y sigue como si nada. Todo lo que aparezca aquí como enviado, no salió.',
            'accion' => ['Configurar el SMTP', route('admin.settings.smtp')],
        ];
    }

    if (($c['medible'] ?? false) && $c['atascada']) {
        $espera = \Carbon\CarbonInterval::seconds($c['espera_segundos'])->cascade()->forHumans(short: true, parts: 2);
        $problemas[] = [
            'titulo' => 'La cola está parada.',
            'texto' => 'Hay '.$c['pendientes'].($c['pendientes'] === 1 ? ' correo esperando' : ' correos esperando').' y el más viejo lleva '.$espera.' sin moverse. '
                .'Todo el correo de este sitio pasa por la cola, así que ahora mismo no sale ninguno. '
                .'Falta el cron del servidor: * * * * * php artisan schedule:run',
            'accion' => null,
        ];
    }

    // Se calcula aquí y no en un @if en línea: Blade se atraganta con los
    // paréntesis anidados dentro de una directiva y se deja sin compilar el
    // @endif que viene después.
    $pendientes = ($c['medible'] ?? false) ? $c['pendientes'] : 0;
    $enCamino = $pendientes ? ", con {$pendientes} en cola" : '';

    if (($p['medible'] ?? false) && $p['faltan']) {
        $problemas[] = [
            'titulo' => 'Faltan plantillas de correo.',
            'texto' => 'No están en la base de datos: '.implode(', ', $p['faltan']).'. '
                .'Los correos automáticos que las usan no se envían y no dejan rastro.',
            'accion' => ['Restaurarlas', route('admin.templates.index')],
        ];
    }
@endphp

@foreach ($problemas as $problema)
    <div class="alert alert-error" style="margin-bottom:16px;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:260px;">
            <strong style="display:block;margin-bottom:4px;">{{ $problema['titulo'] }}</strong>
            <span style="font-size:13px;line-height:1.55;">{{ $problema['texto'] }}</span>
        </div>
        @if ($problema['accion'])
            <a class="btn btn-outline btn-sm" href="{{ $problema['accion'][1] }}">{{ $problema['accion'][0] }}</a>
        @endif
    </div>
@endforeach

@if (! $problemas)
    {{-- Dice cómo está configurado, no que el correo esté llegando: eso sólo lo
         sabe quien hable con el servidor, y para eso está el botón de prueba. --}}
    <div class="alert alert-ok" style="margin-bottom:16px;font-size:13px;">
        Configurado para salir por <strong>{{ $t['transporte'] }}</strong>, como
        <strong>{{ $t['remitente'] }}</strong>{{ $enCamino }}.
        Para saber si el servidor lo acepta, usa el envío de prueba o corre
        <code>php artisan dps:correo</code> en el servidor.
    </div>
@endif
