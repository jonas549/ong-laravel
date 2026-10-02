@extends('layouts.admin')
@section('title', 'Fotografías de las evaluaciones')

@section('actions')
    {{--
        La descarga se lleva EXACTAMENTE lo que hay a la vista: se le pasan los
        mismos parámetros de la URL, filtros incluidos. Un botón que siempre se
        lo lleva todo obliga a separar a mano después, y entonces no ahorra nada.

        `request()->query()` y no una lista de campos: así un filtro nuevo en la
        pantalla viaja solo, sin que nadie tenga que acordarse de añadirlo aquí.
    --}}
    {{-- Punto 9 del 30/09: con una actividad filtrada ya bajaba sólo ésa,
         pero el botón decía lo mismo y no se notaba. Ahora la nombra. --}}
    @php $tituloFiltrado = $filtros['actividad'] ? ($actividades[$filtros['actividad']] ?? null) : null; @endphp
    <a href="{{ route('admin.evaluaciones.fotos.zip', request()->query() + ['estado' => $cual]) }}"
       class="btn btn-outline btn-sm" data-descargar-todas>
        @if ($tituloFiltrado)
            Descargar las fotos de «{{ Str::limit($tituloFiltrado, 34) }}»
        @else
            Descargar estas fotografías
        @endif
    </a>
    <a href="{{ route('admin.evaluaciones.index', request()->query()) }}" class="btn btn-outline btn-sm">Ver respuestas</a>
@endsection

{{--
    Las fotografías que suben los asistentes.

    **La separación entre autorizadas y no autorizadas es el punto entero de
    esta pantalla**, y por eso son dos pestañas y no una columna de una tabla.
    Una foto sin autorización no es una que falte revisar: es una que NO se
    puede publicar, y tenerlas mezcladas en una misma cuadrícula es la forma más
    fácil de que alguien coja la equivocada al preparar un post.

    Se sirven por una ruta del panel y no por URL pública: viven en el disco
    privado justo para que nadie llegue a ellas sin sesión.
--}}

@section('content')

<nav class="eval-pestanas" aria-label="Estado de la autorización">
    <a class="eval-pestana {{ $cual === 'autorizadas' ? 'activa' : '' }}"
       href="{{ route('admin.evaluaciones.fotos', request()->except(['estado', 'page'])) }}">
        Con autorización
        <span class="eval-pestana-cuenta">{{ $cuantas['autorizadas'] }}</span>
    </a>

    <a class="eval-pestana {{ $cual === 'sin-autorizar' ? 'activa' : '' }}"
       href="{{ route('admin.evaluaciones.fotos', request()->except('page') + ['estado' => 'sin-autorizar']) }}">
        Sin autorización
        <span class="eval-pestana-cuenta">{{ $cuantas['sin-autorizar'] }}</span>
    </a>
</nav>

@if ($cual === 'sin-autorizar')
    {{-- El aviso va arriba y no al pie de cada foto: quien entra aquí tiene que
         saber en qué pestaña está ANTES de mirar ninguna imagen. --}}
    <div class="alert alert-error" style="margin-bottom:18px;">
        <strong>Estas fotografías no se pueden publicar.</strong>
        Quien las subió no autorizó su uso para difusión. Sirven para que veáis cómo fue la
        actividad, y ahí se acaba lo que se puede hacer con ellas.
    </div>
@else
    <div class="alert alert-ok" style="margin-bottom:18px;">
        <strong>Estas sí se pueden usar para difusión.</strong>
        Quien las subió marcó la autorización. Con «Pasar a la biblioteca» quedan disponibles
        en el selector de imágenes del panel.
    </div>
@endif

<x-panel.filtros :tamano="false" buscar="Buscar por nombre o correo…">
    <input type="hidden" name="estado" value="{{ $cual }}">

    <select class="fld" name="actividad" x-on:change="enviar()" aria-label="Actividad">
        <option value="">Todas las actividades</option>
        @foreach ($actividades as $id => $titulo)
            <option value="{{ $id }}" @selected((string) $filtros['actividad'] === (string) $id)>{{ Str::limit($titulo, 46) }}</option>
        @endforeach
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

@if ($fotos->isEmpty())
    <section class="card" style="padding:34px;text-align:center;color:var(--gris);">
        {{ $cual === 'autorizadas'
            ? 'Todavía no hay fotografías con autorización de difusión.'
            : 'No hay fotografías sin autorizar.' }}
    </section>
@else
    {{--
        Punto 8 del 30/09: marcar fotos y bajar sólo ésas. La selección es de
        esta página; el zip la cruza con la pestaña y los filtros, así que
        nunca mezcla autorizadas con las que no lo están.
    --}}
    <div x-data="{ sel: [], todas: @js($fotos->pluck('id')->map(fn ($id) => (string) $id)->values()) }">
    <form method="GET" action="{{ route('admin.evaluaciones.fotos.zip') }}" class="eval-seleccion" data-barra-seleccion>
        @foreach (request()->except(['page', 'fotos']) + ['estado' => $cual] as $clave => $valor)
            @if (is_scalar($valor))
                <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
            @endif
        @endforeach
        <template x-for="id in sel" :key="id"><input type="hidden" name="fotos[]" :value="id"></template>

        <label class="eval-seleccion-todas">
            <input type="checkbox" data-seleccionar-todas
                   x-bind:checked="sel.length === todas.length"
                   x-on:change="sel = $event.target.checked ? [...todas] : []">
            Seleccionar todas las de esta página
        </label>
        <span class="helper" x-text="sel.length ? (sel.length === 1 ? '1 seleccionada' : sel.length + ' seleccionadas') : 'Marca las que quieras bajar'"></span>
        <button type="submit" class="btn btn-primary btn-sm" x-bind:disabled="! sel.length" data-descargar-seleccion>
            Descargar seleccionadas
        </button>
    </form>

    <section class="eval-fotos">
        @foreach ($fotos as $foto)
            @php $e = $foto->evaluation; @endphp
            <article class="card eval-foto" x-bind:class="sel.includes('{{ $foto->id }}') && 'eval-foto--marcada'">
                <label class="eval-foto-marca" title="Seleccionar esta fotografía">
                    <input type="checkbox" value="{{ $foto->id }}" x-model="sel" data-marcar-foto
                           aria-label="Seleccionar la fotografía #{{ $foto->id }}">
                </label>
                <a href="{{ route('admin.evaluaciones.foto', $foto) }}" target="_blank" rel="noopener" class="eval-foto-marco">
                    {{-- `loading="lazy"`: una cuadrícula de veinticuatro fotos son
                         veinticuatro peticiones al disco privado, y las de abajo
                         no hacen falta hasta que se llega a ellas. --}}
                    <img src="{{ route('admin.evaluaciones.foto', $foto) }}" alt="Fotografía enviada por {{ $e?->nombre }}" loading="lazy">
                </a>

                <div class="eval-foto-datos">
                    <p class="eval-foto-actividad">{{ Str::limit($e?->activity?->titulo ?? '(actividad borrada)', 40) }}</p>
                    <p class="helper">
                        {{ $e?->nombre }} · {{ \App\Support\Fecha::corta($foto->created_at) }}
                        <span class="col-id" style="width:auto;" title="Identificador de la fotografía">#{{ $foto->id }}</span>
                    </p>

                    @if ($cual === 'autorizadas')
                        <form method="POST" action="{{ route('admin.evaluaciones.biblioteca', $foto) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm" data-cargando="Copiando…">Pasar a la biblioteca</button>
                        </form>
                    @endif

                    {{-- Punto 9: bajar las de su actividad sin pasar por el filtro. --}}
                    @if ($e?->activity_id && ! $filtros['actividad'])
                        <a class="textlink eval-foto-actividad-zip" data-zip-actividad
                           href="{{ route('admin.evaluaciones.fotos.zip', ['estado' => $cual, 'actividad' => $e->activity_id]) }}">Descargar las de esta actividad</a>
                    @endif
                </div>
            </article>
        @endforeach
    </section>
    </div>

    <div style="margin-top:20px;">{{ $fotos->links() }}</div>
@endif
@endsection
