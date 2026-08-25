{{--
    Footer compacto.

    Es el que traen mi-cuenta.html y publicar-actividad.html: una sola fila
    con el logo COS a 44px y tres enlaces. No lleva la franja de redes, ni el
    "Volver arriba", ni el crédito de ilustraciones — eso es exclusivo del
    footer de index.html (partials/public/footer.blade.php).
--}}
<footer style="background:#fff;border-top:1px solid var(--linea);margin-top:auto;">
    <div style="max-width:1240px;margin:0 auto;padding:22px 32px;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;">
        <img loading="lazy" decoding="async" width="400" height="313" src="{{ asset('img/logo-cos-color.png') }}" alt="Comunidad de Organizaciones Solidarias"
             style="height:44px;width:auto;object-fit:contain;">

        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;font-size:13px;color:var(--gris);">
            <a class="textlink" href="{{ url('/privacidad') }}">Política de privacidad</a>
            <span>diadelpatrimoniosocial@comunidad-org.cl</span>
            <span>@diadelpatrimoniosocial</span>
        </div>
    </div>
</footer>
