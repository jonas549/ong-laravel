@extends('layouts.admin')
@section('title', 'Nueva organización')
@section('miga', 'Nueva')

{{--
    Sumar una organización al listado sin pasar por un CSV (punto 11 del
    30/09). Queda como las importadas: sin cuenta, sin verificar y activa. La
    reclama después quien la represente, desde «Publica tu actividad».
--}}

@section('content')
<a href="{{ route('admin.organizations.index') }}" class="textlink" style="font-size:14px;">← Volver a organizaciones</a>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:18px;align-items:start;">

    <section class="card" style="padding:26px;grid-column:span 2;min-width:0;">
        <form method="POST" action="{{ route('admin.organizations.store') }}"
              style="display:flex;flex-direction:column;gap:18px;" data-crear-organizacion>
            @csrf

            @include('admin.organizations._campos', ['tipoObligatorio' => false])

            {{--
                Punto 1 del 08/10: la cuenta de acceso en el mismo paso, con las
                reglas de Panel → Usuarios (`CuentaDeAcceso`). Sin marcar, los
                campos van desactivados y no viajan: queda libre, como antes.
            --}}
            <fieldset data-cuenta-organizacion
                      x-data="cuentaDeOrganizacion({ crearCuenta: @js((bool) old('crear_cuenta')), rutaCorreo: @js(route('publish.correo')) })"
                      style="border:1px solid var(--linea);border-radius:14px;padding:18px 20px;margin:0;display:flex;flex-direction:column;gap:14px;">
                <legend style="padding:0 6px;font-weight:700;">Cuenta de acceso</legend>

                <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="crear_cuenta" value="1" x-model="crearCuenta" style="margin-top:3px;">
                    <span>Crear también la cuenta con la que entra la organización
                        <span class="helper" style="display:block;">Queda como su dueña: puede entrar y publicar sin reclamarla. Sin marcar, la organización queda libre en el listado.</span>
                    </span>
                </label>

                <div x-show="crearCuenta" x-cloak style="display:flex;flex-direction:column;gap:14px;">
                    <div>
                        <label class="helper" for="c-name" style="display:block;margin-bottom:6px;font-weight:600;">Nombre de la persona</label>
                        <input class="fld @error('name') is-invalid @enderror" type="text" id="c-name" name="name" value="@viejo('name')"
                               x-bind:disabled="! crearCuenta" x-bind:required="crearCuenta" autocomplete="off">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="helper" for="c-email" style="display:block;margin-bottom:6px;font-weight:600;">Correo de acceso</label>
                        <input class="fld @error('email') is-invalid @enderror" type="email" id="c-email" name="email" value="@viejo('email')"
                               x-bind:disabled="! crearCuenta" x-bind:required="crearCuenta" autocomplete="off"
                               x-on:blur="comprobarCorreo($event.target.value)"
                               x-on:input="if ($event.target.value.trim() !== correoComprobado) correoExiste = false">
                        <span class="field-error" x-show="correoExiste" x-cloak data-correo-existe>{{ \App\Support\CuentaDeAcceso::CORREO_REPETIDO }}</span>
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="helper" for="c-password" style="display:block;margin-bottom:6px;font-weight:600;">Contraseña</label>
                        <input class="fld @error('password') is-invalid @enderror" type="password" id="c-password" name="password"
                               x-bind:disabled="! crearCuenta" x-bind:required="crearCuenta" autocomplete="new-password">
                        <span class="helper">Mínimo 8 caracteres. Compártela con la organización por un canal seguro.</span>
                        @error('password') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </fieldset>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" data-cargando="Guardando…">Crear organización</button>
                <a href="{{ route('admin.organizations.index') }}" class="btn btn-ghost">Cancelar</a>
            </div>
        </form>
    </section>

    <aside class="card" style="padding:22px 24px;">
        <div class="seclabel" style="margin-bottom:12px;">Cómo queda</div>
        <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;font-size:14px;line-height:1.5;color:var(--gris-700);">
            <li><strong>Sin cuenta</strong>, salvo que marques «Cuenta de acceso»: entonces nace con ella y esa persona ya puede entrar y publicar.</li>
            <li><strong>Reclamable</strong> si no tiene cuenta. Sale en el buscador del paso «Tu organización» de «Publica tu actividad»: quien la represente la elige y se pone su contraseña.</li>
            <li><strong>Sin verificar</strong>, como las importadas. La marca la pones tú cuando sepas quiénes son.</li>
            <li>Para que salga en la marquesina del home, añádela después en Páginas → Marquesina de organizaciones.</li>
        </ul>
    </aside>
</div>
@endsection
