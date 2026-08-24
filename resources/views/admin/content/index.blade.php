@extends('layouts.admin')
@section('title', $def['titulo'])

@section('actions')
    <a href="{{ route('admin.content.create', $tipo) }}" class="btn btn-primary btn-sm">Agregar</a>
@endsection

@section('content')
@php
    // Mostramos como máximo cuatro columnas: la etiqueta principal más las
    // primeras que aporten información, para que la tabla siga siendo legible.
    $columnas = collect($def['campos'])
        ->reject(fn ($m) => in_array($m['tipo'], ['textarea'], true))
        ->take(4);
@endphp

<div class="tabla-wrap">
    <table class="tabla">
        <thead>
            <tr>
                @foreach ($columnas as $campo => $meta)
                    <th>{{ $meta['label'] }}</th>
                @endforeach
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach ($columnas as $campo => $meta)
                        <td @if ($loop->first) style="font-weight:600;" @endif>
                            @if ($meta['tipo'] === 'bool')
                                {{ $fila->{$campo} ? 'Sí' : 'No' }}
                            @elseif ($meta['tipo'] === 'select')
                                {{ $meta['opciones'][$fila->{$campo}] ?? $fila->{$campo} }}
                            @elseif ($meta['tipo'] === 'datetime')
                                {{ $fila->{$campo}?->locale('es')->isoFormat('D MMM YYYY') ?? '—' }}
                            @else
                                {{ Str::limit((string) $fila->{$campo}, 46) ?: '—' }}
                            @endif
                        </td>
                    @endforeach
                    <td style="white-space:nowrap;display:flex;gap:8px;">
                        <a class="btn btn-outline btn-sm" href="{{ route('admin.content.edit', [$tipo, $fila->id]) }}">Editar</a>
                        <form method="POST" action="{{ route('admin.content.destroy', [$tipo, $fila->id]) }}"
                              onsubmit="return confirm('¿Eliminar este registro? No se puede deshacer.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Borrar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columnas->count() + 1 }}" style="color:var(--gris);">
                        Todavía no hay registros. <a class="textlink" href="{{ route('admin.content.create', $tipo) }}">Agrega el primero</a>.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
