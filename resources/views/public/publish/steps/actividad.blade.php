{{-- PASO 4 — TU ACTIVIDAD de publicar-actividad.html (líneas 223-415). --}}
<h1 style="font-size:36px;font-weight:800;letter-spacing:-.02em;margin:0 0 24px;color:var(--ink);">Sobre tu actividad</h1>

<div style="background:#fff;border:1px solid var(--linea);border-radius:24px;box-shadow:0 18px 40px -32px rgba(0,0,0,.22);overflow:hidden;">

    {{-- ── Información básica ── --}}
    <div style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:18px;">Información básica</div>

        <div style="display:flex;flex-direction:column;gap:18px;">
            <label class="lbl">Nombre de la actividad *
                <input class="fld @error('titulo') is-invalid @enderror" name="titulo"
                       value="@viejo('titulo')" placeholder="Ej. Jornada comunitaria en el barrio">
                @error('titulo') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <div>
                <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:9px;">¿Qué características tiene tu actividad? *</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    @foreach ($caracteristicas as $c)
                        <button type="button"
                                x-bind:class="marcado('caracteristicas', {{ $c->id }}) ? 'chip on' : 'chip'"
                                x-bind:aria-pressed="marcado('caracteristicas', {{ $c->id }}) ? 'true' : 'false'"
                                x-on:click="alternar('caracteristicas', {{ $c->id }})">{{ $c->nombre }}</button>
                    @endforeach
                </div>
                <template x-for="id in sel.caracteristicas" x-bind:key="id">
                    <input type="hidden" name="caracteristicas[]" x-bind:value="id">
                </template>
                <div class="helper" style="margin-top:8px;">Selecciona todas las que correspondan.</div>
                @error('caracteristicas') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div>
                <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:9px;">Formato *</div>
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

            <label class="lbl">Descripción de la actividad *
                <textarea class="fld @error('descripcion') is-invalid @enderror" name="descripcion" rows="4"
                          style="resize:vertical;" maxlength="1000"
                          placeholder="Cuenta de qué se trata, qué harán las personas y por qué participar…"
                          x-on:input="descLen = $event.target.value.length">{{ \App\Support\Formulario::viejo('descripcion') }}</textarea>
                <span style="display:flex;justify-content:space-between;gap:12px;">
                    <span class="helper">Máximo 1.000 caracteres.</span>
                    <span class="helper"
                          x-bind:style="'font-variant-numeric:tabular-nums;color:' + (descLen > 900 ? 'var(--rosa)' : 'var(--gris)')"
                          x-text="descLen + ' / 1.000'"></span>
                </span>
                @error('descripcion') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>
    </div>

    {{-- ── Fecha y lugar ── --}}
    <div style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:18px;">Fecha y lugar</div>

        {{--
            Campos de texto, no input[type=date]: es lo que trae el prototipo
            y además los navegadores no dejan pegar en los campos nativos de
            fecha y hora.
        --}}
        <div class="grid-2" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;">
            <label class="lbl">Fecha *
                <input class="fld @error('fecha_inicio') is-invalid @enderror" name="fecha_inicio"
                       inputmode="numeric" placeholder="dd / mm / aaaa"
                       x-bind:disabled="sinFecha" value="@viejo('fecha_inicio')">
                @error('fecha_inicio') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Hora inicio
                <input class="fld @error('hora_inicio') is-invalid @enderror" name="hora_inicio"
                       placeholder="HH:MM" x-bind:disabled="sinFecha" value="@viejo('hora_inicio')">
                @error('hora_inicio') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Hora término
                <input class="fld @error('hora_termino') is-invalid @enderror" name="hora_termino"
                       placeholder="HH:MM" x-bind:disabled="sinFecha" value="@viejo('hora_termino')">
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
                <span class="helper" style="display:block;margin-top:3px;">Los campos de fecha y hora se deshabilitan. Úsalo para actividades sin fecha específica.</span>
            </span>
        </label>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl">Región *
                <select class="fld @error('region_id') is-invalid @enderror" name="region_id"
                        x-model="regionId" x-on:change="cambiarRegion()">
                    <option value="">Selecciona</option>
                    @foreach ($regiones as $region)
                        <option value="{{ $region->id }}">{{ $region->nombre }}</option>
                    @endforeach
                </select>
                @error('region_id') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Comuna *
                <select class="fld @error('commune_id') is-invalid @enderror" name="commune_id" x-model="communeId">
                    <option value="">Selecciona</option>
                    <template x-for="c in comunasDeRegion()" x-bind:key="c.id">
                        <option x-bind:value="c.id" x-text="c.nombre"></option>
                    </template>
                </select>
                @error('commune_id') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="lbl" style="margin-top:16px;">Dirección *
            <input class="fld @error('direccion') is-invalid @enderror" name="direccion"
                   value="@viejo('direccion')" placeholder="Calle, número, referencia">
            @error('direccion') <span class="field-error">{{ $message }}</span> @enderror
        </label>
    </div>

    {{-- ── Temas y público ── --}}
    <div style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:18px;">Temas y público</div>

        <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:9px;">Tema de la actividad *</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            @foreach ($temas as $t)
                <button type="button"
                        x-bind:class="marcado('temas', {{ $t->id }}) ? 'chip on' : 'chip'"
                        x-bind:aria-pressed="marcado('temas', {{ $t->id }}) ? 'true' : 'false'"
                        x-on:click="alternar('temas', {{ $t->id }})">{{ $t->nombre }}</button>
            @endforeach
        </div>
        <template x-for="id in sel.temas" x-bind:key="id">
            <input type="hidden" name="temas[]" x-bind:value="id">
        </template>
        <div class="helper" style="margin-top:8px;">Selecciona hasta tres temas principales.</div>
        @error('temas') <span class="field-error">{{ $message }}</span> @enderror

        <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin:24px 0 9px;">¿Quién es el público beneficiado por esta actividad? *</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            @foreach ($publicos as $p)
                <button type="button"
                        x-bind:class="marcado('publicos', {{ $p->id }}) ? 'chip on' : 'chip'"
                        x-bind:aria-pressed="marcado('publicos', {{ $p->id }}) ? 'true' : 'false'"
                        x-on:click="alternar('publicos', {{ $p->id }})">{{ $p->nombre }}</button>
            @endforeach
        </div>
        <template x-for="id in sel.publicos" x-bind:key="id">
            <input type="hidden" name="publicos[]" x-bind:value="id">
        </template>
        <div class="helper" style="margin-top:8px;">Selecciona todas las que correspondan.</div>
        @error('publicos') <span class="field-error">{{ $message }}</span> @enderror

        <label class="lbl" style="margin-top:16px;max-width:440px;" x-show="publicoOtros()" x-cloak>¿Cuál? *
            <input class="fld @error('publico_otro') is-invalid @enderror" name="publico_otro"
                   value="@viejo('publico_otro')" placeholder="Especifica el público beneficiado">
            @error('publico_otro') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin:24px 0 9px;">¿Tu actividad cuenta con alguna adecuación de accesibilidad?</div>
        <div style="display:flex;gap:8px;">
            <button type="button" x-bind:class="acc ? 'chip on' : 'chip'" x-on:click="acc = true">Sí</button>
            <button type="button" x-bind:class="acc ? 'chip' : 'chip on'" x-on:click="acc = false">No</button>
        </div>
        <input type="hidden" name="tiene_accesibilidad" x-bind:value="acc ? 1 : 0">

        <label class="lbl" style="margin-top:16px;" x-show="acc" x-cloak>Cuéntanos brevemente cuáles (opcional)
            <textarea class="fld" name="accesibilidad_detalle" rows="2" style="resize:vertical;"
                      placeholder="Ej. acceso en silla de ruedas, intérprete de lengua de señas, material accesible…">{{ \App\Support\Formulario::viejo('accesibilidad_detalle') }}</textarea>
        </label>
    </div>

    {{-- ── Público de la actividad ── --}}
    <div style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:18px;">Público de la actividad</div>

        <label class="lbl" style="max-width:260px;">Cantidad de participantes estimados
            <input class="fld @error('participantes_estimados') is-invalid @enderror" name="participantes_estimados"
                   inputmode="numeric" value="@viejo('participantes_estimados')" placeholder="Ej. 80">
            @error('participantes_estimados') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin:20px 0 9px;">¿Requiere inscripción previa?</div>
        <div style="display:flex;gap:8px;">
            <button type="button" x-bind:class="insc ? 'chip on' : 'chip'" x-on:click="insc = true">Sí</button>
            <button type="button" x-bind:class="insc ? 'chip' : 'chip on'" x-on:click="insc = false">No</button>
        </div>
        <input type="hidden" name="inscripcion_habilitada" x-bind:value="insc ? 1 : 0">

        <label class="lbl" style="margin-top:16px;max-width:260px;" x-show="insc" x-cloak>Cupos disponibles
            <input class="fld @error('cupos_totales') is-invalid @enderror" name="cupos_totales"
                   inputmode="numeric" value="@viejo('cupos_totales', 80)">
            <span class="helper">Las personas podrán reservar su cupo desde el sitio web.</span>
            @error('cupos_totales') <span class="field-error">{{ $message }}</span> @enderror
        </label>
    </div>

    {{-- ── Imagen de portada ── --}}
    <div style="padding:30px;border-bottom:1px solid var(--linea);" x-data="{ portada: '' }">
        <div class="seclabel" style="margin-bottom:18px;">Imagen de portada</div>

        <div style="display:flex;align-items:center;gap:18px;">
            <span style="display:grid;place-items:center;width:150px;height:78px;border-radius:16px;border:1.5px dashed #dcdee1;background:#fbfbfc;color:#c3c6ca;flex:none;overflow:hidden;">
                <img x-ref="vista" x-show="portada" x-cloak alt="" style="width:100%;height:100%;object-fit:cover;">
                <svg x-show="!portada" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path></svg>
            </span>
            <div>
                <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                    Subir imagen de portada
                    <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" style="display:none;"
                           x-on:change="portada = $event.target.files[0]?.name || '';
                                        if ($event.target.files[0]) $refs.vista.src = URL.createObjectURL($event.target.files[0])">
                </label>
                <div class="helper" style="margin-top:7px;">PNG o JPG · máx. 2 MB · 1200×600 px recomendado.</div>
                @error('imagen') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- ── Información de contacto ── --}}
    <div style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:6px;">Información de contacto</div>
        <p style="font-size:14px;color:var(--gris);margin:0 0 18px;">Estos datos aparecerán visibles en la ficha pública de la actividad.</p>

        <label style="display:flex;align-items:center;gap:11px;cursor:pointer;margin-bottom:18px;font-size:14.5px;color:var(--ink);">
            <input type="checkbox" name="usar_correo_cuenta" value="1" x-model="mismoCorreo"
                   style="width:18px;height:18px;accent-color:var(--naranjo);">
            Usar el mismo correo de la cuenta como correo de contacto público
        </label>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl">Correo de contacto público
                <input class="fld @error('correo_contacto') is-invalid @enderror" type="email" name="correo_contacto"
                       value="@viejo('correo_contacto')" placeholder="contacto@organizacion.cl">
                <span class="helper">Para que las personas puedan escribirte con preguntas sobre la actividad.</span>
                @error('correo_contacto') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Enlace a red social
                <input class="fld @error('enlace_red_social') is-invalid @enderror" type="url" name="enlace_red_social"
                       value="@viejo('enlace_red_social')" placeholder="https://instagram.com/...">
                <span class="helper">Solo un enlace: Instagram, Facebook, LinkedIn o el que prefieras.</span>
                @error('enlace_red_social') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl">Enlace a página web (opcional)
                <input class="fld @error('enlace_web') is-invalid @enderror" type="url" name="enlace_web"
                       value="@viejo('enlace_web')" placeholder="https://tusitio.cl">
                <span class="helper">Si tu actividad tiene una página con más información, compártela aquí.</span>
                @error('enlace_web') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>
    </div>

    {{-- ── Colaboración ── --}}
    <div style="padding:30px;">
        <div class="seclabel" style="margin-bottom:18px;">Colaboración</div>
        <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:9px;">¿Esta iniciativa se realiza en colaboración con otras organizaciones o instituciones?</div>

        <div style="display:flex;gap:8px;">
            <button type="button" x-bind:class="colab ? 'chip on' : 'chip'" x-on:click="colab = true">Sí</button>
            <button type="button" x-bind:class="colab ? 'chip' : 'chip on'" x-on:click="colab = false; colabs = []">No</button>
        </div>

        <div x-show="colab" x-cloak style="margin-top:18px;">
            <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:9px;">Organizaciones colaboradoras</div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;border:1.5px solid #e4e6e8;border-radius:14px;background:#fff;padding:10px 12px;">
                <template x-for="(nombre, i) in colabs" x-bind:key="i">
                    <span style="display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;padding:6px 12px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo-600);">
                        <span x-text="nombre"></span>
                        <button type="button" x-on:click="colabs.splice(i, 1)" aria-label="Quitar"
                                style="border:0;background:none;padding:0;cursor:pointer;color:inherit;display:inline-flex;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                        </button>
                        <input type="hidden" name="colaboradores[]" x-bind:value="nombre">
                    </span>
                </template>
                <input class="fld" style="flex:1;min-width:200px;border:none;padding:4px 2px;box-shadow:none;"
                       placeholder="Escribe un nombre y presiona Enter…"
                       x-on:keydown.enter.prevent="agregarColaborador($event)">
            </div>
            <div class="helper" style="margin-top:8px;">Cada organización se agrega como etiqueta.</div>
        </div>
    </div>

    <div style="padding:20px 30px;border-top:1px solid var(--linea);background:#fdfcfb;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <span class="helper">* campos obligatorios</span>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-outline" disabled title="Pendiente de definir">Guardar borrador</button>
            <button type="submit" class="btn btn-primary">Enviar actividad →</button>
        </div>
    </div>
</div>
