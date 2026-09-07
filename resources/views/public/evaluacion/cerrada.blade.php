@php $footerCompacto = true; @endphp

@extends('layouts.public')

@section('title', 'La encuesta no está disponible · '.$activity->titulo)

@section('content')
{{--
    Fuera de plazo.

    Esta pantalla existe porque **un 404 aquí sería mentir**. Un 404 es para lo
    que no existe, y quien llega aquí tiene un cartel delante y acaba de
    escanearlo: la actividad existe, la encuesta existe, lo que pasa es que no
    es el momento. Decírselo cuesta una pantalla y evita que se quede pensando
    que el código está roto.

    Lleva `noindex` porque no es contenido: es un estado temporal de una
    dirección que sí lo es.
--}}
@push('head')
    <meta name="robots" content="noindex">
@endpush

<main class="evaluacion-envoltorio">
    <section class="evaluacion-caja evaluacion-gracias card">

        <span class="evaluacion-tic evaluacion-tic-neutro" aria-hidden="true">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
        </span>

        <h1 class="evaluacion-gracias-titulo">
            {{ $estado['estado'] === \App\Services\Evaluaciones::AUN_NO
                ? 'La encuesta todavía no está abierta'
                : 'La encuesta ya está cerrada' }}
        </h1>

        <p class="evaluacion-estas">Actividad</p>
        <p class="evaluacion-actividad">{{ $activity->titulo }}</p>

        <p class="evaluacion-gracias-texto">{{ $estado['mensaje'] }}</p>

        <a class="btn btn-outline evaluacion-gracias-boton" href="{{ route('activities.show', $activity) }}">Ver la actividad</a>

        <a class="textlink evaluacion-gracias-otro" href="{{ route('activities.index') }}">Ver otras actividades</a>
    </section>
</main>
@endsection
