@php
    $tono = $act->estado_color;
    $temas = $act->termsDe('tema');
    $caracs = $act->termsDe('caracteristica');
    $etiquetas = $temas->concat($caracs)->take(3);
@endphp

<div class="act-card reveal tarjeta-carrusel" style="flex:0 0 calc((100% - 52px) / 3);min-width:288px;background:#fff;border:1px solid #eef0f1;border-radius:22px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 10px 30px -22px rgba(0,0,0,.2);">
    {{--
        C6 de la sexta tanda: la imagen también lleva a la ficha, que es donde
        pulsa casi todo el mundo. Fuera del orden de tabulación y oculta a los
        lectores de pantalla: el enlace de la tarjeta para ellos es «Ver
        actividad», y dos enlaces seguidos al mismo sitio sólo son ruido.
    --}}
    <a href="{{ route('activities.show', $act) }}" tabindex="-1" aria-hidden="true"
       style="display:block;aspect-ratio:16/10;overflow:hidden;background:var(--gris-100);">
        <img loading="lazy" decoding="async" class="act-img" src="{{ $act->imagen_url }}" alt="{{ $act->titulo }}"
             style="width:100%;height:100%;object-fit:cover;display:block;">
    </a>

    <div style="padding:20px 22px 22px;display:flex;flex-direction:column;gap:10px;flex:1;">
        @if ($temas->isNotEmpty())
            <span style="align-self:flex-start;font-size:12px;font-weight:700;letter-spacing:.02em;padding:5px 12px;border-radius:999px;background:var(--naranjo-100);color:var(--naranjo-600);">
                {{ $temas->first()->nombre }}
            </span>
        @endif

        <h3 style="font-weight:700;font-size:20px;line-height:1.15;margin:2px 0 0;letter-spacing:-.01em;">{{ $act->titulo }}</h3>

        <div style="display:flex;flex-wrap:wrap;gap:6px 16px;font-size:13.5px;color:var(--gris);">
            <span style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                {{ $act->commune?->nombre ?? 'Por definir' }}
            </span>
            <span style="display:inline-flex;align-items:center;gap:6px;font-weight:600;color:var(--gris-700);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>
                {{ $act->fecha_corta }}
            </span>
        </div>

        <p style="font-size:14.5px;line-height:1.5;margin:0;color:var(--gris);">{{ Str::limit($act->descripcion, 120) }}</p>

        @if ($etiquetas->isNotEmpty())
            <div style="display:flex;flex-wrap:wrap;gap:7px;margin-top:2px;">
                @foreach ($etiquetas as $t)
                    <span style="font-size:12px;font-weight:600;padding:5px 11px;border-radius:999px;background:var(--gris-100);color:var(--gris-700);">{{ $t->nombre }}</span>
                @endforeach
            </div>
        @endif

        {{-- C6: relleno naranja y no de contorno, para que se vea como EL botón
             de la tarjeta y no como una etiqueta más. --}}
        <a href="{{ route('activities.show', $act) }}" class="btn btn-primary"
           style="align-self:flex-start;margin-top:auto;padding:11px 22px;font-size:14.5px;">
            Ver actividad
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
        </a>
    </div>
</div>
