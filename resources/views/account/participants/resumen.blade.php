@extends('layouts.public')
@section('title', 'Inscritos · ' . config('app.name'))

{{-- mi-cuenta.html lleva el footer compacto. --}}
@php $footerCompacto = true; @endphp

{{--
    «Inscritos», la sección de la barra (punto 7 del 23/09).

    Una fila por actividad con inscripción y cuántas personas tiene. La lista
    de cada una —buscar, dar de baja, exportar, los cupos— sigue en su propia
    pantalla; esto sólo lleva hasta ella.
--}}

@section('content')

<main style="flex:1;">
<div class="rise" style="max-width:1080px;margin:0 auto;padding:34px 32px 96px;">

    <x-cuenta.barra>
        <a href="{{ route('home') }}">Inicio</a> →
        <a href="{{ route('account.activities.index') }}">Mi cuenta</a> → Inscritos
    </x-cuenta.barra>

    <h1 style="font-family:var(--font-title);font-size:34px;font-weight:800;letter-spacing:-.02em;margin:0 0 8px;color:var(--ink);">
        Inscritos en tus actividades
    </h1>
    <p style="font-size:15.5px;color:var(--gris);margin:0 0 28px;max-width:62ch;">
        Las personas que reservaron su cupo desde el sitio web. Entra en cada actividad para ver la lista, exportarla o ajustar los cupos.
    </p>

    @if ($actividades->isEmpty())
        <section class="card" style="padding:34px;text-align:center;color:var(--gris);">
            Ninguna de tus actividades pide inscripción previa, así que no hay inscritos que mostrar.
        </section>
    @else
        <div style="display:flex;flex-direction:column;gap:12px;">
            @foreach ($actividades as $a)
                <a href="{{ route('account.participants.index', $a) }}" class="card" data-inscritos-actividad
                   style="padding:18px 22px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:220px;">
                        <div style="font-size:16.5px;font-weight:700;color:var(--ink);margin-bottom:3px;">{{ $a->titulo }}</div>
                        <div class="helper" style="margin:0;">{{ $a->resumen_fecha_lugar }} · {{ $a->estado_label }}</div>
                    </div>
                    <div style="flex:none;text-align:right;">
                        <div style="font-size:24px;font-weight:800;color:var(--ink);font-variant-numeric:tabular-nums;line-height:1;">{{ $a->inscritos }}</div>
                        <div class="helper" style="margin:2px 0 0;">{{ $a->inscritos === 1 ? 'inscrito' : 'inscritos' }}</div>
                    </div>
                    <span style="flex:none;font-size:13.5px;font-weight:600;color:var(--naranjo);">Ver lista →</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
</main>
@endsection
