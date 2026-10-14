{{-- PASO 4 — TU ACTIVIDAD de publicar-actividad.html (líneas 223-415). --}}
<h1 style="font-size:36px;font-weight:800;letter-spacing:-.02em;margin:0 0 24px;color:var(--ink);">Sobre tu actividad</h1>

{{--
    Este paso NO tenía resumen ninguno: el del paso 3 estaba escrito dentro de
    su propia vista. Como aquí caen casi todos los campos, un error en este
    paso volvía del servidor sin absolutamente nada arriba que lo dijera, y la
    única señal era un renglón rosa de doce píxeles y medio a mitad del
    formulario. Eso es lo que llegó reportado como «tira error aunque los
    campos estén completos».
--}}
<x-resumen-errores :errores="$erroresDelServidor" />

<div style="background:#fff;border:1px solid var(--linea);border-radius:24px;box-shadow:0 18px 40px -32px rgba(0,0,0,.22);overflow:hidden;">

    {{-- Los campos viven en un parcial que comparte con el editor de «Mi
         cuenta» (tanda del 09/10). --}}
    @include('public.partials.campos-actividad', ['actividad' => null, 'organizacion' => $organizacion ?? null])

    <div class="wizard-seccion wizard-pie" style="padding:20px 30px;border-top:1px solid var(--linea);background:#fdfcfb;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <span class="helper">* campos obligatorios</span>
        <div class="wizard-pie-botones" style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">Enviar actividad →</button>
            {{--
                «Guardar borrador», SÓLO con sesión y organización: el borrador
                cuelga de las que ya existen (`PublishController::borrador`).
                Sin sesión no se pinta en absoluto. Y `x-if`, no `x-show`: si
                se cierra la sesión a mitad del wizard (B3, sin recargar), el
                botón sale del DOM.

                Va en el mismo formulario con otra dirección: el `x-on:submit`
                del wizard mira con qué botón se envió y revisa sólo lo que el
                borrador exige. Detrás de «Enviar» en el HTML y delante a la
                vista (`order`): Enter en un campo envía con el PRIMER botón del
                formulario, y tiene que seguir siendo «Enviar actividad».
            --}}
            @if (auth()->user()?->organization)
                <template x-if="conSesion">
                    <button type="submit" class="btn btn-outline" data-guardar-borrador style="order:-1;"
                            name="guardar_borrador" value="1"
                            formaction="{{ route('publish.borrador') }}">Guardar borrador</button>
                </template>
            @endif
        </div>
    </div>
</div>
