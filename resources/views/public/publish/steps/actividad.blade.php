<div class="seclabel" style="margin-bottom:10px;">Paso 4</div>
<h2 style="font-weight:800;font-size:28px;margin:0 0 24px;">Cuéntanos de tu actividad</h2>

<div style="display:flex;flex-direction:column;gap:26px;">

    {{-- ── Información básica ── --}}
    <div>
        <div class="seclabel" style="margin-bottom:14px;">Información básica</div>

        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="helper" for="titulo" style="display:block;margin-bottom:6px;font-weight:600;">Nombre de la actividad *</label>
                <input class="fld @error('titulo') is-invalid @enderror" type="text" id="titulo" name="titulo"
                       value="{{ old('titulo') }}" placeholder="Ej. Jornada comunitaria en el barrio" required>
                @error('titulo') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="helper" for="descripcion" style="display:block;margin-bottom:6px;font-weight:600;">Descripción de la actividad *</label>
                <textarea class="fld @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" rows="5"
                          maxlength="1000" x-on:input="descLen = $event.target.value.length"
                          placeholder="Cuenta de qué se trata, qué harán las personas y por qué participar…" required>{{ old('descripcion') }}</textarea>
                <div style="display:flex;justify-content:space-between;gap:12px;margin-top:5px;">
                    <span class="helper">Máximo 1.000 caracteres.</span>
                    <span class="helper" style="font-variant-numeric:tabular-nums;"
                          x-bind:style="descLen > 900 ? 'color:var(--rosa)' : ''"
                          x-text="descLen + ' / 1.000'">0 / 1.000</span>
                </div>
                @error('descripcion') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div>
                <span class="helper" style="display:block;margin-bottom:8px;font-weight:600;">Formato *</span>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    @foreach ($formatos as $f)
                        <label class="chip {{ old('formato', $formatos[0]) === $f ? 'on' : '' }}"
                               x-data x-bind:class="$refs['fmt{{ $loop->index }}'].checked ? 'chip on' : 'chip'">
                            <input type="radio" name="formato" value="{{ $f }}" x-ref="fmt{{ $loop->index }}"
                                   @checked(old('formato', $formatos[0]) === $f) style="position:absolute;opacity:0;width:0;height:0;">
                            {{ $f }}
                        </label>
                    @endforeach
                </div>
                @error('formato') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- ── Fecha y lugar ── --}}
    <div x-data="{ sinFecha: {{ old('sin_fecha_definida') ? 'true' : 'false' }} }">
        <div class="seclabel" style="margin-bottom:14px;">Fecha y lugar</div>

        <label style="display:flex;align-items:center;gap:9px;font-size:14px;color:var(--gris-700);cursor:pointer;margin-bottom:14px;">
            <input type="checkbox" name="sin_fecha_definida" value="1" x-model="sinFecha">
            Todavía no tengo fecha definida
        </label>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;">
            <div>
                <label class="helper" for="fecha_inicio" style="display:block;margin-bottom:6px;font-weight:600;">Fecha *</label>
                <input class="fld @error('fecha_inicio') is-invalid @enderror" type="date" id="fecha_inicio" name="fecha_inicio"
                       value="{{ old('fecha_inicio') }}" x-bind:disabled="sinFecha">
                @error('fecha_inicio') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="helper" for="hora_inicio" style="display:block;margin-bottom:6px;font-weight:600;">Hora inicio</label>
                <input class="fld" type="time" id="hora_inicio" name="hora_inicio" value="{{ old('hora_inicio') }}" x-bind:disabled="sinFecha">
            </div>
            <div>
                <label class="helper" for="hora_termino" style="display:block;margin-bottom:6px;font-weight:600;">Hora término</label>
                <input class="fld @error('hora_termino') is-invalid @enderror" type="time" id="hora_termino" name="hora_termino"
                       value="{{ old('hora_termino') }}" x-bind:disabled="sinFecha">
                @error('hora_termino') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-top:14px;"
             x-data="{ region: '{{ old('region_tmp') }}' }">
            <div>
                <label class="helper" for="commune_id" style="display:block;margin-bottom:6px;font-weight:600;">Comuna *</label>
                <select class="fld @error('commune_id') is-invalid @enderror" id="commune_id" name="commune_id">
                    <option value="">Elige una comuna</option>
                    @foreach ($regiones as $r)
                        <optgroup label="{{ $r->nombre }}">
                            @foreach ($r->communes as $c)
                                <option value="{{ $c->id }}" @selected(old('commune_id') == $c->id)>{{ $c->nombre }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('commune_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="helper" for="direccion" style="display:block;margin-bottom:6px;font-weight:600;">Dirección</label>
                <input class="fld" type="text" id="direccion" name="direccion" value="{{ old('direccion') }}"
                       placeholder="Calle, número, referencia">
            </div>
        </div>
    </div>

    {{-- ── Taxonomías ── --}}
    @foreach ([
        ['grupo' => 'temas', 'items' => $temas, 'label' => 'Temas *', 'ayuda' => 'Selecciona hasta tres temas principales.', 'campo' => 'temas'],
        ['grupo' => 'caracteristicas', 'items' => $caracteristicas, 'label' => 'Características', 'ayuda' => 'Selecciona hasta cinco.', 'campo' => 'caracteristicas'],
        ['grupo' => 'publicos', 'items' => $publicos, 'label' => 'Público de la actividad *', 'ayuda' => 'Selecciona todas las que correspondan.', 'campo' => 'publicos'],
        ['grupo' => 'accesos', 'items' => $accesos, 'label' => 'Accesibilidad', 'ayuda' => 'Indica las medidas con las que cuenta la actividad.', 'campo' => 'accesos'],
    ] as $bloque)
        <div>
            <div class="seclabel" style="margin-bottom:8px;">{{ $bloque['label'] }}</div>
            <p class="helper" style="margin:0 0 10px;">{{ $bloque['ayuda'] }}</p>

            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach ($bloque['items'] as $t)
                    <button type="button"
                            x-bind:class="marcado('{{ $bloque['grupo'] }}', {{ $t->id }}) ? 'chip on' : 'chip'"
                            x-on:click="alternar('{{ $bloque['grupo'] }}', {{ $t->id }})"
                            x-bind:aria-pressed="marcado('{{ $bloque['grupo'] }}', {{ $t->id }}) ? 'true' : 'false'">{{ $t->nombre }}</button>
                @endforeach
            </div>

            <template x-for="id in sel['{{ $bloque['grupo'] }}']" x-bind:key="id">
                <input type="hidden" name="{{ $bloque['campo'] }}[]" x-bind:value="id">
            </template>

            @error($bloque['campo']) <span class="field-error">{{ $message }}</span> @enderror
        </div>
    @endforeach

    {{-- ── Participantes ── --}}
    <div>
        <div class="seclabel" style="margin-bottom:14px;">Participantes</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
            <div>
                <label class="helper" for="participantes_estimados" style="display:block;margin-bottom:6px;font-weight:600;">Cantidad de participantes estimados</label>
                <input class="fld" type="number" min="0" id="participantes_estimados" name="participantes_estimados"
                       value="{{ old('participantes_estimados') }}" placeholder="Ej. 80">
            </div>
            <div>
                <label class="helper" for="cupos_totales" style="display:block;margin-bottom:6px;font-weight:600;">Cupos disponibles</label>
                <input class="fld" type="number" min="0" id="cupos_totales" name="cupos_totales" value="{{ old('cupos_totales') }}">
                <span class="helper">Las personas podrán reservar su cupo desde el sitio web.</span>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;margin-top:16px;">
            <label style="display:flex;align-items:center;gap:9px;font-size:14px;color:var(--gris-700);cursor:pointer;">
                <input type="checkbox" name="abierta_publico" value="1" @checked(old('abierta_publico', true))>
                La actividad es abierta a todo público
            </label>
            <label style="display:flex;align-items:center;gap:9px;font-size:14px;color:var(--gris-700);cursor:pointer;">
                <input type="checkbox" name="inscripcion_habilitada" value="1" @checked(old('inscripcion_habilitada', true))>
                Quiero recibir inscripciones desde el sitio
            </label>
            <label style="display:flex;align-items:center;gap:9px;font-size:14px;color:var(--gris-700);cursor:pointer;">
                <input type="checkbox" name="tiene_accesibilidad" value="1" @checked(old('tiene_accesibilidad'))>
                La actividad cuenta con medidas de accesibilidad
            </label>
        </div>
    </div>

    {{-- ── Contacto ── --}}
    <div>
        <div class="seclabel" style="margin-bottom:14px;">Información de contacto</div>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="helper" for="correo_contacto" style="display:block;margin-bottom:6px;font-weight:600;">Correo de contacto público</label>
                <input class="fld @error('correo_contacto') is-invalid @enderror" type="email" id="correo_contacto"
                       name="correo_contacto" value="{{ old('correo_contacto') }}" placeholder="contacto@organizacion.cl">
                <span class="helper">Para que las personas puedan escribirte con preguntas sobre la actividad.</span>
                @error('correo_contacto') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="helper" for="enlace_red_social" style="display:block;margin-bottom:6px;font-weight:600;">Enlace a red social</label>
                <input class="fld @error('enlace_red_social') is-invalid @enderror" type="url" id="enlace_red_social"
                       name="enlace_red_social" value="{{ old('enlace_red_social') }}" placeholder="https://instagram.com/...">
                <span class="helper">Solo un enlace: Instagram, Facebook, LinkedIn o el que prefieras.</span>
                @error('enlace_red_social') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="helper" for="enlace_web" style="display:block;margin-bottom:6px;font-weight:600;">Enlace a página web (opcional)</label>
                <input class="fld @error('enlace_web') is-invalid @enderror" type="url" id="enlace_web"
                       name="enlace_web" value="{{ old('enlace_web') }}" placeholder="https://tusitio.cl">
                @error('enlace_web') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- ── Colaboración ── --}}
    <div>
        <div class="seclabel" style="margin-bottom:8px;">Colaboración</div>
        <p class="helper" style="margin:0 0 10px;">Cada organización se agrega como etiqueta.</p>

        <input class="fld" type="text" placeholder="Escribe un nombre y presiona Enter…"
               x-on:keydown.enter.prevent="agregarColaborador($event)">

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
            <template x-for="(nombre, i) in colaboradores" x-bind:key="i">
                <span class="chip on" style="cursor:default;display:inline-flex;align-items:center;gap:8px;">
                    <span x-text="nombre"></span>
                    <button type="button" x-on:click="quitarColaborador(i)" aria-label="Quitar"
                            style="border:0;background:none;cursor:pointer;color:inherit;font-weight:700;line-height:1;">×</button>
                    <input type="hidden" name="colaboradores[]" x-bind:value="nombre">
                </span>
            </template>
        </div>
    </div>
</div>
