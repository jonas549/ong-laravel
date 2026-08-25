@extends('layouts.admin')
@section('title', $titulo)

@section('content')
{{--
    El editor de las secciones del home es el bloque F. Esta pantalla existe
    para que el árbol del panel esté completo y navegable, y para dejar claro
    de dónde sale hoy cada cosa: es la mitad del trabajo de ese bloque.
--}}
<div class="card" style="padding:26px;max-width:760px;">
    <p style="font-size:15.5px;line-height:1.6;color:var(--ink);margin:0 0 18px;">{{ $explicacion }}</p>

    <div class="seclabel" style="margin-bottom:6px;">De dónde salen los datos</div>
    <p class="helper" style="margin:0 0 20px;max-width:62ch;">
        {{ $origen }}
        @if (! is_null($cuantos))
            Ahora mismo hay <strong style="color:var(--ink);">{{ $cuantos }}</strong>
            {{ $cuantos === 1 ? 'registro' : 'registros' }}.
        @endif
    </p>

    @if ($tipo)
        <a href="{{ route('admin.content.index', ['tipo' => $tipo]) }}" class="btn btn-primary">Editar el contenido</a>
    @elseif ($ruta)
        <a href="{{ route($ruta) }}" class="btn btn-primary">{{ $enlace }}</a>
    @else
        <div class="alert alert-info" style="margin:0;">
            Esta sección todavía no se edita desde el panel: el texto está en la plantilla del home.
            El editor llega en el bloque F.
        </div>
    @endif
</div>

<div style="margin-top:20px;">
    <a class="textlink" href="{{ url('/') }}" target="_blank" rel="noopener"
       style="font-size:13.5px;font-weight:600;">Ver el home en el sitio ↗</a>
</div>
@endsection
