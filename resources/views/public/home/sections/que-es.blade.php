<section id="que-es" style="scroll-margin-top:90px;background:linear-gradient(160deg,rgba(92,184,178,.14) 0%,rgba(92,184,178,.07) 100%);">
    <div style="max-width:1180px;margin:0 auto;padding:92px 40px;display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:60px;align-items:center;">
        <div class="reveal">
            <h2 style="font-weight:800;font-size:38px;margin:0 0 22px;letter-spacing:-.01em;">¿Qué es el <span style="color:var(--naranjo);">Patrimonio Social</span>?</h2>
            <p style="font-size:16px;line-height:1.65;margin:0 0 16px;color:var(--gris-700);">Es todo aquello que construimos cuando nos unimos para cuidar, compartir y colaborar con otras personas.</p>
            <p style="font-size:16px;line-height:1.65;margin:0 0 20px;color:var(--gris-700);">Es un patrimonio vivo que se fortalece con cada acción solidaria y que nos pertenece a todas y todos.</p>
            <p style="font-size:19px;line-height:1.4;margin:0 0 26px;font-weight:700;color:var(--ink);">Nuestro mayor Patrimonio Social es la solidaridad.</p>
            <a href="{{ route('activities.index') }}" class="btn btn-primary">Conoce más</a>
        </div>

        <figure class="reveal" style="margin:0;position:relative;overflow:visible;">
            <span class="adorno" style="position:absolute;top:-18px;right:-18px;width:72px;height:72px;border-radius:18px;background:var(--amarillo);z-index:-1;opacity:.9;"></span>
            <div style="border-radius:26px;overflow:hidden;aspect-ratio:16/11;background:var(--gris-100);box-shadow:0 26px 50px -28px rgba(7,79,113,.4);">
                <img loading="lazy" decoding="async" width="1376" height="771" src="{{ asset('img/group-people-shaking-hands-with-one-that-says-h-it.jpg') }}"
                     alt="Personas dándose la mano en una jornada solidaria"
                     style="width:100%;height:100%;object-fit:cover;display:block;">
            </div>
            <img loading="lazy" decoding="async" class="sticker" width="400" height="400" src="{{ asset('img/dps-mascara-corazon-ronda.png') }}" alt="" aria-hidden="true"
                 style="--rot:-8deg;position:absolute;top:-52px;left:-52px;width:140px;height:auto;">
        </figure>
    </div>
</section>
