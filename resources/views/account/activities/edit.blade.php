@extends('layouts.public')
@section('title', 'Editar actividad · ' . config('app.name'))

{{-- mi-cuenta.html lleva el footer compacto. --}}
@php $footerCompacto = true; @endphp

@section('content')

<main style="flex:1;">
@php
    use App\Support\CamposDeActividad;

    // Lo que el servidor rechazó, con el nombre en castellano de cada campo.
    // Misma tabla que pinta los `data-etiqueta` de abajo: así el aviso del
    // servidor y el del navegador no pueden decir cosas distintas.
    $erroresDelServidor = CamposDeActividad::resumen($errors->getBag('default'));

    $tono = $activity->estado_color;

    $seleccion = fn (string $grupo, string $campo) => old($campo, $activity->termsDe($grupo)->pluck('id')->all());

    // Sólo los nombres, como en el wizard: el tipo de colaborador salió del
    // formulario (se conserva el que tuviera, ver MyActivityController).
    $colaboradores = old('colaboradores', $activity->collaborators->pluck('nombre')->all());

    // Las comunas de cada región, como en el wizard; más la de la actividad
    // si se apagó en el panel, para no perderla al abrir el editor.
    $comunas = $regiones->mapWithKeys(fn ($r) => [$r->id => $r->communes->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()]);

    if ($activity->commune && ! collect($comunas->get($activity->commune->region_id, []))->contains('id', $activity->commune_id)) {
        $comunas->put($activity->commune->region_id, collect($comunas->get($activity->commune->region_id, []))
            ->push(['id' => $activity->commune->id, 'nombre' => $activity->commune->nombre])->values());
    }

    $correoCuenta = auth()->user()->email;
@endphp

{{-- PANTALLA 2 — EDITAR ACTIVIDAD de mi-cuenta.html --}}
<div class="rise" style="max-width:900px;margin:0 auto;padding:34px 32px 96px;"
     x-data="editorActividad({
        {{-- P16: el buscador de direcciones y el punto que ya tuviera. --}}
        rutaDirecciones: {{ Js::from(route('publish.direcciones')) }},
        latitud: {{ Js::from(old('latitud', $activity->latitud)) }},
        longitud: {{ Js::from(old('longitud', $activity->longitud)) }},
        temas: {{ Js::from($seleccion('tema', 'temas')) }},
        caracteristicas: {{ Js::from($seleccion('caracteristica', 'caracteristicas')) }},
        publicos: {{ Js::from($seleccion('publico', 'publicos')) }},
        formato: {{ Js::from(old('formato', $activity->formato)) }},
        sinFecha: {{ Js::from((bool) old('sin_fecha_definida', $activity->sin_fecha_definida)) }},
        varios: {{ Js::from((bool) old('varios_dias', $activity->fecha_termino !== null)) }},
        fechaBloqueada: {{ Js::from($activity->yaPaso()) }},
        cerrada: {{ Js::from((bool) old('cerrada', $activity->cerrada)) }},
        insc: {{ Js::from((bool) old('inscripcion_habilitada', $activity->inscripcion_habilitada)) }},
        acc: {{ Js::from((bool) old('tiene_accesibilidad', $activity->tiene_accesibilidad)) }},
        colabs: {{ Js::from(array_values(array_filter($colaboradores, 'is_string'))) }},
        regionId: {{ Js::from((string) old('region_id', $activity->commune?->region_id ?? $activity->region_id)) }},
        communeId: {{ Js::from((string) old('commune_id', $activity->commune_id)) }},
        comunas: {{ Js::from($comunas) }},
        otrosId: {{ Js::from(optional($publicos->firstWhere('nombre', 'Otros'))->id) }},
        correoCuenta: {{ Js::from($correoCuenta) }},
        correoContacto: {{ Js::from(old('correo_contacto', $activity->correo_contacto)) }},
        mismoCorreo: {{ Js::from((bool) old('usar_correo_cuenta', $activity->correo_contacto && strcasecmp($activity->correo_contacto, $correoCuenta) === 0)) }},
        tipo: {{ Js::from($activity->organization?->tipo) }},
        editaLaFicha: {{ Js::from(auth()->user()->editaLaFicha()) }},
        descLen: {{ mb_strlen(\App\Support\Formulario::viejo('descripcion', $activity->descripcion ?? '')) }},
        limites: { temas: {{ $limites['tema'] ?? 'null' }}, caracteristicas: {{ $limites['caracteristica'] ?? 'null' }}, publicos: null },
        errores: {{ Js::from($erroresDelServidor) }},
     })">

    <x-cuenta.barra>
        Mi cuenta → <a href="{{ route('account.activities.index') }}">Mis actividades</a> → Editar
    </x-cuenta.barra>
    <h1 style="font-size:38px;font-weight:800;letter-spacing:-.02em;margin:0 0 18px;color:var(--ink);">Editar actividad</h1>

    @if ($activity->estado === 'publicada')
        <div style="display:flex;align-items:flex-start;gap:13px;background:#eaf6f5;border:1.5px solid #cbe7e5;border-radius:18px;padding:16px 18px;margin-bottom:26px;">
            <span style="flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:999px;background:var(--turquesa);color:#fff;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
            </span>
            <div style="font-size:14.5px;line-height:1.6;color:#0d6b64;">Esta actividad está publicada. Los cambios que guardes se actualizarán inmediatamente en el sitio web.<br><span style="color:#3f8b85;">Si modificas la fecha, el lugar o la hora, te recomendamos informar también a las personas inscritas.</span></div>
        </div>

        {{--
            El QR de la encuesta.

            Sólo con la actividad publicada, que es cuando el código lleva a
            algún sitio: antes de publicarse la encuesta no está abierta y un
            cartel impreso desde aquí enseñaría un aviso de «todavía no».

            Es el mismo bloque que se enseña en el panel; vive en un componente
            para que los dos digan lo mismo.
        --}}
        <div style="margin-bottom:26px;">
            <x-qr-actividad
                :activity="$activity"
                :ruta-png="route('account.activities.qr.png', $activity)"
                :ruta-svg="route('account.activities.qr.svg', $activity)" />
        </div>
    @else
        {{-- El prototipo sólo dibuja el aviso de "publicada"; el resto de los
             estados usa el mismo bloque con su propio color y su propio texto. --}}
        <div style="display:flex;align-items:flex-start;gap:13px;background:{{ $tono['bg'] }};border:1.5px solid {{ $tono['borde'] }};border-radius:18px;padding:16px 18px;margin-bottom:26px;">
            <span style="flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:999px;background:{{ $tono['tono'] }};color:#fff;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 8v5"></path><path d="M12 17h.01"></path></svg>
            </span>
            <div style="font-size:14.5px;line-height:1.6;color:{{ $tono['ink'] }};">
                {{ $activity->estado_label }}.
                @if ($activity->estado === 'ajustes' && $activity->observaciones_revision)
                    <br><span style="opacity:.85;">{{ $activity->observaciones_revision }}</span>
                @endif
                @if ($activity->vuelveDeAjustes())
                    <br><span style="opacity:.85;">Recibimos tus correcciones y las estamos revisando.</span>
                @endif
            </div>
        </div>
    @endif

    {{--
        La conversación con la ONG.

        Sólo se pinta si hay algo dicho: en una actividad que nadie ha moderado
        todavía, una tarjeta vacía con el título «Conversación» sólo ocupa
        sitio y hace pensar que falta algo.
    --}}
    @if ($activity->hilo->isNotEmpty())
        <section class="card" style="padding:22px 24px;margin-bottom:26px;">
            <h2 style="font-size:16px;font-weight:700;margin:0 0 14px;color:var(--ink);">Conversación con el equipo organizador</h2>
            <x-hilo-moderacion :activity="$activity" lado="organizacion" />
        </section>
    @endif

    {{--
        Antes aquí ponía «Revisa los campos marcados: hay 1 dato por corregir»,
        que dice que algo falla y deja el trabajo de buscarlo. Lo que hacía
        saltar al campo era el `required` del navegador, que sólo cubría el
        título: en los grupos de chips —que son tres y dos son obligatorios— no
        hay `required` que valga.
    --}}
    <x-resumen-errores :errores="$erroresDelServidor" style="margin-bottom:24px;" />

    {{-- Los dos manejadores de abajo van en el formulario y no campo a campo:
         uno solo cubre los treinta y pico, y al rellenar uno se le quita la
         marca en el acto. Ver resources/js/formularios.js. --}}
    <form method="POST" action="{{ route('account.activities.update', $activity) }}" enctype="multipart/form-data"
          x-on:submit="revisarAntesDeEnviar($event)"
          x-on:input="revisarCampo($event.target.closest('[data-campo]')?.dataset.campo)"
          x-on:change="revisarCampo($event.target.closest('[data-campo]')?.dataset.campo)">
        @csrf
        @method('PUT')

        <div class="card" style="overflow:hidden;">

            {{-- Tanda del 09/10 (punto 9): los MISMOS campos que el paso 4 del
                 wizard, en un parcial que comparten. Ver su cabecera. --}}
            @include('public.partials.campos-actividad', ['actividad' => $activity, 'organizacion' => $activity->organization])

            {{--
                ── Lo que le cuentas a la ONG al devolver la actividad ──

                Sólo cuando viene de «necesita ajustes», que es el único momento
                en que hay algo que responder. Va pegado al botón de guardar
                porque se escribe al terminar de corregir, no al empezar.

                Es opcional a propósito. Obligarlo convertiría el hilo en un
                peaje para poder reenviar, y quien ya hizo lo que le pidieron no
                tiene por qué justificarse para que le vuelvan a mirar.
            --}}
            @if ($activity->estado === 'ajustes')
                <div style="padding:26px 30px;border-top:1px solid var(--linea);background:#fffdf9;">
                    <label class="lbl" for="mensaje_ajustes" style="max-width:none;">
                        ¿Quieres contarle algo al equipo organizador? (opcional)
                        <textarea class="fld @error('mensaje_ajustes') is-invalid @enderror"
                                  id="mensaje_ajustes" name="mensaje_ajustes" rows="3" style="resize:vertical;"
                                  placeholder="Por ejemplo: corregí la fecha y agregué la dirección exacta.">{{ \App\Support\Formulario::viejo('mensaje_ajustes') }}</textarea>
                        <span class="helper">Lo verán junto a sus observaciones al revisar tu actividad.</span>
                        @error('mensaje_ajustes') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <p class="helper" style="margin:14px 0 0;">
                        Al guardar, tu actividad vuelve automáticamente a revisión. No tienes que enviarla otra vez.
                    </p>
                </div>
            @endif

            {{-- ── Barra de acciones ── --}}
            <div style="padding:20px 30px;border-top:1px solid var(--linea);background:#fdfcfb;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                @if ($activity->estado !== 'cancelada')
                    <button type="button" class="btn btn-danger btn-sm" x-on:click="modalCancelar = true">Cancelar actividad</button>
                @else
                    <span></span>
                @endif

                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="{{ route('activities.show', $activity) }}" target="_blank" rel="noopener" class="btn btn-outline">Vista previa</a>
                    <a href="{{ route('account.activities.index') }}" class="btn btn-outline">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Actualizar actividad</button>
                </div>
            </div>
        </div>
    </form>

    {{--
        Fuera del prototipo: sin esto, un borrador —el que deja "Duplicar"—
        no tiene forma de llegar a revisión.
    --}}
    {{--
        En «necesita ajustes» este botón ya no va: guardar devuelve la actividad
        a revisión sola. Dejarlo obligaba a dos pasos para una sola intención, y
        quien sólo daba al primero se quedaba mirando el aviso rosa creyendo que
        su corrección no había servido.

        En un borrador sigue haciendo falta: ahí guardar es guardar, y el
        borrador que deja «Duplicar» no tendría otra forma de llegar a revisión.
    --}}
    @if ($activity->estado === 'borrador')
        <form method="POST" action="{{ route('account.activities.submit', $activity) }}"
              style="margin-top:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            @csrf
            <button type="submit" class="btn btn-primary">Enviar a revisión</button>
            <span class="helper" style="max-width:52ch;">Guarda primero los cambios: al enviarla, el equipo organizador la revisa antes de publicarla.</span>
        </form>
    @endif

    {{-- ══ MODAL DE CANCELACIÓN ══ --}}
    <div x-show="modalCancelar" x-cloak
         style="position:fixed;inset:0;z-index:80;background:rgba(51,54,58,.45);backdrop-filter:blur(3px);display:grid;place-items:center;padding:24px;"
         x-on:click.self="modalCancelar = false" x-on:keydown.escape.window="modalCancelar = false">
        <div style="background:#fff;border-radius:26px;padding:34px 32px;max-width:500px;width:100%;box-sizing:border-box;box-shadow:0 40px 80px -40px rgba(0,0,0,.5);text-align:center;"
             role="dialog" aria-modal="true" aria-labelledby="me-t">
            <span style="display:grid;place-items:center;width:60px;height:60px;border-radius:999px;background:#fdeaf0;color:var(--rosa);margin:0 auto 18px;">
                <svg width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
            </span>

            <h2 id="me-t" style="font-size:26px;font-weight:800;line-height:1.2;margin:0 0 12px;color:var(--ink);text-wrap:pretty;">¿Seguro que quieres cancelar esta actividad?</h2>
            <p style="font-size:15.5px;line-height:1.65;color:var(--gris);margin:0 0 16px;text-wrap:pretty;">Dejará de aparecer en el calendario del Día del Patrimonio Social y las personas inscritas recibirán una notificación por correo. Tu actividad quedará guardada en tus borradores.</p>

            @if ($activity->inscritos_count > 0)
                <p style="font-size:14px;line-height:1.6;color:#7a5e00;background:#fff8e6;border:1.5px solid #f6e0c6;border-radius:14px;padding:13px 16px;margin:0 0 24px;text-wrap:pretty;">
                    Hay {{ $activity->inscritos_count }} {{ \App\Support\Texto::plural('persona', $activity->inscritos_count) }} {{ \App\Support\Texto::plural('inscrita', $activity->inscritos_count) }}. Les enviaremos automáticamente un correo informando la cancelación.
                </p>
            @endif

            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1;justify-content:center;" x-on:click="modalCancelar = false">Volver</button>
                <form method="POST" action="{{ route('account.activities.cancel', $activity) }}" style="flex:1.2;display:flex;">
                    @csrf
                    <button type="submit" class="btn btn-danger" style="flex:1;justify-content:center;">Cancelar actividad</button>
                </form>
            </div>
        </div>
    </div>
</div>
</main>
@endsection

{{--
    El componente `editorActividad` vivía aquí en un <script> suelto. Se mudó a
    resources/js/editor-actividad.js al darle la guía de errores, que es un
    objeto compartido con el wizard y con el formulario de inscripción. Se
    registra con Alpine.data en resources/js/app.js.
--}}

