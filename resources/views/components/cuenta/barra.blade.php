@props(['miga' => null])

{{--
    La barra superior de «Mi cuenta».

    Estaba escrita dentro de «Mis actividades» y sólo se veía allí, así que
    desde el editor, los participantes, las evaluaciones o el perfil no había
    forma de llegar al kit ni de cerrar sesión sin volver antes al listado. Se
    saca aquí para que sea la misma en las seis pantallas (Q4).

    A la izquierda van las migas de cada pantalla, que sí cambian; a la
    derecha, lo que no cambia.

    Nada de esto está en el prototipo: sin estos enlaces no hay forma de llegar
    al perfil ni de salir.
--}}

@php
    // El mismo ajuste del punto 25, no uno nuevo: Configuración → General.
    $kitDifusion = \App\Models\Setting::get('kit_difusion_url');
@endphp

<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px;">
    <div class="crumb">{{ $miga ?? $slot }}</div>

    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        {{--
            Vacío no pinta nada. Un botón que no lleva a ninguna parte es peor
            que no tenerlo, y así la ONG lo enciende cuando la carpeta esté
            lista sin que nadie toque el código.
        --}}
        @if ($kitDifusion)
            <a class="crumb" href="{{ $kitDifusion }}" target="_blank" rel="noopener">Kit de difusión</a>
            <span class="crumb" aria-hidden="true">·</span>
        @endif

        {{-- Las evaluaciones de sus actividades. Es una pantalla nueva y no
             estaba en el prototipo: sin este enlace no hay forma de llegar. --}}
        <a class="crumb" href="{{ route('account.evaluaciones.index') }}">Evaluaciones</a>
        <span class="crumb" aria-hidden="true">·</span>
        <a class="crumb" href="{{ route('account.perfil') }}">Mi perfil</a>
        <span class="crumb" aria-hidden="true">·</span>
        <form method="POST" action="{{ route('account.logout') }}">
            @csrf
            <button type="submit" class="crumb"
                    style="background:none;border:0;padding:0;cursor:pointer;font-family:var(--font);">Cerrar sesión</button>
        </form>
    </div>
</div>
