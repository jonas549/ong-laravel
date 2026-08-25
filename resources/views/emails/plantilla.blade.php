{{--
    Envoltorio de las plantillas editables.

    El cuerpo llega ya resuelto desde EmailTemplateRenderer y se imprime sin
    escapar, porque es HTML escrito a propósito desde el panel. Las variables
    que lo componen sí van escapadas una a una en el renderer, que es donde
    entra el dato de fuera.
--}}
<x-mail-layout>
    {!! $cuerpo !!}
</x-mail-layout>
