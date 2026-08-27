{{-- Sin textos propios: su contenido sale de su CRUD. --}}
{{--
    Auspiciadores y participantes.

    El fuente dimensiona cada tarjeta por su contenido y además da tamaños
    distintos al grupo destacado, así que ninguna coincide con otra. Acá van
    todas en una grilla con la misma caja: mismo alto, mismo radio, y el logo
    centrado y escalado dentro. Las columnas bajan de 4 a 3 y a 2 según el ancho.
--}}
<section class="reveal contenido" style="max-width:1040px;margin:0 auto;padding:30px 40px 76px;text-align:center;">
    <div style="display:flex;flex-direction:column;gap:34px;">
        @foreach ($grupos as $etiqueta => $logos)
            @continue($logos->isEmpty())

            <div>
                <div class="dato-editable" style="font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;color:var(--naranjo);margin-bottom:16px;">{{ $etiqueta }}</div>

                <div class="grid-logos">
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
