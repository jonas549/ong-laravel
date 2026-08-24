@extends('layouts.public')

@section('title', 'Publica tu actividad · ' . config('app.name'))

@section('content')
@php
    $pasos = [
        1 => '¿Voluntariado?',
        2 => 'Tipo de org.',
        3 => 'Tu organización',
        4 => 'Tu actividad',
    ];
    // Si la validación falló, volvemos al paso donde está el primer error.
    $pasoInicial = $errors->any()
        ? ($errors->hasAny(['org_nombre', 'org_tipo', 'org_tipo_otro', 'org_descripcion', 'org_num_voluntarios', 'org_unidad_educativa', 'email', 'password']) ? 3 : 4)
        : 1;
@endphp

<div style="background:var(--bg-warm);min-height:100vh;padding:40px 0 88px;"
     x-data="wizard({{ $pasoInicial }}, {{ Js::from(old('org_tipo', $tiposOrg[0])) }}, {{ Js::from(old('temas', [])) }}, {{ Js::from(old('caracteristicas', [])) }}, {{ Js::from(old('publicos', [])) }}, {{ Js::from(old('accesos', [])) }})">

    <div style="max-width:920px;margin:0 auto;padding:0 40px;">

        {{-- ── Barra de pasos ── --}}
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:34px;">
            @foreach ($pasos as $n => $label)
                <button type="button" class="steplink"
                        x-on:click="irA({{ $n }})"
                        x-bind:style="paso === {{ $n }}
                            ? 'color:var(--naranjo-600)'
                            : (paso > {{ $n }} ? 'color:var(--gris-700)' : 'color:#b7babe')">
                    <span style="display:grid;place-items:center;width:26px;height:26px;border-radius:999px;font-size:12.5px;font-weight:700;border:1.5px solid;"
                          x-bind:style="paso === {{ $n }}
                              ? 'background:var(--naranjo);color:#fff;border-color:var(--naranjo)'
                              : (paso > {{ $n }} ? 'background:var(--naranjo-100);color:var(--naranjo-600);border-color:var(--naranjo)' : 'background:#fff;color:#b7babe;border-color:#e6e8ea')">{{ $n }}</span>
                    {{ $label }}
                </button>
                @if (! $loop->last)
                    <span aria-hidden="true" style="color:#d8dade;">›</span>
                @endif
            @endforeach
        </div>

        @if ($errors->any())
            <div class="alert alert-error" style="margin-bottom:24px;">
                Revisa los campos marcados: hay {{ $errors->count() }} {{ Str::plural('dato', $errors->count()) }} por corregir.
            </div>
        @endif

        {{-- ── Paso 1: ¿buscas voluntariado? ── --}}
        <section x-show="paso === 1" x-cloak class="card rise" style="padding:40px;">
            <div class="seclabel" style="margin-bottom:10px;">Antes de empezar</div>
            <h1 style="font-weight:800;font-size:30px;line-height:1.15;margin:0 0 12px;">¿Estás buscando hacer voluntariado?</h1>
            <p class="helper" style="font-size:14.5px;margin:0 0 26px;">Si quieres participar como voluntario, te llevamos al lugar correcto.</p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;">
                <button type="button" class="bigopt" x-on:click="redirigir = true">
                    <div style="font-weight:700;font-size:17px;margin-bottom:6px;">Sí, quiero ser voluntario</div>
                    <div class="helper">Busco actividades para participar.</div>
                </button>
                <button type="button" class="bigopt" x-on:click="irA(2)">
                    <div style="font-weight:700;font-size:17px;margin-bottom:6px;">No, quiero publicar una actividad</div>
                    <div class="helper">Represento a una organización o empresa.</div>
                </button>
            </div>
        </section>

        {{-- Modal de desvío a voluntariado --}}
        <div x-show="redirigir" x-cloak
             style="position:fixed;inset:0;z-index:100;display:grid;place-items:center;background:rgba(30,25,20,.45);padding:24px;"
             x-on:click.self="redirigir = false" x-on:keydown.escape.window="redirigir = false">
            <div class="card" style="max-width:440px;padding:32px;" role="dialog" aria-modal="true" aria-labelledby="mv-t">
                <h2 id="mv-t" style="font-size:22px;font-weight:800;margin:0 0 12px;">Te llevamos a las actividades</h2>
                <p class="helper" style="font-size:14.5px;margin:0 0 22px;">Ahí puedes buscar por región y comuna, e inscribirte en la que te interese.</p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="{{ route('activities.index') }}" class="btn btn-primary">Ver actividades</a>
                    <button type="button" class="btn btn-ghost" x-on:click="redirigir = false; irA(2)">No, quiero publicar</button>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('publish.store') }}" x-ref="form">
            @csrf

            {{-- ── Paso 2: tipo de organización ── --}}
            <section x-show="paso === 2" x-cloak class="card rise" style="padding:40px;">
                <div class="seclabel" style="margin-bottom:10px;">Paso 2</div>
                <h2 style="font-weight:800;font-size:28px;margin:0 0 22px;">¿Qué tipo de organización eres?</h2>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
                    @foreach ($tiposOrg as $t)
                        <button type="button" class="tileopt"
                                x-bind:class="tipo === {{ Js::from($t) }} ? 'tileopt on' : 'tileopt'"
                                x-on:click="tipo = {{ Js::from($t) }}">{{ $t }}</button>
                    @endforeach
                </div>
                <input type="hidden" name="org_tipo" x-bind:value="tipo">

                <div style="display:flex;gap:10px;margin-top:30px;">
                    <button type="button" class="btn btn-ghost" x-on:click="irA(1)">Atrás</button>
                    <button type="button" class="btn btn-primary" x-on:click="irA(3)">Continuar</button>
                </div>
            </section>

            {{-- ── Paso 3: organización y cuenta ── --}}
            <section x-show="paso === 3" x-cloak class="card rise" style="padding:40px;">
                @include('public.publish.steps.organizacion')

                <div style="display:flex;gap:10px;margin-top:30px;">
                    <button type="button" class="btn btn-ghost" x-on:click="irA(2)">Atrás</button>
                    <button type="button" class="btn btn-primary" x-on:click="irA(4)">Continuar</button>
                </div>
            </section>

            {{-- ── Paso 4: la actividad ── --}}
            <section x-show="paso === 4" x-cloak class="card rise" style="padding:40px;">
                @include('public.publish.steps.actividad')

                <div style="display:flex;gap:10px;margin-top:30px;">
                    <button type="button" class="btn btn-ghost" x-on:click="irA(3)">Atrás</button>
                    <button type="submit" class="btn btn-primary">Enviar a revisión</button>
                </div>
                <p class="helper" style="margin-top:14px;">* campos obligatorios</p>
            </section>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function wizard(pasoInicial, tipoInicial, temas, caracs, publicos, accesos) {
        return {
            paso: pasoInicial,
            redirigir: false,
            tipo: tipoInicial,

            // Los topes vienen del prototipo y se repiten en el Form Request:
            // el cliente evita el error, el servidor lo garantiza.
            sel: {
                temas: temas.map(Number),
                caracteristicas: caracs.map(Number),
                publicos: publicos.map(Number),
                accesos: accesos.map(Number),
            },
            limites: {
                temas: {{ $limites['tema'] ?? 'null' }},
                caracteristicas: {{ $limites['caracteristica'] ?? 'null' }},
                publicos: null,
                accesos: null,
            },

            descLen: {{ mb_strlen(old('descripcion', '')) }},
            colaboradores: {{ Js::from(array_values(array_filter(old('colaboradores', [])))) }},

            irA(n) {
                this.paso = n;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            marcado(grupo, id) {
                return this.sel[grupo].includes(id);
            },

            alternar(grupo, id) {
                const lista = this.sel[grupo];
                const i = lista.indexOf(id);

                if (i !== -1) {
                    lista.splice(i, 1);
                    return;
                }

                const tope = this.limites[grupo];
                if (tope && lista.length >= tope) {
                    lista.shift();
                }

                lista.push(id);
            },

            agregarColaborador(e) {
                const v = e.target.value.trim();
                if (!v) return;
                this.colaboradores.push(v);
                e.target.value = '';
            },

            quitarColaborador(i) {
                this.colaboradores.splice(i, 1);
            },
        };
    }
</script>
@endpush
@endsection
