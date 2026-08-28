@extends('layouts.admin')
@section('title', $soloPendientes ? 'Organizaciones por verificar' : 'Organizaciones')

{{--
    Organizaciones.

    El botón de eliminar sólo aparece cuando la organización no tiene ninguna
    actividad. Con actividades, borrarla se llevaría también las inscripciones
    de todas ellas: puede ser mucha gente apuntada desapareciendo con un clic, y
    sin forma de deshacerlo desde el panel.

    Para lo demás está «Desactivar», que la esconde del sitio sin tocar nada.
--}}

@section('content')

@if ($soloPendientes)
    <p class="helper" style="margin:-6px 0 16px;">
        Sólo las que esperan verificación. Verificar no bloquea nada: es la marca de que alguien comprobó quiénes son.
    </p>
@endif

<x-panel.filtros
    buscar="Buscar organización…"
    :exportar="[
        'xlsx' => route('admin.organizations.exportar', ['formato' => 'xlsx'] + request()->query()),
        'csv' => route('admin.organizations.exportar', ['formato' => 'csv'] + request()->query()),
    ]">

    <select class="fld" name="estado" x-on:change="enviar()" aria-label="Estado">
        <option value="">Activas e inactivas</option>
        <option value="si" @selected(request('estado') === 'si')>Sólo las activas</option>
        <option value="no" @selected(request('estado') === 'no')>Sólo las inactivas</option>
    </select>

    <select class="fld" name="papelera" x-on:change="enviar()" aria-label="Papelera">
        @foreach (\App\Support\Papelera::OPCIONES as $valor => $texto)
            <option value="{{ $valor }}" @selected(\App\Support\Papelera::estado(request()) === $valor)>{{ $texto }}</option>
        @endforeach
    </select>
</x-panel.filtros>

<x-panel.tabla
    :filas="$organizaciones"
    :columnas="6"
    :vacio="request()->hasAny(['q', 'estado', 'papelera']) ? 'Ninguna organización coincide con el filtro.' : 'Todavía no hay organizaciones.'">

    <x-slot:cabecera>
        <x-panel.columna campo="nombre">Organización</x-panel.columna>
        <x-panel.columna campo="tipo">Tipo</x-panel.columna>
        <th>Contacto</th>
        <th class="num">Actividades</th>
        <x-panel.columna campo="verificada">Verificada</x-panel.columna>
        <th></th>
    </x-slot:cabecera>

    @foreach ($organizaciones as $o)
        <tr @class(['fila-eliminada' => $o->trashed()])>
            <td style="font-weight:600;">
                {{ $o->nombre }}
                @unless ($o->activo)
                    <span class="insignia insignia-no" style="margin-left:6px;">Inactiva</span>
                @endunless
            </td>
            <td>{{ $o->tipo_label }}</td>
            <td>{{ $o->user?->email ?? '—' }}</td>
            <td class="num">
                @if ($o->activities_count)
                    <a class="textlink" href="{{ route('admin.activities.index', ['q' => $o->nombre]) }}">{{ $o->activities_count }}</a>
                    <span class="helper">({{ $o->publicadas_count }} pub.)</span>
                @else
                    <span style="color:var(--gris);">0</span>
                @endif
            </td>
            <td><span class="insignia insignia-{{ $o->verificada ? 'si' : 'no' }}">{{ $o->verificada ? 'Sí' : 'No' }}</span></td>

            <td class="col-acciones">
                @if ($o->trashed())
                    <span class="helper">Eliminada {{ \App\Support\Fecha::relativa($o->deleted_at) }}</span>
                    <form method="POST" action="{{ route('admin.organizations.restaurar', $o->id) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm">Restaurar</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.organizations.verify', $o) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">{{ $o->verificada ? 'Quitar verificación' : 'Verificar' }}</button>
                    </form>

                    <form method="POST" action="{{ route('admin.organizations.alternar', $o) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">{{ $o->activo ? 'Desactivar' : 'Activar' }}</button>
                    </form>

                    <a class="btn btn-outline btn-sm" href="{{ route('admin.organizations.edit', $o) }}">Editar</a>

                    {{-- Sin actividades, se puede eliminar. Con ellas, el botón
                         no está: borrarla se llevaría también sus inscripciones. --}}
                    @if ($o->activities_count === 0)
                        <x-panel.confirmar
                            :accion="route('admin.organizations.destroy', $o)"
                            :titulo="'Eliminar «'.Str::limit($o->nombre, 40).'»'"
                            texto="No tiene ninguna actividad, así que no arrastra nada. Se puede recuperar con el filtro de la papelera."
                            confirmar="Sí, eliminar"
                            boton="Borrar" />
                    @else
                        <span class="helper" title="Elimina sus actividades primero, o desactívala">No se puede eliminar</span>
                    @endif
                @endif
            </td>
        </tr>
    @endforeach
</x-panel.tabla>
@endsection
