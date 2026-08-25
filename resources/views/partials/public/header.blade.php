{{--
    Header público.

    En escritorio replica el de los tres HTML fuente. Por debajo de 1020px el
    fuente sólo hace display:none a los enlaces y no pone nada en su lugar, así
    que el menú desplegable es nuestro: en móvil quedan el logo, el botón de
    publicar y la hamburguesa, y los enlaces bajan al desplegable.

    El desenfoque va en .nav-barra y no en el <header>. Si envuelve también al
    panel, al abrirlo el navegador tiene que rehacer el desenfoque de media
    pantalla: en un teléfono eso es el parpadeo en blanco y el retardo al tocar.
--}}
<header style="position:sticky;top:0;z-index:50;"
        x-data="{ abierto: false }"
        x-on:keydown.escape.window="abierto = false">

    <div class="nav-barra" style="background:rgba(255,255,255,.88);backdrop-filter:blur(10px);border-bottom:1px solid #eeeff0;">
        <nav class="nav-fila" style="max-width:1180px;margin:0 auto;padding:12px 32px;display:flex;align-items:center;gap:20px;">

            <div class="nav-logos" style="flex:none;display:flex;align-items:center;gap:16px;min-width:0;">
                <a href="{{ route('home') }}" aria-label="Volver al inicio" style="display:inline-flex;">
                    <img decoding="async" width="400" height="120" src="{{ asset('img/dps-logo-header.png') }}" alt="Día del Patrimonio Social 2026"
                         style="height:46px;width:auto;object-fit:contain;">
                </a>
                <span class="nav-sep" aria-hidden="true" style="width:1px;height:44px;background:#e4e5e7;"></span>
                <a class="nav-cos" href="https://comunidad-org.cl" target="_blank" rel="noopener" style="display:inline-flex;">
                    <img decoding="async" width="400" height="313" src="{{ asset('img/logo-cos-color.png') }}" alt="Comunidad de Organizaciones Solidarias"
                         style="height:58px;width:auto;object-fit:contain;">
                </a>
            </div>

            <div class="nav-links" style="display:flex;align-items:center;gap:24px;margin:0 auto;flex:0 1 auto;min-width:0;">
                <a class="navlink" href="{{ route('activities.index') }}">Actividades</a>
                <a class="navlink" href="{{ route('publish.create') }}">Voluntariado</a>
                <a class="navlink" href="{{ route('home') }}#que-es">¿Qué es el Patrimonio Social?</a>
                <a class="navlink" href="{{ route('home') }}#ediciones">Ediciones</a>
                <a class="navlink" href="{{ route('posts.index') }}">Noticias</a>
            </div>

            <div style="flex:none;display:flex;align-items:center;gap:12px;margin-left:auto;">
                {{-- Se queda visible en móvil: es la acción principal del sitio. --}}
                <a href="{{ route('publish.create') }}" class="btn btn-primary nav-cta">Publica tu actividad</a>

                <div class="nav-iconos" style="display:flex;align-items:center;gap:12px;">
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
                </div>

                {{-- Un solo SVG que cambia de trazo: dos con x-show obligaban a
                     Alpine a tocar el display de ambos en cada toque. --}}
                <button type="button" class="icon-btn nav-toggle"
                        x-on:click="abierto = !abierto"
                        x-bind:aria-expanded="abierto ? 'true' : 'false'"
                        aria-controls="menu-movil"
                        x-bind:aria-label="abierto ? 'Cerrar menú' : 'Abrir menú'">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path x-bind:d="abierto ? 'M6 6l12 12M18 6L6 18' : 'M4 7h16M4 12h16M4 17h16'" d="M4 7h16M4 12h16M4 17h16"></path>
                    </svg>
                </button>
            </div>
        </nav>
    </div>

    {{--
        Sin x-transition, que se pidió instantáneo. Y en posición absoluta: si
        va en el flujo normal, abrirlo cambia la altura del header y obliga a
        rehacer la maquetación de toda la página debajo. Así sólo se pinta el
        panel y el contenido ni se entera.
    --}}
    <div id="menu-movil" x-show="abierto" x-cloak
         style="position:absolute;top:100%;left:0;right:0;background:#fff;border-top:1px solid #eeeff0;border-bottom:1px solid #eeeff0;box-shadow:0 18px 30px -22px rgba(0,0,0,.35);padding:14px 20px 20px;display:flex;flex-direction:column;gap:2px;max-height:calc(100vh - 76px);overflow-y:auto;">

        @foreach ([
            ['Actividades', route('activities.index')],
            ['Voluntariado', route('publish.create')],
            ['¿Qué es el Patrimonio Social?', route('home') . '#que-es'],
            ['Ediciones', route('home') . '#ediciones'],
            ['Noticias', route('posts.index')],
        ] as [$texto, $destino])
            <a class="navlink-movil" href="{{ $destino }}" x-on:click="abierto = false">{{ $texto }}</a>
        @endforeach

        <div style="display:flex;align-items:center;gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid var(--linea);">
            <a href="{{ route('account.login') }}" class="icon-btn" aria-label="Accede a tu cuenta" x-on:click="abierto = false">
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
            <span class="helper" style="margin-left:auto;">Tu cuenta e Instagram</span>
        </div>
    </div>
</header>
