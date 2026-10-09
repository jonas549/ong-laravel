{{--
    Los campos de una actividad: el paso 4 del wizard y el editor de «Mi
    cuenta» (tanda del 09/10, punto 9).

    **Un solo formulario para los dos.** El editor tenía su propia copia,
    sacada del prototipo viejo de mi-cuenta.html, y cada tanda dejaba alguna
    diferencia suelta: la accesibilidad como etiquetas, la comuna sin región,
    colaboradores con tipo, rótulos distintos. Y una que perdía datos: guardar
    en el editor apagaba la accesibilidad contestada en el wizard. Ahora un
    campo nuevo se escribe aquí una vez y sale en los dos.

    Recibe:
    - `$actividad`: la que se edita, o null en el wizard. Da los valores por
      defecto (lo de `old()` manda siempre, tras un rebote) y enciende las
      tres cosas que sólo tiene el editor: los cupos que quedan, la imagen
      actual y «antes de asistir» si ya estaba escrito.
    - `$organizacion`: la de la cuenta, para los dos enlaces de la ficha.
    - Los catálogos de `ActivityCatalogService::todos()`.

    Necesita en el componente de Alpine que lo rodea el mismo estado que el
    wizard: `sel`, `formato`, `sinFecha`, `varios`, `fechaBloqueada`, `acc`,
    `insc`, `cerrada`, `colab`, `colabs`, `regionId`, `communeId`,
    `mismoCorreo`, `correoContacto`, `descLen` y los métodos `alternar`,
    `marcado`, `publicoOtros`, `comunasDeRegion`, `cambiarRegion`,
    `agregarColaborador`, `esEmpresa`, `fichaAjena` y los de la dirección.
    `editorActividad` (resources/js/editor-actividad.js) los da igual que
    `wizard`.
--}}
@php
    use App\Support\CamposDeActividad;

    $actividad = $actividad ?? null;
    $organizacion = $organizacion ?? null;
    $hora = fn (?string $h) => $h ? substr($h, 0, 5) : null;
@endphp

    {{-- ── Información básica ── --}}
    <div class="wizard-seccion" style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:18px;">Información básica</div>

        <div style="display:flex;flex-direction:column;gap:18px;">
            <label class="lbl" data-campo="titulo" data-obligatorio
                   data-etiqueta="{{ CamposDeActividad::etiqueta('titulo') }}">Nombre de la actividad *
                <input class="fld @error('titulo') is-invalid @enderror" name="titulo"
                       value="@viejo('titulo', $actividad?->titulo)" placeholder="Ej. Jornada comunitaria en el barrio"
                       data-placeholder-movil="Ej. Jornada comunitaria">
                @error('titulo') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <div data-campo="caracteristicas" data-obligatorio
                 data-etiqueta="{{ CamposDeActividad::etiqueta('caracteristicas') }}">
                <div style="font-size:14.5px;font-weight:700;color:var(--ink);margin-bottom:9px;">¿Qué características tiene tu actividad? *<x-marca-obligatoria grupo="caracteristicas" /></div>
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
                {{--
                    El número sale de `limiteDe` y no escrito a mano: es el mismo
                    sitio del que sale el `max:` de los dos Form Requests, así que
                    el texto no puede quedarse diciendo un tope que ya no es.
                --}}
                <div class="helper" style="margin-top:8px;">Selecciona hasta {{ \App\Models\TaxonomyTerm::limiteDe('caracteristica') }} opciones que correspondan.</div>
                @error('caracteristicas') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div>
                <div style="font-size:14.5px;font-weight:700;color:var(--ink);margin-bottom:9px;">Formato *</div>
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

            <label class="lbl" data-campo="descripcion" data-obligatorio
                   data-etiqueta="{{ CamposDeActividad::etiqueta('descripcion') }}">Descripción de la actividad *
                <textarea class="fld @error('descripcion') is-invalid @enderror" name="descripcion" rows="4"
                          style="resize:vertical;" maxlength="1000"
                          placeholder="Cuenta de qué se trata, qué harán las personas y por qué participar…"
                          x-on:input="descLen = $event.target.value.length">{{ \App\Support\Formulario::viejo('descripcion', $actividad?->descripcion) }}</textarea>
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
    <div class="wizard-seccion" style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:18px;">Fecha y lugar</div>

        {{--
            Campos de texto, no input[type=date]: es lo que trae el prototipo
            y además los navegadores no dejan pegar en los campos nativos de
            fecha y hora.

            Pero texto libre y sin decir nada más dejaba al usuario adivinando
            entre dd/mm/aaaa y mm/dd/aaaa, que es lo segundo que llegó
            reportado. Así que el texto se queda —el pegado sigue funcionando—
            y se le añaden las tres cosas que le faltaban: el formato escrito
            en el hueco y debajo, una máscara que pone las barras sola mientras
            se teclea, y un calendario de verdad que ESCRIBE en el campo de
            texto en lugar de sustituirlo. Ver `campoFecha` en
            resources/js/formularios.js.
        --}}
        {{-- Fechas: el mismo bloque que el editor de mi-cuenta (tanda del 05/10). --}}
        <x-fechas-actividad etiqueta="Fecha *"
            :inicio="\App\Support\Formulario::viejo('fecha_inicio', $actividad?->fecha_inicio?->format('d / m / Y'))"
            :termino="\App\Support\Formulario::viejo('fecha_termino', $actividad?->fecha_termino?->format('d / m / Y'))">
            <label class="lbl" data-campo="hora_inicio"
                   data-etiqueta="{{ CamposDeActividad::etiqueta('hora_inicio') }}">Hora inicio
                <x-selector-hora name="hora_inicio" :valor="\App\Support\Formulario::viejo('hora_inicio', $hora($actividad?->hora_inicio))" desactivar="sinFecha"
                    :class="$errors->has('hora_inicio') ? 'is-invalid' : ''" />
                @error('hora_inicio') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl" data-campo="hora_termino"
                   data-etiqueta="{{ CamposDeActividad::etiqueta('hora_termino') }}">Hora término
                <x-selector-hora name="hora_termino" :valor="\App\Support\Formulario::viejo('hora_termino', $hora($actividad?->hora_termino))" desactivar="sinFecha"
                    :class="$errors->has('hora_termino') ? 'is-invalid' : ''" />
                @error('hora_termino') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </x-fechas-actividad>

        <div style="display:flex;align-items:center;gap:12px;margin:20px 0;">
            <span style="flex:1;height:1px;background:var(--linea);"></span>
            <span style="font-size:11.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#b7babe;">o bien</span>
            <span style="flex:1;height:1px;background:var(--linea);"></span>
        </div>

        <label style="display:flex;align-items:flex-start;gap:11px;cursor:pointer;margin-bottom:20px;">
            {{-- Al marcarla dejan de hacer falta la fecha, la región, la comuna
                 y la dirección: hay que repasar el resumen, o se quedaría
                 pidiendo campos que acaban de dejar de pedirse. --}}
            {{-- En el editor, una actividad que ya pasó no cambia sus fechas
                 (`fechaBloqueada`, punto 4 del 05/10); en el wizard es false. --}}
            <input type="checkbox" name="sin_fecha_definida" value="1" x-model="sinFecha"
                   x-bind:disabled="fechaBloqueada"
                   x-on:change="$nextTick(() => repasar())"
                   style="width:18px;height:18px;accent-color:var(--naranjo);margin-top:2px;flex:none;">
            <span style="font-size:14.5px;color:var(--ink);">Disponible de forma permanente
                <span class="helper" style="display:block;margin-top:3px;">Los campos de fecha y hora se deshabilitan. Úsalo para actividades sin fecha específica.</span>
            </span>
        </label>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl" data-campo="region_id" data-obligatorio
                   data-obligatorio-salvo="sin_fecha_definida"
                   data-etiqueta="{{ CamposDeActividad::etiqueta('region_id') }}">Región *
                <select class="fld @error('region_id') is-invalid @enderror" name="region_id"
                        x-model="regionId" x-on:change="cambiarRegion()">
                    <option value="">Selecciona</option>
                    @foreach ($regiones as $region)
                        <option value="{{ $region->id }}">{{ $region->nombre }}</option>
                    @endforeach
                </select>
                @error('region_id') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl" data-campo="commune_id" data-obligatorio
                   data-obligatorio-salvo="sin_fecha_definida"
                   data-etiqueta="{{ CamposDeActividad::etiqueta('commune_id') }}">Comuna *
                <select class="fld @error('commune_id') is-invalid @enderror" name="commune_id" x-model="communeId">
                    <option value="">Selecciona</option>
                    <template x-for="c in comunasDeRegion()" x-bind:key="c.id">
                        <option x-bind:value="c.id" x-text="c.nombre"></option>
                    </template>
                </select>
                @error('commune_id') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        {{--
            ── La dirección, con sugerencias (P16) ──

            Sigue siendo texto libre y sigue mandando lo que se escriba: la
            sugerencia ayuda a acertar y, sobre todo, guarda el PUNTO. Con
            latitud y longitud, el enlace del mapa de la ficha lleva al sitio
            exacto en vez de a lo que el buscador adivine de una cadena como
            «Metro Salvador, salida norte».

            `data-obligatorio-salvo` es el `required_without` de la regla: una
            actividad disponible de forma permanente puede no tener sitio fijo.
        --}}
        <label class="lbl" style="margin-top:16px;position:relative;" data-campo="direccion" data-obligatorio
               data-obligatorio-salvo="sin_fecha_definida"
               data-obligatorio-salvo-valor="formato:Online"
               data-etiqueta="{{ CamposDeActividad::etiqueta('direccion') }}">Dirección *
            <input class="fld @error('direccion') is-invalid @enderror" name="direccion"
                   value="@viejo('direccion', $actividad?->direccion)" placeholder="Calle, número, referencia"
                   autocomplete="off" role="combobox" aria-autocomplete="list"
                   x-bind:aria-expanded="dirAbiertas"
                   x-on:input="escribirDireccion($event.target.value)"
                   x-on:focus="if (sugerenciasDir.length) dirAbiertas = true"
                   x-on:blur="setTimeout(() => dirAbiertas = false, 160)"
                   x-on:keydown.escape.prevent="dirAbiertas = false">

            {{-- El punto viaja aparte, en dos campos ocultos. --}}
            <input type="hidden" name="latitud" x-bind:value="latitud">
            <input type="hidden" name="longitud" x-bind:value="longitud">

            <span class="helper" x-show="buscandoDir" x-cloak>Buscando direcciones…</span>

            <span class="helper" x-show="avisoDir && ! buscandoDir" x-cloak x-text="avisoDir" role="status"

                  style="color:var(--naranjo-600);"></span>

            <span class="helper" x-show="tienePunto" x-cloak style="color:var(--naranjo-600);">
                Ubicación exacta guardada: el enlace del mapa llevará justo aquí.
            </span>

            <ul class="org-sugerencias" x-show="dirAbiertas" x-cloak role="listbox">
                <template x-for="d in sugerenciasDir" x-bind:key="d.etiqueta">
                    <li>
                        <button type="button" class="org-sugerencia org-sugerencia-dir" x-on:click="elegirDireccion(d)">
                            <span class="org-sugerencia-nombre" x-text="d.direccion"></span>
                            <span class="org-sugerencia-estado" x-text="d.ciudad"></span>
                        </button>
                    </li>
                </template>
            </ul>

            <span class="helper">
                Escribe y elige una sugerencia para fijar el punto en el mapa. Si tu dirección no sale, escríbela igual.
            </span>

            @error('direccion') <span class="field-error">{{ $message }}</span> @enderror
        </label>

    </div>

    {{-- ── Temas y público ── --}}
    <div class="wizard-seccion" style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:18px;">Temas y público</div>

        <div data-campo="temas" data-obligatorio data-etiqueta="{{ CamposDeActividad::etiqueta('temas') }}">
        <div style="font-size:14.5px;font-weight:700;color:var(--ink);margin-bottom:9px;">Tema de la actividad *<x-marca-obligatoria grupo="temas" /></div>
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
        </div>

        {{-- ESTE es el campo del reporte: obligatorio, pero dibujado como un
             grupo de chips, que no parece algo que haya que rellenar sino un
             filtro que se puede mirar y dejar. El asterisco solo no bastó. --}}
        <div data-campo="publicos" data-obligatorio data-etiqueta="{{ CamposDeActividad::etiqueta('publicos') }}" style="margin-top:24px;">
        <div style="font-size:14.5px;font-weight:700;color:var(--ink);margin:0 0 9px;">¿Quién es el público beneficiado por esta actividad? *<x-marca-obligatoria grupo="publicos" /></div>
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
        </div>

        <label class="lbl" style="margin-top:16px;max-width:440px;" x-show="publicoOtros()" x-cloak
               data-campo="publico_otro" data-obligatorio
               data-etiqueta="{{ CamposDeActividad::etiqueta('publico_otro') }}">¿Cuál? *
            <input class="fld @error('publico_otro') is-invalid @enderror" name="publico_otro"
                   value="@viejo('publico_otro', $actividad?->publico_otro)" placeholder="Especifica el público beneficiado">
            @error('publico_otro') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div style="font-size:14.5px;font-weight:700;color:var(--ink);margin:24px 0 9px;">¿Tu actividad cuenta con alguna adecuación de accesibilidad?</div>
        <div style="display:flex;gap:8px;">
            <button type="button" x-bind:class="acc ? 'chip on' : 'chip'" x-on:click="acc = true">Sí</button>
            <button type="button" x-bind:class="acc ? 'chip' : 'chip on'" x-on:click="acc = false">No</button>
        </div>
        <input type="hidden" name="tiene_accesibilidad" x-bind:value="acc ? 1 : 0">

        <label class="lbl" style="margin-top:16px;" x-show="acc" x-cloak>Cuéntanos brevemente cuáles (opcional)
            <textarea class="fld" name="accesibilidad_detalle" rows="2" style="resize:vertical;"
                      placeholder="Ej. acceso en silla de ruedas, intérprete de lengua de señas, material accesible…">{{ \App\Support\Formulario::viejo('accesibilidad_detalle', $actividad?->accesibilidad_detalle) }}</textarea>
        </label>
    </div>

    {{-- ── Público de la actividad ── --}}
    <div class="wizard-seccion" style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:18px;">Público de la actividad</div>

        <label class="lbl" style="max-width:260px;">Cantidad de participantes estimados
            <input class="fld @error('participantes_estimados') is-invalid @enderror" name="participantes_estimados"
                   inputmode="numeric" value="@viejo('participantes_estimados', $actividad?->participantes_estimados)" placeholder="Ej. 80">
            @error('participantes_estimados') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        {{--
            P15. Venía del paso de la organización, pegada al nombre de la
            empresa. Ahí se cuenta quién eres; lo que esta pregunta pide es
            cuánta gente pone la empresa EN LA ACTIVIDAD, así que su sitio es
            éste, junto a los participantes estimados.

            Sigue siendo un dato de la organización —se guarda en
            `organizations.num_voluntarios`, que es donde estaba— y sigue
            saliendo sólo en el flujo de empresa. Lo que cambia es dónde se
            pregunta, que es lo que pidió el cliente.
        --}}
        <label class="lbl" style="max-width:280px;margin-top:16px;" x-show="esEmpresa()" x-cloak>¿Cuántos trabajadores participan como voluntarios?
            <input class="fld @error('org_num_voluntarios') is-invalid @enderror" name="org_num_voluntarios"
                   inputmode="numeric" value="@viejo('org_num_voluntarios', $organizacion?->num_voluntarios)" placeholder="Ej. 25">
            <span class="helper">Número aproximado. Escribe 0 si no aplica.</span>
            @error('org_num_voluntarios') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div style="font-size:14.5px;font-weight:700;color:var(--ink);margin:20px 0 9px;">¿Requiere inscripción previa?</div>
        <div style="display:flex;gap:8px;">
            <button type="button" x-bind:class="insc ? 'chip on' : 'chip'" x-on:click="insc = true">Sí</button>
            <button type="button" x-bind:class="insc ? 'chip' : 'chip on'" x-on:click="insc = false">No</button>
        </div>
        <input type="hidden" name="inscripcion_habilitada" x-bind:value="insc ? 1 : 0">

        <x-pregunta-cerrada estilo="font-size:14.5px;font-weight:700;color:var(--ink);margin:20px 0 9px;" />

        {{--
            Con «No», el campo no viaja: `x-show` sólo lo esconde, y escondido
            seguía mandando su 80 de ejemplo, que acababa en la ficha como
            «Cupos disponibles 80» de una actividad sin inscripción. En el HTML
            fuente va dentro de un `<sc-if>` y ahí sencillamente no existe;
            `disabled` es lo que lo deja igual. El servidor lo descarta también.
            Con «Sí» sale vacío y el 80 va de marcador: venir relleno hacía que
            quien no lo tocara publicara con 80 cupos. Vacío es «sin límite».
        --}}
        @if ($actividad)
            {{-- En el editor son los que QUEDAN, y se tocan a mano para reflejar
                 inscripciones recibidas por fuera del sitio. --}}
            <label class="lbl" style="margin-top:16px;max-width:260px;" x-show="insc" x-cloak>Cupos disponibles
                <input class="fld @error('cupos_disponibles') is-invalid @enderror" name="cupos_disponibles"
                       inputmode="numeric" value="@viejo('cupos_disponibles', $actividad->cupos_disponibles)" placeholder="Ej. 80" x-bind:disabled="! insc">
                <span class="helper">Los cupos disponibles son editables manualmente para reflejar inscripciones recibidas por fuera del sitio web.</span>
                @error('cupos_disponibles') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        @else
            <label class="lbl" style="margin-top:16px;max-width:260px;" x-show="insc" x-cloak>Cupos disponibles
                <input class="fld @error('cupos_totales') is-invalid @enderror" name="cupos_totales"
                       inputmode="numeric" value="@viejo('cupos_totales')" placeholder="Ej. 80" x-bind:disabled="! insc">
                <span class="helper">Las personas podrán reservar su cupo desde el sitio web.</span>
                @error('cupos_totales') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        @endif

        {{--
            «¿Qué deben saber las personas antes de asistir?» ya no se pregunta
            al publicar (venía del prototipo viejo del editor). Sólo sale en una
            actividad que lo tenga escrito, para poder corregirlo o borrarlo:
            se sigue viendo en la ficha pública.
        --}}
        @if ($actividad && filled(\App\Support\Formulario::viejo('info_previa', $actividad->info_previa)))
            <label class="lbl" style="margin-top:20px;">¿Qué deben saber las personas antes de asistir? (opcional)
                <textarea class="fld @error('info_previa') is-invalid @enderror" name="info_previa" rows="2"
                          style="resize:vertical;">{{ \App\Support\Formulario::viejo('info_previa', $actividad->info_previa) }}</textarea>
                @error('info_previa') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        @endif
    </div>

    {{-- ── Imagen de portada ── --}}
    {{--
        P19: la portada se reduce en el navegador antes de subirla, y el aviso
        de peso llega al elegir el archivo y no después de enviar. Si aun
        reducida no entra, se corta el envío. Ver resources/js/imagenes.js.
    --}}
    <div class="wizard-seccion campo-seccion" style="padding:30px;border-bottom:1px solid var(--linea);"
         data-campo="imagen" data-etiqueta="Imagen de portada"
         x-data="campoImagen({ maxKb: 2048, ladoMaximo: 1600, que: 'La imagen de portada', comoJpeg: true })">
        <div class="seclabel" style="margin-bottom:18px;">Imagen de portada</div>

        <div class="campo-archivo campo-archivo--apilable" style="display:flex;align-items:center;gap:18px;">
            <span style="display:grid;place-items:center;width:150px;height:78px;border-radius:16px;border:1.5px dashed #dcdee1;background:#fbfbfc;color:#c3c6ca;flex:none;overflow:hidden;">
                @if ($actividad)
                    {{-- En el editor, la actual mientras no se elija otra. --}}
                    <img x-bind:src="previa || {{ Js::from($actividad->imagen_url) }}" src="{{ $actividad->imagen_url }}"
                         alt="Imagen actual de la actividad" style="width:100%;height:100%;object-fit:cover;">
                @else
                <img x-show="previa" x-cloak x-bind:src="previa" alt="" style="width:100%;height:100%;object-fit:cover;">
                <svg x-show="!previa" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path></svg>
                @endif
            </span>
            <div class="campo-archivo-texto">
                <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                    <span x-text="tiene || {{ Js::from((bool) $actividad) }} ? 'Cambiar imagen de portada' : 'Subir imagen de portada'">{{ $actividad ? 'Cambiar imagen de portada' : 'Subir imagen de portada' }}</span>
                    <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" style="display:none;"
                           x-on:change="elegir($event)">
                </label>
                <div class="helper" style="margin-top:7px;">PNG o JPG · máx. 2 MB · 1200×600 px recomendado.</div>
                <div class="helper" x-show="reduciendo" x-cloak>Preparando la imagen…</div>
                <div class="helper" x-show="tiene && ! error" x-cloak>
                    <span x-text="nombre"></span> · <span x-text="peso"></span>
                </div>
                <span class="field-error" x-show="error" x-cloak x-text="error"></span>
                @unless ($actividad)
                    <x-archivo-retenido campo="imagen" />
                @endunless
                @error('imagen') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- ── Información de contacto ── --}}
    <div class="wizard-seccion" style="padding:30px;border-bottom:1px solid var(--linea);">
        <div class="seclabel" style="margin-bottom:6px;">Información de contacto</div>
        <p style="font-size:14px;color:var(--gris);margin:0 0 18px;">Estos datos aparecerán visibles en la ficha pública de la actividad.</p>

        <label style="display:flex;align-items:flex-start;gap:11px;cursor:pointer;margin-bottom:18px;font-size:14.5px;line-height:1.45;color:var(--ink);">
            <input type="checkbox" name="usar_correo_cuenta" value="1" x-model="mismoCorreo"
                   style="width:18px;height:18px;accent-color:var(--naranjo);margin-top:1px;flex:none;">
            <span>Usar el mismo correo de la cuenta como correo de contacto público</span>
        </label>

        <div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <label class="lbl">Correo de contacto público
                {{--
                    `readonly` y no `disabled` mientras la casilla está marcada:
                    un campo deshabilitado NO se envía, y el servidor se
                    quedaría sin correo de contacto justo cuando el usuario ha
                    dicho que quiere el de su cuenta.
                --}}
                <input class="fld @error('correo_contacto') is-invalid @enderror" type="email" name="correo_contacto"
                       x-model="correoContacto" x-bind:readonly="mismoCorreo"
                       placeholder="contacto@organizacion.cl">
                <span class="helper">Para que las personas puedan escribirte con preguntas sobre la actividad.</span>
                @error('correo_contacto') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            {{-- Varias cuentas: quien se suma, o publica en una organización
                 de la que no es la cuenta principal, no cambia la ficha. Los
                 campos siguen en el HTML —se puede entrar o cambiar de cuenta
                 sin recargar— pero no se enseñan, y el servidor ignora lo que
                 llegue. --}}
            <p class="helper" style="grid-column:1/-1;margin:0;" x-show="fichaAjena()" x-cloak data-enlaces-de-la-principal>
                El enlace a red social y el de página web son datos de la organización y los cambia su cuenta principal.
            </p>

            {{-- En el editor, a quien no es la principal ni se le pintan (como
                 antes): el servidor los ignora y no hay cuenta que cambiar. --}}
            @unless ($actividad && ! auth()->user()?->editaLaFicha())
            <label class="lbl" x-show="! fichaAjena()">Enlace a red social
                <input class="fld @error('enlace_red_social') is-invalid @enderror" type="text" inputmode="url" data-autoprotocolo name="enlace_red_social"
                       value="@viejo('enlace_red_social', $organizacion?->enlace_red_social)" placeholder="https://instagram.com/...">
                <span class="helper">Solo un enlace: Instagram, Facebook, LinkedIn o el que prefieras.</span>
                @error('enlace_red_social') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="lbl" x-show="! fichaAjena()">Enlace a página web (opcional)
                <input class="fld @error('enlace_web') is-invalid @enderror" type="text" inputmode="url" data-autoprotocolo name="enlace_web"
                       value="@viejo('enlace_web', $organizacion?->enlace_web)" placeholder="https://tusitio.cl">
                <span class="helper">Si tu actividad tiene una página con más información, compártela aquí.</span>
                @error('enlace_web') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            {{-- Ver la nota del mismo par de campos en account/activities/edit. --}}
            <p class="helper" style="grid-column:1/-1;margin:-4px 0 0;" x-show="! fichaAjena()">
                El enlace a red social y el de página web son datos de tu organización: se muestran en todas tus actividades.
            </p>
            @endunless
        </div>
    </div>

    {{-- ── Colaboración ── --}}
    <div class="wizard-seccion" style="padding:30px;">
        <div class="seclabel" style="margin-bottom:18px;">Colaboración</div>
        <div style="font-size:14.5px;font-weight:700;color:var(--ink);margin-bottom:9px;">¿Esta iniciativa se realiza en colaboración con otras organizaciones o instituciones?</div>

        <div style="display:flex;gap:8px;">
            <button type="button" x-bind:class="colab ? 'chip on' : 'chip'" x-on:click="colab = true">Sí</button>
            <button type="button" x-bind:class="colab ? 'chip' : 'chip on'" x-on:click="colab = false; colabs = []">No</button>
        </div>

        <div x-show="colab" x-cloak style="margin-top:18px;">
            <div style="font-size:14.5px;font-weight:700;color:var(--ink);margin-bottom:9px;">Organizaciones colaboradoras</div>
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
                <input class="fld" style="flex:1;min-width:min(100%, 270px);border:none;padding:4px 2px;box-shadow:none;"
                       placeholder="Escribe un nombre y presiona Enter…" data-placeholder-movil="Escribe y presiona Enter…"
                       x-on:keydown.enter.prevent="agregarColaborador($event)">
            </div>
            <div class="helper" style="margin-top:8px;">Escribe el nombre de cada organización, empresa o institución con la que colaboras. Presiona enter para crear cada etiqueta.</div>
        </div>
    </div>
