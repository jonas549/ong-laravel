@extends('layouts.public')

@section('title', 'Publica tu actividad · ' . config('app.name'))

{{-- mi-cuenta.html y publicar-actividad.html llevan el footer compacto. --}}
@php $footerCompacto = true; @endphp

@section('content')
@php
    use App\Support\CamposDeActividad;

    /*
     * Lo que el servidor rechazó, traducido a la lista que pinta el resumen de
     * arriba del formulario y que la guía usa para saltar de campo en campo.
     * El paso al que se vuelve sale de ahí: del primer error de la lista, que
     * va ordenada como se ven los campos en pantalla.
     *
     * Antes esto era una lista de nombres de campo escrita a mano aquí mismo
     * para decidir entre el paso 3 y el 4. Cualquier campo nuevo que no
     * estuviera en ella caía en el 4 por descarte.
     */
    $erroresDelServidor = CamposDeActividad::resumen($errors->getBag('default'));

    $pasoInicial = $erroresDelServidor[0]['paso'] ?? ($errors->any() ? 4 : 1);
@endphp

<div x-data="wizard({
        paso: {{ $pasoInicial }},
        tipo: {{ Js::from(old('org_tipo', $organizacion?->tipo ?? $tiposOrg[0])) }},
        temas: {{ Js::from(old('temas', [])) }},
        caracteristicas: {{ Js::from(old('caracteristicas', [])) }},
        publicos: {{ Js::from(old('publicos', [])) }},
        formato: {{ Js::from(old('formato', $formatos[0])) }},
        sinFecha: {{ Js::from((bool) old('sin_fecha_definida')) }},
        acc: {{ Js::from((bool) old('tiene_accesibilidad')) }},
        insc: {{ Js::from((bool) old('inscripcion_habilitada', true)) }},
        {{-- El prototipo arranca con colab en true, con el bloque desplegado. --}}
        colab: {{ Js::from($errors->any() ? count(array_filter(old('colaboradores', []))) > 0 : true) }},
        colabs: {{ Js::from(array_values(array_filter(old('colaboradores', [])))) }},
        regionId: {{ Js::from(old('region_id')) }},
        communeId: {{ Js::from(old('commune_id')) }},
        mismoCorreo: {{ Js::from((bool) old('usar_correo_cuenta', true)) }},
        descLen: {{ mb_strlen(\App\Support\Formulario::viejo('descripcion')) }},
        otrosId: {{ Js::from(optional($publicos->firstWhere('nombre', 'Otros'))->id) }},
        limites: { temas: {{ $limites['tema'] ?? 'null' }}, caracteristicas: {{ $limites['caracteristica'] ?? 'null' }}, publicos: null },
        comunas: {{ Js::from($regiones->mapWithKeys(fn ($r) => [$r->id => $r->communes->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()])) }},
        errores: {{ Js::from($erroresDelServidor) }},
     })">

@include('public.publish.partials.pasos')

<main style="flex:1;">


    {{-- ══ PASO 1 — ¿VOLUNTARIADO? ══ --}}
    <div x-show="paso === 1" x-cloak data-paso="1" class="rise" style="max-width:860px;margin:0 auto;padding:64px 32px 96px;">
        <h1 style="font-size:40px;font-weight:800;letter-spacing:-.02em;line-height:1.1;margin:0 0 12px;color:var(--ink);text-wrap:pretty;">¿Necesitas convocar a personas voluntarias para esta actividad?</h1>
        <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 0 36px;max-width:58ch;text-wrap:pretty;">Con esta respuesta sabremos si tu actividad necesita una convocatoria de voluntariado o solo difusión en el calendario.</p>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
            <button type="button" class="bigopt" x-on:click="redirigir = true">
                <span style="display:grid;place-items:center;width:52px;height:52px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo);margin-bottom:16px;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M19 8v6M22 11h-6"></path></svg>
                </span>
                <div style="font-family:var(--font-title);font-size:22px;font-weight:800;color:var(--ink);margin-bottom:8px;">Sí, necesito voluntarios</div>
                <div style="font-size:15px;line-height:1.6;color:var(--gris);">Crea tu actividad e invita a personas interesadas a postular.</div>
            </button>

            <button type="button" class="bigopt" x-on:click="irA(2)">
                <span style="display:grid;place-items:center;width:52px;height:52px;border-radius:999px;background:#eaf6f5;color:#0d6b64;margin-bottom:16px;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 13v-2z"></path><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"></path></svg>
                </span>
                <div style="font-family:var(--font-title);font-size:22px;font-weight:800;color:var(--ink);margin-bottom:8px;">No, solo quiero difundir mi actividad</div>
                <div style="font-size:15px;line-height:1.6;color:var(--gris);">Las personas podrán conocerla e inscribirse como asistentes.</div>
            </button>
        </div>
    </div>

    {{--
        `revisarAntesDeEnviar` corta el envío cuando falta algo obligatorio y lo
        enseña arriba. No sustituye a la validación del servidor —que sigue
        siendo la que manda—, le ahorra el viaje a lo que se puede ver desde
        aquí: un campo vacío.

        Los dos manejadores de abajo van en el formulario y no campo a campo: un
        solo `x-on:` cubre los cuarenta y pico, y al rellenar uno se le quita la
        marca roja en el acto. Dejar en rojo lo ya corregido es la otra mitad de
        no enterarse, porque no se sabe qué queda.
    --}}
    <form method="POST" action="{{ route('publish.store') }}" enctype="multipart/form-data"
          x-on:submit="revisarAntesDeEnviar($event)"
          x-on:input="revisarCampo($event.target.closest('[data-campo]')?.dataset.campo)"
          x-on:change="revisarCampo($event.target.closest('[data-campo]')?.dataset.campo)">
        @csrf

        {{-- ══ PASO 2 — TIPO DE ORGANIZACIÓN ══ --}}
        <div x-show="paso === 2" x-cloak data-paso="2" class="rise" style="max-width:900px;margin:0 auto;padding:64px 32px 96px;">
            <h1 style="font-size:40px;font-weight:800;letter-spacing:-.02em;line-height:1.1;margin:0 0 12px;color:var(--ink);">¿Qué tipo de organización eres?</h1>
            <p style="font-size:17px;line-height:1.65;color:var(--gris);margin:0 0 36px;max-width:56ch;">Según tu respuesta te pediremos solo los datos que corresponden.</p>

            {{-- Este paso siempre trae una opción marcada, así que no puede
                 quedarse vacío por descuido. El resumen está igualmente
                 porque `org_tipo` sí puede rebotar del servidor, y sin él la
                 guía mandaría a la persona a un paso donde no hay nada que
                 leer. --}}
            <x-resumen-errores :errores="$erroresDelServidor" />

            <div class="grid-3" data-campo="org_tipo"
                 data-etiqueta="{{ \App\Support\CamposDeActividad::etiqueta('org_tipo') }}"
                 style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;">
                @foreach ($tiposOrg as $t)
                    <button type="button"
                            x-bind:class="tipo === {{ Js::from($t) }} ? 'tileopt on' : 'tileopt'"
                            x-on:click="tipo = {{ Js::from($t) }}">{{ $t }}</button>
                @endforeach
            </div>
            <input type="hidden" name="org_tipo" x-bind:value="tipo">

            <div style="display:flex;justify-content:space-between;gap:12px;margin-top:36px;">
                <button type="button" class="btn btn-outline" x-on:click="irA(1)">← Volver</button>
                <button type="button" class="btn btn-primary" x-on:click="irA(3)">Continuar →</button>
            </div>
        </div>

        {{-- ══ PASO 3 — TU ORGANIZACIÓN ══ --}}
        <div x-show="paso === 3" x-cloak data-paso="3" class="rise" style="max-width:880px;margin:0 auto;padding:48px 32px 96px;">
            @include('public.publish.steps.organizacion')
        </div>

        {{-- ══ PASO 4 — TU ACTIVIDAD ══ --}}
        <div x-show="paso === 4" x-cloak data-paso="4" class="rise" style="max-width:880px;margin:0 auto;padding:48px 32px 96px;">
            @include('public.publish.steps.actividad')
        </div>
    </form>

</main>
{{-- ══ MODAL — DESVÍO A VOLUNTARIADOS CHILE ══ --}}
<div x-show="redirigir" x-cloak
     style="position:fixed;inset:0;z-index:80;background:rgba(51,54,58,.45);backdrop-filter:blur(3px);display:grid;place-items:center;padding:24px;"
     x-on:click.self="cerrarRedirigir()" x-on:keydown.escape.window="cerrarRedirigir()">
    <div style="background:#fff;border-radius:26px;padding:34px 32px;max-width:480px;width:100%;box-sizing:border-box;box-shadow:0 40px 80px -40px rgba(0,0,0,.5);text-align:center;"
         role="dialog" aria-modal="true" aria-labelledby="mv-t">
        <span style="display:grid;place-items:center;width:60px;height:60px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo);margin:0 auto 18px;">
            <svg width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 5h5v5"></path><path d="M19 5 10 14"></path><path d="M19 14v5H5V5h5"></path></svg>
        </span>
        <h2 id="mv-t" style="font-size:26px;font-weight:800;line-height:1.2;margin:0 0 10px;color:var(--ink);">Ahora vas a Voluntariados Chile</h2>
        <p style="font-size:15.5px;line-height:1.65;color:var(--gris);margin:0 0 8px;text-wrap:pretty;">Ahí podrás completar tu convocatoria. Al terminar, tu actividad se suma al calendario del Día del Patrimonio Social.</p>
        <p style="font-size:13.5px;color:#b7babe;margin:0 0 24px;">Redirigiendo en 5 segundos…</p>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline" style="flex:1;justify-content:center;" x-on:click="cerrarRedirigir()">Volver</button>
            <button type="button" class="btn btn-primary" style="flex:1.4;justify-content:center;" x-on:click="cerrarRedirigir()">Ir a Voluntariados Chile →</button>
        </div>
    </div>
</div>
</div>
@endsection

{{--
    El componente `wizard` vivía aquí en un <script> suelto. Se mudó a
    resources/js/wizard.js al darle la guía de errores: son dos cosas que se
    tienen que hablar —la guía salta al paso 3 para enseñar un campo del 3
    estando en el 4— y componerlas en un módulo es más claro que apilar objetos
    dentro de un atributo. Se registra con Alpine.data en resources/js/app.js.
--}}

