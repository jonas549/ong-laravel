@extends('layouts.admin')
@section('title', 'Organizaciones')

@section('content')
<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;max-width:400px;">
    <input class="fld" type="search" name="q" value="{{ \App\Support\Filtro::texto(request(), 'q') }}" placeholder="Buscar organización…">
    <button type="submit" class="btn btn-outline btn-sm">Buscar</button>
</form>

<div class="tabla-wrap">
    <table class="tabla">
        <thead><tr><th>Organización</th><th>Tipo</th><th>Contacto</th><th class="num">Actividades</th><th>Verificada</th><th></th></tr></thead>
        <tbody>
            @forelse ($organizaciones as $o)
                <tr>
                    <td style="font-weight:600;">{{ $o->nombre }}</td>
                    <td>{{ $o->tipo_label }}</td>
                    <td>{{ $o->user?->email }}</td>
                    <td class="num">{{ $o->activities_count }}</td>
                    <td>{{ $o->verificada ? 'Sí' : 'No' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.organizations.verify', $o) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm">{{ $o->verificada ? 'Quitar' : 'Verificar' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="color:var(--gris);">Sin organizaciones.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:22px;">{{ $organizaciones->links() }}</div>
@endsection
