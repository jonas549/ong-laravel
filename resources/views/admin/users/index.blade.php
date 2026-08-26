@extends('layouts.admin')
@section('title', 'Usuarios')

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:22px;align-items:start;">

    <section style="min-width:0;">
        <form method="GET" style="display:flex;gap:10px;margin-bottom:18px;max-width:360px;">
            <input class="fld" type="search" name="q" value="{{ \App\Support\Filtro::texto(request(), 'q') }}" placeholder="Buscar usuario…">
            <button type="submit" class="btn btn-outline btn-sm">Buscar</button>
        </form>

        <div class="tabla-wrap">
            <table class="tabla">
                <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($usuarios as $u)
                        <tr>
                            <td style="font-weight:600;">{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>{{ $u->esAdmin() ? 'Administración' : 'Organizador' }}</td>
                            <td>{{ $u->is_active ? 'Activo' : 'Inactivo' }}</td>
                            <td>
                                <div style="display:flex;gap:8px;white-space:nowrap;">
                                    {{-- El rol viaja en la URL para que el menú
                                         sepa bajo qué nodo está la ficha:
                                         "Administradores" y "Organizadores" son
                                         la misma ruta con distinto parámetro. --}}
                                    <a class="btn btn-outline btn-sm" href="{{ route('admin.users.edit', [$u, 'rol' => $u->role]) }}">Editar</a>
                                    <form method="POST" action="{{ route('admin.users.toggle', $u) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm">{{ $u->is_active ? 'Desactivar' : 'Activar' }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="color:var(--gris);">Sin usuarios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:22px;">{{ $usuarios->links() }}</div>
    </section>

    <aside class="card" style="padding:24px;">
        <h2 style="font-size:16px;font-weight:700;margin:0 0 16px;">Nuevo usuario</h2>

        <form method="POST" action="{{ route('admin.users.store') }}" style="display:flex;flex-direction:column;gap:14px;">
            @csrf
            <div>
                <label class="helper" for="u-name" style="display:block;margin-bottom:6px;font-weight:600;">Nombre</label>
                <input class="fld @error('name') is-invalid @enderror" type="text" id="u-name" name="name" value="@viejo('name')" required>
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="helper" for="u-email" style="display:block;margin-bottom:6px;font-weight:600;">Correo</label>
                <input class="fld @error('email') is-invalid @enderror" type="email" id="u-email" name="email" value="@viejo('email')" required>
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="helper" for="u-password" style="display:block;margin-bottom:6px;font-weight:600;">Contraseña</label>
                <input class="fld @error('password') is-invalid @enderror" type="password" id="u-password" name="password" required autocomplete="new-password">
                <span class="helper">Mínimo 8 caracteres.</span>
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="helper" for="u-role" style="display:block;margin-bottom:6px;font-weight:600;">Rol</label>
                <select class="fld" id="u-role" name="role">
                    <option value="organizer">Organizador</option>
                    <option value="admin">Administración</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="justify-content:center;">Crear usuario</button>
        </form>
    </aside>
</div>
@endsection
