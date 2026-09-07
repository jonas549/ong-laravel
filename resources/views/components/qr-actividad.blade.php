@props(['activity', 'rutaPng', 'rutaSvg'])

@php
    use App\Services\CodigoQr;

    $qr = app(CodigoQr::class);

    /*
     * Aquí sí va incrustado como `data:`, y en el correo no.
     *
     * Son dos sitios con reglas distintas: un navegador pinta un `data:` sin
     * pestañear y ahorra una petición, mientras que los clientes de correo lo
     * bloquean casi siempre —por eso allí viaja como imagen adjunta con `cid:`.
     * Un PNG de 240 px son unos tres kilobytes en base64, así que la página no
     * lo nota.
     *
     * Si el código no se pudiera generar, el bloque entero desaparece en vez de
     * dejar una imagen rota bajo un título que habla de un QR.
     */
    try {
        $png = base64_encode($qr->png($activity, 240));
        $destino = $qr->destino($activity);
    } catch (\Throwable $e) {
        $png = null;
        report($e);
    }
@endphp

@if ($png)
    <div class="qr-caja">
        <img class="qr-imagen" src="data:image/png;base64,{{ $png }}"
             alt="Código QR de la encuesta de evaluación de {{ $activity->titulo }}">

        <div class="qr-datos">
            <strong style="font-size:15px;">Código QR de la encuesta</strong>

            <span class="helper">
                Imprímelo y ponlo a la vista el día de la actividad. Quien lo escanee
                podrá contar cómo le fue en menos de un minuto.
            </span>

            <span class="qr-enlace">{{ $destino }}</span>

            <span class="qr-botones">
                {{-- Los dos formatos, y el orden importa: el SVG es el bueno para
                     imprimir —es vectorial, así que vale igual para una pegatina
                     que para un pendón— y el PNG el cómodo para pegar en un
                     documento. Se dice cuál es cuál, porque «SVG» no significa
                     nada para quien va a llevar esto a una imprenta. --}}
                <a class="btn btn-primary btn-sm" href="{{ $rutaSvg }}">Descargar para imprimir (SVG)</a>
                <a class="btn btn-outline btn-sm" href="{{ $rutaPng }}">Descargar imagen (PNG)</a>
            </span>
        </div>
    </div>
@endif
