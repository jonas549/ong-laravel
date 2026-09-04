@php $b = $borrador ?? false; @endphp

{{--
    «Somos parte de», el último bloque de logos del home.

    Es una sección aparte y no un grupo más de «Partners — grilla» por dónde
    tiene que caer: el cliente pidió el orden auspiciadores → alianzas →
    participantes → somos parte de, y los dos primeros viven dentro de aquella
    sección, con la marquesina en medio. Éste va por debajo, así que necesita su
    propio sitio en el orden del home.

    Se pinta con la misma fila de logos que los otros grupos —misma clase, mismo
    `partners.tamano`— para que un logo se vea igual esté en el bloque que esté.

    **Sin logos en su grupo no se pinta nada, ni el título.** Un encabezado
    solo, encima de una fila vacía, es peor que no tener la sección.
--}}
@if ($somosParte->isNotEmpty())
    <section class="reveal contenido" style="max-width:1040px;margin:0 auto;padding:0 40px 76px;text-align:center;">
        <div class="dato-editable" style="font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;color:var(--naranjo);margin-bottom:16px;">
            {{ $seccion->texto('antetitulo', $b) }}
        </div>

        @php
            $escala = ['chico' => 1, 'mediano' => 2, 'grande' => 3];
            $fila = array_search($somosParte->max(fn ($l) => $escala[$l->tamano_clase]), $escala, true);
        @endphp

        <div class="logos-fila logos-fila--{{ $fila }}">
            @foreach ($somosParte as $lg)
                <div class="logo-chip logo-chip--{{ $lg->tamano_clase }}">
                    @if ($lg->logo_path)
                        <img loading="lazy" decoding="async" src="{{ $lg->logo_url }}" alt="{{ $lg->nombre }}">
                    @else
                        <span>{{ $lg->nombre }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif
