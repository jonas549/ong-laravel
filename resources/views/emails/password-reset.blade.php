<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        Recupera tu contraseña
    </h1>

    <p style="margin:0 0 14px;">
        Recibimos una solicitud para crear una contraseña nueva en tu cuenta.
        Haz clic en el botón y elige una.
    </p>

    <p style="margin:22px 0;">
        <a href="{{ $enlace }}"
           style="display:inline-block;background:#e57200;color:#ffffff;font-weight:600;font-size:14px;padding:12px 22px;border-radius:999px;text-decoration:none;">
            Crear contraseña nueva
        </a>
    </p>

    <p style="margin:0 0 14px;">
        El enlace caduca en {{ $minutos }} minutos.
    </p>

    <p style="margin:0;color:#8f9399;font-size:13.5px;">
        Si no pediste esto, puedes ignorar el correo: tu contraseña actual sigue funcionando.
    </p>
</x-mail-layout>
