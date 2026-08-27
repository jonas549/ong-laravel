@php $b = $borrador ?? false; @endphp

<section id="actividades" style="scroll-margin-top:90px;max-width:1180px;margin:0 auto;padding:88px 40px 92px;">
    <div class="reveal" style="display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-bottom:44px;flex-wrap:wrap;">
        <div class="cabecera-seccion" style="display:flex;align-items:flex-start;gap:22px;max-width:700px;">
            <img loading="lazy" decoding="async" width="698" height="698" src="{{ asset('img/actividades-destacadas.png') }}" alt="" aria-hidden="true"
                 style="flex:none;width:210px;height:auto;margin-top:2px;">
            <div>
                <div class="dato-editable" style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:14px;">{{ $seccion->texto('antetitulo', $b) }}</div>
                <h2 class="dato-editable" style="font-weight:800;font-size:38px;line-height:1.08;margin:0 0 14px;letter-spacing:-.01em;">{{ $seccion->texto('titulo', $b) }}</h2>
                <p class="dato-editable" style="font-size:16px;line-height:1.55;margin:0;color:var(--gris);max-width:52ch;">{{ $seccion->texto('bajada', $b) }}</p>
            </div>
        </div>

        @if ($actividades->count() > 1)
            <div style="display:flex;gap:10px;flex:none;">
                <button type="button" class="icon-btn" data-carousel-prev="act" aria-label="Actividad anterior">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                </button>
                <button type="button" class="icon-btn" data-carousel-next="act" aria-label="Actividad siguiente">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                </button>
            </div>
        @endif
    </div>

    @if ($actividades->isEmpty())
        <p class="dato-editable" style="color:var(--gris);font-size:15px;">{{ $seccion->texto('vacio', $b) }}</p>
    @else
        <div class="carousel" data-carousel="act" style="display:flex;gap:26px;overflow-x:auto;padding-bottom:8px;">
            @foreach ($actividades as $act)
                @include('public.partials.activity-card', ['act' => $act])
            @endforeach
        </div>
    @endif

    <div class="reveal" style="text-align:center;margin-top:48px;">
        <a href="{{ route('activities.index') }}" class="btn btn-primary boton-editable">{{ $seccion->texto('cta_texto', $b) }}</a>
    </div>
</section>
