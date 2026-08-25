<x-mail-layout>
    <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">
        Cambió el correo de tu cuenta
    </h1>

    <p style="margin:0 0 14px;">
        Hola {{ $nombre }}, te avisamos porque la dirección de tu cuenta pasó de
        <strong>{{ $correoAnterior }}</strong> a <strong>{{ $correoNuevo }}</strong>.
    </p>

    <p style="margin:0 0 14px;">
        Si fuiste tú, no tienes que hacer nada: a partir de ahora entra con la dirección nueva.
    </p>

    <p style="margin:0 0 14px;">
        <strong>Si no fuiste tú</strong>, alguien tiene acceso a tu cuenta. Escríbenos cuanto antes
        y la recuperamos.
    </p>

    <p style="margin:22px 0;">
        <a href="{{ $enlaceAyuda }}"
           style="display:inline-block;background:#e57200;color:#ffffff;font-weight:600;font-size:14px;padding:12px 22px;border-radius:999px;text-decoration:none;">
            Escribirnos
        </a>
    </p>

    <p style="margin:0;color:#8f9399;font-size:13.5px;">
        Este es el último mensaje que enviamos a esta dirección.
    </p>
</x-mail-layout>
