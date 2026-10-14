@extends('layouts.public')
@section('title', 'Borrador guardado · ' . config('app.name'))

{{-- Como el resto del wizard, con el footer compacto. --}}
@php $footerCompacto = true; @endphp

@section('content')
{{--
    Lo que ve quien pulsa «Guardar borrador» en el paso 4.

    Tiene que dejar claras tres cosas, porque es lo que se necesita para
    volver: que se creó la cuenta (y con qué correo), que la actividad quedó
    guardada pero NO enviada, y dónde está. No se apoya en ningún correo: el de
    bienvenida puede no llegar, y volver no depende de él.
--}}
<main style="flex:1;">
    <div class="rise" style="max-width:760px;margin:0 auto;padding:64px 32px 96px;" data-borrador-guardado>
        <div style="text-align:center;margin-bottom:32px;">
            <span style="display:grid;place-items:center;width:64px;height:64px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo);margin:0 auto 20px;">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path></svg>
            </span>
            <h1 style="font-size:36px;font-weight:800;letter-spacing:-.02em;line-height:1.12;margin:0 0 12px;color:var(--ink);text-wrap:pretty;">Guardamos tu borrador</h1>
            <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 auto;max-width:52ch;text-wrap:pretty;">
                Todavía <strong style="color:var(--ink);">no la enviaste a revisión</strong>: nadie más la ve y no aparece en el calendario. Puedes completarla cuando quieras.
            </p>
        </div>

        <div style="background:#fff;border:1px solid var(--linea);border-radius:24px;padding:26px 28px;box-shadow:0 18px 40px -32px rgba(0,0,0,.22);margin-bottom:18px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
                <div style="font-family:var(--font-title);font-size:20px;font-weight:800;color:var(--ink);margin-bottom:4px;">{{ $activity->organization->nombre }} — {{ $activity->titulo }}</div>
                @if ($activity->resumen_fecha_lugar !== '')
                    <div style="font-size:14.5px;color:var(--gris);">{{ $activity->resumen_fecha_lugar }}</div>
                @endif
            </div>
            @php $tono = $activity->estado_color; @endphp
            <span style="flex:none;font-size:13px;font-weight:600;padding:7px 14px;border-radius:999px;background:{{ $tono['bg'] }};color:{{ $tono['ink'] }};border:1.5px solid {{ $tono['borde'] }};">Borrador</span>
        </div>

        <div style="background:#fff;border:1px solid var(--linea);border-radius:24px;padding:28px;box-shadow:0 18px 40px -32px rgba(0,0,0,.22);margin-bottom:18px;">
            <div class="seclabel" style="margin-bottom:14px;">Qué pasó y cómo volver</div>
            <div style="display:flex;flex-direction:column;gap:14px;">
                @if ($cuentaCreada)
                    <div style="display:flex;gap:13px;align-items:flex-start;" data-cuenta-creada>
                        <span style="flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo-600);font-size:12.5px;font-weight:800;">1</span>
                        <div style="font-size:15.5px;line-height:1.6;color:var(--gris-700);">
                            <strong style="color:var(--ink);">Creamos tu cuenta</strong> con el correo
                            <strong style="color:var(--ink);">{{ $correo }}</strong> y la contraseña que elegiste. Ya tienes la sesión iniciada.
                        </div>
                    </div>
                @endif
                <div style="display:flex;gap:13px;align-items:flex-start;">
                    <span style="flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo-600);font-size:12.5px;font-weight:800;">{{ $cuentaCreada ? 2 : 1 }}</span>
                    <div style="font-size:15.5px;line-height:1.6;color:var(--gris-700);">
                        Tu actividad está en <strong style="color:var(--ink);">Mi cuenta → Mis actividades → Borradores</strong>.
                    </div>
                </div>
                <div style="display:flex;gap:13px;align-items:flex-start;">
                    <span style="flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo-600);font-size:12.5px;font-weight:800;">{{ $cuentaCreada ? 3 : 2 }}</span>
                    <div style="font-size:15.5px;line-height:1.6;color:var(--gris-700);">
                        Para volver otro día, entra en <a class="textlink" href="{{ route('account.login') }}">Mi cuenta</a>
                        con {{ $correo ? 'el correo '.$correo : 'tu correo' }} y tu contraseña, abre el borrador, complétalo y pulsa
                        <strong style="color:var(--ink);">Enviar a revisión</strong>.
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;">
            <a href="{{ route('account.activities.edit', $activity) }}" class="btn btn-primary" data-seguir-editando>Seguir completándola</a>
            <a href="{{ route('account.activities.index', ['filtro' => 'Borradores']) }}" class="btn btn-outline" data-ir-a-borradores>Ir a mis borradores</a>
        </div>
    </div>
</main>
@endsection
