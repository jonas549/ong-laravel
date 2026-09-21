@extends('layouts.admin')
@section('title', 'Marquesina de organizaciones')

{{--
    Qué organizaciones salen en la tira «Organizaciones e instituciones
    participantes» del home, y en qué orden. Ver App\Support\Marquesina.

    Un solo formulario para el interruptor y la lista: se guardan siempre los
    dos, así que la lista manual se puede preparar con el automático
    encendido y apagarlo nunca la borra.
--}}

@section('content')

<div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px;">
    <div style="flex:1;min-width:260px;">
        <p class="helper" style="margin:0;">
            La tira de «Organizaciones e instituciones participantes» del home. El título se cambia en
            <a class="textlink" href="{{ route('admin.home.editar', 'participantes') }}">su sección</a>.
        </p>
    </div>
    <a class="btn btn-outline btn-sm" href="{{ route('home') }}" target="_blank" rel="noopener">Ver el sitio publicado</a>
</div>

<form method="POST" action="{{ route('admin.marquesina.update') }}"
      x-data="marquesinaAdmin({{ Js::from($elegidas) }}, {{ Js::from(route('admin.marquesina.buscar')) }})"
      x-on:submit="enviando = true"
      style="display:flex;flex-direction:column;gap:16px;max-width:760px;">
    @csrf
    @method('PUT')

    @error('organizaciones.*') <div class="alert alert-error">{{ $message }}</div> @enderror

    {{-- ─────────────────────────────── el interruptor ── --}}
    <section class="card" style="padding:20px 22px;" x-data="{ auto: {{ Js::from($automatica) }} }">
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
            <input type="checkbox" name="automatica" value="1" x-model="auto" x-on:change="cambiado = true"
                   style="margin-top:3px;width:18px;height:18px;flex:none;">
            <span>
                <strong style="display:block;font-size:15px;color:var(--gris-700);">Mostrar automáticamente todas las organizaciones registradas</strong>
                <span class="helper" style="display:block;margin-top:4px;">
                    Encendido, la marquesina se llena sola con las {{ $cuantasTodas }} organizaciones participantes
                    —las que han publicado actividades y las del listado histórico—, por orden alfabético.
                    Apagado, sale sólo la lista de abajo, en su orden.
                </span>
            </span>
        </label>

        <p class="alert alert-info" x-show="auto" x-cloak style="margin:14px 0 0;font-size:13.5px;">
            Con esto encendido la lista de abajo no se muestra, pero se guarda tal cual: al apagarlo vuelve a salir.
        </p>
    </section>

    {{-- ─────────────────────────────── la lista manual ── --}}
    <section class="card" style="padding:20px 22px;">
        <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
            <h2 style="font-size:16px;font-weight:800;margin:0;">Lista elegida</h2>
            <span class="helper" x-text="lista.length === 1 ? '1 organización' : lista.length + ' organizaciones'"></span>
        </div>
        <p class="helper" style="margin:0 0 14px;">Arrastra o usa las flechas para cambiar el orden en que pasan.</p>

        {{-- Añadir: busca entre todas las organizaciones del sistema. --}}
        <div style="position:relative;margin-bottom:14px;">
            <label class="helper" for="buscar-org" style="display:block;margin-bottom:6px;font-weight:600;">Añadir una organización</label>
            <input id="buscar-org" class="fld" type="search" autocomplete="off" placeholder="Escribe su nombre…"
                   x-model="q" x-on:input="buscar()" x-on:keydown.enter.prevent>

            <div x-show="q.trim().length >= 2 && (resultados.length || buscado)" x-cloak class="card"
                 style="margin-top:6px;padding:6px;max-height:320px;overflow:auto;">
                <p class="helper" x-show="buscando" style="margin:6px 8px;">Buscando…</p>
                <p class="helper" x-show="! buscando && buscado && ! resultados.length" style="margin:6px 8px;">Ninguna organización con ese nombre.</p>

                <template x-for="o in resultados" :key="o.id">
                    <button type="button" class="resultado-marquesina" x-on:click="anadir(o)" :disabled="enLista(o.id)"
                            style="display:flex;align-items:center;gap:10px;width:100%;padding:8px 10px;border:0;background:none;border-radius:10px;text-align:left;font:inherit;cursor:pointer;">
                        <span style="flex:none;display:grid;place-items:center;width:64px;height:28px;">
                            <template x-if="o.logo"><img :src="o.logo" alt="" style="max-width:64px;max-height:28px;object-fit:contain;"></template>
                        </span>
                        <span style="flex:1;min-width:0;font-weight:600;color:var(--gris-700);" x-text="o.nombre"></span>
                        <span class="helper" x-show="! o.activa">Desactivada</span>
                        <span class="helper" x-text="enLista(o.id) ? 'Ya está en la lista' : '+ Añadir'"
                              :style="enLista(o.id) ? '' : 'color:var(--naranjo-600);font-weight:700;'"></span>
                    </button>
                </template>
            </div>
        </div>

        <p class="helper" x-show="! lista.length" style="margin:0;padding:18px;border:1px dashed var(--linea);border-radius:14px;text-align:center;">
            La lista está vacía. Con el interruptor apagado, el home no enseña la marquesina.
        </p>

        <ol style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;">
            <template x-for="(o, i) in lista" :key="o.id">
                {{-- Envuelve en móvil: con el nombre largo, «Quitar» se salía de la tarjeta. --}}
                <li class="fila-seccion fila-marquesina" draggable="true" :data-id="o.id" style="flex-wrap:wrap;row-gap:8px;"
                    :class="arrastrando === o.id ? 'arrastrando' : ''"
                    x-on:dragstart="empezar($event, o.id)"
                    x-on:dragover.prevent="sobre(o.id)"
                    x-on:drop.prevent="terminar()"
                    x-on:dragend="terminar()">
                    <input type="hidden" name="organizaciones[]" :value="o.id">

                    <span aria-hidden="true" style="flex:none;width:20px;text-align:center;color:var(--gris);font-size:15px;">⣿</span>
                    <span class="helper" style="flex:none;width:26px;text-align:right;" x-text="i + 1"></span>
                    {{-- Sin logo no se reserva el hueco: en la marquesina sale con su nombre y nada más. --}}
                    <span x-show="o.logo" style="flex:none;display:grid;place-items:center;width:92px;height:30px;">
                        <template x-if="o.logo"><img :src="o.logo" alt="" style="max-width:92px;max-height:30px;object-fit:contain;"></template>
                    </span>
                    <span style="flex:1 1 160px;min-width:0;font-weight:700;color:var(--gris-700);overflow-wrap:anywhere;" x-text="o.nombre"></span>
                    <span x-show="! o.activa" style="font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:999px;background:var(--gris-100);color:var(--gris);"
                          title="Está desactivada: el home no la muestra aunque siga en la lista.">Desactivada, no se ve</span>

                    <span style="flex:none;display:flex;gap:4px;margin-left:auto;">
                        <button type="button" class="btn btn-ghost btn-sm" x-on:click="mover(i, i - 1)" :disabled="i === 0" aria-label="Subir" title="Subir">↑</button>
                        <button type="button" class="btn btn-ghost btn-sm" x-on:click="mover(i, i + 1)" :disabled="i === lista.length - 1" aria-label="Bajar" title="Bajar">↓</button>
                        <button type="button" class="btn btn-ghost btn-sm" x-on:click="quitar(i)" :aria-label="'Quitar ' + o.nombre">Quitar</button>
                    </span>
                </li>
            </template>
        </ol>
    </section>

    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <span class="helper" x-show="cambiado" x-cloak>Hay cambios sin guardar.</span>
    </div>
</form>

@endsection
