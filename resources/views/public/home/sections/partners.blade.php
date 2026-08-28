{{-- Sin textos propios: su contenido sale de su CRUD. --}}
{{--
    Auspiciadores.

    Cada grupo se reparte centrado bajo su etiqueta, como en el fuente, y su
    tarjeta va grande o chica según el grupo. El tamaño no se decide aquí: sale
    de `partners.tamano`, que ya lo guarda, así que cambiarlo es cosa del CRUD
    y no de esta vista.
--}}
<section class="reveal contenido" style="max-width:1040px;margin:0 auto;padding:30px 40px 76px;text-align:center;">
    <div style="display:flex;flex-direction:column;gap:34px;">
        @foreach ($grupos as $etiqueta => $logos)
            @continue($logos->isEmpty())

            <div>
                <div class="dato-editable" style="font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;color:var(--naranjo);margin-bottom:16px;">{{ $etiqueta }}</div>

                @php $grande = $logos->contains(fn ($l) => $l->tamano === 'grande'); @endphp

                <div class="logos-fila {{ $grande ? 'logos-fila--grande' : 'logos-fila--chico' }}">
                    @foreach ($logos as $lg)
                        <div class="logo-chip">
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
