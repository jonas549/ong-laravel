@extends('layouts.admin')
@section('title', 'Detalle del correo')

@section('content')
<a href="{{ route('admin.emails.index') }}" class="textlink" style="font-size:14px;">← Volver al log</a>

<section class="card" style="padding:26px;margin-top:18px;">
    <dl style="display:grid;grid-template-columns:130px 1fr;gap:9px 16px;font-size:14px;margin:0 0 22px;">
        <dt class="helper" style="font-weight:700;">Estado</dt>
        <dd style="margin:0;">
            <span style="font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:{{ $email->status === 'sent' ? '#eaf6f5' : '#fdeaf0' }};color:{{ $email->status === 'sent' ? '#0d6b64' : '#a82249' }};">
                {{ $email->status_label }}
            </span>
        </dd>

        <dt class="helper" style="font-weight:700;">Para</dt><dd style="margin:0;">{{ $email->to }}</dd>
        @if ($email->cc)
            <dt class="helper" style="font-weight:700;">CC</dt><dd style="margin:0;">{{ $email->cc }}</dd>
        @endif
        <dt class="helper" style="font-weight:700;">Asunto</dt><dd style="margin:0;">{{ $email->subject }}</dd>
        <dt class="helper" style="font-weight:700;">Creado</dt><dd style="margin:0;">{{ $email->created_at->locale('es')->isoFormat('D [de] MMMM YYYY, HH:mm') }}</dd>
        @if ($email->sent_at)
            <dt class="helper" style="font-weight:700;">Enviado</dt><dd style="margin:0;">{{ $email->sent_at->locale('es')->isoFormat('D [de] MMMM YYYY, HH:mm') }}</dd>
        @endif
        @if ($email->reenviado_at)
            <dt class="helper" style="font-weight:700;">Reenviado</dt><dd style="margin:0;">{{ $email->reenviado_at->locale('es')->isoFormat('D [de] MMMM YYYY, HH:mm') }}</dd>
        @endif
        @if ($email->plantilla)
            <dt class="helper" style="font-weight:700;">Plantilla</dt><dd style="margin:0;">{{ $email->plantilla }}</dd>
        @endif
        @if ($email->adjuntos)
            <dt class="helper" style="font-weight:700;">Adjuntos</dt><dd style="margin:0;">{{ implode(', ', $email->adjuntos) }}</dd>
        @endif
        <dt class="helper" style="font-weight:700;">Intentos</dt><dd style="margin:0;">{{ $email->attempts }}</dd>
    </dl>

    <form method="POST" action="{{ route('admin.emails.resend', $email) }}" style="margin-bottom:22px;"
          onsubmit="return confirm('¿Reenviar este correo a {{ $email->to }}?');">
        @csrf
        <button type="submit" class="btn btn-outline btn-sm">Reenviar</button>
        <span class="helper" style="margin-left:10px;">Se reenvía el contenido tal como quedó registrado.</span>
    </form>

    @if ($email->error)
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
