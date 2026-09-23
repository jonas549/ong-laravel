@extends('layouts.public')
@section('title', 'Imagen de difusión · ' . config('app.name'))

{{-- mi-cuenta.html lleva el footer compacto. --}}
@php $footerCompacto = true; @endphp

{{--
    La imagen de difusión de una actividad, lista para Instagram (1080×1350).

    Se dibuja en el navegador —ver `resources/js/difusion.js`— con la
    plantilla de la ONG y los textos de `DatosDifusion`. Aquí sólo se enseña
    y se ofrece descargarla o, en el teléfono, compartirla directamente.
--}}

@section('content')

<main style="flex:1;">
<div class="rise" style="max-width:1080px;margin:0 auto;padding:34px 32px 96px;">

    <x-cuenta.barra>
        <a href="{{ route('home') }}">Inicio</a> →
        <a href="{{ route('account.activities.index') }}">Mi cuenta</a> → Imagen de difusión
    </x-cuenta.barra>

    <div class="difusion"
         x-data="difusion(@js($datos), @js(asset('img/difusion')), @js(asset('img/dps-banner-2560x1080-010726.jpg')))">

        <div class="difusion-texto">
            <h1 style="font-family:var(--font-title);font-size:32px;font-weight:800;letter-spacing:-.02em;margin:0 0 8px;color:var(--ink);">
                Imagen de difusión
            </h1>
            <p style="font-size:15.5px;line-height:1.6;color:var(--gris);margin:0 0 20px;max-width:46ch;">
                Una imagen lista para publicar en Instagram y otras redes con los datos de
                <strong style="color:var(--ink);">{{ $activity->titulo }}</strong>.
                Si cambias la actividad, vuelve aquí y la imagen saldrá actualizada.
            </p>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a class="btn btn-primary" x-bind:href="url || null" x-bind:download="archivo?.name"
                   x-bind:class="estado !== 'lista' && 'is-disabled'" x-bind:aria-disabled="estado !== 'lista'"
                   data-descargar>Descargar imagen</a>
                <button type="button" class="btn btn-outline" x-show="puedeCompartir" x-cloak
                        x-on:click="compartir()">Compartir</button>
                <a class="btn btn-outline" href="{{ route('account.activities.edit', $activity) }}">Editar actividad</a>
            </div>

            <p class="field-error" x-show="estado === 'error'" x-cloak style="margin-top:14px;">
                No se pudo preparar la imagen. Recarga la página e inténtalo de nuevo.
            </p>
        </div>

        <div class="difusion-vista">
            <div class="difusion-cargando" x-show="estado === 'dibujando'">Preparando la imagen…</div>
            <img x-show="estado === 'lista'" x-cloak x-bind:src="url" data-difusion-imagen
                 alt="Imagen de difusión de {{ $activity->titulo }}" width="1080" height="1350">
        </div>
    </div>
</div>
</main>
@endsection
