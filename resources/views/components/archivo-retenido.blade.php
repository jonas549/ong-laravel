@props(['campo'])

@php
    $retenido = \App\Support\ArchivosRetenidos::recuperar($campo);
@endphp

@if ($retenido)
    {{--
        Punto 4 de la tanda del 11/09.

        Un `<input type="file">` no se puede rellenar desde el servidor, así que
        al volver de un rebote el selector aparece vacío aunque el archivo siga
        guardado. Sin este aviso, la persona da por perdido lo que subió y o lo
        vuelve a elegir sin necesidad, o —lo que pasaba— reenvía sin él y su
        organización se queda sin logo sin que nadie diga nada.

        Se dice el nombre del archivo, y no un «hay uno guardado»: reconocerlo
        es lo que permite saber si es el que se quería.
    --}}
    <div class="archivo-retenido" role="status">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 6 9 17l-5-5"/>
        </svg>
        <span>
            Guardamos <strong>{{ $retenido['nombre'] }}</strong> de tu intento anterior.
            Se usará ese archivo salvo que elijas otro.
        </span>
    </div>
@endif
