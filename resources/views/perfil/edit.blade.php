{{--
    Perfil, para los dos paneles.

    El layout se elige por el rol: el admin lo ve dentro de su panel y el
    organizador dentro del sitio público, con el mismo aspecto que el resto de
    "Mi cuenta".
--}}
@extends($esAdmin ? 'layouts.admin' : 'layouts.public')
@section('title', 'Mi perfil')
@section('miga', 'Mi perfil')

@php $footerCompacto = ! $esAdmin; @endphp

@section('content')
@if (! $esAdmin)
<main style="flex:1;">
<div class="rise" style="max-width:900px;margin:0 auto;padding:34px 32px 96px;">
    <x-cuenta.barra>
        Mi cuenta → <a href="{{ route('account.activities.index') }}">Mis actividades</a> → Perfil
    </x-cuenta.barra>
    <h1 style="font-size:38px;font-weight:800;letter-spacing:-.02em;margin:0 0 26px;color:var(--ink);">Mi perfil</h1>
@endif

<div style="display:flex;flex-direction:column;gap:18px;max-width:820px;">

    @if (! $usuario->hasVerifiedEmail())
        <div class="alert alert-info" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <span style="flex:1;min-width:240px;">Tu correo todavía no está verificado.</span>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">Reenviar verificación</button>
            </form>
        </div>
    @endif

    {{-- ── Datos ── --}}
    <form method="POST" action="{{ route($esAdmin ? 'admin.perfil.update' : 'account.perfil.update') }}"
          class="card" style="padding:26px;"
          x-data="{ correoOriginal: {{ \Illuminate\Support\Js::from(mb_strtolower($usuario->email)) }}, correo: {{ \Illuminate\Support\Js::from(\App\Support\Formulario::viejo('email', $usuario->email)) }},
                    get cambia() { return this.correo.trim().toLowerCase() !== this.correoOriginal } }">
        @csrf
        @method('PUT')

        <div class="seclabel" style="margin-bottom:16px;">Tus datos</div>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl">Nombre
                <input class="fld @error('name') is-invalid @enderror" name="name" value="@viejo('name', $usuario->name)" required>
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Correo
                <input class="fld @error('email') is-invalid @enderror" type="email" name="email"
                       value="@viejo('email', $usuario->email)" x-model="correo" required>
                <span class="helper">Si lo cambias, habrá que verificarlo de nuevo.</span>
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        {{--
            La contraseña sólo se pide al cambiar el correo. Es la puerta que
            cerraba la toma de cuenta: con una sesión robada bastaba con poner
            otra dirección y pedir "olvidé mi contraseña" para quedarse la cuenta.
        --}}
        <div x-show="cambia" x-cloak style="margin-top:16px;max-width:360px;">
            <label class="lbl">Contraseña actual
                <input class="fld @error('actual_correo') is-invalid @enderror" type="password" name="actual_correo"
                       autocomplete="current-password" x-bind:required="cambia">
                <span class="helper">Sólo hace falta para cambiar el correo.</span>
                @error('actual_correo') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div x-show="cambia" x-cloak class="helper" style="margin-top:12px;max-width:60ch;">
            Avisaremos del cambio a <strong>{{ $usuario->email }}</strong>, tu dirección actual.
        </div>

        <div style="margin-top:18px;">
            <button type="submit" class="btn btn-primary">Guardar datos</button>
        </div>
    </form>

    {{--
        ── El logo de la organización (B6) ──

        En el teléfono el wizard no pide el logo, y en el escritorio es
        opcional: hacía falta un sitio donde subirlo después, y no había
        ninguno —el organizador no podía cambiar su logo sin publicar otra
        actividad—. Va aquí, en «Mi perfil», porque es lo único de la ficha de
        la organización que no se edita desde una actividad.

        Con el mismo trato que en el wizard: se reduce en el navegador a 800 px
        y se avisa del peso al elegirlo, no después de enviar.
    --}}
    @if (! $esAdmin && ($organizacion = $usuario->organization))
        <form method="POST" action="{{ route('account.perfil.logo') }}" enctype="multipart/form-data"
              class="card" style="padding:26px;" id="logo-organizacion">
            @csrf
            @method('PUT')
            <h2 style="font-size:17px;font-weight:800;margin:0 0 4px;">Logo de tu organización</h2>
            <p class="helper" style="margin:0 0 16px;">
                Sale en tus actividades junto al nombre de «{{ $organizacion->nombre }}». Sin logo se muestran sus iniciales.
            </p>

            <div data-campo="logo" x-data="campoImagen({ maxKb: 500, ladoMaximo: 800, que: 'El logo' })"
                 style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <span style="display:grid;place-items:center;width:96px;height:96px;border-radius:20px;border:1.5px dashed #dcdee1;background:#fbfbfc;color:#c3c6ca;flex:none;overflow:hidden;">
                    <img x-show="previa" x-cloak x-bind:src="previa" alt="" style="width:100%;height:100%;object-fit:contain;">
                    @if ($organizacion->logo_path)
                        <img x-show="! previa" src="{{ asset($organizacion->logo_path) }}" alt="Logo actual" style="width:100%;height:100%;object-fit:contain;">
                    @else
                        <span x-show="! previa" style="font-size:12px;">Sin logo</span>
                    @endif
                </span>
                <div>
                    <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                        <span x-text="tiene ? 'Cambiar imagen' : '{{ $organizacion->logo_path ? 'Cambiar logo' : 'Subir logo' }}'">{{ $organizacion->logo_path ? 'Cambiar logo' : 'Subir logo' }}</span>
                        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" style="display:none;"
                               x-on:change="elegir($event)">
                    </label>
                    <div class="helper" style="margin-top:7px;">PNG o JPG · máx. 500 KB · 400×400 px recomendado.</div>
                    <div class="helper" x-show="reduciendo" x-cloak>Preparando la imagen…</div>
                    <div class="helper" x-show="tiene && ! error" x-cloak>
                        <span x-text="nombre"></span> · <span x-text="peso"></span>
                    </div>
                    <span class="field-error" x-show="error" x-cloak x-text="error"></span>
                    @error('logo') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="margin-top:18px;">
                <button type="submit" class="btn btn-primary">Guardar logo</button>
            </div>
        </form>
    @endif

    {{-- ── Contraseña ── --}}
    <form method="POST" action="{{ route($esAdmin ? 'admin.perfil.password' : 'account.perfil.password') }}"
          class="card" style="padding:26px;">
        @csrf

        <div class="seclabel" style="margin-bottom:6px;">Cambiar contraseña</div>
        <p class="helper" style="margin:0 0 16px;max-width:60ch;">
            Al cambiarla se cierran tus sesiones en otros dispositivos, para que nadie siga dentro con la anterior.
        </p>

        <label class="lbl" style="max-width:360px;margin-bottom:16px;">Contraseña actual
            <input class="fld @error('actual') is-invalid @enderror" type="password" name="actual" autocomplete="current-password" required>
            @error('actual') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl">Contraseña nueva
                <input class="fld @error('password') is-invalid @enderror" type="password" name="password" autocomplete="new-password" required>
                <span class="helper">Mínimo 8 caracteres.</span>
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Repite la contraseña nueva
                <input class="fld" type="password" name="password_confirmation" autocomplete="new-password" required>
            </label>
        </div>

        <div style="margin-top:18px;">
            <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
        </div>
    </form>

    {{-- ── Sesiones ── --}}
    <div class="card" style="padding:26px;">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
            <div>
                <div class="seclabel" style="margin-bottom:6px;">Sesiones activas</div>
                <p class="helper" style="margin:0;max-width:60ch;">Dónde está abierta tu cuenta ahora mismo.</p>
            </div>

            @if ($sesionesDisponibles && $sesiones->count() > 1)
                <form method="POST" action="{{ route($esAdmin ? 'admin.perfil.sesiones.otras' : 'account.perfil.sesiones.otras') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Cerrar las demás</button>
                </form>
            @endif
        </div>

        {{-- Sin esto, un `sesion` vacío o mal formado dejaba la pantalla igual
             que estaba, sin decir nada. --}}
        @error('sesion')
            <div class="alert alert-error" style="margin-bottom:16px;">{{ $message }}</div>
        @enderror

        @if (! $sesionesDisponibles)
            <p class="helper" style="margin:0;">
                Las sesiones no se pueden listar porque no se están guardando en base de datos
                (<code>SESSION_DRIVER</code> está en <code>{{ config('session.driver') }}</code>).
            </p>
        @else
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead><tr><th>Dispositivo</th><th>IP</th><th>Última actividad</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($sesiones as $s)
                            <tr>
                                <td style="font-weight:600;">
                                    {{ $s->dispositivo }}
                                    @if ($s->esActual)
                                        <span style="font-size:12px;font-weight:600;padding:3px 9px;border-radius:999px;background:#eaf6f5;color:#0d6b64;margin-left:8px;">esta sesión</span>
                                    @endif
                                </td>
                                <td>{{ $s->ip ?? '—' }}</td>
                                <td>{{ \App\Support\Fecha::relativa($s->ultimaActividad) }}</td>
                                <td style="text-align:right;">
                                    @unless ($s->esActual)
                                        <form method="POST" action="{{ route($esAdmin ? 'admin.perfil.sesiones.cerrar' : 'account.perfil.sesiones.cerrar') }}">
                                            @csrf
                                            <input type="hidden" name="sesion" value="{{ $s->id }}">
                                            <button type="submit" class="btn btn-ghost btn-sm">Cerrar</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@if (! $esAdmin)
</div>
</main>
@endif
@endsection
