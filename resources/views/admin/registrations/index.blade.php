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

<x-panel.filtros buscar="Buscar por nombre o correo…">
    <select class="fld" name="estado" x-on:change="enviar()" aria-label="Estado de la inscripción">
        <option value="">Todos los estados</option>
        <option value="activas" @selected(request('estado') === 'activas')>Sin las canceladas</option>
        @foreach ($estados as $e)
            <option value="{{ $e }}" @selected($estado === $e)>{{ ucfirst($e) }}</option>
        @endforeach
    </select>
</x-panel.filtros>

<x-panel.tabla
    :filas="$inscritos"
    :columnas="5"
    :vacio="request()->hasAny(['q', 'estado']) ? 'Ninguna inscripción coincide con el filtro.' : 'Todavía no hay inscripciones.'">

    <x-slot:cabecera>
        <x-panel.columna campo="nombre">Persona</x-panel.columna>
        <x-panel.columna campo="correo">Correo</x-panel.columna>
        <th>Actividad</th>
        <x-panel.columna campo="created_at">Fecha</x-panel.columna>
        <x-panel.columna campo="estado">Estado</x-panel.columna>
    </x-slot:cabecera>

    @foreach ($inscritos as $i)
        @php $c = $i->estado_color; @endphp
        <tr>
            <td style="font-weight:600;">{{ $i->nombre }}</td>
            <td>{{ $i->correo }}</td>
            <td>{{ Str::limit($i->activity?->titulo, 38) }}</td>
            <td style="white-space:nowrap;">{{ $i->created_at->locale('es')->isoFormat('D MMM YYYY') }}</td>
            <td>
                <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:{{ $c['bg'] }};color:{{ $c['ink'] }};">
                    {{ $i->estado_label }}
                </span>
            </td>
        </tr>
    @endforeach
</x-panel.tabla>
@endsection
