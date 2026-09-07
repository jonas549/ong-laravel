@php $footerCompacto = true; @endphp

@extends('layouts.public')

@section('title', '¡Gracias por ser parte del Día del Patrimonio Social!')
@section('meta', 'Gracias por evaluar tu experiencia en el Día del Patrimonio Social.')

@section('content')
{{--
    La confirmación (P2 del wireframe).

    El copy es el del wireframe, con el nombre del sitio resuelto desde
    Configuración en vez de escrito a mano: si la ONG cambia el dominio, esta
    pantalla no se queda diciendo el viejo.
--}}
<main class="evaluacion-envoltorio">
    <section class="evaluacion-caja evaluacion-gracias card">

        <span class="evaluacion-tic" aria-hidden="true">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </span>

        <h1 class="evaluacion-gracias-titulo">¡Muchas gracias por ser parte del Día del Patrimonio Social!</h1>

        @if ($repetida)
            {{--
                Ya había evaluado esta actividad.

                **No es un error y no se le enseña como tal.** Hizo lo que tenía
                que hacer; quizá volvió a escanear el cartel, o lo intentó desde
                otro teléfono. Se le dice en una línea, en el mismo sitio donde
                habría visto el agradecimiento, y no se le manda de vuelta a un
                formulario a repetir lo que ya escribió.
            --}}
            <p class="evaluacion-gracias-nota">Ya teníamos tu evaluación de esta actividad, así que no la hemos duplicado.</p>
        @endif

        <p class="evaluacion-gracias-texto">
            Encuentra más oportunidades para participar durante todo el año y contribuye
            al bienestar de más personas.
        </p>

        <a class="btn btn-outline evaluacion-gracias-boton" href="{{ route('home') }}">Ir al sitio</a>

        {{--
            Un segundo enlace, al listado.

            El wireframe sólo pide «Ir al sitio», y ése se respeta tal cual. Éste
            va debajo y en pequeño porque quien acaba de evaluar es exactamente
            quien más cerca está de apuntarse a otra: mandarlo a la portada a
            buscarla es perder el momento.
        --}}
        <a class="textlink evaluacion-gracias-otro" href="{{ route('activities.index') }}">Ver otras actividades</a>
    </section>
</main>
@endsection
