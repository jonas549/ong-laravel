@php $b = $borrador ?? false; @endphp

@if ($participantes->isNotEmpty())
    <section class="reveal" style="max-width:1180px;margin:0 auto;padding:30px 0 92px;text-align:center;overflow:hidden;">
        <div class="dato-editable" style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--gris);margin-bottom:34px;padding:0 40px;">
            {{ $seccion->texto('antetitulo', $b) }}
        </div>

        <div class="marquee" style="position:relative;-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);">
            <div class="marquee-track" style="display:flex;gap:16px;width:max-content;">
                {{-- Dos pasadas: la animación desplaza -50%, así el bucle es continuo. --}}
                @foreach ([false, true] as $duplicado)
                    @foreach ($participantes as $p)
                        {{--
                            La lista puede venir de dos sitios: de las
                            organizaciones que han publicado —que es lo normal
                            desde P12— o de las pastillas de `partners`, que
                            quedan como respaldo para una instalación recién
                            sembrada. Se distinguen por lo que traen: una
                            organización tiene logo o iniciales; una pastilla,
                            un color.
                        --}}
                        <div class="logo-chip" @if ($duplicado) aria-hidden="true" @endif
                             style="display:flex;align-items:center;gap:8px;height:46px;padding:0 16px;background:#fff;border:1px solid #eef0f1;border-radius:10px;box-shadow:0 6px 18px -14px rgba(0,0,0,.25);flex:none;">
                            @if ($p->logo_path ?? null)
                                <img loading="lazy" decoding="async" src="{{ asset($p->logo_path) }}" alt=""
                                     style="width:30px;height:30px;object-fit:contain;flex:none;">
                            @elseif (isset($p->iniciales))
                                <span style="display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:var(--naranjo-100);color:var(--naranjo-600);font-size:11px;font-weight:800;flex:none;">{{ $p->iniciales }}</span>
                            @else
                                <span style="width:18px;height:18px;border-radius:50%;background:{{ $p->color }};flex:none;"></span>
                            @endif
                            <span style="font-weight:700;font-size:14px;letter-spacing:-.01em;color:var(--gris-700);white-space:nowrap;">{{ $p->nombre }}</span>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>
@endif
