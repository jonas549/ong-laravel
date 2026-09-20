@extends('layouts.admin')
@section('title', 'Evaluaciones')

@section('actions')
    <a href="{{ route('admin.evaluaciones.fotos') }}" class="btn btn-outline btn-sm">Ver fotografías</a>
@endsection

{{--
    Las respuestas a la encuesta de evaluación.

    No estaba en el encargo y hace falta igual: sin esta pantalla se recogen
    datos que no puede leer nadie, que es la peor versión de recoger datos.

    El texto de cada respuesta se enseña ENTERO y no recortado. Son trescientos
    caracteres como mucho, y recortarlos a cuarenta con puntos suspensivos
    obligaría a abrir una ficha por respuesta para leer justo lo único que esta
    pantalla existe para leer.
--}}

@section('content')

{{-- ── Los promedios ────────────────────────────────────────
     Con el reparto debajo, no sólo la media. Un 3 de media puede ser todo el
     mundo dando un 3, o media sala dando 1 y la otra media dando 5: son dos
     actividades distintas y el promedio solo no las distingue. --}}
<section class="eval-resumen">
    @foreach ($escalas as $clave => $escala)
        @php $r = $resumen[$clave]; @endphp
        <article class="card eval-resumen-tarjeta">
            <p class="eval-resumen-pregunta">{{ $escala['pregunta'] }}</p>

            <p class="eval-resumen-nota">
                @if ($r['promedio'] === null)
                    <span class="eval-resumen-sin">Sin respuestas</span>
                @else
                    <strong>{{ number_format($r['promedio'], 2, ',', '.') }}</strong>
                    <span class="eval-resumen-de">de 5</span>
                    <span class="helper">· {{ $r['total'] }} {{ $r['total'] === 1 ? 'respuesta' : 'respuestas' }}</span>
                @endif
            </p>

            @if ($r['total'] > 0)
                <ul class="eval-reparto">
                    @foreach ($r['reparto'] as $nota => $cuantas)
                        @php $porcentaje = $r['total'] ? round($cuantas * 100 / $r['total']) : 0; @endphp
                        <li class="eval-reparto-fila">
                            <span class="eval-reparto-nota">{{ $nota }}</span>
                            <span class="eval-reparto-barra">
                                {{-- El ancho va en `style` estático y no con Alpine: aquí no
                                     hay componente, y un `:style` con cadena reemplazaría el
                                     atributo entero. --}}
                                <span class="eval-reparto-relleno" style="width:{{ $porcentaje }}%;"></span>
                            </span>
                            <span class="eval-reparto-cuantas">{{ $cuantas }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="helper eval-resumen-extremos">
                    <span>1 · {{ $escala['min'] }}</span>
                    <span>5 · {{ $escala['max'] }}</span>
                </p>
            @endif
        </article>
    @endforeach
</section>

<x-panel.filtros
    buscar="Buscar por nombre, correo o texto…"
    :exportar="[
        'xlsx' => route('admin.evaluaciones.exportar', request()->query()),
        'csv' => route('admin.evaluaciones.exportar', request()->query() + ['formato' => 'csv']),
    ]">

    <select class="fld" name="actividad" x-on:change="enviar()" aria-label="Actividad">
        <option value="">Todas las actividades</option>
        @foreach ($actividades as $id => $titulo)
            <option value="{{ $id }}" @selected((string) $filtros['actividad'] === (string) $id)>{{ Str::limit($titulo, 46) }}</option>
        @endforeach
    </select>

    <select class="fld" name="foto" x-on:change="enviar()" aria-label="Fotografía">
        <option value="">Con y sin fotografía</option>
        <option value="con" @selected($filtros['foto'] === 'con')>Sólo con fotografía</option>
        <option value="autorizadas" @selected($filtros['foto'] === 'autorizadas')>Sólo fotos autorizadas</option>
    </select>

    <label class="panel-filtros-fecha">
        <span class="helper">Desde</span>
        <input class="fld" type="date" name="desde" value="{{ $filtros['desde'] }}" x-on:change="enviar()">
    </label>

    <label class="panel-filtros-fecha">
        <span class="helper">Hasta</span>
        <input class="fld" type="date" name="hasta" value="{{ $filtros['hasta'] }}" x-on:change="enviar()">
    </label>
</x-panel.filtros>

<x-panel.tabla
    :filas="$evaluaciones"
    :columnas="7"
    :vacio="request()->hasAny(['q', 'actividad', 'desde', 'hasta', 'foto'])
        ? 'Ninguna evaluación coincide con el filtro.'
        : 'Todavía no hay evaluaciones. Llegan cuando alguien escanea el QR de una actividad.'">

    <x-slot:cabecera>
        <x-panel.columna campo="id" clase="col-id">ID</x-panel.columna>
        <x-panel.columna campo="created_at">Fecha</x-panel.columna>
        <th>Actividad</th>
        <x-panel.columna campo="nombre">Persona</x-panel.columna>
        <x-panel.columna campo="experiencia">Notas</x-panel.columna>
        <th>Qué significa para ti el Patrimonio Social</th>
        <th></th>
    </x-slot:cabecera>

    @foreach ($evaluaciones as $e)
        <tr>
            <x-panel.id :valor="$e->id" />

            <td style="white-space:nowrap;">{{ \App\Support\Fecha::corta($e->created_at) }}</td>

            <td>{{ Str::limit($e->activity?->titulo ?? '(actividad borrada)', 34) }}</td>

            <td>
                <span style="display:block;font-weight:600;">{{ $e->nombre }}</span>
                <span class="helper">{{ $e->correo }}</span>
                @if ($e->como_se_entero)
                    <span class="helper" style="display:block;">Se enteró por: {{ $e->origen_label }}</span>
                @endif
            </td>

            <td style="white-space:nowrap;">
                <span class="eval-nota" title="Experiencia">{{ $e->experiencia }}</span>
                <span class="eval-nota eval-nota-motivacion" title="Motivación">{{ $e->motivacion }}</span>
            </td>

            <td class="eval-texto">{{ $e->significado }}</td>

            <td style="white-space:nowrap;text-align:right;">
                @if ($e->tieneFoto())
                    <a class="btn btn-outline btn-sm" href="{{ route('admin.evaluaciones.foto', $e) }}" target="_blank" rel="noopener">
                        {{ $e->foto_autorizada ? 'Foto ✓' : 'Foto' }}
                    </a>
                @endif

                <x-panel.confirmar
                    :accion="route('admin.evaluaciones.destroy', $e)"
                    titulo="¿Eliminar esta evaluación?"
                    :texto="'Se borra la respuesta de '.$e->nombre.' y su fotografía, si la hubiera. No se puede deshacer.'"
                    confirmar="Sí, eliminar" />
            </td>
        </tr>
    @endforeach
</x-panel.tabla>
@endsection
