@extends('layouts.admin')
@section('title', 'Configuración de correo')

@section('content')
@php
    $v = $valores;
    $activo = (bool) ($v['smtp_activo'] ?? false);
@endphp

{{-- El mismo aviso que en el registro de correos: ésta es la otra pantalla a
     la que se llega preguntándose por qué no llega nada. --}}
@include('partials.admin.salud-correo')

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:22px;align-items:start;">

    <section class="card" style="padding:26px;">
        <h2 style="font-size:17px;font-weight:700;margin:0 0 6px;">Servidor SMTP</h2>
        <p class="helper" style="margin:0 0 22px;">
            Esta configuración vive en la base de datos, no en archivos: se puede cambiar sin pedir un deploy.
        </p>

        <form method="POST" action="{{ route('admin.settings.smtp.update') }}" style="display:flex;flex-direction:column;gap:16px;">
            @csrf
            @method('PUT')

            <label style="display:flex;align-items:center;gap:10px;font-size:14.5px;color:var(--gris-700);cursor:pointer;">
                <input type="checkbox" name="smtp_activo" value="1" @checked(old('smtp_activo', $activo))>
                Usar esta configuración
            </label>
            <span class="helper" style="margin-top:-8px;">Si está apagada, el sistema usa la configuración del archivo <code>.env</code>.</span>

            <div>
                <label class="helper" for="smtp_host" style="display:block;margin-bottom:6px;font-weight:600;">Servidor SMTP</label>
                <input class="fld @error('smtp_host') is-invalid @enderror" type="text" id="smtp_host" name="smtp_host"
                       value="@viejo('smtp_host', $v['smtp_host'] ?? '')" placeholder="mail.tudominio.cl">
                @error('smtp_host') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="helper" for="smtp_port" style="display:block;margin-bottom:6px;font-weight:600;">Puerto</label>
                    <input class="fld @error('smtp_port') is-invalid @enderror" type="number" id="smtp_port" name="smtp_port"
                           value="@viejo('smtp_port', $v['smtp_port'] ?? 587)">
                    <span class="helper">587 para TLS, 465 para SSL.</span>
                    @error('smtp_port') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="helper" for="smtp_encryption" style="display:block;margin-bottom:6px;font-weight:600;">Cifrado</label>
                    <select class="fld" id="smtp_encryption" name="smtp_encryption">
                        @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Sin cifrado'] as $k => $label)
                            <option value="{{ $k }}" @selected(old('smtp_encryption', $v['smtp_encryption'] ?? 'tls') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="helper" for="smtp_username" style="display:block;margin-bottom:6px;font-weight:600;">Usuario</label>
                <input class="fld" type="text" id="smtp_username" name="smtp_username"
                       value="@viejo('smtp_username', $v['smtp_username'] ?? '')" autocomplete="off">
            </div>

            <div>
                <label class="helper" for="smtp_password" style="display:block;margin-bottom:6px;font-weight:600;">Contraseña</label>
                <input class="fld" type="password" id="smtp_password" name="smtp_password"
                       placeholder="{{ filled($v['smtp_password'] ?? null) ? '•••••••• (guardada)' : 'Sin configurar' }}" autocomplete="new-password">
                <span class="helper">
                    Se guarda cifrada. Déjala en blanco para conservar la actual.
                </span>
            </div>

            <hr style="border:0;border-top:1px solid var(--linea);margin:4px 0;">

            <div>
                <label class="helper" for="smtp_from_address" style="display:block;margin-bottom:6px;font-weight:600;">Correo remitente</label>
                <input class="fld @error('smtp_from_address') is-invalid @enderror" type="email" id="smtp_from_address"
                       name="smtp_from_address" value="@viejo('smtp_from_address', $v['smtp_from_address'] ?? '')">
                @error('smtp_from_address') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="helper" for="smtp_from_name" style="display:block;margin-bottom:6px;font-weight:600;">Nombre remitente</label>
                <input class="fld" type="text" id="smtp_from_name" name="smtp_from_name"
                       value="@viejo('smtp_from_name', $v['smtp_from_name'] ?? config('app.name'))">
            </div>

            <button type="submit" class="btn btn-primary" style="align-self:flex-start;">Guardar configuración</button>
        </form>
    </section>

    <aside style="display:flex;flex-direction:column;gap:18px;">
        <section class="card" style="padding:26px;">
            <h2 style="font-size:17px;font-weight:700;margin:0 0 6px;">Enviar correo de prueba</h2>
            <p class="helper" style="margin:0 0 18px;">
                Manda un mensaje real con la configuración actual. Si falla, mostramos el error del servidor tal cual lo devuelve.
            </p>

            <form method="POST" action="{{ route('admin.settings.smtp.test') }}" style="display:flex;flex-direction:column;gap:12px;">
                @csrf
                <div>
                    <label class="helper" for="destino" style="display:block;margin-bottom:6px;font-weight:600;">Enviar a</label>
                    <input class="fld @error('destino') is-invalid @enderror" type="email" id="destino" name="destino"
                           value="@viejo('destino', auth()->user()->email)" required>
                    @error('destino') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="btn btn-outline" style="justify-content:center;">Enviar correo de prueba</button>
            </form>
        </section>

        <section class="card" style="padding:26px;">
            <h2 style="font-size:16px;font-weight:700;margin:0 0 12px;">Estado actual</h2>
            <div style="font-size:14px;color:var(--gris-700);display:flex;flex-direction:column;gap:8px;">
                <div style="display:flex;justify-content:space-between;gap:12px;">
                    <span class="helper">Origen</span>
                    <strong>{{ $activo ? 'Panel (base de datos)' : 'Archivo .env' }}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;gap:12px;">
                    <span class="helper">Mailer del .env</span>
                    <strong>{{ config('mail.default') }}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;gap:12px;">
                    <span class="helper">Remitente efectivo</span>
                    <strong style="text-align:right;">{{ $v['smtp_from_address'] ?? config('mail.from.address') }}</strong>
                </div>
            </div>

            <hr style="border:0;border-top:1px solid var(--linea);margin:18px 0;">

            <a class="textlink" href="{{ route('admin.emails.index') }}" style="font-size:14px;">Ver el log de correos →</a>
        </section>
    </aside>
</div>
@endsection
