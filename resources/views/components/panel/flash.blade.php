{{--
    Los avisos de la petición anterior, en un solo sitio.

    Estaban escritos a mano en el layout, cada uno con su `style` en línea y su
    variante de sesión: `ok`, `error`, los errores de validación y un
    `detalle_smtp` que sólo usa una pantalla. Aquí es uno.

    Tres decisiones:

    - **El de éxito se va solo a los ocho segundos; los de error, no.** Un «se
      guardó» ya cumplió su función en cuanto se lee; un error hay que poder
      volver a leerlo mientras se arregla lo que falló.
    - **Todos se pueden cerrar a mano**, porque el aviso se queda encima de la
      tabla y a veces estorba justo a la fila que se quiere ver.
    - **`aria-live`**: `polite` para lo bueno y `assertive` para lo malo, para
      que un lector de pantalla los anuncie sin que haya que ir a buscarlos.
--}}

@php
    $avisos = [];

    if (session('ok')) {
        $avisos[] = ['tipo' => 'ok', 'texto' => session('ok'), 'auto' => true];
    }

    if (session('error')) {
        $avisos[] = ['tipo' => 'error', 'texto' => session('error'), 'detalle' => session('detalle_smtp')];
    }

    if (session('aviso')) {
        $avisos[] = ['tipo' => 'info', 'texto' => session('aviso')];
    }
@endphp

@foreach ($avisos as $aviso)
    <div class="alert alert-{{ $aviso['tipo'] }} panel-flash"
         role="{{ $aviso['tipo'] === 'error' ? 'alert' : 'status' }}"
         aria-live="{{ $aviso['tipo'] === 'error' ? 'assertive' : 'polite' }}"
         x-data="flash({{ $aviso['auto'] ?? false ? 'true' : 'false' }})" x-show="visible" x-cloak>

        <div class="panel-flash-texto">
            {{ $aviso['texto'] }}
            @if ($aviso['detalle'] ?? null)
                <pre class="panel-flash-detalle">{{ $aviso['detalle'] }}</pre>
            @endif
        </div>

        <button type="button" class="panel-flash-cerrar" x-on:click="visible = false" aria-label="Cerrar el aviso">×</button>
    </div>
@endforeach

@if ($errors->any())
    {{-- Los de validación no se van solos ni se cierran: son la lista de lo que
         hay que corregir en el formulario que se ve debajo. --}}
    <div class="alert alert-error panel-flash" role="alert" aria-live="assertive">
        <div class="panel-flash-texto">
            @if ($errors->count() === 1)
                {{ $errors->first() }}
            @else
                <strong>Hay {{ $errors->count() }} cosas que corregir:</strong>
                <ul style="margin:6px 0 0;padding-left:18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
