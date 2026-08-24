<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        Tu actividad fue cancelada
    </h1>

    <p style="margin:0 0 14px;">
        <strong>{{ $actividad->titulo }}</strong> ya no aparece en el calendario y dejó de recibir inscripciones.
    </p>

    @if ($actividad->observaciones_revision)
        <div style="background:#f1f2f3;border-left:3px solid #63666a;border-radius:0 10px 10px 0;padding:14px 18px;margin:0 0 18px;font-size:14.5px;color:#4a4d51;">
            {{ $actividad->observaciones_revision }}
        </div>
    @endif

    <p style="margin:0;">
        Si crees que fue un error, responde este correo y lo revisamos.
    </p>
</x-mail-layout>
