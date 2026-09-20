@extends('layouts.admin')
@section('title', 'Inscripciones')

@section('actions')
    <a href="{{ route('admin.registrations.exportar') }}" class="btn btn-outline btn-sm">Exportar con filtros</a>
@endsection

{{--
    Listado de inscripciones, con los componentes del bloque H.

    No lleva selección múltiple: sobre una inscripción no hay ninguna acción
    masiva que tenga sentido todavía —no se borran ni se cambian de estado desde
    aquí— y unas casillas que no llevan a ninguna parte son ruido. El día que
    haya una acción, se le pasan `acciones-en` y `acciones` y aparecen.
--}}

@section('content')

{{--
    El filtro ya no ofrece estados (C3).

    Ofrecía los tres del esquema —pendiente, confirmado, cancelado— y dos de
    ellos no separaban nada: «pendiente» devolvía todo y «confirmado», nada,
    porque el doble opt-in no se construyó. Lo único que distingue de verdad a
    una inscripción de otra es si se dio de baja.
--}}
<x-panel.filtros buscar="Buscar por nombre o correo…">
    <select class="fld" name="estado" x-on:change="enviar()" aria-label="Mostrar">
        <option value="">Todas las inscripciones</option>
        <option value="activas" @selected(request('estado') === 'activas')>Sin las canceladas</option>
        <option value="cancelado" @selected(request('estado') === 'cancelado')>Sólo las canceladas</option>
    </select>
</x-panel.filtros>

<x-panel.tabla
    :filas="$inscritos"
    :columnas="5"
    :vacio="request()->hasAny(['q', 'estado']) ? 'Ninguna inscripción coincide con el filtro.' : 'Todavía no hay inscripciones.'">

    {{--
        Sin columna «Estado» (C3). Pintaba «Pendiente» en todas las filas: nada
        en la aplicación pasa nunca una inscripción a «confirmado». Una baja se
        distingue por la fila atenuada y la etiqueta junto al nombre, igual que
        en la lista del organizador.
    --}}
    <x-slot:cabecera>
        <x-panel.columna campo="id" clase="col-id">ID</x-panel.columna>
        <x-panel.columna campo="nombre">Persona</x-panel.columna>
        <x-panel.columna campo="correo">Correo</x-panel.columna>
        <th>Actividad</th>
        <x-panel.columna campo="created_at">Fecha</x-panel.columna>
    </x-slot:cabecera>

    @foreach ($inscritos as $i)
        <tr @class(['plist-baja' => $i->estado === 'cancelado'])>
            <x-panel.id :valor="$i->id" />

            <td style="font-weight:600;">
                {{ $i->nombre }}
                @if ($i->estado === 'cancelado')
                    <span class="plist-baja-marca">Cancelada</span>
                @endif
            </td>
            <td>{{ $i->correo }}</td>
            <td>{{ Str::limit($i->activity?->titulo, 38) }}</td>
            <td style="white-space:nowrap;">{{ \App\Support\Fecha::corta($i->created_at) }}</td>
        </tr>
    @endforeach
</x-panel.tabla>
@endsection
