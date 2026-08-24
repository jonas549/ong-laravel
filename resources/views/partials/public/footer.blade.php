<section style="position:relative;background:url('{{ asset('img/fondo-03.png') }}') center/cover no-repeat;overflow:hidden;">
    <div aria-hidden="true" style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.28) 0%,rgba(255,255,255,.1) 45%,rgba(255,255,255,.4) 100%);"></div>
    <div class="reveal" style="position:relative;z-index:1;max-width:1180px;margin:0 auto;padding:40px 40px 44px;display:flex;flex-direction:column;align-items:center;gap:18px;text-align:center;">
        <img class="floaty" src="{{ asset('img/dia-del-patrimonio-footer.png') }}"
             alt="Día del Patrimonio Social — 4 y 5 de diciembre"
             style="display:block;width:420px;max-width:100%;height:auto;filter:drop-shadow(0 16px 28px rgba(0,0,0,.16));">
    </div>
</section>

<footer style="background:#fff;color:var(--gris-700);border-top:1px solid #eef0f1;">
    <div style="max-width:1180px;margin:0 auto;padding:30px 40px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
        <span style="flex:none;display:inline-flex;">
            <img src="{{ asset('img/logo-cos-color.png') }}" alt="Comunidad de Organizaciones Solidarias"
                 style="height:68px;width:auto;object-fit:contain;">
        </span>

        <a class="textlink" href="{{ url('/privacidad') }}" style="font-size:14px;">Política de privacidad</a>

        <div style="display:flex;align-items:center;gap:24px;margin-left:auto;flex-wrap:wrap;">
            <a class="textlink" href="mailto:diadelpatrimoniosocial@comunidad-org.cl" style="font-size:14px;">diadelpatrimoniosocial@comunidad-org.cl</a>
            <a class="textlink" href="https://instagram.com/diadelpatrimoniosocial" target="_blank" rel="noopener" style="font-size:14px;">@diadelpatrimoniosocial</a>

            <div style="display:flex;gap:14px;">
                <a href="https://instagram.com/diadelpatrimoniosocial" target="_blank" rel="noopener" class="icon-btn" aria-label="Instagram"
                   style="width:40px;height:40px;background:var(--naranjo-100);border-color:#eef0f1;color:var(--naranjo-600);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="20" x="2" y="2" rx="5"></rect>
                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                        <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line>
                    </svg>
                </a>
                <a href="#" class="icon-btn" aria-label="LinkedIn"
                   style="width:40px;height:40px;background:var(--naranjo-100);border-color:#eef0f1;color:var(--naranjo-600);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
                        <rect width="4" height="12" x="2" y="9"></rect>
                        <circle cx="4" cy="4" r="2"></circle>
                    </svg>
                </a>
                <a href="#" class="icon-btn" aria-label="YouTube"
                   style="width:40px;height:40px;background:var(--naranjo-100);border-color:#eef0f1;color:var(--naranjo-600);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"></path>
                        <path d="m10 15 5-3-5-3z"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <div style="max-width:1180px;margin:0 auto;padding:20px 40px 28px;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;border-top:1px solid #f2f3f4;">
        <span style="font-size:13px;color:var(--gris);">Ilustraciones por Gabriel Ebensperger</span>
        <a href="#top" style="display:inline-flex;align-items:center;gap:9px;font-size:13px;font-weight:700;color:var(--naranjo-600);">
            Volver arriba
            <span style="display:grid;place-items:center;width:34px;height:34px;border-radius:999px;background:var(--naranjo);color:#fff;box-shadow:0 10px 22px -12px rgba(229,114,0,.85);">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m18 15-6-6-6 6"></path>
                </svg>
            </span>
        </a>
    </div>
</footer>
