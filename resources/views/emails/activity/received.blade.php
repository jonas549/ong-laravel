<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        Recibimos tu actividad
    </h1>

    <p style="margin:0 0 14px;">
        Gracias por sumarte. <strong>{{ $actividad->titulo }}</strong> quedó en revisión por el equipo organizador.
    </p>

    <p style="margin:0 0 14px;">
        Te escribiremos apenas esté revisada. Si necesitamos algún ajuste, te lo indicaremos en ese mismo correo.
    </p>

    <p style="margin:22px 0 0;">
        <a href="{{ route('account.activities.index') }}"
           style="display:inline-block;background:#e57200;color:#ffffff;font-weight:600;font-size:14px;padding:12px 22px;border-radius:999px;text-decoration:none;">
            Ver mis actividades
        </a>
    </p>
</x-mail-layout>
