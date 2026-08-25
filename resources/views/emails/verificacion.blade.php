<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        Confirma tu correo
    </h1>

    <p style="margin:0 0 14px;">
        Hola {{ $nombre }}, sólo falta que confirmes que esta dirección es tuya.
    </p>

    <p style="margin:22px 0;">
        <a href="{{ $enlace }}"
           style="display:inline-block;background:#e57200;color:#ffffff;font-weight:600;font-size:14px;padding:12px 22px;border-radius:999px;text-decoration:none;">
            Confirmar mi correo
        </a>
    </p>

    <p style="margin:0 0 14px;">El enlace caduca en {{ $minutos }} minutos.</p>

    <p style="margin:0;color:#8f9399;font-size:13.5px;">
        Si no creaste ninguna cuenta, puedes ignorar este correo.
    </p>
</x-mail-layout>
