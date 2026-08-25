@extends('layouts.public')
@section('title', 'Actividad enviada · ' . config('app.name'))

{{-- mi-cuenta.html y publicar-actividad.html llevan el footer compacto. --}}
@php $footerCompacto = true; @endphp

@section('content')
{{-- PASO 5 — ENVIADO de publicar-actividad.html (líneas 416-461). --}}
<div x-data="{ paso: 5 }">
    @include('public.publish.partials.pasos', ['navegable' => false])
</div>

<main style="flex:1;">

    <div class="rise" style="max-width:820px;margin:0 auto;padding:64px 32px 96px;">
        <div style="text-align:center;margin-bottom:36px;">
            <img loading="lazy" decoding="async" src="{{ asset('img/logo-corazon-15f12e4a.png') }}" alt="" aria-hidden="true"
                 style="width:200px;max-width:100%;height:auto;display:block;margin:0 auto 22px;filter:drop-shadow(0 14px 26px rgba(0,0,0,.14));">
            <h1 style="font-size:38px;font-weight:800;letter-spacing:-.02em;line-height:1.12;margin:0 0 14px;color:var(--ink);text-wrap:pretty;">¡Gracias por sumar tu iniciativa al Día del Patrimonio Social!</h1>
            <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 auto;max-width:52ch;text-wrap:pretty;">Gracias por abrir sus puertas y ser parte del movimiento que construye el patrimonio vivo de nuestras comunidades. Revisaremos la información y te escribiremos con el resultado.</p>
        </div>

        <div style="background:#fff;border:1px solid var(--linea);border-radius:24px;padding:26px 28px;box-shadow:0 18px 40px -32px rgba(0,0,0,.22);margin-bottom:18px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
            <span style="flex:none;display:grid;place-items:center;width:56px;height:56px;border-radius:16px;background:var(--naranjo-100);color:var(--naranjo);">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l8.8 8.8 8.8-8.8a5.5 5.5 0 0 0 0-7.8z"></path></svg>
            </span>
            <div style="flex:1;min-width:220px;">
                <div style="font-family:var(--font-title);font-size:20px;font-weight:800;color:var(--ink);margin-bottom:4px;">{{ $activity->organization->nombre }} — {{ $activity->titulo }}</div>
                <div style="font-size:14.5px;color:var(--gris);">{{ $activity->resumen_fecha_lugar }}</div>
            </div>
            <span style="flex:none;font-size:13px;font-weight:600;padding:7px 14px;border-radius:999px;background:#fff8e6;color:#8a6a00;border:1.5px solid #f6e0c6;">Estamos revisando tu actividad</span>
        </div>

        <div style="background:#fff;border:1px solid var(--linea);border-radius:24px;padding:28px;box-shadow:0 18px 40px -32px rgba(0,0,0,.22);margin-bottom:18px;">
            <div class="seclabel" style="margin-bottom:14px;">¿Qué sigue?</div>
            <div style="display:flex;flex-direction:column;gap:14px;">
                <div style="display:flex;gap:13px;align-items:flex-start;">
                    <span style="flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo-600);font-size:12.5px;font-weight:800;">1</span>
                    <div style="font-size:15.5px;line-height:1.6;color:var(--gris-700);">Revisaremos tu actividad y te escribiremos con el resultado.</div>
                </div>
                <div style="display:flex;gap:13px;align-items:flex-start;">
                    <span style="flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo-600);font-size:12.5px;font-weight:800;">2</span>
                    <div style="font-size:15.5px;line-height:1.6;color:var(--gris-700);">Si es aprobada, recibirás la URL y el QR para compartir con tus asistentes: así quedan registrados como participantes y pueden evaluar su experiencia.</div>
                </div>
            </div>
        </div>

        <div style="background:var(--naranjo-100);border:1.5px solid #f6e0c6;border-radius:24px;padding:28px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:240px;">
                <div style="font-family:var(--font-title);font-size:21px;font-weight:800;color:var(--ink);margin-bottom:6px;">Difunde la campaña e invita a otros a participar</div>
                <div style="font-size:14.5px;line-height:1.6;color:var(--gris-700);">Imágenes, stickers y mensajes listos para tus canales.</div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                @if ($kitDifusion = \App\Models\Setting::get('kit_difusion_url'))
                    <a href="{{ $kitDifusion }}" target="_blank" rel="noopener" class="btn btn-primary">Descargar kit de difusión</a>
                @else
                    {{-- Sin material todavía: falta definir qué entrega este botón. --}}
                    <button type="button" class="btn btn-primary" disabled title="Pendiente de definir">Descargar kit de difusión</button>
                @endif
                <a href="{{ route('publish.create') }}" class="btn btn-outline">Sumar otra actividad</a>
            </div>
        </div>
    </div>
</main>
@endsection
