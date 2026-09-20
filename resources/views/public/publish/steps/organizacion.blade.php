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

    /*
     * C4: qué se le pregunta y qué no.
     *
     * Con sesión abierta sólo se pide lo que falte en su ficha; lo demás viaja
     * en un campo oculto con el valor que ya tiene. Hay que mandarlo —las
     * reglas del servidor lo exigen— pero no hay por qué pedírselo otra vez.
     *
     * Sin sesión, `$faltan` llega vacío y se pide todo, que es lo de siempre.
     */
    $faltan = $faltanDeLaOrganizacion ?? [];
    $conFicha = $organizacion !== null;
    $pedir = fn (string $campo) => ! $conFicha || in_array($campo, $faltan, true);
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
            {{--
                El buscador de organizaciones (P9, P10 y P11).

                La marcación vive en `<x-buscador-organizacion>` y la comparten
                este paso y la pantalla de crear cuenta de organizador. Estaba
                sólo aquí, y por eso aquella se quedó con un campo de texto
                normal durante una tanda entera (C1).

                Sin `required`: la guía de errores del wizard cubre los cinco
                pasos, y un control inválido dentro de un paso oculto hace que
                Chrome corte el envío sin decir nada.
            --}}
            @if ($pedir('org_nombre'))
                <x-buscador-organizacion
                    :valor="\App\Support\Formulario::viejo('org_nombre', $organizacion?->nombre)" />

                <label class="lbl">Tipo de organización
                    <input class="fld" x-bind:value="tipo" readonly style="background:#f8f9fa;color:var(--gris);">
                    <span class="helper" x-text="reclamando ? 'Viene de nuestro listado.' : 'Prellenado del paso anterior.'">Prellenado del paso anterior.</span>
                </label>
            @else
                {{-- Ya lo tiene: viaja, pero no se pregunta (C4). --}}
                <input type="hidden" name="org_nombre" value="{{ $organizacion->nombre }}">
            @endif
        </div>

        {{--
            Los dos campos que dependen del tipo van SIEMPRE en el HTML, incluso
            si la ficha ya los tiene: el tipo se elige en el paso 2 y se puede
            cambiar sin recargar, así que el servidor no sabe cuál de los dos va
            a hacer falta. Un campo obligatorio que no está en pantalla es el
            peor error de todos (bloque K).

            Lo que sí sale de la ficha es si se enseñan: con el tipo sin tocar y
            el dato ya guardado, no hay nada que preguntar (C4).
        --}}
        <label class="lbl" x-show="esOtra() && (tipoCambiado() || {{ Js::from($pedir('org_tipo_otro')) }})"
               x-cloak data-campo="org_tipo_otro" data-obligatorio
               data-etiqueta="{{ CamposDeActividad::etiqueta('org_tipo_otro') }}">Describe tu organización *
            <input class="fld @error('org_tipo_otro') is-invalid @enderror" name="org_tipo_otro"
                   value="@viejo('org_tipo_otro', $organizacion?->tipo_otro)" placeholder="Otra (especificar)">
            <span class="helper">Se muestra solo al seleccionar "Otra".</span>
            @error('org_tipo_otro') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        {{--
            P15: «¿Cuántos trabajadores participan como voluntarios?» se fue de
            aquí al paso de la actividad. Estaba junto al nombre de la empresa,
            que es donde se cuenta quién eres, y lo que pregunta es cuánta gente
            pone la empresa EN LA ACTIVIDAD. Lo pidió el cliente.

            Se sigue guardando en la organización, que es donde vive el campo.
        --}}

        <label class="lbl" x-show="esEducativa() && (tipoCambiado() || {{ Js::from($pedir('org_unidad_educativa')) }})"
               x-cloak data-campo="org_unidad_educativa" data-obligatorio
               data-etiqueta="{{ CamposDeActividad::etiqueta('org_unidad_educativa') }}">¿Qué unidad, grupo o comunidad educativa organiza la actividad? *
            <input class="fld @error('org_unidad_educativa') is-invalid @enderror" name="org_unidad_educativa"
                   value="@viejo('org_unidad_educativa', $organizacion?->unidad_educativa)" placeholder="Ej. Facultad de Enfermería, Centro de Estudiantes, 3° medio B">
            @error('org_unidad_educativa') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        {{--
            P10: reclamando una organización del listado, el logo no se pide.
            Ya lo tiene la ONG, y volver a pedírselo a quien sólo venía a
            ponerse una contraseña es el trabajo que el punto quita.
        --}}
        {{--
            P19: el logo se reduce en el navegador antes de subirlo, y el aviso
            de peso llega al elegir el archivo y no después de enviar. Ver
            resources/js/imagenes.js.
        --}}
        @if ($pedir('org_logo'))
        <div data-campo="org_logo" data-etiqueta="Logo de la organización"
             x-data="campoImagen({ maxKb: 500, ladoMaximo: 800, que: 'El logo' })"
             x-show="! reclamando">
            <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:8px;">Logo de la organización</div>
            <div style="display:flex;align-items:center;gap:16px;">
                <span style="display:grid;place-items:center;width:76px;height:76px;border-radius:20px;border:1.5px dashed #dcdee1;background:#fbfbfc;color:#c3c6ca;flex:none;overflow:hidden;">
                    <img x-show="previa" x-cloak x-bind:src="previa" alt="" style="width:100%;height:100%;object-fit:cover;">
                    <svg x-show="!previa" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path></svg>
                </span>
                <div>
                    <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                        <span x-text="tiene ? 'Cambiar imagen' : 'Subir imagen'">Subir imagen</span>
                        <input type="file" name="org_logo" accept="image/jpeg,image/png,image/webp" style="display:none;"
                               x-on:change="elegir($event)">
                    </label>
                    <div class="helper" style="margin-top:7px;">PNG o JPG · máx. 500 KB · 400×400 px recomendado. Si no subes logo, se mostrará un ícono genérico.</div>
                    <div class="helper" x-show="reduciendo" x-cloak>Preparando la imagen…</div>
                    <div class="helper" x-show="tiene && ! error" x-cloak>
                        <span x-text="nombre"></span> · <span x-text="peso"></span>
                    </div>
                    <span class="field-error" x-show="error" x-cloak x-text="error"></span>
                    <x-archivo-retenido campo="org_logo" />
                    @error('org_logo') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
        @endif
    </div>

    {{--
        Los dos bloques van SIEMPRE en el HTML y se turnan con Alpine, no con
        un `@if` del servidor.

        El motivo es P14: desde el 2026-09-20 se puede iniciar sesión a mitad
        del wizard sin recargar la página, así que el bloque tiene que poder
        cambiar sin que el servidor vuelva a pintar nada. `conSesion` nace de
        lo que diga el servidor y a partir de ahí lo lleva el componente.
    --}}

    {{--
        Con la sesión abierta no hay acceso que crear: la actividad va a la
        cuenta que ya existe. Se conserva el bloque —mismo fondo, mismo
        `seclabel`, misma caja— para no dejar un hueco donde el fuente pone
        una sección, pero en vez de los campos va el aviso de a qué cuenta
        se suma.
    --}}
    <div style="padding:30px;background:#fdfcfb;" x-show="conSesion" x-cloak>
        <div class="seclabel" style="margin-bottom:6px;">Tu cuenta</div>
        <p style="font-size:14.5px;line-height:1.6;color:var(--gris);margin:0;max-width:60ch;">
            Esta actividad se sumará a tu cuenta, <strong style="color:var(--ink);" x-text="correoCuenta">{{ auth()->user()?->email }}</strong>.
            La verás en «Mis actividades» junto a las demás.
        </p>
    </div>

    <div style="padding:30px;background:#fdfcfb;" x-show="! conSesion" x-cloak>
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
                   x-model="correoCuenta" placeholder="contacto@organizacion.cl" autocomplete="email"
                   x-bind:disabled="conSesion">
            <span class="helper">Con este correo entrarás a tu cuenta.</span>
            @error('email') <span class="field-error">{{ $message }}</span> @enderror

            {{--
                P11. Cuando el correo ya tiene cuenta, decirlo no basta: hay que
                dar las dos salidas. Van aquí, pegadas al campo, y no dentro del
                mensaje de error: el resumen de arriba escribe los mensajes con
                `x-text`, que escapa el HTML, así que unos enlaces metidos en el
                texto se leerían literales.

                Se reconoce por la constante y no comparando la frase suelta:
                cambiar el texto no puede apagar los enlaces sin que nadie lo
                note.
            --}}
            @if ($errors->get('email') && in_array(\App\Support\ReglasDeCampo::CORREO_YA_EXISTE, $errors->get('email'), true))
                <span class="helper" style="display:block;margin-top:2px;">
                    <a class="textlink" href="{{ route('account.login') }}">Inicia sesión</a>
                    o <a class="textlink" href="{{ route('password.request') }}">recupera tu contraseña</a>
                    si no la recuerdas.
                </span>
            @endif
        </label>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl" data-campo="password" data-obligatorio
                   data-etiqueta="{{ CamposDeActividad::etiqueta('password') }}">Contraseña *
                <input class="fld @error('password') is-invalid @enderror" type="password" name="password"
                       placeholder="••••••••" autocomplete="new-password"
                       x-bind:disabled="conSesion">
                <span class="helper">Mínimo 8 caracteres.</span>
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Confirmar contraseña *
                <input class="fld" type="password" name="password_confirmation"
                       placeholder="••••••••" autocomplete="new-password"
                       x-bind:disabled="conSesion">
            </label>
        </div>
    </div>

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
