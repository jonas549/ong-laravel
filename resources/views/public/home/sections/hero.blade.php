@php $b = $borrador ?? false; @endphp

<section id="top" style="position:relative;overflow:hidden;background:#e9c7e0;">
    <img decoding="async" width="1920" height="810" src="{{ asset($seccion->imagen('imagen_fondo', $b)) }}"
         alt="Ilustración del Día del Patrimonio Social"
         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;">

    <div style="position:absolute;left:0;right:0;bottom:0;height:280px;background:linear-gradient(to bottom,rgba(255,255,255,0) 0%,rgba(255,255,255,.55) 45%,rgba(255,255,255,.92) 78%,#fff 100%);z-index:1;"></div>

    <div style="position:relative;z-index:2;max-width:900px;margin:0 auto;padding:72px 40px 210px;text-align:center;">
        <div class="hero-pildora reveal" style="display:inline-flex;align-items:center;gap:9px;font-size:13.5px;letter-spacing:.02em;font-weight:600;color:#fff;background:var(--naranjo);padding:9px 20px;border-radius:999px;margin-bottom:26px;box-shadow:0 12px 26px -14px rgba(229,114,0,.9);">
            <span style="flex:none;width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.85);"></span>
            {{-- El texto va envuelto: suelto, cada trozo era un ítem del flex y
                 en móvil se partía en columnas con los puntos separadores sueltos. --}}
            <span class="dato-editable">{{ $seccion->texto('pildora_antes', $b) }} · <strong style="font-weight:800;">{{ $seccion->texto('pildora_fechas', $b) }}</strong> · {{ $seccion->texto('pildora_despues', $b) }}</span>
        </div>

        {{-- El destacado va pegado al título con un espacio, no en su propia
             línea: el fuente lo tiene dentro del mismo <h1> y cualquier salto
             extra aquí se convertiría en un espacio de más en pantalla. --}}
        <h1 class="hero-h1 reveal hero-glow dato-editable" style="font-weight:800;font-size:62px;line-height:1.03;letter-spacing:-.02em;margin:0 auto;max-width:17ch;">
            {{ $seccion->texto('titulo', $b) }}@if ($destacado = $seccion->texto('titulo_destacado', $b)) <span style="color:var(--naranjo);">{{ $destacado }}</span>@endif
        </h1>

        <p class="reveal hero-glow dato-editable" style="display:inline-block;font-size:18px;line-height:1.6;max-width:50ch;margin:24px auto 0;color:var(--gris-700);background:rgba(255,255,255,.62);border:1px solid rgba(255,255,255,.85);border-radius:14px;padding:12px 22px;backdrop-filter:blur(6px);">
            {{ $seccion->texto('bajada', $b) }}
        </p>

        <div style="position:relative;width:470px;max-width:100%;margin:22px auto 0;">
            <img decoding="async" class="floaty" width="486" height="375" src="{{ asset('img/logo-corazon-15f12e4a.png') }}"
                 alt="Día del Patrimonio Social — dar está en nuestra naturaleza"
                 style="display:block;width:100%;height:auto;filter:drop-shadow(0 18px 30px rgba(0,0,0,.22)) drop-shadow(0 4px 10px rgba(0,0,0,.12));">
            <a href="{{ $seccion->enlace('cta_enlace', $b) ?: '#voluntariado' }}" class="hero-cta boton-editable" style="position:absolute;left:50%;bottom:64px;transform:translateX(-50%);background:var(--naranjo);color:#fff;font-weight:700;font-size:17px;padding:13px 30px;border-radius:999px;box-shadow:0 12px 26px -12px rgba(229,114,0,.85);">
                {{ $seccion->texto('cta_texto', $b) }}
            </a>
        </div>
    </div>
</section>
