@extends('layouts.admin')
@section('title', 'Editar organización')
@section('miga', Str::limit($organizacion->nombre, 32))

{{--
    Ficha de una organización.

    Aquí se corrigen los datos que la ONG puede necesitar arreglar —un nombre mal
    escrito, un correo de contacto, un tipo equivocado en el wizard— y, desde
    las varias cuentas por organización, se elige su cuenta principal y se saca
    a una cuenta de ella. Cada actividad sigue siendo de quien la creó: cambiar
    la principal no mueve actividades de una persona a otra.
--}}

@section('content')
<a href="{{ route('admin.organizations.index') }}" class="textlink" style="font-size:14px;">← Volver a organizaciones</a>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:18px;align-items:start;">

    <section class="card" style="padding:26px;grid-column:span 2;min-width:0;">
        <form method="POST" action="{{ route('admin.organizations.update', $organizacion) }}"
              style="display:flex;flex-direction:column;gap:18px;">
            @csrf
            @method('PUT')

            @include('admin.organizations._campos', ['tipoObligatorio' => true])

            {{--
                El interruptor por organización de la aprobación automática.

                Es el que sirve de verdad cuando llega spam: el general apaga la
                comodidad para todas, y éste sólo para quien haga falta. La
                casilla habla en positivo —«revisar siempre»— porque es lo que
                se va a buscar cuando alguien esté dando problemas.
            --}}
            <div style="margin:4px 0 20px;padding:16px 18px;border:1.5px solid var(--linea);border-radius:16px;background:#fdfcfb;">
                {{-- `x-panel.campo` con tipo bool, no `x-panel.casilla`: aquélla es
                     la casilla de una fila de tabla, con su `ids[]` y su form
                     de fuera, y aquí revienta por no recibir un $id. --}}
                <x-panel.campo nombre="requiere_revision" tipo="bool"
                               label="Revisar siempre sus actividades a mano"
                               :valor="$organizacion->requiere_revision" />
                <p class="helper" style="margin:8px 0 0 27px;">
                    @if (\App\Models\Setting::get('aprobacion_automatica', true))
                        Por defecto, una organización que ya publicó alguna vez sube sus
                        actividades sin pasar por revisión. Marca esto para que las suyas
                        pasen siempre.
                    @else
                        Ahora mismo da igual: la aprobación automática está apagada para
                        todo el sitio en Configuración, así que ya se revisan todas.
                    @endif
                </p>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" data-cargando="Guardando…">Guardar cambios</button>
                <a href="{{ route('admin.organizations.index') }}" class="btn btn-ghost">Cancelar</a>
            </div>
        </form>

        {{--
            Tanda del 09/10: la cuenta de acceso también desde aquí, para las
            que no tienen ninguna (casi todas las importadas). Mismos campos y
            reglas que en «Nueva organización» (`CuentaDeAcceso`); queda como
            su cuenta principal. Formulario aparte: guardarla no toca la ficha.
        --}}
        @if ($organizacion->cuentas->isEmpty())
            <form method="POST" action="{{ route('admin.organizations.crear-cuenta', $organizacion) }}" id="crear-cuenta"
                  data-crear-cuenta-organizacion
                  x-data="cuentaDeOrganizacion({ crearCuenta: true, rutaCorreo: @js(route('publish.correo')) })"
                  style="margin-top:26px;padding-top:22px;border-top:1px solid var(--linea);display:flex;flex-direction:column;gap:14px;">
                @csrf
                <div>
                    <h2 style="font-size:16px;font-weight:700;margin:0 0 4px;">Crear su cuenta de acceso</h2>
                    <p class="helper" style="margin:0;">Esta organización no tiene cuenta. La que crees aquí queda como su cuenta principal: puede entrar y publicar sin reclamarla. Compártele la contraseña por un canal seguro.</p>
                </div>
                <div>
                    <label class="helper" for="c-name" style="display:block;margin-bottom:6px;font-weight:600;">Nombre de la persona</label>
                    <input class="fld @error('name') is-invalid @enderror" type="text" id="c-name" name="name" value="@viejo('name')" required autocomplete="off">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="helper" for="c-email" style="display:block;margin-bottom:6px;font-weight:600;">Correo de acceso</label>
                    <input class="fld @error('email') is-invalid @enderror" type="email" id="c-email" name="email" value="@viejo('email')" required autocomplete="off"
                           x-on:blur="comprobarCorreo($event.target.value)"
                           x-on:input="if ($event.target.value.trim() !== correoComprobado) correoExiste = false">
                    <span class="field-error" x-show="correoExiste" x-cloak data-correo-existe>{{ \App\Support\CuentaDeAcceso::CORREO_REPETIDO }}</span>
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="helper" for="c-password" style="display:block;margin-bottom:6px;font-weight:600;">Contraseña</label>
                    <input class="fld @error('password') is-invalid @enderror" type="password" id="c-password" name="password" required autocomplete="new-password">
                    <span class="helper">Mínimo 8 caracteres.</span>
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" data-cargando="Creando…">Crear cuenta</button>
                </div>
            </form>
        @endif
    </section>

    <aside class="card" style="padding:22px 24px;">
        <div class="seclabel" style="margin-bottom:14px;">Qué cuelga de aquí</div>

        <dl style="display:flex;flex-direction:column;gap:12px;margin:0;font-size:14px;">
            {{-- Punto 2 del 08/10: la cuenta con su nombre y su alta, y los
                 correos de contacto usados en la ficha y en sus actividades. --}}
            {{-- Varias cuentas por organización: la principal primero, y las
                 demás con desde cuándo están y cuántas actividades crearon. La
                 principal recibe el aviso cuando alguien se suma y es la única
                 que edita la ficha. --}}
            <div data-cuenta-ficha>
                <dt class="helper">Cuenta principal</dt>
                @if ($organizacion->user && $organizacion->user->organization_id === $organizacion->id)
                    <dd style="margin:0;">
                        {{ $organizacion->user->name }}<br>
                        {{ $organizacion->user->email }}<br>
                        <span class="helper">Alta: {{ \App\Support\Fecha::corta($organizacion->user->created_at) }}</span>
                        @unless ($organizacion->user->is_active)
                            <br><span class="field-error" data-principal-inactiva>Está desactivada: elige otra cuenta como principal.</span>
                        @endunless
                    </dd>
                @elseif ($organizacion->user_id)
                    <dd style="margin:0;" class="field-error" data-sin-principal>
                        @if ($organizacion->cuentas->isEmpty())
                            La cuenta principal ya no existe o salió de la organización, y no le queda ninguna. <a class="textlink" href="#crear-cuenta">Crea una</a>.
                        @else
                            La cuenta principal ya no existe o salió de la organización. Elige otra entre las de abajo.
                        @endif
                    </dd>
                @else
                    <dd style="margin:0;">Sin cuenta · <a class="textlink" href="#crear-cuenta">crearla</a></dd>
                @endif
            </div>
            @php
                $otras = $organizacion->cuentas->reject(fn ($c) => $c->id === $organizacion->user_id);
            @endphp
            @if ($otras->isNotEmpty())
                <div data-cuentas-ficha>
                    <dt class="helper">Otras cuentas ({{ $otras->count() }})</dt>
                    @foreach ($otras as $c)
                        <dd style="margin:0 0 12px;" data-cuenta="{{ $c->id }}">
                            {{ $c->name }}@unless ($c->is_active) <span class="insignia insignia-no">Inactiva</span>@endunless<br>
                            <a class="textlink" href="{{ route('admin.users.edit', [$c, 'rol' => $c->role]) }}">{{ $c->email }}</a><br>
                            <span class="helper">
                                Desde {{ \App\Support\Fecha::corta($c->organizacion_desde ?? $c->created_at) }}
                                · {{ \App\Support\Texto::cuantos($c->activities_count, 'actividad') }}
                            </span>
                            <span style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
                                @if ($c->is_active)
                                    <form method="POST" action="{{ route('admin.organizations.principal', [$organizacion, $c]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-sm" data-hacer-principal>Hacer principal</button>
                                    </form>
                                @endif
                                <x-panel.confirmar
                                    :accion="route('admin.organizations.quitar-cuenta', [$organizacion, $c])"
                                    :titulo="'Sacar a '.$c->email.' de la organización'"
                                    texto="La cuenta no se borra: se queda sin organización y puedes volver a asignarle una desde Usuarios. Sus actividades se quedan en la organización y pasan a la cuenta principal."
                                    confirmar="Sí, sacarla"
                                    boton="Sacar de la organización"
                                    clase="btn btn-ghost btn-sm" />
                            </span>
                        </dd>
                    @endforeach
                </div>
            @endif
            <div data-correos-ficha>
                <dt class="helper">Otros correos de contacto usados</dt>
                @forelse ($organizacion->correosDeContacto() as $correo)
                    <dd style="margin:0;">{{ $correo }}</dd>
                @empty
                    <dd style="margin:0;">—</dd>
                @endforelse
            </div>
            <div>
                <dt class="helper">Actividades</dt>
                <dd style="margin:0;font-weight:700;">{{ $organizacion->activities_count }}</dd>
            </div>
            <div>
                <dt class="helper">Inscripciones en esas actividades</dt>
                <dd style="margin:0;font-weight:700;">{{ $organizacion->registrations_count }}</dd>
            </div>
            <div>
                <dt class="helper">Organización creada</dt>
                <dd style="margin:0;">{{ \App\Support\Fecha::corta($organizacion->created_at) }}</dd>
            </div>
        </dl>

        <div style="border-top:1px solid var(--linea);margin-top:18px;padding-top:16px;display:flex;flex-direction:column;gap:8px;">
            <form method="POST" action="{{ route('admin.organizations.alternar', $organizacion) }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">
                    {{ $organizacion->activo ? 'Desactivar' : 'Activar' }}
                </button>
            </form>

            @if ($organizacion->user_id || $organizacion->cuentas->isNotEmpty())
                {{-- Con cuentas no se borra: se quedarían sin organización. --}}
                <p class="helper" style="margin:0;" data-no-eliminar-con-cuenta>
                    <strong>No se puede eliminar.</strong>
                    @if ($organizacion->cuentas->count() > 1)
                        Tiene {{ $organizacion->cuentas->count() }} cuentas, que se quedarían sin ella.
                    @else
                        Es la organización de la cuenta {{ $organizacion->user?->email }}, que se quedaría sin ella.
                    @endif
                    Desactivarla la esconde sin borrar nada.
                </p>
            @elseif ($organizacion->activities_count === 0)
                <x-panel.confirmar
                    :accion="route('admin.organizations.destroy', $organizacion)"
                    :titulo="'Eliminar «'.Str::limit($organizacion->nombre, 40).'»'"
                    texto="No tiene ninguna actividad, así que no arrastra nada. Se puede recuperar con el filtro de la papelera."
                    confirmar="Sí, eliminar"
                    boton="Eliminar organización"
                    clase="btn btn-danger btn-sm" />
            @else
                <p class="helper" style="margin:0;">
                    <strong>No se puede eliminar.</strong>
                    Tiene {{ \App\Support\Texto::cuantos($organizacion->activities_count, 'actividad') }}
                    y {{ \App\Support\Texto::cuantos($organizacion->registrations_count, 'inscripción') }} colgando de ellas.
                    Desactivarla la esconde sin borrar nada.
                </p>
            @endif
        </div>
    </aside>
</div>
@endsection
