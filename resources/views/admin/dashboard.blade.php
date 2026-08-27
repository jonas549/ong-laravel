@extends('layouts.admin')
@section('title', 'Resumen')

{{--
    Portada del panel. Todo lo que se pinta aquí sale de una consulta: no hay un
    solo número escrito a mano ni un dato de ejemplo. Con la base vacía la
    pantalla enseña ceros, que es la verdad, y no una demo que engañe.

    Cada cifra lleva debajo qué cuenta exactamente. «Inscritos» y
    «organizaciones activas» admiten más de una lectura, y un número que no dice
    lo que mide es un número que nadie puede comprobar contra la base.
--}}

@section('content')

{{-- ------------------------------------------------------------- alertas --}}

@include('partials.admin.salud-correo')

@foreach ($alertas as $alerta)
    <div class="alert alert-{{ $alerta['nivel'] }}" style="margin-bottom:14px;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:260px;">
            <strong style="display:block;margin-bottom:4px;">{{ $alerta['titulo'] }}</strong>
            <span style="font-size:13px;line-height:1.55;">{{ $alerta['texto'] }}</span>
        </div>
        @if ($alerta['accion'])
            <a class="btn btn-outline btn-sm" href="{{ $alerta['accion'][1] }}">{{ $alerta['accion'][0] }}</a>
        @endif
    </div>
@endforeach

{{-- ---------------------------------------------------------------- KPIs --}}

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin:20px 0 26px;">
    <a class="kpi" href="{{ route('admin.activities.pendientes') }}">
        <span class="v" style="color:{{ $pendientes ? 'var(--naranjo)' : 'var(--gris-700)' }};">{{ $pendientes }}</span>
        <span class="l">esperando revisión</span>
    </a>

    <a class="kpi" href="{{ route('admin.activities.publicadas') }}">
        <span class="v" style="color:var(--teal);">{{ $publicadas }}</span>
        <span class="l">actividades publicadas</span>
        <span class="l" style="opacity:.75;">de {{ $actividades }} en total</span>
    </a>

    {{-- Decía «personas inscritas» y contaba inscripciones: una misma persona
         puede apuntarse a varias actividades. Ahora se dicen las dos cifras. --}}
    <a class="kpi" href="{{ route('admin.registrations.index', ['estado' => 'activas']) }}">
        <span class="v" style="color:var(--turquesa);">{{ $inscripciones }}</span>
        <span class="l">inscripciones</span>
        <span class="l" style="opacity:.75;">de {{ $personas }} {{ Str::plural('persona', $personas) }} · {{ $inscripcionesConfirmadas }} confirmadas · sin contar {{ $inscripcionesCanceladas }} canceladas</span>
    </a>

    <a class="kpi" href="{{ route('admin.organizations.index', ['filtro' => 'activas']) }}">
        <span class="v">{{ $organizacionesActivas }}</span>
        <span class="l">organizaciones activas</span>
        <span class="l" style="opacity:.75;">con actividad publicada, de {{ $organizaciones }} registradas</span>
    </a>

    <a class="kpi" href="{{ route('admin.organizations.verificacion') }}">
        <span class="v" style="color:{{ $organizacionesPorVerificar ? 'var(--amarillo)' : 'var(--gris-700)' }};">{{ $organizacionesVerificadas }}</span>
        <span class="l">organizaciones verificadas</span>
        <span class="l" style="opacity:.75;">{{ $organizacionesPorVerificar }} sin verificar</span>
    </a>
</div>

{{-- ------------------------------------------------------ accesos rápidos --}}

<section class="card" style="padding:18px 22px;margin-bottom:20px;">
    <div class="seclabel" style="margin-bottom:12px;">Accesos rápidos</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn btn-primary btn-sm" href="{{ route('admin.activities.pendientes') }}">Revisar actividades</a>
        <a class="btn btn-outline btn-sm" href="{{ route('admin.organizations.verificacion') }}">Verificar organizaciones</a>
        <a class="btn btn-outline btn-sm" href="{{ route('admin.registrations.exportar') }}">Exportar inscripciones</a>
        {{-- Con `?rol`: sin él la pantalla carga pero el menú no sabe qué nodo
             marcar y las migas se quedan a medias. --}}
        <a class="btn btn-outline btn-sm" href="{{ route('admin.users.index', ['rol' => 'organizer']) }}">Usuarios</a>
        <a class="btn btn-outline btn-sm" href="{{ route('admin.templates.index') }}">Plantillas de correo</a>
        <a class="btn btn-outline btn-sm" href="{{ route('admin.emails.index') }}">Registro de correos</a>
        <a class="btn btn-outline btn-sm" href="{{ route('admin.settings.general') }}">Configuración</a>
    </div>
</section>

{{-- ------------------------------------------------------------ evolución --}}

<section class="card" style="padding:22px 24px;margin-bottom:20px;min-width:0;">
    <h2 style="font-size:16px;font-weight:700;margin:0 0 4px;">Evolución por semana</h2>
    @include('partials.admin.grafico-semanas')
</section>

{{-- ------------------------------------------- estados + pendientes -------}}

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;margin-bottom:20px;">

    <section class="card" style="padding:22px 24px;">
        <h2 style="font-size:16px;font-weight:700;margin:0 0 16px;">Actividades por estado</h2>
        <div style="display:flex;flex-direction:column;gap:10px;">
            @foreach (\App\Models\Activity::ESTADOS as $clave => $meta)
                @php $n = $porEstado[$clave] ?? 0; @endphp
                <a href="{{ route('admin.activities.index', ['estado' => $clave]) }}"
                   style="display:flex;align-items:center;gap:12px;font-size:14px;color:var(--gris-700);">
                    <span style="width:9px;height:9px;border-radius:50%;background:{{ $meta['tono'] }};flex:none;"></span>
                    <span style="flex:1;">{{ $meta['filtro'] }}</span>
                    <strong style="font-variant-numeric:tabular-nums;">{{ $n }}</strong>
                </a>
            @endforeach

            <div style="border-top:1px solid var(--linea);margin-top:4px;padding-top:10px;display:flex;font-size:14px;">
                <span style="flex:1;color:var(--gris);">Total</span>
                <strong style="font-variant-numeric:tabular-nums;">{{ $actividades }}</strong>
            </div>
        </div>
    </section>

    <section class="card" style="padding:22px 24px;grid-column:span 2;min-width:0;">
        <div style="display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
            <h2 style="font-size:16px;font-weight:700;margin:0;flex:1;">Esperando revisión</h2>
            @if ($pendientes > $pendientesDeRevision->count())
                <a class="textlink" style="font-size:13px;" href="{{ route('admin.activities.pendientes') }}">
                    Ver las {{ $pendientes }}
                </a>
            @endif
        </div>

        <div class="tabla-wrap" style="border:0;">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Actividad</th>
                        <th>Organización</th>
                        <th class="num">Inscritos</th>
                        <th>Esperando</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendientesDeRevision as $a)
                        <tr>
                            <td><a class="textlink" href="{{ route('admin.activities.show', $a) }}">{{ Str::limit($a->titulo, 38) }}</a></td>
                            <td>{{ $a->organization?->nombre }}</td>
                            <td class="num">{{ $a->inscritos }}</td>
                            <td style="white-space:nowrap;">
                                {{-- El color avisa antes de que haya que leer el número --}}
                                <span style="color:{{ $a->dias_esperando >= 3 ? 'var(--rosa)' : 'var(--gris)' }};">
                                    {{ $a->esperando_desde->diffForHumans(null, true) }}
                                </span>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.activities.show', $a) }}">Revisar</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="color:var(--gris);">No hay nada esperando revisión.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

{{-- ------------------------------------------------- últimas inscripciones --}}

<section class="card" style="padding:22px 24px;min-width:0;">
    <div style="display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <h2 style="font-size:16px;font-weight:700;margin:0;flex:1;">Últimas inscripciones</h2>
        <a class="textlink" style="font-size:13px;" href="{{ route('admin.registrations.index') }}">Ver todas</a>
    </div>

    <div class="tabla-wrap" style="border:0;">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Actividad</th>
                    <th>Organización</th>
                    <th>Estado</th>
                    <th>Cuándo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ultimasInscripciones as $i)
                    @php $t = $i->estado_color; @endphp
                    <tr>
                        <td>
                            {{ $i->nombre }}
                            <span class="helper" style="display:block;">{{ $i->correo }}</span>
                        </td>
                        <td>
                            @if ($i->activity)
                                <a class="textlink" href="{{ route('admin.activities.show', $i->activity) }}">{{ Str::limit($i->activity->titulo, 34) }}</a>
                            @else
                                <span style="color:var(--gris);">—</span>
                            @endif
                        </td>
                        <td>{{ $i->activity?->organization?->nombre }}</td>
                        <td>
                            <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:{{ $t['bg'] }};color:{{ $t['ink'] }};">
                                {{ $i->estado_label }}
                            </span>
                        </td>
                        <td style="white-space:nowrap;">{{ $i->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="color:var(--gris);">Todavía no hay inscripciones.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<p class="helper" style="margin-top:26px;">
    El diseño definitivo del panel se define más adelante: por ahora esta pantalla prioriza que todo sea operable.
</p>
@endsection
