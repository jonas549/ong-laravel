@extends('layouts.public')
@section('title', 'Soy parte del Día del Patrimonio Social · ' . config('app.name'))

{{--
    Punto 7 del 30/09: lo que ve quien acaba de inscribirse en una actividad.

    Arriba, la confirmación (sólo recién inscrito: los datos vienen de la
    sesión). Debajo, la imagen fija «Soy parte del DPS» para descargarla o
    compartirla, el texto del post y el kit de difusión. Compartir funciona
    igual que en la imagen de difusión del organizador: ver
    resources/js/compartir-imagen.js.
--}}

@section('content')
<main style="flex:1;">
<div class="rise" style="max-width:1080px;margin:0 auto;padding:44px 32px 96px;">

    @if ($inscrito)
        <div class="soy-parte-confirmacion" data-confirmacion-inscripcion role="status">
            <span class="soy-parte-check" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
            </span>
            <div>
                <h1 style="font-family:var(--font-title);font-size:30px;font-weight:800;letter-spacing:-.02em;line-height:1.15;margin:0 0 6px;color:var(--ink);">
                    Listo, guardamos tu inscripción. Te esperamos.
                </h1>
                <p style="font-size:15.5px;line-height:1.6;color:var(--gris);margin:0;">
                    Te inscribiste en <strong style="color:var(--ink);">{{ $activity->titulo }}</strong>.
                    La confirmación va camino de <strong style="color:var(--ink);">{{ $inscrito['correo'] }}</strong>.
                </p>
            </div>
        </div>
    @endif

    <div class="difusion"
         x-data="soyParte(@js(asset('img/soy-parte-del-dps.jpg')), 'soy-parte-del-dia-del-patrimonio-social.jpg', @js($texto))">

        <div class="difusion-texto">
            <h2 style="font-family:var(--font-title);font-size:28px;font-weight:800;letter-spacing:-.02em;margin:0 0 8px;color:var(--ink);">
                Cuenta que eres parte
            </h2>
            <p style="font-size:15.5px;line-height:1.6;color:var(--gris);margin:0 0 20px;max-width:46ch;">
                Comparte en tus redes que te sumas a la celebración nacional del Día del Patrimonio Social
                e invita a otras personas a participar.
            </p>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                {{-- Un enlace de verdad al archivo: funciona aunque no cargue
                     el JavaScript. Con él cargado, descarga lo que ya está en
                     memoria. --}}
                <a class="btn btn-primary" href="{{ asset('img/soy-parte-del-dps.jpg') }}"
                   download="soy-parte-del-dia-del-patrimonio-social.jpg" data-descargar
                   x-on:click="if (archivo) { $event.preventDefault(); descargar(); }">Descargar imagen</a>
                <button type="button" class="btn btn-outline" x-show="archivo && modo !== 'descargar'" x-cloak
                        x-on:click="compartir()" data-compartir
                        x-text="modo === 'compartir' ? 'Compartir' : 'Copiar imagen'">Compartir</button>
            </div>

            <p class="helper" x-show="aviso" x-cloak x-text="aviso" data-aviso-compartir
               style="margin-top:12px;color:var(--naranjo-600);font-weight:600;" role="status"></p>

            <div class="soy-parte-post">
                <div style="font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--naranjo-600);margin-bottom:6px;">Texto para tu publicación</div>
                <p data-texto-post style="margin:0 0 10px;font-size:15px;line-height:1.55;color:var(--ink);">{{ $texto }}</p>
                <button type="button" class="btn btn-outline btn-sm" x-on:click="copiarTexto()" data-copiar-texto>
                    <span x-text="textoCopiado ? 'Texto copiado' : 'Copiar texto'">Copiar texto</span>
                </button>
            </div>

            @if ($kit)
                <div class="soy-parte-kit">
                    <div>
                        <div style="font-weight:700;color:var(--ink);margin-bottom:2px;">Kit de difusión</div>
                        <div class="helper">Más piezas para invitar a tu comunidad.</div>
                    </div>
                    <a class="btn btn-outline btn-sm" href="{{ $kit }}" target="_blank" rel="noopener" data-kit>Descargar el kit</a>
                </div>
            @endif

            <a class="textlink" href="{{ route('activities.show', $activity) }}" style="display:inline-block;margin-top:22px;">← Volver a la actividad</a>
        </div>

        <div class="difusion-vista">
            <img src="{{ asset('img/soy-parte-del-dps.jpg') }}" width="1080" height="1350"
                 alt="Soy parte del Día del Patrimonio Social. ¡Súmate!" data-imagen-soy-parte>
        </div>
    </div>
</div>
</main>
@endsection
