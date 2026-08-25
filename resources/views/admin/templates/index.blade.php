@extends('layouts.admin')
@section('title', 'Plantillas de correo')

@section('content')
<p class="helper" style="margin:0 0 22px;max-width:70ch;">
    Estos son los correos que el sistema envía solo. Puedes cambiar el asunto y el texto de cada uno,
    o desactivarlo si no quieres que se envíe.
</p>

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
                <tr><td colspan="5" style="color:var(--gris);">No hay plantillas. Corre <code>php artisan db:seed --class=EmailTemplateSeeder</code>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
