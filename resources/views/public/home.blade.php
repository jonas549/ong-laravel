@extends('layouts.public')

@section('title', 'Día del Patrimonio Social — 4 y 5 de diciembre, Chile 2026')

{{-- Todos los textos de esta página pueden venir del panel, así que se marca
     entera para que ninguna palabra sin espacios pueda desbordar. --}}
@php $homeEditable = true; @endphp

{{--
    El orden y qué secciones se ven salen de la base. Lo que NO sale de la base
    es cómo se ven: cada sección sigue siendo su propio parcial, con la
    maquetación calcada del HTML fuente. Lo que cambió con el bloque F es de
    dónde salen los textos, no cómo se pintan.

    `HomeSection::visibles()` devuelve el orden del catálogo cuando la tabla
    está vacía —o cuando todavía no existe, entre el `git pull` y el `migrate`
    del cron— así que esta lista nunca sale en blanco.
--}}

@section('content')
    @foreach ($secciones as $seccion)
        @include('public.home.sections.'.$seccion->clave, [
            'seccion' => $seccion,
            'borrador' => $borrador ?? false,
        ])
    @endforeach
@endsection
