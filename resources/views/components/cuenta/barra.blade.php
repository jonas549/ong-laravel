@props(['miga' => null])

{{--
    La barra superior de «Mi cuenta».

    Estaba escrita dentro de «Mis actividades» y sólo se veía allí, así que
    desde el editor, los participantes, las evaluaciones o el perfil no había
    forma de llegar al kit ni de cerrar sesión sin volver antes al listado. Se
    saca aquí para que sea la misma en las seis pantallas (Q4).

    Arriba, las migas de cada pantalla, que sí cambian, y la cuenta: perfil y
    salir. Debajo, las secciones del panel.

    Punto 7 del 23/09: las secciones iban sueltas entre las migas, con la
    misma letra pequeña y gris, y no se distinguían. Ahora van agrupadas bajo
    «Secciones» —el título que sugirió el cliente—, como botones, y la de la
    pantalla en la que se está queda marcada.

    Nada de esto está en el prototipo: sin estos enlaces no hay forma de llegar
    al perfil ni de salir.
--}}

@php
    // El mismo ajuste del punto 25, no uno nuevo: Configuración → General.
    $kitDifusion = \App\Models\Setting::get('kit_difusion_url');

    // Los inscritos de una actividad son de «Inscritos», aunque su dirección
    // cuelgue de /actividades.
    $secciones = [
        ['Mis actividades', route('account.activities.index'), request()->routeIs('account.activities.*')],
        ['Inscritos', route('account.participants.resumen'), request()->routeIs('account.participants.*')],
        ['Evaluaciones', route('account.evaluaciones.index'), request()->routeIs('account.evaluaciones.*')],
    ];
@endphp

<div style="margin-bottom:26px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
        <div class="crumb">{{ $miga ?? $slot }}</div>

        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <a class="crumb" href="{{ route('account.perfil') }}">Mi perfil</a>
            <span class="crumb" aria-hidden="true">·</span>
            <form method="POST" action="{{ route('account.logout') }}">
                @csrf
                <button type="submit" class="crumb"
                        style="background:none;border:0;padding:0;cursor:pointer;font-family:var(--font);">Cerrar sesión</button>
            </form>
        </div>
    </div>

    <nav class="secciones" aria-labelledby="secciones-titulo">
        <span id="secciones-titulo" class="secciones-titulo">Secciones</span>
        <div class="secciones-lista">
            @foreach ($secciones as [$texto, $destino, $activa])
                <a href="{{ $destino }}" @class(['seccion', 'seccion--activa' => $activa])
                   @if ($activa) aria-current="page" @endif>{{ $texto }}</a>
            @endforeach

            {{--
                Vacío no pinta nada. Un botón que no lleva a ninguna parte es
                peor que no tenerlo, y así la ONG lo enciende cuando la carpeta
                esté lista sin que nadie toque el código. Es otro sitio: otra
                pestaña.
            --}}
            @if ($kitDifusion)
                <a href="{{ $kitDifusion }}" class="seccion" target="_blank" rel="noopener">Kit de difusión</a>
            @endif
        </div>
    </nav>
</div>
