<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        La configuración de correo funciona
    </h1>

    <p style="margin:0 0 14px;">
        Si estás leyendo esto, el servidor SMTP configurado en el panel entregó el mensaje correctamente.
    </p>

    <p style="margin:0;font-size:13.5px;color:#8f9399;">
        Enviado el {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY [a las] HH:mm') }}.
    </p>
</x-mail-layout>
