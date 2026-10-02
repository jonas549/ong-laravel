{{--
    Los campos de la ficha de una organización. Los comparten crear (punto 11
    del 30/09) y editar, para que las dos pantallas pidan lo mismo.

    `$tipoObligatorio`: al editar, el tipo se exige; al crear una para el
    listado no, igual que en las importadas: se le pregunta a quien la reclame.
--}}
<x-panel.campo nombre="nombre" label="Nombre" :valor="$organizacion->nombre" reglas="required|string|max:255" />

<x-panel.campo nombre="tipo" label="Tipo de organización" tipo="select"
               :valor="$organizacion->tipo" :opciones="$tipos" :reglas="$tipoObligatorio ? 'required' : 'nullable'"
               :ayuda="$tipoObligatorio ? null : 'Opcional: si lo dejas vacío, se le pregunta a quien la reclame.'" />

{{-- La misma regla que valida el servidor: con «Otra» hace falta
     decir cuál. Antes ponía `nullable` y el formulario prometía que
     se podía dejar vacío, y luego rebotaba. --}}
<x-panel.campo nombre="tipo_otro" label="Si es «Otra», ¿cuál?" :valor="$organizacion->tipo_otro"
               reglas="nullable|required_if:tipo,Otra|string|max:255" />

<x-panel.campo nombre="unidad_educativa" label="Unidad educativa" :valor="$organizacion->unidad_educativa"
               reglas="nullable|string|max:255"
               ayuda="Sólo para instituciones educativas." />

<x-panel.campo nombre="descripcion" label="Descripción" tipo="textarea"
               :valor="$organizacion->descripcion" reglas="nullable|string|max:2000" />

<x-panel.campo nombre="correo_contacto" label="Correo de contacto público"
               :valor="$organizacion->correo_contacto" reglas="nullable|email|max:255"
               ayuda="El que se publica. No es el de la cuenta con la que entra." />

<x-panel.campo nombre="enlace_web" label="Sitio web" :valor="$organizacion->enlace_web" reglas="nullable|url:http,https|max:255" />
<x-panel.campo nombre="enlace_red_social" label="Red social" :valor="$organizacion->enlace_red_social" reglas="nullable|url:http,https|max:255" />

<x-panel.campo nombre="anios_participacion" label="Años de participación" :valor="$organizacion->anios_participacion"
               reglas="nullable|string|max:100"
               ayuda="Por ejemplo «2025, 2026». Es lo que la hace salir en la marquesina cuando está en modo automático." />

{{--
    Antes era un campo de texto con la ruta escrita a mano y la nota
    «todavía no hay subida de archivos». Ya la hay: el selector
    envía la misma cadena, así que la validación del controlador no
    cambia.
--}}
<div style="margin-bottom:18px;">
    <x-panel.imagen name="logo_path" :value="$organizacion->logo_path" label="Logo"
                    ayuda="Elígelo de la biblioteca o sube uno nuevo." :alto="110" />
    @error('logo_path')<p class="field-error">{{ $message }}</p>@enderror
</div>
