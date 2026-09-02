@extends('layouts.admin')
@section('title', 'Editar organización')
@section('miga', Str::limit($organizacion->nombre, 32))

{{--
    Ficha de una organización.

    No se puede cambiar la cuenta a la que pertenece: eso es mover la propiedad
    de sus actividades de una persona a otra, y no se resuelve con un selector.
    Aquí se corrigen los datos que la ONG puede necesitar arreglar —un nombre mal
    escrito, un correo de contacto, un tipo equivocado en el wizard—.
--}}

@section('content')
<a href="{{ route('admin.organizations.index') }}" class="textlink" style="font-size:14px;">← Volver a organizaciones</a>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:18px;align-items:start;">

    <section class="card" style="padding:26px;grid-column:span 2;min-width:0;">
        <form method="POST" action="{{ route('admin.organizations.update', $organizacion) }}"
              style="display:flex;flex-direction:column;gap:18px;">
            @csrf
            @method('PUT')

            <x-panel.campo nombre="nombre" label="Nombre" :valor="$organizacion->nombre" reglas="required|string|max:255" />

            <x-panel.campo nombre="tipo" label="Tipo de organización" tipo="select"
                           :valor="$organizacion->tipo" :opciones="$tipos" reglas="required" />

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

            <x-panel.campo nombre="enlace_web" label="Sitio web" :valor="$organizacion->enlace_web" reglas="nullable|url|max:255" />
            <x-panel.campo nombre="enlace_red_social" label="Red social" :valor="$organizacion->enlace_red_social" reglas="nullable|url|max:255" />

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

            {{--
                El interruptor por organización de la aprobación automática.

                Es el que sirve de verdad cuando llega spam: el general apaga la
                comodidad para todas, y éste sólo para quien haga falta. La
                casilla habla en positivo —«revisar siempre»— porque es lo que
                se va a buscar cuando alguien esté dando problemas.
            --}}
            <div style="margin:4px 0 20px;padding:16px 18px;border:1.5px solid var(--linea);border-radius:16px;background:#fdfcfb;">
                {{-- `x-panel.campo` con tipo bool, no `x-panel.casilla`: aquélla es
                     la casilla de una fila de tabla, con su `ids[]` y su form
                     de fuera, y aquí revienta por no recibir un $id. --}}
                <x-panel.campo nombre="requiere_revision" tipo="bool"
                               label="Revisar siempre sus actividades a mano"
                               :valor="$organizacion->requiere_revision" />
                <p class="helper" style="margin:8px 0 0 27px;">
                    @if (\App\Models\Setting::get('aprobacion_automatica', true))
                        Por defecto, una organización que ya publicó alguna vez sube sus
                        actividades sin pasar por revisión. Marca esto para que las suyas
                        pasen siempre.
                    @else
                        Ahora mismo da igual: la aprobación automática está apagada para
                        todo el sitio en Configuración, así que ya se revisan todas.
                    @endif
                </p>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" data-cargando="Guardando…">Guardar cambios</button>
                <a href="{{ route('admin.organizations.index') }}" class="btn btn-ghost">Cancelar</a>
            </div>
        </form>
    </section>

    <aside class="card" style="padding:22px 24px;">
        <div class="seclabel" style="margin-bottom:14px;">Qué cuelga de aquí</div>

        <dl style="display:flex;flex-direction:column;gap:12px;margin:0;font-size:14px;">
            <div>
                <dt class="helper">Cuenta</dt>
                <dd style="margin:0;">{{ $organizacion->user?->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="helper">Actividades</dt>
                <dd style="margin:0;font-weight:700;">{{ $organizacion->activities_count }}</dd>
            </div>
            <div>
                <dt class="helper">Inscripciones en esas actividades</dt>
                <dd style="margin:0;font-weight:700;">{{ $organizacion->registrations_count }}</dd>
            </div>
            <div>
                <dt class="helper">Alta</dt>
                <dd style="margin:0;">{{ \App\Support\Fecha::corta($organizacion->created_at) }}</dd>
            </div>
        </dl>

        <div style="border-top:1px solid var(--linea);margin-top:18px;padding-top:16px;display:flex;flex-direction:column;gap:8px;">
            <form method="POST" action="{{ route('admin.organizations.alternar', $organizacion) }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">
                    {{ $organizacion->activo ? 'Desactivar' : 'Activar' }}
                </button>
            </form>

            @if ($organizacion->activities_count === 0)
                <x-panel.confirmar
                    :accion="route('admin.organizations.destroy', $organizacion)"
                    :titulo="'Eliminar «'.Str::limit($organizacion->nombre, 40).'»'"
                    texto="No tiene ninguna actividad, así que no arrastra nada. Se puede recuperar con el filtro de la papelera."
                    confirmar="Sí, eliminar"
                    boton="Eliminar organización"
                    clase="btn btn-danger btn-sm" />
            @else
                <p class="helper" style="margin:0;">
                    <strong>No se puede eliminar.</strong>
                    Tiene {{ \App\Support\Texto::cuantos($organizacion->activities_count, 'actividad') }}
                    y {{ \App\Support\Texto::cuantos($organizacion->registrations_count, 'inscripción') }} colgando de ellas.
                    Desactivarla la esconde sin borrar nada.
                </p>
            @endif
        </div>
    </aside>
</div>
@endsection
