@extends('layouts.admin')
@section('miga', \Illuminate\Support\Str::limit($email->subject ?: 'Correo', 40))
@section('title', 'Detalle del correo')

@section('content')
<a href="{{ route('admin.emails.index') }}" class="textlink" style="font-size:14px;">← Volver al log</a>

<section class="card" style="padding:26px;margin-top:18px;">
    <dl style="display:grid;grid-template-columns:130px 1fr;gap:9px 16px;font-size:14px;margin:0 0 22px;">
        <dt class="helper" style="font-weight:700;">Estado</dt>
        <dd style="margin:0;">
            <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:{{ $email->status_fondo }};color:{{ $email->status_color }};">
                {{ $email->status_label }}
            </span>
        </dd>

        {{-- Qué transporte lo llevó. Es el dato que faltaba para distinguir un
             envío real de uno que se quedó escrito en el servidor. --}}
        @if ($email->transporte)
            <dt class="helper" style="font-weight:700;">Salió por</dt><dd style="margin:0;"><code>{{ $email->transporte }}</code></dd>
        @endif

        <dt class="helper" style="font-weight:700;">Para</dt><dd style="margin:0;">{{ $email->to }}</dd>
        @if ($email->cc)
            <dt class="helper" style="font-weight:700;">CC</dt><dd style="margin:0;">{{ $email->cc }}</dd>
        @endif
        <dt class="helper" style="font-weight:700;">Asunto</dt><dd style="margin:0;">{{ $email->subject }}</dd>
        <dt class="helper" style="font-weight:700;">Creado</dt><dd style="margin:0;">{{ \App\Support\Fecha::larga($email->created_at) }}</dd>
        @if ($email->encolado_at)
            <dt class="helper" style="font-weight:700;">Encolado</dt><dd style="margin:0;">{{ \App\Support\Fecha::larga($email->encolado_at) }}</dd>
        @endif
        @if ($email->sent_at)
            <dt class="helper" style="font-weight:700;">{{ $email->entregado ? 'Enviado' : 'Procesado' }}</dt><dd style="margin:0;">{{ \App\Support\Fecha::larga($email->sent_at) }}</dd>
        @endif
        @if ($email->reenviado_at)
            <dt class="helper" style="font-weight:700;">Reenviado</dt><dd style="margin:0;">{{ \App\Support\Fecha::larga($email->reenviado_at) }}</dd>
        @endif
        @if ($email->plantilla)
            <dt class="helper" style="font-weight:700;">Plantilla</dt><dd style="margin:0;">{{ $email->plantilla }}</dd>
        @endif
        @if ($email->adjuntos)
            <dt class="helper" style="font-weight:700;">Adjuntos</dt><dd style="margin:0;">{{ implode(', ', $email->adjuntos) }}</dd>
        @endif
        <dt class="helper" style="font-weight:700;">Intentos</dt><dd style="margin:0;">{{ $email->attempts }}</dd>
    </dl>

    {{-- El reenvío manda el HTML registrado, así que necesita que lo haya. Una
         fila `en_cola` todavía no tiene cuerpo: el correo se compone al
         enviarlo, y reenviarla mandaría un mensaje vacío. --}}
    @if (filled($email->body_html))
        <form method="POST" action="{{ route('admin.emails.resend', $email) }}" style="margin-bottom:22px;"
              onsubmit="return confirm('¿Reenviar este correo a {{ $email->to }}?');">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">Reenviar ahora</button>
            <span class="helper" style="margin-left:10px;">
                Se reenvía el contenido tal como quedó registrado, sin pasar por la cola.
            </span>
        </form>
    @else
        <div class="alert alert-info" style="margin-bottom:22px;font-size:13px;">
            Este correo todavía no se ha compuesto, así que no hay nada que reenviar.
            Sigue esperando en la cola: en cuanto el worker lo procese aparecerá aquí su contenido.
        </div>
    @endif

    {{-- `motivo` explica en castellano por qué una fila no cuenta como
         entregada; `error` es el texto crudo del servidor, que sólo aporta
         cuando dice algo distinto. --}}
    @if (! $email->entregado && $email->motivo)
        <div class="alert alert-error" style="margin-bottom:22px;">
            <strong>Por qué no llegó</strong>
            <p style="margin:8px 0 0;font-size:13.5px;line-height:1.55;">{{ $email->motivo }}</p>
            @if ($email->error && $email->error !== $email->motivo)
                <pre style="margin:10px 0 0;font-size:12.5px;white-space:pre-wrap;">{{ $email->error }}</pre>
            @endif
        </div>
    @elseif ($email->error)
        <div class="alert alert-error" style="margin-bottom:22px;">
            <strong>Error del servidor</strong>
            <pre style="margin:8px 0 0;font-size:12.5px;white-space:pre-wrap;">{{ $email->error }}</pre>
        </div>
    @endif

    <div class="seclabel" style="margin-bottom:10px;">Contenido</div>
    <div style="border:1px solid var(--linea);border-radius:14px;overflow:hidden;background:#fff;">
        <iframe title="Vista previa del correo" style="width:100%;height:520px;border:0;display:block;"
                sandbox srcdoc="{{ $email->body_html }}"></iframe>
    </div>
</section>
@endsection
