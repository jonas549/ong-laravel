@php
    use App\Support\ResumenDeErrores;

    /*
     * Lo mismo que hace el wizard, en pequeño. Este formulario tiene dos campos,
     * pero está metido en la columna de la derecha de una ficha larga: al volver
     * de un POST rechazado la página aparece por su comienzo, y en móvil, donde
     * la columna cae debajo de toda la descripción, el aviso quedaba a un par de
     * pantallas de distancia. Y lo usa gente sin cuenta, que si no entiende qué
     * pasó no vuelve a intentarlo.
     *
     * El catálogo va aquí y no en App\Support porque son tres campos y no los
     * comparte nadie; el del wizard, que son treinta y se usan en tres vistas,
     * vive en CamposDeActividad.
     */
    $catalogoInscripcion = [
        'nombre' => ['Nombre', null],
        'correo' => ['Correo', null],
        'telefono' => ['Teléfono', null],
    ];

    $erroresInscripcion = ResumenDeErrores::desde($errors->getBag('default'), $catalogoInscripcion);
@endphp

{{--
    `required` se queda: en un formulario de tres campos sin pasos ocultos, la
    validación del navegador ya hace lo correcto —frena, enfoca y desplaza— y
    además funciona sin JavaScript. La guía cubre lo otro, que es lo que no
    cubría nadie: lo que rebota desde el servidor —un correo mal escrito, uno ya
    inscrito— y que antes sólo se veía bajando a buscarlo.

    En el wizard es al revés y no lleva `required`: allí los campos viven en
    pasos ocultos, y un control inválido que el navegador no puede enfocar hace
    que Chrome corte el envío sin decir nada. Que es, con otra forma, el mismo
    fallo que se viene a arreglar.
--}}
<form method="POST" action="{{ route('registrations.store', $activity) }}"
      style="display:flex;flex-direction:column;gap:12px;"
      x-data="formularioGuiado({{ Js::from($erroresInscripcion) }})"
      x-on:submit="revisarAntesDeEnviar($event)"
      x-on:input="revisarCampo($event.target.closest('[data-campo]')?.dataset.campo)"
      x-on:change="revisarCampo($event.target.closest('[data-campo]')?.dataset.campo)">
    @csrf

    <div class="seclabel">Inscríbete</div>

    <x-resumen-errores :errores="$erroresInscripcion" />

    <div data-campo="nombre" data-obligatorio data-etiqueta="Nombre">
        <label class="helper" for="r-nombre" style="display:block;margin-bottom:5px;font-weight:600;">Nombre *</label>
        <input class="fld @error('nombre') is-invalid @enderror" type="text" id="r-nombre" name="nombre"
               value="@viejo('nombre')" autocomplete="name" required>
        @error('nombre') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div data-campo="correo" data-obligatorio data-etiqueta="Correo">
        <label class="helper" for="r-correo" style="display:block;margin-bottom:5px;font-weight:600;">Correo *</label>
        <input class="fld @error('correo') is-invalid @enderror" type="email" id="r-correo" name="correo"
               value="@viejo('correo')" autocomplete="email" required>
        <span class="helper">Ahí te llega la confirmación de tu cupo.</span>
        @error('correo') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    {{-- Sin asterisco y diciéndolo: es el único opcional de los tres, y no
         decirlo deja la duda de si falta algo cuando se envía en blanco. --}}
    <div data-campo="telefono" data-etiqueta="Teléfono">
        <label class="helper" for="r-telefono" style="display:block;margin-bottom:5px;font-weight:600;">Teléfono <span style="font-weight:400;">(opcional)</span></label>
        <input class="fld @error('telefono') is-invalid @enderror" type="tel" id="r-telefono" name="telefono"
               value="@viejo('telefono')" autocomplete="tel">
        @error('telefono') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <label style="display:flex;align-items:center;gap:9px;font-size:14px;color:var(--gris-700);cursor:pointer;">
        <input type="checkbox" name="es_mayor_edad" value="1" @checked(old('es_mayor_edad', true))
               style="width:17px;height:17px;accent-color:var(--naranjo);">
        Soy mayor de edad
    </label>

    <button type="submit" class="btn btn-primary" style="justify-content:center;">Reservar mi cupo</button>
</form>
