@extends('layouts.public')
@section('title', 'Mis actividades · ' . config('app.name'))

{{-- mi-cuenta.html lleva el footer compacto. --}}
@php $footerCompacto = true; @endphp

@section('content')

<main style="flex:1;">
@php
    // Los textos al pasar el cursor son los del objeto FILTROS del prototipo.
    $ayudaFiltro = [
        'Todas' => 'Todas tus actividades, en cualquier estado',
        'Publicadas' => 'Ya visibles en el calendario del Día del Patrimonio Social',
        'Estamos revisando' => 'En revisión por el equipo organizador',
        'Necesita ajustes' => 'Tienen observaciones que debes resolver',
        'Canceladas' => 'Ya no aparecen en el calendario',
        'Borradores' => 'Guardadas sin enviar a revisión',
    ];
@endphp

{{-- PANTALLA 1 — MIS ACTIVIDADES de mi-cuenta.html --}}
<div class="rise" style="max-width:1080px;margin:0 auto;padding:34px 32px 96px;"
     x-data="{ modal: null, cancelar: {} }">

    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px;">
        <div class="crumb"><a href="{{ route('home') }}">Inicio</a> → Mi cuenta</div>

        {{-- No está en el prototipo: sin esto no hay forma de llegar al perfil
             ni de cerrar sesión. --}}
        <div style="display:flex;align-items:center;gap:14px;">
            <a class="crumb" href="{{ route('account.perfil') }}">Mi perfil</a>
            <span class="crumb" aria-hidden="true">·</span>
            <form method="POST" action="{{ route('account.logout') }}">
                @csrf
                <button type="submit" class="crumb"
                        style="background:none;border:0;padding:0;cursor:pointer;font-family:var(--font);">Cerrar sesión</button>
            </form>
        </div>
    </div>

    @if (! auth()->user()->hasVerifiedEmail())
        {{-- Avisa, pero no bloquea: el recorrido de publicar lleva directo aquí
             y un muro cambiaría ese flujo. --}}
        <div class="alert alert-info" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:22px;">
            <span style="flex:1;min-width:240px;">Confirma tu correo para que no se pierdan los avisos de tus actividades.</span>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">Reenviar verificación</button>
            </form>
        </div>
    @endif

    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:26px;">
        <div>
            <h1 style="font-size:38px;font-weight:800;letter-spacing:-.02em;line-height:1.1;margin:0 0 10px;color:var(--ink);">Mis actividades</h1>
            <p style="font-size:16.5px;line-height:1.6;color:var(--gris);margin:0;max-width:56ch;text-wrap:pretty;">Aquí puedes revisar el estado de tus actividades, editarlas y registrar nuevas iniciativas.</p>
        </div>
        <a href="{{ route('publish.create') }}" class="btn btn-primary">+ Sumar nueva actividad</a>
    </div>

    @if ($necesitanAjustes > 0)
        <div style="display:flex;align-items:flex-start;gap:13px;background:#fff8e6;border:1.5px solid #f6e0c6;border-radius:18px;padding:16px 18px;margin-bottom:26px;">
            <span style="flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:999px;background:var(--amarillo);color:#fff;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 8v5"></path><path d="M12 17h.01"></path></svg>
            </span>
            <div style="font-size:14.5px;line-height:1.6;color:#7a5e00;">
                {{ $necesitanAjustes === 1 ? 'Una actividad requiere' : "{$necesitanAjustes} actividades requieren" }}
                tu atención. Revisa las observaciones y realiza los ajustes para que
                {{ $necesitanAjustes === 1 ? 'pueda' : 'puedan' }} ser {{ $necesitanAjustes === 1 ? 'publicada' : 'publicadas' }}.
            </div>
        </div>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
        @foreach ($filtros as $label => $n)
            <a class="tab {{ $filtroActivo === $label ? 'on' : '' }}"
               href="{{ route('account.activities.index', $label === 'Todas' ? [] : ['filtro' => $label]) }}"
               title="{{ $ayudaFiltro[$label] ?? '' }}">{{ $label }}<span class="count-badge">{{ $n }}</span></a>
        @endforeach
    </div>
    <div class="helper" style="margin-bottom:26px;">Pasa el cursor sobre cada estado para ver su significado.</div>

    @if ($actividades->isEmpty())
        <div class="card" style="padding:44px;text-align:center;">
            <p style="font-size:16px;color:var(--gris);margin:0 0 18px;">No hay actividades en este filtro.</p>
            <a href="{{ route('publish.create') }}" class="btn btn-outline">Publicar una actividad</a>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:14px;">
            @foreach ($actividades as $a)
                @php $tono = $a->estado_color; @endphp
                <div class="card actcard" style="padding:22px 24px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;border-left:4px solid {{ $tono['tono'] }};">
                    <div style="flex:1;min-width:260px;">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                            <span style="font-family:var(--font-title);font-size:21px;font-weight:800;color:var(--ink);">{{ $a->titulo }}</span>
                            @if ($a->estado === 'ajustes')
                                <span style="font-size:12px;font-weight:700;padding:4px 11px;border-radius:999px;background:#fdeaf0;color:var(--rosa);">Requiere acción → revisa los comentarios</span>
                            @endif
                        </div>

                        <div style="font-size:14.5px;color:var(--gris);margin-bottom:10px;">{{ $a->fecha_lista }} · {{ $a->commune?->nombre ?? 'Por definir' }}</div>

                        <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">
                            @foreach ($a->termsDe('tema') as $t)
                                <span style="font-size:12.5px;font-weight:600;padding:5px 11px;border-radius:999px;background:var(--gris-100);color:var(--gris-700);">{{ $t->nombre }}</span>
                            @endforeach

                            @if ($a->estado === 'publicada')
                                <a href="{{ route('account.participants.index', $a) }}"
                                   style="font-size:13px;font-weight:600;color:var(--naranjo);margin-left:4px;">Ver participantes inscritos →</a>
                            @endif
                        </div>

                        {{-- Las observaciones no van aquí: el prototipo sólo pone la
                             píldora, y el texto se lee al entrar a editar. --}}
                    </div>

                    <div class="col-acciones" style="flex:none;display:flex;flex-direction:column;align-items:flex-end;gap:12px;">
                        <span style="font-size:12.5px;font-weight:700;padding:7px 14px;border-radius:999px;background:{{ $tono['bg'] }};color:{{ $tono['ink'] }};border:1.5px solid {{ $tono['borde'] }};">{{ $a->estado_label }}</span>

                        <div style="display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end;opacity:{{ $a->estado === 'cancelada' ? '.45' : '1' }};">
                            <a href="{{ route('account.activities.edit', $a) }}" class="sqbtn" aria-label="Editar actividad" title="Editar actividad">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"></path><path d="M18.4 2.6a2 2 0 0 1 2.8 2.8L12 14.6l-4 1 1-4z"></path></svg>
                            </a>

                            <form method="POST" action="{{ route('account.activities.duplicate', $a) }}">
                                @csrf
                                <button type="submit" class="sqbtn" style="width:auto;padding:0 16px;gap:8px;font-size:14px;font-weight:600;" title="Duplicar actividad">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2.5"></rect><path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"></path></svg>
                                    Duplicar
                                </button>
                            </form>

                            @if ($a->estado !== 'cancelada')
                                <button type="button" class="sqbtn sqbtn-danger" aria-label="Cancelar actividad" title="Cancelar actividad"
                                        x-on:click="cancelar = {{ Js::from([
                                            'nombre' => $a->titulo,
                                            'inscritos' => $a->inscritos,
                                            'url' => route('account.activities.cancel', $a),
                                        ]) }}; modal = 'cancelar'">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M6 6l1 14h10l1-14"></path><path d="M10 11v5M14 11v5"></path></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ══ MODAL DE CANCELACIÓN ══ --}}
    <div x-show="modal === 'cancelar'" x-cloak
         style="position:fixed;inset:0;z-index:80;background:rgba(51,54,58,.45);backdrop-filter:blur(3px);display:grid;place-items:center;padding:24px;"
         x-on:click.self="modal = null" x-on:keydown.escape.window="modal = null">
        <div style="background:#fff;border-radius:26px;padding:34px 32px;max-width:500px;width:100%;box-sizing:border-box;box-shadow:0 40px 80px -40px rgba(0,0,0,.5);text-align:center;"
             role="dialog" aria-modal="true" aria-labelledby="mc-t">
            <span style="display:grid;place-items:center;width:60px;height:60px;border-radius:999px;background:#fdeaf0;color:var(--rosa);margin:0 auto 18px;">
                <svg width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
            </span>

            <h2 id="mc-t" style="font-size:26px;font-weight:800;line-height:1.2;margin:0 0 12px;color:var(--ink);text-wrap:pretty;">¿Seguro que quieres cancelar esta actividad?</h2>
            <p style="font-size:15.5px;line-height:1.65;color:var(--gris);margin:0 0 16px;text-wrap:pretty;">Dejará de aparecer en el calendario del Día del Patrimonio Social y las personas inscritas recibirán una notificación por correo. Tu actividad quedará guardada en tus borradores.</p>

            <p x-show="cancelar.inscritos > 0" x-cloak
               style="font-size:14px;line-height:1.6;color:#7a5e00;background:#fff8e6;border:1.5px solid #f6e0c6;border-radius:14px;padding:13px 16px;margin:0 0 24px;text-wrap:pretty;">
                Hay <span x-text="cancelar.inscritos"></span> personas inscritas. Les enviaremos automáticamente un correo informando la cancelación.
            </p>

            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;justify-content:center;" x-on:click="modal = null">Volver</button>
                <form method="POST" x-bind:action="cancelar.url" style="flex:1.2;display:flex;">
                    @csrf
                    <button type="submit" class="btn btn-danger" style="flex:1;justify-content:center;">Cancelar actividad</button>
                </form>
            </div>
        </div>
    </div>
</div>
</main>
@endsection
