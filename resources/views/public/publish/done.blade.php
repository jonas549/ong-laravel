@extends('layouts.public')
@section('title', 'Actividad enviada · ' . config('app.name'))

@section('content')
<section style="background:var(--bg-warm);padding:64px 0 96px;">
    <div style="max-width:640px;margin:0 auto;padding:0 40px;">
        <div class="card rise" style="padding:44px;text-align:center;">
            <div style="display:grid;place-items:center;width:64px;height:64px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo-600);margin:0 auto 22px;">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5"></path>
                </svg>
            </div>

            <h1 style="font-weight:800;font-size:30px;line-height:1.15;margin:0 0 14px;">¡Recibimos tu actividad!</h1>
            <p style="font-size:16px;line-height:1.6;color:var(--gris);margin:0 0 8px;">
                <strong style="color:var(--ink);">{{ $activity->titulo }}</strong> quedó en revisión.
            </p>
            <p style="font-size:15px;line-height:1.6;color:var(--gris);margin:0 0 28px;">
                Te enviamos un correo de confirmación. Te avisaremos apenas esté revisada.
            </p>

            <div class="seclabel" style="margin-bottom:12px;">¿Qué sigue?</div>
            <ol style="text-align:left;font-size:14.5px;line-height:1.7;color:var(--gris-700);margin:0 0 28px;padding-left:20px;">
                <li>El equipo organizador revisa la información.</li>
                <li>Si falta algo, te escribimos con las observaciones.</li>
                <li>Cuando esté lista, aparece en el calendario público.</li>
            </ol>

            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <a href="{{ route('account.login') }}" class="btn btn-primary">Ir a mi cuenta</a>
                <a href="{{ route('home') }}" class="btn btn-outline">Volver al inicio</a>
            </div>
        </div>
    </div>
</section>
@endsection
