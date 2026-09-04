{{-- Sin textos propios: su contenido sale de su CRUD. --}}
{{--
    Auspiciadores.

    Cada grupo se reparte centrado bajo su etiqueta, como en el fuente. El
    tamaño no se decide aquí: sale de `partners.tamano`, fila a fila, y se
    cambia desde el CRUD. Son tres —grande, mediano y chico— y cada tarjeta
    lleva el suyo, así que un grupo puede mezclarlos; lo único que la fila
    decide es el hueco entre tarjetas, que lo marca el mayor de los que tenga.
--}}
<section class="reveal contenido" style="max-width:1040px;margin:0 auto;padding:30px 40px 76px;text-align:center;">
    <div style="display:flex;flex-direction:column;gap:34px;">
        @foreach ($grupos as $etiqueta => $logos)
            @continue($logos->isEmpty())

            <div>
                <div class="dato-editable" style="font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;color:var(--naranjo);margin-bottom:16px;">{{ $etiqueta }}</div>

                @php
                    $escala = ['chico' => 1, 'mediano' => 2, 'grande' => 3];
                    $fila = array_search($logos->max(fn ($l) => $escala[$l->tamano_clase]), $escala, true);
                @endphp

                <div class="logos-fila logos-fila--{{ $fila }}">
                    @foreach ($logos as $lg)
                        <div class="logo-chip logo-chip--{{ $lg->tamano_clase }}">
                            @if ($lg->logo_path)
                                <img loading="lazy" decoding="async" src="{{ $lg->logo_url }}" alt="{{ $lg->nombre }}">
                            @else
                                <span>{{ $lg->nombre }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
