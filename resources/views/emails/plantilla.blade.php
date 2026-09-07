{{--
    Envoltorio de las plantillas editables.

    El cuerpo llega ya resuelto desde EmailTemplateRenderer y se imprime sin
    escapar, porque es HTML escrito a propósito desde el panel. Las variables
    que lo componen sí van escapadas una a una en el renderer, que es donde
    entra el dato de fuera.
--}}
@php
    /*
     * Las imágenes que viajan dentro del correo.
     *
     * El cuerpo las cita por un nombre —`<img src="cid:qr">`— y aquí ese nombre
     * se cambia por el identificador de verdad que devuelve `embedData`, que no
     * se conoce hasta que el mensaje existe. Por eso se hace en la vista y no en
     * el renderer: el renderer no tiene mensaje al que adjuntar nada.
     *
     * `$message` lo inyecta Laravel en las vistas de correo.
     */
    foreach ($incrustadas ?? [] as $nombre => $imagen) {
        $cuerpo = str_replace(
            'cid:'.$nombre,
            // Llegan en base64 porque la cola serializa a JSON y un PNG en
            // crudo no es UTF-8 válido. Ver el constructor de PlantillaMail.
            $message->embedData(base64_decode($imagen['datos']), $imagen['nombre'], $imagen['mime']),
            $cuerpo,
        );
    }
@endphp

<x-mail-layout>
    {!! $cuerpo !!}
</x-mail-layout>
