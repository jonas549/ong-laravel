@extends('layouts.public')
@section('title', 'Crea tu cuenta · ' . config('app.name'))

@php $footerCompacto = true; @endphp

@section('content')
{{--
    No está en el prototipo: ahí la cuenta sólo se crea publicando una
    actividad. Reusa la composición del login para que no desentone.
--}}
<main style="flex:1;">
<div class="rise grid-2" style="max-width:1080px;margin:0 auto;padding:72px 32px 110px;display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center;">
    <div>
        <h1 style="font-size:42px;font-weight:800;letter-spacing:-.02em;line-height:1.08;margin:0 0 14px;color:var(--ink);text-wrap:pretty;">Crea tu cuenta</h1>
        <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 0 28px;max-width:44ch;text-wrap:pretty;">Con una cuenta puedes publicar actividades cuando quieras, editarlas y ver quién se inscribe. También puedes crearla publicando tu primera actividad.</p>
        <img loading="lazy" decoding="async" width="1008" height="472" src="{{ asset('img/construyamos-juntos-c2664680.png') }}" alt="" aria-hidden="true"
             style="width:100%;max-width:520px;height:auto;display:block;">
    </div>

    <div class="card" style="padding:34px 32px;">
        <form method="POST" action="{{ route('account.registro.store') }}" style="display:flex;flex-direction:column;gap:18px;">
            @csrf

            <label class="lbl">Nombre de la organización *
                <input class="fld @error('org_nombre') is-invalid @enderror" name="org_nombre"
                       value="@viejo('org_nombre')" placeholder="Ej. Fundación Junto al Barrio" required>
                @error('org_nombre') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            {{-- Los dos campos condicionales son los mismos del paso 2 del wizard.
                 El valor va con Js::from y no interpolado entre comillas: Blade
                 escapa la comilla simple, pero el parser de HTML la devuelve al
                 leer el atributo y Alpine acababa evaluando lo que mandara quien
                 enviara el formulario. --}}
            <div x-data="{ tipo: {{ \Illuminate\Support\Js::from(\App\Support\Formulario::viejo('org_tipo', $tiposOrg[0])) }} }"
                 style="display:flex;flex-direction:column;gap:18px;">
                <label class="lbl">Tipo de organización *
                    <select class="fld @error('org_tipo') is-invalid @enderror" name="org_tipo" x-model="tipo" required>
                        @foreach ($tiposOrg as $t)
                            <option value="{{ $t }}" @selected(\App\Support\Formulario::viejo('org_tipo', $tiposOrg[0]) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                    @error('org_tipo') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="lbl" x-show="tipo === 'Otra'" x-cloak>¿Qué tipo de organización es? *
                    <input class="fld @error('org_tipo_otro') is-invalid @enderror" name="org_tipo_otro" value="@viejo('org_tipo_otro')">
                    @error('org_tipo_otro') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="lbl" x-show="tipo === 'Institución educativa'" x-cloak>Nombre de la unidad educativa *
                    <input class="fld @error('org_unidad_educativa') is-invalid @enderror" name="org_unidad_educativa" value="@viejo('org_unidad_educativa')">
                    @error('org_unidad_educativa') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="lbl">Tu nombre *
                <input class="fld @error('name') is-invalid @enderror" name="name" value="@viejo('name')" required>
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Correo electrónico *
                <input class="fld @error('email') is-invalid @enderror" type="email" name="email"
                       value="@viejo('email')" placeholder="contacto@organizacion.cl" required autocomplete="email">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <label class="lbl">Contraseña *
                    <input class="fld @error('password') is-invalid @enderror" type="password" name="password"
                           placeholder="••••••••" required autocomplete="new-password">
                    <span class="helper">Mínimo 8 caracteres.</span>
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="lbl">Repite la contraseña *
                    <input class="fld" type="password" name="password_confirmation"
                           placeholder="••••••••" required autocomplete="new-password">
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="justify-content:center;">Crear cuenta</button>

            <a class="textlink" href="{{ route('account.login') }}"
               style="font-size:13.5px;font-weight:600;text-align:center;">Ya tengo cuenta</a>
        </form>
    </div>
</div>
</main>
@endsection
