<header style="position:sticky;top:0;z-index:50;background:rgba(255,255,255,.88);backdrop-filter:blur(10px);border-bottom:1px solid #eeeff0;"
        x-data="{ abierto: false }">
    <nav style="max-width:1180px;margin:0 auto;padding:14px 40px;display:flex;align-items:center;gap:24px;">

        <div class="nav-logos" style="flex:none;display:flex;align-items:center;gap:18px;">
            <a href="{{ route('home') }}" aria-label="Volver al inicio" style="display:inline-flex;">
                <img src="{{ asset('img/dps-logo-header.png') }}" alt="Día del Patrimonio Social 2026"
                     style="height:46px;width:auto;object-fit:contain;">
            </a>
            <span aria-hidden="true" style="width:1px;height:44px;background:#e4e5e7;"></span>
            <a href="https://comunidad-org.cl" target="_blank" rel="noopener" style="display:inline-flex;">
                <img src="{{ asset('img/logo-cos-color.png') }}" alt="Comunidad de Organizaciones Solidarias"
                     style="height:58px;width:auto;object-fit:contain;">
            </a>
        </div>

        <div class="nav-links" style="display:flex;align-items:center;gap:24px;margin:0 auto;flex:0 1 auto;min-width:0;">
            <a class="navlink" href="{{ route('activities.index') }}">Actividades</a>
            <a class="navlink" href="{{ route('publish.create') }}">Voluntariado</a>
            <a class="navlink" href="{{ route('home') }}#que-es">¿Qué es el Patrimonio Social?</a>
            <a class="navlink" href="{{ route('editions.index') }}">Ediciones</a>
            <a class="navlink" href="{{ route('posts.index') }}">Noticias</a>
        </div>

        <div style="flex:none;display:flex;align-items:center;gap:12px;margin-left:auto;">
            <a href="{{ route('publish.create') }}" class="btn btn-primary">Publica tu actividad</a>

            <a href="{{ route('account.login') }}" class="icon-btn" aria-label="Accede a tu cuenta" title="Accede a tu cuenta">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <circle cx="12" cy="10" r="3.2"></circle>
                    <path d="M6.6 19.2a6 6 0 0 1 10.8 0"></path>
                </svg>
            </a>

            <a href="https://instagram.com/diadelpatrimoniosocial" target="_blank" rel="noopener" class="icon-btn" aria-label="Instagram">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="20" height="20" x="2" y="2" rx="5"></rect>
                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                    <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line>
                </svg>
            </a>

            {{-- El prototipo escondía el menú bajo 1020px sin alternativa. Acá hay botón. --}}
            <button type="button" class="icon-btn" style="display:none;" x-cloak
                    x-bind:aria-expanded="abierto ? 'true' : 'false'"
                    x-on:click="abierto = !abierto"
                    aria-label="Abrir menú"
                    x-init="$el.style.display = window.matchMedia('(max-width:1020px)').matches ? 'grid' : 'none'">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <path d="M4 7h16M4 12h16M4 17h16"></path>
                </svg>
            </button>
        </div>
    </nav>

    <div x-show="abierto" x-cloak x-transition
         style="border-top:1px solid #eeeff0;background:#fff;padding:14px 40px 20px;display:flex;flex-direction:column;gap:14px;">
        <a class="navlink" href="{{ route('activities.index') }}">Actividades</a>
        <a class="navlink" href="{{ route('publish.create') }}">Voluntariado</a>
        <a class="navlink" href="{{ route('home') }}#que-es">¿Qué es el Patrimonio Social?</a>
        <a class="navlink" href="{{ route('editions.index') }}">Ediciones</a>
        <a class="navlink" href="{{ route('posts.index') }}">Noticias</a>
    </div>
</header>
