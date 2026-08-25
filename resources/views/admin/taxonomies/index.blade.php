@extends('layouts.admin')
@section('title', 'Catálogos')

@section('content')
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px;">
    @foreach ($grupos as $clave => $label)
        <a class="tab {{ $grupo === $clave ? 'on' : '' }}" href="{{ route('admin.taxonomies.index', ['grupo' => $clave]) }}">{{ $label }}</a>
    @endforeach
</div>

@if ($limite)
    <div class="alert alert-info" style="margin-bottom:20px;">
        Una actividad puede elegir hasta <strong>{{ $limite }}</strong> {{ Str::lower($grupos[$grupo]) }}.
    </div>
@endif

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:22px;align-items:start;">
    <section style="min-width:0;" class="tabla-wrap">
        <table class="tabla">
            <thead>
                <tr><th>Término</th><th class="num">Orden</th><th class="num">En uso</th><th>Visible</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($terminos as $t)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('admin.taxonomies.update', $t) }}" style="display:flex;gap:8px;align-items:center;">
                                @csrf
                                @method('PUT')
                                <input class="fld" style="padding:7px 10px;font-size:13.5px;" type="text" name="nombre"
                                       value="{{ $t->nombre }}" aria-label="Nombre del término">
                                <input type="hidden" name="orden" value="{{ $t->orden }}">
                                <input type="hidden" name="activo" value="{{ $t->activo ? 1 : 0 }}">
                                <button type="submit" class="btn btn-outline btn-sm">Guardar</button>
                            </form>
                        </td>
                        <td class="num">{{ $t->orden }}</td>
                        <td class="num">{{ $t->activities_count }}</td>
                        <td>{{ $t->activo ? 'Sí' : 'No' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.taxonomies.destroy', $t) }}"
                                  onsubmit="return confirm('¿Eliminar este término?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Borrar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="color:var(--gris);">Sin términos en este grupo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <aside class="card" style="padding:24px;">
        <h2 style="font-size:16px;font-weight:700;margin:0 0 16px;">Agregar término</h2>
        <form method="POST" action="{{ route('admin.taxonomies.store') }}" style="display:flex;flex-direction:column;gap:14px;">
            @csrf
            <input type="hidden" name="grupo" value="{{ $grupo }}">
            <div>
                <label class="helper" for="t-nombre" style="display:block;margin-bottom:6px;font-weight:600;">Nombre</label>
                <input class="fld @error('nombre') is-invalid @enderror" type="text" id="t-nombre" name="nombre" value="@viejo('nombre')" required>
                @error('nombre') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="helper" for="t-orden" style="display:block;margin-bottom:6px;font-weight:600;">Orden</label>
                <input class="fld" type="number" min="0" id="t-orden" name="orden" value="{{ $terminos->count() + 1 }}">
            </div>
            <button type="submit" class="btn btn-primary" style="justify-content:center;">Agregar</button>
        </form>
    </aside>
</div>
@endsection
