@extends('layouts.admin')
@section('title', 'Revisar actividad')
@section('miga', \Illuminate\Support\Str::limit($activity->titulo, 40))

@section('content')
@php $t = $activity->estado_color; @endphp

<a href="{{ route('admin.activities.index') }}" class="textlink" style="font-size:14px;">← Volver al listado</a>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:22px;margin-top:18px;align-items:start;">

    <section class="card" style="padding:26px;">
        <span style="display:inline-block;font-size:12.5px;font-weight:600;padding:5px 12px;border-radius:999px;margin-bottom:14px;background:{{ $t['bg'] }};color:{{ $t['ink'] }};border:1px solid {{ $t['borde'] }};">
            {{ $activity->estado_label }}
        </span>

        <h2 style="font-size:24px;font-weight:800;margin:0 0 14px;letter-spacing:-.01em;">{{ $activity->titulo }}</h2>
        <p style="font-size:15px;line-height:1.7;color:var(--gris-700);white-space:pre-line;margin:0 0 22px;">{{ $activity->descripcion }}</p>

        <dl style="display:grid;grid-template-columns:150px 1fr;gap:9px 16px;font-size:14px;margin:0;">
            <dt class="helper" style="font-weight:700;">Organización</dt>
            <dd style="margin:0;">{{ $activity->organization?->nombre }}</dd>

            <dt class="helper" style="font-weight:700;">Tipo</dt>
            <dd style="margin:0;">{{ $activity->organization?->tipo_label }}</dd>

            <dt class="helper" style="font-weight:700;">Contacto</dt>
            <dd style="margin:0;">{{ $activity->organization?->user?->email }}</dd>

            <dt class="helper" style="font-weight:700;">Fecha</dt>
            <dd style="margin:0;">{{ $activity->fecha_larga }}</dd>

            <dt class="helper" style="font-weight:700;">Lugar</dt>
            <dd style="margin:0;">{{ $activity->lugar }}{{ $activity->direccion ? ' · ' . $activity->direccion : '' }}</dd>

            <dt class="helper" style="font-weight:700;">Formato</dt>
            <dd style="margin:0;">{{ $activity->formato }}</dd>

            <dt class="helper" style="font-weight:700;">Cupos</dt>
            <dd style="margin:0;">{{ $activity->cupos_disponibles ?? '—' }} de {{ $activity->cupos_totales ?? '—' }}</dd>
        </dl>

        @foreach (['tema' => 'Temas', 'caracteristica' => 'Características', 'publico' => 'Público', 'acceso' => 'Accesibilidad'] as $g => $label)
            @php $items = $activity->termsDe($g); @endphp
            @if ($items->isNotEmpty())
                <div style="margin-top:16px;">
                    <div class="helper" style="font-weight:700;margin-bottom:6px;">{{ $label }}</div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach ($items as $x)
                            <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:var(--gris-100);color:var(--gris-700);">{{ $x->nombre }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        @if ($activity->collaborators->isNotEmpty())
            <div style="margin-top:16px;">
                <div class="helper" style="font-weight:700;margin-bottom:6px;">Colaboran</div>
                <p style="margin:0;font-size:14px;color:var(--gris-700);">{{ $activity->collaborators->pluck('nombre')->implode(' · ') }}</p>
            </div>
        @endif
    </section>

    <aside style="display:flex;flex-direction:column;gap:18px;">
        <section class="card" style="padding:24px;">
            <h3 style="font-size:16px;font-weight:700;margin:0 0 16px;">Moderación</h3>

            <div style="display:flex;flex-direction:column;gap:12px;">
                @if ($activity->estado !== 'publicada')
                    <form method="POST" action="{{ route('admin.activities.approve', $activity) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Publicar actividad</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.activities.changes', $activity) }}" style="display:flex;flex-direction:column;gap:9px;">
                    @csrf
                    <label class="helper" for="comentario" style="font-weight:600;">Pedir ajustes</label>
                    <textarea class="fld @error('comentario') is-invalid @enderror" id="comentario" name="comentario" rows="3"
                              placeholder="Explica qué falta o qué hay que corregir…">{{ \App\Support\Formulario::viejo('comentario') }}</textarea>
                    <span class="helper">El organizador recibe este texto tal cual, por correo.</span>
                    @error('comentario') <span class="field-error">{{ $message }}</span> @enderror
                    <button type="submit" class="btn btn-outline btn-sm">Enviar observaciones</button>
                </form>

                <form method="POST" action="{{ route('admin.activities.featured', $activity) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;">
                        {{ $activity->destacada ? 'Quitar del home' : 'Destacar en el home' }}
                    </button>
                </form>

                @if ($activity->estado !== 'cancelada')
                    <form method="POST" action="{{ route('admin.activities.reject', $activity) }}"
                          onsubmit="return confirm('¿Cancelar esta actividad?');">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm" style="width:100%;justify-content:center;">Cancelar actividad</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="card" style="padding:24px;">
            <h3 style="font-size:16px;font-weight:700;margin:0 0 14px;">Historial</h3>
            @forelse ($activity->statusLogs as $log)
                <div style="font-size:13.5px;color:var(--gris-700);padding:9px 0;border-bottom:1px solid var(--linea);">
                    <strong>{{ $log->de_estado ?? 'nueva' }} → {{ $log->a_estado }}</strong>
                    <div class="helper">{{ $log->user?->name ?? 'Sistema' }} · {{ $log->created_at->diffForHumans() }}</div>
                    @if ($log->comentario)
                        <div style="margin-top:5px;font-style:italic;">{{ $log->comentario }}</div>
                    @endif
                </div>
            @empty
                <p class="helper" style="margin:0;">Sin movimientos todavía.</p>
            @endforelse
        </section>
    </aside>
</div>
@endsection
