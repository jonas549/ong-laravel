@php
    use App\Http\Requests\EvaluationRequest;
    use App\Models\ActivityEvaluation;
    use App\Support\ResumenDeErrores;

    $footerCompacto = true;

    /*
     * El catálogo de este formulario, para el resumen de errores del bloque K.
     *
     * Va aquí y no en `App\Support` porque son siete campos que no comparte
     * nadie; el del wizard, que son treinta y se usan en tres vistas, sí vive
     * en `CamposDeActividad`. El paso es `null` en todos: esta pantalla no
     * tiene pasos, así que la guía no intenta cambiar de pantalla antes de
     * saltar al campo.
     */
    $catalogo = [
        'nombre' => ['Nombre', null],
        'correo' => ['Correo electrónico', null],
        'experiencia' => ['Tu experiencia en la actividad', null],
        'significado' => ['Qué significa para ti el Patrimonio Social', null],
        'motivacion' => ['Tu motivación para volver a participar', null],
        'como_se_entero' => ['Cómo te enteraste', null],
        'foto' => ['Fotografía', null],
    ];

    $erroresGuia = ResumenDeErrores::desde($errors->getBag('default'), $catalogo);
@endphp

@extends('layouts.public')

@section('title', 'Cuéntanos cómo fue tu experiencia · '.$activity->titulo)
@section('meta', 'Evalúa tu experiencia en «'.$activity->titulo.'» del Día del Patrimonio Social.')

@section('content')
{{--
    La encuesta.

    El wireframe la dibuja a 375 px porque se llega escaneando un cartel, pero
    va en las dos versiones. En escritorio es la misma columna centrada con un
    ancho máximo, que es lo que hacen ya el resto de formularios del sitio: una
    encuesta de siete campos repartida en dos columnas se lee peor, no mejor.

    El diseño del wireframe es neutro —grises y negro— y aquí se traduce a la
    identidad del sitio: naranja, Raleway para los títulos e Inter para el
    resto, `.fld`, `.btn` y `.card` como en todas las demás pantallas.
--}}
<main class="evaluacion-envoltorio">
    <section class="evaluacion-caja card">

        <h1 class="evaluacion-titulo">Cuéntanos cómo fue tu experiencia</h1>

        <p class="evaluacion-estas">Estás evaluando</p>
        <p class="evaluacion-actividad">{{ $activity->titulo }}</p>

        <hr class="evaluacion-linea">

        <form method="POST" action="{{ route('evaluar.store', $activity) }}"
              enctype="multipart/form-data"
              class="evaluacion-form"
              x-data="encuestaEvaluacion({{ Js::from($erroresGuia) }}, {{ ActivityEvaluation::MAX_SIGNIFICADO }})"
              x-on:submit="revisarAntesDeEnviar($event)"
              x-on:input="revisarCampo($event.target.closest('[data-campo]')?.dataset.campo)"
              x-on:change="revisarCampo($event.target.closest('[data-campo]')?.dataset.campo)">
            @csrf

            {{--
                Anti-spam, capas 2 y 3. Ver `EvaluationRequest`.

                El campo trampa se esconde con CSS y no con `type="hidden"`: un
                robot que lea el HTML rellena lo que parezca un campo de texto y
                salta los ocultos. `tabindex="-1"` y `autocomplete="off"` son
                para que ninguna persona llegue a él tabulando ni se lo rellene
                el navegador solo.
            --}}
            <div class="evaluacion-trampa" aria-hidden="true">
                <label for="{{ EvaluationRequest::TRAMPA }}">No rellenes este campo</label>
                <input type="text" id="{{ EvaluationRequest::TRAMPA }}" name="{{ EvaluationRequest::TRAMPA }}"
                       value="" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="{{ EvaluationRequest::RELOJ }}"
                   value="{{ EvaluationRequest::relojParaElFormulario() }}">

            <x-resumen-errores :errores="$erroresGuia" />

            {{-- ── Nombre ── --}}
            <div class="evaluacion-campo" data-campo="nombre" data-obligatorio data-etiqueta="Nombre">
                <label class="evaluacion-lbl" for="ev-nombre">Nombre *</label>
                <input class="fld @error('nombre') is-invalid @enderror" type="text" id="ev-nombre" name="nombre"
                       value="{{ old('nombre') }}" placeholder="Tu nombre" autocomplete="name" required>
                @error('nombre') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- ── Correo ── --}}
            <div class="evaluacion-campo" data-campo="correo" data-obligatorio data-etiqueta="Correo electrónico">
                <label class="evaluacion-lbl" for="ev-correo">Correo electrónico *</label>
                <input class="fld @error('correo') is-invalid @enderror" type="email" id="ev-correo" name="correo"
                       value="{{ old('correo') }}" placeholder="nombre@correo.cl" autocomplete="email" required>
                @error('correo') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{--
                ── Las dos escalas ──

                Son `<input type="radio">` de verdad, con la caja como etiqueta.
                No son botones con un `hidden` detrás, y eso importa por tres
                cosas: funcionan sin JavaScript, el lector de pantalla los
                anuncia como el grupo de opciones que son, y las flechas del
                teclado los recorren solas. La caja del wireframe es sólo el
                aspecto; debajo hay un formulario normal.
            --}}
            @foreach (['experiencia', 'motivacion'] as $escala)
                <fieldset class="evaluacion-campo evaluacion-escala"
                          data-campo="{{ $escala }}" data-obligatorio
                          data-etiqueta="{{ $escala === 'experiencia' ? 'Tu experiencia en la actividad' : 'Tu motivación para volver a participar' }}">
                    <legend class="evaluacion-lbl">{{ $escalas[$escala]['pregunta'] }} *</legend>

                    <div class="evaluacion-notas">
                        @for ($n = 1; $n <= 5; $n++)
                            <label class="evaluacion-nota">
                                <input type="radio" name="{{ $escala }}" value="{{ $n }}"
                                       @checked((int) old($escala) === $n) required>
                                <span>{{ $n }}</span>
                            </label>
                        @endfor
                    </div>

                    <div class="evaluacion-extremos">
                        <span>{{ $escalas[$escala]['min'] }}</span>
                        <span>{{ $escalas[$escala]['max'] }}</span>
                    </div>

                    @error($escala) <span class="field-error">{{ $message }}</span> @enderror
                </fieldset>
            @endforeach

            {{-- ── Respuesta abierta ── --}}
            <div class="evaluacion-campo" data-campo="significado" data-obligatorio
                 data-etiqueta="Qué significa para ti el Patrimonio Social">
                <label class="evaluacion-lbl" for="ev-significado">Después de participar, ¿qué significa para ti el Patrimonio Social? *</label>

                <textarea class="fld evaluacion-texto @error('significado') is-invalid @enderror"
                          id="ev-significado" name="significado" rows="4"
                          maxlength="{{ ActivityEvaluation::MAX_SIGNIFICADO }}"
                          placeholder="Respuesta breve…"
                          data-contador
                          x-on:input="contar($event)" required>{{ old('significado') }}</textarea>

                {{--
                    El contador se pinta con Alpine y el texto de respaldo va
                    escrito en el HTML: sin JavaScript sigue diciendo cuál es el
                    máximo, que es la mitad de la información.

                    Nunca baja de cero. El del panel se iba a negativo tras un
                    rebote del servidor y quedó anotado en el testing de Cowork.
                --}}
                <span class="helper evaluacion-contador">
                    <span x-text="restantes">{{ ActivityEvaluation::MAX_SIGNIFICADO }}</span>
                    caracteres disponibles de {{ ActivityEvaluation::MAX_SIGNIFICADO }}
                </span>

                @error('significado') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- ══ Opcional ══ --}}
            <div class="evaluacion-separador">
                <span>Opcional</span>
            </div>

            {{-- ── Cómo se enteró ── --}}
            <div class="evaluacion-campo" data-campo="como_se_entero" data-etiqueta="Cómo te enteraste">
                <label class="evaluacion-lbl" for="ev-origen">¿Cómo te enteraste de esta actividad?</label>
                <select class="fld @error('como_se_entero') is-invalid @enderror" id="ev-origen" name="como_se_entero">
                    <option value="">Selecciona una opción</option>
                    @foreach ($origenes as $clave => $texto)
                        <option value="{{ $clave }}" @selected(old('como_se_entero') === $clave)>{{ $texto }}</option>
                    @endforeach
                </select>
                @error('como_se_entero') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- ── Fotografía ── --}}
            <div class="evaluacion-campo" data-campo="foto" data-etiqueta="Fotografía">
                <label class="evaluacion-lbl" for="ev-foto">Subir una fotografía</label>

                <div class="evaluacion-foto">
                    <div class="evaluacion-foto-previa">
                        {{-- La miniatura, cuando ya hay foto elegida. --}}
                        <img x-show="previa" x-cloak x-bind:src="previa" alt="" class="evaluacion-foto-imagen">

                        <span x-show="!previa" class="evaluacion-foto-icono" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        </span>
                    </div>

                    <div class="evaluacion-foto-datos">
                        <label class="btn btn-outline btn-sm evaluacion-foto-boton" for="ev-foto">
                            <span x-text="tieneFoto ? 'Cambiar imagen' : 'Elegir imagen'">Elegir imagen</span>
                        </label>

                        <input type="file" id="ev-foto" name="foto" accept="image/jpeg,image/png"
                               class="evaluacion-foto-input"
                               x-on:change="elegirFoto($event)">

                        <span class="helper evaluacion-foto-pie" x-show="!tieneFoto">JPG o PNG, hasta 5 MB</span>

                        <span class="helper evaluacion-foto-nombre" x-show="tieneFoto" x-cloak x-text="nombreFoto"></span>

                        <button type="button" class="btn btn-ghost btn-sm evaluacion-foto-quitar"
                                x-show="tieneFoto" x-cloak x-on:click="quitarFoto()">Quitar</button>

                        <span class="helper" x-show="reduciendo" x-cloak>Preparando la imagen…</span>
                    </div>
                </div>

                {{-- El error del navegador (tipo o tamaño), antes de enviar nada. --}}
                <span class="field-error" x-show="errorFoto" x-cloak x-text="errorFoto"></span>

                @error('foto') <span class="field-error">{{ $message }}</span> @enderror

                {{--
                    La autorización.

                    **Oculta hasta que hay fotografía**, que es lo que pide el
                    encargo y además es lo correcto: una casilla para autorizar
                    el uso de una foto que no existe no significa nada, y
                    marcada por descuido dejaría una autorización sobre nada.
                    El servidor lo vuelve a comprobar —sin archivo guarda
                    `false` pase lo que pase— porque esto de aquí es comodidad,
                    no garantía.

                    `x-cloak` evita que asome durante el instante que Alpine
                    tarda en arrancar.
                --}}
                <div class="evaluacion-autorizacion" x-show="tieneFoto" x-cloak>
                    <label class="evaluacion-autorizacion-lbl">
                        <input type="checkbox" name="foto_autorizada" value="1" @checked(old('foto_autorizada'))>
                        <span>Autorizo el uso de esta fotografía para fines de difusión del Día del Patrimonio Social</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary evaluacion-enviar">Enviar evaluación</button>

            {{--
                Dónde va lo que se escribe aquí. Se recogen nombre, correo y una
                fotografía de una persona: decirlo es lo mínimo, y el enlace es
                a la página que ya existe.
            --}}
            <p class="helper evaluacion-privacidad">
                Usaremos tus respuestas para mejorar las próximas ediciones.
                Puedes leer cómo tratamos tus datos en la
                <a class="textlink" href="{{ url('/privacidad') }}">política de privacidad</a>.
            </p>
        </form>
    </section>
</main>
@endsection
