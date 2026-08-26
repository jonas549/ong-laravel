@extends('layouts.admin')
@section('miga', $usuario->name)
@section('title', $usuario->name)

@section('actions')
    <a class="btn btn-outline btn-sm" href="{{ route('admin.users.index', ['rol' => $usuario->role]) }}">← Volver a usuarios</a>
@endsection

@section('content')
<div style="display:flex;flex-direction:column;gap:18px;max-width:820px;">

    {{-- ── Datos ── --}}
    <form method="POST" action="{{ route('admin.users.update', $usuario) }}" class="card" style="padding:26px;">
        @csrf
        @method('PUT')

        <div class="seclabel" style="margin-bottom:16px;">Datos de la cuenta</div>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl">Nombre
                <input class="fld @error('name') is-invalid @enderror" name="name" value="@viejo('name', $usuario->name)" required>
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Correo
                <input class="fld @error('email') is-invalid @enderror" type="email" name="email"
                       value="@viejo('email', $usuario->email)" required>
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
            <label class="lbl">Rol
                <select class="fld @error('role') is-invalid @enderror" name="role">
                    <option value="organizer" @selected(old('role', $usuario->role) === 'organizer')>Organizador</option>
                    <option value="admin" @selected(old('role', $usuario->role) === 'admin')>Administración</option>
                </select>
                @error('role') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <div class="lbl">Estado
                <div style="display:flex;align-items:center;gap:12px;margin-top:6px;">
                    <span style="font-size:12px;font-weight:600;padding:5px 12px;border-radius:999px;background:{{ $usuario->is_active ? '#eaf6f5' : '#f1f2f3' }};color:{{ $usuario->is_active ? '#0d6b64' : 'var(--gris)' }};">
                        {{ $usuario->is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                    @unless ($esUnoMismo)
                        <button type="submit" form="estado-{{ $usuario->id }}" class="btn btn-ghost btn-sm">
                            {{ $usuario->is_active ? 'Desactivar' : 'Activar' }}
                        </button>
                    @endunless
                </div>
            </div>
        </div>

        @if ($usuario->organization)
            <p class="helper" style="margin:16px 0 0;">
                Organización: <strong>{{ $usuario->organization->nombre }}</strong>.
                El correo de contacto que se publica es el de la organización, no el de esta cuenta.
            </p>
        @endif

        <div style="margin-top:18px;">
            <button type="submit" class="btn btn-primary">Guardar datos</button>
        </div>
    </form>

    {{-- El formulario de estado vive fuera para no anidar formularios. --}}
    @unless ($esUnoMismo)
        <form id="estado-{{ $usuario->id }}" method="POST" action="{{ route('admin.users.toggle', $usuario) }}" style="display:none;">@csrf</form>
    @endunless

    {{-- ── Contraseña ── --}}
    <form method="POST" action="{{ route('admin.users.password', $usuario) }}" class="card" style="padding:26px;"
          onsubmit="return confirm('Se le asignará una contraseña nueva a {{ $usuario->name }}, se cerrarán todas sus sesiones y se le avisará por correo. ¿Seguir?');">
        @csrf

        <div class="seclabel" style="margin-bottom:6px;">Asignar una contraseña nueva</div>

        @if ($esUnoMismo)
            <p class="helper" style="margin:0;max-width:62ch;">
                Ésta es tu propia cuenta. Tu contraseña se cambia desde
                <a class="textlink" href="{{ route('admin.perfil') }}">tu perfil</a>, donde se pide la actual.
            </p>
        @else
            <p class="helper" style="margin:0 0 16px;max-width:62ch;">
                Para cuando alguien perdió el acceso y no puede recuperarlo por correo.
                Al guardarla se cierran <strong>todas</strong> sus sesiones, queda registrado en
                <a class="textlink" href="{{ route('admin.accesos.index') }}">el registro de accesos</a> que fuiste tú,
                y se le avisa por correo. La contraseña no va en ese aviso: tienes que dársela tú.
            </p>

            <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <label class="lbl">Contraseña nueva
                    <input class="fld @error('password') is-invalid @enderror" type="password" name="password"
                           autocomplete="new-password" required>
                    <span class="helper">Mínimo 8 caracteres.</span>
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="lbl">Repite la contraseña
                    <input class="fld" type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
            </div>

            @if ($sesionesAbiertas === null)
                <p class="helper" style="margin:14px 0 0;">
                    Las sesiones no se pueden listar con <code>SESSION_DRIVER</code> en
                    <code>{{ config('session.driver') }}</code>, pero el «recuérdame» se invalida igual.
                </p>
            @elseif ($sesionesAbiertas > 0)
                <p class="helper" style="margin:14px 0 0;">
                    Ahora mismo tiene <strong>{{ $sesionesAbiertas }}</strong>
                    {{ $sesionesAbiertas === 1 ? 'sesión abierta' : 'sesiones abiertas' }}, que se cerrarán.
                </p>
            @endif

            <div style="margin-top:18px;">
                <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
            </div>
        @endif
    </form>
</div>
@endsection
