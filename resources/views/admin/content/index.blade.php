@extends('layouts.admin')
@section('title', $def['titulo'])

@section('actions')
    <a href="{{ route('admin.content.create', $tipo) }}" class="btn btn-primary btn-sm">Agregar</a>
@endsection

{{--
    Listado genérico de contenido: gobierna siete de los once CRUD del bloque G
    —noticias, ediciones, testimonios, partners, cifras, tarjetas y páginas—.

    Todo sale de los componentes del bloque H. Esta vista sólo dice qué columnas
    tiene este contenido y qué acciones admite; ni una tabla escrita a mano.

    Lo eliminado no desaparece: se esconde, y el filtro de la papelera lo trae de
    vuelta con su botón de restaurar. Se recupera en el mismo listado donde se
    borró, que es donde uno va a buscarlo.
--}}

@section('content')
@php
    // Como mucho cuatro columnas: la etiqueta principal y las primeras que
    // aporten algo, para que la tabla siga siendo legible.
    $columnas = collect($def['campos'])
        ->reject(fn ($m) => $m['tipo'] === 'textarea')
        ->take(4);

    $conFiltros = request()->hasAny(['q', 'estado', 'papelera']);
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

    <select class="fld" name="papelera" x-on:change="enviar()" aria-label="Papelera">
        @foreach (\App\Support\Papelera::OPCIONES as $valor => $texto)
            <option value="{{ $valor }}" @selected(\App\Support\Papelera::estado(request()) === $valor)>{{ $texto }}</option>
        @endforeach
    </select>
</x-panel.filtros>

@if ($tieneOrden && ! $puedeReordenar)
    <p class="helper" style="margin:-6px 0 14px;">
        Para cambiar el orden arrastrando, quita los filtros y vuelve al orden por defecto.
        Arrastrar una fila sobre otra no significa nada cuando la lista está filtrada.
    </p>
@endif

<div @if ($puedeReordenar) x-data="filasOrdenables({{ Js::from(route('admin.content.orden', $tipo)) }})" @endif>
    @if ($puedeReordenar)
        <p class="helper" x-show="guardando" x-cloak style="margin:-6px 0 10px;">Guardando el orden…</p>
        <p class="helper" x-show="error" x-cloak x-text="error" style="margin:-6px 0 10px;color:var(--rosa);"></p>
    @endif

    <x-panel.tabla
        :filas="$filas"
        :columnas="$columnas->count() + ($puedeReordenar ? 2 : 1)"
        que="registros"
        :vacio="$conFiltros ? 'Ningún registro coincide con el filtro.' : 'Todavía no hay registros. Pulsa «Agregar» para crear el primero.'"
        :acciones-en="route('admin.content.masivas', $tipo)"
        :acciones="array_filter([
            'activar' => $tieneActivo && ! $verEliminados ? ['texto' => 'Mostrar en el sitio'] : null,
            'desactivar' => $tieneActivo && ! $verEliminados ? ['texto' => 'Esconder'] : null,
            'restaurar' => $verEliminados ? ['texto' => 'Restaurar'] : null,
            'eliminar' => $verEliminados ? null : ['texto' => 'Eliminar', 'peligro' => true, 'confirmar' => 'Se eliminarán los registros seleccionados. Se pueden recuperar después con el filtro de la papelera.'],
        ])">

        <x-slot:cabecera>
            @if ($puedeReordenar)
                <th style="width:30px;"><span class="visualmente-oculto">Orden</span></th>
            @endif
            @foreach ($columnas as $campo => $meta)
                <x-panel.columna :campo="$puedeReordenar || ! in_array($campo, $ordenables, true) ? null : $campo">{{ $meta['label'] }}</x-panel.columna>
            @endforeach
            <th></th>
        </x-slot:cabecera>

        @foreach ($filas as $fila)
            <tr @class(['fila-eliminada' => $fila->trashed()])
                data-fila="{{ $fila->id }}"
                @if ($puedeReordenar)
                    draggable="true"
                    x-on:dragstart="empezar($event, {{ $fila->id }})"
                    x-on:dragover.prevent="sobre($event, {{ $fila->id }})"
                    x-on:drop.prevent="terminar()"
                    x-on:dragend="terminar()"
                @endif>

                <x-panel.casilla :id="$fila->id" />

                @if ($puedeReordenar)
                    <td style="cursor:grab;color:var(--gris);" aria-hidden="true">⣿</td>
                @endif

                @foreach ($columnas as $campo => $meta)
                    <td @if ($loop->first) style="font-weight:600;" @endif>
                        @if ($meta['tipo'] === 'bool')
                            <span class="insignia insignia-{{ $fila->{$campo} ? 'si' : 'no' }}">{{ $fila->{$campo} ? 'Sí' : 'No' }}</span>
                        @elseif ($meta['tipo'] === 'select')
                            {{ $meta['opciones'][$fila->{$campo}] ?? $fila->{$campo} }}
                        @elseif ($meta['tipo'] === 'datetime')
                            {{ \App\Support\Fecha::corta($fila->{$campo}) }}
                        @else
                            {{ Str::limit((string) $fila->{$campo}, 46) ?: '—' }}
                        @endif
                    </td>
                @endforeach

                <td class="col-acciones">
                    @if ($fila->trashed())
                        <span class="helper">Eliminado {{ \App\Support\Fecha::relativa($fila->deleted_at) }}</span>
                        <form method="POST" action="{{ route('admin.content.restaurar', [$tipo, $fila->id]) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm" data-cargando="…">Restaurar</button>
                        </form>
                    @else
                        @if ($tieneActivo)
                            <form method="POST" action="{{ route('admin.content.alternar', [$tipo, $fila->id]) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">{{ $fila->activo ? 'Esconder' : 'Mostrar' }}</button>
                            </form>
                        @endif

                        <a class="btn btn-outline btn-sm" href="{{ route('admin.content.edit', [$tipo, $fila->id]) }}">Editar</a>

                        <x-panel.confirmar
                            :accion="route('admin.content.destroy', [$tipo, $fila->id])"
                            :titulo="'Eliminar «'.Str::limit((string) ($fila->{$def['etiqueta']} ?? 'este registro'), 40).'»'"
                            texto="Deja de verse en el sitio. Se puede recuperar después con el filtro de la papelera."
                            confirmar="Sí, eliminar"
                            boton="Borrar" />
                    @endif
                </td>
            </tr>
        @endforeach
    </x-panel.tabla>
</div>
@endsection
