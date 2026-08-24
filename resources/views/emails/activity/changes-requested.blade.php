<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        Necesitamos algunos ajustes
    </h1>

    <p style="margin:0 0 14px;">
        Revisamos <strong>{{ $actividad->titulo }}</strong> y hay un par de cosas por corregir antes de publicarla.
    </p>

    @if ($actividad->observaciones_revision)
        <div style="background:#fdeaf0;border-left:3px solid #c63663;border-radius:0 10px 10px 0;padding:14px 18px;margin:0 0 18px;font-size:14.5px;color:#a82249;">
            {{ $actividad->observaciones_revision }}
        </div>
    @endif

    <p style="margin:22px 0 0;">
        <a href="{{ route('account.activities.index') }}"
           style="display:inline-block;background:#e57200;color:#ffffff;font-weight:600;font-size:14px;padding:12px 22px;border-radius:999px;text-decoration:none;">
            Editar mi actividad
        </a>
    </p>
</x-mail-layout>
