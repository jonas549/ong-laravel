<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        Una actividad volvió corregida
    </h1>

    <p style="margin:0 0 14px;">
        <strong>{{ $actividad->organization?->nombre ?? 'Una organización' }}</strong> resolvió las observaciones de
        <strong>{{ $actividad->titulo }}</strong> (ID #{{ $actividad->id }}) y la actividad volvió a revisión.
    </p>

    @if (filled($mensaje))
        {{-- El mensaje del organizador va dentro del aviso y no sólo en el panel:
             si hubiera que entrar a leerlo, el correo no ahorraría el viaje. --}}
        <p style="margin:0 0 8px;font-size:13px;color:#7a7d82;">Lo que nos cuenta:</p>
        <div style="background:#fff8e6;border-left:3px solid #fab600;border-radius:0 10px 10px 0;padding:14px 18px;margin:0 0 18px;font-size:14.5px;color:#8a6a00;">
            {{ $mensaje }}
        </div>
    @else
        <p style="margin:0 0 18px;font-size:14px;color:#7a7d82;">No dejó ningún mensaje.</p>
    @endif

    @if ($actividad->observaciones_revision)
        <p style="margin:0 0 8px;font-size:13px;color:#7a7d82;">Lo que le habíamos pedido:</p>
        <div style="background:#f6f6f7;border-left:3px solid #c3c6ca;border-radius:0 10px 10px 0;padding:14px 18px;margin:0 0 18px;font-size:14.5px;color:#63666a;">
            {{ $actividad->observaciones_revision }}
        </div>
    @endif

    <p style="margin:22px 0 0;">
        <a href="{{ route('admin.activities.show', $actividad) }}"
           style="display:inline-block;background:#e57200;color:#ffffff;font-weight:600;font-size:14px;padding:12px 22px;border-radius:999px;text-decoration:none;">
            Revisarla ahora
        </a>
    </p>
</x-mail-layout>
