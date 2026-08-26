@extends('layouts.admin')
@section('title', 'Plantillas de correo')

@section('actions')
    <a class="btn btn-outline btn-sm" href="{{ route('admin.emails.index') }}">Registro de correos</a>
@endsection

@section('content')
<p class="helper" style="margin:0 0 22px;max-width:70ch;">
    Estos son los correos que el sistema envía solo. Puedes cambiar el asunto y el texto de cada uno,
    o desactivarlo si no quieres que se envíe. La lista es fija: el sistema pide cada plantilla por su
    nombre en el momento que le toca, así que no se crean ni se borran, sólo se editan.
</p>

{{--
    Que falte una plantilla es un fallo mudo: el correo que la usa no se envía y
    tampoco deja fila en el registro. Antes esta pantalla sólo enseñaba una
    tabla vacía, y desde el panel no había forma de arreglarlo.
--}}
@if ($faltan)
    <div class="alert alert-error" style="margin-bottom:20px;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:280px;">
            <strong style="display:block;margin-bottom:4px;">
                {{ count($faltan) === 1 ? 'Falta una plantilla' : 'Faltan '.count($faltan).' plantillas' }}.
            </strong>
            <span style="font-size:13px;line-height:1.55;">
                No están en la base de datos, así que estos correos no se envían y no queda constancia:
                <strong>{{ collect($faltan)->pluck('nombre')->implode(', ') }}</strong>.
                Al restaurarlas se crean sólo las que faltan; las que ya hayas editado no se tocan.
            </span>
        </div>
        <form method="POST" action="{{ route('admin.templates.restore') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Restaurar las que faltan</button>
        </form>
    </div>
@endif

<div class="tabla-wrap">
    <table class="tabla">
        <thead>
            <tr><th>Plantilla</th><th>Cuándo se envía</th><th>Asunto</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($plantillas as $p)
                <tr>
                    <td style="font-weight:600;">{{ $p->nombre }}</td>
                    <td style="color:var(--gris);">{{ $p->descripcion }}</td>
                    <td>{{ Str::limit($p->asunto, 40) }}</td>
                    <td>
                        <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;white-space:nowrap;background:{{ $p->activo ? '#eaf6f5' : '#f1f2f3' }};color:{{ $p->activo ? '#0d6b64' : 'var(--gris)' }};">
                            {{ $p->activo ? 'Activa' : 'Desactivada' }}
                        </span>
                    </td>
                    <td><a class="btn btn-outline btn-sm" href="{{ route('admin.templates.edit', $p) }}">Editar</a></td>
                </tr>
            @empty
                @unless ($faltan)
                    <tr><td colspan="5" style="color:var(--gris);">No hay plantillas.</td></tr>
                @endunless
            @endforelse

            {{-- Las que faltan se listan también, en gris: así se ve de un
                 vistazo qué correo concreto no se está enviando. --}}
            @foreach ($faltan as $meta)
                <tr style="opacity:.62;">
                    <td style="font-weight:600;">{{ $meta['nombre'] }}</td>
                    <td style="color:var(--gris);">{{ $meta['descripcion'] }}</td>
                    <td style="color:var(--gris);">—</td>
                    <td>
                        <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;white-space:nowrap;background:#fdecef;color:#a8324a;">
                            No existe
                        </span>
                    </td>
                    <td style="color:var(--gris);font-size:13px;">Sin restaurar</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
