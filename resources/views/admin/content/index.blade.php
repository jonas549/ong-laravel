@extends('layouts.admin')
@section('title', $def['titulo'])

@section('actions')
    <a href="{{ route('admin.content.create', $tipo) }}" class="btn btn-primary btn-sm">Agregar</a>
@endsection

{{--
    Listado genérico de contenido, ya con los componentes del bloque H.

    Antes eran cincuenta líneas de tabla escritas a mano, sin paginación, sin
    orden, sin filtros y con un `confirm()` del navegador para borrar. Ahora la
    tabla, la barra de filtros y el diálogo salen de los componentes, y esta
    vista sólo dice qué columnas tiene este contenido.
--}}

@section('content')
@php
    // Como mucho cuatro columnas: la etiqueta principal y las primeras que
    // aporten algo, para que la tabla siga siendo legible.
    $columnas = collect($def['campos'])
        ->reject(fn ($m) => $m['tipo'] === 'textarea')
        ->take(4);
@endphp

<x-panel.filtros
    :buscar="'Buscar en '.mb_strtolower($def['titulo']).'…'"
    :exportar="[
        'xlsx' => route('admin.content.exportar', ['tipo' => $tipo, 'formato' => 'xlsx'] + request()->query()),
        'csv' => route('admin.content.exportar', ['tipo' => $tipo, 'formato' => 'csv'] + request()->query()),
    ]">

    @if ($tieneActivo)
        <select class="fld" name="estado" x-on:change="enviar()" aria-label="Visibilidad">
            <option value="">Visibles y ocultos</option>
            <option value="si" @selected(request('estado') === 'si')>Sólo los visibles</option>
            <option value="no" @selected(request('estado') === 'no')>Sólo los ocultos</option>
        </select>
    @endif
</x-panel.filtros>

<x-panel.tabla
    :filas="$filas"
    :columnas="$columnas->count() + 1"
    que="registros"
    :vacio="request()->hasAny(['q', 'estado']) ? 'Ningún registro coincide con el filtro.' : 'Todavía no hay registros. Pulsa «Agregar» para crear el primero.'"
    :acciones-en="route('admin.content.masivas', $tipo)"
    :acciones="array_filter([
        'activar' => $tieneActivo ? ['texto' => 'Mostrar en el sitio'] : null,
        'desactivar' => $tieneActivo ? ['texto' => 'Esconder'] : null,
        'eliminar' => ['texto' => 'Eliminar', 'peligro' => true, 'confirmar' => 'Se eliminarán los registros seleccionados. No se puede deshacer.'],
    ])">

    <x-slot:cabecera>
        @foreach ($columnas as $campo => $meta)
            <x-panel.columna :campo="in_array($campo, $ordenables, true) ? $campo : null">{{ $meta['label'] }}</x-panel.columna>
        @endforeach
        <th></th>
    </x-slot:cabecera>

    @foreach ($filas as $fila)
        <tr>
            <x-panel.casilla :id="$fila->id" />

            @foreach ($columnas as $campo => $meta)
                <td @if ($loop->first) style="font-weight:600;" @endif>
                    @if ($meta['tipo'] === 'bool')
                        <span class="insignia insignia-{{ $fila->{$campo} ? 'si' : 'no' }}">{{ $fila->{$campo} ? 'Sí' : 'No' }}</span>
                    @elseif ($meta['tipo'] === 'select')
                        {{ $meta['opciones'][$fila->{$campo}] ?? $fila->{$campo} }}
                    @elseif ($meta['tipo'] === 'datetime')
                        {{ $fila->{$campo}?->locale('es')->isoFormat('D MMM YYYY') ?? '—' }}
                    @else
                        {{ Str::limit((string) $fila->{$campo}, 46) ?: '—' }}
                    @endif
                </td>
            @endforeach

            <td style="text-align:right;white-space:nowrap;">
                <a class="btn btn-outline btn-sm" href="{{ route('admin.content.edit', [$tipo, $fila->id]) }}">Editar</a>

                <x-panel.confirmar
                    :accion="route('admin.content.destroy', [$tipo, $fila->id])"
                    :titulo="'Eliminar «'.Str::limit((string) ($fila->{$def['etiqueta']} ?? 'este registro'), 40).'»'"
                    texto="Se borra del panel y deja de verse en el sitio. No se puede deshacer."
                    confirmar="Sí, eliminar"
                    boton="Borrar" />
            </td>
        </tr>
    @endforeach
</x-panel.tabla>
@endsection
