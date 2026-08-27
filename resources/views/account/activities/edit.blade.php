@extends('layouts.public')
@section('title', 'Editar actividad · ' . config('app.name'))

{{-- mi-cuenta.html lleva el footer compacto. --}}
@php $footerCompacto = true; @endphp

@section('content')

<main style="flex:1;">
@php
    $tono = $activity->estado_color;

    $seleccion = fn (string $grupo, string $campo) => old($campo, $activity->termsDe($grupo)->pluck('id')->all());

    $colaboradores = old('colaboradores', $activity->collaborators
        ->map(fn ($c) => ['nombre' => $c->nombre, 'tipo' => $c->tipo])
        ->all());

    $hora = fn (?string $h) => $h ? substr($h, 0, 5) : '';
@endphp

{{-- PANTALLA 2 — EDITAR ACTIVIDAD de mi-cuenta.html --}}
<div class="rise" style="max-width:900px;margin:0 auto;padding:34px 32px 96px;"
     x-data="editorActividad({
        temas: {{ Js::from($seleccion('tema', 'temas')) }},
        caracteristicas: {{ Js::from($seleccion('caracteristica', 'caracteristicas')) }},
        publicos: {{ Js::from($seleccion('publico', 'publicos')) }},
        accesos: {{ Js::from($seleccion('acceso', 'accesos')) }},
        formato: {{ Js::from(old('formato', $activity->formato)) }},
        sinFecha: {{ Js::from((bool) old('sin_fecha_definida', $activity->sin_fecha_definida)) }},
        abierta: {{ Js::from((bool) old('abierta_publico', $activity->abierta_publico)) }},
        insc: {{ Js::from((bool) old('inscripcion_habilitada', $activity->inscripcion_habilitada)) }},
        colaboradores: {{ Js::from(array_values($colaboradores)) }},
        descLen: {{ mb_strlen(\App\Support\Formulario::viejo('descripcion', $activity->descripcion ?? '')) }},
        limites: { temas: {{ $limites['tema'] ?? 'null' }}, caracteristicas: {{ $limites['caracteristica'] ?? 'null' }}, publicos: null, accesos: null },
     })">

    <div class="crumb" style="margin-bottom:20px;">Mi cuenta → <a href="{{ route('account.activities.index') }}">Mis actividades</a> → Editar</div>
    <h1 style="font-size:38px;font-weight:800;letter-spacing:-.02em;margin:0 0 18px;color:var(--ink);">Editar actividad</h1>

    @if ($activity->estado === 'publicada')
        <div style="display:flex;align-items:flex-start;gap:13px;background:#eaf6f5;border:1.5px solid #cbe7e5;border-radius:18px;padding:16px 18px;margin-bottom:26px;">
            <span style="flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:999px;background:var(--turquesa);color:#fff;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
            </span>
            <div style="font-size:14.5px;line-height:1.6;color:#0d6b64;">Esta actividad está publicada. Los cambios que guardes se actualizarán inmediatamente en el sitio web.<br><span style="color:#3f8b85;">Si modificas la fecha, el lugar o la hora, te recomendamos informar también a las personas inscritas.</span></div>
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
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" style="margin-bottom:24px;">
            Revisa los campos marcados: hay {{ $errors->count() }} {{ \App\Support\Texto::plural('dato', $errors->count()) }} por corregir.
        </div>
    @endif

    <form method="POST" action="{{ route('account.activities.update', $activity) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card" style="overflow:hidden;">

            {{-- ── Sobre la actividad ── --}}
            <div style="padding:30px;border-bottom:1px solid var(--linea);">
                <div class="seclabel" style="margin-bottom:18px;">Sobre la actividad</div>

                <div style="display:flex;flex-direction:column;gap:18px;">
                    <label class="lbl">Nombre de la actividad *
                        <input class="fld @error('titulo') is-invalid @enderror" name="titulo"
                               value="@viejo('titulo', $activity->titulo)" required>
                        @error('titulo') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="lbl">Descripción * — máx. 1.000 caracteres
                        <textarea class="fld @error('descripcion') is-invalid @enderror" name="descripcion" rows="4"
                                  style="resize:vertical;" maxlength="1000"
                                  x-on:input="descLen = $event.target.value.length">{{ \App\Support\Formulario::viejo('descripcion', $activity->descripcion) }}</textarea>
                        <span style="display:flex;justify-content:space-between;gap:12px;">
                            <span class="helper">Máximo 1.000 caracteres.</span>
                            {{-- El style va entero en el binding: Alpine reemplaza
                                 el atributo, no lo fusiona. --}}
                            <span class="helper"
                                  x-bind:style="'font-variant-numeric:tabular-nums;color:' + (descLen > 900 ? 'var(--rosa)' : 'var(--gris)')"
                                  x-text="descLen + ' / 1.000'"></span>
                        </span>
                        @error('descripcion') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <div>
                        <div class="lbl" style="margin-bottom:9px;">Formato *</div>
                        <div style="display:flex;gap:8px;">
                            @foreach ($formatos as $f)
                                <button type="button"
                                        x-bind:class="formato === {{ Js::from($f) }} ? 'chip on' : 'chip'"
                                        x-on:click="formato = {{ Js::from($f) }}">{{ $f }}</button>
                            @endforeach
                        </div>
                        <input type="hidden" name="formato" x-bind:value="formato">
                        @error('formato') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- ── Fecha y lugar ── --}}
            <div style="padding:30px;border-bottom:1px solid var(--linea);">
                <div class="seclabel" style="margin-bottom:18px;">Fecha y lugar</div>

                {{--
                    Campos de texto, no input[type=date]: es lo que trae el
                    prototipo y además los navegadores no dejan pegar en los
                    campos nativos de fecha y hora.
                --}}
                <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <label class="lbl">Fecha de inicio *
                        <input class="fld @error('fecha_inicio') is-invalid @enderror" name="fecha_inicio"
                               placeholder="dd / mm / aaaa" inputmode="numeric"
                               x-bind:disabled="sinFecha"
                               value="@viejo('fecha_inicio', $activity->fecha_inicio?->format('d / m / Y'))">
                        @error('fecha_inicio') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="lbl">Fecha de término
                        <input class="fld @error('fecha_termino') is-invalid @enderror" name="fecha_termino"
                               placeholder="dd / mm / aaaa" inputmode="numeric"
                               x-bind:disabled="sinFecha"
                               value="@viejo('fecha_termino', $activity->fecha_termino?->format('d / m / Y'))">
                        @error('fecha_termino') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="lbl">Hora de inicio (opcional)
                        <input class="fld @error('hora_inicio') is-invalid @enderror" name="hora_inicio"
                               placeholder="HH:MM" x-bind:disabled="sinFecha"
                               value="@viejo('hora_inicio', $hora($activity->hora_inicio))">
                        @error('hora_inicio') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="lbl">Hora de término (opcional)
                        <input class="fld @error('hora_termino') is-invalid @enderror" name="hora_termino"
                               placeholder="HH:MM" x-bind:disabled="sinFecha"
                               value="@viejo('hora_termino', $hora($activity->hora_termino))">
                        @error('hora_termino') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div style="display:flex;align-items:center;gap:12px;margin:20px 0;">
                    <span style="flex:1;height:1px;background:var(--linea);"></span>
                    <span style="font-size:11.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#b7babe;">o bien</span>
                    <span style="flex:1;height:1px;background:var(--linea);"></span>
                </div>

                <label style="display:flex;align-items:flex-start;gap:11px;cursor:pointer;margin-bottom:20px;">
                    <input type="checkbox" name="sin_fecha_definida" value="1" x-model="sinFecha"
                           style="width:18px;height:18px;accent-color:var(--naranjo);margin-top:2px;">
                    <span style="font-size:14.5px;color:var(--ink);">Disponible de forma permanente
                        <span class="helper" style="display:block;margin-top:3px;">Al marcar esta opción, los campos de fecha y hora se deshabilitarán. Usa esta opción para actividades que no tienen una fecha específica.</span>
                    </span>
                </label>

                <label class="lbl">Dirección *
                    <input class="fld @error('direccion') is-invalid @enderror" name="direccion"
                           value="@viejo('direccion', $activity->direccion)">
                    @error('direccion') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                {{--
                    El prototipo dice aquí "Región y comuna se detectan
                    automáticamente" y no ofrece selector. Sin un servicio de
                    geocodificación no hay de dónde deducirlas, así que se pide
                    la comuna igual que en el paso 4 del wizard.
                --}}
                <label class="lbl" style="margin-top:16px;max-width:340px;">Comuna *
                    <select class="fld @error('commune_id') is-invalid @enderror" name="commune_id">
                        <option value="">Selecciona una comuna</option>
                        @foreach ($regiones as $region)
                            <optgroup label="{{ $region->nombre }}">
                                @foreach ($region->communes as $comuna)
                                    <option value="{{ $comuna->id }}" @selected((int) old('commune_id', $activity->commune_id) === $comuna->id)>{{ $comuna->nombre }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <span class="helper">La región se toma de la comuna.</span>
                    @error('commune_id') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>

            {{-- ── Temas y público ── --}}
            <div style="padding:30px;border-bottom:1px solid var(--linea);">
                <div class="seclabel" style="margin-bottom:18px;">Temas y público</div>

                @foreach ([
                    ['grupo' => 'temas', 'items' => $temas, 'label' => 'Temas de la actividad (hasta 3) *', 'ayuda' => null, 'margen' => '0 0 9px'],
                    ['grupo' => 'caracteristicas', 'items' => $caracteristicas, 'label' => '¿Qué características tiene tu actividad? *', 'ayuda' => 'Selecciona hasta 5 características.', 'margen' => '24px 0 9px'],
                    ['grupo' => 'publicos', 'items' => $publicos, 'label' => '¿Quién es el público beneficiado por esta actividad? *', 'ayuda' => 'Selecciona todas las que correspondan.', 'margen' => '24px 0 9px'],
                    ['grupo' => 'accesos', 'items' => $accesos, 'label' => '¿La actividad es accesible para personas con discapacidad?', 'ayuda' => 'Marca todas las características que correspondan.', 'margen' => '24px 0 9px'],
                ] as $bloque)
                    <div class="lbl" style="margin:{{ $bloque['margen'] }};">{{ $bloque['label'] }}</div>

                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        @foreach ($bloque['items'] as $t)
                            <button type="button"
                                    x-bind:class="marcado('{{ $bloque['grupo'] }}', {{ $t->id }}) ? 'chip on' : 'chip'"
                                    x-bind:aria-pressed="marcado('{{ $bloque['grupo'] }}', {{ $t->id }}) ? 'true' : 'false'"
                                    x-on:click="alternar('{{ $bloque['grupo'] }}', {{ $t->id }})">{{ $t->nombre }}</button>
                        @endforeach
                    </div>

                    <template x-for="id in sel['{{ $bloque['grupo'] }}']" x-bind:key="id">
                        <input type="hidden" name="{{ $bloque['grupo'] }}[]" x-bind:value="id">
                    </template>

                    @if ($bloque['ayuda'])
                        <div class="helper" style="margin-top:8px;">{{ $bloque['ayuda'] }}</div>
                    @endif

                    @error($bloque['grupo']) <span class="field-error">{{ $message }}</span> @enderror
                @endforeach
            </div>

            {{-- ── Público de la actividad ── --}}
            <div style="padding:30px;border-bottom:1px solid var(--linea);">
                <div class="seclabel" style="margin-bottom:18px;">Público de la actividad</div>

                <label class="lbl" style="max-width:260px;">Cantidad de participantes estimados
                    <input class="fld @error('participantes_estimados') is-invalid @enderror" name="participantes_estimados"
                           inputmode="numeric" value="@viejo('participantes_estimados', $activity->participantes_estimados)">
                    @error('participantes_estimados') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <div class="seclabel" style="margin:26px 0 14px;color:var(--gris-700);letter-spacing:.08em;">Registro de asistentes</div>

                <div class="lbl" style="margin-bottom:9px;">¿Esta actividad es abierta al público?</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" x-bind:class="abierta ? 'chip on' : 'chip'" x-on:click="abierta = true">Sí</button>
                    <button type="button" x-bind:class="abierta ? 'chip' : 'chip on'" x-on:click="abierta = false">No</button>
                </div>
                <input type="hidden" name="abierta_publico" x-bind:value="abierta ? 1 : 0">

                <div class="lbl" style="margin:20px 0 9px;">¿Las personas deben inscribirse para asistir?</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" x-bind:class="insc ? 'chip on' : 'chip'" x-on:click="insc = true">Sí</button>
                    <button type="button" x-bind:class="insc ? 'chip' : 'chip on'" x-on:click="insc = false">No</button>
                </div>
                <input type="hidden" name="inscripcion_habilitada" x-bind:value="insc ? 1 : 0">

                <div x-show="insc" x-cloak>
                    <label class="lbl" style="margin-top:16px;max-width:260px;">Cupos disponibles
                        <input class="fld @error('cupos_disponibles') is-invalid @enderror" name="cupos_disponibles"
                               inputmode="numeric" value="@viejo('cupos_disponibles', $activity->cupos_disponibles)">
                        <span class="helper">Los cupos disponibles son editables manualmente para reflejar inscripciones recibidas por fuera del sitio web.</span>
                        @error('cupos_disponibles') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="lbl" style="margin-top:20px;">¿Qué deben saber las personas antes de asistir? (opcional)
                    <textarea class="fld @error('info_previa') is-invalid @enderror" name="info_previa" rows="2"
                              style="resize:vertical;" placeholder="Campo no obligatorio">{{ \App\Support\Formulario::viejo('info_previa', $activity->info_previa) }}</textarea>
                    @error('info_previa') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>

            {{-- ── Información de contacto ── --}}
            <div style="padding:30px;border-bottom:1px solid var(--linea);">
                <div class="seclabel" style="margin-bottom:6px;">Información de contacto</div>
                <p style="font-size:14px;color:var(--gris);margin:0 0 18px;">Estos datos aparecerán visibles en la ficha pública de la actividad.</p>

                <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <label class="lbl">Correo de contacto público
                        <input class="fld @error('correo_contacto') is-invalid @enderror" type="email" name="correo_contacto"
                               value="@viejo('correo_contacto', $activity->correo_contacto)">
                        @error('correo_contacto') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="lbl">Enlace a red social
                        <input class="fld @error('enlace_red_social') is-invalid @enderror" type="url" name="enlace_red_social"
                               value="@viejo('enlace_red_social', $activity->enlace_red_social)">
                        <span class="helper">Instagram, Facebook u otro.</span>
                        @error('enlace_red_social') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="lbl">Enlace a página web (opcional)
                        <input class="fld @error('enlace_web') is-invalid @enderror" type="url" name="enlace_web"
                               placeholder="https://tusitio.cl" value="@viejo('enlace_web', $activity->enlace_web)">
                        <span class="helper">Si tu actividad tiene una página con más información, compártela aquí.</span>
                        @error('enlace_web') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
            </div>

            {{-- ── Imagen ── --}}
            <div style="padding:30px;border-bottom:1px solid var(--linea);" x-data="{ nombre: '' }">
                <div class="seclabel" style="margin-bottom:18px;">Imagen</div>
                <div class="lbl" style="margin-bottom:10px;">Imagen de la actividad</div>

                <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
                    <img loading="lazy" decoding="async" src="{{ $activity->imagen_url }}" alt="Imagen actual de la actividad" x-ref="vista"
                         style="width:170px;height:96px;object-fit:cover;border-radius:16px;border:1px solid var(--linea);">
                    <div>
                        <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                            Cambiar imagen
                            <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" style="display:none;"
                                   x-on:change="nombre = $event.target.files[0]?.name || '';
                                                if ($event.target.files[0]) $refs.vista.src = URL.createObjectURL($event.target.files[0])">
                        </label>
                        <div class="helper" style="margin-top:7px;" x-show="!nombre">PNG o JPG · máx. 2 MB · 1200×600 px recomendado.</div>
                        <div class="helper" style="margin-top:7px;" x-show="nombre" x-cloak x-text="nombre"></div>
                        @error('imagen') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- ── Colaboración ── --}}
            <div style="padding:30px;">
                <div class="seclabel" style="margin-bottom:18px;">Colaboración</div>
                <div class="lbl" style="margin-bottom:9px;">¿Esta iniciativa se realiza en colaboración con otra(s) organización(es) o institución(es)?</div>

                <div style="display:flex;gap:8px;">
                    <button type="button" x-bind:class="colab ? 'chip on' : 'chip'" x-on:click="activarColab()">Sí</button>
                    {{-- x-show sólo oculta: hay que vaciar las filas o se envían igual. --}}
                    <button type="button" x-bind:class="colab ? 'chip' : 'chip on'"
                            x-on:click="colab = false; colaboradores = []">No</button>
                </div>

                <div x-show="colab" x-cloak style="margin-top:18px;display:flex;flex-direction:column;gap:12px;">
                    <template x-for="(fila, i) in colaboradores" x-bind:key="i">
                        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;">
                            <label class="lbl">Nombre de la organización
                                <input class="fld" x-model="fila.nombre" x-bind:name="'colaboradores[' + i + '][nombre]'">
                            </label>
                            <label class="lbl">Tipo de organización
                                <select class="fld" x-model="fila.tipo" x-bind:name="'colaboradores[' + i + '][tipo]'">
                                    <option value="">Seleccione</option>
                                    @foreach ($tiposColaborador as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="button" class="icon-btn" style="width:38px;height:38px;"
                                    aria-label="Quitar colaborador" x-on:click="colaboradores.splice(i, 1)">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </template>

                    <div>
                        <button type="button" class="btn btn-outline btn-sm"
                                x-on:click="colaboradores.push({ nombre: '', tipo: '' })">+ Agregar otro colaborador</button>
                        <div class="helper" style="margin-top:8px;">Cada colaborador agrega una nueva fila con nombre y tipo de organización.</div>
                    </div>
                </div>
            </div>

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
    @if (in_array($activity->estado, ['borrador', 'ajustes'], true))
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

@push('scripts')
<script>
    function editorActividad(inicial) {
        return {
            sel: {
                temas: inicial.temas.map(Number),
                caracteristicas: inicial.caracteristicas.map(Number),
                publicos: inicial.publicos.map(Number),
                accesos: inicial.accesos.map(Number),
            },
            limites: inicial.limites,

            formato: inicial.formato,
            sinFecha: inicial.sinFecha,
            abierta: inicial.abierta,
            insc: inicial.insc,
            descLen: inicial.descLen,

            colaboradores: inicial.colaboradores,
            colab: inicial.colaboradores.length > 0,

            modalCancelar: false,

            marcado(grupo, id) {
                return this.sel[grupo].includes(id);
            },

            // Igual que en el wizard: al pasarse del tope, sale el más antiguo.
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

            activarColab() {
                this.colab = true;

                if (this.colaboradores.length === 0) {
                    this.colaboradores.push({ nombre: '', tipo: '' });
                }
            },
        };
    }
</script>
@endpush
