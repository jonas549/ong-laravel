<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        Cambiamos la contraseña de tu cuenta
    </h1>

    <p style="margin:0 0 14px;">
        Hola {{ $nombre }}, te avisamos de que <strong>{{ $adminNombre }}</strong>, del equipo de
        {{ config('app.name') }}, cambió la contraseña de tu cuenta.
    </p>

    <p style="margin:0 0 14px;">
        Por seguridad se cerraron todas tus sesiones, así que tendrás que volver a entrar.
        La contraseña nueva no va en este correo: te la damos por el mismo medio por el que
        pediste el cambio.
    </p>

    <p style="margin:22px 0;">
        <a href="{{ $enlaceAcceso }}"
           style="display:inline-block;background:#e57200;color:#ffffff;font-weight:600;font-size:14px;padding:12px 22px;border-radius:999px;text-decoration:none;">
            Entrar a mi cuenta
        </a>
    </p>

    <p style="margin:0 0 14px;">
        <strong>Si no pediste este cambio</strong>, avísanos cuanto antes. Mientras tanto puedes
        <a href="{{ $enlaceRecuperar }}" style="color:#cc6600;">crear una contraseña nueva tú mismo</a>,
        y así sólo la sabrás tú.
    </p>
</x-mail-layout>
