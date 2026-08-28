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

            <x-panel.campo nombre="logo_path" label="Logo" :valor="$organizacion->logo_path" reglas="nullable|string|max:255"
                           ayuda="Ruta dentro de public/, por ejemplo img/logo-x.png. Todavía no hay subida de archivos." />

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
