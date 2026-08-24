<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        Tu actividad ya está publicada
    </h1>

    <p style="margin:0 0 14px;">
        <strong>{{ $actividad->titulo }}</strong> ya es parte del Día del Patrimonio Social y aparece en el calendario público.
    </p>

    <p style="margin:0 0 14px;">
        {{ $actividad->fecha_larga }} · {{ $actividad->lugar }}
    </p>

    <p style="margin:22px 0 0;">
        <a href="{{ route('activities.show', $actividad) }}"
           style="display:inline-block;background:#e57200;color:#ffffff;font-weight:600;font-size:14px;padding:12px 22px;border-radius:999px;text-decoration:none;">
            Ver la actividad publicada
        </a>
    </p>
</x-mail-layout>
