{{--
    PASO 3 — TU ORGANIZACIÓN de publicar-actividad.html (líneas 148-220).

    La clase .lbl es exactamente el estilo en línea que el fuente repite en
    cada <label>: display:flex;flex-direction:column;gap:6px;font-size:13px;
    font-weight:600;color:var(--gris-700).
--}}
@php
    // Un @include compila a su propio archivo, así que el `use` del wizard no
    // llega hasta aquí y hay que repetirlo.
    use App\Support\CamposDeActividad;
@endphp

<h1 style="font-size:36px;font-weight:800;letter-spacing:-.02em;margin:0 0 24px;color:var(--ink);">Sobre tu organización</h1>

{{--
    Antes aquí ponía «Revisa los campos marcados: hay 1 dato por corregir», que
    dice que algo falla pero no qué, así que había que bajar a buscarlo igual. Y
    sólo estaba en este paso: con el error en el paso 4 —que es donde caen casi
    todos— no había absolutamente nada arriba.
--}}
<x-resumen-errores :errores="$erroresDelServidor" />

<div style="background:#fff;border:1px solid var(--linea);border-radius:24px;box-shadow:0 18px 40px -32px rgba(0,0,0,.22);overflow:hidden;">

    <div style="padding:30px;display:flex;flex-direction:column;gap:18px;border-bottom:1px solid var(--linea);">
        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl" data-campo="org_nombre" data-obligatorio
                   data-etiqueta="{{ CamposDeActividad::etiqueta('org_nombre') }}">Nombre de la organización *
                <input class="fld @error('org_nombre') is-invalid @enderror" name="org_nombre"
                       value="@viejo('org_nombre', $organizacion?->nombre)" placeholder="Ej. Fundación Junto al Barrio">
                @error('org_nombre') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Tipo de organización
                <input class="fld" x-bind:value="tipo" readonly style="background:#f8f9fa;color:var(--gris);">
                <span class="helper">Prellenado del paso anterior.</span>
            </label>
        </div>

        <label class="lbl" x-show="esOtra()" x-cloak data-campo="org_tipo_otro" data-obligatorio
               data-etiqueta="{{ CamposDeActividad::etiqueta('org_tipo_otro') }}">Describe tu organización *
            <input class="fld @error('org_tipo_otro') is-invalid @enderror" name="org_tipo_otro"
                   value="@viejo('org_tipo_otro', $organizacion?->tipo_otro)" placeholder="Otra (especificar)">
            <span class="helper">Se muestra solo al seleccionar "Otra".</span>
            @error('org_tipo_otro') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="lbl" style="max-width:280px;" x-show="esEmpresa()" x-cloak>¿Cuántos trabajadores participan como voluntarios?
            <input class="fld @error('org_num_voluntarios') is-invalid @enderror" name="org_num_voluntarios"
                   inputmode="numeric" value="@viejo('org_num_voluntarios', $organizacion?->num_voluntarios)" placeholder="Ej. 25">
            <span class="helper">Número aproximado. Escribe 0 si no aplica.</span>
            @error('org_num_voluntarios') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="lbl" x-show="esEducativa()" x-cloak data-campo="org_unidad_educativa" data-obligatorio
               data-etiqueta="{{ CamposDeActividad::etiqueta('org_unidad_educativa') }}">¿Qué unidad, grupo o comunidad educativa organiza la actividad? *
            <input class="fld @error('org_unidad_educativa') is-invalid @enderror" name="org_unidad_educativa"
                   value="@viejo('org_unidad_educativa', $organizacion?->unidad_educativa)" placeholder="Ej. Facultad de Enfermería, Centro de Estudiantes, 3° medio B">
            @error('org_unidad_educativa') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div x-data="{ logo: '' }">
            <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:8px;">Logo de la organización</div>
            <div style="display:flex;align-items:center;gap:16px;">
                <span style="display:grid;place-items:center;width:76px;height:76px;border-radius:20px;border:1.5px dashed #dcdee1;background:#fbfbfc;color:#c3c6ca;flex:none;overflow:hidden;">
                    <img x-ref="vista" x-show="logo" x-cloak alt="" style="width:100%;height:100%;object-fit:cover;">
                    <svg x-show="!logo" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path></svg>
                </span>
                <div>
                    <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                        Subir imagen
                        <input type="file" name="org_logo" accept="image/jpeg,image/png,image/webp" style="display:none;"
                               x-on:change="logo = $event.target.files[0]?.name || '';
                                            if ($event.target.files[0]) $refs.vista.src = URL.createObjectURL($event.target.files[0])">
                    </label>
                    <div class="helper" style="margin-top:7px;">PNG o JPG · máx. 500 KB · 400×400 px recomendado. Si no subes logo, se mostrará un ícono genérico.</div>
                    @error('org_logo') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
    </div>

    @if ($organizacion)
        {{--
            Con la sesión abierta no hay acceso que crear: la actividad va a la
            cuenta que ya existe. Se conserva el bloque —mismo fondo, mismo
            `seclabel`, misma caja— para no dejar un hueco donde el fuente pone
            una sección, pero en vez de los campos va el aviso de a qué cuenta
            se suma.
        --}}
        <div style="padding:30px;background:#fdfcfb;">
            <div class="seclabel" style="margin-bottom:6px;">Tu cuenta</div>
            <p style="font-size:14.5px;line-height:1.6;color:var(--gris);margin:0;max-width:60ch;">
                Esta actividad se sumará a tu cuenta, <strong style="color:var(--ink);">{{ auth()->user()->email }}</strong>.
                La verás en «Mis actividades» junto a las demás.
            </p>
        </div>
    @else
    <div style="padding:30px;background:#fdfcfb;">
        <div class="seclabel" style="margin-bottom:6px;">Crea tu acceso</div>
        <p style="font-size:14.5px;line-height:1.6;color:var(--gris);margin:0 0 18px;max-width:60ch;">Con este acceso podrás ingresar a tu cuenta para editar tus actividades y hacer seguimiento a tu publicación.</p>

        {{--
            El correo NO está en el prototipo: pide contraseña pero nunca el
            usuario. Sin él no hay cuenta que crear —y el paso 4 habla de "el
            correo de la cuenta"—, así que va donde se crea el acceso.
        --}}
        <label class="lbl" style="margin-bottom:16px;" data-campo="email" data-obligatorio
               data-etiqueta="{{ CamposDeActividad::etiqueta('email') }}">Correo electrónico *
            <input class="fld @error('email') is-invalid @enderror" type="email" name="email"
                   value="@viejo('email')" placeholder="contacto@organizacion.cl" autocomplete="email">
            <span class="helper">Con este correo entrarás a tu cuenta.</span>
            @error('email') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl" data-campo="password" data-obligatorio
                   data-etiqueta="{{ CamposDeActividad::etiqueta('password') }}">Contraseña *
                <input class="fld @error('password') is-invalid @enderror" type="password" name="password"
                       placeholder="••••••••" autocomplete="new-password">
                <span class="helper">Mínimo 8 caracteres.</span>
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Confirmar contraseña *
                <input class="fld" type="password" name="password_confirmation"
                       placeholder="••••••••" autocomplete="new-password">
            </label>
        </div>
    </div>

    @endif

    <div style="padding:20px 30px;border-top:1px solid var(--linea);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <span class="helper">* campos obligatorios</span>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline" disabled title="Pendiente de definir">Guardar borrador</button>
            {{-- Revisa lo obligatorio de ESTE paso antes de dejar pasar. La
                 barra de pasos de arriba sigue navegando libre a propósito: ahí
                 se va a consultar, y frenar a quien vuelve a mirar un dato
                 sería peor que el problema que se está arreglando. --}}
            <button type="button" class="btn btn-primary" x-on:click="continuar(3, 4)">Continuar →</button>
        </div>
    </div>
</div>
